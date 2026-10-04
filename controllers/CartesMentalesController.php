<?php
declare(strict_types=1);

/**
 * Les cartes mentales d'un cours : vierges, ou proposées par l'IA — celles-là se demandent depuis la page « Résumés IA »
 * (voir ResumesController) — puis corrigées dans l'éditeur, et retrouvées dans la fiche de révision du cours.
 * Chaque carte appartient à un cours, et à son propriétaire seul.
 */
final class CartesMentalesController
{
    /** La carte, dans son éditeur. */
    public function voir(int $id): void
    {
        Auth::exiger();
        $carte = $this->carte($id, Auth::id());
        $arbre = json_decode((string) $carte['arbre'], true);
        $arbre = CarteMentale::nettoyer($arbre) ?? CarteMentale::racine((string) $carte['titre']);
        $cours = Database::one('SELECT id, titre FROM cours WHERE id = ? AND user_id = ?', [(int) $carte['cours_id'], Auth::id()]);

        $donnees = ['carte' => $carte, 'arbre' => $arbre, 'cours' => $cours];

        // Demandée depuis une liste, la carte s'ouvre dans une fenêtre, par-dessus la page où l'on était.
        if (Vue::enFenetre()) {
            Vue::fragment('cartes-mentales/voir', $donnees);

            return;
        }

        Vue::afficher('cartes-mentales/voir', $donnees, (string) $carte['titre']);
    }

    /**
     * Une carte neuve, réduite à son idée centrale : le titre du cours. Elle se demande depuis la page « Résumés IA »,
     * comme les cartes écrites par l'IA ; le cours vient du formulaire, et doit être le vôtre.
     */
    public function creer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $coursId = (int) entier_ou_null($_POST['cours'] ?? null);
        $cours = $this->cours($coursId, $userId);

