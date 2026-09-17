<?php
declare(strict_types=1);

/**
 * Partager un cours ou un fichier, en lecture.
 *
 * Deux façons, qui se cumulent :
 *  - avec ses amis (ou un groupe) : chacun reçoit un accès, qui paraît dans
 *    « Partagés avec moi », et une carte dans la discussion ;
 *  - avec n'importe qui : un lien public, que le propriétaire peut désactiver
 *    à tout moment. Qui a le lien voit et télécharge, sans compte.
 *
 * Rien n'est copié : le partage montre le cours tel qu'il est aujourd'hui, et
 * disparaît avec lui. Un ami peut en faire sa propre copie.
 *
 * Partager un cours ouvre aussi ses fichiers joints (pas ceux de sa fiche de
 * révision, qui restent à soi). Une fiche de révision se partage à part, avec
 * ses fichiers et ses liens : on la désigne par son cours.
 */
final class Partages
{
    public const TYPES = ['cours', 'fichier', 'fiche'];

    /** Ce que c'est, en quelques mots : « Cours partagé »… */
    public static function libelle(string $type): string
    {
        return match ($type) {
            'cours' => 'Cours partagé',
            'fiche' => 'Fiche partagée',
            default => 'Fichier partagé',
        };
    }

    /** L'adresse d'un type : « cours », « fiches » ou « fichiers ». */
    public static function mot(string $type): string
    {
        return match ($type) {
            'cours' => 'cours',
            'fiche' => 'fiches',
            default => 'fichiers',
        };
    }

    /** L'icône de partage : trois points reliés, du trait des autres icônes. */
    public static function icone(int $taille = 18): string
    {
        return '<svg class="icone-partage" viewBox="0 0 24 24" width="' . $taille . '" height="' . $taille . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>'
            . '<path d="M8.6 13.5l6.8 4"/><path d="M15.4 6.5l-6.8 4"/></svg>';
    }

    /** Destinataires au plus en un envoi. */
    private const ENVOI_MAX = 50;

    /**
     * Ce qu'on partage, s'il existe : son propriétaire, son titre et, pour un
     * fichier, de quoi le servir.
     */
    public static function cible(string $type, int $id): ?array
    {
        if ($type === 'cours') {
            return Database::one(
                "SELECT c.id, c.user_id, c.titre, c.contenu, c.updated_at, m.nom AS matiere_nom, COALESCE(u.pseudo, '') AS proprietaire,
                        (SELECT COUNT(*) FROM fichiers f WHERE f.cours_id = c.id AND f.pour_fiche = 0) AS nb_fichiers
                   FROM cours c JOIN users u ON u.id = c.user_id LEFT JOIN matieres m ON m.id = c.matiere_id
                  WHERE c.id = ?",
                [$id]
            );
        }
        if ($type === 'fiche') {
            $fiche = Database::one(
                "SELECT c.id, c.user_id, c.titre AS titre_cours, c.fiche_revision, c.updated_at, m.nom AS matiere_nom, COALESCE(u.pseudo, '') AS proprietaire,
                        (SELECT COUNT(*) FROM fichiers f WHERE f.cours_id = c.id AND f.pour_fiche = 1) AS nb_fichiers
                   FROM cours c JOIN users u ON u.id = c.user_id LEFT JOIN matieres m ON m.id = c.matiere_id
                  WHERE c.id = ?",
                [$id]
            );

            return $fiche === null ? null : ['titre' => 'Fiche — ' . $fiche['titre_cours']] + $fiche;
        }
        if ($type === 'fichier') {
            return Database::one(
                "SELECT f.id, f.user_id, f.cours_id, f.pour_fiche, f.nom_origine AS titre, f.nom_origine, f.nom_stocke, f.mime, f.taille,
                        f.created_at, COALESCE(u.pseudo, '') AS proprietaire
                   FROM fichiers f JOIN users u ON u.id = f.user_id
                  WHERE f.id = ?",
                [$id]
            );
        }

        return null;
    }

    /** Ce qu'on partage, s'il est à soi. */
    public static function mienne(string $type, int $id, int $moi): ?array
    {
        $cible = self::cible($type, $id);

        return $cible !== null && (int) $cible['user_id'] === $moi ? $cible : null;
    }

