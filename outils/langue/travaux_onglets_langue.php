<?php
/**
 * Les onglets d'un travail de groupe, au choix du groupe (HTTP + base) : le choix à la création du projet (« Qui fait quoi » et « Membres » toujours là, les
 * autres cochés d'office), ce que ça devient (les onglets affichés, les pages des autres en 404, un groupe qui n'a pas « Cours » ou « Fichiers » n'en reçoit
 * pas), le lien public qui ne montre que ce que le groupe a gardé, le changement ensuite (l'administrateur seulement), ce qui est gardé quand un onglet est
 * retiré puis remis, un site dont la migration n'est pas passée, et les quatre langues.
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

$mdp = 'MotDePasse!2026';
$comptes = [
    'a' => ['email' => 'tong-a@exemple-test.fr', 'pseudo' => 'Tong_Admin'],
    'b' => ['email' => 'tong-b@exemple-test.fr', 'pseudo' => 'Tong_Membre'],
];
foreach ($comptes as $c => $d) {
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$d['email'], '%@exemple-test.fr']);
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)', [$d['email'], $d['pseudo'], password_hash($mdp, PASSWORD_DEFAULT), 'Essai ' . $c]);
    $comptes[$c]['id'] = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$d['email']]);
}
$id = static fn (string $c): int => $comptes[$c]['id'];

$jar = static fn (string $nom): string => __DIR__ . '/ck_tong_' . $nom . '.txt';
$appel = static function (string $navigateur, string $chemin, ?array $post = null, string $langue = '') use ($jar): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120,
        CURLOPT_COOKIEJAR => $jar($navigateur), CURLOPT_COOKIEFILE => $jar($navigateur)]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    $r = [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), $corps, (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
/** Les onglets d'un projet, vus par la barre d'onglets de sa page (leurs noms, dans l'ordre). */
$barre = static function (string $html): string {
    if (preg_match('#<nav class="onglets".*?</nav>#s', $html, $nav) !== 1) { return '-'; }
    preg_match_all('#<span aria-hidden="true">[^<]*</span>\s*([^<]+?)\s*</a>#u', $nav[0], $m);
    return implode(' | ', $m[1]);
};