        $arbre = CarteMentale::racine((string) $cours['titre']);
        Database::run(
            'INSERT INTO cartes_mentales (user_id, cours_id, titre, arbre, ia) VALUES (?, ?, ?, ?, 0)',
            [$userId, $coursId, CarteMentale::titre('', $arbre), json_encode($arbre, JSON_UNESCAPED_UNICODE)]
        );
        Session::flash('succes', t('cm.fl.creee'));
        // La liste rouvre aussitôt la carte dans une fenêtre (lien « data-ouvrir-auto »).
        redirect('resumes', ['carte' => Database::dernierId()]);
    }

    /**
     * Garde la carte, telle que l'éditeur l'envoie (à chaque modification, sans recharger la page). Ne répond
     * rien, sinon un code : 204 si c'est gardé, 422 si ce n'est pas une carte, 404 si elle n'est pas à vous.
     */
    public function enregistrer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $carte = Database::one('SELECT id, titre FROM cartes_mentales WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($carte === null) {
            http_response_code(404);
            exit;
        }

        $arbre = CarteMentale::nettoyer(json_decode((string) ($_POST['arbre'] ?? ''), true));
        if ($arbre === null) {
            http_response_code(422);
            exit;
        }
        $titre = array_key_exists('titre', $_POST)
            ? CarteMentale::titre((string) $_POST['titre'], $arbre)
            : (string) $carte['titre'];

        Database::run(
            'UPDATE cartes_mentales SET titre = ?, arbre = ? WHERE id = ? AND user_id = ?',
            [$titre, json_encode($arbre, JSON_UNESCAPED_UNICODE), $id, $userId]
        );
        http_response_code(204);
        exit;
    }

    /**
     * Joint la carte, en image, à la fiche de révision de son cours : elle rejoint les fichiers et images de la fiche,
     * où l'on la voit, la télécharge ou la retire comme les autres.
     *
     * L'image est dessinée par le navigateur (un PNG) ; on ne lui fait pas confiance pour autant : le type, les
     * dimensions et le poids sont vérifiés, et l'image est ré-encodée par GD quand il est là, pour que ce qui est
     * rangé soit un vrai PNG et rien d'autre. Ne répond rien, sinon un code : 204 si c'est joint, 400 si l'envoi est
     * incomplet, 413 s'il est trop lourd, 422 si ce n'est pas une image acceptable, 404 si la carte n'est pas à vous.
     */
    public function imageVersLaFiche(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();
        $carte = Database::one('SELECT id, cours_id, titre FROM cartes_mentales WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($carte === null) {
            http_response_code(404);
            exit;
        }

        $envoi = $_FILES['image'] ?? null;
        if (!is_array($envoi) || ($envoi['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $envoi['tmp_name'])) {
            http_response_code(($envoi['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($envoi['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE ? 413 : 400);
            exit;
        }
        if ((int) $envoi['size'] > min(Fichiers::tailleMax(), TexteRiche::IMAGE_MAX)) {
            http_response_code(413);
            exit;
        }

        $tmp = (string) $envoi['tmp_name'];
        $infos = @getimagesize($tmp);
        // Un PNG, de dimensions raisonnables : une carte de 250 idées tient largement dans 8 000 px, 16 millions de points.
        if ($infos === false || $infos[2] !== IMAGETYPE_PNG || $infos[0] < 1 || $infos[1] < 1
            || $infos[0] > 8000 || $infos[1] > 8000 || $infos[0] * $infos[1] > 16000000) {
            http_response_code(422);
            exit;
        }
        $octets = (string) file_get_contents($tmp);
        if (function_exists('imagecreatefromstring') && ($image = @imagecreatefromstring($octets)) !== false) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            ob_start();
            $ecrit = imagepng($image);
            $propre = (string) ob_get_clean();
            if ($ecrit && $propre !== '') {
                $octets = $propre;
            }
        }

        $dossier = (string) Config::get('app', 'dossier_uploads');
        if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            http_response_code(500);
            exit;
        }
        $stocke = bin2hex(random_bytes(16)) . '.png';
        if (file_put_contents($dossier . DIRECTORY_SEPARATOR . $stocke, $octets) === false) {
            http_response_code(500);
            exit;
        }

        // Le nom : celui de la carte, sans les signes qu'un système de fichiers refuse ; un nom déjà pris reçoit un numéro.
        $base = trim((string) preg_replace('/[\\\\\/:*?"<>|]+/', ' ', (string) $carte['titre'])) ?: 'carte';
        $base = mb_substr(t('cm.image_nom', ['titre' => $base]), 0, 240);
        $deja = array_column(Database::all(
            'SELECT nom_origine FROM fichiers WHERE cours_id = ? AND pour_fiche = 1', [(int) $carte['cours_id']]), 'nom_origine');
        $nom = $base . '.png';
        for ($n = 2; in_array($nom, $deja, true); $n++) {
            $nom = $base . ' (' . $n . ').png';
        }
        Database::run(
            'INSERT INTO fichiers (user_id, cours_id, pour_fiche, nom_origine, nom_stocke, mime, taille) VALUES (?, ?, 1, ?, ?, ?, ?)',
            [$userId, (int) $carte['cours_id'], $nom, $stocke, 'image/png', strlen($octets)]
        );
        Partages::suivreAjouts($userId, 'fiche', (int) $carte['cours_id'], 1);

        http_response_code(204);
        exit;
    }

    /** Efface la carte, et revient à la fiche du cours. */
    public function supprimer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $carte = $this->carte($id, Auth::id());

        Database::run('DELETE FROM cartes_mentales WHERE id = ? AND user_id = ?', [$id, Auth::id()]);
        Session::flash('succes', t('cm.fl.supprimee'));
        // Depuis une fenêtre, « fenetre=1 » suit (voir redirect) : la fiche du cours remplace la carte effacée.
        redirect('revision/' . (int) $carte['cours_id']);
    }

    // --- Dedans ---------------------------------------------------------------

    /** Une carte de l'utilisateur, ou une page introuvable. */
    private function carte(int $id, int $userId): array
    {
        $carte = Database::one('SELECT * FROM cartes_mentales WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($carte === null) {
            $this->introuvable();
        }

        return $carte;
    }

    /** Un cours de l'utilisateur, ou une page introuvable. */
    private function cours(int $id, int $userId): array
    {
        $cours = Database::one('SELECT id, titre FROM cours WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($cours === null) {
            $this->introuvable();
        }

        return $cours;
    }

    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], t('titre.introuvable'));
        exit;
    }
}