    /**
     * La personne peut-elle le voir ? Son propriétaire, ou un ami à qui on l'a
     * partagé. Un fichier joint se voit aussi quand son cours est partagé.
     */
    public static function peutVoir(string $type, int $id, int $moi): bool
    {
        $cible = self::cible($type, $id);
        if ($cible === null) {
            return false;
        }
        if ((int) $cible['user_id'] === $moi || self::accesDirect($type, $id, $moi)) {
            return true;
        }

        return $type === 'fichier'
            && self::accesDirect((int) $cible['pour_fiche'] === 1 ? 'fiche' : 'cours', (int) $cible['cours_id'], $moi);
    }

    /** Les fichiers d'une fiche de révision partagée. */
    public static function fichiersDeLaFiche(int $coursId): array
    {
        return Database::all(
            'SELECT id, nom_origine, nom_stocke, mime, taille FROM fichiers WHERE cours_id = ? AND pour_fiche = 1 ORDER BY nom_origine',
            [$coursId]
        );
    }

    /** Les liens d'une fiche de révision (ses renvois vers d'autres cours et évènements restent privés). */
    public static function liensDeLaFiche(int $coursId): array
    {
        return Database::all(
            "SELECT libelle, url FROM fiche_elements WHERE cours_id = ? AND type = 'lien' AND (url LIKE 'http://%' OR url LIKE 'https://%') ORDER BY position, id",
            [$coursId]
        );
    }

    private static function accesDirect(string $type, int $id, int $moi): bool
    {
        return Database::valeur(
            'SELECT 1 FROM partages_amis WHERE destinataire_id = ? AND cible_type = ? AND cible_id = ?',
            [$moi, $type, $id]
        ) !== null;
    }

    /** Les fichiers joints d'un cours partagé (sa fiche de révision reste privée). */
    public static function fichiersDuCours(int $coursId): array
    {
        return Database::all(
            'SELECT id, nom_origine, nom_stocke, mime, taille FROM fichiers WHERE cours_id = ? AND pour_fiche = 0 ORDER BY nom_origine',
            [$coursId]
        );
    }

    /**
     * Partage avec des amis et des groupes : un accès pour chacun, et une
     * carte dans la discussion.
     *
     * @return array{0: int, 1: ?string, 2: list<int>} les personnes atteintes, la raison d'un refus, les notifications en file
     */
    public static function partagerAvecAmis(int $moi, string $type, int $id, array $amis, array $groupes, string $texte): array
    {
        $cible = self::mienne($type, $id, $moi);
        if ($cible === null) {
            return [0, 'Ce document est introuvable.', []];
        }
        $texte = trim(str_replace(["\r\n", "\r"], "\n", $texte));
        if (mb_strlen($texte) > Amis::MESSAGE_MAX) {
            return [0, 'Le message ne peut pas dépasser ' . Amis::MESSAGE_MAX . ' caractères.', []];
        }
        $amis = array_values(array_unique(array_filter(array_map('intval', $amis), static fn (int $a): bool => $a > 0 && $a !== $moi)));
        $groupes = array_values(array_unique(array_filter(array_map('intval', $groupes), static fn (int $g): bool => $g > 0)));
        if ($amis === [] && $groupes === []) {
            return [0, 'Choisissez au moins un ami ou un groupe.', []];
        }
        if (count($amis) + count($groupes) > self::ENVOI_MAX) {
            return [0, 'Pas plus de ' . self::ENVOI_MAX . ' destinataires à la fois.', []];
        }
        foreach ($amis as $a) {
            if (!Amis::sontAmis($moi, $a)) {
                return [0, 'Vous ne pouvez partager qu’avec vos amis.', []];
            }
        }
        foreach ($groupes as $g) {
            if (Conversations::membre($g, $moi) === null) {
                return [0, 'Vous ne faites pas partie de ce groupe.', []];
            }
        }

        $atteints = [];
        $notifications = [];
        $pseudo = (string) (Amis::compte($moi)['pseudo'] ?? 'Un ami');
        $quoi = match ($type) { 'cours' => 'le cours', 'fiche' => 'la fiche de révision', default => 'le fichier' }
            . ' « ' . mb_strimwidth((string) ($cible['titre_cours'] ?? $cible['titre']), 0, 80, '…') . ' »';

        foreach ($amis as $a) {
            self::donnerAcces($moi, $a, $type, $id);
            $atteints[$a] = true;
            Database::run(
                'INSERT INTO messages (expediteur_id, destinataire_id, texte, partage_type, partage_id, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$moi, $a, $texte, $type, $id]
            );
            $n = Amis::notifier($moi, $a, '🔗 ' . $pseudo . ' a partagé ' . $quoi . ($texte === '' ? '' : ' · ' . $texte));
            if ($n !== null) {
                $notifications[] = $n;
            }
        }
        foreach ($groupes as $g) {
            foreach (Conversations::membres($g) as $membre) {
                if ($membre['id'] !== $moi) {
                    self::donnerAcces($moi, $membre['id'], $type, $id);
                    $atteints[$membre['id']] = true;
                }
            }
            Database::run(
                'INSERT INTO conversation_messages (conversation_id, expediteur_id, texte, partage_type, partage_id, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [$g, $moi, $texte, $type, $id]
            );
            $dernier = Database::dernierId();
            Database::run(
                'UPDATE conversation_membres SET lu_jusqua = GREATEST(lu_jusqua, ?) WHERE conversation_id = ? AND user_id = ?',
                [$dernier, $g, $moi]
            );
            array_push($notifications, ...Conversations::notifier($moi, $g, '🔗 a partagé ' . $quoi . ($texte === '' ? '' : ' · ' . $texte)));
        }

        return [count($atteints), null, $notifications];
    }

