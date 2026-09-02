<?php
declare(strict_types=1);

/** Gestion des pièces jointes des cours. */
final class Fichiers
{
    /**
     * Enregistre les fichiers téléversés et renvoie la liste des erreurs rencontrées.
     * @return string[]
     */
    public static function enregistrer(array $fichiers, int $coursId, int $userId): array
    {
        $erreurs = [];
        $dossier = (string) Config::get('app', 'dossier_uploads');
        $tailleMax = (int) Config::get('app', 'taille_max_fichier');
        $extensions = (array) Config::get('app', 'extensions_autorisees');

        if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            return ['Impossible de créer le dossier de stockage des fichiers.'];
        }

        $nombre = count($fichiers['name'] ?? []);
        for ($i = 0; $i < $nombre; $i++) {
            $code = $fichiers['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($code === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $nomOrigine = (string) ($fichiers['name'][$i] ?? '');

            if ($code !== UPLOAD_ERR_OK) {
                $erreurs[] = sprintf('« %s » : %s', $nomOrigine, self::messageErreur((int) $code));
                continue;
            }

            $tmp = (string) $fichiers['tmp_name'][$i];
            if (!is_uploaded_file($tmp)) {
                $erreurs[] = sprintf('« %s » : transfert invalide.', $nomOrigine);
                continue;
            }

            $taille = (int) ($fichiers['size'][$i] ?? 0);
            if ($taille > $tailleMax) {
                $erreurs[] = sprintf(
                    '« %s » dépasse la taille maximale (%s).',
                    $nomOrigine,
                    taille_lisible($tailleMax)
                );
                continue;
            }

            $ext = strtolower(pathinfo($nomOrigine, PATHINFO_EXTENSION));
            if ($ext === '' || !in_array($ext, $extensions, true)) {
                $erreurs[] = sprintf('« %s » : type de fichier non autorisé.', $nomOrigine);
                continue;
            }

            $mime = self::detecterMime($tmp) ?: 'application/octet-stream';
            $nomStocke = bin2hex(random_bytes(16)) . '.' . $ext;
            $destination = $dossier . DIRECTORY_SEPARATOR . $nomStocke;

            if (!move_uploaded_file($tmp, $destination)) {
                $erreurs[] = sprintf('« %s » : échec de l\'enregistrement sur le disque.', $nomOrigine);
                continue;
            }

            Database::run(
                'INSERT INTO fichiers (user_id, cours_id, nom_origine, nom_stocke, mime, taille)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$userId, $coursId, mb_substr($nomOrigine, 0, 255), $nomStocke, $mime, $taille]
            );
        }

        return $erreurs;
    }

    /** Supprime un fichier (base + disque) s'il appartient à l'utilisateur. */
    public static function supprimer(int $fichierId, int $userId): bool
    {
        $fichier = Database::one(
            'SELECT * FROM fichiers WHERE id = ? AND user_id = ?',
            [$fichierId, $userId]
        );
        if ($fichier === null) {
            return false;
        }
        $chemin = Config::get('app', 'dossier_uploads') . DIRECTORY_SEPARATOR . $fichier['nom_stocke'];
        if (is_file($chemin)) {
            @unlink($chemin);
        }
        // La copie d'avant la première modification n'a plus lieu d'être.
        $origine = EditionDocument::dossierVersions() . DIRECTORY_SEPARATOR . $fichier['nom_stocke'];
        if (is_file($origine)) {
            @unlink($origine);
        }
        Database::run('DELETE FROM fichiers WHERE id = ?', [$fichierId]);
        return true;
    }

    /** Envoie le fichier au navigateur (affichage en ligne ou téléchargement). */
    public static function envoyer(array $fichier, bool $telecharger): never
    {
        $chemin = Config::get('app', 'dossier_uploads') . DIRECTORY_SEPARATOR . $fichier['nom_stocke'];
        if (!is_file($chemin)) {
            http_response_code(404);
            exit('Fichier introuvable sur le disque.');
        }

        $mime = $fichier['mime'];
        // On ne rend en ligne que les types sûrs ; le reste est forcé en téléchargement.
        $enLigneAutorise = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'text/plain'];
        $disposition = ($telecharger || !in_array($mime, $enLigneAutorise, true)) ? 'attachment' : 'inline';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($chemin));
        header('X-Content-Type-Options: nosniff');

        /*
         * Le lecteur PDF intégré des navigateurs charge le document comme un
         * objet embarqué : « object-src 'none' » le bloque, et le navigateur
         * se rabat alors sur le téléchargement. On l'autorise donc, mais
         * seulement depuis notre propre origine, et seulement pour un PDF
         * affiché en ligne. Tout le reste garde la politique la plus stricte.
         */
        $pdfEnLigne = $disposition === 'inline' && $mime === 'application/pdf';
        header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; object-src '
            . ($pdfEnLigne ? '\'self\'; frame-src \'self\'' : '\'none\''));
        header(sprintf(
            "Content-Disposition: %s; filename*=UTF-8''%s",
            $disposition,
            rawurlencode($fichier['nom_origine'])
        ));
        readfile($chemin);
        exit;
    }

    public static function estImage(string $mime): bool
    {
        return str_starts_with($mime, 'image/');
    }

    public static function icone(string $mime, string $nom): string
    {
        $ext = strtolower(pathinfo($nom, PATHINFO_EXTENSION));
        if (self::estImage($mime)) {
            return '🖼️';
        }
        return match (true) {
            $ext === 'pdf'                              => '📕',
            in_array($ext, ['doc', 'docx', 'odt'], true) => '📄',
            in_array($ext, ['ppt', 'pptx', 'odp'], true) => '📊',
            in_array($ext, ['xls', 'xlsx', 'ods', 'csv'], true) => '📈',
            in_array($ext, ['txt', 'md'], true)           => '📝',
            in_array($ext, ['zip', 'rar', '7z'], true)   => '🗜️',
            in_array($ext, ['mp3', 'm4a'], true)         => '🎧',
            $ext === 'mp4'                               => '🎬',
            default                                      => '📎',
        };
    }

    private static function detecterMime(string $chemin): ?string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $chemin);
                finfo_close($finfo);
                return $mime !== false ? $mime : null;
            }
        }
        return null;
    }

    private static function messageErreur(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'fichier trop volumineux pour le serveur (voir upload_max_filesize dans php.ini).',
            UPLOAD_ERR_PARTIAL   => 'transfert interrompu.',
            UPLOAD_ERR_NO_TMP_DIR => 'dossier temporaire manquant sur le serveur.',
            UPLOAD_ERR_CANT_WRITE => 'écriture impossible sur le disque.',
            UPLOAD_ERR_EXTENSION => 'transfert bloqué par une extension PHP.',
            default              => 'erreur inconnue.',
        };
    }
}
