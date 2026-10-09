<?php
declare(strict_types=1);

/**
 * Les calendriers partagés : un calendrier à part, ouvert aux seuls amis que son créateur a choisis.
 *
 * Il a son nom, sa couleur et ses propres évènements, qui n'appartiennent à aucun des calendriers personnels : retirer un membre
 * ne lui enlève rien de ce qu'il a écrit, et supprimer son compte n'efface pas ce qu'il a laissé au calendrier.
 *
 * Les droits sont simples :
 *  - tout membre voit le calendrier, y ajoute des évènements, et modifie ou supprime les siens ;
 *  - le créateur, en plus, gère le calendrier (nom, couleur, membres), modifie ou supprime n'importe quel évènement, et le supprime ;
 *  - un membre peut le quitter, et le masquer de son calendrier sans le quitter (la case du volet).
 *
 * Qui peut en être membre : un ami du créateur, au moment de l'ajout. L'amitié défaite plus tard ne le retire pas — c'est au créateur
 * de le faire — mais n'ouvre rien de plus : on ne fait entrer que des amis.
 */
final class CalendriersAmis
{
    public const NOM_MAX = 80;
    public const CALENDRIERS_MAX = 20;
    public const MEMBRES_MAX = 50;
    public const COULEUR_DEFAUT = '#6d5dfc';
    /** Les évènements d'un calendrier partagé portent un identifiant à part dans les vues : jamais celui d'un évènement personnel. */
    public const DECALAGE_ID = 2000000000;

    public const COULEURS = ['#6d5dfc', '#3ba55d', '#ed4245', '#f59e0b', '#eb459e', '#14b8a6', '#3498db', '#e67e22', '#9b59b6', '#607d8b'];

    // --- Lire ----------------------------------------------------------------------------------------------------------

    /** La clé du calendrier dans le volet : « ca12 » (les agendas d'Outlook et de Google portent une empreinte de 32 caractères). */
    public static function cle(int $id): string
    {
        return 'ca' . $id;
    }

    public static function estMembre(int $calendrier, int $moi): bool
    {
        return (int) Database::valeur('SELECT COUNT(*) FROM calendrier_amis_membres WHERE calendrier_id = ? AND user_id = ?', [$calendrier, $moi]) > 0;
    }

    /**
     * Un calendrier que je vois, avec ce qui m'y concerne : suis-je son créateur, qui l'a créé, combien de membres, ma couleur.
     * Null si je n'en suis pas membre.
     *
     * @return ?array<string, mixed>
     */
    public static function calendrier(int $id, int $moi): ?array
    {
        $ligne = Database::one(
            "SELECT c.id, c.proprietaire_id, c.nom, c.couleur AS couleur_calendrier, m.affiche, m.couleur AS couleur_perso,
                    COALESCE(u.pseudo, '') AS proprietaire_pseudo,
                    (SELECT COUNT(*) FROM calendrier_amis_membres x WHERE x.calendrier_id = c.id) AS membres
               FROM calendriers_amis c
               JOIN calendrier_amis_membres m ON m.calendrier_id = c.id AND m.user_id = ?
               JOIN users u ON u.id = c.proprietaire_id
              WHERE c.id = ?",
            [$moi, $id]
        );
        if ($ligne === null) {
            return null;
        }

        return self::completer($ligne, $moi);
    }

    /**
     * Les calendriers dont je suis membre, les miens d'abord.
     *
     * @return list<array<string, mixed>>
     */
    public static function liste(int $moi): array
    {
        $lignes = Database::all(
            "SELECT c.id, c.proprietaire_id, c.nom, c.couleur AS couleur_calendrier, m.affiche, m.couleur AS couleur_perso,
                    COALESCE(u.pseudo, '') AS proprietaire_pseudo,
                    (SELECT COUNT(*) FROM calendrier_amis_membres x WHERE x.calendrier_id = c.id) AS membres
               FROM calendrier_amis_membres m
               JOIN calendriers_amis c ON c.id = m.calendrier_id
               JOIN users u ON u.id = c.proprietaire_id
              WHERE m.user_id = ?
              ORDER BY (c.proprietaire_id = m.user_id) DESC, c.nom, c.id",
            [$moi]
        );

        return array_map(static fn (array $l): array => self::completer($l, $moi), $lignes);
    }

