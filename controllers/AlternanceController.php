<?php
declare(strict_types=1);

/**
 * L'espace alternance : notes, rythme, journal des missions, documents.
 * Chaque page porte en tête où l'on en est — à l'école ou en entreprise.
 */
final class AlternanceController
{
    // --- La fiche de l'alternance ----------------------------------------------

    public function entreprise(): void
    {
        Auth::exiger();
        $userId = Auth::id();
        $contrat = Alternance::contrat($userId);
        $this->afficher('alternance/entreprise', [
            'contrat'    => $contrat,
            'avancement' => Alternance::avancementContrat($contrat),
            'auCalendrier' => $this->echeancesPosees($userId, $contrat),
        ], 'Mon alternance', 'entreprise');
    }

    public function enregistrerEntreprise(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $texte = static fn (string $champ, int $taille): ?string => mb_substr(trim(post($champ)), 0, $taille) ?: null;
        $date = static fn (string $champ): ?string => Alternance::dateValide(post($champ));

        $debut = $date('debut');
        $fin = $date('fin');
        if ($debut !== null && $fin !== null && $fin < $debut) {
            Session::flash('erreur', 'La fin du contrat ne peut pas précéder son début.');
            redirect('alternance/entreprise');
        }
        $courriel = $texte('tuteur_email', 190);
        if ($courriel !== null && !filter_var($courriel, FILTER_VALIDATE_EMAIL)) {
            Session::flash('erreur', 'L’adresse électronique du tuteur ne ressemble pas à une adresse.');
            redirect('alternance/entreprise');
        }

        $valeurs = [
            'entreprise'     => $texte('entreprise', 150),
            'adresse'        => $texte('adresse', 255),
            'poste'          => $texte('poste', 150),
            'tuteur'         => $texte('tuteur', 120),
            'tuteur_email'   => $courriel,
            'tuteur_tel'     => $texte('tuteur_tel', 40),
            'referent'       => $texte('referent', 120),
            'debut'          => $debut,
            'fin'            => $fin,
            'remise_rapport' => $date('remise_rapport'),
            'soutenance'     => $date('soutenance'),
        ];
        $colonnes = array_keys($valeurs);
        Database::run(
            'INSERT INTO alternance_contrat (user_id, `' . implode('`, `', $colonnes) . '`)
             VALUES (?' . str_repeat(', ?', count($colonnes)) . ')
             ON DUPLICATE KEY UPDATE '
             . implode(', ', array_map(static fn (string $c): string => "`$c` = VALUES(`$c`)", $colonnes)),
            array_merge([$userId], array_values($valeurs))
        );

        Session::flash('succes', 'Fiche enregistrée.');
        redirect('alternance/entreprise');
    }

    /**
     * Les dates du contrat posées au calendrier, en journées entières : on les
     * retrouve là où l'on regarde les autres, avec leurs rappels. Celles qui y
     * sont déjà ne sont pas reposées.
     */
    public function poserEcheances(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $contrat = Alternance::contrat($userId);

        $posees = 0;
        foreach (Alternance::ECHEANCES as $champ => $echeance) {
            $jour = (string) ($contrat[$champ] ?? '');
            if ($jour === '' || $this->echeancePosee($userId, $echeance['titre'], $jour)) {
                continue;
            }
            Database::run(
                'INSERT INTO evenements (user_id, titre, debut, fin, journee_entiere, lieu)
                 VALUES (?, ?, ?, ?, 1, ?)',
                [$userId, $echeance['titre'], $jour . ' 00:00:00', $jour . ' 23:59:59',
                 mb_substr((string) $contrat['entreprise'], 0, 150) ?: null]);
            $posees++;
        }

        Session::flash($posees === 0 ? 'erreur' : 'succes', match (true) {
            $posees === 0 => 'Rien à poser : vos dates sont déjà au calendrier, ou vous n’en avez pas encore donné.',
            $posees === 1 => 'Une date posée au calendrier.',
            default       => $posees . ' dates posées au calendrier.',
        });
        redirect('alternance/entreprise');
    }

    /** @return array<string, bool> quelles dates du contrat sont déjà au calendrier */
    private function echeancesPosees(int $userId, array $contrat): array
    {
        $posees = [];
        foreach (Alternance::ECHEANCES as $champ => $echeance) {
            $jour = (string) ($contrat[$champ] ?? '');
            $posees[$champ] = $jour !== '' && $this->echeancePosee($userId, $echeance['titre'], $jour);
        }

        return $posees;
    }

