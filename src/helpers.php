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

/**
 * Types d'évènement proposés à la création d'un compte.
 * Ils sont ensuite entièrement modifiables depuis la page « Types ».
 */
function types_evenement_par_defaut(): array
{
    return [
        ['nom' => 'Cours',    'icone' => '📘', 'couleur' => '#4f46e5', 'est_echeance' => 0],
        ['nom' => 'Examen',   'icone' => '📝', 'couleur' => '#dc2626', 'est_echeance' => 1],
        ['nom' => 'Devoir',   'icone' => '🗂️', 'couleur' => '#ea580c', 'est_echeance' => 1],
        ['nom' => 'Révision', 'icone' => '🔁', 'couleur' => '#059669', 'est_echeance' => 0],
        ['nom' => 'Autre',    'icone' => '📌', 'couleur' => '#64748b', 'est_echeance' => 0],
    ];
}

/** Icône d'un évènement, avec repli si son type a été supprimé. */
function icone_evenement(array $evt): string
{
    return (string) ($evt['type_icone'] ?? '') !== '' ? (string) $evt['type_icone'] : '📌';
}

/** Libellé du type d'un évènement, avec repli si le type a été supprimé. */
function libelle_type(array $evt): string
{
    return (string) ($evt['type_nom'] ?? '') !== '' ? (string) $evt['type_nom'] : 'Sans type';
}

/**
 * Couleur d'un évènement : celle de sa matière en priorité,
 * sinon celle de son type, sinon un gris neutre.
 */
function couleur_evenement(array $evt): string
{
    foreach (['matiere_couleur', 'type_couleur'] as $cle) {
        $couleur = (string) ($evt[$cle] ?? '');
        if ($couleur !== '') {
            return $couleur;
        }
    }
    return '#94a3b8';
}

/** Emoji proposés dans le sélecteur d'icône d'un type. */
function icones_proposees(): array
{
    return ['📘', '📝', '🗂️', '🔁', '📌', '🧪', '🎓', '✏️', '📚', '🧮', '🗣️', '🎨',
        '🎵', '⚽', '💻', '🔬', '🌍', '⏰', '⭐', '🚀', '📊', '🧠', '📅', '🏫'];
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