    private static function donnerAcces(int $moi, int $destinataire, string $type, int $id): void
    {
        Database::run(
            'INSERT IGNORE INTO partages_amis (destinataire_id, cible_type, cible_id, proprietaire_id, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
            [$destinataire, $type, $id, $moi]
        );
    }

    /** Retire l'accès d'un ami (la carte déjà envoyée ne mène plus à rien pour lui). */
    public static function retirerAcces(int $moi, string $type, int $id, int $destinataire): bool
    {
        return Database::run(
            'DELETE FROM partages_amis WHERE destinataire_id = ? AND cible_type = ? AND cible_id = ? AND proprietaire_id = ?',
            [$destinataire, $type, $id, $moi]
        )->rowCount() > 0;
    }

    /** Les personnes qui ont accès, par pseudo. */
    public static function destinataires(int $moi, string $type, int $id): array
    {
        return array_map(static fn (array $l): array => ['id' => (int) $l['id'], 'pseudo' => (string) $l['pseudo']], Database::all(
            "SELECT u.id, COALESCE(u.pseudo, '') AS pseudo FROM partages_amis p JOIN users u ON u.id = p.destinataire_id
              WHERE p.proprietaire_id = ? AND p.cible_type = ? AND p.cible_id = ? ORDER BY u.pseudo",
            [$moi, $type, $id]
        ));
    }

    /**
     * Ce qu'on m'a partagé, du plus récent au plus ancien — seulement ce qui
     * existe encore, et que son propriétaire est toujours mon ami.
     */
    public static function recus(int $moi): array
    {
        $lignes = Database::all(
            "SELECT p.cible_type, p.cible_id, p.proprietaire_id, p.created_at, COALESCE(u.pseudo, '') AS proprietaire,
                    c.titre AS titre_cours, c.contenu, f.nom_origine, f.mime, f.taille,
                    cf.titre AS titre_fiche, cf.fiche_revision
               FROM partages_amis p
               JOIN users u ON u.id = p.proprietaire_id
               LEFT JOIN cours c ON p.cible_type = 'cours' AND c.id = p.cible_id AND c.user_id = p.proprietaire_id
               LEFT JOIN fichiers f ON p.cible_type = 'fichier' AND f.id = p.cible_id AND f.user_id = p.proprietaire_id
               LEFT JOIN cours cf ON p.cible_type = 'fiche' AND cf.id = p.cible_id AND cf.user_id = p.proprietaire_id
              WHERE p.destinataire_id = ? AND (c.id IS NOT NULL OR f.id IS NOT NULL OR cf.id IS NOT NULL)
              ORDER BY p.created_at DESC",
            [$moi]
        );
        $recus = [];
        foreach ($lignes as $l) {
            if (!Amis::sontAmis($moi, (int) $l['proprietaire_id'])
                && !self::partagentUnGroupe($moi, (int) $l['proprietaire_id'])) {
                continue;
            }
            $type = (string) $l['cible_type'];
            $recus[] = [
                'type' => $type,
                'id' => (int) $l['cible_id'],
                'titre' => match ($type) { 'cours' => (string) $l['titre_cours'], 'fiche' => 'Fiche — ' . $l['titre_fiche'], default => (string) $l['nom_origine'] },
                'icone' => match ($type) { 'cours' => '📘', 'fiche' => '📝', default => Fichiers::icone((string) $l['mime'], (string) $l['nom_origine']) },
                'detail' => match ($type) {
                    'cours' => extrait((string) $l['contenu']),
                    'fiche' => extrait((string) $l['fiche_revision']),
                    default => taille_lisible((int) $l['taille']),
                },
                'proprietaire' => (string) $l['proprietaire'],
                'proprietaire_id' => (int) $l['proprietaire_id'],
                'quand' => (string) $l['created_at'],
                'url' => self::adresse((string) $l['cible_type'], (int) $l['cible_id']),
            ];
        }

        return $recus;
    }

