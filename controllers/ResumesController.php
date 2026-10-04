<?php
declare(strict_types=1);

/**
 * Les résumés que l'IA écrit à partir des documents de l'utilisateur, avec sa propre clé Gemini.
 *
 * Rien ne part chez Google que ce qui a été coché, et seulement quand on appuie sur « Générer » : la page
 * le dit en toutes lettres. Les appels sont longs (de quelques secondes à quelques minutes pour une voix) :
 * la session est libérée pendant qu'on attend, pour ne pas bloquer les autres onglets.
 */
final class ResumesController
{
    private const HISTORIQUE_MAX = 60;

    /** La liste des résumés, et de quoi en demander un. */
    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $documentsParCours = [];
        foreach (Database::all(
            'SELECT id, cours_id, nom_origine, mime, taille, pour_fiche
               FROM fichiers WHERE user_id = ? ORDER BY pour_fiche, created_at',
            [$userId]
        ) as $fichier) {
            $documentsParCours[(int) $fichier['cours_id']][] = $fichier;
        }

        $choisi = entier_ou_null($_GET['cours'] ?? null);
        // Un résumé qui vient d'être écrit : la page l'ouvre aussitôt dans une fenêtre (voir app.js).
        $aOuvrir = null;
        $demande = entier_ou_null($_GET['ouvrir'] ?? null);
        if ($demande !== null) {
            $aOuvrir = Database::one('SELECT id, titre FROM resumes_ia WHERE id = ? AND user_id = ?', [$demande, $userId]);
        }
        Vue::afficher('resumes/index', [
            'aOuvrir'       => $aOuvrir,
            'cleConfiguree' => CleApi::configure(),
            'cleFin'        => CleApi::fin($userId, CleApi::GEMINI),
            'documentsParCours' => $documentsParCours,
            'choisi'        => $choisi,
            'cours'         => Database::all(
                'SELECT c.id, c.titre, m.nom AS matiere_nom,
                        TRIM(COALESCE(c.contenu, \'\')) <> \'\'        AS a_contenu,
                        TRIM(COALESCE(c.fiche_revision, \'\')) <> \'\' AS a_fiche,
                        (SELECT COUNT(*) FROM fichiers f WHERE f.cours_id = c.id) AS nb_fichiers
                 FROM cours c LEFT JOIN matieres m ON m.id = c.matiere_id
                 WHERE c.user_id = ?
                 ORDER BY COALESCE(m.nom, \'￿\'), c.titre',
                [$userId]
            ),
            'historique'    => Database::all(
                'SELECT id, titre, genre, longueur, created_at, audio_nom IS NOT NULL AS a_audio
                   FROM resumes_ia WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . self::HISTORIQUE_MAX,
                [$userId]
            ),
        ], t('titre.resumes'));
    }

    /** Écrit un résumé : lit les documents cochés, les envoie à Gemini, garde le texte rendu. */
    public function generer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $cle = CleApi::lire($userId, CleApi::GEMINI);
        if ($cle === null) {
            Session::flash('erreur', t('ria.fl.pas_de_cle'));
            redirect('resumes');
        }

        $ids = [];
        foreach ((array) ($_POST['cours'] ?? []) as $brut) {
            $id = entier_ou_null($brut);
            // Un cours qui n'est pas le vôtre ne passe pas ; un doublon non plus.
            if ($id !== null && !in_array($id, $ids, true)
                && Database::valeur('SELECT id FROM cours WHERE id = ? AND user_id = ?', [$id, $userId]) !== null) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            Session::flash('erreur', t('ria.fl.un_cours'));
            redirect('resumes');
        }

        $parts = array_values(array_intersect(['cours', 'fiche', 'documents'], (array) ($_POST['sources'] ?? [])));
        if ($parts === []) {
            $parts = ['cours', 'fiche', 'documents'];
        }
        // Un ou plusieurs genres, dans l'ordre de la page ; rien de coché, c'est un résumé.
        $genres = array_values(array_intersect(ResumeIa::GENRES, array_map('strval', (array) ($_POST['genres'] ?? []))));
        if ($genres === []) {
            $genres = ['resume'];
        }
        $longueur = in_array($_POST['longueur'] ?? '', ResumeIa::LONGUEURS, true) ? (string) $_POST['longueur'] : 'moyen';
        // « Audio » coché : chaque résumé écrit est aussi lu à voix haute. Coché seul, il lit un résumé simple.
        $avecAudio = !empty($_POST['audio']);
        $voix = in_array($_POST['voix'] ?? '', Gemini::VOIX, true) ? (string) $_POST['voix'] : Gemini::VOIX[0];

        $lu = ResumeIa::rassembler($userId, $ids, $parts, $this->documentsRetenus($ids));
        foreach ($lu['muets'] as $nom) {
            Session::flash('info', t('ria.fl.muet', ['nom' => $nom]));
        }
        if ($lu['blocs'] === []) {
            Session::flash('erreur', t('ria.fl.rien_a_lire'));
            redirect('resumes');
        }

        $langue = Langue::courante();
        $contenu = ResumeIa::contenu($lu['blocs']);

        /*
         * Un appel par genre, un après l'autre. Une clé refusée ou une limite atteinte arrête tout : les
         * suivants échoueraient de la même façon. Une autre panne n'empêche pas les genres restants.
         */
        /*
         * L'audio s'ajoute après chaque texte, dans la limite d'un budget de temps : au-delà, les voix
         * restantes sont laissées (le texte, lui, est gardé) et on dit comment les demander ensuite.
         */
        $limite = time() + max(0, (int) (Config::get('gemini', 'budget_audio') ?? 270));
        [$ecrits, $echecs] = $this->sansVerrou(static function () use ($cle, $genres, $longueur, $langue, $contenu, $avecAudio, $voix, $limite): array {
            $ecrits = [];
            $echecs = [];
            foreach ($genres as $genre) {
                try {
                    [$texte, $modele] = Gemini::texte($cle, ResumeIa::consigne($genre, $longueur, $langue), $contenu);
                    $son = null;
                    $sonErreur = null;
                    if ($avecAudio) {
                        try {
                            $son = self::sonDe($cle, $texte, $voix, $limite);
                        } catch (GeminiErreur $e) {
                            $sonErreur = $e;
                        }
                    }
                    $ecrits[] = [$genre, $texte, $modele, $son, $sonErreur];
                } catch (GeminiErreur $e) {
                    $echecs[$genre] = $e;
                    if (in_array($e->nature, ['cle', 'quota'], true)) {
                        break;
                    }
                }
            }

            return [$ecrits, $echecs];
        });

        $noms = array_column($lu['sources'], 'cours');
        $ids = [];
        $sons = 0;
        $sonsManques = [];
        foreach ($ecrits as [$genre, $texte, $modele, $son, $sonErreur]) {
            $titre = t('ria.genre.' . $genre) . ' — ' . (count($noms) === 1 ? $noms[0] : tn('ria.n_cours', count($noms)));
            Database::run(
                'INSERT INTO resumes_ia (user_id, titre, genre, longueur, langue, sources, contenu, modele)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$userId, mb_substr($titre, 0, 190), $genre, $longueur, $langue,
                 json_encode($lu['sources'], JSON_UNESCAPED_UNICODE), $texte, $modele]
            );
            $ids[] = Database::dernierId();
            if ($son !== null) {
                $nom = self::ranger($son[0], $son[1]);
                if ($nom !== null) {
                    Database::run('UPDATE resumes_ia SET audio_nom = ?, audio_voix = ? WHERE id = ? AND user_id = ?',
                        [$nom, $voix, end($ids), $userId]);
                    $sons++;
                    continue;
                }
                $sonErreur = new GeminiErreur('écriture', 'ecriture');
            }
            if ($sonErreur !== null) {
                $sonsManques[$genre] = $sonErreur;
            }
        }

        if ($ids !== []) {
            if ($lu['tronque']) {
                Session::flash('info', t('ria.fl.tronque'));
            }
            Session::flash('succes', tn($sons > 0 && $sons === count($ids) ? 'ria.fl.ecrits_audio' : 'ria.fl.ecrits', count($ids)));
        }
        $this->direLesEchecs($echecs, $genres);
        foreach ($sonsManques as $genre => $e) {
            Session::flash($e->nature === 'delai' ? 'info' : 'erreur', t($e->nature === 'delai' ? 'ria.fl.audio_delai' : 'ria.fl.echec_audio', [
                'genre' => mb_strtolower(t('ria.genre.' . $genre)),
                'detail' => $e->nature === 'delai' ? '' : $this->messageDErreur($e),
            ]));
        }
        if ($ids === []) {
            redirect('resumes');
        }
        // Un seul : la liste l'ouvre aussitôt dans une fenêtre. Plusieurs : la liste, où ils sont tous.
        redirect('resumes', count($ids) === 1 ? ['ouvrir' => $ids[0]] : []);
    }

    /** Un résumé : son texte, ses sources, sa voix. */
    public function voir(int $id): void
    {
        Auth::exiger();
        $resume = $this->resume($id, Auth::id());

        $donnees = [
            'resume'  => $resume,
            'sources' => (array) (json_decode((string) $resume['sources'], true) ?? []),
            'cleFin'  => CleApi::fin(Auth::id(), CleApi::GEMINI),
        ];

        // Demandé en fragment (depuis la liste), le résumé s'ouvre dans une fenêtre ; sa voix et son effacement
        // s'y font sans la quitter. Sans script, la page entière répond.
        if (Vue::enFenetre()) {
            Vue::fragment('resumes/voir', $donnees);

            return;
        }

        Vue::afficher('resumes/voir', $donnees, (string) $resume['titre']);
    }

    /** Envoie le fichier son, pour son seul propriétaire. */
    public function audio(int $id): void
    {
        Auth::exiger();
        session_write_close();
        $resume = $this->resume($id, Auth::id());
        if ($resume['audio_nom'] === null) {
            $this->introuvable();
        }
        Fichiers::envoyer([
            'nom_stocke'  => (string) $resume['audio_nom'],
            'nom_origine' => 'resume-' . $id . '.wav',
            'mime'        => 'audio/wav',
        ], ($_GET['telecharger'] ?? '') === '1', self::dossier());
    }

    /** Lit le résumé à voix haute : un fichier son, fait morceau par morceau. */
    public function voix(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $resume = $this->resume($id, $userId);

        $cle = CleApi::lire($userId, CleApi::GEMINI);
        if ($cle === null) {
            Session::flash('erreur', t('ria.fl.pas_de_cle'));
            redirect('resumes/' . $id);
        }
        $voix = in_array($_POST['voix'] ?? '', Gemini::VOIX, true) ? (string) $_POST['voix'] : Gemini::VOIX[0];

        $texte = Markdown::brut((string) $resume['contenu']);
        if (ResumeIa::morceauxDeVoix($texte) === []) {
            Session::flash('erreur', t('ria.fl.rien_a_lire'));
            redirect('resumes/' . $id);
        }

        try {
            [$pcm, $frequence] = $this->sansVerrou(static fn (): array => self::sonDe($cle, (string) $resume['contenu'], $voix, time() + 280));
        } catch (GeminiErreur $e) {
            Session::flash('erreur', $this->messageDErreur($e));
            redirect('resumes/' . $id);
        }

        $nom = self::ranger($pcm, $frequence);
        if ($nom === null) {
            Session::flash('erreur', t('ria.fl.ecriture'));
            redirect('resumes/' . $id);
        }
        // La voix précédente, s'il y en avait une, cède la place.
        $this->effacerLeSon((string) ($resume['audio_nom'] ?? ''));
        Database::run('UPDATE resumes_ia SET audio_nom = ?, audio_voix = ? WHERE id = ? AND user_id = ?',
            [$nom, $voix, $id, $userId]);

        if (mb_strlen($texte) > ResumeIa::VOIX_MAX) {
            Session::flash('info', t('ria.fl.voix_coupee'));
        }
        Session::flash('succes', t('ria.fl.voix_prete'));
        redirect('resumes/' . $id);
    }

    /** Efface un résumé, et sa voix. */
    public function supprimer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $resume = $this->resume($id, Auth::id());
        $this->effacerLeSon((string) ($resume['audio_nom'] ?? ''));
        Database::run('DELETE FROM resumes_ia WHERE id = ? AND user_id = ?', [$id, Auth::id()]);
        Session::flash('info', t('ria.fl.efface'));
        redirect('resumes');
    }

    /**
     * Lit un texte (Markdown) à voix haute, morceau par morceau.
     *
     * @return array{0: string, 1: int}  le son brut (PCM 16 bits mono) et sa fréquence
     * @throws GeminiErreur  « delai » si la limite de temps est passée avant la fin : un son coupé en route
     *                       serait pire que pas de son
     */
    private static function sonDe(string $cle, string $markdown, string $voix, int $limite): array
    {
        $morceaux = ResumeIa::morceauxDeVoix(Markdown::brut($markdown));
        if ($morceaux === []) {
            throw new GeminiErreur('Rien à lire.', 'vide');
        }
        $son = '';
        $frequence = 24000;
        foreach ($morceaux as $morceau) {
            if (time() >= $limite) {
                throw new GeminiErreur('Délai dépassé.', 'delai');
            }
            [$partie, $frequence] = Gemini::voix($cle, $morceau, $voix);
            $son .= $partie;
        }

        return [$son, $frequence];
    }

    /** Range un son dans un fichier WAV : son nom, ou null si l'écriture échoue. */
    private static function ranger(string $pcm, int $frequence): ?string
    {
        $dossier = self::dossier();
        if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            return null;
        }
        $nom = bin2hex(random_bytes(12)) . '.wav';

        return file_put_contents($dossier . DIRECTORY_SEPARATOR . $nom, Gemini::wav($pcm, $frequence)) === false ? null : $nom;
    }

