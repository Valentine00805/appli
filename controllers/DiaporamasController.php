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
            'coursFiche' => Diaporama::coursDeLaFiche($ligne, $userId),
            // Ouvert depuis l'aperçu de la fiche, à la diapositive qu'on y regardait.
            'debut'     => max(0, min(count(Diaporama::diapos($ligne)) - 1, (int) (entier_ou_null($_GET['diapo'] ?? null) ?? 0))),
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

    /**
     * Range le diaporama dans la fiche de révision d'un des cours qu'il a lus : il y apparaît parmi ses diaporamas, et
     * s'y rouvre avec sa voix. Le cours vient du diaporama lui-même, jamais d'un numéro libre.
     */
    public function versLaFiche(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $ligne = $this->diaporama($id, $userId);

        $cours = $this->coursDeLaFicheChoisi($ligne, $userId);
        if ($cours === null) {
            Session::flash('erreur', t('dia.fl.fiche_impossible'));
            redirect('diaporamas/' . $id);
        }
        if ($cours['lie']) {
            Session::flash('info', t('dia.fl.fiche_deja', ['cours' => $cours['cours']]));
            redirect('diaporamas/' . $id);
        }
        Database::run('INSERT IGNORE INTO diaporama_cours (diaporama_id, cours_id, user_id) VALUES (?, ?, ?)', [$id, $cours['id'], $userId]);
        Session::flash('succes', t('dia.fl.fiche_ajoute', ['cours' => $cours['cours']]));
        redirect('diaporamas/' . $id);
    }

    /** Retire le diaporama de la fiche d'un cours (il n'est pas effacé : il reste dans « Résumés IA »). */
    public function retirerDeLaFiche(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $ligne = $this->diaporama($id, $userId);

        $coursId = (int) entier_ou_null($_POST['cours'] ?? null);
        $titre = Database::valeur('SELECT titre FROM cours WHERE id = ? AND user_id = ?', [$coursId, $userId]);
        if ($titre === null) {
            Session::flash('erreur', t('dia.fl.fiche_impossible'));
            redirect('diaporamas/' . $id);
        }
        Database::run('DELETE FROM diaporama_cours WHERE diaporama_id = ? AND cours_id = ? AND user_id = ?', [$id, $coursId, $userId]);
        Session::flash('succes', t('dia.fl.fiche_retire', ['cours' => (string) $titre]));

        // Retiré depuis la fiche elle-même : on y revient (la fiche d'un cours, ou son volet) ; sinon, au diaporama.
        $retour = $_POST['retour'] ?? '';
        if ($retour === 'fiche') {
            redirect('revision/' . $coursId);
        }
        if ($retour === 'volet') {
            redirect('cours/' . $coursId, ['revision' => 1]);
        }
        redirect('diaporamas/' . $id);
    }

    /** Le diaporama en PDF (titres, points, commentaires), à télécharger. */
    public function pdf(int $id): void
    {
        Auth::exiger();
        $ligne = $this->diaporama($id, Auth::id());
        try {
            $pdf = $this->fabriquerLePdf($ligne);
        } catch (Throwable) {
            Session::flash('erreur', t('ria.fl.pdf_echec'));
            redirect('diaporamas/' . $id);
        }
        $nom = self::nomDuPdf($ligne);
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdf));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        header(sprintf("Content-Disposition: attachment; filename=\"%s\"; filename*=UTF-8''%s",
            preg_replace('/[^A-Za-z0-9._-]+/', '_', $nom) ?? 'diaporama.pdf', rawurlencode($nom)));
        echo $pdf;
        exit;
    }

    /**
     * Joint le PDF du diaporama à la fiche de révision d'un des cours lus : il y rejoint les fichiers de la fiche. Un
     * nom déjà pris dans la fiche reçoit un numéro, pour que deux PDF ne se confondent pas.
     */
    public function pdfVersLaFiche(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $ligne = $this->diaporama($id, $userId);

        $cours = $this->coursDeLaFicheChoisi($ligne, $userId);
        if ($cours === null) {
            Session::flash('erreur', t('dia.fl.fiche_impossible'));
            redirect('diaporamas/' . $id);
        }
        try {
            $pdf = $this->fabriquerLePdf($ligne);
        } catch (Throwable) {
            Session::flash('erreur', t('ria.fl.pdf_echec'));
            redirect('diaporamas/' . $id);
        }

        $dossier = (string) Config::get('app', 'dossier_uploads');
        if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            Session::flash('erreur', t('ria.fl.ecriture'));
            redirect('diaporamas/' . $id);
        }
        $stocke = bin2hex(random_bytes(16)) . '.pdf';
        if (file_put_contents($dossier . DIRECTORY_SEPARATOR . $stocke, $pdf) === false) {
            Session::flash('erreur', t('ria.fl.ecriture'));
            redirect('diaporamas/' . $id);
        }

        $nom = self::nomDuPdf($ligne);
        $deja = array_column(Database::all(
            'SELECT nom_origine FROM fichiers WHERE cours_id = ? AND pour_fiche = 1', [$cours['id']]), 'nom_origine');
        $base = substr($nom, 0, -4);
        for ($n = 2; in_array($nom, $deja, true); $n++) {
            $nom = $base . ' (' . $n . ').pdf';
        }
        Database::run(
            'INSERT INTO fichiers (user_id, cours_id, pour_fiche, nom_origine, nom_stocke, mime, taille) VALUES (?, ?, 1, ?, ?, ?, ?)',
            [$userId, $cours['id'], mb_substr($nom, 0, 255), $stocke, 'application/pdf', strlen($pdf)]
        );
        Partages::suivreAjouts($userId, 'fiche', $cours['id'], 1);

        Session::flash('succes', t('ria.fl.pdf_joint', ['cours' => $cours['cours']]));
        redirect('diaporamas/' . $id);
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

    /** Le cours choisi dans le formulaire, s'il fait partie de ceux que le diaporama a lus (et qu'on possède encore). */
    private function coursDeLaFicheChoisi(array $ligne, int $userId): ?array
    {
        $voulu = entier_ou_null($_POST['cours'] ?? null);
        foreach (Diaporama::coursDeLaFiche($ligne, $userId) as $cours) {
            if ($voulu !== null && $cours['id'] === $voulu) {
                return $cours;
            }
        }

        return null;
    }

    /** Fabrique le PDF d'un diaporama. */
    private function fabriquerLePdf(array $ligne): string
    {
        return ExportPdf::depuisResume((string) $ligne['titre'], Diaporama::sousTitrePdf($ligne),
            Diaporama::htmlPourPdf(Diaporama::diapos($ligne)));
    }

    /** Le nom d'un PDF de diaporama : son titre, sans les signes qu'un système de fichiers refuse. */
    private static function nomDuPdf(array $ligne): string
    {
        return (trim((string) preg_replace('/[\\\\\/:*?"<>|]+/', ' ', (string) $ligne['titre'])) ?: 'diaporama') . '.pdf';
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
