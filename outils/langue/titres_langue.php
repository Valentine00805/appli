<?php
/*
 * Le titre de chaque page, dans les quatre langues.
 *
 * Un titre est le texte qu'aucun scanner ne regarde vraiment : une seule chaîne passée à un
 * contrôleur, souvent un mot sans accent (« Accueil », « Cartes », « Tableau », « Recherche »).
 * Quatre sont restés en français dans toutes les langues, sans que rien ne le dise.
 *
 * On ouvre donc chaque page dans chaque langue, et un titre identique au français est une anomalie
 * — sauf quand il s'écrit vraiment pareil.
 */
require __DIR__ . '/base.php';

$anomalies = 0;
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-44s %s%s\n", $bon ? '✓' : '✗', $quoi, $obtenu, $bon ? '' : "\n       attendu : " . $attendu);
};

$pages = ['', 'calendrier', 'calendrier?vue=liste', 'calendrier?vue=semaine', 'calendrier?vue=jour',
    'calendrier?vue=annee', 'cours', 'cours/nouveau', 'revision', 'taches', 'taches/nouvelle', 'cartes',
    'cartes/seance', 'tableau', 'focus', 'travaux', 'travaux/nouveau', 'alternance', 'alternance/entreprise',
    'alternance/rythme', 'alternance/journal', 'alternance/documents', 'alternance/notes/nouvelle', 'amis',
    'groupes/nouveau', 'partages', 'partages/envoyes', 'budget', 'budget/previsions', 'budget/categories',
    'budget/remboursements', 'budget/import', 'budget/personnes', 'organisation', 'organisation/matieres',
    'organisation/types', 'organisation/tags', 'organisation/dossiers', 'compte', 'compte/sauvegarde',
    'notifications', 'agenda', 'evenements/nouveau', 'recherche?q=alg'];
// Ce qui s'écrit pareil dans une autre langue : « Notifications » est aussi de l'anglais.
$pareils = ['notifications' => ['en']];

$mdp = 'MotDePasse!2026';
$email = 'tit-a@exemple-test.fr';
bd_run('DELETE FROM users WHERE email = ?', [$email]);
bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
    [$email, 'Tit_essai', password_hash($mdp, PASSWORD_DEFAULT), 'Essai titres']);
$id = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$email]);

$ck = __DIR__ . '/ck_tit.txt';
@unlink($ck);
$appel = static function (string $chemin, ?array $post = null) use ($ck): string {
    usleep(150000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $ck, CURLOPT_COOKIEFILE => $ck]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    return (string) curl_exec($h);
};
$jeton = static fn (string $h): string => preg_match('/name="_csrf" value="([^"]+)"/', $h, $m) === 1 ? $m[1] : '';
$titre = static fn (string $h): string => preg_match('/<title>([^<]*)<\/title>/', $h, $m) === 1
    ? trim(html_entity_decode((string) preg_replace('/\s*·\s*Mes Cours\s*$/u', '', $m[1]))) : '(aucun titre)';

try {
    $appel('connexion', ['_csrf' => $jeton($appel('connexion')), 'identifiant' => $email, 'mot_de_passe' => $mdp]);
    $csrf = $jeton($appel('compte'));

    $titres = [];
    foreach (['fr', 'en', 'es', 'de'] as $langue) {
        $appel('compte/langue', ['_csrf' => $csrf, 'langue' => $langue]);
        foreach ($pages as $page) {
            $titres[$page][$langue] = $titre($appel($page));
        }
    }

    foreach (['en', 'es', 'de'] as $langue) {
        echo "\n" . $langue . " : " . count($pages) . " pages\n";
        $memes = [];
        foreach ($pages as $page) {
            $pareil = $titres[$page][$langue] === $titres[$page]['fr'];
            if ($pareil && !in_array($langue, $pareils[$page] ?? [], true)) {
                $memes[] = ($page === '' ? '/' : $page) . ' (« ' . $titres[$page]['fr'] . ' »)';
            }
        }
        $dire('titres restés en français', $memes === [] ? 'aucun' : implode(', ', $memes), 'aucun');
        $vides = array_filter($pages, static fn (string $p): bool => $titres[$p][$langue] === '(aucun titre)');
        $dire('pages sans titre', $vides === [] ? 'aucune' : implode(', ', $vides), 'aucune');
    }

    echo "\nLes quatre titres qu'aucun scanner ne voyait\n";
    $dire('accueil : « Home » / « Inicio » / « Start »', $titres['']['en'] . ' / ' . $titres['']['es'] . ' / ' . $titres['']['de'], 'Home / Inicio / Start');
    $dire('cartes : « Flashcards »', $titres['cartes']['en'], 'Flashcards');
    $dire('tableau : « Board »', $titres['tableau']['en'], 'Board');
    $dire('recherche : « Search »', $titres['recherche?q=alg']['en'], 'Search');
    $dire('budget, en espagnol : « Presupuesto — … »', (string) str_starts_with($titres['budget']['es'], 'Presupuesto — '), '1');
} finally {
    // Ménage : ce compte d'essai seul, et ce qu'il a semé.
    if ($id > 0) {
        foreach (['cours', 'matieres', 'types_evenement', 'categories_budget', 'dossiers'] as $table) {
            bd_run("DELETE FROM $table WHERE user_id = ?", [$id]);
        }
        bd_run('DELETE FROM users WHERE id = ? AND email = ?', [$id, $email]);
    }
    @unlink($ck);
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . (int) bd_valeur('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) . "\n";
}
