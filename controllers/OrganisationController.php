<?php
declare(strict_types=1);

/**
 * Section « Organisation » : matières, types d'évènement et tags.
 * Ce contrôleur ne fait qu'aiguiller ; chaque onglet est servi par son
 * propre contrôleur.
 */
final class OrganisationController
{
    /** Onglet affiché par défaut. */
    private const ONGLET_PAR_DEFAUT = 'matieres';

    public function index(): void
    {
        Auth::exiger();
        redirect('organisation/' . self::ONGLET_PAR_DEFAUT);
    }

    /**
     * Les adresses d'avant le regroupement (/matieres, /types, /tags)
     * renvoient vers l'onglet correspondant, paramètres compris.
     */
    public function ancienneAdresse(): void
    {
        Auth::exiger();

        $onglet = in_array(ROUTE, ['matieres', 'types', 'tags'], true)
            ? ROUTE
            : self::ONGLET_PAR_DEFAUT;

        redirect('organisation/' . $onglet, $_GET);
    }
}
