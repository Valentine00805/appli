<?php
declare(strict_types=1);

/**
 * Faire venir dans l'application les évènements d'un agenda Outlook.
 *
 * Le sens est unique pour l'instant : d'Outlook vers ici. Ce qui vient de
 * là-bas y est tenu pour vrai — une modification faite dans l'application sur
 * un évènement importé sera reprise à la synchronisation suivante. C'est dit
 * à l'écran plutôt que deviné.
 *
 * On demande à Microsoft une *vue de calendrier* plutôt que la liste brute des
 * évènements : elle déplie les séries récurrentes en occurrences, chacune avec
 * sa date. C'est ce qu'il faut pour un calendrier qu'on regarde, et cela évite
 * d'avoir à interpréter soi-même des règles de récurrence.
 *
 * En contrepartie, une vue porte sur une période. Ce qui en sort n'est pas
 * effacé de l'application : seul disparaît d'ici ce qui a disparu de là-bas
 * *à l'intérieur* de la fenêtre regardée.
 */
final class SynchroAgenda
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

    /** La fenêtre regardée, autour d'aujourd'hui. */
    private const AVANT = '-1 month';
    private const APRES = '+12 months';


    private const PAR_PAGE = 100;

    /** Une garde : au-delà, quelque chose ne tourne pas rond côté du fournisseur. */
    private const PAGES_MAX = 60;

    /**
     * Le fuseau de la personne, réglé au démarrage.
     *
     * On le redemande à PHP plutôt que de le figer : c'est la même heure que
     * celle où s'affichent ses évènements, et les deux ne doivent jamais
     * diverger.
     */
    private function fuseau(): string
    {
        return date_default_timezone_get();
    }

    /**
     * Le calendrier principal, quand on n'a jamais demandé la liste.
     *
     * C'est celui que Microsoft donne par défaut, et l'application y a les
     * mêmes droits que dans le sien : on peut y supprimer.
     */
    private const DEFAUT = 'principal';

    /** Dans le volet du calendrier, la ligne de ses propres évènements. */
    public const MIENS = 'miens';

    /**
     * Le repos entre deux lectures automatiques, en secondes.
     *
     * Cinq minutes. Un quart d'heure paraissait raisonnable jusqu'à ce qu'on
     * ajoute un rendez-vous et qu'on attende devant l'écran : ce n'est pas la
     * charge du serveur qui fixe ce délai, c'est la patience de qui regarde.
     * Pour ne pas attendre du tout, le bouton est là.
     */
    private const REPOS = 300;

    /**
     * Va chercher les évènements et met l'application à jour.
     *
     * @return array{ajoutes: int, modifies: int, retires: int, inchanges: int}
     * @throws RuntimeException si le compte n'est pas relié, ou si Microsoft refuse
     */
    public function tirer(int $userId): array
    {
        /*
         * Deux lectures en même temps — un onglet qui se réveille pendant
         * qu'on clique — créeraient les mêmes évènements deux fois : chacune
         * les croirait nouveaux. Le verrou est demandé sans attendre ; la
         * seconde repart les mains vides plutôt que de faire la queue.
         */
        $verrou = 'mescours_outlook_' . $userId;
        if ((int) Database::valeur('SELECT GET_LOCK(?, 0)', [$verrou]) !== 1) {
            return ['ajoutes' => 0, 'modifies' => 0, 'retires' => 0,
                    'inchanges' => 0, 'effaces' => 0, 'occupe' => true];
        }

        try {
            return $this->vraimentTirer($userId);
        } finally {
            Database::run('SELECT RELEASE_LOCK(?)', [$verrou]);
        }
    }

    /** @return array{ajoutes: int, modifies: int, retires: int, inchanges: int, occupe: bool} */
    private function vraimentTirer(int $userId): array
    {
        $fuseau = new DateTimeZone($this->fuseau());
        $maintenant = new DateTimeImmutable('now', $fuseau);
        $depuis = $maintenant->modify(self::AVANT)->setTime(0, 0);
        $jusqua = $maintenant->modify(self::APRES)->setTime(23, 59, 59);

        // Avant de lire : ce qu'on a supprimé ici doit partir de là-bas, sans
        // quoi la lecture le ramènerait aussitôt.
        $bilanEfface = $this->porterLesSuppressions($userId);

        $venus = $this->lire($userId, $depuis, $jusqua);
        $connus = $this->liens($userId);
        /*
         * Ce que l'application a elle-même écrit dans « Mes Cours ». On le
         * croise en relisant ce calendrier, et le rapatrier ferait de chaque
         * évènement son propre double — puis le double d'un double.
         */
        $ecrits = $this->ecritsParNous($userId);

        $bilan = ['ajoutes' => 0, 'modifies' => 0, 'retires' => 0,
                  'inchanges' => 0, 'effaces' => 0, 'occupe' => false];
        $vus = [];

        foreach ($venus as $brut) {
            $champs = $this->traduire($brut, $fuseau);
            if ($champs === null) {
                continue;
            }

            $outlookId = (string) $brut['id'];
            if (isset($ecrits[$outlookId])) {
                continue;
            }
            $vus[$outlookId] = true;
            $empreinte = md5(json_encode($champs, JSON_THROW_ON_ERROR));
            $ou = (string) ($brut['_calendrier'] ?? '');
            $lien = $connus[$outlookId] ?? null;

            if ($lien === null) {
                $this->ajouter($userId, $outlookId, $champs, $empreinte, $ou);
                $bilan['ajoutes']++;
                continue;
            }
            if ((string) ($lien['calendrier'] ?? '') !== $ou) {
                // Une origine inconnue, ou changée : sans elle on refuserait
                // plus tard de supprimer là-bas, faute de savoir où.
                Database::run(
                    'UPDATE agenda_liens SET calendrier = ? WHERE user_id = ? AND fournisseur = ? AND distant_id = ?',
                    [$ou, $userId, $this->f->cle(), $outlookId]
                );
            }
            if ($lien['empreinte'] === $empreinte) {
                $bilan['inchanges']++;
                continue;
            }

            $this->mettreAJour($userId, (int) $lien['evenement_id'], $outlookId, $champs, $empreinte);
            $bilan['modifies']++;
        }

        $bilan['retires'] = $this->retirerLesDisparus($userId, $connus, $vus, $depuis, $jusqua);
        $bilan['effaces'] = $bilanEfface;

        Database::run('UPDATE agenda_comptes SET synchro_le = NOW() WHERE user_id = ? AND fournisseur = ?', [$userId, $this->f->cle()]);

        return $bilan;
    }

    /**
     * Est-il temps de relire l'agenda de son propre chef ?
     *
     * Faux si le compte n'est pas relié, si aucun calendrier n'est suivi, ou
     * si la dernière lecture est trop fraîche.
     */
    public function aBesoinDEtreRelu(int $userId): bool
    {
        if (!$this->lien()->configure() || !$this->lien()->relie($userId)) {
            return false;
        }

        /*
         * Un lien devenu orphelin, c'est un évènement supprimé ici dont
         * Outlook ne sait rien encore. On ne fait pas attendre cinq minutes
         * une suppression : c'est le geste qu'on vérifie le plus vite.
         */
        $orphelins = (int) Database::valeur(
            'SELECT COUNT(*) FROM agenda_liens WHERE user_id = ? AND fournisseur = ? AND evenement_id IS NULL',
            [$userId, $this->f->cle()]
        );
        if ($orphelins > 0) {
            return true;
        }

        $connus = (int) Database::valeur(
            'SELECT COUNT(*) FROM agenda_calendriers WHERE user_id = ? AND fournisseur = ?', [$userId, $this->f->cle()]);
        $suivis = (int) Database::valeur(
            'SELECT COUNT(*) FROM agenda_calendriers WHERE user_id = ? AND fournisseur = ? AND suivi = 1', [$userId, $this->f->cle()]);
        if ($connus > 0 && $suivis === 0) {
            // Tout a été décoché : il n'y a plus rien à aller chercher.
            return false;
        }

        $quand = $this->derniereFois($userId);
        if ($quand === null) {
            // Jamais lu : c'est à l'utilisateur de lancer la première fois,
            // pour qu'il voie arriver ce qu'il a demandé.
            return false;
        }

        return (time() - (int) strtotime($quand)) >= self::REPOS;
    }

    /**
     * Retient le dernier échec, ou l'efface quand tout est rentré dans l'ordre.
     *
     * La synchronisation tourne aussi seule, en arrière-plan, où elle ne peut
     * rien dire sans interrompre. Muette, elle laisserait quelqu'un attendre
     * des jours des évènements qui ne viennent plus.
     */
    public function retenirLeSouci(int $userId, ?string $souci): void
    {
        $texte = $souci === null ? null : mb_substr($souci, 0, 500);

        Database::run(
            'UPDATE agenda_comptes
                SET souci = ?, souci_le = IF(? IS NULL, NULL, NOW())
              WHERE user_id = ? AND fournisseur = ?',
            [$texte, $texte, $userId, $this->f->cle()]
        );
    }

    /**
     * Le dernier échec, s'il n'a pas été suivi d'une réussite.
     *
     * @return ?array{quoi: string, quand: string}
     */
    public function dernierSouci(int $userId): ?array
    {
        $ligne = Database::one(
            'SELECT souci, souci_le FROM agenda_comptes WHERE user_id = ? AND fournisseur = ? AND souci IS NOT NULL',
            [$userId, $this->f->cle()]
        );

        return $ligne === null
            ? null
            : ['quoi' => (string) $ligne['souci'], 'quand' => (string) $ligne['souci_le']];
    }

    /** La date de la dernière synchronisation, ou null s'il n'y en a jamais eu. */
    public function derniereFois(int $userId): ?string
    {
        $quand = Database::valeur(
            'SELECT synchro_le FROM agenda_comptes WHERE user_id = ? AND fournisseur = ?',
            [$userId, $this->f->cle()]
        );

        return $quand === null ? null : (string) $quand;
    }

    /** Combien d'évènements de l'application viennent d'Outlook. */
    public function combien(int $userId): int
    {
        return (int) Database::valeur(
            'SELECT COUNT(*) FROM agenda_liens WHERE user_id = ? AND fournisseur = ?',
            [$userId, $this->f->cle()]
        );
    }

    /**
     * Retire du calendrier tout ce qui venait d'Outlook.
     *
     * Rien n'est perdu : ces évènements existent toujours dans l'agenda
     * Microsoft, d'où ils reviendront à la prochaine lecture. Ce qui a été
     * créé dans l'application, lui, n'est pas concerné — la suppression ne
     * porte que sur ce que la synchronisation avait elle-même apporté.
     *
     * @return int  combien ont été retirés
     */
    public function toutRetirer(int $userId): int
    {
        $ids = Database::all(
            'SELECT evenement_id FROM agenda_liens WHERE user_id = ? AND fournisseur = ? AND evenement_id IS NOT NULL',
            [$userId, $this->f->cle()]
        );

        /*
         * On délie d'abord. Depuis qu'un lien sans évènement vaut ordre de
         * suppression chez le fournisseur, en laisser derrière soi ferait disparaître
         * de l'agenda ce qu'on voulait seulement retirer d'ici.
         */
        Database::run('DELETE FROM agenda_liens WHERE user_id = ? AND fournisseur = ?', [$userId, $this->f->cle()]);

        $retires = 0;
        foreach ($ids as $ligne) {
            // Un par un, et toujours borné au compte : la règle de la maison.
            Database::run('DELETE FROM evenements WHERE id = ? AND user_id = ?',
                [(int) $ligne['evenement_id'], $userId]);
            $retires++;
        }
        Database::run('UPDATE agenda_comptes SET synchro_le = NULL WHERE user_id = ? AND fournisseur = ?', [$userId, $this->f->cle()]);

        return $retires;
    }

    /* --- Les calendriers -------------------------------------------------- */

    /**
     * Les calendriers connus de l'application, tels qu'elle les a vus.
     *
     * @return array<int, array>
     */
    public function calendriers(int $userId): array
    {
        $ecriture = EnvoiAgenda::pour($this->f)->calendrierConnu($userId);

        return Database::all(
            'SELECT id, empreinte, nom, proprietaire, partage, principal, suivi, affiche, couleur
               FROM agenda_calendriers
              WHERE user_id = ? AND fournisseur = ? AND empreinte <> ?
              ORDER BY principal DESC, partage ASC, nom ASC',
            [$userId, $this->f->cle(), $ecriture === null ? '' : md5($ecriture)]
        );
    }

    /**
     * Redemande à Microsoft la liste des calendriers du compte.
     *
     * Les calendriers vivent dans des groupes — « Mes calendriers », « Autres
     * calendriers » —, et c'est dans le second que Microsoft range ceux qu'on
     * nous a partagés. Les demander groupe par groupe est le seul moyen de ne
     * pas passer à côté de la moitié d'un agenda.
     *
     * Un calendrier vu pour la première fois n'est pas suivi, sauf le
     * principal : on ne remplit pas le calendrier de quelqu'un sans qu'il l'ait
     * demandé.
     *
     * @return int  combien de calendriers sont désormais connus
     * @throws RuntimeException si Microsoft refuse
     */
    public function rafraichirLesCalendriers(int $userId): int
    {
        $moi = mb_strtolower((string) ($this->lien()->compte($userId)['compte'] ?? ''));
        $trouves = [];

        foreach ($this->sourcesDeCalendriers($userId) as $chemin) {
            $reponse = $this->lien()->appeler($userId, 'GET', $chemin);
            if ($reponse['code'] >= 400) {
                // Un groupe inaccessible ne doit pas emporter les autres :
                // certains comptes n'ont pas tous les groupes.
                continue;
            }
            foreach ($this->f->elements($reponse['corps']) as $cal) {
                if (isset($cal['id'])) {
                    $trouves[(string) $cal['id']] = $cal;
                }
            }
        }

        if ($trouves === []) {
            throw new RuntimeException($this->f->nom() . ' n’a donné aucun calendrier.');
        }

        $connus = [];
        foreach ($this->calendriers($userId) as $ligne) {
            $connus[(string) $ligne['empreinte']] = true;
        }

        foreach ($trouves as $id => $cal) {
            $empreinte = md5($id);
            $lu = $this->f->lireCalendrier($cal);
            if ($lu === null) {
                continue;
            }
            $adresse = mb_strtolower($lu['adresse']);
            $principal = $lu['principal'] ? 1 : 0;
            $partage = ($adresse !== '' && $moi !== '' && $adresse !== $moi) ? 1 : 0;

            if (isset($connus[$empreinte])) {
                // Le choix de l'utilisateur ne se réécrit pas : seul le
                // signalement change.
                Database::run(
                    'UPDATE agenda_calendriers
                        SET nom = ?, proprietaire = ?, partage = ?, principal = ?, vu_le = NOW()
                      WHERE user_id = ? AND fournisseur = ? AND empreinte = ?',
                    [
                        mb_substr($lu['nom'], 0, 190),
                        mb_substr($lu['proprietaire'], 0, 190),
                        $partage, $principal, $userId, $this->f->cle(), $empreinte,
                    ]
                );
                continue;
            }

            /*
             * Une couleur lui est attribuée d'emblée, en tournant dans la
             * palette : un agenda sans couleur serait gris comme ses voisins,
             * et c'est justement pour les distinguer qu'on les suit.
             */
            $deja = (int) Database::valeur(
                'SELECT COUNT(*) FROM agenda_calendriers WHERE user_id = ? AND fournisseur = ?', [$userId, $this->f->cle()]);

            Database::run(
                'INSERT INTO agenda_calendriers
                     (user_id, fournisseur, calendrier_id, empreinte, nom, proprietaire, partage,
                      principal, suivi, couleur)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $userId, $this->f->cle(), $id, $empreinte,
                    mb_substr($lu['nom'], 0, 190),
                    mb_substr($lu['proprietaire'], 0, 190),
                    $partage, $principal, $principal,
                    Agenda::couleurOuDefaut('', $deja + 1),
                ]
            );
        }

        return count($trouves);
    }

    /**
     * Retient les calendriers à lire.
     *
     * @param  array<int, string> $empreintes  ceux que l'utilisateur a cochés
     * @return int  combien sont suivis
     */
    public function choisir(int $userId, array $empreintes): int
    {
        $garder = [];
        foreach ($empreintes as $empreinte) {
            if (is_string($empreinte) && preg_match('/^[0-9a-f]{32}$/', $empreinte) === 1) {
                $garder[$empreinte] = true;
            }
        }

        foreach ($this->calendriers($userId) as $ligne) {
            $veut = isset($garder[(string) $ligne['empreinte']]) ? 1 : 0;
            if ((int) $ligne['suivi'] === $veut) {
                continue;
            }
            Database::run(
                'UPDATE agenda_calendriers SET suivi = ? WHERE user_id = ? AND fournisseur = ? AND empreinte = ?',
                [$veut, $userId, $this->f->cle(), (string) $ligne['empreinte']]
            );
        }

        return count($garder);
    }

    /** Où le fournisseur range les calendriers d'un compte. */
    private function sourcesDeCalendriers(int $userId): array
    {
        return $this->f->cheminsDesCalendriers($userId);
    }

    /* --- Lire chez le fournisseur --------------------------------------------- */

    /**
     * Toutes les occurrences de la fenêtre, page après page.
     *
     * @return array<int, array>
     */
    private function lire(int $userId, DateTimeImmutable $depuis, DateTimeImmutable $jusqua): array
    {
        $tout = [];
        foreach ($this->aLire($userId) as $calendrier) {
            // D'où vient l'évènement : c'est de cela que dépendra le droit de
            // le supprimer là-bas, le jour où on le supprimera ici.
            $ou = $calendrier === null
                ? self::DEFAUT
                : md5((string) $calendrier['calendrier_id']);

            $question = $this->f->cheminDesEvenements(
                $calendrier === null ? null : (string) $calendrier['calendrier_id'],
                $depuis, $jusqua, self::PAR_PAGE, $this->fuseau()
            );

            foreach ($this->unCalendrier($userId, $calendrier, $question) as $evenement) {
                // Un évènement partagé entre deux calendriers ne compte qu'une
                // fois : son identifiant tranche.
                $evenement['_calendrier'] = $ou;
                $tout[(string) ($evenement['id'] ?? '')] = $evenement;
            }
        }
        unset($tout['']);

        return array_values($tout);
    }

    /**
     * Les calendriers à parcourir.
     *
     * Tant que la liste n'a jamais été demandée à Microsoft, on s'en tient au
     * calendrier principal : c'est le comportement d'avant, et il vaut mieux
     * lire trop peu que se mettre à remplir un calendrier sans prévenir.
     *
     * « Mes Cours » — celui que l'application remplit — est lu lui aussi, mais
     * à part : il n'est pas à cocher, puisqu'on ne choisit pas de suivre son
     * propre reflet. Ce qu'on y trouve et qu'on n'y a pas écrit soi-même est
     * un évènement créé depuis Outlook, et il a sa place ici.
     *
     * @return array<int, ?array>  null désigne le calendrier principal
     */
    private function aLire(int $userId): array
    {
        $ecriture = EnvoiAgenda::pour($this->f)->calendrierConnu($userId);

        $suivis = Database::all(
            'SELECT calendrier_id, nom FROM agenda_calendriers
              WHERE user_id = ? AND fournisseur = ? AND suivi = 1 AND empreinte <> ?',
            [$userId, $this->f->cle(), $ecriture === null ? '' : md5($ecriture)]
        );
        if ($suivis === []) {
            $suivis = [null];
        }

        if ($ecriture !== null) {
            $suivis[] = ['calendrier_id' => $ecriture, 'nom' => 'Mes Cours'];
        }

        return $suivis;
    }

    /**
     * Les occurrences d'un calendrier, page après page.
     *
     * @param  ?array $calendrier  null pour le calendrier principal
     * @return array<int, array>
     */
    private function unCalendrier(int $userId, ?array $calendrier, string $question): array
    {
        $chemin = $question;
        $nom = $calendrier === null ? 'l’agenda' : '« ' . (string) $calendrier['nom'] . ' »';

        /*
         * « Prefer » demande que les heures reviennent déjà dans notre fuseau.
         * Sans cela Microsoft répond en UTC, et il faudrait convertir à la main
         * une date que lui sait convertir mieux que nous.
         */
        $entetes = $this->f->entetesDeLecture($this->fuseau());

        $tout = [];
        for ($page = 0; $page < self::PAGES_MAX && $chemin !== ''; $page++) {
            $reponse = $this->lien()->appeler($userId, 'GET', $chemin, null, $entetes);

            if ($reponse['code'] === 403) {
                throw new RuntimeException(
                    $this->f->nom() . ' refuse l’accès à ' . $nom . '. Si c’est un calendrier '
                    . 'partagé par quelqu’un d’autre, réautorisez l’application : '
                    . 'la permission qui les ouvre est plus récente que votre liaison.'
                );
            }
            if ($reponse['code'] >= 400) {
                $dit = (string) ($reponse['corps']['error']['message'] ?? '');

                throw new RuntimeException($this->f->nom() . ' a refusé de donner ' . $nom
                    . ($dit === '' ? '.' : ' : ' . mb_substr($dit, 0, 200)));
            }

            foreach ($this->f->elements($reponse['corps']) as $evenement) {
                $tout[] = $evenement;
            }
            $chemin = $this->f->pageSuivante($reponse['corps'], $chemin);
        }

        return $tout;
    }

    /* --- Traduire ---------------------------------------------------------- */

    /**
     * Un évènement Microsoft dans les termes de l'application.
     *
     * @return ?array  null si l'évènement n'a pas sa place ici (annulé, illisible)
     */
    private function traduire(array $brut, DateTimeZone $fuseau): ?array
    {
        return $this->f->lireEvenement($brut, $fuseau);
    }

    /* --- Écrire ici -------------------------------------------------------- */

    /**
     * Supprime chez le fournisseur ce qui a été supprimé ici — là où c'est chez soi.
     *
     * Un évènement effacé dans l'application laisse son lien derrière lui,
     * orphelin : c'est cette trace qu'on ramasse. Encore faut-il avoir le
     * droit d'en tirer les conséquences, et ce droit s'arrête au bord de
     * l'agenda des autres.
     *
     * On ne supprime donc que dans deux calendriers : « Mes Cours », qui
     * n'existe que par cette application, et le calendrier principal, qui est
     * celui de la personne. Ailleurs — l'agenda d'un proche, d'un groupe, les
     * jours fériés — le lien est simplement oublié, et l'évènement reviendra à
     * la lecture suivante : c'est déjà ce qui était annoncé.
     *
     * @return int  combien ont été effacés chez le fournisseur
     */
    private function porterLesSuppressions(int $userId): int
    {
        $orphelins = Database::all(
            'SELECT id, distant_id, calendrier FROM agenda_liens
              WHERE user_id = ? AND fournisseur = ? AND evenement_id IS NULL',
            [$userId, $this->f->cle()]
        );
        if ($orphelins === []) {
            return 0;
        }

        $permis = $this->calendriersOuLOnPeutEffacer($userId);
        $parEmpreinte = $this->calendriersParEmpreinte($userId);
        $effaces = 0;

        foreach ($orphelins as $orphelin) {
            $ou = (string) ($orphelin['calendrier'] ?? '');
            if (isset($permis[$ou])) {
                $this->effacerLaBas($userId, (string) $orphelin['distant_id'],
                    $parEmpreinte[$ou] ?? 'primary');
                $effaces++;
            }
            Database::run('DELETE FROM agenda_liens WHERE user_id = ? AND fournisseur = ? AND id = ?',
                [$userId, $this->f->cle(), (int) $orphelin['id']]);
        }

        return $effaces;
    }

    /**
     * L'identifiant distant de chaque calendrier, par empreinte.
     *
     * L'empreinte suffit à reconnaître un calendrier ; pour lui parler, il faut
     * l'identifiant que le fournisseur lui a donné.
     *
     * @return array<string, string>
     */
    private function calendriersParEmpreinte(int $userId): array
    {
        $par = [];
        foreach (Database::all(
            'SELECT empreinte, calendrier_id FROM agenda_calendriers
              WHERE user_id = ? AND fournisseur = ?',
            [$userId, $this->f->cle()]
        ) as $ligne) {
            $par[(string) $ligne['empreinte']] = (string) $ligne['calendrier_id'];
        }

        return $par;
    }

    /**
     * Les calendriers où l'application s'autorise à supprimer.
     *
     * @return array<string, true>  par empreinte
     */
    private function calendriersOuLOnPeutEffacer(int $userId): array
    {
        // Le calendrier principal, y compris quand on ne connaît pas encore la
        // liste et qu'on lit celui que le fournisseur donne d'office.
        $permis = [self::DEFAUT => true];

        foreach (Database::all(
            'SELECT empreinte FROM agenda_calendriers
              WHERE user_id = ? AND fournisseur = ? AND principal = 1 AND partage = 0',
            [$userId, $this->f->cle()]
        ) as $ligne) {
            $permis[(string) $ligne['empreinte']] = true;
        }

        $ecriture = EnvoiAgenda::pour($this->f)->calendrierConnu($userId);
        if ($ecriture !== null) {
            $permis[md5($ecriture)] = true;
        }

        return $permis;
    }

    /**
     * Efface chez le fournisseur, sans s'émouvoir de ce qui n'y est déjà plus.
     *
     * Le calendrier accompagne l'évènement : Microsoft le retrouve sans, Google
     * non, et l'on écrit le même code pour les deux.
     */
    private function effacerLaBas(int $userId, string $distantId, string $calendrierId): void
    {
        $reponse = $this->lien()->appeler($userId, 'DELETE',
            $this->f->cheminDeSuppression($calendrierId, $distantId));

        if ($reponse['code'] < 400 || in_array($reponse['code'], [404, 410], true)) {
            return;
        }

        $dit = (string) ($reponse['corps']['error']['message'] ?? '');

        throw new RuntimeException($this->f->nom() . ' a refusé de supprimer un évènement'
            . ($dit === '' ? '.' : ' : ' . mb_substr($dit, 0, 200)));
    }

    /**
     * Les identifiants des évènements que l'application a écrits dans Outlook.
     *
     * @return array<string, true>
     */
    private function ecritsParNous(int $userId): array
    {
        $par = [];
        foreach (Database::all('SELECT distant_id FROM agenda_envois WHERE user_id = ? AND fournisseur = ?',
            [$userId, $this->f->cle()]) as $ligne) {
            $par[(string) $ligne['distant_id']] = true;
        }

        return $par;
    }

    /** Ce que l'application sait déjà, rangé par identifiant Outlook. */
    private function liens(int $userId): array
    {
        $lignes = Database::all(
            'SELECT distant_id, evenement_id, empreinte, calendrier
               FROM agenda_liens WHERE user_id = ? AND fournisseur = ? AND evenement_id IS NOT NULL',
            [$userId, $this->f->cle()]
        );

        $par = [];
        foreach ($lignes as $ligne) {
            $par[(string) $ligne['distant_id']] = $ligne;
        }

        return $par;
    }

    private function ajouter(
        int $userId,
        string $outlookId,
        array $champs,
        string $empreinte,
        string $calendrier
    ): void
    {
        Database::run(
            'INSERT INTO evenements (user_id, titre, description, lieu, debut, fin, journee_entiere)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId, $champs['titre'], $champs['description'], $champs['lieu'],
                $champs['debut'], $champs['fin'], $champs['journee_entiere'],
            ]
        );
        $evenementId = Database::dernierId();

        Database::run(
            'INSERT INTO agenda_liens (user_id, fournisseur, evenement_id, distant_id, empreinte, calendrier)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE evenement_id = VALUES(evenement_id),
                 empreinte = VALUES(empreinte), calendrier = VALUES(calendrier)',
            [$userId, $this->f->cle(), $evenementId, $outlookId, $empreinte, $calendrier]
        );
    }

    private function mettreAJour(
        int $userId,
        int $evenementId,
        string $outlookId,
        array $champs,
        string $empreinte
    ): void {
        /*
         * Le user_id est répété dans la condition alors que l'identifiant
         * suffirait : c'est la règle de la maison, aucune requête ne franchit
         * la frontière d'un compte, même quand elle vient de nous.
         */
        Database::run(
            'UPDATE evenements
                SET titre = ?, description = ?, lieu = ?, debut = ?, fin = ?, journee_entiere = ?
              WHERE id = ? AND user_id = ?',
            [
                $champs['titre'], $champs['description'], $champs['lieu'],
                $champs['debut'], $champs['fin'], $champs['journee_entiere'],
                $evenementId, $userId,
            ]
        );

        Database::run(
            'UPDATE agenda_liens SET empreinte = ? WHERE user_id = ? AND fournisseur = ? AND distant_id = ?',
            [$empreinte, $userId, $this->f->cle(), $outlookId]
        );
    }

    /**
     * Efface ce qui a disparu d'Outlook — et rien d'autre.
     *
     * Seuls sont retirés les évènements que la synchronisation avait elle-même
     * apportés, et seulement ceux dont la date tombe dans la fenêtre qu'on
     * vient de regarder. Un évènement plus lointain n'a pas été vu ; ce n'est
     * pas une raison pour croire qu'il a été supprimé.
     */
    private function retirerLesDisparus(
        int $userId,
        array $connus,
        array $vus,
        DateTimeImmutable $depuis,
        DateTimeImmutable $jusqua
    ): int {
        $retires = 0;

        foreach ($connus as $outlookId => $lien) {
            if (isset($vus[$outlookId])) {
                continue;
            }

            $dedans = Database::valeur(
                'SELECT id FROM evenements WHERE id = ? AND user_id = ? AND debut BETWEEN ? AND ?',
                [
                    (int) $lien['evenement_id'], $userId,
                    $depuis->format('Y-m-d H:i:s'), $jusqua->format('Y-m-d H:i:s'),
                ]
            );
            if ($dedans === null) {
                continue;
            }

            Database::run('DELETE FROM evenements WHERE id = ? AND user_id = ?',
                [(int) $lien['evenement_id'], $userId]);
            Database::run('DELETE FROM agenda_liens WHERE user_id = ? AND fournisseur = ? AND distant_id = ?',
                [$userId, $this->f->cle(), (string) $outlookId]);
            $retires++;
        }

        return $retires;
    }
}
