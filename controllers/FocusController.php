<?php
declare(strict_types=1);

/**
 * Le mode focus : une session de révision minutée, sur un écran sans rien
 * d'autre que ce qu'on révise.
 *
 * Le minuteur vit dans le navigateur — lui seul sait ce qui tourne et ce qui
 * est en pause. Le serveur, lui, ouvre la session au départ et la referme à
 * l'arrivée : c'est ce qui permet de dire, la semaine suivante, combien de
 * temps on a vraiment travaillé et sur quoi.
 */
final class FocusController
{
    /** La page de départ : ce qu'on va réviser, et ce qu'on a déjà fait. */
    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $bilan = Focus::bilan($userId);
        $apres = Focus::sessionRecente($userId);
        Vue::afficher('focus/index', [
            'enCours'   => Focus::enCours($userId),
            'bilan'     => $bilan,
            'objectif'  => Focus::objectif($userId),
            'avancement' => Focus::avancementObjectif($userId, (int) $bilan['semaine']),
            'dernieres' => Focus::dernieres($userId),
            'cours'     => Database::all(
                'SELECT c.id, c.titre, m.nom AS matiere_nom, d.nom AS dossier_nom
                 FROM cours c
                 LEFT JOIN matieres m ON m.id = c.matiere_id
                 LEFT JOIN dossiers d ON d.id = c.dossier_id
                 WHERE c.user_id = ? ORDER BY c.updated_at DESC LIMIT 300', [$userId]),
            'coursChoisi' => entier_ou_null($_GET['cours'] ?? null),
            'dossiers'  => Database::all(
                'SELECT d.id, d.nom, d.icone,
                        (SELECT COUNT(*) FROM cours c WHERE c.dossier_id = d.id) AS nb_cours
                 FROM dossiers d WHERE d.user_id = ? ORDER BY d.nom', [$userId]),
            'dernierCours' => Focus::dernierCours($userId),
            // Ce qu’on propose juste après une session : la revoir plus tard,
            // et les cartes que ce cours donne à revoir aujourd’hui.
            'apres'     => $apres,
            'apresCours' => $apres === null ? [] : Focus::coursDeLaSession((int) $apres['id']),
        ], 'Session de révision');
    }

    /** Ouvre la session, puis emmène droit sur l'écran de travail. */
    public function demarrer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        // Une session déjà ouverte se reprend : on n'en empile pas deux.
        $ouverte = Focus::enCours($userId);
        if ($ouverte !== null) {
            redirect('focus/' . (int) $ouverte['id']);
        }

        // Un cours, plusieurs, ou des dossiers entiers : tout se vaut ici.
        $coursIds = Focus::coursChoisis($userId,
            is_array($_POST['cours'] ?? null) ? $_POST['cours'] : [],
            is_array($_POST['dossiers'] ?? null) ? $_POST['dossiers'] : []);
        $sujet = mb_substr(trim(post('sujet')), 0, 150) ?: null;
        $minutes = Focus::rythmeValide($_POST['minutes'] ?? null);
        // Le silence est le défaut : on s’isole pour ne pas être dérangé.
        $silence = ($_POST['ne_pas_deranger'] ?? '1') !== '0';

        redirect('focus/' . Focus::demarrer($userId, $coursIds, $sujet, $minutes, $silence));
    }

    /** L'écran de la session : le minuteur, et ce qu'on révise. */
    public function session(int $id): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $session = Database::one(
            'SELECT s.*, c.titre AS cours_titre, c.contenu, c.fiche_revision, m.nom AS matiere_nom
             FROM sessions_revision s
             LEFT JOIN cours c ON c.id = s.cours_id
             LEFT JOIN matieres m ON m.id = c.matiere_id
             WHERE s.id = ? AND s.user_id = ?', [$id, $userId]);
        if ($session === null) {
            http_response_code(404);
            Vue::afficher('erreurs/404', [], 'Introuvable');
            return;
        }
        if ($session['fin'] !== null) {
            Session::flash('succes', 'Cette session est terminée.');
            redirect('focus');
        }

        Vue::afficher('focus/session', [
            'session' => $session,
            'coursDeLaSession' => Focus::coursDeLaSession($id),
            'pause'   => Focus::RYTHMES[Focus::rythmeValide($session['minutes_voulues'])]['pause'],
        ], 'Session en cours');
    }

