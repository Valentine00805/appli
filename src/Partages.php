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
 *
 * Partager un dossier ouvre tous les cours qu'il contient, y compris ceux de
 * ses sous-dossiers : ce qu'on y range ensuite est partagé aussi, ce qu'on en
 * sort ne l'est plus. Les fiches de révision de ces cours restent à soi.
 *
 * Chaque partage porte un droit : lire, commenter, ou modifier. Il vaut pour
 * ce qu'on partage et pour ce qui en dépend — le droit donné sur un dossier
 * vaut pour ses cours, celui d'un cours pour ses fichiers joints. Le
 * propriétaire le change ou le reprend quand il veut ; un lien public, lui,
 * ne donne jamais que la lecture.
 */
final class Partages
{
    public const TYPES = ['cours', 'fichier', 'fiche', 'dossier', 'evenement'];

    /** Du plus restreint au plus large : l'ordre compte pour les comparer. */
    public const DROITS = ['lecture', 'commentaire', 'modification'];

    /** Ce qu'un droit permet, en un mot. */
    public static function libelleDroit(string $droit): string
    {
        return match ($droit) {
            'modification' => 'Modification',
            'commentaire' => 'Commentaire',
            default => 'Lecture seule',
        };
    }

    /** Ce qu'il permet, en une phrase : pour l'expliquer avant de choisir. */
    public static function expliqueDroit(string $droit): string
    {
        return match ($droit) {
            'modification' => 'Écrire dans le document, y joindre des fichiers et en retirer.',
            'commentaire' => 'Lire, et écrire des commentaires sous le document.',
            default => 'Ouvrir le document et ses fichiers, sans rien y changer.',
        };
    }

    /** Un droit reçu d'un formulaire, ou la lecture à défaut. */
    public static function droitValide(mixed $droit): string
    {
        return is_string($droit) && in_array($droit, self::DROITS, true) ? $droit : 'lecture';
    }

    /** Ce droit en permet-il au moins autant que celui qu'on demande ? */
    public static function permet(?string $droit, string $minimum): bool
    {
        return $droit !== null
            && array_search($droit, self::DROITS, true) >= array_search($minimum, self::DROITS, true);
    }

    /** Cours au plus dans la copie d'un dossier : au-delà, on préfère refuser. */
    public const COPIE_MAX = 100;

    /** Ce que c'est, en quelques mots : « Cours partagé »… */
    public static function libelle(string $type): string
    {
        return match ($type) {
            'cours' => 'Cours partagé',
            'fiche' => 'Fiche partagée',
            'dossier' => 'Dossier partagé',
            'evenement' => 'Évènement partagé',
            'lot' => 'Lien de plusieurs documents',
            default => 'Fichier partagé',
        };
    }