    /** Ajoute ce que les vues demandent : le créateur, la couleur qui s'applique à moi. */
    private static function completer(array $l, int $moi): array
    {
        $couleur = Serveurs::couleurValide($l['couleur_perso'] ?? null) ?? Serveurs::couleurValide($l['couleur_calendrier']) ?? self::COULEUR_DEFAUT;

        return [
            'id' => (int) $l['id'],
            'nom' => (string) $l['nom'],
            'proprietaire_id' => (int) $l['proprietaire_id'],
            'proprietaire_pseudo' => (string) $l['proprietaire_pseudo'],
            'est_proprietaire' => (int) $l['proprietaire_id'] === $moi,
            'membres' => (int) $l['membres'],
            'affiche' => (int) $l['affiche'] !== 0,
            'couleur' => $couleur,
            'couleur_calendrier' => (string) $l['couleur_calendrier'],
            'couleur_perso' => $l['couleur_perso'] === null ? null : (string) $l['couleur_perso'],
        ];
    }

    /**
     * Les membres d'un calendrier : le créateur en tête, puis les autres par pseudo.
     *
     * @return list<array{id: int, pseudo: string, proprietaire: bool}>
     */
    public static function membres(int $calendrier): array
    {
        $lignes = Database::all(
            "SELECT u.id, COALESCE(u.pseudo, '') AS pseudo, (u.id = c.proprietaire_id) AS proprietaire
               FROM calendrier_amis_membres m
               JOIN calendriers_amis c ON c.id = m.calendrier_id
               JOIN users u ON u.id = m.user_id
              WHERE m.calendrier_id = ?
              ORDER BY (u.id = c.proprietaire_id) DESC, u.pseudo, u.id",
            [$calendrier]
        );

        return array_map(static fn (array $l): array => [
            'id' => (int) $l['id'], 'pseudo' => (string) $l['pseudo'], 'proprietaire' => (int) $l['proprietaire'] === 1,
        ], $lignes);
    }

    /** Mes amis qu'on peut encore ajouter : ceux qui n'y sont pas déjà. @return list<array> */
    public static function aAjouter(int $calendrier, int $moi): array
    {
        $dedans = array_map('intval', array_column(Database::all('SELECT user_id FROM calendrier_amis_membres WHERE calendrier_id = ?', [$calendrier]), 'user_id'));

        return array_values(array_filter(Amis::liste($moi), static fn (array $a): bool => !in_array((int) $a['id'], $dedans, true)));
    }

    /**
     * Les calendriers partagés visés par un formulaire (les clés « ca12 » parmi les agendas cochés), réduits à ceux dont je suis membre.
     *
     * @param array<int, mixed> $cles
     * @return list<int>
     */
    public static function cibles(int $moi, array $cles): array
    {
        $ids = [];
        foreach ($cles as $cle) {
            if (is_string($cle) && preg_match('/^ca([0-9]{1,10})$/', $cle, $m) === 1 && self::estMembre((int) $m[1], $moi)) {
                $ids[(int) $m[1]] = true;
            }
        }

        return array_keys($ids);
    }

    // --- Créer, gérer --------------------------------------------------------------------------------------------------

