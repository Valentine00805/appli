<?php
declare(strict_types=1);

/**
 * Les agendas distants que l'application sait relier.
 *
 * Un point unique où les nommer, et où dire lesquels cette installation a
 * réellement configurés. Le reste du code ne connaît que des Fournisseur ;
 * seul cet endroit sait qu'ils s'appellent Outlook et Google Agenda.
 */
final class Agenda
{
    /** @var array<string, Fournisseur>|null */
    private static ?array $tous = null;

    /**
     * Tous les agendas connus de l'application, configurés ou non.
     *
     * @return array<string, Fournisseur>
     */
    public static function tous(): array
    {
        return self::$tous ??= [
            'microsoft' => new FournisseurMicrosoft(),
            'google'    => new FournisseurGoogle(),
        ];
    }

    /** Un agenda par sa clé, ou null si ce nom ne désigne rien. */
    public static function pour(string $cle): ?Fournisseur
    {
        return self::tous()[$cle] ?? null;
    }

    /**
     * Ceux que cette installation a inscrits, et qu'on peut donc proposer.
     *
     * @return array<string, Fournisseur>
     */
    public static function configures(): array
    {
        return array_filter(
            self::tous(),
            static fn (Fournisseur $f): bool => LiaisonAgenda::pour($f)->configure()
        );
    }

    /**
     * Les agendas qu'on peut montrer ou masquer, rangés par fournisseur.
     *
     * Une liste à plat mettait sur le même plan ses propres évènements, le
     * calendrier d'un proche et les jours fériés : dix cases dont rien ne
     * disait d'où elles venaient. Outlook et Google les groupent, et l'œil
     * cherche la bonne section avant de chercher la bonne ligne.
     *
     * Une section vide ne s'affiche pas — un agenda relié dont aucun
     * calendrier n'est suivi n'a rien à montrer.
     *
     * @return array<int, array{titre: string, sources: array<int, array{
     *             cle: string, nom: string, affiche: bool, couleur: string,
     *             partage: bool, agenda: string}>}>
     */
    public static function sourcesDuCalendrier(int $userId): array
    {
        $relies = self::relies($userId);
        if ($relies === []) {
            return [];
        }

        $moi = Database::one(
            'SELECT afficher_miens, couleur_miens FROM users WHERE id = ?', [$userId]);

        $sections = [[
            // Le nom de l'application : ce qui est à soi porte son nom.
            'titre'   => (string) (Config::get('app', 'nom') ?? '') ?: 'Mes Cours',
            'sources' => [[
                'cle'     => SynchroAgenda::MIENS,
                'nom'     => 'Mes évènements',
                'affiche' => (int) ($moi['afficher_miens'] ?? 1) !== 0,
                'couleur' => self::couleurOuDefaut((string) ($moi['couleur_miens'] ?? ''), 0),
                'partage' => false,
                'agenda'  => '',
            ]],
        ]];

        $rang = 1;
        foreach ($relies as $f) {
            $sources = [];
            foreach (SynchroAgenda::pour($f)->calendriers($userId) as $cal) {
                if ((int) $cal['suivi'] !== 1) {
                    continue;
                }
                $sources[] = [
                    'cle'     => (string) $cal['empreinte'],
                    'nom'     => (string) ($cal['nom'] ?? 'Calendrier'),
                    'affiche' => (int) $cal['affiche'] !== 0,
                    'couleur' => self::couleurOuDefaut((string) ($cal['couleur'] ?? ''), $rang++),
                    'partage' => (int) $cal['partage'] === 1,
                    'agenda'  => $f->nom(),
                ];
            }
            if ($sources !== []) {
                $sections[] = ['titre' => $f->nom(), 'sources' => $sources];
            }
        }

        return $sections;
    }

    /**
     * La couleur d'un agenda, ou celle que la palette lui réserve.
     *
     * Un agenda tout neuf n'en a pas encore : plutôt que de le rendre gris
     * comme ses voisins — et donc indistinct, ce qui est exactement ce qu'on
     * veut éviter —, on lui en prête une.
     */
    public static function couleurOuDefaut(string $couleur, int $rang): string
    {
        $couleur = strtolower(trim($couleur));
        if (preg_match('/^#[0-9a-f]{6}$/', $couleur) === 1) {
            return $couleur;
        }

        $palette = MatieresController::PALETTE;

        return $palette[$rang % count($palette)];
    }

