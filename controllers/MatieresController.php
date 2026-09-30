<?php
declare(strict_types=1);

final class MatieresController
{
    /** Palette proposée dans le formulaire. */
    public const PALETTE = [
        '#4f46e5', '#0ea5e9', '#059669', '#65a30d', '#ca8a04',
        '#ea580c', '#dc2626', '#db2777', '#7c3aed', '#475569',
    ];

    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $matieres = Database::all(
            'SELECT m.*,
                    (SELECT COUNT(*) FROM cours c      WHERE c.matiere_id = m.id) AS nb_cours,
                    (SELECT COUNT(*) FROM evenements e WHERE e.matiere_id = m.id) AS nb_evenements
             FROM matieres m
             WHERE m.user_id = ?
             ORDER BY m.nom',
            [$userId]
        );

        Vue::afficher('matieres/index', [
            'matieres' => $matieres,
            'palette'  => self::PALETTE,
            'sansMatiere' => (int) Database::valeur(
                'SELECT COUNT(*) FROM cours WHERE user_id = ? AND matiere_id IS NULL',
                [$userId]
            ),
        ], t('mat.titre'));
    }

    public function creer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $nom = mb_substr(post('nom'), 0, 120);
        if ($nom === '') {
            Session::flash('erreur', t('flash.matiere_nom_obligatoire'));
            redirect('organisation/matieres');
        }
        if (Database::valeur('SELECT id FROM matieres WHERE user_id = ? AND nom = ?', [$userId, $nom]) !== null) {
            Session::flash('erreur', t('flash.matiere_deja', ['nom' => $nom]));
            redirect('organisation/matieres');
        }

        Database::run(
            'INSERT INTO matieres (user_id, nom, couleur, enseignant) VALUES (?, ?, ?, ?)',
            [$userId, $nom, $this->couleurValide(post('couleur')), mb_substr(post('enseignant'), 0, 120) ?: null]
        );

        Session::flash('succes', t('flash.matiere_creee', ['nom' => $nom]));
        redirect('organisation/matieres');
    }

    public function modifier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        if (Database::valeur('SELECT id FROM matieres WHERE id = ? AND user_id = ?', [$id, $userId]) === null) {
            http_response_code(404);
            Vue::afficher('erreurs/404', [], t('titre.introuvable'));
            return;
        }

        $nom = mb_substr(post('nom'), 0, 120);
        if ($nom === '') {
            Session::flash('erreur', t('flash.matiere_nom_obligatoire'));
            redirect('organisation/matieres');
        }

        $doublon = Database::valeur(
            'SELECT id FROM matieres WHERE user_id = ? AND nom = ? AND id <> ?',
            [$userId, $nom, $id]
        );
        if ($doublon !== null) {
            Session::flash('erreur', t('flash.matiere_autre_deja'));
            redirect('organisation/matieres');
        }

        Database::run(
            'UPDATE matieres SET nom = ?, couleur = ?, enseignant = ? WHERE id = ? AND user_id = ?',
            [$nom, $this->couleurValide(post('couleur')), mb_substr(post('enseignant'), 0, 120) ?: null, $id, $userId]
        );

        Session::flash('succes', t('flash.matiere_maj'));
        redirect('organisation/matieres');
    }

    public function supprimer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        // Les cours et évènements liés sont conservés : leur matière passe simplement à NULL.
        Database::run('DELETE FROM matieres WHERE id = ? AND user_id = ?', [$id, Auth::id()]);
        Session::flash('succes', t('flash.matiere_supprimee'));
        redirect('organisation/matieres');
    }

    private function couleurValide(string $couleur): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $couleur) === 1 ? strtolower($couleur) : '#4f46e5';
    }
}
