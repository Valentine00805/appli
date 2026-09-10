<?php
declare(strict_types=1);

final class CalendrierController
{
    /**
     * Le pas de chaque répétition.
     *
     * Le mois se traite à part : « +1 month » sur un 31 janvier donne un
     * 3 mars, ce que personne n'attend d'un rendez-vous mensuel.
     */
    private const RYTHMES = [
        'jour'      => 1,
        'semaine'   => 7,
        'quinzaine' => 14,
        'mois'      => 0,
    ];

    /**
     * Ce qu'une série ne dépassera pas.
     *
     * Les occurrences sont écrites une par une : sans borne, « chaque jour »
     * remplirait la base et l'agenda de quelqu'un pour l'éternité. Deux ans et
     * deux cents occurrences couvrent une année scolaire avec de la marge.
     */
    /**
     * Les rythmes hebdomadaires, seuls à admettre un choix de jours.
     *
     * « Chaque jour » les prend déjà tous, et « chaque mois » se compte en
     * quantièmes, pas en jours de semaine.
     */
    private const RYTHMES_A_JOURS = ['semaine' => 1, 'quinzaine' => 2];

    private const SERIE_MAX = 200;
    private const SERIE_HORIZON = '+2 years';

    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $vue = self::vueDemandee($userId);
        $ancre = $this->dateAncre();

        [$debut, $fin] = match ($vue) {
            'jour'    => [$ancre->setTime(0, 0), $ancre->setTime(23, 59, 59)],
            'semaine' => $this->bornesSemaine($ancre),
            'liste'   => [(clone $ancre)->modify('first day of this month')->setTime(0, 0),
                          (clone $ancre)->modify('+1 year')->setTime(23, 59, 59)],
            default   => $this->bornesMois($ancre),
        };

        $matiereId = entier_ou_null($_GET['matiere'] ?? null);
        $typeId = $this->typeValide($userId, $_GET['type'] ?? null);

        $evenements = $this->evenements($userId, $debut, $fin, $matiereId, $typeId);

        // Les échéances des tâches s'invitent dans le calendrier sans y être
        // recopiées : elles sont relues à chaque affichage, donc toujours à
        // jour. Un filtre par matière ou par type les écarte, n'en ayant pas.
        // Elles suivent le sort de « Mes évènements » dans le volet : ce sont
        // les siennes, et les masquer à moitié n'aurait pas de sens.
        if ($matiereId === null && $typeId === null
            && !Agenda::masques($userId)['miens']) {
            $evenements = array_merge($evenements, self::echeancesEntre($userId, $debut, $fin));
            usort($evenements, static fn (array $a, array $b): int => $a['debut'] <=> $b['debut']);
        }