    public static function nettoyerNom(string $nom): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $nom));
    }

    private static function problemeNom(string $nom): ?string
    {
        if ($nom === '') {
            return t('cam.err.nom_vide');
        }

        return mb_strlen($nom) > self::NOM_MAX ? t('cam.err.nom_long', ['max' => self::NOM_MAX]) : null;
    }

    /**
     * Crée un calendrier dont je suis le créateur et le premier membre, puis y fait entrer les amis choisis.
     *
     * @param list<int> $amis
     * @return array{0: ?int, 1: ?string}  l'identifiant, ou le refus
     */
    public static function creer(int $moi, string $nom, ?string $couleur, array $amis): array
    {
        $nom = self::nettoyerNom($nom);
        if (($probleme = self::problemeNom($nom)) !== null) {
            return [null, $probleme];
        }
        if ((int) Database::valeur('SELECT COUNT(*) FROM calendriers_amis WHERE proprietaire_id = ?', [$moi]) >= self::CALENDRIERS_MAX) {
            return [null, t('cam.err.trop_de_calendriers', ['max' => self::CALENDRIERS_MAX])];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run('INSERT INTO calendriers_amis (proprietaire_id, nom, couleur) VALUES (?, ?, ?)', [$moi, $nom, Serveurs::couleurValide($couleur) ?? self::COULEUR_DEFAUT]);
            $id = Database::dernierId();
            Database::run('INSERT INTO calendrier_amis_membres (calendrier_id, user_id) VALUES (?, ?)', [$id, $moi]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        self::ajouterMembres($moi, $id, $amis);

        return [$id, null];
    }

    /** Renomme et recolore le calendrier (son créateur). */
    public static function modifier(int $moi, int $id, string $nom, ?string $couleur): ?string
    {
        $calendrier = self::calendrier($id, $moi);
        if ($calendrier === null) {
            return t('cam.err.introuvable');
        }
        if (!$calendrier['est_proprietaire']) {
            return t('cam.err.createur_seul');
        }
        $nom = self::nettoyerNom($nom);
        if (($probleme = self::problemeNom($nom)) !== null) {
            return $probleme;
        }
        Database::run('UPDATE calendriers_amis SET nom = ?, couleur = ? WHERE id = ?', [$nom, Serveurs::couleurValide($couleur) ?? (string) $calendrier['couleur_calendrier'], $id]);

        return null;
    }

    /**
     * Fait entrer des amis (le créateur). Les comptes qui ne sont pas ses amis, ou qui y sont déjà, sont passés sous silence.
     *
     * @param list<int|string> $ids
     * @return int  combien sont entrés
     */
    public static function ajouterMembres(int $moi, int $id, array $ids): int
    {
        $calendrier = self::calendrier($id, $moi);
        if ($calendrier === null || !$calendrier['est_proprietaire']) {
            return 0;
        }
        $ajoutes = 0;
        foreach (array_values(array_unique(array_map('intval', $ids))) as $ami) {
            if ($ami === $moi || !Amis::sontAmis($moi, $ami) || self::estMembre($id, $ami)) {
                continue;
            }
            if ((int) Database::valeur('SELECT COUNT(*) FROM calendrier_amis_membres WHERE calendrier_id = ?', [$id]) >= self::MEMBRES_MAX) {
                break;
            }
            Database::run('INSERT IGNORE INTO calendrier_amis_membres (calendrier_id, user_id) VALUES (?, ?)', [$id, $ami]);
            $ajoutes++;
        }

        return $ajoutes;
    }

    /** Retire un membre (le créateur) : ses évènements restent au calendrier. */
    public static function retirerMembre(int $moi, int $id, int $membre): ?string
    {
        $calendrier = self::calendrier($id, $moi);
        if ($calendrier === null) {
            return t('cam.err.introuvable');
        }
        if (!$calendrier['est_proprietaire']) {
            return t('cam.err.createur_seul');
        }
        if ($membre === $moi) {
            return t('cam.err.createur_reste');
        }
        Database::run('DELETE FROM calendrier_amis_membres WHERE calendrier_id = ? AND user_id = ?', [$id, $membre]);

        return null;
    }

    /** Quitte un calendrier. Son créateur ne le quitte pas : il le supprime. */
    public static function quitter(int $moi, int $id): ?string
    {
        $calendrier = self::calendrier($id, $moi);
        if ($calendrier === null) {
            return t('cam.err.introuvable');
        }
        if ($calendrier['est_proprietaire']) {
            return t('cam.err.createur_reste');
        }
        Database::run('DELETE FROM calendrier_amis_membres WHERE calendrier_id = ? AND user_id = ?', [$id, $moi]);

        return null;
    }

    /** Supprime le calendrier et ses évènements, pour tous ses membres (son créateur). */
    public static function supprimer(int $moi, int $id): ?string
    {
        $calendrier = self::calendrier($id, $moi);
        if ($calendrier === null) {
            return t('cam.err.introuvable');
        }
        if (!$calendrier['est_proprietaire']) {
            return t('cam.err.createur_seul');
        }
        Database::run('DELETE FROM calendriers_amis WHERE id = ?', [$id]);

        return null;
    }

    // --- Le volet du calendrier ----------------------------------------------------------------------------------------

    /**
     * Les calendriers partagés comme lignes du volet des agendas.
     *
     * @return list<array{cle: string, nom: string, affiche: bool, couleur: string, partage: bool, agenda: string, calendrier_id: int}>
     */
    public static function sourcesDuVolet(int $moi): array
    {
        return array_map(static fn (array $c): array => [
            'cle' => self::cle($c['id']),
            'nom' => $c['nom'],
            'affiche' => $c['affiche'],
            'couleur' => $c['couleur'],
            'partage' => true,
            'agenda' => '',
            'calendrier_id' => $c['id'],
        ], self::liste($moi));
    }

    /**
     * Retient les calendriers cochés dans le volet : ceux dont la clé est dans la liste s'affichent, les autres sont masqués.
     *
     * @param array<int, mixed> $cles
     */
    public static function montrer(int $moi, array $cles): void
    {
        $cochees = [];
        foreach ($cles as $cle) {
            if (is_string($cle) && preg_match('/^ca([0-9]{1,10})$/', $cle, $m) === 1) {
                $cochees[(int) $m[1]] = true;
            }
        }
        foreach (self::liste($moi) as $c) {
            $veut = isset($cochees[$c['id']]);
            if ($veut !== $c['affiche']) {
                Database::run('UPDATE calendrier_amis_membres SET affiche = ? WHERE calendrier_id = ? AND user_id = ?', [$veut ? 1 : 0, $c['id'], $moi]);
            }
        }
    }

    /**
     * Retient les couleurs choisies dans le volet (« ca12 » => « #aabbcc »). Une couleur qui est celle du créateur n'est pas gardée :
     * si le créateur la change plus tard, le membre qui ne l'a jamais touchée suit.
     *
     * @param array<string, mixed> $couleurs
     */
    public static function colorier(int $moi, array $couleurs): void
    {
        foreach ($couleurs as $cle => $couleur) {
            if (!is_string($cle) || preg_match('/^ca([0-9]{1,10})$/', $cle, $m) !== 1 || ($choisie = Serveurs::couleurValide($couleur)) === null) {
                continue;
            }
            $c = self::calendrier((int) $m[1], $moi);
            if ($c === null || $choisie === $c['couleur']) {
                continue;
            }
            Database::run(
                'UPDATE calendrier_amis_membres SET couleur = ? WHERE calendrier_id = ? AND user_id = ?',
                [$choisie === strtolower($c['couleur_calendrier']) ? null : $choisie, $c['id'], $moi]
            );
        }
    }

    // --- Les évènements ------------------------------------------------------------------------------------------------

    /**
     * Les évènements des calendriers que je veux voir, entre deux dates, sous la forme des lignes du calendrier.
     * Ils s'ouvrent dans leur propre fenêtre (« calendriers-amis/evenements/{id} »).
     *
     * @return list<array<string, mixed>>
     */
    public static function evenementsAffiches(int $moi, DateTimeInterface $debut, DateTimeInterface $fin): array
    {
        $lignes = Database::all(
            "SELECT e.*, c.nom AS calendrier_nom, c.couleur AS couleur_calendrier, m.couleur AS couleur_perso
               FROM calendrier_amis_membres m
               JOIN calendriers_amis c ON c.id = m.calendrier_id
               JOIN calendrier_amis_evenements e ON e.calendrier_id = c.id
              WHERE m.user_id = ? AND m.affiche = 1 AND e.debut <= ? AND e.fin >= ?
              ORDER BY e.debut, e.fin, e.id",
            [$moi, $fin->format('Y-m-d H:i:s'), $debut->format('Y-m-d H:i:s')]
        );

        $evenements = [];
        foreach ($lignes as $l) {
            $couleur = Serveurs::couleurValide($l['couleur_perso']) ?? Serveurs::couleurValide($l['couleur_calendrier']) ?? self::COULEUR_DEFAUT;
            $evenements[] = [
                'id' => self::DECALAGE_ID + (int) $l['id'],
                'titre' => (string) $l['titre'],
                'description' => $l['description'],
                'lieu' => $l['lieu'],
                'debut' => (string) $l['debut'],
                'fin' => (string) $l['fin'],
                'journee_entiere' => (int) $l['journee_entiere'],
                'termine' => 0,
                'type_nom' => (string) $l['calendrier_nom'],
                'type_icone' => '👥',
                'type_couleur' => $couleur,
                'agenda_nom' => (string) $l['calendrier_nom'],
                'agenda_couleur' => $couleur,
                'matiere_id' => null, 'matiere_nom' => null, 'matiere_couleur' => null,
                'cours_id' => null, 'cours_titre' => null, 'serie_id' => null,
                'est_echeance' => 0,
                'outlook_calendrier' => null,
                'est_tache' => false,
                'est_partage' => true,
                'lien' => url('calendriers-amis/evenements/' . (int) $l['id']),
            ];
        }

        return $evenements;
    }

    /**
     * Un évènement d'un calendrier partagé que je peux voir, avec le calendrier, son auteur, et ce que j'ai le droit d'en faire.
     *
     * @return ?array<string, mixed>
     */
    public static function evenement(int $id, int $moi): ?array
    {
        $l = Database::one(
            "SELECT e.*, COALESCE(u.pseudo, '') AS auteur_pseudo FROM calendrier_amis_evenements e
               LEFT JOIN users u ON u.id = e.auteur_id WHERE e.id = ?",
            [$id]
        );
        if ($l === null) {
            return null;
        }
        $calendrier = self::calendrier((int) $l['calendrier_id'], $moi);
        if ($calendrier === null) {
            return null;
        }
        $l['calendrier'] = $calendrier;
        // Le sien, ou n'importe lequel quand on gère le calendrier.
        $l['peut_modifier'] = $calendrier['est_proprietaire'] || ((int) ($l['auteur_id'] ?? 0) === $moi);

        return $l;
    }

    /**
     * Les prochains évènements d'un calendrier, pour sa fenêtre.
     *
     * @return list<array<string, mixed>>
     */
    public static function aVenir(int $calendrier, int $limite = 8): array
    {
        return Database::all(
            "SELECT e.id, e.titre, e.debut, e.fin, e.journee_entiere, e.lieu, COALESCE(u.pseudo, '') AS auteur_pseudo
               FROM calendrier_amis_evenements e LEFT JOIN users u ON u.id = e.auteur_id
              WHERE e.calendrier_id = ? AND e.fin >= NOW()
              ORDER BY e.debut, e.id LIMIT " . max(1, $limite),
            [$calendrier]
        );
    }

    /**
     * Lit le formulaire d'un évènement (mêmes champs que celui du calendrier personnel, sans les rappels ni les répétitions).
     *
     * @param array<string, mixed> $post
     * @return array{0: ?array<string, mixed>, 1: ?string}  les valeurs prêtes pour la base, ou le refus
     */
    public static function lireFormulaire(array $post): array
    {
        $texte = static fn (string $cle): string => is_string($post[$cle] ?? null) ? trim($post[$cle]) : '';
        $titre = $texte('titre');
        if ($titre === '') {
            return [null, t('cam.err.titre_vide')];
        }
        if (mb_strlen($titre) > 200) {
            return [null, t('cam.err.titre_long')];
        }
        $dateDebut = $texte('date_debut');
        $dateFin = $texte('date_fin') !== '' ? $texte('date_fin') : $dateDebut;
        foreach ([$dateDebut, $dateFin] as $d) {
            $objet = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
            if ($objet === false || $objet->format('Y-m-d') !== $d) {
                return [null, t('cam.err.date')];
            }
        }
        $journee = isset($post['journee_entiere']);
        if ($journee) {
            $debut = $dateDebut . ' 00:00:00';
            $fin = $dateFin . ' 23:59:59';
        } else {
            $heureDebut = $texte('heure_debut') !== '' ? $texte('heure_debut') : '08:00';
            $heureFin = $texte('heure_fin') !== '' ? $texte('heure_fin') : (new DateTimeImmutable($dateDebut . ' ' . $heureDebut))->modify('+1 hour')->format('H:i');
            foreach ([$heureDebut, $heureFin] as $h) {
                if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h) !== 1) {
                    return [null, t('cam.err.heure')];
                }
            }
            $debut = $dateDebut . ' ' . $heureDebut . ':00';
            $fin = $dateFin . ' ' . $heureFin . ':00';
        }
        if ($fin < $debut) {
            return [null, t('cam.err.fin_avant_debut')];
        }
        $lieu = mb_substr($texte('lieu'), 0, 160);
        $description = TexteRiche::depuisFormulaire($texte('description'));
        if (strlen($description) > 60000) {
            return [null, t('cam.err.notes_longues')];
        }

        return [[
            'titre' => $titre,
            'description' => $description === '' ? null : $description,
            'lieu' => $lieu === '' ? null : $lieu,
            'debut' => $debut,
            'fin' => $fin,
            'journee_entiere' => $journee ? 1 : 0,
        ], null];
    }

    /**
     * Ajoute un évènement (tout membre).
     *
     * @param array<string, mixed> $donnees  voir lireFormulaire()
     * @return array{0: ?int, 1: ?string}
     */
    public static function creerEvenement(int $moi, int $calendrier, array $donnees): array
    {
        if (self::calendrier($calendrier, $moi) === null) {
            return [null, t('cam.err.introuvable')];
        }
        Database::run(
            'INSERT INTO calendrier_amis_evenements (calendrier_id, auteur_id, titre, description, lieu, debut, fin, journee_entiere)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$calendrier, $moi, $donnees['titre'], $donnees['description'], $donnees['lieu'], $donnees['debut'], $donnees['fin'], $donnees['journee_entiere']]
        );

        return [Database::dernierId(), null];
    }

    /** Modifie un évènement (son auteur, ou le créateur du calendrier). */
    public static function modifierEvenement(int $moi, int $id, array $donnees): ?string
    {
        $evenement = self::evenement($id, $moi);
        if ($evenement === null) {
            return t('cam.err.evenement_introuvable');
        }
        if (!$evenement['peut_modifier']) {
            return t('cam.err.pas_le_votre');
        }
        Database::run(
            'UPDATE calendrier_amis_evenements SET titre = ?, description = ?, lieu = ?, debut = ?, fin = ?, journee_entiere = ? WHERE id = ?',
            [$donnees['titre'], $donnees['description'], $donnees['lieu'], $donnees['debut'], $donnees['fin'], $donnees['journee_entiere'], $id]
        );

        return null;
    }

    /** Supprime un évènement (son auteur, ou le créateur du calendrier). */
    public static function supprimerEvenement(int $moi, int $id): ?string
    {
        $evenement = self::evenement($id, $moi);
        if ($evenement === null) {
            return t('cam.err.evenement_introuvable');
        }
        if (!$evenement['peut_modifier']) {
            return t('cam.err.pas_le_votre');
        }
        Database::run('DELETE FROM calendrier_amis_evenements WHERE id = ?', [$id]);

        return null;
    }
}
