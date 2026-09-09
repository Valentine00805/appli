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
     * Les agendas qu'on peut montrer ou masquer dans le calendrier.
     *
     * Tous fournisseurs confondus, et « Mes évènements » en tête : ce sont les
     * siens, ils n'appartiennent à aucun agenda distant.
     *
     * @return array<int, array{cle: string, nom: string, affiche: bool,
     *                          couleur: string, partage: bool, agenda: string}>
     */
    public static function sourcesDuCalendrier(int $userId): array
    {
        $relies = self::relies($userId);
        if ($relies === []) {
            return [];
        }

        $moi = Database::one(
            'SELECT afficher_miens, couleur_miens FROM users WHERE id = ?', [$userId]);

        $sources = [[
            'cle'     => SynchroAgenda::MIENS,
            'nom'     => 'Mes évènements',
            'affiche' => (int) ($moi['afficher_miens'] ?? 1) !== 0,
            'couleur' => self::couleurOuDefaut((string) ($moi['couleur_miens'] ?? ''), 0),
            'partage' => false,
            'agenda'  => '',
        ]];

        $rang = 1;
        foreach ($relies as $f) {
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
        }

        return $sources;
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
     * Les agendas de quelqu'un d'autre où l'on a le droit de déposer.
     *
     * Les siens n'y figurent pas : ce qu'on crée ici y arrive déjà tout seul,
     * par « Mes Cours ». Ne restent que les agendas partagés — et parmi eux,
     * seulement ceux dont le propriétaire a accordé la modification.
     *
     * @return array<int, array{cle: string, nom: string, chez: string,
     *                          agenda: string, fournisseur: string}>
     */
    public static function ouDeposer(int $userId): array
    {
        $ou = [];
        foreach (self::relies($userId) as $f) {
            foreach (Database::all(
                'SELECT empreinte, nom, proprietaire FROM agenda_calendriers
                  WHERE user_id = ? AND fournisseur = ? AND partage = 1 AND peut_ecrire = 1
                  ORDER BY nom',
                [$userId, $f->cle()]
            ) as $cal) {
                $ou[] = [
                    'cle'         => (string) $cal['empreinte'],
                    'nom'         => (string) ($cal['nom'] ?? 'Agenda'),
                    'chez'        => (string) ($cal['proprietaire'] ?? ''),
                    'agenda'      => $f->nom(),
                    'fournisseur' => $f->cle(),
                ];
            }
        }

        return $ou;
    }

    /**
     * Les agendas à soi qui peuvent recevoir ce qu'on crée dans l'application.
     *
     * L'inverse exact de « ouDeposer » : là-bas les agendas des autres, ici
     * les siens. Un agenda qu'on nous a partagé n'a pas sa place dans cette
     * liste — y déverser tous ses évènements et toutes ses échéances de
     * tâches, sans que le propriétaire l'ait demandé, n'est pas un partage,
     * c'est une invasion.
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
     * Combien d'agendas partagés on connaît, quel que soit le droit d'écriture.
     *
     * Sert à distinguer deux silences très différents : « personne ne vous a
     * partagé son agenda » et « on ne vous y laisse pas écrire ».
     */
    public static function combienDePartages(int $userId): int
    {
        return (int) Database::valeur(
            'SELECT COUNT(*) FROM agenda_calendriers WHERE user_id = ? AND partage = 1', [$userId]);
    }

    /**
     * Ce qui a déjà été déposé pour un évènement.
     *
     * @return array<int, array>
     */
    public static function depots(int $userId, int $evenementId): array
    {
        return Database::all(
            'SELECT calendrier_empreinte, calendrier_nom, chez, titre, depose_le
               FROM agenda_depots
              WHERE user_id = ? AND evenement_id = ?
              ORDER BY depose_le',
            [$userId, $evenementId]
        );
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
