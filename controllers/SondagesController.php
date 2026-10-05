<?php
declare(strict_types=1);

/**
 * Les sondages des discussions : en créer un (entre amis ou dans un groupe) et y voter. Le script de la page appelle ces
 * adresses sans recharger et reçoit du JSON : {fait: true, …} ou {fait: false, message}. Tout ce qui arrive repasse par
 * Sondages (validation, droit de voir le message qui le porte).
 */
final class SondagesController
{
    public function creerAmis(int $id): void
    {
        $this->creer('amis', $id);
    }

    public function creerGroupe(int $id): void
    {
        $this->creer('groupes', $id);
    }

    private function creer(string $canal, int $cible): never
    {
        Auth::exiger();
        Session::verifierCsrf();
        $moi = Auth::id();

        $options = $_POST['options'] ?? [];
        [$propre, $erreur] = Sondages::valider((string) ($_POST['question'] ?? ''), is_array($options) ? $options : []);
        if ($propre === null) {
            $this->json(['fait' => false, 'message' => t((string) $erreur)], 422);
        }
        $multiple = !in_array((string) ($_POST['multiple'] ?? ''), ['', '0'], true);

        [$messageId, $refus] = $canal === 'amis'
            ? Sondages::creerAmis($moi, $cible, $propre, $multiple)
            : Sondages::creerGroupe($moi, $cible, $propre, $multiple);
        if ($messageId === null) {
            $this->json(['fait' => false, 'message' => $refus], 422);
        }

        // Comme un message : la notification est écrite avant de répondre, et part après (le sondage ne fait pas attendre).
        $texte = '📊 ' . $propre['question'];
        $notifications = $canal === 'amis'
            ? array_filter([Amis::notifier($moi, $cible, $texte)], static fn (?int $n): bool => $n !== null)
            : Conversations::notifier($moi, $cible, $texte);
        session_write_close();
        ignore_user_abort(true);
        $corps = (string) json_encode(['fait' => true, 'id' => $messageId], JSON_UNESCAPED_UNICODE);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($corps));
        header('Connection: close');
        echo $corps;
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        foreach ($notifications as $n) {
            FileNotifications::envoyer($n);
        }
        exit;
    }

    /** Enregistre les réponses cochées : « options[] », toutes celles qu'on garde (aucune : on retire son vote). */
    public function voter(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $options = $_POST['options'] ?? [];
        [$fait, $message, $sondage] = Sondages::voter(Auth::id(), $id, is_array($options) ? $options : []);
        if (!$fait) {
            $this->json(['fait' => false, 'message' => $message], $message === t('son.err.introuvable') ? 404 : 422);
        }
        $this->json(['fait' => true, 'sondage' => $sondage]);
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
