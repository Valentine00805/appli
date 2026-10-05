<?php
declare(strict_types=1);

/**
 * Les diaporamas commentés : lus diapositive par diapositive, avec une voix.
 *
 * Ils s'écrivent depuis la page « Résumés IA » (case « Diaporama commenté », voir ResumesController). Ici : les
 * regarder, fabriquer leur voix Gemini une diapositive à la fois (le script de la page appelle voix() à la suite, ce
 * qui montre l'avancement et permet de reprendre après une limite atteinte), écouter, effacer. Un diaporama appartient
 * à son propriétaire seul.
 */
final class DiaporamasController
{
    /** Le diaporama, dans son lecteur. */
    public function voir(int $id): void
    {
        Auth::exiger();
        $userId = Auth::id();
        $ligne = $this->diaporama($id, $userId);

        $donnees = [
            'diaporama' => $ligne,
            'diapos'    => Diaporama::diapos($ligne),
            'cleFin'    => CleApi::fin($userId, CleApi::GEMINI),
        ];
        // Demandé depuis une liste, il s'ouvre dans une fenêtre, par-dessus la page où l'on était.
        if (Vue::enFenetre()) {
            Vue::fragment('diaporamas/voir', $donnees);

            return;
        }
        Vue::afficher('diaporamas/voir', $donnees, (string) $ligne['titre']);
    }

    /**
     * Fabrique la voix Gemini d'UNE diapositive (le script les demande une à une) et répond en JSON :
     * {ok: true, n} ou {ok: false, nature, message}. Une voix déjà faite pour cette diapositive est remplacée.
     */
    public function voix(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $ligne = Database::one('SELECT * FROM diaporamas WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($ligne === null) {
            $this->json(['ok' => false, 'nature' => 'introuvable', 'message' => t('titre.introuvable')], 404);
        }

        $diapos = Diaporama::diapos($ligne);
        $n = entier_ou_null($_POST['n'] ?? null);
        if ($n === null || !isset($diapos[$n]) || $diapos[$n]['c'] === '') {
            $this->json(['ok' => false, 'nature' => 'vide', 'message' => t('ria.fl.rien_a_lire')], 422);
        }
        $cle = CleApi::lire($userId, CleApi::GEMINI);
        if ($cle === null) {
            $this->json(['ok' => false, 'nature' => 'cle', 'message' => t('ria.fl.pas_de_cle')], 409);
        }
        $voix = in_array($_POST['voix'] ?? '', Gemini::VOIX, true) ? (string) $_POST['voix'] : Gemini::VOIX[0];

        $resumes = new ResumesController();
        try {
            [$pcm, $frequence] = $resumes->sansVerrou(
                static fn (): array => ResumesController::sonDe($cle, $diapos[$n]['c'], $voix, time() + 120));
        } catch (GeminiErreur $e) {
            $this->json(['ok' => false, 'nature' => $e->nature, 'message' => $resumes->messageDErreur($e)], 502);
        }
        $nom = ResumesController::ranger($pcm, $frequence);
        if ($nom === null) {
            $this->json(['ok' => false, 'nature' => 'ecriture', 'message' => t('ria.fl.ecriture')], 500);
        }

        // Relu au dernier moment : les voix des autres diapositives, faites pendant ce temps, ne doivent pas être perdues.
        $frais = Database::one('SELECT * FROM diaporamas WHERE id = ? AND user_id = ?', [$id, $userId]);
        $diapos = Diaporama::diapos($frais ?? $ligne);
        if (!isset($diapos[$n])) {
            @unlink(ResumesController::dossier() . DIRECTORY_SEPARATOR . $nom);
            $this->json(['ok' => false, 'nature' => 'vide', 'message' => t('ria.fl.rien_a_lire')], 422);
        }
        if (isset($diapos[$n]['a'])) {
            @unlink(ResumesController::dossier() . DIRECTORY_SEPARATOR . basename($diapos[$n]['a']));
        }
        $diapos[$n]['a'] = $nom;
        Database::run('UPDATE diaporamas SET diapos = ?, voix = ? WHERE id = ? AND user_id = ?',
            [json_encode($diapos, JSON_UNESCAPED_UNICODE), $voix, $id, $userId]);

        $this->json(['ok' => true, 'n' => $n]);
    }

    /** Envoie la voix d'une diapositive, pour son seul propriétaire. */
    public function audio(int $id, int $n): void
    {
        Auth::exiger();
        session_write_close();
        $diapos = Diaporama::diapos($this->diaporama($id, Auth::id()));
        if (!isset($diapos[$n]['a'])) {
            $this->introuvable();
        }
        Fichiers::envoyer([
            'nom_stocke'  => $diapos[$n]['a'],
            'nom_origine' => 'diaporama-' . $id . '-' . ($n + 1) . '.wav',
            'mime'        => 'audio/wav',
        ], false, ResumesController::dossier());
    }

    /** Efface le diaporama et ses voix. */
    public function supprimer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $ligne = $this->diaporama($id, Auth::id());

        foreach (Diaporama::diapos($ligne) as $diapo) {
            if (isset($diapo['a'])) {
                @unlink(ResumesController::dossier() . DIRECTORY_SEPARATOR . basename($diapo['a']));
            }
        }
        Database::run('DELETE FROM diaporamas WHERE id = ? AND user_id = ?', [$id, Auth::id()]);
        Session::flash('succes', t('dia.fl.supprime'));
        redirect('resumes');
    }

    // --- Dedans ---------------------------------------------------------------

    /** Un diaporama de l'utilisateur, ou une page introuvable. */
    private function diaporama(int $id, int $userId): array
    {
        $ligne = Database::one('SELECT * FROM diaporamas WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($ligne === null) {
            $this->introuvable();
        }

        return $ligne;
    }

    private function json(array $donnees, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($donnees, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], t('titre.introuvable'));
        exit;
    }
}