    private static function partagentUnGroupe(int $a, int $b): bool
    {
        return Database::valeur(
            'SELECT 1 FROM conversation_membres x JOIN conversation_membres y ON y.conversation_id = x.conversation_id
              WHERE x.user_id = ? AND y.user_id = ? LIMIT 1',
            [$a, $b]
        ) !== null;
    }

    /** Retire un partage reçu de sa liste (le propriétaire n'en sait rien). */
    public static function oublierRecu(int $moi, string $type, int $id): bool
    {
        return Database::run(
            'DELETE FROM partages_amis WHERE destinataire_id = ? AND cible_type = ? AND cible_id = ?',
            [$moi, $type, $id]
        )->rowCount() > 0;
    }

    /** L'adresse de lecture d'un partage, pour un compte. */
    public static function adresse(string $type, int $id): string
    {
        return url('partages/' . self::mot($type) . '/' . $id);
    }

    /** Le lien public d'un document, s'il en a un. */
    public static function lien(string $type, int $id): ?array
    {
        return Database::one('SELECT * FROM liens_partage WHERE cible_type = ? AND cible_id = ?', [$type, $id]);
    }

    /** Crée le lien public (ou rend celui qui existe). */
    public static function creerLien(int $moi, string $type, int $id): ?array
    {
        if (self::mienne($type, $id, $moi) === null) {
            return null;
        }
        Database::run(
            'INSERT IGNORE INTO liens_partage (user_id, cible_type, cible_id, jeton, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
            [$moi, $type, $id, bin2hex(random_bytes(16))]
        );

        return self::lien($type, $id);
    }

    /** Désactive le lien public : l'adresse ne mène plus à rien, même si elle a circulé. */
    public static function desactiverLien(int $moi, string $type, int $id): bool
    {
        return Database::run(
            'DELETE FROM liens_partage WHERE user_id = ? AND cible_type = ? AND cible_id = ?',
            [$moi, $type, $id]
        )->rowCount() > 0;
    }

