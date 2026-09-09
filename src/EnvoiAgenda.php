<?php
declare(strict_types=1);

/**
 * Porter vers Outlook ce qui est né dans l'application.
 *
 * L'autre sens de la synchronisation. Il écrit, ce que le premier ne faisait
 * pas, et cela change tout : une lecture ratée ne coûte qu'une lecture, une
 * écriture ratée abîme l'agenda de quelqu'un.
 *
 * D'où le calendrier séparé par défaut. L'application crée le sien chez le
 * fournisseur — « Mes Cours » —, on l'affiche ou on le masque d'une case dans
 * Outlook, le supprimer n'emporte que ce qui vient d'ici, et une erreur de
 * notre part ne peut pas atteindre l'agenda dont on se sert.
 *
 * Un évènement peut cependant désigner d'autres agendas, parmi ceux qu'on a le
 * droit de modifier, et plusieurs à la fois. Ce qui est parti se compte donc
 * par évènement et par calendrier : le même rendez-vous a une copie dans
 * chacun, chacune avec son identifiant et son empreinte. Décocher un agenda
 * n'est alors qu'une copie de moins à retrouver, et le rapprochement l'efface
 * comme il efface un évènement supprimé.
 *
 * Ce qui monte : les évènements créés dans l'application, et les échéances des
 * tâches, en journée entière. Ce qui est venu d'Outlook n'y retourne pas —
 * sans quoi chaque passage rendrait les évènements à leur expéditeur, et
 * l'agenda enflerait tout seul.
 */
final class EnvoiAgenda
{
    /** @var array<string, self> une instance par agenda, pas davantage */
    private static array $instances = [];

    private function __construct(private readonly Fournisseur $f)
    {
    }

    public static function pour(Fournisseur $f): self
    {
        return self::$instances[$f->cle()] ??= new self($f);
    }

    /** L'agenda distant auquel cette synchronisation s'adresse. */
    private function lien(): LiaisonAgenda
    {
        return LiaisonAgenda::pour($this->f);
    }

    /** Le nom du calendrier créé chez le fournisseur. */
    private const CALENDRIER = 'Mes Cours';

    /** La fenêtre envoyée, la même que celle qu'on lit. */
    private const AVANT = '-1 month';
    private const APRES = '+12 months';

    /** Le fuseau de la personne, réglé au démarrage. */
    private function fuseau(): string
    {
        return date_default_timezone_get();
    }

    /** Une échéance de tâche se reconnaît d'un coup d'œil dans l'agenda. */
    private const MARQUE_TACHE = '☑ ';

    /**
     * Envoie ce qui doit l'être, corrige ce qui a changé, retire ce qui a disparu.
     *
     * @return array{crees: int, majs: int, retires: int, inchanges: int}
     * @throws RuntimeException si le compte n'est pas relié, ou si Microsoft refuse
     */
    public function pousser(int $userId): array
    {
        $calendrier = $this->calendrierDEnvoi($userId);

        [$depuis, $jusqua] = $this->fenetre();

        $aEnvoyer = $this->aEnvoyer($userId, $depuis, $jusqua, $calendrier);
        $partis = $this->partis($userId);

        $bilan = ['crees' => 0, 'majs' => 0, 'retires' => 0, 'inchanges' => 0];

        foreach ($aEnvoyer as $cle => $quoi) {
            $empreinte = md5(json_encode($quoi['corps'], JSON_THROW_ON_ERROR));
            $connu = $partis[$cle] ?? null;
            $ou = (string) $quoi['calendrier'];

            if ($connu === null) {
                $this->creer($userId, $ou, $quoi, $empreinte);
                $bilan['crees']++;
                continue;
            }
            if ((string) $connu['empreinte'] === $empreinte) {
                $bilan['inchanges']++;
                continue;
            }

            $this->modifier($userId, $quoi, $empreinte, (string) $connu['distant_id'], $ou);
            $bilan['majs']++;
        }

        foreach ($partis as $cle => $connu) {
            if (isset($aEnvoyer[$cle])) {
                continue;
            }
            $this->effacer($userId, (string) $connu['distant_id'],
                (string) ($connu['calendrier_id'] ?? $calendrier));
            Database::run('DELETE FROM agenda_envois WHERE user_id = ? AND fournisseur = ? AND id = ?',
                [$userId, $this->f->cle(), (int) $connu['id']]);
            $bilan['retires']++;
        }

        Database::run(
            'UPDATE agenda_comptes SET envoi_le = NOW(), empreinte_envoi = ?
              WHERE user_id = ? AND fournisseur = ?',
            [$this->signature($userId), $userId, $this->f->cle()]
        );

        return $bilan;
    }

