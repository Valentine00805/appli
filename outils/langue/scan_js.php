<?php
/**
 * Les phrases françaises qui restent dans un fichier JavaScript.
 *
 * On ne lit que les chaînes littérales, en sautant celles qui ne sont pas du
 * texte : sélecteurs, classes, attributs, adresses, clés déjà traduites.
 */
$fichier = $argv[1] ?? 'assets/js/app.js';
$racine = 'C:/wamp64/www/mon_appli/appli/';
$source = file_get_contents($racine . $fichier);
$lignes = preg_split("/\r\n|\n/", $source);

// Ce qui ressemble à du français : un accent, ou un mot courant.
$frequents = '/(^|[\s,;:!?\'’«»()])(le|la|les|un|une|des|du|de|au|aux|et|ou|en|dans|pour|par|sur|sans|avec'
    . '|est|sont|a|ont|ne|pas|plus|que|qui|quoi|se|sa|son|ses|ce|cet|cette|ces|vous|votre|vos|on|il|elle'
    . '|Aucun|Aucune|Tout|Toute|Rien|Oui|Non|Voir|Cacher|Copier|Copié|Ajouter|Retirer|Modifier|Supprimer'
    . '|Enregistrer|Annuler|Fermer|Ouvrir|Envoyer|Répondre|Épingler|Chargement)([\s,.;:!?\'’«»()]|$)/ui';

$aSauter = [
    // Ce qui n'est pas du texte lu par quelqu'un.
    '/^[a-zA-Z0-9_\-\[\]\.\#\*\s>=,:"\'\(\)\$\/%&+~^\|]+$/',   // sélecteurs, classes, attributs
    '/^(https?:|\/|\.\/|#|data-|aria-|\?|&)/',                  // adresses et attributs
    '/^[\s\p{P}\p{S}\d]*$/u',                                   // rien que ponctuation, symboles, chiffres
];

$trouvees = [];
foreach ($lignes as $rang => $ligne) {
    $numero = $rang + 1;
    $nue = ltrim($ligne);
    // Les commentaires du script ne se lisent pas à l'écran.
    if (str_starts_with($nue, '//') || str_starts_with($nue, '*') || str_starts_with($nue, '/*')) {
        continue;
    }
    // Chaque chaîne littérale de la ligne, simple ou double quote.
    if (!preg_match_all('/(?<!\\\\)([\'"])((?:\\\\.|(?!\1).)*)\1/', $ligne, $m, PREG_SET_ORDER)) {
        continue;
    }
    foreach ($m as $bout) {
        $texte = $bout[2];
        if ($texte === '') {
            continue;
        }
        $passe = false;
        foreach ($aSauter as $motif) {
            if (preg_match($motif, $texte)) {
                $passe = true;
                break;
            }
        }
        if ($passe) {
            continue;
        }
        // Accent, ou tournure française : c'est du texte à traduire.
        if (!preg_match('/[àâäçéèêëîïôöùûüœÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŒ]/u', $texte) && !preg_match($frequents, $texte)) {
            continue;
        }
        $trouvees[] = [$numero, $texte];
    }
}

printf("--- %s : %d phrase(s)\n", $fichier, count($trouvees));
foreach ($trouvees as [$numero, $texte]) {
    printf("%6d  %s\n", $numero, mb_strimwidth($texte, 0, 110, '…'));
}
