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
            'taches'     => Alternance::tachesAFaire($userId),
        ], t('titre.alt_entreprise'), 'entreprise');
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
            Session::flash('erreur', t('alt.msg.fin_avant_debut'));
            redirect('alternance/entreprise');
        }
        $courriel = $texte('tuteur_email', 190);
        if ($courriel !== null && !filter_var($courriel, FILTER_VALIDATE_EMAIL)) {
            Session::flash('erreur', t('alt.msg.courriel_invalide'));
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

        Session::flash('succes', t('alt.msg.fiche_enregistree'));
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
        foreach (Alternance::echeances() as $champ => $echeance) {
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

        Session::flash($posees === 0 ? 'erreur' : 'succes',
            $posees === 0 ? t('alt.msg.rien_a_poser') : tn('alt.msg.dates_posees', $posees));
        redirect('alternance/entreprise');
    }

    /**
     * Le rétroplanning du rapport et de la soutenance, en tâches datées : on
     * sait alors quoi faire ce mois-ci, plutôt que de découvrir l'échéance
     * trois jours avant.
     */
    public function retroplanning(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $contrat = Alternance::contrat($userId);

        $posees = 0;
        $depassees = 0;
        foreach (array_keys(Alternance::JALONS) as $quoi) {
            $jour = (string) ($contrat[$quoi] ?? '');
            if ($jour === '') {
                continue;
            }
            $jalons = Alternance::jalonsAvant($jour, $quoi);
            $depassees += count(Alternance::JALONS[$quoi]) - count($jalons);
            foreach ($jalons as $jalon) {
                $bilan = Alternance::poserTaches($userId, [$jalon['titre']], $jalon['echeance']);
                $posees += $bilan['ajoutees'];
            }
        }

        Session::flash($posees === 0 ? 'erreur' : 'succes', match (true) {
            $posees === 0 && $depassees > 0 => t('alt.msg.etapes_passees'),
            $posees === 0 => t('alt.msg.donnez_date'),
            default => tn('alt.msg.etapes_posees', $posees, ['liste' => Alternance::LISTE])
                . ($depassees > 0 ? tn('alt.msg.deja_passees', $depassees) : t('alt.msg.point')),
        });
        redirect('alternance/entreprise');
    }

    /** @return array<string, bool> quelles dates du contrat sont déjà au calendrier */
    private function echeancesPosees(int $userId, array $contrat): array
    {
        $posees = [];
        foreach (Alternance::echeances() as $champ => $echeance) {
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
        // Les épinglées d'abord : ce qu'on relit souvent reste en haut.
        $recherche = trim((string) ($_GET['q'] ?? ''));
        $etiquette = trim((string) ($_GET['etiquette'] ?? ''));
        $ou = '';
        $valeurs = [Auth::id()];
        if ($recherche !== '') {
            $ou .= ' AND (titre LIKE ? OR contenu LIKE ? OR etiquettes LIKE ?)';
            $valeurs[] = '%' . $recherche . '%';
            $valeurs[] = '%' . $recherche . '%';
            $valeurs[] = '%' . $recherche . '%';
        }
        if ($etiquette !== '') {
            $ou .= ' AND etiquettes LIKE ?';
            $valeurs[] = '%' . $etiquette . '%';
        }

        $this->afficher('alternance/notes', [
            'notes' => Database::all(
                'SELECT id, titre, contenu, epinglee, etiquettes, updated_at FROM alternance_notes
                 WHERE user_id = ?' . $ou . ' ORDER BY epinglee DESC, updated_at DESC, id DESC', $valeurs),
            'recherche' => $recherche,
            'etiquette' => $etiquette,
            'etiquettes' => Alternance::etiquettesConnues(Auth::id()),
            'combien'   => (int) Database::valeur(
                'SELECT COUNT(*) FROM alternance_notes WHERE user_id = ?', [Auth::id()]),
        ], t('titre.alt_notes'), 'notes');
    }

    /**
     * Les cases à cocher d'une note deviennent des tâches, rangées dans la
     * liste « Alternance ». Ce qu'on a décidé en réunion ne reste pas au fond
     * d'une note.
     */
    public function tachesDepuisNote(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $note = Database::one('SELECT titre, contenu FROM alternance_notes WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($note === null) {
            $this->introuvable();
        }

        $voulues = $_POST['aFaire'] ?? [];
        $titres = is_array($voulues)
            ? array_values(array_filter(array_map('strval', $voulues), static fn (string $t): bool => trim($t) !== ''))
            : [];
        // Rien de coché : on prend tout ce que la note laisse à faire.
        if ($titres === []) {
            $titres = Alternance::aFaireDans((string) $note['contenu']);
        }
        if ($titres === []) {
            Session::flash('erreur', t('alt.msg.pas_de_case'));
            redirect('alternance/notes/' . $id);
        }

        $bilan = Alternance::poserTaches($userId, $titres, Alternance::dateValide(post('echeance')));
        Session::flash($bilan['ajoutees'] === 0 ? 'erreur' : 'succes',
            $bilan['ajoutees'] === 0
                ? t('alt.msg.taches_deja', ['liste' => Alternance::LISTE])
                : tn('alt.msg.taches_ajoutees', $bilan['ajoutees'], ['liste' => Alternance::LISTE])
                    . ($bilan['connues'] > 0 ? tn('alt.msg.y_etaient_deja', $bilan['connues']) : t('alt.msg.point')));
        redirect('alternance/notes/' . $id);
    }

    /** Épingler une note, ou la décrocher. */
    public function epinglerNote(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $this->exigerA('alternance_notes', $id);
        $epinglee = (int) Database::valeur('SELECT epinglee FROM alternance_notes WHERE id = ?', [$id]) === 1;
        // Épingler ne change pas la note : sa date de modification ne bouge pas.
        Database::run('UPDATE alternance_notes SET epinglee = ?, updated_at = updated_at WHERE id = ? AND user_id = ?',
            [$epinglee ? 0 : 1, $id, Auth::id()]);
        Session::flash('succes', t($epinglee ? 'alt.msg.note_decrochee' : 'alt.msg.note_epinglee'));
        repartir_vers('alternance');
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
        // Les cases à cocher écrites dans la note : à en faire des tâches.
        $aFaire = $note === null ? [] : Alternance::aFaireDans((string) $note['contenu']);

        // « + Nouvelle note » l'ouvre dans une fenêtre, par-dessus la liste.
        if (Vue::enFenetre()) {
            Vue::fragment('alternance/note', ['note' => $note, 'aFaire' => $aFaire]);
            return;
        }
        $this->afficher('alternance/note', ['note' => $note, 'aFaire' => $aFaire],
            $note === null ? t('titre.alt_note_nouvelle') : (string) $note['titre'], 'notes');
    }

    public function creerNote(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        [$titre, $contenu, $etiquettes] = $this->lireNote();
        if ($titre === '') {
            Session::flash('erreur', t('alt.msg.titre_manquant'));
            redirect('alternance/notes/nouvelle');
        }
        Database::run('INSERT INTO alternance_notes (user_id, titre, contenu, etiquettes) VALUES (?, ?, ?, ?)',
            [Auth::id(), $titre, $contenu, $etiquettes]);
        Session::flash('succes', t('alt.msg.note_creee', ['titre' => $titre]));
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
        [$titre, $contenu, $etiquettes] = $this->lireNote();
        if ($titre === '') {
            Session::flash('erreur', t('alt.msg.titre_manquant'));
            redirect('alternance/notes/' . $id);
        }
        Database::run('UPDATE alternance_notes SET titre = ?, contenu = ?, etiquettes = ? WHERE id = ? AND user_id = ?',
            [$titre, $contenu, $etiquettes, $id, Auth::id()]);
        Session::flash('succes', t('alt.msg.note_enregistree'));
        redirect('alternance/notes/' . $id);
    }

    public function supprimerNote(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $this->exigerA('alternance_notes', $id);
        Database::run('DELETE FROM alternance_notes WHERE id = ? AND user_id = ?', [$id, Auth::id()]);
        Session::flash('succes', t('alt.msg.note_supprimee'));
        redirect('alternance');
    }

    /** @return array{string, ?string, ?string} le titre, le texte, les étiquettes */
    private function lireNote(): array
    {
        $etiquettes = implode(', ', Alternance::etiquettes(post('etiquettes')));

        return [
            mb_substr(trim(post('titre')), 0, 200),
            TexteRiche::depuisFormulaire(post('contenu')) ?: null,
            mb_substr($etiquettes, 0, 200) ?: null,
        ];
    }

    // --- Le rythme -------------------------------------------------------------

    public function rythme(): void
    {
        Auth::exiger();
        $userId = Auth::id();
        $jeton = (string) (Database::valeur('SELECT jeton_ics FROM alternance_contrat WHERE user_id = ?', [$userId]) ?? '');
        $this->afficher('alternance/rythme', [
            'periodes' => Alternance::periodes($userId),
            'bilan'    => Alternance::bilan($userId),
            // Le lien d'abonnement, s'il a été créé : on ne le fabrique pas tout seul.
            'lienIcs'  => $jeton === '' ? '' : Alternance::adresseIcs($jeton),
        ], t('titre.alt_rythme'), 'rythme');
    }

    /**
     * Le rythme en iCalendar, par le lien d'abonnement : pas de session ici,
     * c'est Outlook ou Google qui vient le relire, jeton en main.
     */
    public function icsRythme(string $jeton): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        $userId = Alternance::parJetonIcs($jeton);
        if ($userId === null) {
            http_response_code(404);
            exit(t('corps.lien_inconnu'));
        }

        $ics = Alternance::icsRythme($userId);
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Length: ' . strlen($ics));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="alternance.ics"');
        echo $ics;
        exit;
    }

    /** Créer le lien d'abonnement, ou le renouveler pour couper l'ancien. */
    public function lienIcs(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $renouveler = Database::valeur('SELECT jeton_ics FROM alternance_contrat WHERE user_id = ?',
            [Auth::id()]) !== null && ($_POST['renouveler'] ?? '') === '1';
        Alternance::jetonIcs(Auth::id(), $renouveler);
        Session::flash('succes', t($renouveler ? 'alt.msg.lien_renouvele' : 'alt.msg.lien_cree'));
        redirect('alternance/rythme');
    }

    /**
     * Un planning importé : l'agenda de l'école (.ics) ou un tableau collé.
     * Chaque période est posée comme à la main, et remplace donc les jours
     * déjà prévus sur ses dates.
     */
    public function importerRythme(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $defaut = post('lieu_defaut');
        if (!isset(Alternance::LIEUX[$defaut])) {
            $defaut = 'entreprise';
        }

        $contenu = trim(post('colle'));
        $depose = $_FILES['planning'] ?? null;
        if (is_array($depose) && (int) ($depose['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            if ((int) $depose['size'] > 8 * 1024 * 1024) {
                Session::flash('erreur', t('alt.msg.fichier_trop_gros'));
                redirect('alternance/rythme');
            }
            $chemin = (string) $depose['tmp_name'];
            $contenu = (string) file_get_contents($chemin);
            // Un planning en PDF dit souvent par la couleur ce qu'il n'écrit
            // pas : on le lit, puis on demande ce que chaque couleur veut dire.
            if (str_starts_with($contenu, '%PDF')) {
                $this->couleursDuPdf($chemin, (string) $depose['name'], $defaut);
                return;
            }
        }
        if (trim($contenu) === '') {
            Session::flash('erreur', t('alt.msg.donnez_fichier'));
            redirect('alternance/rythme');
        }
        // Un fichier écrit par un tableur n'est pas toujours en UTF-8.
        if (!mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = (string) mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }

        $lu = Alternance::lirePlanning($contenu, $defaut);
        if ($lu['periodes'] === []) {
            Session::flash('erreur', t('alt.msg.rien_lisible'));
            redirect('alternance/rythme');
        }
        if (count($lu['periodes']) > 400) {
            Session::flash('erreur', t('alt.msg.trop_de_periodes'));
            redirect('alternance/rythme');
        }

        $comptes = [];
        foreach ($lu['periodes'] as $p) {
            Alternance::poserPeriode($userId, $p['lieu'], $p['debut'], $p['fin'], $p['note']);
            $comptes[$p['lieu']] = ($comptes[$p['lieu']] ?? 0) + 1;
        }

        $detail = [];
        foreach ($comptes as $lieu => $combien) {
            $detail[] = $combien . ' ' . mb_strtolower(Alternance::lieuNom($lieu));
        }
        Session::flash('succes',
            tn('alt.msg.importees', count($lu['periodes']), ['detail' => implode(', ', $detail)])
            . ($lu['ignorees'] > 0 ? t('alt.msg.lignes_sautees', ['n' => $lu['ignorees']]) : t('alt.msg.point')));
        redirect('alternance/rythme');
    }

    /**
     * Ce qu'on a su lire d'un planning PDF : les jours et leur couleur. On ne
     * devine pas ce qu'une couleur veut dire — la page le demande, légende en
     * main, et l'import n'a lieu qu'après.
     */
    private function couleursDuPdf(string $chemin, string $nom, string $defaut): void
    {
        $annee = entier_ou_null(post('annee'));
        $lu = PlanningPdf::lire($chemin, $annee !== null && $annee >= 2000 && $annee <= 2100 ? $annee : null);

        if ($lu['jours'] === []) {
            Session::flash('erreur', t($lu['pages'] === 0 ? 'alt.msg.pdf_illisible' : 'alt.msg.pdf_sans_date'));
            redirect('alternance/rythme');
        }

        $this->afficher('alternance/import_pdf', [
            'nomFichier' => mb_substr($nom, 0, 120),
            'jours'      => $lu['jours'],
            'couleurs'   => $lu['couleurs'],
            'methode'    => $lu['methode'],
            'defaut'     => $defaut,
        ], t('titre.alt_couleurs'), 'rythme');
    }

    /**
     * L'import d'un PDF, une fois les couleurs expliquées : les jours qui se
     * suivent et disent la même chose deviennent une période.
     */
    public function importerCouleurs(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $jours = json_decode(post('jours'), true);
        if (!is_array($jours) || $jours === []) {
            Session::flash('erreur', t('alt.msg.planning_perdu'));
            redirect('alternance/rythme');
        }

        $legende = [];
        $couleurs = $_POST['couleurs'] ?? [];
        $lieux = $_POST['lieux'] ?? [];
        foreach (is_array($couleurs) ? $couleurs : [] as $rang => $couleur) {
            $lieu = (string) ($lieux[$rang] ?? '');
            if (is_string($couleur) && isset(Alternance::LIEUX[$lieu])) {
                $legende[$couleur] = $lieu;
            }
        }
        if ($legende === []) {
            Session::flash('erreur', t('alt.msg.dites_une_couleur'));
            redirect('alternance/rythme');
        }

        $periodes = PlanningPdf::periodes(
            array_map('strval', array_filter($jours, 'is_string')), $legende);
        if ($periodes === []) {
            Session::flash('erreur', t('alt.msg.couleurs_vides'));
            redirect('alternance/rythme');
        }

        $comptes = [];
        $jours = 0;
        foreach ($periodes as $p) {
            Alternance::poserPeriode($userId, $p['lieu'], $p['debut'], $p['fin'], null);
            $comptes[$p['lieu']] = ($comptes[$p['lieu']] ?? 0) + 1;
            $jours += Alternance::joursOuvres($p['debut'], $p['fin']);
        }

        $detail = [];
        foreach ($comptes as $lieu => $combien) {
            $detail[] = $combien . ' ' . mb_strtolower(Alternance::lieuNom($lieu));
        }
        Session::flash('succes', tn('alt.msg.couleurs_importees', count($periodes), [
            'jours' => tn('alt.msg.jours_ouvres', $jours),
            'detail' => implode(', ', $detail),
        ]));
        redirect('alternance/rythme');
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
            !isset(Alternance::LIEUX[$lieu]) => t('alt.msg.choisir_lieu'),
            $debut === null                  => t('alt.msg.debut_invalide'),
            $fin < $debut                    => t('alt.msg.fin_avant'),
            (new DateTimeImmutable($debut))->diff(new DateTimeImmutable($fin))->days >= Alternance::JOURS_MAX
                                             => t('alt.msg.trois_ans'),
            default                          => null,
        };
        if ($erreur !== null) {
            Session::flash('erreur', $erreur);
            redirect('alternance/rythme');
        }

        $avant = (int) Database::valeur('SELECT COUNT(*) FROM alternance_periodes WHERE user_id = ? AND id <> ? AND debut <= ? AND fin >= ?',
            [$userId, $id ?? 0, $fin, $debut]);
        Alternance::poserPeriode($userId, $lieu, $debut, $fin, $note, $id);

        $texte = $debut === $fin
            ? t('alt.msg.periode_le', ['lieu' => ucfirst(Alternance::lieuDans($lieu)), 'date' => Alternance::jourCourt($debut)])
            : t('alt.msg.periode_du_au', [
                'lieu' => ucfirst(Alternance::lieuDans($lieu)),
                'debut' => Alternance::jourCourt($debut),
                'fin' => Alternance::jourCourt($fin),
            ]);
        // « 23 oct. » porte déjà son point.
        $texte = rtrim($texte, '.') . '.';
        if ($avant > 0) {
            $texte .= t('alt.msg.remplaces');
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
        Session::flash('succes', t('alt.msg.periode_retiree'));
        redirect('alternance/rythme');
    }

    // --- Le journal ------------------------------------------------------------

    public function journal(): void
    {
        Auth::exiger();
        $userId = Auth::id();
        // Chercher une mission ou une compétence, quand le journal s'allonge.
        $recherche = trim((string) ($_GET['q'] ?? ''));
        $ou = '';
        $valeurs = [$userId];
        if ($recherche !== '') {
            $ou = ' AND (missions LIKE ? OR competences LIKE ?)';
            $valeurs[] = '%' . $recherche . '%';
            $valeurs[] = '%' . $recherche . '%';
        }

        $this->afficher('alternance/journal', [
            'pages'    => Database::all(
                'SELECT * FROM alternance_journal WHERE user_id = ?' . $ou . ' ORDER BY semaine DESC', $valeurs),
            'aEcrire'  => $recherche === '' ? Alternance::semainesAEcrire($userId) : [],
            'cetteSemaine' => Alternance::lundi(date('Y-m-d')),
            'recherche' => $recherche,
            'combien'   => (int) Database::valeur(
                'SELECT COUNT(*) FROM alternance_journal WHERE user_id = ?', [$userId]),
        ], t('titre.alt_journal'), 'journal');
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
        ], t('titre.alt_competences'), 'journal');
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
            t('titre.alt_semaine', ['date' => Alternance::jourCourt($semaine)]), 'journal');
    }

    public function ecrireJournal(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $date = Alternance::dateValide(post('semaine'));
        if ($date === null) {
            Session::flash('erreur', t('alt.msg.choisir_semaine'));
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
            Session::flash('erreur', t('alt.msg.ecrivez_quelque_chose'));
            redirect('alternance/journal/semaine', ['semaine' => $semaine]);
        }

        $deja = entier_ou_null(Database::valeur(
            'SELECT id FROM alternance_journal WHERE user_id = ? AND semaine = ?', [$userId, $semaine]));
        if ($deja !== null && $deja !== $id) {
            // Une page écrite ailleurs entre-temps : on ne l'écrase pas en silence.
            Session::flash('erreur', t('alt.msg.semaine_deja', ['date' => Alternance::jourCourt($semaine)]));
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
        Session::flash('succes', t('alt.msg.semaine_enregistree', ['date' => Alternance::jourCourt($semaine)]));
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
            Session::flash('erreur', t($du === null && $au === null ? 'alt.msg.journal_vide' : 'alt.msg.aucune_semaine'));
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
                'titre'       => t('alt.pdf.semaine_du_au', [
                    'debut' => date_fr($lundi . ' 00:00:00', false),
                    'fin' => date_fr($vendredi . ' 00:00:00', false),
                ]),
                'sous_titre'  => $jours === 0 ? '' : tn('alt.pj.jours_entreprise', $jours),
                'missions'    => $page['missions'],
                'competences' => Alternance::competences($page['competences']),
            ];
        }

        // L'en-tête porte l'entreprise et le poste, quand la fiche les donne :
        // le PDF part souvent seul, sans rien pour dire de qui il parle.
        $contrat = Alternance::contrat($userId);
        $qui = array_filter([trim((string) $contrat['entreprise']), trim((string) $contrat['poste'])], 'strlen');

        $periode = ($qui === [] ? '' : implode(' · ', $qui) . ' — ')
            . t('alt.pdf.periode', [
                'debut' => date_fr((string) $pages[0]['semaine'] . ' 00:00:00', false),
                'fin' => date_fr((new DateTimeImmutable((string) $pages[count($pages) - 1]['semaine']))->modify('+4 days')->format('Y-m-d') . ' 00:00:00', false),
                'semaines' => tn('alt.co.semaines', count($semaines)),
            ]);

        try {
            $pdf = ExportPdf::depuisJournal($semaines, $periode);
        } catch (Throwable) {
            Session::flash('erreur', t('alt.msg.pdf_echec'));
            redirect('alternance/journal');
        }

        $nom = t('alt.pdf.nom_fichier');
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
        Session::flash('succes', t('alt.msg.page_supprimee'));
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
            t('titre.alt_documents'), 'documents');
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
            Session::flash('succes', tn('alt.msg.documents_ranges', $recus, [
                'rayon' => Alternance::categorieNom($categorie),
            ]));
        }
        if ($erreurs !== []) {
            Session::flash('erreur', implode(' ', $erreurs));
        } elseif ($recus === 0) {
            Session::flash('erreur', t('alt.msg.choisir_fichier'));
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
            Session::flash('succes', t('alt.msg.range_dans', ['rayon' => Alternance::categorieNom($categorie)]));
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
        Session::flash('succes', t('alt.msg.document_supprime', ['nom' => $nom]));
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
        Vue::afficher('erreurs/404', [], t('titre.introuvable'));
        exit;
    }
}