    /** La période envoyée : la même partout, sans quoi rien ne concorderait. */
    private function fenetre(): array
    {
        $maintenant = new DateTimeImmutable('now', new DateTimeZone($this->fuseau()));

        return [
            $maintenant->modify(self::AVANT)->setTime(0, 0),
            $maintenant->modify(self::APRES)->setTime(23, 59, 59),
        ];
    }

    /**
     * Y a-t-il quelque chose de neuf à porter là-bas ?
     *
     * Un drapeau posé à chaque création d'évènement ou de tâche aurait obligé
     * à toucher tous les contrôleurs qui les écrivent, et à n'en oublier aucun
     * — ni aujourd'hui, ni dans six mois. On constate l'écart plutôt que de
     * le faire déclarer : une empreinte de ce qui devrait être là-bas,
     * comparée à celle du dernier envoi.
     *
     * Deux agrégats sur des index existants : c'est assez léger pour être
     * demandé à chaque page.
     */
    public function aPousser(int $userId): bool
    {
        if (!$this->lien()->configure() || !$this->lien()->relie($userId)) {
            return false;
        }

        $connue = Database::valeur(
            'SELECT empreinte_envoi FROM agenda_comptes WHERE user_id = ? AND fournisseur = ?', [$userId, $this->f->cle()]);

        return (string) $connue !== $this->signature($userId);
    }

