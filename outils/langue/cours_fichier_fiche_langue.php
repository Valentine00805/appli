<?php
/**
 * La fiche de révision d'un « simple » cours importé — un fichier gardé comme tel (HTTP + base) : un bouton « 📝 Fiche de révision » sur sa carte et dans son
 * aperçu, la fiche qui s'ouvre et s'enregistre sans que le fichier devienne un cours (même titre, pas de contenu, toujours « seulement fichier »), « Voir le
 * fichier » à la place de « Voir le cours », la fiche dans la liste des fiches, chacun la sienne (404 pour les autres), et les quatre langues.
 *
 * Deux comptes d'essai : A (le propriétaire), B (un autre compte).
 */
require __DIR__ . '/base.php';

date_default_timezone_set('Europe/Paris');

$anomalies = 0;
$termine = false;   // faux si le script s'arrête en route (erreur fatale) : le bilan ne doit pas dire « aucune anomalie »
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-76s %s%s\n", $bon ? '✓' : '✗', $quoi, $bon ? $obtenu : substr($obtenu, 0, 110), $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$emails = ['cff-a@exemple-test.fr', 'cff-b@exemple-test.fr'];
$nettoyer = static function () use ($emails): void {
    foreach ($emails as $e) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']); }
};
$nettoyer();
foreach ($emails as $i => $e) {
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)', [$e, 'Cff_' . $i, password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai fiche']);
}
[$idA, $idB] = array_map(static fn (string $e): int => (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$e]), $emails);

$creer = static function (int $u, string $titre, bool $seul, string $nomFichier, string $mime, ?string $contenu = null): array {
    bd_run('INSERT INTO cours (user_id, titre, contenu, est_fichier) VALUES (?, ?, ?, ?)', [$u, $titre, $contenu, $seul ? 1 : 0]);
    $c = (int) bd_valeur('SELECT MAX(id) FROM cours WHERE user_id = ?', [$u]);
    bd_run('INSERT INTO fichiers (user_id, cours_id, nom_origine, nom_stocke, mime, taille) VALUES (?, ?, ?, ?, ?, ?)', [$u, $c, $nomFichier, 'zz_cff_' . $c . '.bin', $mime, 1150000]);
    return [$c, (int) bd_valeur('SELECT id FROM fichiers WHERE cours_id = ?', [$c])];
};
[$seul, $fichier] = $creer($idA, 'Cours_Architecture_logicielle.pdf', true, 'Cours_Architecture_logicielle.pdf', 'application/pdf');
[$archive, $fichierArchive] = $creer($idA, 'sources.zip', true, 'sources.zip', 'application/zip');
[$ordinaire] = $creer($idA, 'Un cours ordinaire', false, 'annexe.pdf', 'application/pdf', 'Du texte.');
[$seulB] = $creer($idB, 'Fichier de B.pdf', true, 'Fichier de B.pdf', 'application/pdf');

