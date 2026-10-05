<?php
declare(strict_types=1);

/**
 * Les liens vers d'autres applications du menu en grille : ajouter, modifier, supprimer. Le script du menu les appelle
 * sans recharger la page et reçoit du JSON : {ok: true, lien: {…}} ou {ok: false, message}. Un lien appartient à son
 * propriétaire seul ; tout ce qui arrive repasse par LienApp::valider.
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
        [$lien, $erreur] = LienApp::valider((string) ($_POST['nom'] ?? ''), (string) ($_POST['url'] ?? ''), (string) ($_POST['icone'] ?? ''));
        if ($lien === null) {
            $this->json(['ok' => false, 'message' => t((string) $erreur)], 422);
        }

        $position = (int) Database::valeur('SELECT COALESCE(MAX(position), 0) + 1 FROM liens_apps WHERE user_id = ?', [$userId]);
        Database::run('INSERT INTO liens_apps (user_id, nom, url, icone, position) VALUES (?, ?, ?, ?, ?)',
            [$userId, $lien['nom'], $lien['url'], $lien['icone'], $position]);

        $this->json(['ok' => true, 'lien' => $this->pourLeScript(Database::dernierId(), $lien)]);
    }

    public function modifier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $this->possede($id, $userId);

        [$lien, $erreur] = LienApp::valider((string) ($_POST['nom'] ?? ''), (string) ($_POST['url'] ?? ''), (string) ($_POST['icone'] ?? ''));
        if ($lien === null) {
            $this->json(['ok' => false, 'message' => t((string) $erreur)], 422);
        }
        Database::run('UPDATE liens_apps SET nom = ?, url = ?, icone = ? WHERE id = ? AND user_id = ?',
            [$lien['nom'], $lien['url'], $lien['icone'], $id, $userId]);

        $this->json(['ok' => true, 'lien' => $this->pourLeScript($id, $lien)]);
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
                'site_icone' => LienApp::iconeDuSite($lien['url'])];
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