    /**
     * Une empreinte de tout ce qui devrait se trouver dans Outlook.
     *
     * Le compte et la somme suffisent : ce n'est qu'un signal — au pire on
     * envoie pour rien, ou l'on attend cinq minutes de plus. Le vrai travail
     * de comparaison reste celui de « pousser », ligne par ligne.
     */
    private function signature(int $userId): string
    {
        [$depuis, $jusqua] = $this->fenetre();

        $evts = Database::one(
            'SELECT COUNT(*) AS n, COALESCE(SUM(CRC32(CONCAT_WS("|",
                        e.id, e.titre, COALESCE(e.description, ""), COALESCE(e.lieu, ""),
                        e.debut, e.fin, e.journee_entiere))), 0) AS s
               FROM evenements e
               /*
                * Aucun fournisseur ici, volontairement : un évènement venu de
                * n’importe quel agenda n’est pas né dans l’application et n’a
                * donc à repartir vers aucun autre.
                */
               LEFT JOIN agenda_liens l ON l.evenement_id = e.id AND l.user_id = e.user_id
              WHERE e.user_id = ? AND l.id IS NULL AND e.debut BETWEEN ? AND ?',
            [$userId, $depuis->format('Y-m-d H:i:s'), $jusqua->format('Y-m-d H:i:s')]
        );

        $taches = Database::one(
            'SELECT COUNT(*) AS n, COALESCE(SUM(CRC32(CONCAT_WS("|",
                        t.id, t.titre, COALESCE(t.note, ""), t.echeance, l.nom))), 0) AS s
               FROM taches t
               JOIN listes_taches l ON l.id = t.liste_id
              WHERE t.user_id = ? AND t.faite = 0 AND t.echeance BETWEEN ? AND ?',
            [$userId, $depuis->format('Y-m-d'), $jusqua->format('Y-m-d')]
        );

        /*
         * Les agendas visés comptent autant que le contenu : cocher une case
         * de plus ne change pas un seul évènement, et pourtant il faut repartir
         * en écrire une copie de plus.
         */
        $vises = Database::one(
            'SELECT COUNT(*) AS n, COALESCE(SUM(CRC32(CONCAT_WS("|", a.evenement_id, a.empreinte))), 0) AS s
               FROM evenement_agendas a
               JOIN evenements e ON e.id = a.evenement_id
              WHERE e.user_id = ? AND e.debut BETWEEN ? AND ?',
            [$userId, $depuis->format('Y-m-d H:i:s'), $jusqua->format('Y-m-d H:i:s')]
        );

        return implode(':', [
            (int) ($evts['n'] ?? 0), (int) ($evts['s'] ?? 0),
            (int) ($taches['n'] ?? 0), (int) ($taches['s'] ?? 0),
            (int) ($vises['n'] ?? 0), (int) ($vises['s'] ?? 0),
        ]);
    }

    /** La date du dernier envoi, ou null s'il n'y en a jamais eu. */
    public function derniereFois(int $userId): ?string
    {
        $quand = Database::valeur('SELECT envoi_le FROM agenda_comptes WHERE user_id = ? AND fournisseur = ?', [$userId, $this->f->cle()]);

        return $quand === null ? null : (string) $quand;
    }

    /** Combien d'éléments de l'application vivent dans Outlook. */
    public function combien(int $userId): int
    {
        return (int) Database::valeur('SELECT COUNT(*) FROM agenda_envois WHERE user_id = ? AND fournisseur = ?', [$userId, $this->f->cle()]);
    }

    /** L'identifiant du calendrier où l'application écrit, s'il existe déjà. */
    public function calendrierConnu(int $userId): ?string
    {
        $id = Database::valeur(
            'SELECT calendrier_envoi_id FROM agenda_comptes WHERE user_id = ? AND fournisseur = ?', [$userId, $this->f->cle()]);

        return ($id === null || (string) $id === '') ? null : (string) $id;
    }

    /**
     * Retire d'Outlook tout ce que l'application y avait mis.
     *
     * Le calendrier lui-même reste : le supprimer relève de l'utilisateur, pas
     * de nous — il a pu y ajouter autre chose à la main.
     *
     * @return int  combien ont été retirés
     */
    public function toutRetirer(int $userId): int
    {
        $retires = 0;
        foreach (Database::all('SELECT id, distant_id, calendrier_id FROM agenda_envois
                                  WHERE user_id = ? AND fournisseur = ?',
            [$userId, $this->f->cle()]) as $ligne) {
            $this->effacer($userId, (string) $ligne['distant_id'],
                (string) ($ligne['calendrier_id'] ?? 'primary'));
            Database::run('DELETE FROM agenda_envois WHERE user_id = ? AND fournisseur = ? AND id = ?',
                [$userId, $this->f->cle(), (int) $ligne['id']]);
            $retires++;
        }

        Database::run('UPDATE agenda_comptes SET envoi_le = NULL, empreinte_envoi = NULL
                       WHERE user_id = ? AND fournisseur = ?', [$userId, $this->f->cle()]);

        return $retires;
    }

    /* --- Le calendrier de destination ------------------------------------- */

    /**
     * Le calendrier « Mes Cours », créé au besoin.
     *
     * On ne le recrée pas s'il porte déjà ce nom chez le fournisseur : quelqu'un
     * qui délie puis relie son compte retrouverait sinon deux calendriers
     * identiques, et ne saurait pas lequel jeter.
     *
     * @throws RuntimeException si Microsoft refuse de le donner ou de le créer
     */
    private function calendrierDEnvoi(int $userId): string
    {
        $connu = $this->calendrierConnu($userId);
        if ($connu !== null) {
            return $connu;
        }

        $liste = $this->lien()->appeler($userId, 'GET', $this->f->cheminDeListeSimple());
        if ($liste['code'] < 400) {
            foreach ($this->f->elements($liste['corps']) as $cal) {
                $lu = $this->f->lireCalendrier($cal);
                if ($lu !== null && $lu['nom'] === self::CALENDRIER) {
                    return $this->retenirLeCalendrier($userId, $lu['id']);
                }
            }
        }

        [$chemin, $corps] = $this->f->creationDeCalendrier(self::CALENDRIER);
        $cree = $this->lien()->appeler($userId, 'POST', $chemin, $corps);
        if ($cree['code'] >= 400 || !isset($cree['corps']['id'])) {
            $dit = (string) ($cree['corps']['error']['message'] ?? '');

            throw new RuntimeException($this->f->nom() . ' a refusé de créer le calendrier « '
                . self::CALENDRIER . ' »' . ($dit === '' ? '.' : ' : ' . mb_substr($dit, 0, 200)));
        }

        return $this->retenirLeCalendrier($userId, (string) $cree['corps']['id']);
    }

    private function retenirLeCalendrier(int $userId, string $id): string
    {
        Database::run(
            'UPDATE agenda_comptes SET calendrier_envoi_id = ?, calendrier_envoi_nom = ?,
                    envoi_choisi = 0
              WHERE user_id = ? AND fournisseur = ?',
            [$id, self::CALENDRIER, $userId, $this->f->cle()]
        );

        return $id;
    }

    /**
     * Où vont les évènements, et si c'est la personne qui l'a voulu.
     *
     * La différence n'est pas cosmétique : « Mes Cours » est le reflet de
     * l'application et se retire des listes qu'on affiche — on ne choisit pas
     * de suivre son propre reflet. Un calendrier désigné, lui, reste un
     * calendrier ordinaire, qu'on coche et qu'on colorie comme les autres.
     *
     * @return array{id: ?string, nom: string, choisi: bool}
     */
    public function destination(int $userId): array
    {
        $ligne = Database::one(
            'SELECT calendrier_envoi_id, calendrier_envoi_nom, envoi_choisi
               FROM agenda_comptes WHERE user_id = ? AND fournisseur = ?',
            [$userId, $this->f->cle()]
        );

        $id = (string) ($ligne['calendrier_envoi_id'] ?? '');

        return [
            'id'     => $id === '' ? null : $id,
            'nom'    => (string) ($ligne['calendrier_envoi_nom'] ?? self::CALENDRIER),
            'choisi' => (int) ($ligne['envoi_choisi'] ?? 0) === 1,
        ];
    }

    /**
     * Change l'agenda qui reçoit les évènements de l'application.
     *
     * Ce qui était déjà parti quitte l'ancienne destination avant que la
     * nouvelle ne s'ouvre : laisser la moitié de ses évènements dans un
     * calendrier et l'autre moitié ailleurs serait pire que les deux
     * situations qu'on essaie de départager. La remise en place ne se fait pas
     * ici — l'empreinte d'envoi est effacée, et la synchronisation suivante,
     * qui part d'elle-même, les recrée au bon endroit.
     *
     * @param  ?string $empreinte  l'empreinte d'un calendrier à soi, ou null
     *                             pour revenir au « Mes Cours » de l'application
     * @return array{nom: string, retires: int}
     * @throws RuntimeException si le calendrier ne s'y prête pas, ou si le
     *                          fournisseur refuse de rendre ce qu'il détient
     */
    public function changerDeDestination(int $userId, ?string $empreinte): array
    {
        $cible = null;
        if ($empreinte !== null) {
            $cible = Database::one(
                'SELECT calendrier_id, nom FROM agenda_calendriers
                  WHERE user_id = ? AND fournisseur = ? AND empreinte = ?
                    AND partage = 0 AND peut_ecrire = 1',
                [$userId, $this->f->cle(), $empreinte]
            );
            if ($cible === null) {
                throw new RuntimeException('Cet agenda n’est pas un des vôtres, ou '
                    . $this->f->nom() . ' n’y autorise pas l’écriture.');
            }
        }

        /*
         * Est-ce le même endroit ? Sans calendrier visé, on désigne « Mes
         * Cours » — que l'application l'ait déjà trouvé chez le fournisseur ou
         * non, ce qui se dit de deux façons en base et ne fait qu'une seule
         * destination.
         */
        $avant = $this->destination($userId);
        $memeEndroit = $cible === null
            ? !$avant['choisi']
            : $avant['choisi'] && $avant['id'] === (string) $cible['calendrier_id'];

        if ($memeEndroit) {
            return ['nom' => $avant['nom'], 'retires' => 0];
        }

        $retires = $this->toutRetirer($userId);

        if ($cible === null) {
            // On oublie la destination : le prochain envoi retrouvera « Mes
            // Cours », ou le recréera s'il a disparu entre-temps.
            Database::run(
                'UPDATE agenda_comptes SET calendrier_envoi_id = NULL,
                        calendrier_envoi_nom = NULL, envoi_choisi = 0
                  WHERE user_id = ? AND fournisseur = ?',
                [$userId, $this->f->cle()]
            );

            return ['nom' => self::CALENDRIER, 'retires' => $retires];
        }

        Database::run(
            'UPDATE agenda_comptes SET calendrier_envoi_id = ?, calendrier_envoi_nom = ?,
                    envoi_choisi = 1
              WHERE user_id = ? AND fournisseur = ?',
            [(string) $cible['calendrier_id'], mb_substr((string) $cible['nom'], 0, 190),
             $userId, $this->f->cle()]
        );

        return ['nom' => (string) $cible['nom'], 'retires' => $retires];
    }

    /* --- Ce qui doit monter ----------------------------------------------- */

    /**
     * Tout ce qui, dans l'application, a sa place dans l'agenda.
     *
     * @return array<string, array{corps: array}>  rangé par « sorte:id »
     */
    private function aEnvoyer(
        int $userId,
        DateTimeImmutable $depuis,
        DateTimeImmutable $jusqua,
        string $defaut
    ): array {
        $tout = [];
        $miens = $this->calendriersDIci($userId);
        $connues = $this->calendriersConnus($userId);
        $vises = $this->ciblesParEvenement($userId, $depuis, $jusqua);

        /*
         * Les évènements, sauf ceux qui viennent d'Outlook : les renvoyer
         * reviendrait à les rendre à leur expéditeur, en double.
         */
        foreach (Database::all(
            'SELECT e.id, e.titre, e.description, e.lieu, e.debut, e.fin, e.journee_entiere
               FROM evenements e
               /*
                * Aucun fournisseur ici, volontairement : un évènement venu de
                * n’importe quel agenda n’est pas né dans l’application et n’a
                * donc à repartir vers aucun autre.
                */
               LEFT JOIN agenda_liens l ON l.evenement_id = e.id AND l.user_id = e.user_id
              WHERE e.user_id = ? AND l.id IS NULL AND e.debut BETWEEN ? AND ?',
            [$userId, $depuis->format('Y-m-d H:i:s'), $jusqua->format('Y-m-d H:i:s')]
        ) as $evt) {
            $corps = $this->corpsDUnEvenement($evt);

            foreach ($vises[(int) $evt['id']] ?? [Agenda::DEFAUT] as $cible) {
                /*
                 * Une cible inconnue de tous les agendas — un calendrier
                 * délié, supprimé, repris — vaut « Mes évènements » : mieux
                 * vaut le rendez-vous quelque part que nulle part.
                 */
                if ($cible === Agenda::DEFAUT || !isset($connues[$cible])) {
                    $ou = $defaut;
                } elseif (isset($miens[$cible])) {
                    $ou = $miens[$cible];
                } else {
                    // Elle désigne l'agenda d'un autre fournisseur : c'est lui
                    // qui s'en charge, et ici elle n'a rien à faire.
                    continue;
                }

                $tout['evenement:' . (int) $evt['id'] . ':' . md5($ou)] = [
                    'sorte'      => 'evenement',
                    'source_id'  => (int) $evt['id'],
                    'calendrier' => $ou,
                    'corps'      => $corps,
                ];
            }
        }

        /*
         * Les échéances de tâches, en journée entière. Une tâche faite quitte
         * l'agenda : elle n'a plus rien à y rappeler.
         */
        foreach (Database::all(
            'SELECT t.id, t.titre, t.note, t.echeance, l.nom AS liste_nom
               FROM taches t
               JOIN listes_taches l ON l.id = t.liste_id
              WHERE t.user_id = ? AND t.faite = 0 AND t.echeance BETWEEN ? AND ?',
            [$userId, $depuis->format('Y-m-d'), $jusqua->format('Y-m-d')]
        ) as $tache) {
            // Une échéance de tâche ne se choisit pas d'agenda : c'est un
            // rappel de l'application, il reste là où l'application écrit.
            $tout['tache:' . (int) $tache['id'] . ':' . md5($defaut)] = [
                'sorte'      => 'tache',
                'source_id'  => (int) $tache['id'],
                'calendrier' => $defaut,
                'corps'      => $this->corpsDUneTache($tache),
            ];
        }

        return $tout;
    }

    /**
     * Les agendas visés par chaque évènement de la fenêtre.
     *
     * En une requête plutôt qu'une par évènement : la boucle en compte
     * plusieurs centaines, et l'envoi la parcourt pour chaque agenda relié.
     *
     * Un évènement sans ligne ne figure pas ici ; l'appelant en fait « Mes
     * évènements », ce qui est le cas de tout ce qui a été écrit avant que le
     * choix existe.
     *
     * @return array<int, array<int, string>>
     */
    private function ciblesParEvenement(
        int $userId,
        DateTimeImmutable $depuis,
        DateTimeImmutable $jusqua
    ): array {
        $par = [];
        foreach (Database::all(
            'SELECT a.evenement_id, a.empreinte
               FROM evenement_agendas a
               JOIN evenements e ON e.id = a.evenement_id
              WHERE e.user_id = ? AND e.debut BETWEEN ? AND ?',
            [$userId, $depuis->format('Y-m-d H:i:s'), $jusqua->format('Y-m-d H:i:s')]
        ) as $ligne) {
            $par[(int) $ligne['evenement_id']][] = (string) $ligne['empreinte'];
        }

        return $par;
    }

    /**
     * Les calendriers de ce fournisseur-ci : empreinte vers identifiant.
     *
     * @return array<string, string>
     */
    private function calendriersDIci(int $userId): array
    {
        $par = [];
        foreach (Database::all(
            'SELECT empreinte, calendrier_id FROM agenda_calendriers
              WHERE user_id = ? AND fournisseur = ? AND peut_ecrire = 1',
            [$userId, $this->f->cle()]
        ) as $ligne) {
            $par[(string) $ligne['empreinte']] = (string) $ligne['calendrier_id'];
        }

        return $par;
    }

    /**
     * Toutes les empreintes connues, tous fournisseurs confondus.
     *
     * Elles départagent les deux façons dont une cible peut ne pas être d'ici :
     * elle appartient à l'autre agenda, et c'est lui qui l'enverra — ou elle
     * n'existe plus nulle part, et l'évènement doit retomber quelque part.
     *
     * @return array<string, true>
     */
    private function calendriersConnus(int $userId): array
    {
        $par = [];
        foreach (Database::all(
            'SELECT empreinte FROM agenda_calendriers WHERE user_id = ? AND peut_ecrire = 1',
            [$userId]
        ) as $ligne) {
            $par[(string) $ligne['empreinte']] = true;
        }

        return $par;
    }

    /**
     * Un évènement de l'application dans les termes de l'agenda.     *
     * Public parce que la lecture s'en sert aussi : quand on modifie ici un
     * évènement venu de là-bas, c'est la même traduction qui repart.
     */
    public function corpsDUnEvenement(array $evt): array
    {
        $journee = (int) $evt['journee_entiere'] === 1;
        $debut = new DateTimeImmutable((string) $evt['debut']);
        $fin = new DateTimeImmutable((string) $evt['fin']);

        if ($journee) {
            /*
             * L'application termine une journée entière à 23:59:59 ; Microsoft
             * la borne par le lendemain à minuit. On avance d'une seconde puis
             * on coupe à la date : c'est l'inverse exact de ce que fait la
             * lecture, et les deux doivent rester d'accord.
             */
            $debut = $debut->setTime(0, 0);
            $fin = $fin->modify('+1 second')->setTime(0, 0);
            if ($fin <= $debut) {
                $fin = $debut->modify('+1 day');
            }
        }

        return $this->corps(
            (string) $evt['titre'],
            (string) ($evt['description'] ?? ''),
            (string) ($evt['lieu'] ?? ''),
            $debut,
            $fin,
            $journee
        );
    }

    /** Une échéance de tâche : une journée entière, et de quoi savoir d'où elle vient. */
    private function corpsDUneTache(array $tache): array
    {
        $jour = new DateTimeImmutable((string) $tache['echeance']);
        $note = trim((string) ($tache['note'] ?? ''));

        return $this->corps(
            self::MARQUE_TACHE . (string) $tache['titre'],
            'Échéance d’une tâche de la liste « ' . (string) $tache['liste_nom'] . ' ».'
                . ($note === '' ? '' : "\n\n" . $note),
            '',
            $jour->setTime(0, 0),
            $jour->modify('+1 day')->setTime(0, 0),
            true
        );
    }

    /** La forme qu'attend l'agenda, la même pour tout ce qu'on envoie. */
    private function corps(
        string $titre,
        string $texte,
        string $lieu,
        DateTimeImmutable $debut,
        DateTimeImmutable $fin,
        bool $journee
    ): array {
        return $this->f->corpsDUnEvenement(
            $titre, $texte, $lieu, $debut, $fin, $journee, $this->fuseau());
    }

    /* --- Écrire chez le fournisseur -------------------------------------------- */

    /** Ce qui est déjà parti, rangé par « sorte:id ». */
    private function partis(int $userId): array
    {
        $par = [];
        foreach (Database::all(
            'SELECT id, sorte, source_id, distant_id, calendrier_id,
                    calendrier_empreinte, empreinte
               FROM agenda_envois WHERE user_id = ? AND fournisseur = ?',
            [$userId, $this->f->cle()]
        ) as $ligne) {
            $par[$ligne['sorte'] . ':' . (int) $ligne['source_id']
                 . ':' . (string) $ligne['calendrier_empreinte']] = $ligne;
        }

        return $par;
    }

    private function creer(int $userId, string $calendrier, array $quoi, string $empreinte): void
    {
        $reponse = $this->lien()->appeler(
            $userId,
            'POST',
            $this->f->cheminDeCreation($calendrier),
            $quoi['corps']
        );
        $this->verifier($reponse, 'créer un évènement');

        Database::run(
            'INSERT INTO agenda_envois
                 (user_id, fournisseur, sorte, source_id, distant_id, calendrier_id,
                  calendrier_empreinte, empreinte)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE distant_id = VALUES(distant_id),
                 calendrier_id = VALUES(calendrier_id), empreinte = VALUES(empreinte)',
            [$userId, $this->f->cle(), $quoi['sorte'], $quoi['source_id'],
             (string) ($reponse['corps']['id'] ?? ''), $calendrier, md5($calendrier), $empreinte]
        );
    }

    private function modifier(
        int $userId,
        array $quoi,
        string $empreinte,
        string $distantId,
        string $calendrierId
    ): void {
        $reponse = $this->lien()->appeler($userId, 'PATCH',
            $this->f->cheminDeModification($calendrierId, $distantId), $quoi['corps']);

        /*
         * Introuvable : quelqu'un l'a supprimé là-bas. On ne s'en offusque pas
         * — on oublie le lien, et le prochain passage le recréera.
         */
        if ($reponse['code'] === 404) {
            Database::run('DELETE FROM agenda_envois WHERE user_id = ? AND fournisseur = ? AND distant_id = ?',
                [$userId, $this->f->cle(), $distantId]);

            return;
        }
        $this->verifier($reponse, 'mettre à jour un évènement');

        Database::run(
            'UPDATE agenda_envois SET empreinte = ?, maj_le = NOW()
              WHERE user_id = ? AND fournisseur = ? AND distant_id = ?',
            [$empreinte, $userId, $this->f->cle(), $distantId]
        );
    }

    /**
     * Efface là-bas, sans s'émouvoir de ce qui n'y est déjà plus.
     *
     * Le calendrier accompagne l'évènement : Microsoft le retrouve sans lui,
     * Google non.
     */
    private function effacer(int $userId, string $distantId, string $calendrierId): void
    {
        $reponse = $this->lien()->appeler($userId, 'DELETE',
            $this->f->cheminDeSuppression($calendrierId, $distantId));
        if ($reponse['code'] === 404 || $reponse['code'] === 410) {
            return;
        }
        $this->verifier($reponse, 'supprimer un évènement');
    }

    /** @throws RuntimeException si le fournisseur a refusé */
    private function verifier(array $reponse, string $quoi): void
    {
        if ($reponse['code'] < 400) {
            return;
        }

        $dit = (string) ($reponse['corps']['error']['message'] ?? '');

        throw new RuntimeException($this->f->nom() . ' a refusé de ' . $quoi
            . ($dit === '' ? '.' : ' : ' . mb_substr($dit, 0, 200)));
    }
}