    private function echeancePosee(int $userId, string $titre, string $jour): bool
    {
        return Database::valeur(
            'SELECT id FROM evenements WHERE user_id = ? AND titre = ? AND DATE(debut) = ?',
            [$userId, $titre, $jour]) !== null;
    }

    // --- Les notes -------------------------------------------------------------

    public function notes(): void
    {
        Auth::exiger();
        $this->afficher('alternance/notes', [
            'notes' => Database::all(
                'SELECT id, titre, contenu, updated_at FROM alternance_notes
                 WHERE user_id = ? ORDER BY updated_at DESC, id DESC', [Auth::id()]),
        ], 'Alternance', 'notes');
    }

    /** La note à écrire, ou à relire et modifier. */
    public function note(?int $id = null): void
    {
        Auth::exiger();
        $note = null;
        if ($id !== null) {
            $note = Database::one('SELECT * FROM alternance_notes WHERE id = ? AND user_id = ?', [$id, Auth::id()]);
            if ($note === null) {
                $this->introuvable();
            }
        }
        // « + Nouvelle note » l'ouvre dans une fenêtre, par-dessus la liste.
        if (Vue::enFenetre()) {
            Vue::fragment('alternance/note', ['note' => $note]);
            return;
        }
        $this->afficher('alternance/note', ['note' => $note],
            $note === null ? 'Nouvelle note' : (string) $note['titre'], 'notes');
    }

    public function creerNote(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        [$titre, $contenu] = $this->lireNote();
        if ($titre === '') {
            Session::flash('erreur', 'Donnez un titre à la note.');
            redirect('alternance/notes/nouvelle');
        }
        Database::run('INSERT INTO alternance_notes (user_id, titre, contenu) VALUES (?, ?, ?)',
            [Auth::id(), $titre, $contenu]);
        Session::flash('succes', 'Note « ' . $titre . ' » enregistrée.');
        // Écrite dans la fenêtre : on retrouve la liste, où elle vient d'arriver.
        if (($_POST['fenetre'] ?? '') === '1') {
            redirect('alternance');
        }
        redirect('alternance/notes/' . Database::dernierId());
    }

    public function modifierNote(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $this->exigerA('alternance_notes', $id);
        [$titre, $contenu] = $this->lireNote();
        if ($titre === '') {
            Session::flash('erreur', 'Donnez un titre à la note.');
            redirect('alternance/notes/' . $id);
        }
        Database::run('UPDATE alternance_notes SET titre = ?, contenu = ? WHERE id = ? AND user_id = ?',
            [$titre, $contenu, $id, Auth::id()]);
        Session::flash('succes', 'Note enregistrée.');
        redirect('alternance/notes/' . $id);
    }

    public function supprimerNote(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $this->exigerA('alternance_notes', $id);
        Database::run('DELETE FROM alternance_notes WHERE id = ? AND user_id = ?', [$id, Auth::id()]);
        Session::flash('succes', 'Note supprimée.');
        redirect('alternance');
    }

    /** @return array{string, ?string} */
    private function lireNote(): array
    {
        return [
            mb_substr(trim(post('titre')), 0, 200),
            TexteRiche::depuisFormulaire(post('contenu')) ?: null,
        ];
    }

    // --- Le rythme -------------------------------------------------------------

    public function rythme(): void
    {
        Auth::exiger();
        $userId = Auth::id();
        $this->afficher('alternance/rythme', [
            'periodes' => Alternance::periodes($userId),
            'bilan'    => Alternance::bilan($userId),
        ], 'Rythme d’alternance', 'rythme');
    }

    public function poserPeriode(): void
    {
        $this->enregistrerPeriode(null);
    }

    public function modifierPeriode(int $id): void
    {
        $this->enregistrerPeriode($id);
    }

    private function enregistrerPeriode(?int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        if ($id !== null) {
            $this->exigerA('alternance_periodes', $id);
        }

        $lieu = post('lieu');
        $debut = Alternance::dateValide(post('debut'));
        $fin = Alternance::dateValide(post('fin', post('debut'))) ?? $debut;
        $note = mb_substr(trim(post('note')), 0, 200) ?: null;

        $erreur = match (true) {
            !isset(Alternance::LIEUX[$lieu]) => 'Choisissez École ou Entreprise.',
            $debut === null                  => 'La date de début est invalide.',
            $fin < $debut                    => 'La fin ne peut pas précéder le début.',
            (new DateTimeImmutable($debut))->diff(new DateTimeImmutable($fin))->days >= Alternance::JOURS_MAX
                                             => 'Une période ne dépasse pas trois ans.',
            default                          => null,
        };
        if ($erreur !== null) {
            Session::flash('erreur', $erreur);
            redirect('alternance/rythme');
        }

        $avant = (int) Database::valeur('SELECT COUNT(*) FROM alternance_periodes WHERE user_id = ? AND id <> ? AND debut <= ? AND fin >= ?',
            [$userId, $id ?? 0, $fin, $debut]);
        Alternance::poserPeriode($userId, $lieu, $debut, $fin, $note, $id);

        $texte = ucfirst(Alternance::LIEUX[$lieu]['dans']) . ' '
            . ($debut === $fin ? 'le ' . Alternance::jourCourt($debut)
                : 'du ' . Alternance::jourCourt($debut) . ' au ' . Alternance::jourCourt($fin));
        // « 23 oct. » porte déjà son point.
        $texte = rtrim($texte, '.') . '.';
        if ($avant > 0) {
            $texte .= ' Les jours déjà posés sur ces dates ont été remplacés.';
        }
        Session::flash('succes', $texte);
        redirect('alternance/rythme');
    }

    public function supprimerPeriode(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $this->exigerA('alternance_periodes', $id);
        Database::run('DELETE FROM alternance_periodes WHERE id = ? AND user_id = ?', [$id, Auth::id()]);
        Session::flash('succes', 'Période retirée.');
        redirect('alternance/rythme');
    }

    // --- Le journal ------------------------------------------------------------

    public function journal(): void
    {
        Auth::exiger();
        $userId = Auth::id();
        $this->afficher('alternance/journal', [
            'pages'    => Database::all(
                'SELECT * FROM alternance_journal WHERE user_id = ? ORDER BY semaine DESC', [$userId]),
            'aEcrire'  => Alternance::semainesAEcrire($userId),
            'cetteSemaine' => Alternance::lundi(date('Y-m-d')),
        ], 'Journal des missions', 'journal');
    }

    /** Ce qu'on a travaillé, toutes semaines confondues. */
    public function competences(): void
    {
        Auth::exiger();
        $userId = Auth::id();
        $this->afficher('alternance/competences', [
            'competences' => Alternance::bilanCompetences($userId),
            'semaines'    => (int) Database::valeur(
                'SELECT COUNT(*) FROM alternance_journal WHERE user_id = ?', [$userId]),
        ], 'Compétences travaillées', 'journal');
    }

    /** La page d'une semaine : celle qui existe, sinon une page blanche. */
    public function pageJournal(): void
    {
        Auth::exiger();
        $userId = Auth::id();
        $semaine = Alternance::lundi(Alternance::dateValide((string) ($_GET['semaine'] ?? '')) ?? date('Y-m-d'));
        $page = Database::one('SELECT * FROM alternance_journal WHERE user_id = ? AND semaine = ?', [$userId, $semaine]);

        $fin = (new DateTimeImmutable($semaine))->modify('+6 days');
        $donnees = [
            'semaine' => $semaine,
            'page'    => $page,
            'lieux'   => Alternance::lieuxEntre($userId, new DateTimeImmutable($semaine), $fin),
        ];
        // Écrire sa semaine se fait dans une fenêtre, par-dessus le journal.
        if (Vue::enFenetre()) {
            Vue::fragment('alternance/page_journal', $donnees);
            return;
        }
        $this->afficher('alternance/page_journal', $donnees,
            'Semaine du ' . Alternance::jourCourt($semaine), 'journal');
    }

    public function ecrireJournal(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $date = Alternance::dateValide(post('semaine'));
        if ($date === null) {
            Session::flash('erreur', 'Choisissez la semaine.');
            redirect('alternance/journal');
        }
        $semaine = Alternance::lundi($date);
        $id = entier_ou_null($_POST['id'] ?? null);
        if ($id !== null) {
            $this->exigerA('alternance_journal', $id);
        }
        $missions = TexteRiche::depuisFormulaire(post('missions')) ?: null;
        $competences = implode(', ', Alternance::competences(post('competences')));
        $competences = mb_substr($competences, 0, 500) ?: null;
        if ($missions === null && $competences === null) {
            Session::flash('erreur', 'Écrivez au moins une mission ou une compétence.');
            redirect('alternance/journal/semaine', ['semaine' => $semaine]);
        }

        $deja = entier_ou_null(Database::valeur(
            'SELECT id FROM alternance_journal WHERE user_id = ? AND semaine = ?', [$userId, $semaine]));
        if ($deja !== null && $deja !== $id) {
            // Une page écrite ailleurs entre-temps : on ne l'écrase pas en silence.
            Session::flash('erreur', 'La semaine du ' . Alternance::jourCourt($semaine)
                . ' a déjà sa page : la voici. Complétez-la plutôt.');
            redirect('alternance/journal/semaine', ['semaine' => $semaine]);
        }

        if ($id === null) {
            Database::run(
                'INSERT INTO alternance_journal (user_id, semaine, missions, competences) VALUES (?, ?, ?, ?)',
                [$userId, $semaine, $missions, $competences]);
        } else {
            Database::run(
                'UPDATE alternance_journal SET semaine = ?, missions = ?, competences = ? WHERE id = ? AND user_id = ?',
                [$semaine, $missions, $competences, $id, $userId]);
        }
        Session::flash('succes', 'Semaine du ' . Alternance::jourCourt($semaine) . ' enregistrée.');
        redirect('alternance/journal');
    }

    /**
     * Le journal en PDF, sur la période demandée : ce qu'on recopie dans le
     * livret, ou qu'on joint au rapport. Les bornes sont ramenées à leur
     * semaine, pour ne pas couper une semaine en deux.
     */
    public function pdfJournal(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $du = Alternance::dateValide((string) ($_GET['du'] ?? ''));
        $au = Alternance::dateValide((string) ($_GET['au'] ?? ''));
        $conditions = '';
        $valeurs = [$userId];
        if ($du !== null) {
            $conditions .= ' AND semaine >= ?';
            $valeurs[] = Alternance::lundi($du);
        }
        if ($au !== null) {
            $conditions .= ' AND semaine <= ?';
            $valeurs[] = Alternance::lundi($au);
        }
        $pages = Database::all(
            'SELECT * FROM alternance_journal WHERE user_id = ?' . $conditions . ' ORDER BY semaine', $valeurs);
        if ($pages === []) {
            Session::flash('erreur', $du === null && $au === null
                ? 'Le journal est vide : écrivez une semaine avant de l’exporter.'
                : 'Aucune semaine écrite sur cette période.');
            redirect('alternance/journal');
        }

        // Les jours passés en entreprise, pour situer chaque semaine.
        $lieux = Alternance::lieuxEntre($userId, new DateTimeImmutable((string) $pages[0]['semaine']),
            (new DateTimeImmutable((string) $pages[count($pages) - 1]['semaine']))->modify('+6 days'));

        $semaines = [];
        foreach ($pages as $page) {
            $lundi = (string) $page['semaine'];
            $vendredi = (new DateTimeImmutable($lundi))->modify('+4 days')->format('Y-m-d');
            $jours = 0;
            foreach ($lieux as $jour => $lieu) {
                if ($jour >= $lundi && $jour <= $vendredi && $lieu['lieu'] === 'entreprise') {
                    $jours++;
                }
            }
            $semaines[] = [
                'titre'       => 'Semaine du ' . date_fr($lundi . ' 00:00:00', false)
                                 . ' au ' . date_fr($vendredi . ' 00:00:00', false),
                'sous_titre'  => $jours === 0 ? '' : $jours . ' jour' . ($jours > 1 ? 's' : '') . ' en entreprise',
                'missions'    => $page['missions'],
                'competences' => Alternance::competences($page['competences']),
            ];
        }

        // L'en-tête porte l'entreprise et le poste, quand la fiche les donne :
        // le PDF part souvent seul, sans rien pour dire de qui il parle.
        $contrat = Alternance::contrat($userId);
        $qui = array_filter([trim((string) $contrat['entreprise']), trim((string) $contrat['poste'])], 'strlen');

        $periode = ($qui === [] ? '' : implode(' · ', $qui) . ' — ')
            . 'Du ' . date_fr((string) $pages[0]['semaine'] . ' 00:00:00', false) . ' au '
            . date_fr((new DateTimeImmutable((string) $pages[count($pages) - 1]['semaine']))->modify('+4 days')->format('Y-m-d') . ' 00:00:00', false)
            . ' · ' . count($semaines) . ' semaine' . (count($semaines) > 1 ? 's' : '');

        try {
            $pdf = ExportPdf::depuisJournal($semaines, $periode);
        } catch (Throwable) {
            Session::flash('erreur', 'Le journal n’a pas pu être mis en PDF.');
            redirect('alternance/journal');
        }

        $nom = 'Journal des missions.pdf';
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdf));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        header(sprintf(
            "Content-Disposition: attachment; filename=\"%s\"; filename*=UTF-8''%s",
            'journal-des-missions.pdf', rawurlencode($nom)
        ));
        echo $pdf;
        exit;
    }

    public function supprimerJournal(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $this->exigerA('alternance_journal', $id);
        Database::run('DELETE FROM alternance_journal WHERE id = ? AND user_id = ?', [$id, Auth::id()]);
        Session::flash('succes', 'Page du journal supprimée.');
        redirect('alternance/journal');
    }

    // --- Les documents ---------------------------------------------------------

    public function documents(): void
    {
        Auth::exiger();
        $parCategorie = array_fill_keys(array_keys(Alternance::CATEGORIES), []);
        foreach (Database::all(
            'SELECT * FROM alternance_documents WHERE user_id = ? ORDER BY created_at DESC, id DESC',
            [Auth::id()]) as $doc) {
            $parCategorie[$doc['categorie']][] = $doc;
        }
        $this->afficher('alternance/documents', ['parCategorie' => $parCategorie],
            'Documents d’alternance', 'documents');
    }

    public function deposer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $categorie = post('categorie');
        if (!isset(Alternance::CATEGORIES[$categorie])) {
            $categorie = 'autre';
        }
        $fichiers = $_FILES['fichiers'] ?? [];
        $avant = (int) Database::valeur('SELECT COUNT(*) FROM alternance_documents WHERE user_id = ?', [Auth::id()]);
        $erreurs = Alternance::deposer($fichiers, Auth::id(), $categorie);
        $recus = (int) Database::valeur('SELECT COUNT(*) FROM alternance_documents WHERE user_id = ?', [Auth::id()]) - $avant;

        if ($recus > 0) {
            Session::flash('succes', $recus . ' document' . ($recus > 1 ? 's rangés' : ' rangé')
                . ' dans « ' . Alternance::CATEGORIES[$categorie]['nom'] . ' ».');
        }
        if ($erreurs !== []) {
            Session::flash('erreur', implode(' ', $erreurs));
        } elseif ($recus === 0) {
            Session::flash('erreur', 'Choisissez au moins un fichier.');
        }
        redirect('alternance/documents');
    }

    public function document(int $id): void
    {
        Auth::exiger();
        $doc = Database::one('SELECT * FROM alternance_documents WHERE id = ? AND user_id = ?', [$id, Auth::id()]);
        if ($doc === null) {
            $this->introuvable();
        }
        Fichiers::envoyer($doc, isset($_GET['telecharger']), Alternance::dossier());
    }

    public function rangerDocument(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $this->exigerA('alternance_documents', $id);
        $categorie = post('categorie');
        if (isset(Alternance::CATEGORIES[$categorie])) {
            Database::run('UPDATE alternance_documents SET categorie = ? WHERE id = ? AND user_id = ?',
                [$categorie, $id, Auth::id()]);
            Session::flash('succes', 'Rangé dans « ' . Alternance::CATEGORIES[$categorie]['nom'] . ' ».');
        }
        redirect('alternance/documents');
    }

    public function supprimerDocument(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $nom = Alternance::supprimerDocument(Auth::id(), $id);
        if ($nom === null) {
            $this->introuvable();
        }
        Session::flash('succes', '« ' . $nom . ' » supprimé.');
        redirect('alternance/documents');
    }

    // --- Commun ----------------------------------------------------------------

    /** Chaque page de l'espace, avec ses onglets et où l'on en est. */
    private function afficher(string $vue, array $donnees, string $titre, string $onglet): void
    {
        Vue::afficher($vue, $donnees + [
            'onglet'    => $onglet,
            'situation' => Alternance::situation(Auth::id()),
        ], $titre);
    }

    /** La ligne existe et elle est à nous, sinon 404. */
    private function exigerA(string $table, int $id): void
    {
        if (Database::valeur("SELECT id FROM `$table` WHERE id = ? AND user_id = ?", [$id, Auth::id()]) === null) {
            $this->introuvable();
        }
    }

    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], 'Introuvable');
        exit;
    }
}
