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
            . e($image['source'] === ''
                ? t('cours.image_liee')
                : t('cours.image_format_inconnu', [
                    'format' => strtoupper((string) pathinfo($image['source'], PATHINFO_EXTENSION)),
                  ]))
            . '</p>';
        continue;
    }
    $taille = $image['largeur'] === null ? ''
        : ' width="' . (int) $image['largeur'] . '" height="' . (int) $image['hauteur'] . '"';
    $html .= '<figure class="document-image"><img src="'
        . e(url('fichiers/' . $fichier['id'] . '/image', ['n' => (int) $image['rang']]))
        . '" alt="' . e($image['alt'] !== '' ? $image['alt'] : t('cours.image_du_document')) . '"'
        . $taille . ' loading="lazy" decoding="async"></figure>';
}

echo $html;