    /** L'adresse d'un type : « cours », « fiches », « dossiers » ou « fichiers ». */
    public static function mot(string $type): string
    {
        return match ($type) {
            'cours' => 'cours',
            'fiche' => 'fiches',
            'dossier' => 'dossiers',
            'evenement' => 'evenements',
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

    /** Documents au plus dans un partage groupé : au-delà, autant partager le dossier. */
    public const LOT_MAX = 25;

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
        if ($type === 'dossier') {
            $dossier = Database::one(
                "SELECT d.id, d.user_id, d.nom AS titre, d.icone, d.couleur, d.created_at, COALESCE(u.pseudo, '') AS proprietaire
                   FROM dossiers d JOIN users u ON u.id = d.user_id
                  WHERE d.id = ?",
                [$id]
            );
            if ($dossier === null) {
                return null;
            }
            $dossier['nb_cours'] = (int) Database::valeur(
                'SELECT COUNT(*) FROM cours WHERE user_id = ? AND dossier_id IN ('
                . implode(',', array_fill(0, count($sous = DossiersController::avecDescendants((int) $dossier['user_id'], $id)), '?')) . ')',
                array_merge([(int) $dossier['user_id']], $sous)
            );

            return $dossier;
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
        if ($type === 'evenement') {
            return Database::one(
                "SELECT e.id, e.user_id, e.titre, e.description, e.lieu, e.debut, e.fin, e.journee_entiere,
                        t.nom AS type_nom, t.icone AS type_icone, m.nom AS matiere_nom,
                        COALESCE(u.pseudo, '') AS proprietaire, u.fuseau
                   FROM evenements e JOIN users u ON u.id = e.user_id
                   LEFT JOIN types_evenement t ON t.id = e.type_id
                   LEFT JOIN matieres m ON m.id = e.matiere_id
                  WHERE e.id = ?",
                [$id]
            );
        }
        // Un lot ne se partage pas à ses amis : il n'existe que pour son lien.
        if ($type === 'lot') {
            return Database::one(
                "SELECT l.id, l.user_id, l.nom AS titre, l.created_at, COALESCE(u.pseudo, '') AS proprietaire
                   FROM lots_partage l JOIN users u ON u.id = l.user_id
                  WHERE l.id = ?",
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

        // Un cours se voit aussi quand un dossier qui le contient est partagé.
        if ($type === 'cours') {
            return self::dossierPartage($id, $moi) !== null;
        }
        // Un fichier joint suit son cours ; celui d'une fiche suit la fiche.
        if ($type === 'fichier') {
            return (int) $cible['pour_fiche'] === 1
                ? self::accesDirect('fiche', (int) $cible['cours_id'], $moi)
                : self::peutVoir('cours', (int) $cible['cours_id'], $moi);
        }

        return false;
    }

    /**
     * Les dossiers qui contiennent un cours, du plus proche à la racine :
     * partager un dossier partage aussi ce que contiennent ses sous-dossiers.
     *
     * @return list<int>
     */
    public static function chaineDossiers(int $coursId): array
    {
        $cours = Database::one('SELECT user_id, dossier_id FROM cours WHERE id = ?', [$coursId]);
        if ($cours === null || $cours['dossier_id'] === null) {
            return [];
        }
        $parents = [];
        foreach (Database::all('SELECT id, parent_id FROM dossiers WHERE user_id = ?', [(int) $cours['user_id']]) as $d) {
            $parents[(int) $d['id']] = $d['parent_id'] === null ? null : (int) $d['parent_id'];
        }
        $chaine = [];
        $courant = (int) $cours['dossier_id'];
        // Bornée par le nombre de dossiers, et à l'épreuve d'une boucle.
        while (array_key_exists($courant, $parents) && !in_array($courant, $chaine, true)) {
            $chaine[] = $courant;
            if ($parents[$courant] === null) {
                break;
            }
            $courant = $parents[$courant];
        }

        return $chaine;
    }

    /** Le dossier partagé qui donne accès à ce cours, s'il y en a un. */
    public static function dossierPartage(int $coursId, int $moi): ?int
    {
        foreach (self::chaineDossiers($coursId) as $dossierId) {
            if (self::accesDirect('dossier', $dossierId, $moi)) {
                return $dossierId;
            }
        }

        return null;
    }

    /** Ce cours est-il dans ce dossier, ou dans un de ses sous-dossiers ? */
    public static function dansLeDossier(int $coursId, int $dossierId): bool
    {
        return in_array($dossierId, self::chaineDossiers($coursId), true);
    }

    /**
     * Ce qu'un dossier partagé montre : ses cours, puis ceux de chaque
     * sous-dossier qui en contient, dans l'ordre de l'arborescence.
     *
     * @return list<array{id: int, nom: string, icone: string, profondeur: int, cours: list<array<string, mixed>>}>
     */
    public static function contenuDuDossier(int $dossierId, int $proprietaire): array
    {
        $ids = DossiersController::avecDescendants($proprietaire, $dossierId);
        $arbre = DossiersController::pourUtilisateur($proprietaire);
        $racine = null;
        $groupes = [];
        foreach ($arbre as $d) {
            if (!in_array((int) $d['id'], $ids, true)) {
                continue;
            }
            if ((int) $d['id'] === $dossierId) {
                $racine = (int) $d['profondeur'];
            }
            $cours = Database::all(
                "SELECT c.id, c.titre, c.contenu, c.updated_at, m.nom AS matiere_nom,
                        (SELECT COUNT(*) FROM fichiers f WHERE f.cours_id = c.id AND f.pour_fiche = 0) AS nb_fichiers
                   FROM cours c LEFT JOIN matieres m ON m.id = c.matiere_id
                  WHERE c.user_id = ? AND c.dossier_id = ? ORDER BY c.titre",
                [$proprietaire, (int) $d['id']]
            );
            if ($cours === [] && (int) $d['id'] !== $dossierId) {
                continue;
            }
            $groupes[] = [
                'id' => (int) $d['id'],
                'nom' => (string) $d['nom'],
                'icone' => (string) $d['icone'],
                'profondeur' => (int) $d['profondeur'] - ($racine ?? 0),
                'cours' => $cours,
            ];
        }

        return $groupes;
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
        return self::droitDirect($type, $id, $moi) !== null;
    }

    /** Le droit reçu sur ce document même, s'il y en a un. */
    private static function droitDirect(string $type, int $id, int $moi): ?string
    {
        $droit = Database::valeur(
            'SELECT droit FROM partages_amis WHERE destinataire_id = ? AND cible_type = ? AND cible_id = ?',
            [$moi, $type, $id]
        );

        return $droit === null || $droit === false ? null : self::droitValide($droit);
    }

    /**
     * Ce qu'une personne peut faire de ce document : le sien, elle peut tout ;
     * sinon le droit qu'on lui a donné, directement ou par ce qui le contient.
     * Le plus large l'emporte, et rien du tout vaut « null ».
     */
    public static function droit(string $type, int $id, int $moi): ?string
    {
        $cible = self::cible($type, $id);
        if ($cible === null) {
            return null;
        }
        if ((int) $cible['user_id'] === $moi) {
            return 'modification';
        }
        $droits = [];
        $direct = self::droitDirect($type, $id, $moi);
        if ($direct !== null) {
            $droits[] = $direct;
        }
        // Un cours suit aussi les dossiers qui le contiennent ; un fichier suit
        // son cours, ou la fiche à laquelle il appartient.
        if ($type === 'cours') {
            foreach (self::chaineDossiers($id) as $dossierId) {
                $herite = self::droitDirect('dossier', $dossierId, $moi);
                if ($herite !== null) {
                    $droits[] = $herite;
                }
            }
        } elseif ($type === 'fichier') {
            $herite = (int) $cible['pour_fiche'] === 1
                ? self::droitDirect('fiche', (int) $cible['cours_id'], $moi)
                : self::droit('cours', (int) $cible['cours_id'], $moi);
            if ($herite !== null) {
                $droits[] = $herite;
            }
        }
        if ($droits === []) {
            return null;
        }
        usort($droits, static fn (string $a, string $b): int =>
            array_search($a, self::DROITS, true) <=> array_search($b, self::DROITS, true));

        return end($droits);
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
    public static function partagerAvecAmis(int $moi, string $type, int $id, array $amis, array $groupes, string $texte, string $droit = 'lecture'): array
    {
        $cible = self::mienne($type, $id, $moi);
        if ($cible === null) {
            return [0, 'Ce document est introuvable.', []];
        }
        $texte = trim(str_replace(["\r\n", "\r"], "\n", $texte));
        if (mb_strlen($texte) > Amis::MESSAGE_MAX) {
            return [0, 'Le message ne peut pas dépasser ' . Amis::MESSAGE_MAX . ' caractères.', []];
        }
        [$amis, $groupes, $refus] = self::destinatairesChoisis($moi, $amis, $groupes);
        if ($refus !== null) {
            return [0, $refus, []];
        }

        $atteints = [];
        $notifications = [];
        $pseudo = (string) (Amis::compte($moi)['pseudo'] ?? 'Un ami');
        $quoi = match ($type) { 'cours' => 'le cours', 'fiche' => 'la fiche de révision', 'dossier' => 'le dossier', default => 'le fichier' }
            . ' « ' . mb_strimwidth((string) ($cible['titre_cours'] ?? $cible['titre']), 0, 80, '…') . ' »';

        foreach ($amis as $a) {
            self::donnerAcces($moi, $a, $type, $id, $droit);
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
                    self::donnerAcces($moi, $membre['id'], $type, $id, $droit);
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

    /**
     * Les amis et les groupes retenus d'un envoi : nettoyés, et vérifiés.
     *
     * @return array{0: list<int>, 1: list<int>, 2: ?string}
     */
    private static function destinatairesChoisis(int $moi, array $amis, array $groupes): array
    {
        $amis = array_values(array_unique(array_filter(array_map('intval', $amis), static fn (int $a): bool => $a > 0 && $a !== $moi)));
        $groupes = array_values(array_unique(array_filter(array_map('intval', $groupes), static fn (int $g): bool => $g > 0)));
        if ($amis === [] && $groupes === []) {
            return [[], [], 'Choisissez au moins un ami ou un groupe.'];
        }
        if (count($amis) + count($groupes) > self::ENVOI_MAX) {
            return [[], [], 'Pas plus de ' . self::ENVOI_MAX . ' destinataires à la fois.'];
        }
        foreach ($amis as $a) {
            if (!Amis::sontAmis($moi, $a)) {
                return [[], [], 'Vous ne pouvez partager qu’avec vos amis.'];
            }
        }
        foreach ($groupes as $g) {
            if (Conversations::membre($g, $moi) === null) {
                return [[], [], 'Vous ne faites pas partie de ce groupe.'];
            }
        }

        return [$amis, $groupes, null];
    }

    /**
     * Partage plusieurs documents d'un coup — des cours, des fiches, des
     * dossiers, des fichiers, ou de chaque sorte : un accès et une carte par
     * document, mais une seule notification, qui dit combien.
     *
     * @param array<string, list<int|string>> $parType  les identifiants choisis, par type
     * @return array{0: string, 1: int, 2: ?string, 3: list<int>} ce qui est parti (« 3 cours »), les personnes atteintes, un refus, les notifications
     */
    public static function partagerPlusieurs(int $moi, array $parType, array $amis, array $groupes, string $texte, string $droit = 'lecture'): array
    {
        $texte = trim(str_replace(["\r\n", "\r"], "\n", $texte));
        if (mb_strlen($texte) > Amis::MESSAGE_MAX) {
            return ['', 0, 'Le message ne peut pas dépasser ' . Amis::MESSAGE_MAX . ' caractères.', []];
        }
        $comptes = [];
        foreach (self::TYPES as $type) {
            $ids = is_array($parType[$type] ?? null) ? $parType[$type] : [];
            $comptes[$type] = array_values(array_unique(
                array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)
            ));
        }
        $total = array_sum(array_map('count', $comptes));
        if ($total === 0) {
            return ['', 0, 'Choisissez au moins un document à partager.', []];
        }
        if ($total > self::LOT_MAX) {
            return ['', 0, 'Pas plus de ' . self::LOT_MAX . ' documents à la fois.', []];
        }
        // Les documents, dans l'ordre où ils paraissent, et tous à moi.
        $documents = [];
        foreach ($comptes as $type => $ids) {
            foreach ($ids as $i) {
                $cible = self::mienne($type, $i, $moi);
                if ($cible === null) {
                    return ['', 0, 'Un des documents choisis est introuvable.', []];
                }
                $documents[] = ['type' => $type, 'id' => (int) $cible['id'], 'titre' => (string) $cible['titre']];
            }
        }
        [$amis, $groupes, $refus] = self::destinatairesChoisis($moi, $amis, $groupes);
        if ($refus !== null) {
            return ['', 0, $refus, []];
        }

        $atteints = [];
        $notifications = [];
        $pseudo = (string) (Amis::compte($moi)['pseudo'] ?? 'Un ami');
        $combien = self::combien(array_map('count', $comptes))
            . ' (dont « ' . mb_strimwidth($documents[0]['titre'], 0, 60, '…') . ' »)';

        foreach ($amis as $a) {
            foreach ($documents as $rang => $doc) {
                self::donnerAcces($moi, $a, $doc['type'], $doc['id'], $droit);
                Database::run(
                    'INSERT INTO messages (expediteur_id, destinataire_id, texte, partage_type, partage_id, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                    [$moi, $a, $rang === 0 ? $texte : '', $doc['type'], $doc['id']]
                );
            }
            $atteints[$a] = true;
            $n = Amis::notifier($moi, $a, '🔗 ' . $pseudo . ' a partagé ' . $combien . ($texte === '' ? '' : ' · ' . $texte));
            if ($n !== null) {
                $notifications[] = $n;
            }
        }
        foreach ($groupes as $g) {
            foreach (Conversations::membres($g) as $membre) {
                if ($membre['id'] !== $moi) {
                    foreach ($documents as $doc) {
                        self::donnerAcces($moi, $membre['id'], $doc['type'], $doc['id'], $droit);
                    }
                    $atteints[$membre['id']] = true;
                }
            }
            foreach ($documents as $rang => $doc) {
                Database::run(
                    'INSERT INTO conversation_messages (conversation_id, expediteur_id, texte, partage_type, partage_id, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                    [$g, $moi, $rang === 0 ? $texte : '', $doc['type'], $doc['id']]
                );
                $dernier = Database::dernierId();
                Database::run(
                    'UPDATE conversation_membres SET lu_jusqua = GREATEST(lu_jusqua, ?) WHERE conversation_id = ? AND user_id = ?',
                    [$dernier, $g, $moi]
                );
            }
            array_push($notifications, ...Conversations::notifier($moi, $g, '🔗 a partagé ' . $combien . ($texte === '' ? '' : ' · ' . $texte)));
        }

        return [self::combien(array_map('count', $comptes)), count($atteints), null, $notifications];
    }

    /**
     * « 3 cours », « 2 fichiers », « 1 dossier », « 5 documents » : ce qu'un
     * lot contient. Une seule sorte se nomme ; plusieurs font des documents.
     *
     * @param array<string, int> $comptes  combien de chaque type
     */
    public static function combien(array $comptes): string
    {
        $total = array_sum($comptes);
        foreach ($comptes as $type => $nb) {
            if ($nb !== $total) {
                continue;
            }
            return $total . ' ' . match ($type) {
                'cours' => 'cours',
                'fiche' => 'fiche' . ($total > 1 ? 's' : '') . ' de révision',
                'dossier' => 'dossier' . ($total > 1 ? 's' : ''),
                default => 'fichier' . ($total > 1 ? 's' : ''),
            };
        }

        return $total . ' documents';
    }

    private static function donnerAcces(int $moi, int $destinataire, string $type, int $id, string $droit = 'lecture'): void
    {
        Database::run(
            'INSERT INTO partages_amis (destinataire_id, cible_type, cible_id, droit, proprietaire_id, created_at)
                  VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE droit = VALUES(droit)',
            [$destinataire, $type, $id, self::droitValide($droit), $moi]
        );
    }

    /** Change ce qu'un ami peut faire, sans lui renvoyer de carte. */
    public static function changerDroit(int $moi, string $type, int $id, int $destinataire, string $droit): bool
    {
        return Database::run(
            'UPDATE partages_amis SET droit = ? WHERE destinataire_id = ? AND cible_type = ? AND cible_id = ? AND proprietaire_id = ?',
            [self::droitValide($droit), $destinataire, $type, $id, $moi]
        )->rowCount() > 0;
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
        return array_map(static fn (array $l): array => [
            'id' => (int) $l['id'], 'pseudo' => (string) $l['pseudo'], 'droit' => self::droitValide($l['droit']),
        ], Database::all(
            "SELECT u.id, COALESCE(u.pseudo, '') AS pseudo, p.droit FROM partages_amis p JOIN users u ON u.id = p.destinataire_id
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
            "SELECT p.cible_type, p.cible_id, p.droit, p.proprietaire_id, p.created_at, COALESCE(u.pseudo, '') AS proprietaire,
                    c.titre AS titre_cours, c.contenu, f.nom_origine, f.mime, f.taille,
                    cf.titre AS titre_fiche, cf.fiche_revision, d.nom AS nom_dossier, d.icone AS icone_dossier, d.user_id AS dossier_a,
                    ev.titre AS titre_evenement, ev.debut AS debut_evenement, ev.journee_entiere AS journee_evenement
               FROM partages_amis p
               JOIN users u ON u.id = p.proprietaire_id
               LEFT JOIN cours c ON p.cible_type = 'cours' AND c.id = p.cible_id AND c.user_id = p.proprietaire_id
               LEFT JOIN fichiers f ON p.cible_type = 'fichier' AND f.id = p.cible_id AND f.user_id = p.proprietaire_id
               LEFT JOIN cours cf ON p.cible_type = 'fiche' AND cf.id = p.cible_id AND cf.user_id = p.proprietaire_id
               LEFT JOIN dossiers d ON p.cible_type = 'dossier' AND d.id = p.cible_id AND d.user_id = p.proprietaire_id
               LEFT JOIN evenements ev ON p.cible_type = 'evenement' AND ev.id = p.cible_id AND ev.user_id = p.proprietaire_id
              WHERE p.destinataire_id = ? AND (c.id IS NOT NULL OR f.id IS NOT NULL OR cf.id IS NOT NULL OR d.id IS NOT NULL OR ev.id IS NOT NULL)
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
                'titre' => match ($type) {
                    'cours' => (string) $l['titre_cours'],
                    'fiche' => 'Fiche — ' . $l['titre_fiche'],
                    'dossier' => (string) $l['nom_dossier'],
                    'evenement' => (string) $l['titre_evenement'],
                    default => (string) $l['nom_origine'],
                },
                'icone' => match ($type) {
                    'cours' => '📘',
                    'fiche' => '📝',
                    'dossier' => (string) $l['icone_dossier'],
                    'evenement' => '📅',
                    default => Fichiers::icone((string) $l['mime'], (string) $l['nom_origine']),
                },
                'detail' => match ($type) {
                    'cours' => extrait((string) $l['contenu']),
                    'fiche' => extrait((string) $l['fiche_revision']),
                    'dossier' => self::compteCours(self::nbCours((int) $l['cible_id'], (int) $l['dossier_a'])),
                    'evenement' => ucfirst(date_fr((string) $l['debut_evenement'], (int) $l['journee_evenement'] !== 1)),
                    default => taille_lisible((int) $l['taille']),
                },
                'droit' => self::droitValide($l['droit']),
                'proprietaire' => (string) $l['proprietaire'],
                'proprietaire_id' => (int) $l['proprietaire_id'],
                'quand' => (string) $l['created_at'],
                'url' => self::adresse((string) $l['cible_type'], (int) $l['cible_id']),
            ];
        }

        return $recus;
    }

    /** Combien de cours un dossier contient, sous-dossiers compris. */
    public static function nbCours(int $dossierId, int $proprietaire): int
    {
        $sous = DossiersController::avecDescendants($proprietaire, $dossierId);

        return (int) Database::valeur(
            'SELECT COUNT(*) FROM cours WHERE user_id = ? AND dossier_id IN ('
            . implode(',', array_fill(0, count($sous), '?')) . ')',
            array_merge([$proprietaire], $sous)
        );
    }

    /** « 3 cours », « aucun cours ». */
    public static function compteCours(int $nb): string
    {
        return $nb === 0 ? 'aucun cours' : $nb . ' cours';
    }

    /**
     * Ce que je partage, du plus récent au plus ancien : avec combien d'amis,
     * et si un lien public court. Ce qui n'existe plus est écarté.
     */
    public static function envoyes(int $moi): array
    {
        $lignes = [];
        foreach (Database::all(
            'SELECT cible_type, cible_id, COUNT(*) AS nb, MAX(created_at) AS quand
               FROM partages_amis WHERE proprietaire_id = ? GROUP BY cible_type, cible_id',
            [$moi]
        ) as $l) {
            $lignes[$l['cible_type'] . '-' . $l['cible_id']] = [
                'type' => (string) $l['cible_type'], 'id' => (int) $l['cible_id'],
                'destinataires' => (int) $l['nb'], 'lien' => false, 'vues' => 0, 'quand' => (string) $l['quand'],
            ];
        }
        foreach (Database::all('SELECT cible_type, cible_id, vues, created_at FROM liens_partage WHERE user_id = ?', [$moi]) as $l) {
            $cle = $l['cible_type'] . '-' . $l['cible_id'];
            $lignes[$cle] = [
                'type' => (string) $l['cible_type'], 'id' => (int) $l['cible_id'],
                'destinataires' => $lignes[$cle]['destinataires'] ?? 0, 'lien' => true, 'vues' => (int) $l['vues'],
                'quand' => max($lignes[$cle]['quand'] ?? '', (string) $l['created_at']),
            ];
        }
        usort($lignes, static fn (array $a, array $b): int => strcmp($b['quand'], $a['quand']));

        $envoyes = [];
        foreach ($lignes as $l) {
            $cible = self::mienne($l['type'], $l['id'], $moi);
            if ($cible === null) {
                continue;
            }
            $envoyes[] = $l + [
                'titre' => (string) $cible['titre'],
                'icone' => match ($l['type']) {
                    'cours' => '📘',
                    'fiche' => '📝',
                    'dossier' => (string) $cible['icone'],
                    'lot' => '🔗',
                    'evenement' => '📅',
                    default => Fichiers::icone((string) $cible['mime'], (string) $cible['nom_origine']),
                },
                // Un lot se reprend en main depuis la fenêtre du partage groupé.
                'gerer' => $l['type'] === 'lot'
                    ? url('partager/plusieurs')
                    : url('partager/' . self::mot($l['type']) . '/' . $l['id']),
            ];
        }

        return $envoyes;
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

    /**
     * Ce lien donne-t-il à voir ce document ?
     *
     * Un lien désigne une cible ; ce qu'elle contient suit. Un dossier ouvre
     * ses cours, un cours ses fichiers joints, une fiche les siens, un lot
     * tout ce qu'on y a mis — et ce que ces documents contiennent à leur tour.
     */
    public static function visiblePar(array $lien, string $type, int $id): bool
    {
        $cible = self::cible($type, $id);
        if ($cible === null || (int) $cible['user_id'] !== (int) $lien['user_id']) {
            return false;
        }
        $vise = static fn (string $t, int $i): bool =>
            (string) $lien['cible_type'] === $t && (int) $lien['cible_id'] === $i;

        // Le document lui-même, ou un de ceux du lot.
        if ($vise($type, $id) || self::dansLeLot($lien, $type, $id)) {
            return true;
        }
        // Un cours, quand un dossier qui le contient est visé.
        if ($type === 'cours') {
            foreach (self::chaineDossiers($id) as $dossierId) {
                if ($vise('dossier', $dossierId) || self::dansLeLot($lien, 'dossier', $dossierId)) {
                    return true;
                }
            }

            return false;
        }
        // Un fichier joint suit son cours ; celui d'une fiche suit la fiche.
        if ($type === 'fichier') {
            return (int) $cible['pour_fiche'] === 1
                ? self::visiblePar($lien, 'fiche', (int) $cible['cours_id'])
                : self::visiblePar($lien, 'cours', (int) $cible['cours_id']);
        }

        return false;
    }

    private static function dansLeLot(array $lien, string $type, int $id): bool
    {
        return (string) $lien['cible_type'] === 'lot' && Database::valeur(
            'SELECT 1 FROM lots_partage_documents WHERE lot_id = ? AND cible_type = ? AND cible_id = ?',
            [(int) $lien['cible_id'], $type, $id]
        ) !== null;
    }

    /**
     * Rassemble des documents sous un lien : un lot, et le lien qui le montre.
     *
     * @param array<string, list<int|string>> $parType
     * @return array{0: ?array, 1: ?string} le lot et son lien, ou la raison du refus
     */
    public static function creerLot(int $moi, array $parType, string $nom = ''): array
    {
        $documents = [];
        foreach (self::TYPES as $type) {
            $ids = is_array($parType[$type] ?? null) ? $parType[$type] : [];
            foreach (array_unique(array_map('intval', $ids)) as $i) {
                if ($i > 0 && self::mienne($type, $i, $moi) !== null) {
                    $documents[] = [$type, $i];
                } elseif ($i > 0) {
                    return [null, 'Un des documents choisis est introuvable.'];
                }
            }
        }
        if ($documents === []) {
            return [null, 'Choisissez au moins un document à partager.'];
        }
        if (count($documents) > self::LOT_MAX) {
            return [null, 'Pas plus de ' . self::LOT_MAX . ' documents à la fois.'];
        }
        $nom = trim($nom);
        if ($nom === '') {
            $nom = 'Ma sélection du ' . date_fr(date('Y-m-d H:i:s'), false);
        }
        Database::run(
            'INSERT INTO lots_partage (user_id, nom, created_at) VALUES (?, ?, UTC_TIMESTAMP())',
            [$moi, mb_substr($nom, 0, 120)]
        );
        $lotId = Database::dernierId();
        foreach ($documents as $position => [$type, $i]) {
            Database::run(
                'INSERT INTO lots_partage_documents (lot_id, cible_type, cible_id, position) VALUES (?, ?, ?, ?)',
                [$lotId, $type, $i, $position]
            );
        }
        Database::run(
            'INSERT INTO liens_partage (user_id, cible_type, cible_id, jeton, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())',
            [$moi, 'lot', $lotId, bin2hex(random_bytes(16))]
        );

        return [self::lot($lotId), null];
    }

    /** Un lot, avec son lien et ce qu'il contient. */
    public static function lot(int $id): ?array
    {
        $lot = Database::one('SELECT * FROM lots_partage WHERE id = ?', [$id]);
        if ($lot === null) {
            return null;
        }
        $lien = self::lien('lot', $id);
        $lot['jeton'] = $lien === null ? null : (string) $lien['jeton'];
        $lot['vues'] = $lien === null ? 0 : (int) $lien['vues'];
        $lot['documents'] = self::documentsDuLot($id);

        return $lot;
    }

    /**
     * Ce qu'un lot montre, dans l'ordre où on l'a choisi : ce qui existe
     * encore, avec de quoi l'annoncer.
     */
    public static function documentsDuLot(int $lotId): array
    {
        $documents = [];
        foreach (Database::all(
            'SELECT cible_type, cible_id FROM lots_partage_documents WHERE lot_id = ? ORDER BY position, cible_id',
            [$lotId]
        ) as $ligne) {
            $type = (string) $ligne['cible_type'];
            $cible = self::cible($type, (int) $ligne['cible_id']);
            if ($cible === null) {
                continue;
            }
            $documents[] = [
                'type' => $type,
                'id' => (int) $cible['id'],
                'titre' => (string) $cible['titre'],
                'icone' => match ($type) {
                    'cours' => '📘',
                    'fiche' => '📝',
                    'dossier' => (string) $cible['icone'],
                    'evenement' => '📅',
                    default => Fichiers::icone((string) $cible['mime'], (string) $cible['nom_origine']),
                },
                'detail' => match ($type) {
                    'cours' => 'Cours' . ((int) $cible['nb_fichiers'] > 0 ? ' · ' . (int) $cible['nb_fichiers'] . ' fichier' . ((int) $cible['nb_fichiers'] > 1 ? 's' : '') : ''),
                    'fiche' => 'Fiche de révision',
                    'dossier' => 'Dossier · ' . self::compteCours((int) $cible['nb_cours']),
                    'evenement' => 'Évènement · ' . date_fr((string) $cible['debut'], (int) $cible['journee_entiere'] !== 1),
                    default => 'Fichier · ' . taille_lisible((int) $cible['taille']),
                },
            ];
        }

        return $documents;
    }

    /** Mes lots, du plus récent au plus ancien. */
    public static function mesLots(int $moi): array
    {
        $lots = [];
        foreach (Database::all('SELECT id FROM lots_partage WHERE user_id = ? ORDER BY created_at DESC, id DESC', [$moi]) as $l) {
            $lot = self::lot((int) $l['id']);
            if ($lot !== null) {
                $lots[] = $lot;
            }
        }

        return $lots;
    }

    /** Supprime un lot et son lien : l'adresse ne mène plus à rien. */
    public static function supprimerLot(int $moi, int $id): bool
    {
        if (Database::valeur('SELECT 1 FROM lots_partage WHERE id = ? AND user_id = ?', [$id, $moi]) === null) {
            return false;
        }
        Database::run("DELETE FROM liens_partage WHERE cible_type = 'lot' AND cible_id = ?", [$id]);
        Database::run('DELETE FROM lots_partage WHERE id = ? AND user_id = ?', [$id, $moi]);

        return true;
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
        Database::run('DELETE FROM commentaires_partage WHERE cible_type = ? AND cible_id = ?', [$type, $id]);
        Database::run('DELETE FROM lots_partage_documents WHERE cible_type = ? AND cible_id = ?', [$type, $id]);
        if (in_array($type, ['cours', 'fiche'], true)) {
            $dossier = (string) Config::get('app', 'dossier_uploads');
            foreach (Database::all(
                "SELECT nom_stocke FROM modifications_partage WHERE cible_type = ? AND cible_id = ? AND nature = 'retrait' AND restaure = 0",
                [$type, $id]
            ) as $misDeCote) {
                $chemin = $dossier . DIRECTORY_SEPARATOR . basename((string) $misDeCote['nom_stocke']);
                if (is_file($chemin)) {
                    @unlink($chemin);
                }
            }
            Database::run('DELETE FROM modifications_partage WHERE cible_type = ? AND cible_id = ?', [$type, $id]);
        }
    }

    /**
     * Les commentaires d'un document, du plus ancien au plus récent : une
     * discussion au fil du texte, que le propriétaire lit aussi.
     */
    public static function commentaires(string $type, int $id, int $moi = 0): array
    {
        $lignes = Database::all(
            "SELECT c.id, c.user_id, c.reponse_a, c.texte, c.created_at, COALESCE(u.pseudo, '') AS pseudo,
                    (SELECT COUNT(*) FROM commentaires_jaime j WHERE j.commentaire_id = c.id) AS nb_jaime,
                    (SELECT COUNT(*) FROM commentaires_jaime j WHERE j.commentaire_id = c.id AND j.user_id = ?) AS moi_jaime
               FROM commentaires_partage c JOIN users u ON u.id = c.user_id
              WHERE c.cible_type = ? AND c.cible_id = ? ORDER BY c.created_at, c.id",
            [$moi, $type, $id]
        );
        // Un fil : chaque commentaire, puis ses réponses sous lui.
        $fils = [];
        $reponses = [];
        foreach ($lignes as $l) {
            $l['nb_jaime'] = (int) $l['nb_jaime'];
            $l['moi_jaime'] = (int) $l['moi_jaime'] > 0;
            if ($l['reponse_a'] === null) {
                $fils[(int) $l['id']] = $l + ['reponses' => []];
            } else {
                $reponses[] = $l;
            }
        }
        foreach ($reponses as $r) {
            if (isset($fils[(int) $r['reponse_a']])) {
                $fils[(int) $r['reponse_a']]['reponses'][] = $r;
            }
        }

        return array_values($fils);
    }

    /**
     * Aime un commentaire, ou cesse de l'aimer. Il faut pouvoir le lire.
     *
     * @return ?array{0: string, 1: int} le document d'où il vient
     */
    public static function aimerCommentaire(int $moi, int $commentaireId): ?array
    {
        $commentaire = Database::one('SELECT cible_type, cible_id FROM commentaires_partage WHERE id = ?', [$commentaireId]);
        if ($commentaire === null) {
            return null;
        }
        $type = (string) $commentaire['cible_type'];
        $id = (int) $commentaire['cible_id'];
        if (!self::permet(self::droit($type, $id, $moi), 'commentaire')) {
            return null;
        }
        $defait = Database::run(
            'DELETE FROM commentaires_jaime WHERE commentaire_id = ? AND user_id = ?',
            [$commentaireId, $moi]
        )->rowCount() > 0;
        if (!$defait) {
            Database::run(
                'INSERT INTO commentaires_jaime (commentaire_id, user_id, created_at) VALUES (?, ?, UTC_TIMESTAMP())',
                [$commentaireId, $moi]
            );
        }

        return [$type, $id];
    }

    /** Combien de commentaires : de quoi l'annoncer sans tous les charger. */
    public static function nbCommentaires(string $type, int $id): int
    {
        return (int) Database::valeur(
            'SELECT COUNT(*) FROM commentaires_partage WHERE cible_type = ? AND cible_id = ?',
            [$type, $id]
        );
    }

    /** Écrit un commentaire, si on en a le droit. Rend la raison d'un refus. */
    public static function commenter(int $moi, string $type, int $id, string $texte, ?int $reponseA = null): ?string
    {
        if (!self::permet(self::droit($type, $id, $moi), 'commentaire')) {
            return 'Vous ne pouvez pas commenter ce document.';
        }
        $texte = trim(str_replace(["\r\n", "\r"], "\n", $texte));
        if ($texte === '') {
            return 'Écrivez d’abord votre commentaire.';
        }
        if (mb_strlen($texte) > Amis::MESSAGE_MAX) {
            return 'Le commentaire ne peut pas dépasser ' . Amis::MESSAGE_MAX . ' caractères.';
        }
        // Une réponse vise un commentaire de ce document ; répondre à une
        // réponse la range sous le même commentaire, sur un seul niveau.
        $parent = null;
        if ($reponseA !== null) {
            $parent = Database::one(
                'SELECT id, user_id, reponse_a FROM commentaires_partage WHERE id = ? AND cible_type = ? AND cible_id = ?',
                [$reponseA, $type, $id]
            );
            if ($parent === null) {
                return 'Ce commentaire n’existe plus.';
            }
        }
        $racine = $parent === null ? null : (int) ($parent['reponse_a'] ?? $parent['id']);
        Database::run(
            'INSERT INTO commentaires_partage (cible_type, cible_id, user_id, reponse_a, texte, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$type, $id, $moi, $racine, $texte]
        );

        // Le propriétaire est prévenu, et celui à qui l'on répond ; les autres
        // liront en rouvrant le document. Un clic ouvre le fil des commentaires.
        $cible = self::cible($type, $id);
        $pseudo = (string) (Amis::compte($moi)['pseudo'] ?? 'Un ami');
        $titre = mb_strimwidth((string) ($cible['titre'] ?? ''), 0, 60, '…');
        $fil = url('partages/' . self::mot($type) . '/' . $id . '/commentaires');
        $prevenir = [];
        if ($cible !== null && (int) $cible['user_id'] !== $moi) {
            $prevenir[(int) $cible['user_id']] = 'a commenté « ' . $titre . ' »';
        }
        if ($parent !== null && (int) $parent['user_id'] !== $moi) {
            $prevenir[(int) $parent['user_id']] = 'vous a répondu sur « ' . $titre . ' »';
        }
        foreach ($prevenir as $qui => $annonce) {
            $n = FileNotifications::ajouter($qui, 'commentaire', [
                'title' => '💬 ' . $pseudo,
                'body' => mb_strimwidth($pseudo . ' ' . $annonce . ' · ' . $texte, 0, 200, '…'),
                'url' => $fil,
                'tag' => 'commentaire-' . $type . '-' . $id,
            ]);
            if ($n !== null) {
                FileNotifications::envoyer($n);
            }
        }

        return null;
    }

    /**
     * Retire un commentaire : le sien, ou n'importe lequel sur son propre
     * document. Rend le document d'où il vient, pour y revenir.
     *
     * @return ?array{0: string, 1: int}
     */
    public static function retirerCommentaire(int $moi, int $commentaireId): ?array
    {
        $commentaire = Database::one('SELECT * FROM commentaires_partage WHERE id = ?', [$commentaireId]);
        if ($commentaire === null) {
            return null;
        }
        $type = (string) $commentaire['cible_type'];
        $id = (int) $commentaire['cible_id'];
        $cible = self::cible($type, $id);
        $sien = (int) $commentaire['user_id'] === $moi;
        $chezMoi = $cible !== null && (int) $cible['user_id'] === $moi;
        if (!$sien && !$chezMoi) {
            return null;
        }
        Database::run('DELETE FROM commentaires_partage WHERE id = ?', [$commentaireId]);

        return [$type, $id];
    }

    /**
     * Écrit dans un document partagé : le texte d'un cours, ou celui d'une
     * fiche. Rend la raison d'un refus.
     */
    public static function ecrire(int $moi, string $type, int $id, string $texte): ?string
    {
        if (!in_array($type, ['cours', 'fiche'], true)) {
            return 'Ce document ne s’écrit pas.';
        }
        if (!self::permet(self::droit($type, $id, $moi), 'modification')) {
            return 'Vous ne pouvez pas modifier ce document.';
        }
        $texte = TexteRiche::depuisFormulaire($texte);
        $avant = (string) Database::valeur(
            'SELECT COALESCE(' . ($type === 'cours' ? 'contenu' : 'fiche_revision') . ", '') FROM cours WHERE id = ?",
            [$id]
        );
        Database::run(
            'UPDATE cours SET ' . ($type === 'cours' ? 'contenu' : 'fiche_revision') . ' = ? WHERE id = ?',
            [$texte === '' ? null : $texte, $id]
        );

        // Un enregistrement qui ne change rien ne dérange personne.
        if ($texte !== $avant) {
            $cible = self::cible($type, $id);
            self::noter($moi, $type, $id, 'texte', ['avant' => $avant, 'apres' => $texte]);
            self::prevenir($moi, $type, $id, '✏️', match (true) {
                $texte === '' => 'a vidé le texte',
                $avant === '' => 'a écrit le texte',
                default => 'a modifié le texte',
            } . ($type === 'cours' ? ' du cours « ' : ' de la fiche « ')
                . mb_strimwidth((string) ($cible['titre_cours'] ?? $cible['titre'] ?? ''), 0, 60, '…') . ' »',
                self::adresseHistorique($type, $id));
        }

        return null;
    }

    /**
     * Prévient le propriétaire d'un document de ce qu'un autre y a fait. Un
     * clic sur la notification ouvre le document chez lui ; une notification
     * sur le même document remplace la précédente plutôt que de s'empiler.
     */
    private static function prevenir(int $moi, string $type, int $id, string $icone, string $quoi, ?string $adresse = null): void
    {
        $cible = self::cible($type, $id);
        if ($cible === null || (int) $cible['user_id'] === $moi) {
            return;
        }
        $pseudo = (string) (Amis::compte($moi)['pseudo'] ?? 'Un ami');
        $n = FileNotifications::ajouter((int) $cible['user_id'], 'partage', [
            'title' => $icone . ' ' . $pseudo,
            'body' => mb_strimwidth($pseudo . ' ' . $quoi, 0, 200, '…'),
            'url' => $adresse ?? match ($type) {
                'cours' => url('cours/' . $id),
                'fiche' => url('revision/' . $id),
                'evenement' => url('evenements/' . $id),
                default => url('partages/envoyes'),
            },
            'tag' => 'partage-' . $type . '-' . $id,
        ]);
        if ($n !== null) {
            FileNotifications::envoyer($n);
        }
    }

    /**
     * Joint des fichiers à un document partagé. Ils appartiennent au cours,
     * donc à son propriétaire : le partage retiré, rien ne se perd pour lui.
     *
     * @return array{0: int, 1: list<string>} les fichiers joints, et les refus
     */
    public static function joindre(int $moi, string $type, int $id, array $envoi): array
    {
        if (!in_array($type, ['cours', 'fiche'], true)
            || !self::permet(self::droit($type, $id, $moi), 'modification')) {
            return [0, ['Vous ne pouvez pas modifier ce document.']];
        }
        $cible = self::cible($type, $id);
        if ($cible === null) {
            return [0, ['Ce document est introuvable.']];
        }
        $avant = (int) Database::valeur('SELECT COUNT(*) FROM fichiers WHERE cours_id = ?', [$id]);
        $erreurs = Fichiers::enregistrer($envoi, $id, (int) $cible['user_id'], $type === 'fiche');
        $apres = (int) Database::valeur('SELECT COUNT(*) FROM fichiers WHERE cours_id = ?', [$id]);
        $ajoutes = $apres - $avant;

        if ($ajoutes > 0) {
            // Chaque fichier joint entre dans l'historique, par son nom.
            foreach (Database::all(
                'SELECT id, nom_origine, mime, taille FROM fichiers WHERE cours_id = ? ORDER BY id DESC LIMIT ' . $ajoutes,
                [$id]
            ) as $nouveau) {
                self::noter($moi, $type, $id, 'ajout', [
                    'fichier_id' => (int) $nouveau['id'], 'nom_origine' => (string) $nouveau['nom_origine'],
                    'mime' => (string) $nouveau['mime'], 'taille' => (int) $nouveau['taille'],
                ]);
            }
            $noms = array_column(Database::all(
                'SELECT nom_origine FROM fichiers WHERE cours_id = ? ORDER BY id DESC LIMIT ' . min($ajoutes, 3),
                [$id]
            ), 'nom_origine');
            self::prevenir($moi, $type, $id, '📎', 'a ajouté ' . ($ajoutes === 1 ? 'le fichier' : $ajoutes . ' fichiers')
                . ' ' . implode(', ', array_map(static fn (string $n): string => '« ' . $n . ' »', array_reverse($noms)))
                . ($ajoutes > 3 ? '…' : '')
                . ($type === 'cours' ? ' au cours « ' : ' à la fiche « ')
                . mb_strimwidth((string) ($cible['titre_cours'] ?? $cible['titre']), 0, 60, '…') . ' »',
                self::adresseHistorique($type, $id));
        }

        return [$ajoutes, $erreurs];
    }

    /**
     * Retire un fichier d'un document partagé, quand on a le droit d'y écrire.
     *
     * @return ?array{0: string, 1: int} le document d'où il vient
     */
    public static function retirerFichier(int $moi, int $fichierId): ?array
    {
        $fichier = self::cible('fichier', $fichierId);
        if ($fichier === null || !self::permet(self::droit('fichier', $fichierId, $moi), 'modification')) {
            return null;
        }
        $type = (int) $fichier['pour_fiche'] === 1 ? 'fiche' : 'cours';
        $coursId = (int) $fichier['cours_id'];
        if ((int) $fichier['user_id'] === $moi) {
            if (!Fichiers::supprimer($fichierId, $moi)) {
                return null;
            }
        } else {
            // Le fichier d'un autre n'est pas effacé : il sort du document,
            // mais reste sur le disque, pour que son propriétaire le remette.
            self::noter($moi, $type, $coursId, 'retrait', [
                'nom_origine' => (string) $fichier['nom_origine'], 'nom_stocke' => (string) $fichier['nom_stocke'],
                'mime' => (string) $fichier['mime'], 'taille' => (int) $fichier['taille'],
            ]);
            Database::run('DELETE FROM fichiers WHERE id = ?', [$fichierId]);
        }
        self::oublier('fichier', $fichierId);
        $document = self::cible($type, $coursId);
        self::prevenir($moi, $type, $coursId, '🗑️', 'a retiré le fichier « ' . $fichier['nom_origine'] . ' »'
            . ($type === 'cours' ? ' du cours « ' : ' de la fiche « ')
            . mb_strimwidth((string) ($document['titre_cours'] ?? $document['titre'] ?? ''), 0, 60, '…') . ' »',
            self::adresseHistorique($type, $coursId));

        return [$type, $coursId];
    }

    /** Où voir ce qui a changé dans un document. */
    public static function adresseHistorique(string $type, int $id): string
    {
        return url('partages/' . self::mot($type) . '/' . $id . '/modifications');
    }

    /**
     * Garde une modification d'un document partagé. Celles des autres, et
     * celles du propriétaire aussi : l'historique doit raconter tout ce qui
     * s'est passé, sans quoi une ligne apparaît sans que personne ne l'ait
     * écrite. Tant que le document n'est partagé avec personne, ce qu'on y
     * fait chez soi ne laisse pas de trace.
     *
     * @param array<string, mixed> $details
     */
    private static function noter(int $moi, string $type, int $id, string $nature, array $details): void
    {
        $cible = self::cible($type, $id);
        if ($cible === null || ((int) $cible['user_id'] === $moi && !self::estPartage($type, $id))) {
            return;
        }
        Database::run(
            'INSERT INTO modifications_partage (cible_type, cible_id, user_id, nature, avant, apres, fichier_id, nom_origine, nom_stocke, mime, taille, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$type, $id, $moi, $nature, $details['avant'] ?? null, $details['apres'] ?? null, $details['fichier_id'] ?? null,
             $details['nom_origine'] ?? null, $details['nom_stocke'] ?? null, $details['mime'] ?? null, $details['taille'] ?? null]
        );
    }

    /**
     * Ce cours, ou cette fiche, est-il partagé avec au moins un ami ? Un
     * cours l'est aussi par un dossier qui le contient.
     */
    public static function estPartage(string $type, int $id): bool
    {
        $partage = static fn (string $t, int $i): bool => Database::valeur(
            'SELECT 1 FROM partages_amis WHERE cible_type = ? AND cible_id = ? LIMIT 1', [$t, $i]
        ) !== null;
        if ($partage($type, $id)) {
            return true;
        }
        if ($type === 'cours') {
            foreach (self::chaineDossiers($id) as $dossierId) {
                if ($partage('dossier', $dossierId)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Le propriétaire a réécrit le texte chez lui : l'historique le garde. */
    public static function suivreTexte(int $moi, string $type, int $id, string $avant, string $apres): void
    {
        if ($avant !== $apres) {
            self::noter($moi, $type, $id, 'texte', ['avant' => $avant, 'apres' => $apres]);
        }
    }

    /** Le propriétaire a joint des fichiers chez lui : chacun entre dans l'historique. */
    public static function suivreAjouts(int $moi, string $type, int $id, int $ajoutes): void
    {
        if ($ajoutes <= 0) {
            return;
        }
        foreach (Database::all(
            'SELECT id, nom_origine, mime, taille FROM fichiers WHERE cours_id = ? AND pour_fiche = ? ORDER BY id DESC LIMIT ' . $ajoutes,
            [$id, $type === 'fiche' ? 1 : 0]
        ) as $nouveau) {
            self::noter($moi, $type, $id, 'ajout', [
                'fichier_id' => (int) $nouveau['id'], 'nom_origine' => (string) $nouveau['nom_origine'],
                'mime' => (string) $nouveau['mime'], 'taille' => (int) $nouveau['taille'],
            ]);
        }
    }

    /**
     * Le propriétaire a supprimé un de ses fichiers : l'historique le dit,
     * sans pouvoir le remettre — chez soi, supprimer reste définitif.
     */
    public static function suivreRetrait(int $moi, array $fichier): void
    {
        self::noter($moi, (int) $fichier['pour_fiche'] === 1 ? 'fiche' : 'cours', (int) $fichier['cours_id'], 'retrait', [
            'nom_origine' => (string) $fichier['nom_origine'], 'mime' => (string) $fichier['mime'], 'taille' => (int) $fichier['taille'],
        ]);
    }

    /**
     * Un évènement au format iCalendar, que tout agenda sait importer :
     * Google, Outlook, Apple. L'heure est celle du fuseau de son auteur.
     */
    public static function ics(array $evenement): string
    {
        $echapper = static fn (string $t): string => str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $t
        );
        // Une ligne ne doit pas dépasser 75 octets : les suivantes commencent par une espace.
        $plier = static function (string $ligne): string {
            $morceaux = [];
            while (strlen($ligne) > 74) {
                $coupe = 74;
                // Ne jamais couper un caractère UTF-8 en deux.
                while ($coupe > 0 && (ord($ligne[$coupe]) & 0xC0) === 0x80) {
                    $coupe--;
                }
                $morceaux[] = substr($ligne, 0, $coupe);
                $ligne = ' ' . substr($ligne, $coupe);
            }
            $morceaux[] = $ligne;

            return implode("\r\n", $morceaux);
        };
        $debut = new DateTimeImmutable((string) $evenement['debut']);
        $fin = new DateTimeImmutable((string) $evenement['fin']);
        $fuseau = (string) ($evenement['fuseau'] ?? '') !== '' ? (string) $evenement['fuseau'] : 'Europe/Paris';
        $lignes = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Mes Cours//Partage//FR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:evenement-' . (int) $evenement['id'] . '@mes-cours',
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        ];
        if ((int) $evenement['journee_entiere'] === 1) {
            // Une journée entière finit le lendemain, par convention.
            $lignes[] = 'DTSTART;VALUE=DATE:' . $debut->format('Ymd');
            $lignes[] = 'DTEND;VALUE=DATE:' . $fin->modify('+1 day')->format('Ymd');
        } else {
            $lignes[] = 'DTSTART;TZID=' . $fuseau . ':' . $debut->format('Ymd\THis');
            $lignes[] = 'DTEND;TZID=' . $fuseau . ':' . $fin->format('Ymd\THis');
        }
        $lignes[] = 'SUMMARY:' . $echapper((string) $evenement['titre']);
        if (trim((string) ($evenement['lieu'] ?? '')) !== '') {
            $lignes[] = 'LOCATION:' . $echapper((string) $evenement['lieu']);
        }
        $description = trim(html_entity_decode(strip_tags(TexteRiche::versHtml((string) ($evenement['description'] ?? ''))), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($description !== '') {
            $lignes[] = 'DESCRIPTION:' . $echapper($description);
        }
        $lignes[] = 'END:VEVENT';
        $lignes[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($plier, $lignes)) . "\r\n";
    }

    /** Combien de modifications : de quoi l'annoncer. */
    public static function nbModifications(string $type, int $id): int
    {
        return (int) Database::valeur(
            'SELECT COUNT(*) FROM modifications_partage WHERE cible_type = ? AND cible_id = ?',
            [$type, $id]
        );
    }

    /**
     * L'historique d'un document, de la plus récente à la plus ancienne :
     * qui, quand, et quoi — la comparaison du texte, ou le fichier.
     */
    public static function historique(string $type, int $id): array
    {
        $lignes = Database::all(
            "SELECT m.*, COALESCE(u.pseudo, '') AS pseudo,
                    (SELECT 1 FROM fichiers f WHERE f.id = m.fichier_id) AS fichier_existe
               FROM modifications_partage m JOIN users u ON u.id = m.user_id
              WHERE m.cible_type = ? AND m.cible_id = ? ORDER BY m.created_at DESC, m.id DESC",
            [$type, $id]
        );
        $actuel = (string) Database::valeur(
            'SELECT COALESCE(' . ($type === 'cours' ? 'contenu' : 'fiche_revision') . ", '') FROM cours WHERE id = ?",
            [$id]
        );
        foreach ($lignes as &$l) {
            $l['difference'] = $l['nature'] === 'texte'
                ? Difference::comparer((string) $l['avant'], (string) $l['apres'])
                : [];
            // Revenir au texte d'avant efface aussi ce qui a changé depuis.
            $l['change_depuis'] = $l['nature'] === 'texte' && (string) $l['apres'] !== $actuel;
        }

        return $lignes;
    }

    /**
     * Annule une modification d'un ami : le texte revient à ce qu'il était,
     * le fichier ajouté est retiré, le fichier retiré est remis. Le
     * propriétaire seul le peut ; la ligne reste dans l'historique, annulée.
     *
     * @return ?array{0: string, 1: int, 2: string} le document, et ce qui a été fait
     */
    public static function annulerModification(int $moi, int $modificationId): ?array
    {
        $ligne = Database::one(
            'SELECT * FROM modifications_partage WHERE id = ? AND annulee = 0 AND restaure = 0',
            [$modificationId]
        );
        if ($ligne === null) {
            return null;
        }
        $type = (string) $ligne['cible_type'];
        $id = (int) $ligne['cible_id'];
        $document = self::cible($type, $id);
        // Le propriétaire, ou quiconque a le droit de modifier ce document.
        if ($document === null || !self::permet(self::droit($type, $id, $moi), 'modification')) {
            return null;
        }
        $proprietaire = (int) $document['user_id'];

        if ($ligne['nature'] === 'retrait') {
            // Effacé pour de bon par son propriétaire : rien à remettre.
            if ($ligne['nom_stocke'] === null) {
                return null;
            }
            return self::restaurerFichier($moi, $modificationId) === null
                ? null
                : [$type, $id, 'Fichier remis à sa place.'];
        }
        if ($ligne['nature'] === 'texte') {
            $avant = (string) $ligne['avant'];
            Database::run(
                'UPDATE cours SET ' . ($type === 'cours' ? 'contenu' : 'fiche_revision') . ' = ? WHERE id = ? AND user_id = ?',
                [$avant === '' ? null : $avant, $id, $proprietaire]
            );
            $fait = 'Le texte est revenu à ce qu’il était avant cette modification.';
        } else {
            // Le fichier ajouté, s'il est encore là, quitte le document.
            $fichier = Database::one(
                'SELECT id FROM fichiers WHERE id = ? AND cours_id = ?',
                [(int) $ligne['fichier_id'], $id]
            );
            $fait = $fichier === null
                ? 'Ce fichier n’y était déjà plus.'
                : (Fichiers::supprimer((int) $fichier['id'], $proprietaire) ? 'Fichier retiré.' : 'Ce fichier n’y était déjà plus.');
        }
        Database::run('UPDATE modifications_partage SET annulee = 1 WHERE id = ?', [$modificationId]);
        self::prevenirAnnulation($moi, $ligne);

        return [$type, $id, $fait];
    }

    /**
     * Prévient de l'annulation d'une modification : son auteur, s'il n'est
     * pas celui qui annule, et le propriétaire du document, qui sait ainsi ce
     * qui revient chez lui. Un clic mène à l'historique.
     */
    private static function prevenirAnnulation(int $moi, array $ligne): void
    {
        $auteur = (int) $ligne['user_id'];
        $type = (string) $ligne['cible_type'];
        $id = (int) $ligne['cible_id'];
        $document = self::cible($type, $id);
        if ($document === null) {
            return;
        }
        $proprietaire = (int) $document['user_id'];
        $titre = '« ' . mb_strimwidth((string) ($document['titre_cours'] ?? $document['titre'] ?? ''), 0, 60, '…') . ' »';
        $du = ($type === 'cours' ? 'du cours ' : 'de la fiche ') . $titre;
        $au = ($type === 'cours' ? 'au cours ' : 'à la fiche ') . $titre;
        $fichier = '« ' . $ligne['nom_origine'] . ' »';
        $pseudo = (string) (Amis::compte($moi)['pseudo'] ?? 'Quelqu’un');
        $pseudoAuteur = (string) (Amis::compte($auteur)['pseudo'] ?? 'un ami');

        // Qui l'apprend, et comment on le lui dit : « votre » modification pour
        // son auteur, « sa » ou « celle de … » pour le propriétaire.
        $destinataires = [];
        if ($auteur !== $moi) {
            $destinataires[$auteur] = match ((string) $ligne['nature']) {
                'texte' => 'a annulé votre modification du texte ' . $du,
                'ajout' => 'a retiré le fichier ' . $fichier . ' que vous aviez ajouté ' . $au,
                default => 'a remis le fichier ' . $fichier . ' que vous aviez retiré ' . $du,
            };
        }
        if ($proprietaire !== $moi && $proprietaire !== $auteur) {
            $de = $auteur === $moi ? 'sa' : 'la';
            $qui = $auteur === $moi ? '' : ' de ' . $pseudoAuteur;
            $destinataires[$proprietaire] = match ((string) $ligne['nature']) {
                'texte' => 'a annulé ' . $de . ' modification' . $qui . ' du texte ' . $du,
                'ajout' => $auteur === $moi
                    ? 'a annulé son ajout du fichier ' . $fichier . ' ' . $au
                    : 'a retiré le fichier ' . $fichier . ' ajouté par ' . $pseudoAuteur . ' ' . $au,
                default => $auteur === $moi
                    ? 'a annulé son retrait du fichier ' . $fichier . ' ' . $du
                    : 'a remis le fichier ' . $fichier . ' retiré par ' . $pseudoAuteur . ' ' . $du,
            };
        }
        foreach ($destinataires as $qui => $quoi) {
            $n = FileNotifications::ajouter($qui, 'partage', [
                'title' => '↶ ' . $pseudo,
                'body' => mb_strimwidth($pseudo . ' ' . $quoi, 0, 200, '…'),
                'url' => self::adresseHistorique($type, $id),
                'tag' => 'annulation-' . $type . '-' . $id,
            ]);
            if ($n !== null) {
                FileNotifications::envoyer($n);
            }
        }
    }

    /**
     * Un fichier mis de côté, pour son propriétaire seulement : de quoi
     * l'ouvrir avant de décider de le remettre.
     */
    public static function fichierMisDeCote(int $moi, int $modificationId): ?array
    {
        $ligne = Database::one(
            "SELECT * FROM modifications_partage WHERE id = ? AND nature = 'retrait' AND restaure = 0 AND nom_stocke IS NOT NULL",
            [$modificationId]
        );
        if ($ligne === null) {
            return null;
        }
        return self::permet(self::droit((string) $ligne['cible_type'], (int) $ligne['cible_id'], $moi), 'modification')
            ? $ligne : null;
    }

    /**
     * Remet dans le document un fichier qu'un ami en avait retiré.
     *
     * @return ?array{0: string, 1: int} le document où il revient
     */
    public static function restaurerFichier(int $moi, int $modificationId): ?array
    {
        $ligne = self::fichierMisDeCote($moi, $modificationId);
        $document = $ligne === null ? null : self::cible((string) $ligne['cible_type'], (int) $ligne['cible_id']);
        if ($ligne === null || $document === null) {
            return null;
        }
        Database::run(
            'INSERT INTO fichiers (user_id, cours_id, pour_fiche, nom_origine, nom_stocke, mime, taille) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [(int) $document['user_id'], (int) $ligne['cible_id'], $ligne['cible_type'] === 'fiche' ? 1 : 0, (string) $ligne['nom_origine'],
             (string) $ligne['nom_stocke'], (string) $ligne['mime'], (int) $ligne['taille']]
        );
        Database::run(
            'UPDATE modifications_partage SET restaure = 1, fichier_id = ? WHERE id = ?',
            [Database::dernierId(), $modificationId]
        );
        self::prevenirAnnulation($moi, $ligne);

        return [(string) $ligne['cible_type'], (int) $ligne['cible_id']];
    }

    /**
     * Copie un document partagé dans ses propres cours : un cours entier avec
     * ses fichiers joints, ou un fichier rangé dans le cours qu'on choisit.
     *
     * @return array{0: ?int, 1: ?string} le cours où la copie se trouve, ou la raison du refus
     */
    public static function copier(int $moi, string $type, int $id, ?int $coursCible = null): array
    {
        [$ou, $refus] = self::faireCopie($moi, $type, $id, $coursCible);

        // Le propriétaire sait qu'on a fait sa propre copie de son document.
        if ($refus === null && $ou !== null) {
            $cible = self::cible($type, $id);
            $titre = '« ' . mb_strimwidth((string) ($cible['titre_cours'] ?? $cible['titre'] ?? ''), 0, 60, '…') . ' »';
            self::prevenir($moi, $type, $id, '📥', match ($type) {
                'cours' => 'a copié le cours ' . $titre . ' dans ses cours',
                'fiche' => 'a copié la fiche ' . $titre . ' dans ses cours',
                'dossier' => 'a copié le dossier ' . $titre . ' dans ses dossiers',
                'evenement' => 'a ajouté l’évènement ' . $titre . ' à son calendrier',
                default => 'a copié le fichier ' . $titre . ' dans un de ses cours',
            }, match ($type) {
                'dossier' => url('cours', ['dossier' => $id]),
                'evenement' => url('evenements/' . $id),
                'fichier' => url('cours/' . (int) ($cible['cours_id'] ?? 0)),
                default => null,
            });
        }

        return [$ou, $refus];
    }

    /** @return array{0: ?int, 1: ?string} */
    private static function faireCopie(int $moi, string $type, int $id, ?int $coursCible): array
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

        // Un dossier se recopie en entier : ses sous-dossiers, puis ses cours.
        if ($type === 'dossier') {
            $groupes = self::contenuDuDossier($id, (int) $cible['user_id']);
            $total = 0;
            foreach ($groupes as $groupe) {
                $total += count($groupe['cours']);
            }
            if ($total > self::COPIE_MAX) {
                return [null, 'Ce dossier contient plus de ' . self::COPIE_MAX . ' cours : copiez-les un par un.'];
            }
            $nouveaux = [];
            foreach ($groupes as $groupe) {
                $parent = $nouveaux[self::parentDe($groupe['id'], (int) $cible['user_id'])] ?? null;
                $nouveaux[$groupe['id']] = self::creerDossier($moi, (string) $groupe['nom'], (string) $groupe['icone'], $parent);
                foreach ($groupe['cours'] as $c) {
                    Database::run(
                        'INSERT INTO cours (user_id, dossier_id, titre, contenu) VALUES (?, ?, ?, ?)',
                        [$moi, $nouveaux[$groupe['id']], mb_substr((string) $c['titre'], 0, 200), (string) $c['contenu']]
                    );
                    $nouveauCours = Database::dernierId();
                    foreach (self::fichiersDuCours((int) $c['id']) as $f) {
                        self::copierFichier($f, $moi, $nouveauCours, $dossier);
                    }
                }
            }

            return [$nouveaux[$id] ?? null, null];
        }

        // Un évènement entre dans mon calendrier, une seule fois.
        if ($type === 'evenement') {
            $deja = Database::valeur(
                'SELECT id FROM evenements WHERE user_id = ? AND titre = ? AND debut = ? AND fin = ?',
                [$moi, (string) $cible['titre'], (string) $cible['debut'], (string) $cible['fin']]
            );
            if ($deja !== null) {
                return [null, 'Cet évènement est déjà dans votre calendrier.'];
            }
            Database::run(
                'INSERT INTO evenements (user_id, titre, description, lieu, debut, fin, journee_entiere) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$moi, (string) $cible['titre'], $cible['description'], $cible['lieu'], (string) $cible['debut'], (string) $cible['fin'],
                 (int) $cible['journee_entiere']]
            );
            $nouveau = Database::dernierId();
            Agenda::viser($nouveau, [Agenda::DEFAUT]);

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

    /** Le dossier parent, chez son propriétaire. */
    private static function parentDe(int $dossierId, int $proprietaire): int
    {
        $parent = Database::valeur('SELECT parent_id FROM dossiers WHERE id = ? AND user_id = ?', [$dossierId, $proprietaire]);

        return $parent === null || $parent === false ? 0 : (int) $parent;
    }

    /**
     * Crée un dossier à soi. Un compte ne peut avoir deux dossiers du même
     * nom : la copie d'un dossier déjà nommé ainsi prend « (2) », « (3) »…
     */
    private static function creerDossier(int $moi, string $nom, string $icone, ?int $parent): int
    {
        $nom = mb_substr(trim($nom) === '' ? 'Dossier partagé' : trim($nom), 0, 110);
        $essai = $nom;
        for ($i = 2; Database::valeur('SELECT 1 FROM dossiers WHERE user_id = ? AND nom = ?', [$moi, $essai]) !== null; $i++) {
            $essai = $nom . ' (' . $i . ')';
        }
        $position = (int) Database::valeur(
            'SELECT COALESCE(MAX(position), 0) + 1 FROM dossiers WHERE user_id = ? AND parent_id ' . ($parent === null ? 'IS NULL' : '= ?'),
            $parent === null ? [$moi] : [$moi, $parent]
        );
        Database::run(
            'INSERT INTO dossiers (user_id, parent_id, nom, icone, position) VALUES (?, ?, ?, ?, ?)',
            [$moi, $parent, $essai, $icone === '' ? '📁' : $icone, $position]
        );

        return Database::dernierId();
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
            'icone' => match ($type) {
                'cours' => '📘',
                'fiche' => '📝',
                'dossier' => (string) $cible['icone'],
                'evenement' => '📅',
                default => Fichiers::icone((string) $cible['mime'], (string) $cible['nom_origine']),
            },
            'detail' => !$visible ? 'Le partage a été retiré.'
                : match ($type) {
                    'fichier' => 'Fichier · ' . taille_lisible((int) $cible['taille']),
                    'dossier' => 'Dossier · ' . self::compteCours((int) $cible['nb_cours']),
                    'evenement' => 'Évènement · ' . date_fr((string) $cible['debut'], (int) $cible['journee_entiere'] !== 1),
                    default => ($type === 'cours' ? 'Cours' : 'Fiche de révision')
                        . ((int) $cible['nb_fichiers'] > 0 ? ' · ' . (int) $cible['nb_fichiers'] . ' fichier' . ((int) $cible['nb_fichiers'] > 1 ? 's' : '') : ''),
                },
            'url' => $visible ? self::adresse($type, $id) : null,
        ];
    }
}