    /** Un lien public valable, et ce qu'il montre. */
    public static function parJeton(string $jeton): ?array
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $jeton)) {
            return null;
        }
        $lien = Database::one('SELECT * FROM liens_partage WHERE jeton = ?', [$jeton]);
        if ($lien === null) {
            return null;
        }
        $cible = self::cible((string) $lien['cible_type'], (int) $lien['cible_id']);
        if ($cible === null || (int) $cible['user_id'] !== (int) $lien['user_id']) {
            return null;
        }

        return ['lien' => $lien, 'cible' => $cible];
    }

    /** Compte une ouverture du lien public (pas ses téléchargements). */
    public static function compterVue(int $lienId): void
    {
        Database::run('UPDATE liens_partage SET vues = vues + 1 WHERE id = ?', [$lienId]);
    }

    /** L'adresse complète d'un lien public, à copier. */
    public static function adresseLien(string $jeton): string
    {
        $site = Reinitialisation::adresseDuSite();
        if ($site === null) {
            $https = (string) ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';
            $site = ($https ? 'https' : 'http') . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }

        return $site . url('p/' . $jeton);
    }

    /** Oublie tout partage d'un document qu'on supprime. */
    public static function oublier(string $type, int $id): void
    {
        Database::run('DELETE FROM partages_amis WHERE cible_type = ? AND cible_id = ?', [$type, $id]);
        Database::run('DELETE FROM liens_partage WHERE cible_type = ? AND cible_id = ?', [$type, $id]);
    }

    /**
     * Copie un document partagé dans ses propres cours : un cours entier avec
     * ses fichiers joints, ou un fichier rangé dans le cours qu'on choisit.
     *
     * @return array{0: ?int, 1: ?string} le cours où la copie se trouve, ou la raison du refus
     */
    public static function copier(int $moi, string $type, int $id, ?int $coursCible = null): array
    {
        if (!self::peutVoir($type, $id, $moi)) {
            return [null, 'Ce document n’est plus partagé avec vous.'];
        }
        $cible = self::cible($type, $id);
        $dossier = (string) Config::get('app', 'dossier_uploads');

        if ($type === 'cours') {
            Database::run(
                'INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?)',
                [$moi, mb_substr((string) $cible['titre'], 0, 200), (string) $cible['contenu']]
            );
            $nouveau = Database::dernierId();
            foreach (self::fichiersDuCours($id) as $f) {
                self::copierFichier($f, $moi, $nouveau, $dossier);
            }

            return [$nouveau, null];
        }

        // Une fiche devient un cours à soi, dont c'est la fiche de révision.
        if ($type === 'fiche') {
            Database::run(
                "INSERT INTO cours (user_id, titre, contenu, fiche_revision) VALUES (?, ?, '', ?)",
                [$moi, mb_substr((string) $cible['titre_cours'], 0, 200), (string) $cible['fiche_revision']]
            );
            $nouveau = Database::dernierId();
            foreach (self::fichiersDeLaFiche($id) as $f) {
                self::copierFichier($f, $moi, $nouveau, $dossier, true);
            }
            foreach (self::liensDeLaFiche($id) as $position => $lien) {
                Database::run(
                    "INSERT INTO fiche_elements (user_id, cours_id, type, libelle, url, position) VALUES (?, ?, 'lien', ?, ?, ?)",
                    [$moi, $nouveau, $lien['libelle'], $lien['url'], $position]
                );
            }

            return [$nouveau, null];
        }

        if ($coursCible === null || Database::valeur('SELECT 1 FROM cours WHERE id = ? AND user_id = ?', [$coursCible, $moi]) === null) {
            return [null, 'Choisissez un de vos cours pour y ranger le fichier.'];
        }
        if (!self::copierFichier($cible, $moi, $coursCible, $dossier)) {
            return [null, 'Le fichier n’a pas pu être copié.'];
        }

        return [$coursCible, null];
    }

    private static function copierFichier(array $f, int $moi, int $coursId, string $dossier, bool $pourFiche = false): bool
    {
        $source = $dossier . DIRECTORY_SEPARATOR . basename((string) $f['nom_stocke']);
        if (!is_file($source)) {
            return false;
        }
        $extension = strtolower(pathinfo((string) $f['nom_stocke'], PATHINFO_EXTENSION));
        $nom = bin2hex(random_bytes(16)) . ($extension === '' ? '' : '.' . $extension);
        if (!copy($source, $dossier . DIRECTORY_SEPARATOR . $nom)) {
            return false;
        }
        Database::run(
            'INSERT INTO fichiers (user_id, cours_id, pour_fiche, nom_origine, nom_stocke, mime, taille) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$moi, $coursId, $pourFiche ? 1 : 0, (string) $f['nom_origine'], $nom, (string) $f['mime'], (int) $f['taille']]
        );

        return true;
    }

    /**
     * La carte d'un message de partage, du point de vue de qui la lit : le
     * titre, une icône, et où elle mène — rien si le document n'existe plus.
     */
    public static function carte(?string $type, ?int $id, int $moi): ?array
    {
        if ($type === null || $id === null) {
            return null;
        }
        $cible = self::cible($type, $id);
        if ($cible === null) {
            return ['type' => $type, 'titre' => 'Document supprimé', 'icone' => '🚫', 'detail' => 'Il n’est plus disponible.', 'url' => null];
        }
        $visible = self::peutVoir($type, $id, $moi);

        return [
            'type' => $type,
            'titre' => (string) $cible['titre'],
            'icone' => match ($type) { 'cours' => '📘', 'fiche' => '📝', default => Fichiers::icone((string) $cible['mime'], (string) $cible['nom_origine']) },
            'detail' => !$visible ? 'Le partage a été retiré.'
                : ($type === 'fichier'
                    ? 'Fichier · ' . taille_lisible((int) $cible['taille'])
                    : ($type === 'cours' ? 'Cours' : 'Fiche de révision')
                        . ((int) $cible['nb_fichiers'] > 0 ? ' · ' . (int) $cible['nb_fichiers'] . ' fichier' . ((int) $cible['nb_fichiers'] > 1 ? 's' : '') : '')),
            'url' => $visible ? self::adresse($type, $id) : null,
        ];
    }
}