    /** Où rangent les fichiers son. */
    public static function dossier(): string
    {
        return dirname((string) Config::get('app', 'dossier_uploads')) . DIRECTORY_SEPARATOR . 'resumes';
    }

    // --- Dedans ---------------------------------------------------------------

    /**
     * Les documents que l'utilisateur a retenus, cours par cours, quand il a eu la liste (même logique que
     * les cartes : le champ « documents_de » dit de quels cours viennent les listes ouvertes).
     *
     * @param list<int> $ids
     * @return array<int, ?list<int>>  null : aucune liste soumise pour ce cours, on prend tous ses documents
     */
    private function documentsRetenus(array $ids): array
    {
        $listes = [];
        foreach ((array) ($_POST['documents_de'] ?? []) as $brut) {
            $listes[] = entier_ou_null($brut);
        }
        $retenus = [];
        foreach ((array) ($_POST['documents'] ?? []) as $brut) {
            $doc = entier_ou_null($brut);
            if ($doc !== null) {
                $retenus[] = $doc;
            }
        }

        $parCours = [];
        foreach ($ids as $id) {
            $parCours[$id] = in_array($id, $listes, true) ? $retenus : null;
        }

        return $parCours;
    }

    /** Fait quelque chose de long sans tenir la session : les autres onglets restent libres. */
    private function sansVerrou(callable $travail): mixed
    {
        @set_time_limit(600);
        session_write_close();
        try {
            return $travail();
        } finally {
            // Rouverte pour dire le résultat (message, redirection) : le jeton CSRF et les messages y vivent.
            Session::demarrer();
        }
    }

