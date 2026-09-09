<?php
declare(strict_types=1);

/**
 * Ce qui distingue un agenda distant d'un autre.
 *
 * Tout le reste — la fenêtre regardée, le calendrier d'écriture, les liens, les
 * empreintes, les suppressions, le volet, les couleurs — est commun et vit dans
 * SynchroAgenda et EnvoiAgenda. Ici ne se trouvent que les endroits où Microsoft
 * et Google ne disent pas la même chose : les adresses, la forme des dates, la
 * pagination, la façon de nommer une journée entière.
 *
 * Écrire un troisième agenda demanderait d'écrire une classe de plus, et rien
 * d'autre. C'était l'objet de la refonte.
 */
abstract class Fournisseur
{
    /** Le nom retenu en base, jamais montré. */
    abstract public function cle(): string;

    /** Le nom montré à l'écran. */
    abstract public function nom(): string;

    /* --- L'autorisation ---------------------------------------------------- */

    abstract public function urlAutorisation(): string;

    abstract public function urlJetons(): string;

    /**
     * Le chemin où le fournisseur renvoie le navigateur.
     *
     * Il est déclaré chez lui au mot près : le changer obligerait chacun à
     * retoucher son inscription. Microsoft garde donc celui d'avant la
     * refonte, quand les agendas n'étaient qu'Outlook.
     */
    abstract public function cheminDeRetour(): string;

    /** Ce que l'application demande à la personne d'accorder. */
    abstract public function permissions(): string;

    /**
     * Ce qu'on ajoute à la demande d'autorisation, propre à chacun.
     *
     * Microsoft veut qu'on choisisse son compte ; Google veut qu'on dise
     * explicitement vouloir revenir plus tard, sans quoi il ne donne pas de
     * jeton de renouvellement.
     *
     * @return array<string, string>
     */
    abstract public function parametresDAutorisation(): array;

    /**
     * Le secret est-il indispensable ?
     *
     * Microsoft s'en passe en client public, PKCE suffit. Google l'exige pour
     * un identifiant « application web », même avec PKCE.
     */
    abstract public function secretObligatoire(): bool;

    /* --- Parler à l'API ---------------------------------------------------- */

    abstract public function racineApi(): string;

    /** Qui est relié : adresse du compte, nom du calendrier principal. */
    abstract public function identite(int $userId): array;

    /* --- Lire les calendriers ---------------------------------------------- */

    /**
     * Les chemins où trouver les calendriers du compte.
     *
     * @return array<int, string>
     */
    abstract public function cheminsDesCalendriers(int $userId): array;

    /**
     * Un calendrier distant dans les termes de l'application.
     *
     * « ecriture » dit si le compte a le droit d'y ajouter quelque chose. Un
     * agenda partagé en lecture et un agenda partagé en modification se
     * ressemblent tant qu'on ne l'a pas demandé, et proposer de déposer là où
     * le dépôt sera refusé est la pire des deux erreurs.
     *
     * @return ?array{id: string, nom: string, proprietaire: string,
     *                adresse: string, principal: bool, ecriture: bool}
     */
    abstract public function lireCalendrier(array $brut): ?array;

    /* --- Lire les évènements ----------------------------------------------- */

    /**
     * Le chemin des occurrences d'un calendrier sur une période.
     *
     * @param ?string $calendrierId  null pour le calendrier principal
     */
    abstract public function cheminDesEvenements(
        ?string $calendrierId,
        DateTimeImmutable $depuis,
        DateTimeImmutable $jusqua,
        int $parPage,
        string $fuseau
    ): string;

    /**
     * Les en-têtes propres à la lecture, s'il en faut.
     *
     * @return array<int, string>
     */
    abstract public function entetesDeLecture(string $fuseau): array;

    /**
     * Les éléments d'une liste, là où ce fournisseur les range.
     *
     * Microsoft dit « value », Google dit « items ». Chercher l'un chez
     * l'autre ne lève aucune erreur : on obtient une liste vide, et un agenda
     * qui semble n'avoir ni calendrier ni rendez-vous.
     *
     * @return array<int, array>
     */
    abstract public function elements(array $corps): array;

    /** La page suivante, ou une chaîne vide s'il n'y en a plus. */
    abstract public function pageSuivante(array $corps, string $cheminPrecedent): string;

    /**
     * Un évènement distant dans les termes de l'application.
     *
     * @return ?array{titre: string, description: ?string, lieu: ?string,
     *                debut: string, fin: string, journee_entiere: int}
     *         null si l'évènement n'a pas sa place ici — annulé, illisible
     */
    abstract public function lireEvenement(array $brut, DateTimeZone $fuseau): ?array;

    /* --- Écrire ------------------------------------------------------------ */

    /** Le corps d'un évènement, tel que l'agenda l'attend. */
    abstract public function corpsDUnEvenement(
        string $titre,
        string $texte,
        string $lieu,
        DateTimeImmutable $debut,
        DateTimeImmutable $fin,
        bool $journee,
        string $fuseau
    ): array;

    abstract public function cheminDeCreation(string $calendrierId): string;

    abstract public function cheminDeModification(string $calendrierId, string $evenementId): string;

    abstract public function cheminDeSuppression(string $calendrierId, string $evenementId): string;

    /** Où lister les calendriers pour retrouver « Mes Cours », et où le créer. */
    abstract public function cheminDeListeSimple(): string;

    /** @return array{0: string, 1: array} le chemin et le corps de la création */
    abstract public function creationDeCalendrier(string $nom): array;

    /** L'identifiant rendu après création d'un calendrier. */
    abstract public function idDuCalendrierCree(array $corps): string;
}