$cookies = [$emails[0] => __DIR__ . '/ck_cff_a.txt', $emails[1] => __DIR__ . '/ck_cff_b.txt'];
foreach ($cookies as $f) { @unlink($f); }
/** @return array{0: string, 1: int, 2: string} corps, code, adresse finale */
$appel = static function (string $compte, string $chemin, ?array $post = null) use ($cookies): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $cookies[$compte], CURLOPT_COOKIEFILE => $cookies[$compte]]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    $r = [$corps, (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
$ligne = static fn (int $c): array => bd_all('SELECT fiche_revision, titre, contenu, est_fichier, dossier_id, matiere_id FROM cours WHERE id = ?', [$c])[0] ?? [];
$nbCours = static fn (int $u): int => (int) bd_valeur('SELECT COUNT(*) FROM cours WHERE user_id = ?', [$u]);

try {
    [$a, $b] = $emails;
    foreach ($emails as $e) {
        [$p] = $appel($e, 'connexion');
        $appel($e, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $e, 'mot_de_passe' => 'MotDePasse!2026']);
    }
    [$page] = $appel($a, 'cours');
    $csrfA = $jeton($page);
    [$pageB] = $appel($b, 'cours');
    $csrfB = $jeton($pageB);
    $avantTout = $nbCours($idA);

    echo "\n1. Les points d'entrée\n";
    $dire('sur la carte du fichier : « 📝 Fiche de révision » (fenêtre), sans « • » tant qu\'il n\'y a pas de fiche',
        $oui(preg_match('~<a class="cours-carte__cours discret" href="[^"]*/revision/' . $seul . '" data-fenetre>📝 Fiche de révision</a>~u', $page) === 1), 'oui');
    $dire('pas de bouton « fiche » ajouté à la carte d\'un cours ordinaire (il a sa fiche dans son cours)', $oui(!str_contains($page, 'revision/' . $ordinaire . '"')), 'oui');
    [$apercu] = $appel($a, 'fichiers/' . $fichier . '/apercu');
    [$apercuFenetre] = $appel($a, 'fichiers/' . $fichier . '/apercu?fenetre=1');
    $dire('dans l\'aperçu : le bouton « 📝 Fiche de révision », qui s\'ouvre dans la même fenêtre (avec retour possible)',
        $oui(preg_match('~<a class="bouton bouton--secondaire" href="[^"]*/revision/' . $seul . '">\s*📝 Fiche de révision\s*</a>~u', $apercu) === 1 && preg_match('~/revision/' . $seul . '" data-fenetre>\s*📝 Fiche de révision~u', $apercuFenetre) === 1), 'oui');
    [$annexe] = $appel($a, 'fichiers/' . (int) bd_valeur('SELECT id FROM fichiers WHERE cours_id = ?', [$ordinaire]) . '/apercu');
    $dire('la pièce jointe d\'un cours ordinaire n\'a pas ce bouton', $oui(!str_contains($annexe, 'revision/' . $ordinaire)), 'oui');

    echo "\n2. La fiche, ouverte depuis le fichier\n";
    [$fiche, $codeFiche] = $appel($a, 'revision/' . $seul . '?fenetre=1');
    $dire('elle s\'ouvre : le nom du fichier avec son icône, « Fiche de révision », le champ du texte',
        $codeFiche . ' · ' . $oui(str_contains($fiche, '📕 Cours_Architecture_logicielle.pdf') && str_contains($fiche, 'Fiche de révision') && str_contains($fiche, 'name="fiche_revision"') && !str_contains($fiche, '<html')), '200 · oui');
    $dire('« Voir le fichier » (l\'aperçu) à la place de « Voir le cours »', $oui(preg_match('~<a class="bouton bouton--secondaire" href="[^"]*/fichiers/' . $fichier . '/apercu"\s+data-fenetre>\s*Voir le fichier~u', $fiche) === 1 && !str_contains($fiche, 'Voir le cours')), 'oui');
    [$ficheArchive] = $appel($a, 'revision/' . $archive . '?fenetre=1');
    $dire('un fichier sans aperçu (une archive) : « Voir le fichier » mène au fichier lui-même, dans un onglet',
        $oui(preg_match('~href="[^"]*/fichiers/' . $fichierArchive . '"\s+target="_blank" rel="noopener">\s*Voir le fichier~u', $ficheArchive) === 1 && !str_contains($ficheArchive, '/fichiers/' . $fichierArchive . '/apercu')), 'oui');
    [$ficheOrdinaire] = $appel($a, 'revision/' . $ordinaire . '?fenetre=1');
    $dire('la fiche d\'un cours ordinaire est inchangée : « Voir le cours »', $oui(str_contains($ficheOrdinaire, 'Voir le cours') && !str_contains($ficheOrdinaire, 'Voir le fichier')), 'oui');

    echo "\n3. Écrire la fiche\n";
    [, , $adresse] = $appel($a, 'cours/' . $seul . '/revision', ['_csrf' => $csrfA, 'page' => 'fiche', 'fiche_revision' => 'À retenir : ports et adaptateurs.']);
    $l = $ligne($seul);
    $dire('la fiche est enregistrée, et l\'on reste sur la page de la fiche', $oui(str_contains((string) $l['fiche_revision'], 'ports et adaptateurs')) . ' · ' . $oui(str_ends_with($adresse, '/revision/' . $seul)), 'oui · oui');
    $dire('le fichier reste un fichier : même titre, sans contenu, toujours « seulement fichier », même matière et même dossier',
        $l['titre'] . ' · ' . var_export($l['contenu'], true) . ' · ' . $l['est_fichier'] . ' · ' . var_export($l['matiere_id'], true) . ' · ' . var_export($l['dossier_id'], true), 'Cours_Architecture_logicielle.pdf · NULL · 1 · NULL · NULL');
    $dire('et rien n\'a été créé : les mêmes cours qu\'avant', (string) $nbCours($idA), (string) $avantTout);
    [$page2] = $appel($a, 'cours');
    [$apercu2] = $appel($a, 'fichiers/' . $fichier . '/apercu');
    $dire('la carte et l\'aperçu montrent « • » (la fiche existe), et la carte reste celle d\'un fichier (« Ouvrir comme un cours » toujours là)',
        $oui(str_contains($page2, '📝 Fiche de révision •') && str_contains($apercu2, '📝 Fiche de révision •') && str_contains($page2, 'cours-carte--fichier') && str_contains($page2, 'Ouvrir comme un cours')), 'oui');
    [$ficheApres] = $appel($a, 'revision/' . $seul . '?fenetre=1');
    $dire('rouverte, la fiche montre le texte écrit', $oui(str_contains($ficheApres, 'ports et adaptateurs')), 'oui');
    [$liste] = $appel($a, 'revision');
    $dire('et elle paraît dans la liste des fiches de révision, sous le nom du fichier', $oui(str_contains($liste, 'Cours_Architecture_logicielle.pdf') && str_contains($liste, 'revision/' . $seul)), 'oui');
    $appel($a, 'cours/' . $seul . '/revision', ['_csrf' => $csrfA, 'page' => 'fiche', 'fiche_revision' => '']);
    $dire('vidée, la fiche redevient absente (plus de « • »)', var_export($ligne($seul)['fiche_revision'], true), 'NULL');

    echo "\n4. Chacun la sienne\n";
    [, $codeB] = $appel($b, 'revision/' . $seul);
    [, $codeBp] = $appel($b, 'cours/' . $seul . '/revision', ['_csrf' => $csrfB, 'fiche_revision' => 'Piraté']);
    $dire('B n\'ouvre ni n\'écrit la fiche du fichier de A (404)', $codeB . ' · ' . $codeBp . ' · ' . var_export($ligne($seul)['fiche_revision'], true), '404 · 404 · NULL');
    [, $codeSans] = $appel($a, 'cours/' . $seul . '/revision', ['page' => 'fiche', 'fiche_revision' => 'Sans jeton']);
    $dire('sans le jeton de sécurité (CSRF), rien n\'est enregistré (400)', $codeSans . ' · ' . var_export($ligne($seul)['fiche_revision'], true), '400 · NULL');

    echo "\n5. Les quatre langues\n";
    [$pCompte] = $appel($a, 'compte');
    $tokenCompte = $jeton($pCompte);
    foreach (['en' => ['Revision sheet', 'See the file'], 'es' => ['Ficha de repaso', 'Ver el archivo'], 'de' => ['Lernblatt', 'Datei ansehen'], 'fr' => ['Fiche de révision', 'Voir le fichier']] as $langue => [$fiche1, $voir]) {
        $appel($a, 'compte/langue', ['_csrf' => $tokenCompte, 'langue' => $langue]);
        [$liste] = $appel($a, 'cours');
        [$ap] = $appel($a, 'fichiers/' . $fichier . '/apercu');
        [$fi] = $appel($a, 'revision/' . $seul . '?fenetre=1');
        $tout = html_entity_decode($liste . $ap . $fi, ENT_QUOTES, 'UTF-8');
        $manque = array_values(array_filter(['📝 ' . $fiche1, $voir], static fn (string $mot): bool => !str_contains($tout, $mot)));
        $dire($langue . ' : « 📝 ' . $fiche1 . ' » (carte, aperçu) et « ' . $voir . ' »', $manque === [] ? 'tout y est' : 'manque : ' . implode(' | ', $manque), 'tout y est');
        $dire($langue . ' : pas de clé brute', $oui(!preg_match('/\b(cours\.ff|fiche\.voir)_?[a-z_.]*/', $tout)), 'oui');
    }
    foreach (['fr', 'en', 'es', 'de'] as $langue) {
        $texte = (string) file_get_contents(dirname(__DIR__, 2) . '/lang/' . $langue . '.php');
        preg_match_all("/^    '([^']+)' =>/m", $texte, $cles);
        $doubles = array_keys(array_filter(array_count_values($cles[1]), static fn (int $n): bool => $n > 1));
        $dire($langue . ' : fichier de langue en UTF-8 valide, sans clé écrite deux fois', $oui(mb_check_encoding($texte, 'UTF-8')) . ' · ' . ($doubles === [] ? 'aucune' : implode(', ', array_slice($doubles, 0, 5))), 'oui · aucune');
    }
    $termine = true;
} finally {
    $nettoyer();
    foreach ($cookies as $f) { @unlink($f); }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'cff-_@exemple-test.fr'")
        . ' · fichiers d’essai restants ' . bd_valeur("SELECT COUNT(*) FROM fichiers WHERE nom_stocke LIKE 'zz\\_cff\\_%'") . "\n";
}