    /**
     * Dit ce qui n'a pas pu être écrit. Une clé refusée ou une limite atteinte ne se dit qu'une fois, telle
     * quelle : elle vaut pour tous les genres. Une autre panne se dit genre par genre quand on en a demandé
     * plusieurs — sinon on ne saurait pas lequel manque.
     *
     * @param array<string, GeminiErreur> $echecs
     * @param list<string> $genres  ceux qui ont été demandés
     */
    private function direLesEchecs(array $echecs, array $genres): void
    {
        foreach ($echecs as $genre => $e) {
            if (count($genres) === 1 || in_array($e->nature, ['cle', 'quota'], true)) {
                Session::flash('erreur', $this->messageDErreur($e));
                continue;
            }
            Session::flash('erreur', t('ria.fl.echec_genre', [
                'genre' => mb_strtolower(t('ria.genre.' . $genre)), 'detail' => $this->messageDErreur($e),
            ]));
        }
    }

    private function messageDErreur(GeminiErreur $e): string
    {
        return match ($e->nature) {
            'quota'   => t('ria.err.quota'),
            'cle'     => t('ria.err.cle'),
            'modele'  => t('ria.err.modele', ['detail' => $e->getMessage()]),
            'service' => t('ria.err.service'),
            'reseau'  => t('ria.err.reseau', ['detail' => $e->getMessage()]),
            'refus'   => t('ria.err.refus'),
            'vide'    => t('ria.err.vide'),
            'delai'   => t('ria.err.delai'),
            'ecriture' => t('ria.fl.ecriture'),
            default   => t('ria.err.autre', ['detail' => $e->getMessage()]),
        };
    }

    private function effacerLeSon(string $nom): void
    {
        if ($nom !== '') {
            @unlink(self::dossier() . DIRECTORY_SEPARATOR . basename($nom));
        }
    }

    /** Un résumé de l'utilisateur, ou une page introuvable. */
    private function resume(int $id, int $userId): array
    {
        $resume = Database::one('SELECT * FROM resumes_ia WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($resume === null) {
            $this->introuvable();
        }

        return $resume;
    }

    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], t('titre.introuvable'));
        exit;
    }
}
