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