    /**
     * Referme la session. La page l'appelle au bouton « Terminer », et aussi
     * en partant (« sendBeacon ») : une session qu'on quitte sans rien dire
     * garde tout de même le temps qu'on y a passé.
     */
    public function terminer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $fait = Focus::terminer(Auth::id(), $id,
            (int) ($_POST['secondes'] ?? 0), (int) ($_POST['pauses'] ?? 0), $_POST['ressenti'] ?? null);

        if (veut_du_json()) {
            repondre_json(['fait' => $fait]);
        }
        if ($fait) {
            $secondes = (int) Database::valeur('SELECT secondes FROM sessions_revision WHERE id = ?', [$id]);
            Session::flash($secondes >= Focus::SECONDES_MIN ? 'succes' : 'erreur',
                $secondes >= Focus::SECONDES_MIN
                    ? 'Session terminée : ' . Focus::duree($secondes) . ' de révision. Bravo.'
                    : 'Session trop courte pour être comptée — elle n’apparaîtra pas dans votre suivi.');
        }
        redirect('focus');
    }

    /** L’objectif de la semaine, changé depuis la page du focus. */
    public function objectif(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $minutes = Focus::changerObjectif(Auth::id(), $_POST['minutes'] ?? 0);
        Session::flash('succes', $minutes === 0
            ? 'Objectif retiré : le suivi continue, sans but à atteindre.'
            : 'Objectif de la semaine : ' . Focus::duree($minutes * 60) . ' de révision.');
        redirect('focus');
    }

    /**
     * Les prochaines révisions du cours qu'on vient de travailler : le
     * lendemain, trois jours après, une semaine après. On retient mieux en
     * revoyant à intervalles qui s'écartent qu'en relisant tout la veille.
     */
    public function espacer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        // Tous les cours de la session, ou le seul qu’on nomme.
        $sessionId = entier_ou_null($_POST['session_id'] ?? null);
        $coursIds = $sessionId === null
            ? array_filter([entier_ou_null($_POST['cours_id'] ?? null)])
            : array_map(static fn (array $c): int => (int) $c['id'], Focus::coursDeLaSession($sessionId));
        $coursIds = Focus::coursChoisis($userId, $coursIds, []);
        if ($coursIds === []) {
            Session::flash('erreur', 'Ce cours est introuvable.');
            redirect('focus');
        }

        $posees = 0;
        foreach ($coursIds as $coursId) {
            $posees += Focus::programmerRevisions($userId, $coursId)['posees'];
        }
        Session::flash($posees === 0 ? 'erreur' : 'succes', $posees === 0
            ? 'Ces révisions sont déjà dans votre liste « ' . Focus::LISTE . ' ».'
            : $posees . ' révision' . ($posees > 1 ? 's posées' : ' posée')
              . ' dans « ' . Focus::LISTE . ' » : demain, dans 3 jours, dans une semaine.');
        redirect('focus');
    }

    /** Poser une session au calendrier, pour s'y tenir. */
    public function planifier(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $coursId = entier_ou_null($_POST['cours_id'] ?? null);
        if ($coursId !== null
            && Database::valeur('SELECT id FROM cours WHERE id = ? AND user_id = ?', [$coursId, $userId]) === null) {
            $coursId = null;
        }

        $evenement = Focus::planifier($userId, $coursId, post('jour'), post('heure'),
            Focus::rythmeValide($_POST['minutes'] ?? null));
        if ($evenement === null) {
            Session::flash('erreur', 'Donnez un jour et une heure pour cette session.');
            redirect('focus');
        }

        Session::flash('succes', 'Session posée au calendrier, avec son rappel un quart d’heure avant.');
        redirect('evenements/' . $evenement);
    }

    /** Abandonner : la session se referme sans rien compter. */
    public function abandonner(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        Focus::terminer(Auth::id(), $id, 0, 0, null);
        Session::flash('succes', 'Session abandonnée : rien n’a été compté.');
        redirect('focus');
    }
}