    /**
     * Retient les agendas cochés dans le volet, quel que soit le fournisseur.
     *
     * @param array<int, string> $cles
     */
    public static function montrer(int $userId, array $cles): void
    {
        $garder = [];
        foreach ($cles as $cle) {
            if (is_string($cle) && preg_match('/^[0-9a-f]{32}$/', $cle) === 1) {
                $garder[$cle] = true;
            }
        }

        Database::run('UPDATE users SET afficher_miens = ? WHERE id = ?',
            [in_array(SynchroAgenda::MIENS, $cles, true) ? 1 : 0, $userId]);

        foreach (Database::all(
            'SELECT empreinte, affiche FROM agenda_calendriers WHERE user_id = ?', [$userId]
        ) as $cal) {
            $veut = isset($garder[(string) $cal['empreinte']]) ? 1 : 0;
            if ((int) $cal['affiche'] === $veut) {
                continue;
            }
            Database::run(
                'UPDATE agenda_calendriers SET affiche = ? WHERE user_id = ? AND empreinte = ?',
                [$veut, $userId, (string) $cal['empreinte']]
            );
        }
    }

    /**
     * Retient les couleurs choisies dans le volet.
     *
     * @param array<string, string> $couleurs
     */
    public static function colorier(int $userId, array $couleurs): void
    {
        foreach ($couleurs as $cle => $couleur) {
            if (!is_string($cle) || !is_string($couleur)
                || preg_match('/^#[0-9a-f]{6}$/i', $couleur) !== 1) {
                continue;
            }
            $couleur = strtolower($couleur);

            if ($cle === SynchroAgenda::MIENS) {
                Database::run('UPDATE users SET couleur_miens = ? WHERE id = ?', [$couleur, $userId]);
                continue;
            }
            if (preg_match('/^[0-9a-f]{32}$/', $cle) !== 1) {
                continue;
            }
            Database::run(
                'UPDATE agenda_calendriers SET couleur = ? WHERE user_id = ? AND empreinte = ?',
                [$couleur, $userId, $cle]
            );
        }
    }

    /**
     * Ce que le calendrier doit taire.
     *
     * @return array{miens: bool, calendriers: array<int, string>}
     */
    public static function masques(int $userId): array
    {
        $miens = Database::valeur('SELECT afficher_miens FROM users WHERE id = ?', [$userId]);

        $caches = [];
        foreach (Database::all(
            'SELECT empreinte FROM agenda_calendriers WHERE user_id = ? AND affiche = 0', [$userId]
        ) as $ligne) {
            $caches[] = (string) $ligne['empreinte'];
        }

        return ['miens' => (int) $miens === 0, 'calendriers' => $caches];
    }

    /**
     * Tous les agendas où un évènement peut être envoyé, fournisseurs confondus.
     *
     * Les siens, et ceux qu'on nous a ouverts en modification : « Votre
     * famille » est l'agenda de quelqu'un d'autre, mais y écrire nous a été
     * accordé, et ce qu'on y met s'y corrige et s'y supprime comme ailleurs.
     * Ce qui est refusé ici l'est parce que le fournisseur le refuserait.
     *
     * « Mes Cours » n'y figure pas : c'est là que va « Mes évènements », le
     * choix par défaut, et le proposer une seconde fois sous son autre nom ne
     * ferait que semer le doute.
     *
     * @return array<int, array{cle: string, nom: string, agenda: string,
     *                          fournisseur: string, partage: bool}>
     */
    public static function ouEnvoyer(int $userId): array
    {
        $ou = [];
        foreach (self::relies($userId) as $f) {
            $actuelle = EnvoiAgenda::pour($f)->destination($userId);
            $reflet = $actuelle['choisi'] || $actuelle['id'] === null ? '' : md5($actuelle['id']);

            foreach (Database::all(
                'SELECT empreinte, nom, proprietaire, partage FROM agenda_calendriers
                  WHERE user_id = ? AND fournisseur = ? AND peut_ecrire = 1 AND empreinte <> ?
                  ORDER BY partage, principal DESC, nom',
                [$userId, $f->cle(), $reflet]
            ) as $cal) {
                $ou[] = [
                    'cle'         => (string) $cal['empreinte'],
                    'nom'         => (string) ($cal['nom'] ?? 'Agenda'),
                    'agenda'      => $f->nom(),
                    'fournisseur' => $f->cle(),
                    'partage'     => (int) $cal['partage'] === 1,
                ];
            }
        }

        return $ou;
    }

    /**
     * L'empreinte qui désigne le calendrier de l'application.
     *
     * Vide plutôt qu'un mot choisi : c'est la valeur qu'une case décochée
     * laisse derrière elle, et celle qu'ont déjà tous les évènements écrits
     * avant que le choix existe.
     *
     * À ne pas confondre avec SynchroAgenda::MIENS, qui nomme la case « Mes
     * évènements » du volet : celle-là dit ce qu'on affiche, celle-ci où l'on
     * écrit.
     */
    public const DEFAUT = '';

