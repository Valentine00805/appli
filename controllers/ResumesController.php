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
        Vue::afficher('resumes/index', [
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
        $genre = in_array($_POST['genre'] ?? '', ResumeIa::GENRES, true) ? (string) $_POST['genre'] : 'resume';
        $longueur = in_array($_POST['longueur'] ?? '', ResumeIa::LONGUEURS, true) ? (string) $_POST['longueur'] : 'moyen';

        $lu = ResumeIa::rassembler($userId, $ids, $parts, $this->documentsRetenus($ids));
        foreach ($lu['muets'] as $nom) {
            Session::flash('info', t('ria.fl.muet', ['nom' => $nom]));
        }
        if ($lu['blocs'] === []) {
            Session::flash('erreur', t('ria.fl.rien_a_lire'));
            redirect('resumes');
        }

        $langue = Langue::courante();
        $consigne = ResumeIa::consigne($genre, $longueur, $langue);
        $contenu = ResumeIa::contenu($lu['blocs']);

        try {
            [$texte, $modele] = $this->sansVerrou(static fn (): array => Gemini::texte($cle, $consigne, $contenu));
        } catch (GeminiErreur $e) {
            Session::flash('erreur', $this->messageDErreur($e));
            redirect('resumes');
        }

        $noms = array_column($lu['sources'], 'cours');
        $titre = t('ria.genre.' . $genre) . ' — ' . (count($noms) === 1 ? $noms[0] : tn('ria.n_cours', count($noms)));
        Database::run(
            'INSERT INTO resumes_ia (user_id, titre, genre, longueur, langue, sources, contenu, modele)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, mb_substr($titre, 0, 190), $genre, $longueur, $langue,
             json_encode($lu['sources'], JSON_UNESCAPED_UNICODE), $texte, $modele]
        );
        $id = (int) Database::dernierId();

        if ($lu['tronque']) {
            Session::flash('info', t('ria.fl.tronque'));
        }
        Session::flash('succes', t('ria.fl.ecrit'));
        redirect('resumes/' . $id);
    }

    /** Un résumé : son texte, ses sources, sa voix. */
    public function voir(int $id): void
    {
        Auth::exiger();
        $resume = $this->resume($id, Auth::id());

        Vue::afficher('resumes/voir', [
            'resume'  => $resume,
            'sources' => (array) (json_decode((string) $resume['sources'], true) ?? []),
            'cleFin'  => CleApi::fin(Auth::id(), CleApi::GEMINI),
        ], (string) $resume['titre']);
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
        $morceaux = ResumeIa::morceauxDeVoix($texte);
        if ($morceaux === []) {
            Session::flash('erreur', t('ria.fl.rien_a_lire'));
            redirect('resumes/' . $id);
        }

        try {
            [$pcm, $frequence] = $this->sansVerrou(static function () use ($cle, $morceaux, $voix): array {
                $son = '';
                $frequence = 24000;
                foreach ($morceaux as $morceau) {
                    [$partie, $frequence] = Gemini::voix($cle, $morceau, $voix);
                    $son .= $partie;
                }

                return [$son, $frequence];
            });
        } catch (GeminiErreur $e) {
            Session::flash('erreur', $this->messageDErreur($e));
            redirect('resumes/' . $id);
        }

        $dossier = self::dossier();
        if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            Session::flash('erreur', t('ria.fl.ecriture'));
            redirect('resumes/' . $id);
        }
        $nom = bin2hex(random_bytes(12)) . '.wav';
        if (file_put_contents($dossier . DIRECTORY_SEPARATOR . $nom, Gemini::wav($pcm, $frequence)) === false) {
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
        @set_time_limit(300);
        session_write_close();
        try {
            return $travail();
        } finally {
            // Rouverte pour dire le résultat (message, redirection) : le jeton CSRF et les messages y vivent.
            Session::demarrer();
        }
    }

    private function messageDErreur(GeminiErreur $e): string
    {
        return match ($e->nature) {
            'quota'   => t('ria.err.quota'),
            'cle'     => t('ria.err.cle'),
            'modele'  => t('ria.err.modele', ['detail' => $e->getMessage()]),
            'service' => t('ria.err.service'),
            'reseau'  => t('ria.err.reseau'),
            'refus'   => t('ria.err.refus'),
            'vide'    => t('ria.err.vide'),
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