$projets = [];
$colonneRenommee = false;
try {
    foreach (['a', 'b'] as $c) {
        [, $connexion] = $appel($c, 'connexion');
        $appel($c, 'connexion', ['_csrf' => $jeton($connexion), 'identifiant' => $comptes[$c]['email'], 'mot_de_passe' => $mdp]);
    }
    $csrf = static fn (string $nav, string $chemin): string => $jeton($GLOBALS['appel']($nav, $chemin)[1]);
    $projetNomme = static fn (string $nom): int => (int) bd_valeur('SELECT id FROM projets WHERE nom = ? AND cree_par = ? ORDER BY id DESC LIMIT 1', [$nom, $GLOBALS['comptes']['a']['id']]);
    /** Crée un projet par le formulaire (a l'administrateur, b y est membre) ; $onglets : null = pas de choix, sinon les clés cochées. */
    $creer = static function (string $nom, ?array $onglets) use ($appel, $csrf, $id, &$projets, $projetNomme): int {
        $post = ['_csrf' => $csrf('a', 'travaux/nouveau'), 'nom' => $nom, 'description' => ''];
        if ($onglets !== null) { $post['onglets_choisis'] = '1'; if ($onglets !== []) { $post['onglets'] = $onglets; } }
        $appel('a', 'travaux', $post);
        $p = $projetNomme($nom);
        $projets[] = $p;
        bd_run("INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, 'membre', 'membre')", [$p, $id('b')]);
        return $p;
    };
    $onglets = static fn (int $p): string => (string) (bd_valeur('SELECT COALESCE(onglets, \'(tous)\') FROM projets WHERE id = ?', [$p]));

    echo "\n1. Le choix, à la création du projet\n";
    [$cod, $form] = $appel('a', 'travaux/nouveau');
    $dire('le formulaire : « Onglets du projet », « Qui fait quoi » et « Membres » (« Toujours là », grisés), six onglets au choix tous cochés',
        $cod . ' · ' . $oui(str_contains($form, 'Onglets du projet') && substr_count($form, 'Toujours là') === 2 && substr_count($form, 'checked disabled') === 2
            && preg_match_all('#<input type="checkbox" id="onglet-nouveau-\w+" name="onglets\[\]" value="(?:echeances|fichiers|cours|document|livrables|listes)" checked>#', $form) === 6
            && str_contains($form, 'name="onglets_choisis" value="1"')), '200 · oui');
    $dire('  chacun avec ce à quoi il sert', $oui(str_contains($form, 'Les fichiers que tout le groupe dépose et télécharge.') && str_contains($form, 'Les documents finaux à rendre, avec leur date de rendu.') && str_contains($form, 'Un texte écrit à plusieurs, qu’on peut exporter en PDF.')), 'oui');
    [, $fragment] = $appel('a', 'travaux/nouveau?fenetre=1');
    $dire('ouvert en fenêtre : le même choix, sans la page autour', $oui(!str_contains($fragment, '<html') && str_contains($fragment, 'Onglets du projet') && str_contains($fragment, 'data-envoi-fenetre')), 'oui');

    echo "\n2. Un projet avec seulement certains onglets\n";
    $p1 = $creer('Onglets : fichiers et document (essai)', ['fichiers', 'document']);
    $dire('gardés : « fichiers,document »', $onglets($p1), 'fichiers,document');
    [$cod, $tableau] = $appel('a', 'travaux/' . $p1);
    $dire('la barre d\'onglets : Qui fait quoi, Fichiers, Document commun, Membres', $cod . ' · ' . $barre($tableau), '200 · Qui fait quoi | Fichiers | Document commun | Membres');
    $codes = [];
    foreach (['echeances', 'cours', 'livrables', 'listes', 'livrables/nouveau', 'fichiers', 'document', 'membres'] as $page) { $codes[] = $appel('a', 'travaux/' . $p1 . '/' . $page)[0]; }
    $dire('les pages des onglets retirés (échéances, cours, livrables, listes) : 404 ; celles des onglets gardés, et « Membres » : 200', implode(' ', $codes), '404 404 404 404 404 200 200 200');
    [$cod] = $appel('a', 'travaux/' . $p1 . '/document/pdf');
    $dire('le PDF du document (onglet gardé) répond : « document vide » ramène au document', (string) $cod, '200');
    bd_run('UPDATE projets SET document = ? WHERE id = ?', ['<!--riche--><p>Du texte.</p>', $p1]);
    [, $docSans] = $appel('a', 'travaux/' . $p1 . '/document');
    $dire('sans l\'onglet « Livrables », le document n\'offre pas « Mettre dans les livrables » (mais garde « Exporter en PDF »)',
        $oui(!str_contains($docSans, 'Mettre dans les livrables') && str_contains($docSans, 'Exporter en PDF')), 'oui');
    [, $refusDoc] = $appel('a', 'travaux/' . $p1 . '/document/livrable', ['_csrf' => $csrf('a', 'travaux/' . $p1 . '/membres'), 'livrable' => 'nouveau']);
    $dire('et le mettre dans les livrables par une requête directe est refusé', $oui(str_contains($refusDoc, 'flash--erreur')) . ' · ' . $oui(!Travaux_essai_livrable_existe($p1)), 'oui · oui');

    echo "\n3. Aucun onglet au choix, tous, ou pas de choix\n";
    $p2 = $creer('Onglets : aucun (essai)', []);
    [, $tableau2] = $appel('a', 'travaux/' . $p2);
    $dire('rien de coché : « Qui fait quoi » et « Membres » seulement', ($onglets($p2) === '' ? '(vide)' : $onglets($p2)) . ' · ' . $barre($tableau2), '(vide) · Qui fait quoi | Membres');
    $p3 = $creer('Onglets : tous (essai)', ['echeances', 'fichiers', 'cours', 'document', 'livrables', 'listes']);
    [, $tableau3] = $appel('a', 'travaux/' . $p3);
    $dire('tous cochés : pas de choix gardé (les onglets à venir apparaîtront d\'eux-mêmes), tous les onglets', $onglets($p3) . ' · ' . $barre($tableau3),
        '(tous) · Qui fait quoi | Échéances | Fichiers | Cours | Document commun | Livrables | Listes | Membres');
    $p4 = $creer('Onglets : sans choix (essai)', null);
    $dire('un projet créé sans le champ du choix (ancien formulaire, autre client) : tous les onglets', $onglets($p4), '(tous)');
    $p5 = $creer('Onglets : valeurs absurdes (essai)', ['taches', 'membres', 'n\'importe quoi', 'fichiers']);
    $dire('des valeurs qui ne sont pas des onglets au choix sont ignorées', $onglets($p5), 'fichiers');

    echo "\n4. Les changer ensuite : l'administrateur\n";
    [, $membres] = $appel('a', 'travaux/' . $p1 . '/membres');
    $dire('l\'administrateur voit le choix dans ses réglages (« Onglets du projet »), avec fichiers et document cochés, le reste non',
        $oui(str_contains($membres, 'Onglets du projet') && preg_match('#id="onglet-reglage-fichiers" name="onglets\[\]" value="fichiers" checked>#', $membres) === 1
            && preg_match('#id="onglet-reglage-document" name="onglets\[\]" value="document" checked>#', $membres) === 1
            && preg_match('#id="onglet-reglage-cours" name="onglets\[\]" value="cours">#', $membres) === 1), 'oui');
    [, $membresB] = $appel('b', 'travaux/' . $p1 . '/membres');
    $dire('un simple membre ne le voit pas', $oui(!str_contains($membresB, 'Onglets du projet') && !str_contains($membresB, 'onglet-reglage')), 'oui');
    [, $refus] = $appel('b', 'travaux/' . $p1 . '/onglets', ['_csrf' => $jeton($membresB) ?: $csrf('b', 'compte'), 'onglets' => ['cours', 'livrables']]);
    $dire('et s\'il l\'envoie quand même : « Seul l\'administrateur… », rien ne change', $oui(str_contains($refus, 'Seul l’administrateur du groupe peut choisir les onglets.')) . ' · ' . $onglets($p1), 'oui · fichiers,document');
    $ta = $jeton($membres);
    [, $apres, $adresse] = $appel('a', 'travaux/' . $p1 . '/onglets', ['_csrf' => $ta, 'onglets' => ['cours', 'document', 'livrables']]);
    $dire('l\'administrateur choisit cours, document, livrables : « Onglets enregistrés. », retour aux membres',
        $oui(str_contains($apres, 'Onglets enregistrés.') && str_ends_with($adresse, '/travaux/' . $p1 . '/membres')) . ' · ' . $onglets($p1), 'oui · cours,document,livrables');
    [, $tableau1] = $appel('a', 'travaux/' . $p1);
    $codes = [];
    foreach (['fichiers', 'cours', 'livrables', 'document'] as $page) { $codes[] = $appel('a', 'travaux/' . $p1 . '/' . $page)[0]; }
    $dire('la barre suit : Qui fait quoi, Cours, Document commun, Livrables, Membres ; « Fichiers » devient 404', $barre($tableau1) . ' · ' . implode(' ', $codes), 'Qui fait quoi | Cours | Document commun | Livrables | Membres · 404 200 200 200');
    [, $docAvec] = $appel('a', 'travaux/' . $p1 . '/document');
    $dire('avec « Livrables », le document a « Mettre dans les livrables »', $oui(str_contains($docAvec, 'Mettre dans les livrables')), 'oui');
    $appel('a', 'travaux/' . $p1 . '/onglets', ['_csrf' => $ta, 'onglets' => ['echeances', 'fichiers', 'cours', 'document', 'livrables', 'listes']]);
    $dire('tout recocher : plus de choix gardé, les huit onglets', $onglets($p1) . ' · ' . $barre($appel('a', 'travaux/' . $p1)[1]), '(tous) · Qui fait quoi | Échéances | Fichiers | Cours | Document commun | Livrables | Listes | Membres');

    echo "\n5. Ce qui est gardé quand un onglet est retiré, puis remis\n";
    bd_run('INSERT INTO projet_fichiers (projet_id, user_id, nom_origine, nom_stocke, mime, taille) VALUES (?, ?, ?, ?, ?, 1)', [$p1, $id('a'), 'garde-moi.txt', 'inexistant-onglets', 'text/plain']);
    $appel('a', 'travaux/' . $p1 . '/onglets', ['_csrf' => $ta, 'onglets' => ['document']]);
    $dire('« Fichiers » retiré : la page répond 404', (string) $appel('a', 'travaux/' . $p1 . '/fichiers')[0], '404');
    $appel('a', 'travaux/' . $p1 . '/onglets', ['_csrf' => $ta, 'onglets' => ['document', 'fichiers']]);
    [$cod, $retour] = $appel('a', 'travaux/' . $p1 . '/fichiers');
    $dire('remis : les fichiers déposés avant sont toujours là (rien n\'a été effacé)', $cod . ' · ' . $oui(str_contains($retour, 'garde-moi.txt')) . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_fichiers WHERE projet_id = ?', [$p1]), '200 · oui · 1');

    echo "\n6. Le lien public ne montre que ce que le groupe a gardé\n";
    $lien = bin2hex(random_bytes(16));
    bd_run('UPDATE projets SET jeton = ? WHERE id = ?', [$lien, $p1]);
    bd_run('INSERT INTO calendriers_amis (proprietaire_id, projet_id, nom, couleur) VALUES (?, ?, ?, ?)', [$id('a'), $p1, 'Calendrier du groupe', '#e11d48']);
    $fichierId = (int) bd_valeur('SELECT id FROM projet_fichiers WHERE projet_id = ?', [$p1]);
    $appel('a', 'travaux/' . $p1 . '/onglets', ['_csrf' => $ta, 'onglets' => ['echeances', 'fichiers']]);
    [, $public] = $appel('a', 'g/' . $lien);
    $dire('« Échéances » et « Fichiers » gardés : le calendrier et la liste des fichiers sont sur le lien', $oui(str_contains($public, 'id="calendrier-public"') && str_contains($public, 'garde-moi.txt')), 'oui');
    $appel('a', 'travaux/' . $p1 . '/onglets', ['_csrf' => $ta, 'onglets' => ['document']]);
    [, $public] = $appel('a', 'g/' . $lien);
    [$cod] = $appel('a', 'g/' . $lien . '/fichiers/' . $fichierId);
    $dire('« Échéances » et « Fichiers » retirés : plus de calendrier, plus de fichiers sur le lien, et l\'adresse du fichier répond 404',
        $oui(!str_contains($public, 'id="calendrier-public"') && !str_contains($public, 'garde-moi.txt')) . ' · ' . $cod, 'oui · 404');
    $dire('les tâches y sont toujours (« Qui fait quoi » est toujours là)', $oui(str_contains($public, 'Qui fait quoi')), 'oui');

    echo "\n7. Un groupe sans « Cours » (ou « Fichiers ») n'en reçoit pas\n";
    bd_run('INSERT INTO cours (user_id, titre) VALUES (?, ?)', [$id('a'), 'Cours d\'essai des onglets']);
    $cours = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$id('a'), 'Cours d\'essai des onglets']);
    $appel('a', 'travaux/' . $p1 . '/onglets', ['_csrf' => $ta, 'onglets' => ['fichiers']]);
    [, $refusLier] = $appel('a', 'travaux/' . $p1 . '/liens', ['_csrf' => $ta, 'lien' => 'cours:' . $cours]);
    $dire('lier un cours à un groupe qui n\'a pas « Cours » : « Ce groupe n\'a pas cet onglet. », rien n\'est lié',
        $oui(str_contains($refusLier, 'Ce groupe n’a pas cet onglet.')) . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_liens WHERE projet_id = ?', [$p1]), 'oui · 0');
    $appel('a', 'travaux/' . $p1 . '/onglets', ['_csrf' => $ta, 'onglets' => ['cours']]);
    $appel('a', 'travaux/' . $p1 . '/liens', ['_csrf' => $ta, 'lien' => 'cours:' . $cours]);
    $dire('avec « Cours » : le cours est lié', (string) bd_valeur('SELECT COUNT(*) FROM projet_liens WHERE projet_id = ?', [$p1]), '1');
    [, $partage] = $appel('a', 'partager/cours/' . $cours);
    $dire('la fenêtre « Partager » propose les groupes qui ont « Cours » (celui-ci, déjà lié), pas ceux qui ne l\'ont pas',
        $oui(str_contains($partage, 'Onglets : fichiers et document (essai)') && !str_contains($partage, 'Onglets : aucun (essai)') && str_contains($partage, 'Onglets : tous (essai)')), 'oui');
    bd_run('DELETE FROM projet_liens WHERE projet_id = ?', [$p1]);

    echo "\n8. Sans la migration, le site tourne\n";
    bd_run('ALTER TABLE projets CHANGE onglets onglets_essai VARCHAR(120) NULL');
    $colonneRenommee = true;
    [$c1, $formSans] = $appel('a', 'travaux/nouveau');
    $appel('a', 'travaux', ['_csrf' => $jeton($formSans), 'nom' => 'Onglets : sans migration (essai)', 'description' => '', 'onglets_choisis' => '1', 'onglets' => ['fichiers']]);
    $p6 = $projetNomme('Onglets : sans migration (essai)');
    $projets[] = $p6;
    [$c2, $tableauSans] = $appel('a', 'travaux/' . $p6);
    [$c3, $membresSans] = $appel('a', 'travaux/' . $p6 . '/membres');
    [, $refusSans] = $appel('a', 'travaux/' . $p6 . '/onglets', ['_csrf' => $jeton($membresSans), 'onglets' => ['fichiers']]);
    $codesSans = [];
    foreach (['echeances', 'cours', 'fichiers', 'document'] as $page) { $codesSans[] = $appel('a', 'travaux/' . $p6 . '/' . $page)[0]; }
    bd_run('ALTER TABLE projets CHANGE onglets_essai onglets VARCHAR(120) NULL');
    $colonneRenommee = false;
    $dire('le formulaire de création n\'a pas le choix, le projet se crée avec tous ses onglets, les pages répondent',
        $c1 . ' · ' . $oui(!str_contains($formSans, 'Onglets du projet')) . ' · ' . $c2 . ' · ' . $oui(substr_count($barre($tableauSans), '|') >= 6) . ' · ' . implode(' ', $codesSans), '200 · oui · 200 · oui · 200 200 200 200');
    $dire('les réglages n\'ont pas le choix non plus, et l\'envoyer est refusé sans planter', $c3 . ' · ' . $oui(!str_contains($membresSans, 'Onglets du projet') && str_contains($refusSans, 'flash--erreur')), '200 · oui');

    echo "\n9. Les quatre langues\n";
    $attendus = [
        'en' => ['Project tabs', 'Always there', 'Tick what your group needs.', 'The files the whole group uploads and downloads.', 'The final documents to hand in, with their due date.', 'Tabs saved.', 'Only the group administrator can choose the tabs.'],
        'es' => ['Pestañas del proyecto', 'Siempre está', 'Marca lo que necesita tu grupo.', 'Los archivos que todo el grupo sube y descarga.', 'Los documentos finales que hay que entregar, con su fecha.', 'Pestañas guardadas.', 'Solo el administrador del grupo puede elegir las pestañas.'],
        'de' => ['Tabs des Projekts', 'Immer dabei', 'Hake an, was deine Gruppe braucht.', 'Die Dateien, die die ganze Gruppe hochlädt und herunterlädt.', 'Die Enddokumente, die abzugeben sind, mit ihrem Termin.', 'Tabs gespeichert.', 'Nur der Gruppenadministrator kann die Tabs wählen.'],
    ];
    foreach ($attendus as $langue => $mots) {
        $appel('a', 'compte/langue', ['_csrf' => $csrf('a', 'compte'), 'langue' => $langue]);
        [, $formLangue] = $appel('a', 'travaux/nouveau');
        [, $membresLangue] = $appel('a', 'travaux/' . $p3 . '/membres');
        [, $apresLangue] = $appel('a', 'travaux/' . $p3 . '/onglets', ['_csrf' => $jeton($membresLangue), 'onglets' => ['fichiers', 'cours', 'echeances', 'document', 'livrables', 'listes']]);
        $appel('b', 'compte/langue', ['_csrf' => $csrf('b', 'compte'), 'langue' => $langue]);
        [, $refusLangue] = $appel('b', 'travaux/' . $p3 . '/onglets', ['_csrf' => $csrf('b', 'compte'), 'onglets' => ['fichiers']]);
        $tout = $formLangue . $membresLangue . $apresLangue . $refusLangue;
        $manque = array_values(array_filter($mots, static fn (string $m): bool => !str_contains($tout, $m)));
        $dire($langue . ' : le choix des onglets (titre, aide, descriptions, messages)', $manque === [] ? 'tout y est' : 'manque : ' . implode(' | ', $manque), 'tout y est');
        $dire($langue . ' : ni clé brute ni français resté', $oui(!preg_match('/\btr\.(ot|on)\.[a-z_.]+/', $tout) && !str_contains($formLangue, 'Onglets du projet') && !str_contains($formLangue, 'Toujours là') && !str_contains($tout, 'Onglets enregistrés')), 'oui');
    }
    $appel('a', 'compte/langue', ['_csrf' => $csrf('a', 'compte'), 'langue' => 'fr']);
    foreach (['fr', 'en', 'es', 'de'] as $langue) {
        $texte = (string) file_get_contents(dirname(__DIR__, 2) . '/lang/' . $langue . '.php');
        preg_match_all("/^    '([^']+)' =>/m", $texte, $cles);
        $doubles = array_keys(array_filter(array_count_values($cles[1]), static fn (int $n): bool => $n > 1));
        $dire($langue . ' : fichier de langue en UTF-8 valide, sans clé écrite deux fois', $oui(mb_check_encoding($texte, 'UTF-8')) . ' · ' . ($doubles === [] ? 'aucune' : implode(', ', array_slice($doubles, 0, 5))), 'oui · aucune');
    }
    $termine = true;
} finally {
    if ($colonneRenommee) {
        try { bd_run('ALTER TABLE projets CHANGE onglets_essai onglets VARCHAR(120) NULL'); } catch (Throwable) {}
    }
    foreach ($projets as $idProjet) {
        bd_run('DELETE FROM calendriers_amis WHERE projet_id = ?', [$idProjet]);
        bd_run('DELETE FROM projet_membres WHERE projet_id = ?', [$idProjet]);
        bd_run('DELETE FROM projets WHERE id = ? AND cree_par = ?', [$idProjet, $id('a')]);
    }
    bd_run('DELETE FROM cours WHERE user_id = ? AND titre = ?', [$id('a'), 'Cours d\'essai des onglets']);
    foreach ($comptes as $c => $d) {
        if (isset($d['id'])) { bd_run('DELETE FROM users WHERE id = ? AND email = ? AND email LIKE ?', [$d['id'], $d['email'], '%@exemple-test.fr']); }
    }
    foreach (glob(__DIR__ . '/ck_tong_*.txt') ?: [] as $fichierCookie) { @unlink($fichierCookie); }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'tong-_@exemple-test.fr'")
        . ' · projets d’essai restants ' . bd_valeur("SELECT COUNT(*) FROM projets WHERE nom LIKE 'Onglets :%'") . "\n";
}

/** Le livrable « Document commun » existe-t-il dans ce projet ? */
function Travaux_essai_livrable_existe(int $projet): bool
{
    return (int) bd_valeur('SELECT COUNT(*) FROM projet_livrables WHERE projet_id = ?', [$projet]) > 0;
}