    /**
     * Les agendas cochés, réduits à ceux qui existent et qu'on peut écrire.
     *
     * On repart de la liste plutôt que du formulaire : une empreinte soumise à
     * la main, ou celle d'un calendrier délié depuis, ne doit pas suffire à
     * faire écrire l'application quelque part.
     *
     * Rien de valable ne reste ? Alors « Mes évènements » — un évènement doit
     * bien aller quelque part, et c'est là qu'il allait avant qu'on choisisse.
     *
     * @param  array<int, mixed> $cles  ce que le formulaire a envoyé
     * @return array<int, string>       les empreintes retenues, jamais vide
     */
    public static function ciblesValides(int $userId, array $cles): array
    {
        $connus = [];
        foreach (self::ouEnvoyer($userId) as $cal) {
            $connus[$cal['cle']] = true;
        }

        $garder = [];
        foreach ($cles as $cle) {
            if ($cle === self::DEFAUT) {
                $garder[self::DEFAUT] = true;
                continue;
            }
            if (is_string($cle) && isset($connus[$cle])) {
                $garder[$cle] = true;
            }
        }

        return $garder === [] ? [self::DEFAUT] : array_keys($garder);
    }

    /**
     * Les agendas où part un évènement, tels qu'ils sont retenus.
     *
     * @return array<int, string>  jamais vide : sans ligne, c'est « Mes évènements »
     */
    public static function ciblesDe(int $evenementId): array
    {
        $cibles = [];
        foreach (Database::all(
            'SELECT empreinte FROM evenement_agendas WHERE evenement_id = ?', [$evenementId]
        ) as $ligne) {
            $cibles[] = (string) $ligne['empreinte'];
        }

        return $cibles === [] ? [self::DEFAUT] : $cibles;
    }

    /**
     * Retient les agendas d'un évènement.
     *
     * @param array<int, string> $cibles  déjà passées par « ciblesValides »
     */
    public static function viser(int $evenementId, array $cibles): void
    {
        Database::run('DELETE FROM evenement_agendas WHERE evenement_id = ?', [$evenementId]);

        foreach ($cibles as $empreinte) {
            Database::run(
                'INSERT INTO evenement_agendas (evenement_id, empreinte) VALUES (?, ?)',
                [$evenementId, $empreinte]
            );
        }
    }

    /**
     * Les agendas à soi qui peuvent recevoir ce qu'on crée dans l'application.
     *
     * Un agenda qu'on nous a partagé n'a pas sa place dans cette liste : y
     * déverser tous ses évènements et toutes ses échéances de tâches, sans
     * que le propriétaire l'ait demandé, n'est pas un partage.
     *
     * @return array<int, array{cle: string, nom: string, principal: bool}>
     */
    public static function ouEcrire(int $userId, Fournisseur $f): array
    {
        /*
         * « Mes Cours » est un agenda à soi comme les autres — il remplit
         * toutes les conditions de la requête ci-dessous. Mais il est déjà
         * proposé à part, comme le choix par défaut, et le voir deux fois dans
         * la même liste ferait douter de ce qu'on est en train de choisir.
         */
        $actuelle = EnvoiAgenda::pour($f)->destination($userId);
        $reflet = $actuelle['choisi'] || $actuelle['id'] === null ? '' : md5($actuelle['id']);

        $ou = [];
        foreach (Database::all(
            'SELECT empreinte, nom, principal FROM agenda_calendriers
              WHERE user_id = ? AND fournisseur = ? AND partage = 0 AND peut_ecrire = 1
                AND empreinte <> ?
              ORDER BY principal DESC, nom',
            [$userId, $f->cle(), $reflet]
        ) as $cal) {
            $ou[] = [
                'cle'       => (string) $cal['empreinte'],
                'nom'       => (string) ($cal['nom'] ?? 'Agenda'),
                'principal' => (int) $cal['principal'] === 1,
            ];
        }

        return $ou;
    }

    /**
     * Un agenda a-t-il quelque chose à faire, chez n'importe quel fournisseur ?
     *
     * C'est ce qui décide si la page réveille la synchronisation en arrière-plan.
     */
    public static function aQuelqueChoseAFaire(int $userId): bool
    {
        foreach (self::relies($userId) as $f) {
            if (SynchroAgenda::pour($f)->aBesoinDEtreRelu($userId)
                || EnvoiAgenda::pour($f)->aPousser($userId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ceux qu'une personne a effectivement reliés.
     *
     * @return array<string, Fournisseur>
     */
    public static function relies(int $userId): array
    {
        return array_filter(
            self::configures(),
            static fn (Fournisseur $f): bool => LiaisonAgenda::pour($f)->relie($userId)
        );
    }
}
