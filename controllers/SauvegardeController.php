<?php
declare(strict_types=1);

/** Export et restauration des données du compte. */
final class SauvegardeController
{
    /** Taille maximale d'une archive déposée. */
    private const TAILLE_MAX = 200 * 1024 * 1024;

    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $tables = ['matieres', 'dossiers', 'tags', 'types_evenement', 'categories_budget',
            'cours', 'cours_tag', 'fichiers', 'evenements', 'recurrences', 'soldes_saisis',
            'operations', 'reglements', 'listes_taches', 'taches'];
        Vue::afficher('compte/sauvegarde', [
            'resume' => Sauvegarde::resume($userId),
            // Le nom lisible de chaque table vient du fichier de langue.
            'libelles' => array_combine($tables, array_map(
                static fn (string $table): string => t('svg.table.' . $table), $tables)),
        ], t('svg.titre'));
    }

    public function exporter(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        // Une archive volumineuse peut prendre du temps à assembler.
        set_time_limit(0);

        try {
            Sauvegarde::telecharger($userId);
        } catch (Throwable $e) {
            Session::flash('erreur', 'La sauvegarde a échoué : ' . $e->getMessage());
            redirect('compte/sauvegarde');
        }
    }

    public function restaurer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        if (!isset($_POST['confirmation'])) {
            Session::flash('erreur', 'Cochez la case de confirmation : la restauration remplace vos données.');
            redirect('compte/sauvegarde');
        }

        $fichier = $_FILES['archive'] ?? null;
        if (!is_array($fichier) || ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            Session::flash('erreur', 'Choisissez le fichier de sauvegarde à restaurer.');
            redirect('compte/sauvegarde');
        }
        if ($fichier['error'] !== UPLOAD_ERR_OK) {
            Session::flash('erreur',
                'Le transfert a échoué. Une archive volumineuse peut dépasser la limite du serveur.');
            redirect('compte/sauvegarde');
        }
        if ($fichier['size'] > self::TAILLE_MAX) {
            Session::flash('erreur', 'Archive trop volumineuse (maximum ' . taille_lisible(self::TAILLE_MAX) . ').');
            redirect('compte/sauvegarde');
        }
        if (strtolower(pathinfo((string) $fichier['name'], PATHINFO_EXTENSION)) !== 'zip') {
            Session::flash('erreur', 'Le fichier attendu est l’archive .zip produite par la sauvegarde.');
            redirect('compte/sauvegarde');
        }

        set_time_limit(0);

        try {
            $bilan = Sauvegarde::restaurer($userId, (string) $fichier['tmp_name']);
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
            redirect('compte/sauvegarde');
        }

        Session::flash('succes', sprintf(
            'Restauration terminée : %d ligne%s et %d pièce%s jointe%s.',
            $bilan['lignes'],
            $bilan['lignes'] > 1 ? 's' : '',
            $bilan['fichiers'],
            $bilan['fichiers'] > 1 ? 's' : '',
            $bilan['fichiers'] > 1 ? 's' : ''
        ));
        redirect('compte/sauvegarde');
    }
}
