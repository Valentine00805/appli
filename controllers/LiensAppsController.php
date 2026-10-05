<?php
declare(strict_types=1);

/**
 * Les liens vers d'autres applications du menu en grille : ajouter, modifier, supprimer. Le script du menu les appelle
 * sans recharger la page et reçoit du JSON : {ok: true, lien: {…}} ou {ok: false, message}. Un lien appartient à son
 * propriétaire seul ; tout ce qui arrive repasse par LienApp::valider. Réorganiser (glisser-déposer) envoie la liste des
 * identifiants dans leur nouvel ordre.
 */
final class LiensAppsController
{
    public function creer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        if ((int) Database::valeur('SELECT COUNT(*) FROM liens_apps WHERE user_id = ?', [$userId]) >= LienApp::MAX) {
            $this->json(['ok' => false, 'message' => t('lia.err.max', ['n' => LienApp::MAX])], 422);
        }
        [$lien, $erreur] = LienApp::valider((string) ($_POST['nom'] ?? ''), (string) ($_POST['url'] ?? ''), (string) ($_POST['icone'] ?? ''), (string) ($_POST['logo'] ?? ''));
        if ($lien === null) {
            $this->json(['ok' => false, 'message' => t((string) $erreur)], 422);
        }

        $position = (int) Database::valeur('SELECT COALESCE(MAX(position), 0) + 1 FROM liens_apps WHERE user_id = ?', [$userId]);
        Database::run('INSERT INTO liens_apps (user_id, nom, url, icone, logo, position) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $lien['nom'], $lien['url'], $lien['icone'], $lien['logo'], $position]);

        $this->json(['ok' => true, 'lien' => $this->pourLeScript(Database::dernierId(), $lien)]);
    }

    public function modifier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $this->possede($id, $userId);

        [$lien, $erreur] = LienApp::valider((string) ($_POST['nom'] ?? ''), (string) ($_POST['url'] ?? ''), (string) ($_POST['icone'] ?? ''), (string) ($_POST['logo'] ?? ''));
        if ($lien === null) {
            $this->json(['ok' => false, 'message' => t((string) $erreur)], 422);
        }
        Database::run('UPDATE liens_apps SET nom = ?, url = ?, icone = ?, logo = ? WHERE id = ? AND user_id = ?',
            [$lien['nom'], $lien['url'], $lien['icone'], $lien['logo'], $id, $userId]);

        $this->json(['ok' => true, 'lien' => $this->pourLeScript($id, $lien)]);
    }

    /**
     * Enregistre l'ordre des liens : « ids[] », dans l'ordre voulu. Seuls les liens du compte comptent ; un identifiant
     * étranger ou répété est ignoré, et un lien oublié garde sa place à la suite des autres.
     */
    public function ordonner(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $demandes = $_POST['ids'] ?? [];
        $demandes = is_array($demandes) ? $demandes : [];
        $siens = array_map('intval', array_column(Database::all('SELECT id FROM liens_apps WHERE user_id = ? ORDER BY position, id', [$userId]), 'id'));

        $ordre = [];
        foreach ($demandes as $d) {
            $id = is_scalar($d) ? (int) $d : 0;
            if ($id > 0 && in_array($id, $siens, true) && !in_array($id, $ordre, true)) {
                $ordre[] = $id;
            }
        }
        $ordre = array_merge($ordre, array_values(array_diff($siens, $ordre)));

        foreach ($ordre as $rang => $id) {
            Database::run('UPDATE liens_apps SET position = ? WHERE id = ? AND user_id = ?', [$rang + 1, $id, $userId]);
        }
        $this->json(['ok' => true, 'ordre' => $ordre]);
    }

    public function supprimer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $this->possede($id, $userId);

        Database::run('DELETE FROM liens_apps WHERE id = ? AND user_id = ?', [$id, $userId]);
        $this->json(['ok' => true, 'id' => $id]);
    }

    // --- Dedans ---------------------------------------------------------------

    /** Ce qu'on rend au script : le lien tel qu'il s'affichera, avec l'icône choisie ou proposée. */
    private function pourLeScript(int $id, array $lien): array
    {
        return ['id' => $id, 'nom' => $lien['nom'], 'url' => $lien['url'],
                'icone' => LienApp::icone($lien['icone'], $lien['url']), 'icone_choisie' => $lien['icone'],
                'logo' => $lien['logo'], 'image' => LienApp::image($lien['url'], $lien['logo'], $lien['icone'])];
    }

    private function possede(int $id, int $userId): void
    {
        if (Database::valeur('SELECT id FROM liens_apps WHERE id = ? AND user_id = ?', [$id, $userId]) === null) {
            $this->json(['ok' => false, 'message' => t('titre.introuvable')], 404);
        }
    }

    private function json(array $donnees, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($donnees, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