        Vue::afficher('calendrier/index', [
            'vue'           => $vue,
            'ancre'         => $ancre,
            'debut'         => $debut,
            'fin'           => $fin,
            'evenements'    => $evenements,
            'parJour'       => $this->grouperParJour($evenements),
            'matieres'      => $this->matieres($userId),
            'matiereId'     => $matiereId,
            'types'         => TypesEvenementController::pourUtilisateur($userId),
            'typeId'        => $typeId,
            'aVenir'        => $this->aVenir($userId, 6),
            'sources'       => Agenda::sourcesDuCalendrier($userId),
        ], 'Calendrier');
    }

    /** Les vues que le calendrier sait afficher. */
    public const VUES = ['jour', 'semaine', 'mois', 'liste'];

    /**
     * La vue à ouvrir : celle demandée, sinon celle qu'on préfère.
     *
     * L'adresse garde le dernier mot. Changer de vue d'un clic ne doit pas
     * devenir un choix définitif — on regarde sa semaine, on revient à son
     * mois, et le réglage n'a pas bougé.
     */
    private static function vueDemandee(int $userId): string
    {
        if (in_array($_GET['vue'] ?? '', self::VUES, true)) {
            return (string) $_GET['vue'];
        }

        return self::vuePreferee($userId);
    }

    /** Celle qu'on retrouve en arrivant, le mois à défaut. */
    public static function vuePreferee(int $userId): string
    {
        $voulue = (string) (Database::valeur(
            'SELECT vue_calendrier FROM users WHERE id = ?', [$userId]) ?? '');

        return in_array($voulue, self::VUES, true) ? $voulue : 'mois';
    }

    /**
     * Retient la vue qu'on veut retrouver.
     *
     * Une valeur qui ne désigne rien remet le mois plutôt que d'échouer : le
     * réglage n'est pas assez important pour mériter un message d'erreur.
     */
    public function vue(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $voulue = (string) ($_POST['vue'] ?? '');
        Database::run('UPDATE users SET vue_calendrier = ? WHERE id = ?',
            [in_array($voulue, self::VUES, true) ? $voulue : null, Auth::id()]);

        Session::flash('succes', 'Le calendrier s’ouvrira désormais sur '
            . match ($voulue) {
                'jour'    => 'la journée',
                'semaine' => 'la semaine',
                'liste'   => 'la liste',
                default   => 'le mois',
            } . '.');
        repartir_vers('agenda');
    }

    /**
     * Retient les agendas cochés dans le volet.
     *
     * Rien n'est synchronisé ni effacé : on choisit ce qu'on regarde, pas ce
     * que l'application va chercher.
     */
    public function sources(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $coches = $_POST['sources'] ?? [];
        Agenda::montrer(Auth::id(), is_array($coches) ? $coches : []);

        $couleurs = $_POST['couleur'] ?? [];
        Agenda::colorier(Auth::id(), is_array($couleurs) ? $couleurs : []);

        repartir_vers('calendrier');
    }

    /** Formulaire de création (id null) ou de modification d'un événement. */
    /**
     * Retient les sections du volet qu'on a repliées.
     *
     * Appelée en arrière-plan quand on plie ou déplie. Sans elle, le
     * formulaire du volet — qui se renvoie tout seul dès qu'on coche un agenda
     * — rouvrirait au clic suivant ce qu'on venait de fermer.
     *
     * Sans JavaScript, plier fonctionne quand même : c'est le navigateur qui
     * s'en charge. Seule la mémoire manque, et rien d'important n'en dépend.
     */
    public function volet(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $replies = $_POST['replie'] ?? [];
        Agenda::replier(Auth::id(), is_array($replies) ? $replies : []);

        if (veut_du_json()) {
            repondre_json(['fait' => true]);
        }
        repartir_vers('calendrier');
    }

    /**
     * Un évènement, en lecture.
     *
     * Cliquer sur un rendez-vous dans le calendrier ouvrait le formulaire de
     * modification : on ne pouvait pas le consulter sans se retrouver, sans
     * l'avoir demandé, en train de le changer. Un champ effleuré, un
     * enregistrement machinal, et l'horaire n'est plus le bon.
     *
     * Cette page ne fait que montrer. Modifier reste à un clic, mais c'est un
     * clic qu'on donne.
     */
    public function voir(int $id): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $evenement = Database::one(
            'SELECT e.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                    c.titre AS cours_titre,
                    t.nom AS type_nom, t.icone AS type_icone, t.couleur AS type_couleur,
                    oc.nom AS agenda_nom, oc.couleur AS agenda_couleur,
                    oc.proprietaire AS agenda_proprietaire, oc.partage AS agenda_partage,
                    ol.fournisseur AS agenda_fournisseur
               FROM evenements e
               LEFT JOIN matieres m        ON m.id = e.matiere_id
               LEFT JOIN cours c           ON c.id = e.cours_id
               LEFT JOIN types_evenement t ON t.id = e.type_id
               LEFT JOIN agenda_liens ol   ON ol.evenement_id = e.id AND ol.user_id = e.user_id
               LEFT JOIN agenda_calendriers oc
                      ON oc.user_id = e.user_id AND oc.empreinte = ol.calendrier
              WHERE e.id = ? AND e.user_id = ?',
            [$id, $userId]
        );
        if ($evenement === null) {
            $this->introuvable();
        }

        /*
         * Où il part, sous les noms qu'on lui connaît. Un agenda délié depuis
         * n'apparaît pas : la liste des destinations sert de dictionnaire.
         */
        $noms = [];
        foreach (Agenda::ouEnvoyer($userId) as $cal) {
            $noms[$cal['cle']] = $cal['nom'] . ' (' . $cal['agenda'] . ')';
        }
        $vises = [];
        foreach (Agenda::ciblesDe($id) as $cible) {
            if ($cible === Agenda::DEFAUT) {
                $vises[] = 'Mes évènements';
            } elseif (isset($noms[$cible])) {
                $vises[] = $noms[$cible];
            }
        }

        /*
         * Demandée en fragment, la fiche part seule : c'est le script de la
         * page qui l'enveloppe dans une fenêtre. Sans lui, l'adresse répond
         * une page entière et l'évènement s'ouvre normalement — la fenêtre
         * est un confort, pas un passage obligé.
         */
        $donnees = [
            'evenement' => $evenement,
            'serie'     => $evenement['serie_id'] === null ? null
                : Database::one('SELECT * FROM series_evenements WHERE id = ? AND user_id = ?',
                    [(int) $evenement['serie_id'], $userId]),
            'vises'     => $vises,
            'copie'     => Database::one('SELECT id, titre FROM evenements
                                           WHERE copie_de = ? AND user_id = ?', [$id, $userId]),
            'origine'   => $evenement['copie_de'] === null ? null
                : Database::one('SELECT id, titre FROM evenements WHERE id = ? AND user_id = ?',
                    [(int) $evenement['copie_de'], $userId]),
        ];

        if (Vue::enFenetre()) {
            Vue::fragment('calendrier/voir', $donnees);

            return;
        }

        Vue::afficher('calendrier/voir', $donnees, (string) $evenement['titre']);
    }

    public function formulaire(?int $id = null): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $evenement = null;
        if ($id !== null) {
            $evenement = Database::one('SELECT * FROM evenements WHERE id = ? AND user_id = ?', [$id, $userId]);
            if ($evenement === null) {
                $this->introuvable();
            }
        }

        $dateDefaut = (string) ($_GET['date'] ?? date('Y-m-d'));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDefaut) !== 1) {
            $dateDefaut = date('Y-m-d');
        }

        $donnees = [
            'evenement'  => $evenement,

            'matieres'   => $this->matieres($userId),
            'coursListe' => Database::all('SELECT id, titre FROM cours WHERE user_id = ? ORDER BY titre', [$userId]),
            'dateDefaut' => $dateDefaut,
            'types'      => TypesEvenementController::pourUtilisateur($userId),
            'typeDefaut' => $this->typeValide($userId, $_GET['type'] ?? null),
            'serie'      => $evenement === null || $evenement['serie_id'] === null ? null
                : Database::one('SELECT * FROM series_evenements WHERE id = ? AND user_id = ?',
                    [(int) $evenement['serie_id'], $userId]),
            'ouEnvoyer'  => Agenda::ouEnvoyer($userId),
            'vises'      => $evenement === null
                ? [Agenda::DEFAUT] : Agenda::ciblesDe((int) $evenement['id']),
            'venuDAilleurs' => $evenement !== null && $this->venuDAilleurs($userId, (int) $evenement['id']),
            'copie'      => $evenement === null ? null
                : Database::one('SELECT id, titre FROM evenements
                                  WHERE copie_de = ? AND user_id = ?',
                    [(int) $evenement['id'], $userId]),
        ];

        // Comme la fiche : demandé en fragment, le formulaire part seul et
        // c'est le script qui l'enveloppe. Sans lui, la page entière répond.
        if (Vue::enFenetre()) {
            Vue::fragment('calendrier/formulaire', $donnees);

            return;
        }

        Vue::afficher('calendrier/formulaire', $donnees,
            $evenement === null ? 'Nouvel événement' : 'Modifier l\'événement');
    }

    public function creer(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $donnees = $this->lireFormulaire($userId);
        if (is_string($donnees)) {
            Session::flash('erreur', $donnees);
            redirect('evenements/nouveau');
        }

        $quand = $this->repetitionSoumise($donnees['debut'], $donnees['fin']);
        if (is_string($quand)) {
            Session::flash('erreur', $quand);
            redirect('evenements/nouveau');
        }

        $serieId = null;
        if ($quand !== null) {
            Database::run(
                'INSERT INTO series_evenements
                     (user_id, frequence, jours, jusqu_au, nombre_voulu, occurrences)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$userId, $quand['frequence'], implode(',', $quand['jours']) ?: null,
                 $quand['jusqu_au']->format('Y-m-d'), $quand['nombre'], count($quand['dates'])]
            );
            $serieId = Database::dernierId();
        }

        foreach ($quand === null ? [null] : $quand['dates'] as $decalage) {
            $this->poser($userId, $donnees, $serieId, $decalage);
        }

        $combien = $quand === null ? 1 : count($quand['dates']);
        Session::flash('succes', $combien === 1
            ? 'Événement ajouté au calendrier.'
            : $combien . ' occurrences ajoutées au calendrier, jusqu’au '
              . $quand['jusqu_au']->format('d/m/Y') . '.');

        redirect('calendrier', ['date' => substr($donnees['debut'], 0, 10)]);
    }

    /**
     * Écrit un évènement, éventuellement décalé d'une occurrence.
     *
     * @param ?array{debut: DateTimeImmutable, fin: DateTimeImmutable} $quand
     * @return int  l'identifiant écrit, dont le dépôt a besoin
     */
    private function poser(int $userId, array $donnees, ?int $serieId, ?array $quand): int
    {
        Database::run(
            'INSERT INTO evenements (user_id, matiere_id, cours_id, serie_id, type_id, titre,
                                     description, lieu, debut, fin, journee_entiere)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $donnees['matiere_id'],
                $donnees['cours_id'],
                $serieId,
                $donnees['type_id'],
                $donnees['titre'],
                $donnees['description'],
                $donnees['lieu'],
                $quand === null ? $donnees['debut'] : $quand['debut']->format('Y-m-d H:i:s'),
                $quand === null ? $donnees['fin'] : $quand['fin']->format('Y-m-d H:i:s'),
                $donnees['journee_entiere'],
            ]
        );

        $id = Database::dernierId();
        Agenda::viser($id, $donnees['agendas']);

        return $id;
    }

    /**
     * La répétition demandée, dépliée en dates.
     *
     * @return null|string|array{frequence: string, jusqu_au: DateTimeImmutable, dates: array}
     *         null si l'on ne répète pas, un message si la demande ne tient pas debout
     */
    private function repetitionSoumise(string $debut, string $fin): null|string|array
    {
        $frequence = (string) ($_POST['repetition'] ?? '');
        if ($frequence === '' || $frequence === 'jamais') {
            return null;
        }
        if (!isset(self::RYTHMES[$frequence])) {
            return 'Cette façon de répéter n’existe pas.';
        }

        // La durée vient des dates déjà validées, et non d'une seconde lecture
        // du formulaire : deux lectures finiraient par ne plus dire pareil.
        $premier = new DateTimeImmutable($debut);
        $duree = $premier->diff(new DateTimeImmutable($fin));
        $horizon = $premier->modify(self::SERIE_HORIZON);

        $combien = $this->nombreSoumis();
        if (is_string($combien)) {
            return $combien;
        }

        if ($combien === null) {
            $borne = (string) ($_POST['repeter_jusqu_au'] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $borne) !== 1) {
                return 'Indiquez jusqu’à quelle date l’évènement se répète.';
            }
            $jusqu = (new DateTimeImmutable($borne))->setTime(23, 59, 59);
            if ($jusqu < $premier) {
                return 'La répétition ne peut pas s’arrêter avant de commencer.';
            }
        } else {
            // Compté en occurrences, l'horizon sert de garde-fou et la date de
            // fin sera celle de la dernière, une fois qu'on la connaîtra.
            $jusqu = $horizon;
        }
        if ($jusqu > $horizon) {
            $jusqu = $horizon;
        }

        $jours = $this->joursSoumis($frequence);

        $dates = [];
        foreach ($this->deplier($premier, $frequence, $jusqu, $jours, $combien) as $quand) {
            $dates[] = ['debut' => $quand, 'fin' => $quand->add($duree)];
        }
        if ($dates === []) {
            return 'Aucune date ne correspond : vérifiez les jours cochés.';
        }
        if ($combien !== null) {
            $jusqu = end($dates)['debut']->setTime(23, 59, 59);
        }

        return ['frequence' => $frequence, 'jours' => $jours, 'nombre' => $combien,
                'jusqu_au' => $jusqu, 'dates' => $dates];
    }

    /**
     * Le nombre d'occurrences demandé, ou null si la borne est une date.
     *
     * « Douze séances » se sait d'avance ; la date où elles se terminent, non —
     * il faudrait sauter les mois sans 31 et compter les jours cochés. C'est le
     * travail de l'application, pas celui de qui remplit le formulaire.
     *
     * @return null|int|string  un message si le nombre ne tient pas debout
     */
    private function nombreSoumis(): null|int|string
    {
        if (($_POST['fin_type'] ?? 'date') !== 'nombre') {
            return null;
        }

        $brut = trim((string) ($_POST['repeter_nombre'] ?? ''));
        if (preg_match('/^\d+$/', $brut) !== 1) {
            return 'Indiquez combien de fois l’évènement se répète.';
        }

        $combien = (int) $brut;
        if ($combien < 1) {
            return 'Une répétition compte au moins une occurrence.';
        }

        return min($combien, self::SERIE_MAX);
    }

    /**
     * Les jours de semaine cochés, en numéros ISO croissants.
     *
     * Vide s'il n'y en a pas, ou si le rythme n'en admet pas : la série garde
     * alors le jour de sa date de départ.
     *
     * @return array<int, int>
     */
    private function joursSoumis(string $frequence): array
    {
        if (!isset(self::RYTHMES_A_JOURS[$frequence])) {
            return [];
        }

        $jours = [];
        foreach ((array) ($_POST['jours'] ?? []) as $jour) {
            $jour = (int) $jour;
            if ($jour >= 1 && $jour <= 7) {
                $jours[$jour] = true;
            }
        }
        ksort($jours);

        return array_keys($jours);
    }

    /**
     * Les dates d'une répétition, de la première jusqu'à la borne.
     *
     * @param array<int, int> $jours  jours de semaine ISO, ou vide
     * @return array<int, DateTimeImmutable>
     */
    private function deplier(
        DateTimeImmutable $premier,
        string $frequence,
        DateTimeImmutable $jusqu,
        array $jours = [],
        ?int $combien = null
    ): array {
        $plafond = $combien === null ? self::SERIE_MAX : min($combien, self::SERIE_MAX);

        if ($jours !== [] && isset(self::RYTHMES_A_JOURS[$frequence])) {
            return $this->deplierSurLesJours(
                $premier, self::RYTHMES_A_JOURS[$frequence], $jusqu, $jours, $plafond);
        }

        $dates = [];
        $rang = 0;
        while (count($dates) < $plafond) {
            $quand = $this->occurrence($premier, $frequence, $rang++);
            if ($quand === null) {
                // Un 31 dans un mois qui n'en a pas : on passe, sans décaler
                // le reste de la série sur un autre jour du mois.
                if ($rang > self::SERIE_MAX * 2) {
                    break;
                }
                continue;
            }
            if ($quand > $jusqu) {
                break;
            }
            $dates[] = $quand;
        }

        return $dates;
    }

    /**
     * Les dates d'une répétition qui retombe sur plusieurs jours de semaine.
     *
     * On avance de semaine en semaine — ou de quinzaine en quinzaine — et l'on
     * prend, dans chacune, les jours cochés. Ceux qui précèdent la date de
     * départ sont laissés : une série commencée un mercredi ne remonte pas au
     * lundi de la même semaine.
     *
     * @param int $pas  1 pour chaque semaine, 2 pour une sur deux
     * @param array<int, int> $jours
     * @return array<int, DateTimeImmutable>
     */
    private function deplierSurLesJours(
        DateTimeImmutable $premier,
        int $pas,
        DateTimeImmutable $jusqu,
        array $jours,
        int $plafond
    ): array {
        // Le lundi de la semaine de départ : le repère à partir duquel les
        // semaines se comptent, quel que soit le jour où l'on a commencé.
        $lundi = $premier->modify('monday this week')
            ->setTime((int) $premier->format('H'), (int) $premier->format('i'));

        $dates = [];
        for ($semaine = 0; count($dates) < $plafond; $semaine += $pas) {
            $debutSemaine = $lundi->modify('+' . ($semaine * 7) . ' days');
            if ($debutSemaine > $jusqu) {
                break;
            }
            foreach ($jours as $jour) {
                $quand = $debutSemaine->modify('+' . ($jour - 1) . ' days');
                if ($quand < $premier || $quand > $jusqu) {
                    continue;
                }
                $dates[] = $quand;
                if (count($dates) >= $plafond) {
                    break;
                }
            }
        }

        return $dates;
    }

    /**
     * Le rythme demandé pour une série qui existe déjà.
     *
     * Vide, il ne change rien : on ne redéplie une série que lorsqu'on le
     * demande, et modifier un titre ne doit pas déplacer des dates.
     *
     * @return null|string|array{frequence: string, jusqu_au: DateTimeImmutable}
     */
    private function rythmeSoumis(array $serie): null|string|array
    {
        $frequence = (string) ($_POST['repetition'] ?? '');
        $borne = (string) ($_POST['repeter_jusqu_au'] ?? '');
        if ($frequence === '' && $borne === '') {
            return null;
        }
        if ($frequence === '') {
            $frequence = (string) $serie['frequence'];
        }
        if (!isset(self::RYTHMES[$frequence])) {
            return 'Cette façon de répéter n’existe pas.';
        }

        $combien = $this->nombreSoumis();
        if (is_string($combien)) {
            return $combien;
        }

        if ($combien === null) {
            if ($borne === '') {
                $borne = (string) $serie['jusqu_au'];
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $borne) !== 1) {
                return 'La date de fin de la répétition est illisible.';
            }
            $jusqu = (new DateTimeImmutable($borne))->setTime(23, 59, 59);
        } else {
            // La date de fin sera celle de la dernière occurrence : on la
            // laisse ouverte, le redépliage la fixera.
            $borne = '';
            $jusqu = null;
        }

        $jours = $this->joursSoumis($frequence);
        $inchange = $frequence === (string) $serie['frequence']
            && implode(',', $jours) === (string) ($serie['jours'] ?? '')
            && ($combien === null
                ? $borne === (string) $serie['jusqu_au'] && $serie['nombre_voulu'] === null
                : $combien === (int) ($serie['nombre_voulu'] ?? 0));
        if ($inchange) {
            return null;
        }

        return ['frequence' => $frequence, 'jours' => $jours,
                'nombre' => $combien, 'jusqu_au' => $jusqu];
    }

    /**
     * Redéplie une série sur un nouveau rythme.
     *
     * Les occurrences qui tombent encore sur une date attendue sont laissées
     * telles quelles : une séance qu'on avait déplacée ou renommée à la main
     * survit au changement de rythme. Seules disparaissent celles dont la date
     * n'est plus prévue, et n'apparaissent que celles qui manquaient.
     *
     * L'ancre est la première occurrence de la série, pas celle qu'on avait
     * ouverte : changer le rythme depuis la troisième séance ne doit pas
     * décaler les deux premières.
     *
     * @return array{ajoutees: int, retirees: int}
     */
    private function rebatirLaSerie(int $userId, int $serieId, array $donnees, array $rythme): array
    {
        $premier = Database::valeur(
            'SELECT MIN(debut) FROM evenements WHERE serie_id = ? AND user_id = ?', [$serieId, $userId]);
        if ($premier === null) {
            return ['ajoutees' => 0, 'retirees' => 0];
        }

        $ancre = new DateTimeImmutable(
            substr((string) $premier, 0, 10) . ' ' . substr($donnees['debut'], 11));
        $horizon = $ancre->modify(self::SERIE_HORIZON);
        $jusqu = ($rythme['jusqu_au'] === null || $rythme['jusqu_au'] > $horizon)
            ? $horizon : $rythme['jusqu_au'];

        $voulues = [];
        foreach ($this->deplier($ancre, $rythme['frequence'], $jusqu,
            $rythme['jours'], $rythme['nombre']) as $quand) {
            $voulues[$quand->format('Y-m-d')] = $quand;
        }
        if ($rythme['nombre'] !== null && $voulues !== []) {
            $jusqu = end($voulues)->setTime(23, 59, 59);
        }
        if ($voulues === []) {
            // Aucune date : plutôt que de vider la série, on n'y touche pas.
            return ['ajoutees' => 0, 'retirees' => 0];
        }

        $bilan = ['ajoutees' => 0, 'retirees' => 0];
        $connues = [];
        foreach (Database::all(
            'SELECT id, debut FROM evenements WHERE serie_id = ? AND user_id = ?', [$serieId, $userId]
        ) as $occurrence) {
            $jour = substr((string) $occurrence['debut'], 0, 10);
            if (isset($voulues[$jour])) {
                $connues[$jour] = true;
                continue;
            }
            Database::run('DELETE FROM evenements WHERE id = ? AND user_id = ?',
                [(int) $occurrence['id'], $userId]);
            $bilan['retirees']++;
        }

        $duree = (new DateTimeImmutable($donnees['debut']))->diff(new DateTimeImmutable($donnees['fin']));
        foreach ($voulues as $jour => $quand) {
            if (isset($connues[$jour])) {
                continue;
            }
            $this->poser($userId, $donnees, $serieId, ['debut' => $quand, 'fin' => $quand->add($duree)]);
            $bilan['ajoutees']++;
        }

        Database::run(
            'UPDATE series_evenements
                SET frequence = ?, jours = ?, jusqu_au = ?, nombre_voulu = ?, occurrences = ?
              WHERE id = ? AND user_id = ?',
            [$rythme['frequence'], implode(',', $rythme['jours']) ?: null,
             $jusqu->format('Y-m-d'), $rythme['nombre'], count($voulues), $serieId, $userId]
        );

        return $bilan;
    }


    /**
     * La n-ième occurrence, ou null si elle n'existe pas ce mois-là.
     *
     * Un rendez-vous mensuel posé un 31 n'a pas lieu en février : il est sauté,
     * plutôt que déplacé au 3 mars comme le ferait « +1 month ».
     */
    private function occurrence(DateTimeImmutable $premier, string $frequence, int $rang): ?DateTimeImmutable
    {
        if ($rang === 0) {
            return $premier;
        }
        $jours = self::RYTHMES[$frequence];
        if ($jours > 0) {
            return $premier->modify('+' . ($jours * $rang) . ' days');
        }

        $vise = $premier->modify('+' . $rang . ' months');

        return $vise->format('d') === $premier->format('d') ? $vise : null;
    }

    public function modifier(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $avant = Database::one('SELECT id, serie_id FROM evenements WHERE id = ? AND user_id = ?',
            [$id, $userId]);
        if ($avant === null) {
            $this->introuvable();
        }

        $donnees = $this->lireFormulaire($userId);
        if (is_string($donnees)) {
            Session::flash('erreur', $donnees);
            redirect('evenements/' . $id . '/modifier');
        }

        $serieId = entier_ou_null($avant['serie_id']);
        if (($_POST['portee'] ?? '') === 'serie' && $serieId !== null) {
            $serie = Database::one('SELECT * FROM series_evenements WHERE id = ? AND user_id = ?',
                [$serieId, $userId]);
            $rythme = $serie === null ? null : $this->rythmeSoumis($serie);
            if (is_string($rythme)) {
                Session::flash('erreur', $rythme);
                redirect('evenements/' . $id . '/modifier');
            }

            // Le rythme d'abord : les occurrences qu'il ajoute doivent recevoir
            // les mêmes valeurs que les autres, pas celles d'avant.
            $refait = $rythme === null
                ? ['ajoutees' => 0, 'retirees' => 0]
                : $this->rebatirLaSerie($userId, $serieId, $donnees, $rythme);

            $combien = $this->modifierLaSerie($userId, $serieId, $donnees);

            $dit = $combien . ' occurrence' . ($combien > 1 ? 's mises à jour' : ' mise à jour');
            if ($refait['ajoutees'] > 0) { $dit .= ', ' . $refait['ajoutees'] . ' ajoutée' . ($refait['ajoutees'] > 1 ? 's' : ''); }
            if ($refait['retirees'] > 0) { $dit .= ', ' . $refait['retirees'] . ' retirée' . ($refait['retirees'] > 1 ? 's' : ''); }

            Session::flash('succes', $dit . '.');
            redirect('calendrier', ['date' => substr($donnees['debut'], 0, 10)]);
        }

        /*
         * Un évènement venu de l'agenda de quelqu'un d'autre ne peut pas être
         * envoyé ailleurs : il ne nous appartient pas. Cocher un de ses
         * agendas en fait donc une copie, qui est à nous — l'original ne
         * change ni de contenu, ni de couleur, ni de camp.
         */
        if ($this->venuDAilleurs($userId, $id)) {
            $this->copier($userId, $id, $donnees);
        } else {
            Agenda::viser($id, $donnees['agendas']);
        }

        Database::run(
            'UPDATE evenements
             SET matiere_id = ?, cours_id = ?, type_id = ?, titre = ?, description = ?, lieu = ?,
                 debut = ?, fin = ?, journee_entiere = ?
             WHERE id = ? AND user_id = ?',
            [
                $donnees['matiere_id'],
                $donnees['cours_id'],
                $donnees['type_id'],
                $donnees['titre'],
                $donnees['description'],
                $donnees['lieu'],
                $donnees['debut'],
                $donnees['fin'],
                $donnees['journee_entiere'],
                $id,
                $userId,
            ]
        );

        /*
         * Un évènement venu d'un agenda distant y retourne modifié, si le
         * calendrier est à nous. Fait ici plutôt qu'à la synchronisation
         * suivante : celle-ci lit avant d'écrire, et rétablirait la version de
         * là-bas avant d'avoir vu la nôtre.
         */
        $souci = null;
        foreach (Agenda::relies($userId) as $agenda) {
            try {
                $souci ??= SynchroAgenda::pour($agenda)->porterLaModification($userId, $id);
            } catch (Throwable $e) {
                $souci ??= $e->getMessage();
            }
        }

        if ($souci !== null) {
            Session::flash('erreur', 'La modification est enregistrée ici, mais n’a pas pu être '
                . 'portée dans l’agenda : ' . $souci
                . ' Elle sera défaite à la prochaine lecture.');
        }

        Session::flash('succes', 'Événement mis à jour.');
        redirect('calendrier', ['date' => substr($donnees['debut'], 0, 10)]);
    }

    /**
     * Applique la modification à toutes les occurrences d'une série.
     *
     * Chacune garde sa date : c'est ce qui fait d'elles une série, et la leur
     * imposer les entasserait toutes le même jour. Ce qui se propage, c'est le
     * reste — titre, lieu, notes, matière, type, cours — plus l'heure de la
     * journée et la durée, prises sur l'occurrence qu'on avait sous les yeux.
     *
     * @return int  combien ont été mises à jour
     */
    private function modifierLaSerie(int $userId, int $serieId, array $donnees): int
    {
        $debut = new DateTimeImmutable($donnees['debut']);
        $finie = new DateTimeImmutable($donnees['fin']);
        $duree = $debut->diff($finie);
        $journee = (int) $donnees['journee_entiere'] === 1;
        // Une journée entière ne se mesure pas en heures mais en jours
        // couverts : de minuit au dernier soir.
        $jours = (int) $debut->setTime(0, 0)->diff($finie->setTime(0, 0))->days;

        $occurrences = Database::all(
            'SELECT id, debut FROM evenements WHERE serie_id = ? AND user_id = ? ORDER BY debut',
            [$serieId, $userId]
        );

        foreach ($occurrences as $occurrence) {
            $jour = new DateTimeImmutable(substr((string) $occurrence['debut'], 0, 10));

            if ($journee) {
                $neuf = $jour->setTime(0, 0);
                $fin = $jour->modify('+' . $jours . ' days')->setTime(23, 59, 59);
            } else {
                $neuf = $jour->setTime((int) $debut->format('H'), (int) $debut->format('i'));
                $fin = $neuf->add($duree);
            }

            Database::run(
                'UPDATE evenements
                    SET matiere_id = ?, cours_id = ?, type_id = ?, titre = ?, description = ?,
                        lieu = ?, debut = ?, fin = ?, journee_entiere = ?
                  WHERE id = ? AND user_id = ?',
                [
                    $donnees['matiere_id'], $donnees['cours_id'], $donnees['type_id'],
                    $donnees['titre'], $donnees['description'], $donnees['lieu'],
                    $neuf->format('Y-m-d H:i:s'), $fin->format('Y-m-d H:i:s'),
                    $donnees['journee_entiere'],
                    (int) $occurrence['id'], $userId,
                ]
            );

            Agenda::viser((int) $occurrence['id'], $donnees['agendas']);
        }

        return count($occurrences);
    }

    /**
     * Cet évènement vient-il de l'agenda de quelqu'un d'autre ?
     *
     * Le lien de lecture le dit : un évènement écrit ici n'en a pas.
     */
    private function venuDAilleurs(int $userId, int $evenementId): bool
    {
        return Database::valeur(
            'SELECT id FROM agenda_liens WHERE user_id = ? AND evenement_id = ?',
            [$userId, $evenementId]
        ) !== null;
    }

    /**
     * Fait de cet évènement-là un évènement à soi.
     *
     * Une copie, pas un déplacement : l'original reste ce qu'il est, dans
     * l'agenda de qui l'a écrit, avec sa couleur. La copie est un évènement
     * ordinaire de l'application — elle prend la couleur de « Mes évènements »,
     * se modifie, se supprime, et part dans les agendas qu'on lui a cochés.
     *
     * On ne la fait qu'une fois. Rouvrir l'original ne doit pas se solder par
     * un second exemplaire à chaque enregistrement ; l'écran le dit et renvoie
     * vers celle qui existe.
     */
    private function copier(int $userId, int $origine, array $donnees): void
    {
        if ($donnees['agendas'] === [Agenda::DEFAUT] && !$this->voulaitCopier()) {
            return;
        }

        $deja = Database::valeur('SELECT id FROM evenements WHERE copie_de = ? AND user_id = ?',
            [$origine, $userId]);
        if ($deja !== null) {
            return;
        }

        Database::run(
            'INSERT INTO evenements (user_id, matiere_id, cours_id, copie_de, type_id, titre,
                                     description, lieu, debut, fin, journee_entiere)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $donnees['matiere_id'],
                $donnees['cours_id'],
                $origine,
                $donnees['type_id'],
                $donnees['titre'],
                $donnees['description'],
                $donnees['lieu'],
                $donnees['debut'],
                $donnees['fin'],
                $donnees['journee_entiere'],
            ]
        );

        $copie = Database::dernierId();
        Agenda::viser($copie, $donnees['agendas']);

        Session::flash('succes', 'Une copie de cet évènement est désormais la vôtre. '
            . 'L’original reste celui de son agenda : ce que vous en ferez ici ne le touchera pas.');
    }

    /**
     * A-t-on demandé la copie, ou seulement laissé les cases comme elles étaient ?
     *
     * Sur un évènement venu d'ailleurs, la case « Mes évènements » n'est pas
     * cochée d'avance : rien n'est encore à nous. La cocher est donc une
     * demande, au même titre que cocher un agenda — et il faut savoir la
     * distinguer du cas où l'on n'a rien coché du tout, que « ciblesValides »
     * ramène pareillement à « Mes évènements ».
     */
    private function voulaitCopier(): bool
    {
        $coches = $_POST['agendas'] ?? [];

        return is_array($coches) && $coches !== [];
    }

    /**
     * Supprime un évènement, ou toute la série dont il fait partie.
     *
     * Une série de cent occurrences créée par erreur se défait mal une par
     * une : c'est pour cela que le bouton existe. Il faut le demander
     * explicitement — sans quoi supprimer un cours annulé effacerait l'année.
     */
    public function supprimer(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $serieId = ($_POST['serie'] ?? '') === '1'
            ? entier_ou_null(Database::valeur(
                'SELECT serie_id FROM evenements WHERE id = ? AND user_id = ?', [$id, $userId]))
            : null;

        if ($serieId === null) {
            Database::run('DELETE FROM evenements WHERE id = ? AND user_id = ?', [$id, $userId]);
            Session::flash('succes', 'Événement supprimé.');
            redirect('calendrier');
        }

        $combien = (int) Database::valeur(
            'SELECT COUNT(*) FROM evenements WHERE serie_id = ? AND user_id = ?', [$serieId, $userId]);
        Database::run('DELETE FROM evenements WHERE serie_id = ? AND user_id = ?', [$serieId, $userId]);
        Database::run('DELETE FROM series_evenements WHERE id = ? AND user_id = ?', [$serieId, $userId]);

        Session::flash('succes', $combien . ' occurrence' . ($combien > 1 ? 's supprimées' : ' supprimée') . '.');
        redirect('calendrier');
    }

    public function basculerTermine(int $id): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        Database::run(
            'UPDATE evenements SET termine = 1 - termine WHERE id = ? AND user_id = ?',
            [$id, Auth::id()]
        );
        /*
         * On revient d'où l'on vient. Une chaîne vide n'est pas une absence :
         * c'est l'accueil, qui est la racine de l'application — sans quoi
         * cocher un évènement depuis l'accueil renverrait au calendrier.
         * Seul un chemin interne est accepté.
         */
        $retour = $_POST['retour'] ?? null;
        $interne = is_string($retour)
            && !str_contains($retour, '//')
            && preg_match('#[\r\n:]#', $retour) !== 1;

        redirect($interne ? ltrim($retour, '/') : 'calendrier');
    }

    // --- Outils internes ------------------------------------------------

    /** Événements d'un utilisateur qui chevauchent une période. */
    public static function evenementsEntre(
        int $userId,
        DateTimeInterface $debut,
        DateTimeInterface $fin,
        ?int $matiereId = null,
        ?int $typeId = null
    ): array {
        /*
         * L'agenda d'origine sert à masquer : un évènement sans lien vient
         * d'ici, les autres du calendrier Outlook nommé par « ol.calendrier ».
         */
        $sql = 'SELECT e.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur, c.titre AS cours_titre,
                       t.nom AS type_nom, t.icone AS type_icone, t.couleur AS type_couleur, t.est_echeance,
                       ol.calendrier AS outlook_calendrier, oc.nom AS agenda_nom, oc.couleur AS agenda_couleur
                FROM evenements e
                LEFT JOIN matieres m        ON m.id = e.matiere_id
                LEFT JOIN cours c           ON c.id = e.cours_id
                LEFT JOIN types_evenement t ON t.id = e.type_id
                LEFT JOIN agenda_liens ol  ON ol.evenement_id = e.id AND ol.user_id = e.user_id
                LEFT JOIN agenda_calendriers oc
                       ON oc.user_id = e.user_id AND oc.empreinte = ol.calendrier
                WHERE e.user_id = ? AND e.debut <= ? AND e.fin >= ?';
        $params = [$userId, $fin->format('Y-m-d H:i:s'), $debut->format('Y-m-d H:i:s')];

        $sql .= self::masqueDesAgendas($userId, $params);

        if ($matiereId !== null) {
            $sql .= ' AND e.matiere_id = ?';
            $params[] = $matiereId;
        }
        if ($typeId !== null) {
            $sql .= ' AND e.type_id = ?';
            $params[] = $typeId;
        }
        $sql .= ' ORDER BY e.debut ASC, e.fin ASC';

        return Database::all($sql, $params);
    }

    /**
     * La condition qui écarte les agendas décochés dans le volet.
     *
     * Elle vaut partout où l'on montre des évènements, y compris dans
     * « Prochainement » : décocher un agenda et le retrouver plus bas ferait
     * douter de la case autant que de la liste.
     *
     * La requête doit avoir joint « agenda_liens » sous l'alias « ol ».
     *
     * @param array $params  complété des valeurs à lier
     */
    private static function masqueDesAgendas(int $userId, array &$params): string
    {
        $masques = Agenda::masques($userId);
        $sql = '';

        if ($masques['miens']) {
            $sql .= ' AND ol.id IS NOT NULL';
        }
        if ($masques['calendriers'] !== []) {
            // Une origine inconnue reste montrée : mieux vaut un évènement de
            // trop qu'un rendez-vous escamoté sans qu'on sache pourquoi.
            $trous = implode(', ', array_fill(0, count($masques['calendriers']), '?'));
            $sql .= ' AND (ol.calendrier IS NULL OR ol.calendrier NOT IN (' . $trous . '))';
            $params = array_merge($params, $masques['calendriers']);
        }

        return $sql;
    }

    private function evenements(
        int $userId,
        DateTimeInterface $debut,
        DateTimeInterface $fin,
        ?int $matiereId,
        ?int $typeId
    ): array {
        return self::evenementsEntre($userId, $debut, $fin, $matiereId, $typeId);
    }

    /** Répartit les événements sur chaque jour qu'ils couvrent (clé : Y-m-d). */
    /**
     * Les échéances des tâches, présentées comme des évènements d'une journée.
     * Rien n'est écrit : ces lignes n'existent que le temps de l'affichage.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function echeancesEntre(int $userId, DateTimeInterface $debut, DateTimeInterface $fin): array
    {
        $bornes = [$userId, $debut->format('Y-m-d'), $fin->format('Y-m-d')];
        $lignes = [];

        foreach (Database::all(
            'SELECT t.id, t.titre, t.echeance, t.faite, t.liste_id,
                    l.nom AS liste_nom, l.couleur AS liste_couleur, l.icone AS liste_icone
             FROM taches t
             JOIN listes_taches l ON l.id = t.liste_id
             WHERE t.user_id = ? AND t.echeance BETWEEN ? AND ?',
            $bornes
        ) as $tache) {
            $lignes[] = self::pseudoEvenement(
                (int) $tache['id'],
                (string) $tache['titre'],
                (string) $tache['echeance'],
                (int) $tache['faite'] === 1,
                (int) $tache['liste_id'],
                'Sous-tâche',
                '☑️',
                'taches/' . (int) $tache['id'] . '/cocher',
                (string) $tache['liste_couleur'],
                (string) $tache['liste_icone'] . ' ' . (string) $tache['liste_nom']
            );
        }

        foreach (Database::all(
            'SELECT l.id, l.nom, l.echeance, l.couleur, l.icone,
                    (SELECT COUNT(*) FROM taches t WHERE t.liste_id = l.id)                  AS total,
                    (SELECT COUNT(*) FROM taches t WHERE t.liste_id = l.id AND t.faite = 0)  AS reste
             FROM listes_taches l
             WHERE l.user_id = ? AND l.echeance BETWEEN ? AND ?',
            $bornes
        ) as $liste) {
            $lignes[] = self::pseudoEvenement(
                (int) $liste['id'],
                (string) $liste['nom'],
                (string) $liste['echeance'],
                (int) $liste['total'] > 0 && (int) $liste['reste'] === 0,
                (int) $liste['id'],
                'Tâche principale',
                (string) $liste['icone'],
                'taches/listes/' . (int) $liste['id'] . '/cocher',
                (string) $liste['couleur'],
                (int) $liste['total'] === 0
                    ? 'aucune sous-tâche'
                    : (int) $liste['reste'] . ' sur ' . (int) $liste['total'] . ' à faire'
            );
        }

        return $lignes;
    }

    /** Une échéance mise à la forme d'un évènement, pour que les vues la rendent. */
    private static function pseudoEvenement(
        int $id,
        string $titre,
        string $jour,
        bool $termine,
        int $listeId,
        string $typeNom,
        string $icone,
        string $routeCocher,
        string $couleur,
        string $detail
    ): array {
        return [
            'id'              => $id,
            'titre'           => $titre,
            'debut'           => $jour . ' 00:00:00',
            'fin'             => $jour . ' 23:59:59',
            'journee_entiere' => 1,
            'termine'         => $termine ? 1 : 0,
            'type_nom'        => $typeNom,
            'type_icone'      => $icone !== '' ? $icone : '📋',
            'type_couleur'    => $couleur,
            'matiere_nom'     => null,
            'matiere_couleur' => null,
            'lieu'            => null,
            'cours_id'        => null,
            'cours_titre'     => null,
            'description'     => null,
            // Ce qui distingue une échéance d'un vrai évènement.
            'est_tache'       => true,
            'liste_id'        => $listeId,
            'detail_tache'    => $detail,
            'route_cocher'    => $routeCocher,
        ];
    }

    private function grouperParJour(array $evenements): array
    {
        $parJour = [];
        foreach ($evenements as $evenement) {
            $curseur = new DateTimeImmutable(substr($evenement['debut'], 0, 10));
            $dernier = new DateTimeImmutable(substr($evenement['fin'], 0, 10));
            // Garde-fou : un événement ne s'etale pas sur plus de 60 jours d'affichage.
            for ($i = 0; $i < 60 && $curseur <= $dernier; $i++) {
                $parJour[$curseur->format('Y-m-d')][] = $evenement;
                $curseur = $curseur->modify('+1 day');
            }
        }
        return $parJour;
    }

    private function aVenir(int $userId, int $limite): array
    {
        $params = [$userId];
        $lignes = Database::all(
            'SELECT e.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                    t.nom AS type_nom, t.icone AS type_icone, t.couleur AS type_couleur,
                    oc.nom AS agenda_nom, oc.couleur AS agenda_couleur
             FROM evenements e
             LEFT JOIN matieres m        ON m.id = e.matiere_id
             LEFT JOIN types_evenement t ON t.id = e.type_id
             LEFT JOIN agenda_liens ol  ON ol.evenement_id = e.id AND ol.user_id = e.user_id
             LEFT JOIN agenda_calendriers oc
                    ON oc.user_id = e.user_id AND oc.empreinte = ol.calendrier
             WHERE e.user_id = ? AND e.fin >= NOW() AND e.termine = 0'
             . self::masqueDesAgendas($userId, $params)
             . ' ORDER BY e.debut ASC LIMIT ' . $limite,
            $params
        );

        // Les échéances encore ouvertes s'y ajoutent, sur un horizon large —
        // et suivent le sort de « Mes évènements », comme dans la grille.
        $horizon = (new DateTimeImmutable('today'))->modify('+1 year');
        $echeances = Agenda::masques($userId)['miens']
            ? []
            : self::echeancesEntre($userId, new DateTimeImmutable('today'), $horizon);
        foreach ($echeances as $echeance) {
            if ((int) $echeance['termine'] === 0) {
                $lignes[] = $echeance;
            }
        }

        usort($lignes, static fn (array $a, array $b): int => $a['debut'] <=> $b['debut']);

        return array_slice($lignes, 0, $limite);
    }

    private function dateAncre(): DateTimeImmutable
    {
        $date = (string) ($_GET['date'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return new DateTimeImmutable($date);
        }
        $mois = entier_ou_null($_GET['m'] ?? null);
        $annee = entier_ou_null($_GET['a'] ?? null);
        if ($mois !== null && $annee !== null && $mois >= 1 && $mois <= 12 && $annee >= 1970 && $annee <= 2100) {
            return new DateTimeImmutable(sprintf('%04d-%02d-01', $annee, $mois));
        }
        return new DateTimeImmutable('today');
    }

    /** Premier lundi affiché et dernier dimanche affiché pour une grille mensuelle. */
    private function bornesMois(DateTimeImmutable $ancre): array
    {
        $premier = $ancre->modify('first day of this month')->setTime(0, 0);
        $dernier = $ancre->modify('last day of this month')->setTime(23, 59, 59);
        $debut = $premier->modify('-' . ((int) $premier->format('N') - 1) . ' days');
        $fin = $dernier->modify('+' . (7 - (int) $dernier->format('N')) . ' days')->setTime(23, 59, 59);
        return [$debut, $fin];
    }

    private function bornesSemaine(DateTimeImmutable $ancre): array
    {
        $lundi = $ancre->modify('-' . ((int) $ancre->format('N') - 1) . ' days')->setTime(0, 0);
        return [$lundi, $lundi->modify('+6 days')->setTime(23, 59, 59)];
    }

    private function matieres(int $userId): array
    {
        return Database::all('SELECT * FROM matieres WHERE user_id = ? ORDER BY nom', [$userId]);
    }

    /** Valide le formulaire ; renvoie un tableau de données ou un message d'erreur. */
    private function lireFormulaire(int $userId): array|string
    {
        $titre = post('titre');
        if ($titre === '') {
            return 'Le titre de l\'événement est obligatoire.';
        }

        $typeId = $this->typeValide($userId, $_POST['type_id'] ?? null);

        $journeeEntiere = isset($_POST['journee_entiere']) ? 1 : 0;
        $dateDebut = post('date_debut');
        $dateFin = post('date_fin', $dateDebut);
        if ($dateFin === '') {
            $dateFin = $dateDebut;
        }

        if ($journeeEntiere === 1) {
            $debut = $dateDebut . ' 00:00:00';
            $fin = $dateFin . ' 23:59:59';
        } else {
            $debut = $dateDebut . ' ' . (post('heure_debut', '08:00') ?: '08:00') . ':00';
            $fin = $dateFin . ' ' . (post('heure_fin', '09:00') ?: '09:00') . ':00';
        }

        $tsDebut = strtotime($debut);
        $tsFin = strtotime($fin);
        if ($tsDebut === false || $tsFin === false) {
            return 'Dates ou heures invalides.';
        }
        if ($tsFin < $tsDebut) {
            return 'La fin ne peut pas être antérieure au début.';
        }

        $coursId = entier_ou_null($_POST['cours_id'] ?? null);
        if ($coursId !== null
            && Database::valeur('SELECT id FROM cours WHERE id = ? AND user_id = ?', [$coursId, $userId]) === null) {
            $coursId = null;
        }
        $matiereId = entier_ou_null($_POST['matiere_id'] ?? null);
        if ($matiereId !== null
            && Database::valeur('SELECT id FROM matieres WHERE id = ? AND user_id = ?', [$matiereId, $userId]) === null) {
            $matiereId = null;
        }

        /*
         * Les agendas cochés passent par « ciblesValides » : une empreinte
         * soumise à la main, ou celle d'un calendrier délié depuis, ne doit
         * pas suffire à faire écrire l'application quelque part. Rien de
         * valable ? Alors « Mes évènements » — le comportement d'avant.
         */
        $coches = $_POST['agendas'] ?? [];

        return [
            'agendas'         => Agenda::ciblesValides($userId, is_array($coches) ? $coches : []),
            'matiere_id'      => $matiereId,
            'cours_id'        => $coursId,
            'type_id'         => $typeId,
            'titre'           => mb_substr($titre, 0, 200),
            'description'     => post('description') ?: null,
            'lieu'            => mb_substr(post('lieu'), 0, 160) ?: null,
            'debut'           => date('Y-m-d H:i:s', $tsDebut),
            'fin'             => date('Y-m-d H:i:s', $tsFin),
            'journee_entiere' => $journeeEntiere,
        ];
    }

    /** Renvoie l'identifiant du type s'il appartient bien à l'utilisateur, sinon null. */
    private function typeValide(int $userId, mixed $valeur): ?int
    {
        $id = entier_ou_null($valeur);
        if ($id === null) {
            return null;
        }
        $existe = Database::valeur(
            'SELECT id FROM types_evenement WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
        return $existe === null ? null : $id;
    }
    private function introuvable(): never
    {
        http_response_code(404);
        Vue::afficher('erreurs/404', [], 'Introuvable');
        exit;
    }
}
