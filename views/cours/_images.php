<?php
/**
 * Les images d'un paragraphe, rendues à leur place.
 *
 * Partagé par l'aperçu et l'éditeur : les deux pages montrent les mêmes
 * images de la même façon, et une seule version évite qu'elles finissent par
 * diverger.
 *
 * Elles ne sont pas recopiées : l'adresse va les relire dans le fichier
 * déposé. « loading="lazy" » évite de les demander toutes d'un coup — un
 * cours de trente captures ne doit pas peser trente fois au premier écran.
 *
 * @var array $fichier
 * @var list<array{rang: int, source: string, type: ?string, alt: string,
 *                 largeur: ?int, hauteur: ?int}> $images
 */
$html = '';
foreach ($images as $image) {
    if ($image['type'] === null) {
        /*
         * Une image qu'on ne peut pas montrer : soit un vieux format de Word
         * — EMF, WMF — qu'aucun navigateur ne dessine, soit une image
         * seulement liée, restée sur l'ordinateur de qui a écrit le document.
         * Le dire vaut mieux qu'un trou muet.
         */
        $html .= '<p class="document-image__absente">'
            . ($image['source'] === ''
                ? 'Une image liée : le document ne la contient pas, elle est restée'
                  . ' sur l’ordinateur où il a été écrit.'
                : 'Une image dans un format que le navigateur n’affiche pas ('
                  . e(strtoupper((string) pathinfo($image['source'], PATHINFO_EXTENSION)))
                  . '). Elle reste dans le fichier, à ouvrir dans Word.')
            . '</p>';
        continue;
    }
    $taille = $image['largeur'] === null ? ''
        : ' width="' . (int) $image['largeur'] . '" height="' . (int) $image['hauteur'] . '"';
    $html .= '<figure class="document-image"><img src="'
        . e(url('fichiers/' . $fichier['id'] . '/image', ['n' => (int) $image['rang']]))
        . '" alt="' . e($image['alt'] !== '' ? $image['alt'] : 'Image du document') . '"'
        . $taille . ' loading="lazy" decoding="async"></figure>';
}

echo $html;
