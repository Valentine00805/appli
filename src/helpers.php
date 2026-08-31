<?php
declare(strict_types=1);

/** Échappe une valeur pour l'affichage HTML. */
function e(?string $valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Construit une URL absolue à partir d'un chemin interne. */
function url(string $chemin = '', array $params = []): string
{
    $chemin = ltrim($chemin, '/');
    $segments = $chemin === '' ? [] : array_map('rawurlencode', explode('/', $chemin));
    $url = BASE_URL . '/' . implode('/', $segments);
    if ($params !== []) {
        $url .= '?' . http_build_query($params);
    }
    return $url;
}

/** Redirige puis stoppe le script. */
function redirect(string $chemin, array $params = []): never
{
    header('Location: ' . url($chemin, $params));
    exit;
}

/** Récupère une valeur POST (utile pour repeupler les formulaires). */
function post(string $cle, string $defaut = ''): string
{
    $valeur = $_POST[$cle] ?? $defaut;
    return is_string($valeur) ? trim($valeur) : $defaut;
}

/** Récupère un entier depuis POST/GET, ou null. */
function entier_ou_null(mixed $valeur): ?int
{
    if ($valeur === null || $valeur === '' || !is_numeric($valeur)) {
        return null;
    }
    return (int) $valeur;
}

/** Formate une taille en octets de façon lisible. */
function taille_lisible(int $octets): string
{
    $unites = ['o', 'Ko', 'Mo', 'Go'];
    $i = 0;
    $taille = (float) $octets;
    while ($taille >= 1024 && $i < count($unites) - 1) {
        $taille /= 1024;
        $i++;
    }
    return ($i === 0 ? (string) (int) $taille : number_format($taille, 1, ',', ' ')) . ' ' . $unites[$i];
}

/** Noms français des mois et des jours. */
function nom_mois(int $mois): string
{
    return [1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
        'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'][$mois] ?? '';
}

function jours_semaine(): array
{
    return ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
}

/** Affiche une date/heure au format français. */
function date_fr(string $datetime, bool $avecHeure = true): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    $jour = date('j', $ts);
    $mois = strtolower(nom_mois((int) date('n', $ts)));
    $annee = date('Y', $ts);
    $texte = "$jour $mois $annee";
    if ($avecHeure) {
        $texte .= ' à ' . date('H\hi', $ts);
    }
    return $texte;
}

/** Libellés et couleurs des types d'évènement. */
function types_evenement(): array
{
    return [
        'cours'    => ['libelle' => 'Cours',       'icone' => '📘'],
        'examen'   => ['libelle' => 'Examen',      'icone' => '📝'],
        'devoir'   => ['libelle' => 'Devoir',      'icone' => '🗂️'],
        'revision' => ['libelle' => 'Révision',    'icone' => '🔁'],
        'autre'    => ['libelle' => 'Autre',       'icone' => '📌'],
    ];
}

/** Contraste : renvoie du texte noir ou blanc selon la couleur de fond. */
function couleur_texte(string $hex): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) {
        return '#ffffff';
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    return $luminance > 0.6 ? '#111827' : '#ffffff';
}

/** Extrait un résumé texte d'un contenu de cours. */
function extrait(?string $texte, int $longueur = 160): string
{
    $texte = trim(preg_replace('/\s+/u', ' ', (string) $texte) ?? '');
    if ($texte === '') {
        return '';
    }
    if (mb_strlen($texte) <= $longueur) {
        return $texte;
    }
    return mb_substr($texte, 0, $longueur) . '…';
}

/** Surligne les termes recherchés dans un texte déjà échappé. */
function surligner(string $texteEchappe, array $termes): string
{
    foreach ($termes as $terme) {
        $terme = trim($terme);
        if (mb_strlen($terme) < 2) {
            continue;
        }
        $texteEchappe = preg_replace(
            '/(' . preg_quote(e($terme), '/') . ')/iu',
            '<mark>$1</mark>',
            $texteEchappe
        ) ?? $texteEchappe;
    }
    return $texteEchappe;
}
