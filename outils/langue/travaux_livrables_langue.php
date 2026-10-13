<?php
/**
 * Les livrables d'un travail de groupe, les documents finaux à rendre (HTTP + base + disque) : l'onglet, créer / modifier / supprimer un livrable
 * (la suppression est réservée à l'administrateur), y déposer des documents (refus d'une extension, d'un envoi vide, trop de documents), ce qu'ils
 * deviennent (ni dans « Fichiers » ni sur le lien public, téléchargeables par les membres seulement), « Marquer comme rendu » et son annulation,
 * les dates de rendu, un site dont la migration n'est pas passée, et les quatre langues.
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
    'a' => ['email' => 'tliv-a@exemple-test.fr', 'pseudo' => 'Tliv_Admin'],
    'b' => ['email' => 'tliv-b@exemple-test.fr', 'pseudo' => 'Tliv_Membre'],
    'c' => ['email' => 'tliv-c@exemple-test.fr', 'pseudo' => 'Tliv_Dehors'],
];
foreach ($comptes as $c => $d) {
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$d['email'], '%@exemple-test.fr']);
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)', [$d['email'], $d['pseudo'], password_hash($mdp, PASSWORD_DEFAULT), 'Essai ' . $c]);
    $comptes[$c]['id'] = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$d['email']]);
}
$id = static fn (string $c): int => $comptes[$c]['id'];

$jar = static fn (string $nom): string => __DIR__ . '/ck_tliv_' . $nom . '.txt';
$appel = static function (string $navigateur, string $chemin, ?array $post = null) use ($jar): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar($navigateur), CURLOPT_COOKIEFILE => $jar($navigateur)]);
    // Un tableau où figure un fichier part en « multipart » ; sinon, en formulaire ordinaire.
    if ($post !== null) {
        curl_setopt($h, CURLOPT_POST, true);
        $avecFichier = array_filter($post, static fn (mixed $v): bool => $v instanceof CURLFile) !== [];
        curl_setopt($h, CURLOPT_POSTFIELDS, $avecFichier ? $post : http_build_query($post));
    }
    $brut = (string) curl_exec($h);
    $taille = (int) curl_getinfo($h, CURLINFO_HEADER_SIZE);
    $r = [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), substr($brut, $taille), (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL), substr($brut, 0, $taille)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';

$projets = [];
$renomme = false;
$colonneRenommee = false;
$temporaires = [];
$dossierStockage = dirname(__DIR__, 2) . '/storage/travaux';
try {
    foreach (['a', 'b', 'c'] as $c) {
        [, $connexion] = $appel($c, 'connexion');
        $appel($c, 'connexion', ['_csrf' => $jeton($connexion), 'identifiant' => $comptes[$c]['email'], 'mot_de_passe' => $mdp]);
    }
    $dateRapport = date('Y-m-d', strtotime('+5 days'));
    $existe = static function (string $nomStocke) use ($dossierStockage): bool { clearstatcache(true); return is_file($dossierStockage . '/' . $nomStocke); };
    $jourPlus = static fn (int $n): string => date('Y-m-d', strtotime(($n >= 0 ? '+' : '') . $n . ' days'));
    $lienPublic = bin2hex(random_bytes(16));
    bd_run('INSERT INTO projets (nom, cree_par, jeton) VALUES (?, ?, ?)', ['Projet des livrables (essai)', $id('a'), $lienPublic]);
    $p = (int) bd_valeur('SELECT id FROM projets WHERE cree_par = ? ORDER BY id DESC LIMIT 1', [$id('a')]);
    $projets[] = $p;
    bd_run("INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, 'admin', 'membre'), (?, ?, 'membre', 'membre')", [$p, $id('a'), $p, $id('b')]);
    $livrables = static fn (): array => array_column(bd_all('SELECT id, titre FROM projet_livrables WHERE projet_id = ? ORDER BY position, id', [$GLOBALS['p']]), 'titre', 'id');
    $csrf = static fn (string $nav, string $chemin): string => $jeton($GLOBALS['appel']($nav, $chemin)[1]);
    $fichier = static function (string $nom, string $contenu) use (&$temporaires): CURLFile {
        $chemin = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tliv_' . bin2hex(random_bytes(4)) . '_' . $nom;
        file_put_contents($chemin, $contenu);
        $temporaires[] = $chemin;
        return new CURLFile($chemin, 'text/plain', $nom);
    };

    echo "\n1. L'onglet « Livrables »\n";
    [$cod, $tableau] = $appel('a', 'travaux/' . $p);
    $dire('un onglet « Livrables », après « Document commun » et avant « Listes » et « Membres »',
        $cod . ' · ' . $oui(preg_match('#Document commun\s*</a>\s*<a href="[^"]*/travaux/' . $p . '/livrables"[^>]*>\s*<span aria-hidden="true">📤</span> Livrables\s*</a>\s*<a href="[^"]*/travaux/' . $p . '/listes"#u', $tableau) === 1), '200 · oui');
    [$cod, $page] = $appel('a', 'travaux/' . $p . '/livrables');
    $dire('la page vide : l\'aide, le formulaire de création, « Aucun livrable pour l\'instant. »',
        $cod . ' · ' . $oui(str_contains($page, 'Les documents finaux que le groupe doit rendre') && !str_contains($page, 'Créer le livrable') && !str_contains($page, 'name="titre"') && str_contains($page, 'Aucun livrable pour l’instant.') && str_contains($page, 'aria-current="page"')), '200 · oui');
    $dire('un bouton « + Nouveau livrable » ouvre une fenêtre (comme « + Nouvelle échéance »)', $oui(preg_match('#<a class="bouton bouton--petit" href="[^"]*/travaux/' . $p . '/livrables/nouveau" data-fenetre>\+ Nouveau livrable</a>#', $page) === 1), 'oui');
    [$cod, $formulaire] = $appel('a', 'travaux/' . $p . '/livrables/nouveau');
    $dire('la page du formulaire : titre, date, consigne, « Créer le livrable » et « Annuler »',
        $cod . ' · ' . $oui(str_contains($formulaire, '📤 Nouveau livrable') && str_contains($formulaire, 'name="titre"') && str_contains($formulaire, 'type="date" id="date-livrable"') && str_contains($formulaire, 'name="note"')
            && str_contains($formulaire, 'Créer le livrable') && str_contains($formulaire, '>Annuler</a>') && str_contains($formulaire, 'action="/mon_appli/appli/travaux/' . $p . '/livrables"')), '200 · oui');
    [, $fragment] = $appel('a', 'travaux/' . $p . '/livrables/nouveau?fenetre=1');
    $dire('ouverte en fenêtre : seulement le contenu (l\'envoi et « Annuler » restent dans la fenêtre)',
        $oui(!str_contains($fragment, '<html') && str_contains($fragment, 'data-envoi-fenetre') && str_contains($fragment, 'data-fermer') && !str_contains($fragment, '← ')), 'oui');
    [$cod] = $appel('c', 'travaux/' . $p . '/livrables');
    [$cod2] = $appel('c', 'travaux/' . $p . '/livrables/nouveau');
    $dire('quelqu\'un du dehors : 404 (la liste et le formulaire)', $cod . ' · ' . $cod2, '404 · 404');

    echo "\n2. Créer des livrables\n";
    $ta = $csrf('a', 'travaux/' . $p . '/livrables/nouveau');
    [, $vide, $adresseVide] = $appel('a', 'travaux/' . $p . '/livrables', ['_csrf' => $ta, 'titre' => '   ']);
    $dire('sans titre : « Donnez un titre au livrable. », on reste sur le formulaire, rien n\'est créé', $oui(str_contains($vide, 'Donnez un titre au livrable.') && str_ends_with($adresseVide, '/livrables/nouveau')) . ' · ' . count($livrables()), 'oui · 0');
    [, $cree, $adresseCree] = $appel('a', 'travaux/' . $p . '/livrables', ['_csrf' => $ta, 'titre' => 'Rapport final', 'echeance' => $dateRapport, 'note' => "PDF, 30 pages au plus\nà rendre sur la plateforme"]);
    $dire('« Livrable créé. » : « Rapport final », avec sa date et sa consigne', $oui(str_contains($cree, 'Livrable créé.') && str_contains($cree, 'Rapport final') && str_contains($cree, 'PDF, 30 pages au plus') && str_ends_with($adresseCree, '/livrables')) . ' · ' . bd_valeur('SELECT echeance FROM projet_livrables WHERE titre = ?', ['Rapport final']), 'oui · ' . $dateRapport);
    $appel('a', 'travaux/' . $p . '/livrables', ['_csrf' => $ta, 'titre' => 'Diaporama', 'echeance' => 'pas une date', 'note' => '']);
    $dire('« Diaporama » : une date illisible est ignorée, pas de consigne', (string) bd_valeur('SELECT COALESCE(echeance, \'sans date\') FROM projet_livrables WHERE titre = ?', ['Diaporama']) . ' · ' . $oui(bd_valeur('SELECT note FROM projet_livrables WHERE titre = ?', ['Diaporama']) === null), 'sans date · oui');
    $appel('b', 'travaux/' . $p . '/livrables', ['_csrf' => $csrf('b', 'travaux/' . $p . '/livrables/nouveau'), 'titre' => 'Annexes', 'echeance' => $jourPlus(-2)]);
    $dire('un simple membre peut aussi en créer : « Annexes » (en retard de 2 jours)', implode(' | ', $livrables()), 'Rapport final | Diaporama | Annexes');
    $appel('a', 'travaux/' . $p . '/livrables', ['_csrf' => $ta, 'titre' => str_repeat('é', 200), 'note' => str_repeat('x', 800)]);
    $long = bd_all('SELECT titre, note FROM projet_livrables WHERE projet_id = ? ORDER BY id DESC LIMIT 1', [$p])[0];
    $dire('un titre trop long est coupé à 120 caractères, une consigne à 500', mb_strlen($long['titre']) . ' · ' . mb_strlen($long['note']), '120 · 500');
    bd_run('DELETE FROM projet_livrables WHERE projet_id = ? AND CHAR_LENGTH(titre) = 120', [$p]);
    $reste = Travaux_essai_max('LIVRABLES_MAX') - count($livrables());
    for ($i = 0; $i < $reste; $i++) {
        bd_run('INSERT INTO projet_livrables (projet_id, titre, position, created_at) VALUES (?, ?, ?, NOW())', [$p, 'Rempli ' . $i, 100 + $i]);
    }
    [, $trop] = $appel('a', 'travaux/' . $p . '/livrables', ['_csrf' => $ta, 'titre' => 'De trop']);
    $dire('au plus ' . Travaux_essai_max('LIVRABLES_MAX') . ' livrables', $oui(str_contains($trop, 'Un projet a au plus ' . Travaux_essai_max('LIVRABLES_MAX') . ' livrables.')) . ' · ' . count($livrables()), 'oui · ' . Travaux_essai_max('LIVRABLES_MAX'));
    bd_run("DELETE FROM projet_livrables WHERE projet_id = ? AND titre LIKE 'Rempli %'", [$p]);
    [$c1] = $appel('c', 'travaux/' . $p . '/livrables', ['_csrf' => $csrf('c', 'compte'), 'titre' => 'Intrus']);
    $dire('quelqu\'un du dehors n\'en crée pas (404)', $c1 . ' · ' . count($livrables()), '404 · 3');
    $rapport = (int) array_search('Rapport final', $livrables(), true);
    $diaporama = (int) array_search('Diaporama', $livrables(), true);
    $annexes = (int) array_search('Annexes', $livrables(), true);

    echo "\n3. Les états et les dates\n";
    [, $page] = $appel('a', 'travaux/' . $p . '/livrables');
    $dire('sans document : « À rendre » pour chacun', (string) substr_count($page, 'pastille--muette">À rendre<'), '3');
    $dire('en tête, les plus pressés : « Annexes » (en retard), « Rapport final » (5 jours), puis « Diaporama » (sans date)',
        $oui(preg_match('#Annexes.*?Rapport final.*?Diaporama#s', $page) === 1 && preg_match('#class="echeance echeance--retard">📅 À rendre : En retard#u', $page) === 1 || preg_match('#echeance--retard">📅 À rendre : #u', $page) === 1), 'oui');
    $dire('et une date pour « Rapport final » (pas en retard)', $oui(preg_match('#Rapport final.*?echeance echeance--(proche|lointain)">📅 À rendre : #su', $page) === 1), 'oui');
    $dire('le bilan : « 0 / 3 rendus »', $oui(str_contains($page, '0 / 3 rendus')), 'oui');

    echo "\n4. Déposer des documents\n";
    $tb = $csrf('b', 'travaux/' . $p . '/livrables/nouveau');
    [, $apresDepot] = $appel('a', 'travaux/livrables/' . $rapport . '/fichiers', ['_csrf' => $ta, 'fichiers[0]' => $fichier('rapport.txt', "Le rapport final.\n")]);
    $doc = bd_all('SELECT id, livrable_id, user_id, nom_origine, nom_stocke, taille FROM projet_fichiers WHERE projet_id = ?', [$p]);
    $dire('un document déposé : lié au livrable, au nom de a, avec son nom et sa taille', count($doc) . ' · ' . ($doc[0]['livrable_id'] ?? '-') . ' · ' . ($doc[0]['user_id'] ?? '-') . ' · ' . ($doc[0]['nom_origine'] ?? '-') . ' · ' . ($doc[0]['taille'] ?? '-'), '1 · ' . $rapport . ' · ' . $id('a') . ' · rapport.txt · 18');
    $dire('« 1 document déposé » et il est dans le livrable', $oui(str_contains($apresDepot, 'rapport.txt') && str_contains($apresDepot, 'déposé par Tliv_Admin')), 'oui');
    $dire('le fichier est bien rangé sur le disque', $oui($existe($doc[0]['nom_stocke'])), 'oui');
    $appel('b', 'travaux/livrables/' . $rapport . '/fichiers', ['_csrf' => $tb, 'fichiers[0]' => $fichier('annexe-1.txt', 'A1'), 'fichiers[1]' => $fichier('annexe-2.txt', 'A2')]);
    $dire('un autre membre en dépose deux d\'un coup', (string) bd_valeur('SELECT COUNT(*) FROM projet_fichiers WHERE livrable_id = ?', [$rapport]), '3');
    [, $refus] = $appel('a', 'travaux/livrables/' . $diaporama . '/fichiers', ['_csrf' => $ta, 'fichiers[0]' => $fichier('virus.exe', 'MZ')]);
    $dire('une extension interdite est refusée, rien n\'est gardé', $oui(str_contains($refus, 'flash--erreur')) . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_fichiers WHERE livrable_id = ?', [$diaporama]), 'oui · 0');
    [, $rien] = $appel('a', 'travaux/livrables/' . $diaporama . '/fichiers', ['_csrf' => $ta]);
    $dire('un envoi sans fichier : « Choisissez au moins un fichier. »', $oui(str_contains($rien, 'flash--erreur')) . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_fichiers WHERE livrable_id = ?', [$diaporama]), 'oui · 0');
    [$cod] = $appel('c', 'travaux/livrables/' . $diaporama . '/fichiers', ['_csrf' => $csrf('c', 'compte'), 'fichiers[0]' => $fichier('intrus.txt', 'x')]);
    $dire('quelqu\'un du dehors ne dépose rien (404)', $cod . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_fichiers WHERE livrable_id = ?', [$diaporama]), '404 · 0');
    $plein = Travaux_essai_max('LIVRABLE_FICHIERS_MAX') - (int) bd_valeur('SELECT COUNT(*) FROM projet_fichiers WHERE livrable_id = ?', [$annexes]);
    for ($i = 0; $i < $plein; $i++) {
        bd_run('INSERT INTO projet_fichiers (projet_id, livrable_id, user_id, nom_origine, nom_stocke, mime, taille) VALUES (?, ?, ?, ?, ?, ?, 1)', [$p, $annexes, $id('a'), 'doc' . $i . '.txt', 'inexistant-' . $i, 'text/plain']);
    }
    [, $tropDeDocs] = $appel('a', 'travaux/livrables/' . $annexes . '/fichiers', ['_csrf' => $ta, 'fichiers[0]' => $fichier('de-trop.txt', 'x')]);
    $dire('au plus ' . Travaux_essai_max('LIVRABLE_FICHIERS_MAX') . ' documents par livrable', $oui(str_contains($tropDeDocs, 'Au plus ' . Travaux_essai_max('LIVRABLE_FICHIERS_MAX') . ' documents par livrable.')) . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_fichiers WHERE livrable_id = ?', [$annexes]), 'oui · ' . Travaux_essai_max('LIVRABLE_FICHIERS_MAX'));
    bd_run('DELETE FROM projet_fichiers WHERE livrable_id = ?', [$annexes]);

    echo "\n5. Où vont ces documents\n";
    $premier = (int) $doc[0]['id'];
    [$cod, $contenu] = $appel('b', 'travaux/fichiers/' . $premier);
    $dire('un membre les télécharge', $cod . ' · ' . $oui(str_contains($contenu, 'Le rapport final.')), '200 · oui');
    [$cod, $corps] = $appel('c', 'travaux/fichiers/' . $premier);
    $dire('quelqu\'un du dehors : 404', $cod . ' · ' . $oui(!str_contains($corps, 'Le rapport final.')), '404 · oui');
    [, $onglet] = $appel('a', 'travaux/' . $p . '/fichiers');
    $dire('l\'onglet « Fichiers » ne les montre pas (ils sont à part)', $oui(!str_contains($onglet, 'rapport.txt') && !str_contains($onglet, 'annexe-1.txt')), 'oui');
    $appel('a', 'travaux/' . $p . '/fichiers', ['_csrf' => $ta, 'fichiers[0]' => $fichier('notes.txt', 'Des notes.')]);
    [, $onglet] = $appel('a', 'travaux/' . $p . '/fichiers');
    $dire('un fichier ordinaire, lui, y est toujours', $oui(str_contains($onglet, 'notes.txt') && !str_contains($onglet, 'rapport.txt')), 'oui');
    [, $public] = $appel('a', 'g/' . $lienPublic);
    $dire('le lien public ne montre pas les documents des livrables (ni leurs titres)', $oui(!str_contains($public, 'rapport.txt') && !str_contains($public, 'Rapport final') && str_contains($public, 'notes.txt')), 'oui');
    [$cod, $corps] = $appel('a', 'g/' . $lienPublic . '/fichiers/' . $premier);
    $dire('et son adresse de fichier ne les sert pas (404)', $cod . ' · ' . $oui(!str_contains($corps, 'Le rapport final.')), '404 · oui');
    $notes = (int) bd_valeur('SELECT id FROM projet_fichiers WHERE projet_id = ? AND nom_origine = ?', [$p, 'notes.txt']);
    [$cod, $corps] = $appel('a', 'g/' . $lienPublic . '/fichiers/' . $notes);
    $dire('tandis qu\'un fichier ordinaire du projet reste servi par le lien', $cod . ' · ' . $oui(str_contains($corps, 'Des notes.')), '200 · oui');

    echo "\n6. Marquer comme rendu\n";
    [, $page] = $appel('a', 'travaux/' . $p . '/livrables');
    $dire('le rapport a des documents : « Prêt à rendre » ; le diaporama, rien : « À rendre »', $oui(str_contains($page, 'pastille--active">Prêt à rendre<') && substr_count($page, 'pastille--muette">À rendre<') === 2), 'oui');
    $dire('« Marquer comme rendu » : sans confirmation pour le rapport (il a des documents), avec pour le diaporama',
        $oui(preg_match('#livrables/' . $rapport . '/rendre" class="livrable__rendre"\s*>#', $page) === 1 && preg_match('#livrables/' . $diaporama . '/rendre" class="livrable__rendre"\s*data-confirmation="Aucun document n’est déposé dans « Diaporama »#u', $page) === 1), 'oui');
    [, $rendu] = $appel('b', 'travaux/livrables/' . $rapport . '/rendre', ['_csrf' => $tb, 'rendu' => '1']);
    $ligne = bd_all('SELECT rendu_le, rendu_par FROM projet_livrables WHERE id = ?', [$rapport])[0];
    $dire('b le marque rendu : « Livrable marqué comme rendu. », avec qui et quand',
        $oui(str_contains($rendu, 'Livrable marqué comme rendu.')) . ' · ' . $oui($ligne['rendu_le'] !== null) . ' · ' . $ligne['rendu_par'], 'oui · oui · ' . $id('b'));
    $dire('la page dit « Rendu le … par Tliv_Membre. », « Rendu » en vert, « 1 / 3 rendus »',
        $oui(preg_match('#Rendu le \d+ [^<]+ par Tliv_Membre\.#u', $rendu) === 1 && str_contains($rendu, 'pastille--ok">✅ Rendu<') && str_contains($rendu, '1 / 3 rendus') && str_contains($rendu, 'livrable--rendu')), 'oui');
    $dire('rendu, il passe après ceux qui restent à rendre ; plus de date « en retard » dessus', $oui(preg_match('#Annexes.*?Diaporama.*?Rapport final#s', $rendu) === 1 && preg_match('#id="livrable-' . $rapport . '".*?</section>#s', $rendu, $carte) === 1 && !str_contains($carte[0], 'class="echeance')), 'oui');
    $dire('et le bouton devient « Pas encore rendu »', $oui(str_contains($rendu, '↩ Pas encore rendu')), 'oui');
    [$cod] = $appel('c', 'travaux/livrables/' . $diaporama . '/rendre', ['_csrf' => $csrf('c', 'compte'), 'rendu' => '1']);
    $dire('quelqu\'un du dehors ne marque rien (404)', $cod . ' · ' . $oui(bd_valeur('SELECT rendu_le FROM projet_livrables WHERE id = ?', [$diaporama]) === null), '404 · oui');
    [, $annule] = $appel('a', 'travaux/livrables/' . $rapport . '/rendre', ['_csrf' => $ta, 'rendu' => '0']);
    $dire('annulé : « Livrable remis « à rendre ». », plus de date de rendu', $oui(str_contains($annule, 'Livrable remis « à rendre ».')) . ' · ' . $oui(bd_valeur('SELECT rendu_le FROM projet_livrables WHERE id = ?', [$rapport]) === null) . ' · ' . $oui(bd_valeur('SELECT rendu_par FROM projet_livrables WHERE id = ?', [$rapport]) === null), 'oui · oui · oui');

    echo "\n7. Modifier\n";
    $appel('b', 'travaux/livrables/' . $diaporama . '/modifier', ['_csrf' => $tb, 'titre' => 'Diaporama de soutenance', 'echeance' => $jourPlus(9), 'note' => 'Dix diapositives']);
    $ligne = bd_all('SELECT titre, echeance, note FROM projet_livrables WHERE id = ?', [$diaporama])[0];
    $dire('un simple membre modifie le titre, la date et la consigne', $ligne['titre'] . ' · ' . $ligne['echeance'] . ' · ' . $ligne['note'], 'Diaporama de soutenance · ' . $jourPlus(9) . ' · Dix diapositives');
    [, $sansTitre] = $appel('a', 'travaux/livrables/' . $diaporama . '/modifier', ['_csrf' => $ta, 'titre' => '  ', 'echeance' => '', 'note' => '']);
    $dire('sans titre : refusé, rien ne change', $oui(str_contains($sansTitre, 'Donnez un titre au livrable.')) . ' · ' . bd_valeur('SELECT titre FROM projet_livrables WHERE id = ?', [$diaporama]), 'oui · Diaporama de soutenance');
    $appel('a', 'travaux/livrables/' . $diaporama . '/modifier', ['_csrf' => $ta, 'titre' => 'Diaporama de soutenance', 'echeance' => '', 'note' => '']);
    $dire('vider la date et la consigne les efface', (string) bd_valeur('SELECT COALESCE(echeance, \'sans date\') FROM projet_livrables WHERE id = ?', [$diaporama]) . ' · ' . $oui(bd_valeur('SELECT note FROM projet_livrables WHERE id = ?', [$diaporama]) === null), 'sans date · oui');
    [$cod] = $appel('c', 'travaux/livrables/' . $diaporama . '/modifier', ['_csrf' => $csrf('c', 'compte'), 'titre' => 'Piraté']);
    $dire('quelqu\'un du dehors ne modifie rien (404)', $cod . ' · ' . bd_valeur('SELECT titre FROM projet_livrables WHERE id = ?', [$diaporama]), '404 · Diaporama de soutenance');

    echo "\n8. Retirer un document, supprimer un livrable\n";
    $annexe1 = (int) bd_valeur('SELECT id FROM projet_fichiers WHERE nom_origine = ?', ['annexe-1.txt']);
    $stocke = (string) bd_valeur('SELECT nom_stocke FROM projet_fichiers WHERE id = ?', [$annexe1]);
    $appel('a', 'travaux/fichiers/' . $annexe1 . '/supprimer', ['_csrf' => $ta]);
    $dire('l\'administrateur retire « annexe-1.txt » (déposé par b) : le document et son fichier disparaissent',
        bd_valeur('SELECT COUNT(*) FROM projet_fichiers WHERE id = ?', [$annexe1]) . ' · ' . $oui(!$existe($stocke)), '0 · oui');
    $annexe2 = (int) bd_valeur('SELECT id FROM projet_fichiers WHERE nom_origine = ?', ['annexe-2.txt']);
    [, $retour, $adresse] = $appel('b', 'travaux/fichiers/' . $annexe2 . '/supprimer', ['_csrf' => $tb]);
    $dire('b retire le sien (« annexe-2.txt ») et revient aux livrables, pas à « Fichiers »', bd_valeur('SELECT COUNT(*) FROM projet_fichiers WHERE id = ?', [$annexe2]) . ' · ' . $oui(str_ends_with($adresse, '/travaux/' . $p . '/livrables')), '0 · oui');
    $stockeRapport = (string) bd_valeur('SELECT nom_stocke FROM projet_fichiers WHERE nom_origine = ?', ['rapport.txt']);
    [, $nonAdmin] = $appel('b', 'travaux/livrables/' . $rapport . '/supprimer', ['_csrf' => $tb]);
    $dire('un simple membre ne supprime pas un livrable : « Seul l\'administrateur… », tout reste', $oui(str_contains($nonAdmin, 'Seul l’administrateur du groupe peut supprimer un livrable.')) . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_livrables WHERE id = ?', [$rapport]) . ' · ' . $oui($existe($stockeRapport)), 'oui · 1 · oui');
    [, $page] = $appel('b', 'travaux/' . $p . '/livrables');
    $dire('et sa page ne propose pas « Supprimer le livrable » à un simple membre', $oui(!str_contains($page, 'Supprimer le livrable') && str_contains($page, 'Seul l’administrateur du groupe peut supprimer un livrable.')), 'oui');
    [, $pageA] = $appel('a', 'travaux/' . $p . '/livrables');
    $dire('mais l\'administrateur l\'a, avec sa confirmation', $oui(str_contains($pageA, 'Supprimer le livrable « Rapport final » et tous ses documents ?')), 'oui');
    [, $supprime] = $appel('a', 'travaux/livrables/' . $rapport . '/supprimer', ['_csrf' => $ta]);
    $dire('l\'administrateur supprime « Rapport final » : « Livrable supprimé. », ses documents et leurs fichiers aussi',
        $oui(str_contains($supprime, 'Livrable supprimé.')) . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_livrables WHERE id = ?', [$rapport]) . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_fichiers WHERE livrable_id = ?', [$rapport]) . ' · ' . $oui(!$existe($stockeRapport)), 'oui · 0 · 0 · oui');
    $dire('le fichier ordinaire du projet (« notes.txt ») n\'a pas bougé', (string) bd_valeur('SELECT COUNT(*) FROM projet_fichiers WHERE id = ?', [$notes]), '1');

    echo "\n8b. Le document commun, en PDF — et dans les livrables\n";
    /** Le texte écrit dans un PDF de PdfSimple (les flux sont compressés, le texte est en Windows-1252). */
    $texteDuPdf = static function (string $pdf): string {
        $texte = '';
        preg_match_all('/stream\n(.*?)\nendstream/s', $pdf, $flux);
        foreach ($flux[1] as $brut) {
            $contenu = @gzuncompress($brut);
            if ($contenu !== false && preg_match_all('/\((.*?)(?<!\\\\)\) Tj/s', $contenu, $mm)) {
                $texte .= implode(' ', array_map(static fn (string $s): string => (string) mb_convert_encoding(strtr($s, ['\\(' => '(', '\\)' => ')', '\\\\' => '\\']), 'UTF-8', 'Windows-1252'), $mm[1])) . ' ';
            }
        }
        return $texte;
    };
    $nomDuPdf = 'Document commun - Projet des livrables (essai).pdf';
    $livrableDoc = static fn (): int => (int) bd_valeur('SELECT id FROM projet_livrables WHERE projet_id = ? AND titre = ?', [$GLOBALS['p'], 'Document commun']);
    $pdfsDuLivrable = static fn (int $livrable): array => bd_all('SELECT id, user_id, mime, taille, nom_origine, nom_stocke FROM projet_fichiers WHERE livrable_id = ? AND nom_origine = ?', [$livrable, $GLOBALS['nomDuPdf']]);
    [$cod, $sansDoc, $adresse] = $appel('a', 'travaux/' . $p . '/document/pdf');
    $dire('document vide : pas de PDF, on revient au document avec « Le document commun est vide »', $cod . ' · ' . $oui(!str_starts_with($sansDoc, '%PDF') && str_contains($sansDoc, 'Le document commun est vide') && str_ends_with($adresse, '/travaux/' . $p . '/document')), '200 · oui');
    [, $refusVide] = $appel('a', 'travaux/' . $p . '/document/livrable', ['_csrf' => $ta, 'livrable' => 'nouveau']);
    $dire('et le mettre dans les livrables est refusé de même (aucun livrable « Document commun » créé)', $oui(str_contains($refusVide, 'Le document commun est vide')) . ' · ' . $livrableDoc(), 'oui · 0');
    [, $pageDoc] = $appel('a', 'travaux/' . $p . '/document');
    $version = preg_match('/name="version" value="(\d+)"/', $pageDoc, $mv) ? $mv[1] : '0';
    $appel('a', 'travaux/' . $p . '/document', ['_csrf' => $jeton($pageDoc), 'version' => $version, 'document' => '<!--riche--><p>Bonjour à toute l’équipe</p><h2>Le plan</h2><ul><li>Introduction</li><li>Conclusion</li></ul>']);
    [, $lecture] = $appel('a', 'travaux/' . $p . '/document');
    $dire('le document écrit, sa page de lecture a « Exporter en PDF » et « Mettre dans les livrables » (avec son panneau)',
        $oui(str_contains($lecture, 'href="/mon_appli/appli/travaux/' . $p . '/document/pdf"') && str_contains($lecture, '⬇ Exporter en PDF') && str_contains($lecture, '📤 Mettre dans les livrables')
            && str_contains($lecture, 'acces-panneau') && str_contains($lecture, '<option value="nouveau" selected>Le livrable « Document commun » (créé s’il n’existe pas)</option>')
            && str_contains($lecture, '>Diaporama de soutenance</option>') && str_contains($lecture, '>Annexes</option>')), 'oui');

    [$cod, $pdfDoc, , $entetesDoc] = $appel('a', 'travaux/' . $p . '/document/pdf');
    $dire('« Exporter en PDF » : un PDF en pièce jointe, « Document commun - Projet des livrables (essai).pdf »',
        $cod . ' · ' . $oui(str_starts_with($pdfDoc, '%PDF') && str_contains($entetesDoc, 'application/pdf') && str_contains($entetesDoc, 'attachment') && str_contains($entetesDoc, 'Document%20commun%20-%20Projet')), '200 · oui');
    $texteDoc = $texteDuPdf($pdfDoc);
    $dire('il porte le nom du projet, « Document commun », le texte et ses titres', $oui(str_contains($texteDoc, 'Projet des livrables (essai)') && str_contains($texteDoc, 'Document commun') && str_contains($texteDoc, 'Bonjour à toute l’équipe') && str_contains($texteDoc, 'Le plan') && str_contains($texteDoc, 'Introduction')), 'oui');
    [$cod] = $appel('b', 'travaux/' . $p . '/document/pdf');
    [$cod2, $corps2] = $appel('c', 'travaux/' . $p . '/document/pdf');
    [, $corps3, , $adresse3] = $appel('anonyme', 'travaux/' . $p . '/document/pdf');
    $dire('un autre membre l\'exporte ; quelqu\'un du dehors : 404 ; sans compte : la connexion', $cod . ' · ' . $cod2 . ' · ' . $oui(!str_starts_with($corps2 . $corps3, '%PDF') && str_contains($adresse3, '/connexion')), '200 · 404 · oui');

    [, $apresMise, $adresseMise] = $appel('a', 'travaux/' . $p . '/document/livrable', ['_csrf' => $ta, 'livrable' => 'nouveau']);
    $livraison = $livrableDoc();
    $pdfs = $pdfsDuLivrable($livraison);
    $dire('« Mettre dans les livrables » (nouveau) : le livrable « Document commun » est créé, avec le PDF dedans (de a, application/pdf)',
        ($livraison > 0 ? 'créé' : 'absent') . ' · ' . count($pdfs) . ' · ' . ($pdfs[0]['mime'] ?? '-') . ' · ' . ($pdfs[0]['user_id'] ?? '-'), 'créé · 1 · application/pdf · ' . $id('a'));
    $dire('on arrive sur les livrables, avec « Le document commun (PDF) est dans le livrable « Document commun ». » et le PDF listé',
        $oui(str_ends_with($adresseMise, '/travaux/' . $p . '/livrables') && str_contains($apresMise, 'Le document commun (PDF) est dans le livrable « Document commun ».') && str_contains($apresMise, $nomDuPdf)), 'oui');
    $existe = static function (string $nomStocke) use ($dossierStockage): bool { clearstatcache(true); return is_file($dossierStockage . '/' . $nomStocke); };
    $dire('le fichier est un vrai PDF rangé sur le disque, de la taille annoncée', $oui($existe($pdfs[0]['nom_stocke']) && str_starts_with((string) file_get_contents($dossierStockage . '/' . $pdfs[0]['nom_stocke']), '%PDF') && filesize($dossierStockage . '/' . $pdfs[0]['nom_stocke']) === (int) $pdfs[0]['taille']), 'oui');
    [$cod, $telecharge] = $appel('b', 'travaux/fichiers/' . $pdfs[0]['id']);
    $dire('un membre le télécharge depuis le livrable ; il n\'est pas dans « Fichiers »', $cod . ' · ' . $oui(str_starts_with($telecharge, '%PDF') && !str_contains($appel('a', 'travaux/' . $p . '/fichiers')[1], $nomDuPdf)), '200 · oui');

    $ancienStocke = $pdfs[0]['nom_stocke'];
    [$version] = [preg_match('/name="version" value="(\d+)"/', $appel('a', 'travaux/' . $p . '/document?modifier=1')[1], $mv2) ? $mv2[1] : '0'];
    $appel('a', 'travaux/' . $p . '/document', ['_csrf' => $ta, 'version' => $version, 'document' => '<!--riche--><p>Bonjour à toute l’équipe</p><h2>Le plan</h2><ul><li>Introduction</li><li>Conclusion</li></ul><p>Et une phrase ajoutée.</p>']);
    $appel('b', 'travaux/' . $p . '/document/livrable', ['_csrf' => $tb, 'livrable' => 'nouveau']);
    $pdfs = $pdfsDuLivrable($livraison);
    $dire('le remettre (par b, le texte ayant changé) remplace le PDF : toujours un seul, nouveau fichier, l\'ancien est effacé du disque',
        count($pdfs) . ' · ' . $oui($pdfs !== [] && $pdfs[0]['nom_stocke'] !== $ancienStocke) . ' · ' . $oui(!$existe($ancienStocke)) . ' · ' . ($pdfs[0]['user_id'] ?? '-') . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_livrables WHERE projet_id = ? AND titre = ?', [$p, 'Document commun']), '1 · oui · oui · ' . $id('b') . ' · 1');
    [, $nouveauPdf] = $appel('a', 'travaux/fichiers/' . $pdfs[0]['id']);
    $dire('et le nouveau PDF a la phrase ajoutée', $oui(str_contains($texteDuPdf($nouveauPdf), 'Et une phrase ajoutée.')), 'oui');

    $appel('b', 'travaux/' . $p . '/document/livrable', ['_csrf' => $tb, 'livrable' => (string) $diaporama]);
    $dire('vers un livrable choisi (« Diaporama de soutenance ») : le PDF y est, l\'autre livrable garde le sien',
        count($pdfsDuLivrable($diaporama)) . ' · ' . count($pdfsDuLivrable($livraison)), '1 · 1');
    bd_run('INSERT INTO projets (nom, cree_par, jeton) VALUES (?, ?, ?)', ['Un autre projet (essai)', $id('a'), bin2hex(random_bytes(16))]);
    $autreProjet = (int) bd_valeur('SELECT id FROM projets WHERE nom = ? AND cree_par = ?', ['Un autre projet (essai)', $id('a')]);
    $projets[] = $autreProjet;
    bd_run("INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, 'admin', 'membre')", [$autreProjet, $id('a')]);
    bd_run('INSERT INTO projet_livrables (projet_id, titre, position, created_at) VALUES (?, ?, 1, NOW())', [$autreProjet, 'Livrable d\'ailleurs']);
    $ailleurs = (int) bd_valeur('SELECT id FROM projet_livrables WHERE projet_id = ?', [$autreProjet]);
    [, $refus1] = $appel('a', 'travaux/' . $p . '/document/livrable', ['_csrf' => $ta, 'livrable' => (string) $ailleurs]);
    [, $refus2] = $appel('a', 'travaux/' . $p . '/document/livrable', ['_csrf' => $ta, 'livrable' => 'n\'importe quoi']);
    $dire('un livrable d\'un autre projet, ou un choix absurde : refusé, rien n\'est déposé',
        $oui(str_contains($refus1, 'flash--erreur') && str_contains($refus2, 'flash--erreur')) . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_fichiers WHERE livrable_id = ?', [$ailleurs]), 'oui · 0');
    [$cod] = $appel('c', 'travaux/' . $p . '/document/livrable', ['_csrf' => $csrf('c', 'compte'), 'livrable' => 'nouveau']);
    $dire('quelqu\'un du dehors ne met rien dans les livrables (404)', (string) $cod, '404');
    $plein = Travaux_essai_max('LIVRABLE_FICHIERS_MAX') - (int) bd_valeur('SELECT COUNT(*) FROM projet_fichiers WHERE livrable_id = ?', [$annexes]);
    for ($i = 0; $i < $plein; $i++) {
        bd_run('INSERT INTO projet_fichiers (projet_id, livrable_id, user_id, nom_origine, nom_stocke, mime, taille) VALUES (?, ?, ?, ?, ?, ?, 1)', [$p, $annexes, $id('a'), 'doc' . $i . '.txt', 'inexistant-' . $i, 'text/plain']);
    }
    [, $tropDocs] = $appel('a', 'travaux/' . $p . '/document/livrable', ['_csrf' => $ta, 'livrable' => (string) $annexes]);
    $dire('un livrable déjà plein (' . Travaux_essai_max('LIVRABLE_FICHIERS_MAX') . ' documents) : refusé', $oui(str_contains($tropDocs, 'Au plus ' . Travaux_essai_max('LIVRABLE_FICHIERS_MAX') . ' documents par livrable.')) . ' · ' . count($pdfsDuLivrable($annexes)), 'oui · 0');
    bd_run('DELETE FROM projet_fichiers WHERE livrable_id = ?', [$annexes]);

    bd_run('RENAME TABLE projet_livrables TO projet_livrables_essai');
    $renomme = true;
    [$c1, $docSans] = $appel('a', 'travaux/' . $p . '/document');
    [$c2, $pdfSans] = $appel('a', 'travaux/' . $p . '/document/pdf');
    [, $refusSans] = $appel('a', 'travaux/' . $p . '/document/livrable', ['_csrf' => $ta, 'livrable' => 'nouveau']);
    bd_run('RENAME TABLE projet_livrables_essai TO projet_livrables');
    $renomme = false;
    $dire('sans la migration des livrables : le document garde « Exporter en PDF » (qui marche), sans « Mettre dans les livrables » ; le refus ne plante pas',
        $c1 . ' · ' . $c2 . ' · ' . $oui(str_contains($docSans, '⬇ Exporter en PDF') && !str_contains($docSans, 'Mettre dans les livrables') && str_starts_with($pdfSans, '%PDF') && str_contains($refusSans, 'flash--erreur')), '200 · 200 · oui');

    echo "\n8c. Choisir ce qu'on met en tête du PDF\n";
    [, $lectureEntete] = $appel('a', 'travaux/' . $p . '/document');
    $dire('la page du document a « 🏷️ En-tête du PDF » : le titre (le nom du projet en grisé), « Document commun », rien de coché',
        $oui(str_contains($lectureEntete, '🏷️ En-tête du PDF') && str_contains($lectureEntete, 'id="document-entete-ouvrir"') && str_contains($lectureEntete, 'name="titre" maxlength="120" value="" placeholder="Projet des livrables (essai)"')
            && str_contains($lectureEntete, 'name="sous_titre" maxlength="120" value="Document commun"') && !str_contains($lectureEntete, 'name="membres" value="1" checked') && !str_contains($lectureEntete, 'name="date" value="1" checked')), 'oui');
    $dire('les deux panneaux (« En-tête » et « Livrables ») ont chacun leur case, dans leur bloc',
        (string) preg_match_all('#<div>\s*<input type="checkbox" id="document-(?:entete|livrable)-ouvrir" class="acces-bascule sr-only">\s*<div class="acces-panneau"#', $lectureEntete), '2');
    $autre = "Professeur : M. Martin\n\nClasse L3\nTroisième ligne\nQuatrième ligne (en trop)\n" . str_repeat('x', 300);
    [, $apresEntete, $adresseEntete] = $appel('a', 'travaux/' . $p . '/document/entete', ['_csrf' => $ta, 'titre' => 'Rapport sur les étoiles', 'sous_titre' => 'Cours d’astronomie', 'membres' => '1', 'date' => '1', 'autre' => $autre]);
    $garde = json_decode((string) bd_valeur('SELECT pdf_entete FROM projets WHERE id = ?', [$p]), true);
    $dire('« En-tête du PDF enregistré. », et on revient au document', $oui(str_contains($apresEntete, 'En-tête du PDF enregistré.') && str_ends_with($adresseEntete, '/travaux/' . $p . '/document')), 'oui');
    $dire('gardé : titre, sous-titre, membres, date, et seulement trois lignes d\'« autre » (sans la vide)',
        ($garde['titre'] ?? '-') . ' · ' . ($garde['sous_titre'] ?? '-') . ' · ' . ($garde['membres'] ?? '-') . ' · ' . ($garde['date'] ?? '-') . ' · ' . count(explode("\n", (string) ($garde['autre'] ?? ''))),
        'Rapport sur les étoiles · Cours d’astronomie · 1 · 1 · 3');
    $dire('une ligne trop longue est coupée à 150 caractères (ici, après « Classe L3 » et « Troisième ligne »)', '-' . implode(',', array_map('mb_strlen', explode("\n", (string) ($garde['autre'] ?? '')))), '-22,9,15');
    [, $lectureApres] = $appel('a', 'travaux/' . $p . '/document');
    $dire('et la page le remet dans les champs', $oui(str_contains($lectureApres, 'value="Rapport sur les étoiles"') && str_contains($lectureApres, 'value="Cours d’astronomie"') && str_contains($lectureApres, 'name="membres" value="1" checked') && str_contains($lectureApres, 'name="date" value="1" checked') && str_contains($lectureApres, 'Professeur : M. Martin')), 'oui');
    [, $pdfEntete] = $appel('a', 'travaux/' . $p . '/document/pdf');
    $texteEntete = $texteDuPdf($pdfEntete);
    $dire('le PDF : le titre choisi (et plus le nom du projet), le sous-titre choisi', $oui(str_contains($texteEntete, 'Rapport sur les étoiles Cours d’astronomie') && !str_contains($texteEntete, 'Projet des livrables')), 'oui');
    $dire('  puis « Groupe : » avec les membres, « Version du … », les lignes libres (la quatrième n\'y est pas)',
        $oui(preg_match('/Groupe : Tliv_Admin, Tliv_Membre Version du \d+ [^ ]+ \d{4} Professeur : M\. Martin Classe L3 Troisième ligne/u', $texteEntete) === 1 && !str_contains($texteEntete, 'Quatrième')), 'oui');
    $dire('  et le texte du document vient ensuite', $oui(preg_match('/Troisième ligne.*Bonjour à toute l’équipe/su', $texteEntete) === 1), 'oui');
    $appel('b', 'travaux/' . $p . '/document/livrable', ['_csrf' => $tb, 'livrable' => 'nouveau']);
    $dansLivrable = bd_all('SELECT id FROM projet_fichiers WHERE livrable_id = ? AND nom_origine = ?', [$livrableDoc(), $nomDuPdf]);
    [, $pdfLivrable] = $appel('a', 'travaux/fichiers/' . $dansLivrable[0]['id']);
    $dire('le PDF mis dans les livrables a le même en-tête', $count = count($dansLivrable) . ' · ' . $oui(str_contains($texteDuPdf($pdfLivrable), 'Rapport sur les étoiles Cours d’astronomie Groupe : Tliv_Admin, Tliv_Membre')), '1 · oui');
    $appel('b', 'travaux/' . $p . '/document/entete', ['_csrf' => $tb, 'titre' => '', 'sous_titre' => '', 'autre' => '']);
    $texteVide = $texteDuPdf($appel('a', 'travaux/' . $p . '/document/pdf')[1]);
    $dire('un simple membre règle aussi : titre vide = le nom du projet, sous-titre vidé = aucun, rien coché = aucune ligne de plus',
        $oui(str_starts_with(trim($texteVide), 'Projet des livrables (essai) Bonjour') && !str_contains($texteVide, 'Groupe :') && !str_contains($texteVide, 'Version du') && !str_contains($texteVide, 'Document commun')), 'oui');
    $appel('a', 'travaux/' . $p . '/document/entete', ['_csrf' => $ta, 'titre' => str_repeat('é', 200), 'sous_titre' => 'Document commun']);
    $dire('un titre trop long est coupé à 120 caractères', (string) mb_strlen((string) (json_decode((string) bd_valeur('SELECT pdf_entete FROM projets WHERE id = ?', [$p]), true)['titre'] ?? '')), '120');
    [$cod] = $appel('c', 'travaux/' . $p . '/document/entete', ['_csrf' => $csrf('c', 'compte'), 'titre' => 'Piraté']);
    $dire('quelqu\'un du dehors ne règle rien (404)', $cod . ' · ' . $oui(!str_contains((string) bd_valeur('SELECT pdf_entete FROM projets WHERE id = ?', [$p]), 'Piraté')), '404 · oui');
    $appel('a', 'travaux/' . $p . '/document/entete', ['_csrf' => $ta, 'titre' => '', 'sous_titre' => 'Document commun', 'membres' => '1', 'date' => '1']);

    bd_run('ALTER TABLE projets CHANGE pdf_entete pdf_entete_essai TEXT NULL');
    $colonneRenommee = true;
    [$c1, $docSansEntete] = $appel('a', 'travaux/' . $p . '/document');
    [$c2, $pdfSansEntete] = $appel('a', 'travaux/' . $p . '/document/pdf');
    [, $refusEntete] = $appel('a', 'travaux/' . $p . '/document/entete', ['_csrf' => $ta, 'titre' => 'Impossible']);
    bd_run('ALTER TABLE projets CHANGE pdf_entete_essai pdf_entete TEXT NULL');
    $colonneRenommee = false;
    $texteSans = $texteDuPdf($pdfSansEntete);
    $dire('sans la migration de l\'en-tête : le document n\'a pas « En-tête du PDF », le PDF garde l\'en-tête de départ, l\'enregistrement est refusé sans planter',
        $c1 . ' · ' . $c2 . ' · ' . $oui(!str_contains($docSansEntete, 'En-tête du PDF') && str_contains($docSansEntete, '⬇ Exporter en PDF') && str_starts_with(trim($texteSans), 'Projet des livrables (essai) Document commun Bonjour') && str_contains($refusEntete, 'flash--erreur')), '200 · 200 · oui');

    echo "\n9. Sans la migration, le site tourne\n";
    bd_run('RENAME TABLE projet_livrables TO projet_livrables_essai');
    $renomme = true;
    [$c1, $page1] = $appel('a', 'travaux/' . $p);
    [$c2] = $appel('a', 'travaux/' . $p . '/livrables');
    [$c3] = $appel('a', 'travaux/' . $p . '/fichiers');
    [$c4] = $appel('a', 'g/' . $lienPublic);
    bd_run('RENAME TABLE projet_livrables_essai TO projet_livrables');
    $renomme = false;
    $dire('le tableau, « Fichiers » et le lien public s\'ouvrent ; « Livrables » n\'est pas dans les onglets et répond 404',
        $c1 . ' ' . $c3 . ' ' . $c4 . ' · ' . $c2 . ' · ' . $oui(!str_contains($page1, '/livrables"')), '200 200 200 · 404 · oui');

    echo "\n10. Les quatre langues\n";
    $appel('a', 'travaux/livrables/' . $diaporama . '/fichiers', ['_csrf' => $ta, 'fichiers[0]' => $fichier('diapos.txt', 'D')]);
    $appel('a', 'travaux/livrables/' . $diaporama . '/rendre', ['_csrf' => $ta, 'rendu' => '1']);
    $appel('a', 'travaux/' . $p . '/livrables', ['_csrf' => $ta, 'titre' => 'En retard', 'echeance' => $jourPlus(-3)]);
    $attendus = [
        'en' => ['Deliverables', 'The final documents the group has to hand in', 'Create the deliverable', 'Deliverable title', 'Handed in', 'Not handed in yet', 'Drop the final document here', 'Delete the deliverable', '1 / 4 handed in', 'Due: ', 'Instructions'],
        'es' => ['Entregables', 'Los documentos finales que el grupo debe entregar', 'Crear el entregable', 'Título del entregable', 'Entregado', 'Aún no entregado', 'Suelta aquí el documento final', 'Eliminar el entregable', '1 / 4 entregados', 'Para el ', 'Instrucciones'],
        'de' => ['Abgaben', 'Die Enddokumente, die die Gruppe abgeben muss', 'Abgabe anlegen', 'Titel der Abgabe', 'Abgegeben', 'Noch nicht abgegeben', 'Das Enddokument hier ablegen', 'Abgabe löschen', '1 / 4 abgegeben', 'Fällig: ', 'Vorgabe'],
    ];
    foreach ($attendus as $langue => $mots) {
        $appel('a', 'compte/langue', ['_csrf' => $csrf('a', 'compte'), 'langue' => $langue]);
        [, $pageLangue] = $appel('a', 'travaux/' . $p . '/livrables');
        [, $formulaireLangue] = $appel('a', 'travaux/' . $p . '/livrables/nouveau');
        [, $documentLangue] = $appel('a', 'travaux/' . $p . '/document');
        [, , , $entetesPdfLangue] = $appel('a', 'travaux/' . $p . '/document/pdf');
        [, $tableauLangue] = $appel('a', 'travaux/' . $p);
        [, $refusLangue] = $appel('a', 'travaux/' . $p . '/livrables', ['_csrf' => $csrf('a', 'travaux/' . $p . '/livrables/nouveau'), 'titre' => ' ']);
        $manque = array_values(array_filter(array_slice($mots, 1), static fn (string $m): bool => !str_contains($pageLangue . $formulaireLangue, $m)));
        $dire($langue . ' : la page des livrables', ($manque === [] ? 'tout y est' : 'manque : ' . implode(' | ', $manque)) . ' · ' . $oui(str_contains($tableauLangue, '</span> ' . $mots[0])), 'tout y est · oui');
        $motsDoc = ['en' => ['Export as PDF', 'Put in the deliverables', 'Common%20document%20-%20Projet', 'PDF header', 'Group: Tliv_Admin', 'Version of '], 'es' => ['Exportar a PDF', 'Poner en los entregables', 'Documento%20com%C3%BAn%20-%20Projet', 'Encabezado del PDF', 'Grupo: Tliv_Admin', 'Versión del '], 'de' => ['Als PDF exportieren', 'In die Abgaben legen', 'Gemeinsames%20Dokument%20-%20Projet', 'PDF-Kopfzeile', 'Gruppe: Tliv_Admin', 'Version vom ']][$langue];
        [, $pdfLangue] = $appel('a', 'travaux/' . $p . '/document/pdf');
        $dire($langue . ' : l\'en-tête du PDF (bouton, et lignes « groupe » et « version » dans la langue)', $oui(str_contains($documentLangue, $motsDoc[3]) && !str_contains($documentLangue, 'En-tête du PDF') && str_contains($texteDuPdf($pdfLangue), $motsDoc[4]) && str_contains($texteDuPdf($pdfLangue), $motsDoc[5]) && !str_contains($texteDuPdf($pdfLangue), 'Groupe :')), 'oui');
        $dire($langue . ' : le document commun (boutons) et le nom de son PDF', $oui(str_contains($documentLangue, $motsDoc[0]) && str_contains($documentLangue, $motsDoc[1]) && str_contains($entetesPdfLangue, $motsDoc[2]) && !str_contains($documentLangue, 'Exporter en PDF') && !str_contains($documentLangue, 'Mettre dans les livrables') && !preg_match('/\\btr\\.do\\.[a-z_.]+/', $documentLangue)), 'oui');
        $dire($langue . ' : ni clé brute ni français resté (page, onglets, refus)',
            $oui(!preg_match('/\btr\.(lv|on)\.[a-z_.]+/', $pageLangue . $formulaireLangue . $tableauLangue . $refusLangue) && !str_contains($pageLangue . $formulaireLangue, 'Créer le livrable') && !str_contains($pageLangue . $formulaireLangue, 'Nouveau livrable') && !str_contains($pageLangue, 'Pas encore rendu') && !str_contains($refusLangue, 'Donnez un titre') && str_contains($refusLangue, 'flash--erreur')), 'oui');
    }
    $appel('a', 'compte/langue', ['_csrf' => $csrf('a', 'compte'), 'langue' => 'fr']);
    foreach (['fr', 'en', 'es', 'de'] as $langue) {
        preg_match_all("/^    '([^']+)' =>/m", (string) file_get_contents(dirname(__DIR__, 2) . '/lang/' . $langue . '.php'), $cles);
        $doubles = array_keys(array_filter(array_count_values($cles[1]), static fn (int $n): bool => $n > 1));
        $dire($langue . ' : aucune clé de langue écrite deux fois', $doubles === [] ? 'aucune' : implode(', ', array_slice($doubles, 0, 5)), 'aucune');
        $dire($langue . ' : le fichier de langue est de l\'UTF-8 valide', $oui(mb_check_encoding((string) file_get_contents(dirname(__DIR__, 2) . '/lang/' . $langue . '.php'), 'UTF-8')), 'oui');
    }
    // Les phrases que la page passe au script (window.MOTS) doivent faire du JSON valide : sinon tout le script de la page se casse, en silence.
    [, $pageMots] = $appel('a', 'travaux/' . $p . '/fichiers');
    $dire('window.MOTS, sur une page, est du JSON valide (avec « depot.joindre »)', $oui(preg_match('/window\.MOTS = (\{.*?\});<\/script>/s', $pageMots, $mm) === 1 && (json_decode($mm[1], true)['depot.joindre'] ?? '') !== ''), 'oui');
    $termine = true;
} finally {
    if ($renomme) {
        try { bd_run('RENAME TABLE projet_livrables_essai TO projet_livrables'); } catch (Throwable) {}
    }
    if ($colonneRenommee) {
        try { bd_run('ALTER TABLE projets CHANGE pdf_entete_essai pdf_entete TEXT NULL'); } catch (Throwable) {}
    }
    // Ménage : les fichiers rangés par le projet d'essai (livrables compris), puis le projet, les comptes et les fichiers temporaires.
    foreach ($projets as $idProjet) {
        foreach (bd_all('SELECT nom_stocke FROM projet_fichiers WHERE projet_id = ?', [$idProjet]) as $f) {
            $chemin = $dossierStockage . '/' . basename((string) $f['nom_stocke']);
            if (is_file($chemin)) { @unlink($chemin); }
        }
        bd_run('DELETE FROM projet_membres WHERE projet_id = ?', [$idProjet]);
        bd_run('DELETE FROM projets WHERE id = ? AND cree_par = ?', [$idProjet, $id('a')]);
    }
    foreach ($comptes as $c => $d) {
        if (isset($d['id'])) { bd_run('DELETE FROM users WHERE id = ? AND email = ? AND email LIKE ?', [$d['id'], $d['email'], '%@exemple-test.fr']); }
    }
    foreach ($temporaires as $chemin) { @unlink($chemin); }
    foreach (glob(__DIR__ . '/ck_tliv_*.txt') ?: [] as $fichierCookie) { @unlink($fichierCookie); }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'tliv-_@exemple-test.fr'")
        . ' · fichiers d’essai restants ' . count(glob($dossierStockage . '/*') ?: []) . ' dans storage/travaux (dont ceux d\'avant)' . "\n";
}

/** Un plafond de l'application, lu dans son code (le script d'essai ne charge rien d'elle). */
function Travaux_essai_max(string $nom): int
{
    return preg_match('/' . $nom . ' = (\d+)/', (string) file_get_contents(dirname(__DIR__, 2) . '/src/Travaux.php'), $m) ? (int) $m[1] : 0;
}
