<?php
declare(strict_types=1);

/** Gestion des pièces jointes des cours. */
final class Fichiers
{
    /** Extensions que le navigateur sait lire dans la page. */
    private const AUDIO = ['mp3', 'm4a', 'wav', 'ogg', 'oga', 'opus', 'aac', 'weba'];

    /**
     * Enregistre les fichiers téléversés et renvoie la liste des erreurs rencontrées.
     *
     * $pourFiche range le fichier dans la fiche de révision du cours plutôt que
     * dans ses pièces jointes : même stockage, même contrôle, autre rayon.
     *
     * @return string[]
     */
    public static function enregistrer(array $fichiers, int $coursId, int $userId, bool $pourFiche = false): array
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
                'INSERT INTO fichiers (user_id, cours_id, pour_fiche, nom_origine, nom_stocke, mime, taille)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$userId, $coursId, $pourFiche ? 1 : 0, mb_substr($nomOrigine, 0, 255), $nomStocke, $mime, $taille]
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
        $enLigne = !$telecharger
            && (in_array($mime, $enLigneAutorise, true) || self::estAudio($mime, (string) $fichier['nom_origine']));
        $disposition = $enLigne ? 'inline' : 'attachment';

        $taille = (int) filesize($chemin);
        [$debut, $fin] = self::plageDemandee($taille, $enLigne);
        $partiel = $debut > 0 || $fin < $taille - 1;

        if ($partiel) {
            http_response_code(206);
            header(sprintf('Content-Range: bytes %d-%d/%d', $debut, $fin, $taille));
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) ($fin - $debut + 1));
        header('X-Content-Type-Options: nosniff');
        // Sans cet en-tête, le navigateur ne propose pas de se déplacer dans
        // un enregistrement : la barre de lecture reste inerte.
        header('Accept-Ranges: bytes');

        /*
         * Le lecteur PDF intégré des navigateurs charge le document comme un
         * objet embarqué : « object-src 'none' » le bloque, et le navigateur
         * se rabat alors sur le téléchargement. On l'autorise donc, mais
         * seulement depuis notre propre origine, et seulement pour un PDF
         * affiché en ligne. Un enregistrement ouvert seul a besoin de la même
         * permission pour « media-src ». Tout le reste garde la politique la
         * plus stricte.
         */
        $politique = 'default-src \'none\'; img-src \'self\'; ';
        if ($enLigne && $mime === 'application/pdf') {
            $politique .= 'object-src \'self\'; frame-src \'self\'';
        } elseif ($enLigne && self::estAudio($mime, (string) $fichier['nom_origine'])) {
            $politique .= 'object-src \'none\'; media-src \'self\'';
        } else {
            $politique .= 'object-src \'none\'';
        }
        header('Content-Security-Policy: ' . $politique);

        header(sprintf(
            "Content-Disposition: %s; filename*=UTF-8''%s",
            $disposition,
            rawurlencode($fichier['nom_origine'])
        ));

        self::diffuser($chemin, $debut, $fin);
        exit;
    }

    /**
     * La portion demandée par l'en-tête « Range », ou le fichier entier.
     *
     * Un navigateur qui lit un enregistrement ne le télécharge pas d'un bloc :
     * il en réclame des morceaux, et c'est ainsi qu'on peut se déplacer dedans.
     *
     * @return array{0: int, 1: int} premier et dernier octet, inclus
     */
    private static function plageDemandee(int $taille, bool $enLigne): array
    {
        $entier = [0, max(0, $taille - 1)];
        $entete = (string) ($_SERVER['HTTP_RANGE'] ?? '');

        if (!$enLigne || $taille === 0 || preg_match('/^bytes=(\d*)-(\d*)$/', $entete, $m) !== 1) {
            return $entier;
        }

        if ($m[1] === '' && $m[2] === '') {
            return $entier;
        }
        if ($m[1] === '') {
            // « bytes=-500 » : les 500 derniers octets.
            $debut = max(0, $taille - (int) $m[2]);
            $fin = $taille - 1;
        } else {
            $debut = (int) $m[1];
            $fin = $m[2] === '' ? $taille - 1 : min((int) $m[2], $taille - 1);
        }

        // Une demande incohérente est ignorée : on renvoie tout, comme avant.
        return ($debut > $fin || $debut >= $taille) ? $entier : [$debut, $fin];
    }

    /** Envoie une portion du fichier, par morceaux plutôt qu'en une bouchée. */
    private static function diffuser(string $chemin, int $debut, int $fin): void
    {
        $flux = fopen($chemin, 'rb');
        if ($flux === false) {
            return;
        }
        try {
            fseek($flux, $debut);
            $reste = $fin - $debut + 1;
            while ($reste > 0 && !feof($flux)) {
                $morceau = fread($flux, (int) min(262144, $reste));
                if ($morceau === false || $morceau === '') {
                    break;
                }
                echo $morceau;
                $reste -= strlen($morceau);
            }
        } finally {
            fclose($flux);
        }
    }

    /** Un enregistrement, que le navigateur sait lire dans la page. */
    public static function estAudio(string $mime, string $nom = ''): bool
    {
        if (str_starts_with($mime, 'audio/')) {
            return true;
        }
        // Un .m4a mal détecté ressort parfois en « video/mp4 » ou en octet-stream.
        return in_array(strtolower(pathinfo($nom, PATHINFO_EXTENSION)), self::AUDIO, true);
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
            in_array($ext, self::AUDIO, true)            => '🎧',
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
