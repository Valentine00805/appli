<?php
/**
 * Les dossiers de l'onglet « Fichiers » d'un travail de groupe (HTTP + base + disque) : créer un dossier (dans une fenêtre à part, à la racine ou dans un autre ;
 * les noms refusés, la profondeur, le plafond), s'y repérer (le chemin, les sous-dossiers et leurs compteurs), y déposer des fichiers, les déplacer, renommer
 * un dossier, le supprimer AVEC son contenu (sous-dossiers et fichiers, base et disque ; il faut retaper son nom ; par un administrateur, ou par qui l'a créé tant que tous les fichiers sont les siens), ce que voit le lien public, les livrables
 * qui restent à part, l'onglet retiré, un site dont la migration n'est pas passée, et les quatre langues.
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
    'a' => ['email' => 'tdos-a@exemple-test.fr', 'pseudo' => 'Tdos_Admin'],
    'b' => ['email' => 'tdos-b@exemple-test.fr', 'pseudo' => 'Tdos_Membre'],
    'c' => ['email' => 'tdos-c@exemple-test.fr', 'pseudo' => 'Tdos_Dehors'],
];
foreach ($comptes as $c => $d) {
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$d['email'], '%@exemple-test.fr']);
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)', [$d['email'], $d['pseudo'], password_hash($mdp, PASSWORD_DEFAULT), 'Essai ' . $c]);
    $comptes[$c]['id'] = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$d['email']]);
}
$id = static fn (string $c): int => $comptes[$c]['id'];

$jar = static fn (string $nom): string => __DIR__ . '/ck_tdos_' . $nom . '.txt';
$appel = static function (string $navigateur, string $chemin, ?array $post = null) use ($jar): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120,
        CURLOPT_COOKIEJAR => $jar($navigateur), CURLOPT_COOKIEFILE => $jar($navigateur)]);
    if ($post !== null) {
        curl_setopt($h, CURLOPT_POST, true);
        $avecFichier = array_filter($post, static fn (mixed $v): bool => $v instanceof CURLFile) !== [];
        curl_setopt($h, CURLOPT_POSTFIELDS, $avecFichier ? $post : http_build_query($post));
    }
    $corps = (string) curl_exec($h);
    $r = [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), $corps, (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';

$projets = [];
$renomme = false;
$temporaires = [];
$dossierStockage = dirname(__DIR__, 2) . '/storage/travaux';
try {
    foreach (['a', 'b', 'c'] as $c) {
        [, $connexion] = $appel($c, 'connexion');
        $appel($c, 'connexion', ['_csrf' => $jeton($connexion), 'identifiant' => $comptes[$c]['email'], 'mot_de_passe' => $mdp]);
    }
    $lienPublic = bin2hex(random_bytes(16));
    bd_run('INSERT INTO projets (nom, cree_par, jeton) VALUES (?, ?, ?)', ['Projet des dossiers (essai)', $id('a'), $lienPublic]);
    $p = (int) bd_valeur('SELECT id FROM projets WHERE cree_par = ? ORDER BY id DESC LIMIT 1', [$id('a')]);
    $projets[] = $p;
    bd_run("INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, 'admin', 'membre'), (?, ?, 'membre', 'membre')", [$p, $id('a'), $p, $id('b')]);
    bd_run('INSERT INTO projets (nom, cree_par, jeton) VALUES (?, ?, ?)', ['Autre projet des dossiers (essai)', $id('a'), bin2hex(random_bytes(16))]);
    $autre = (int) bd_valeur('SELECT id FROM projets WHERE cree_par = ? ORDER BY id DESC LIMIT 1', [$id('a')]);
    $projets[] = $autre;
    bd_run("INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, 'admin', 'membre')", [$autre, $id('a')]);

    bd_run('INSERT INTO projet_dossiers (projet_id, nom, created_at) VALUES (?, ?, NOW())', [$autre, 'Chez l\'autre projet']);
    $chezAutre = (int) bd_valeur('SELECT id FROM projet_dossiers WHERE projet_id = ?', [$autre]);
    $csrf = static fn (string $nav, string $chemin): string => $jeton($GLOBALS['appel']($nav, $chemin)[1]);
    $fichier = static function (string $nom, string $contenu) use (&$temporaires): CURLFile {
        $chemin = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tdos_' . bin2hex(random_bytes(4)) . '_' . $nom;
        file_put_contents($chemin, $contenu);
        $temporaires[] = $chemin;
        return new CURLFile($chemin, 'text/plain', $nom);
    };
    $existe = static function (string $nomStocke) use ($dossierStockage): bool { clearstatcache(true); return is_file($dossierStockage . '/' . $nomStocke); };
    /** Les dossiers du projet « nom => id », et un dossier par son nom. */
    $dossiers = static fn (int $projet = 0): array => array_column(bd_all('SELECT id, nom FROM projet_dossiers WHERE projet_id = ? ORDER BY id', [$projet ?: $GLOBALS['p']]), 'id', 'nom');
    $combien = static fn (): int => (int) bd_valeur('SELECT COUNT(*) FROM projet_dossiers WHERE projet_id = ?', [$GLOBALS['p']]);
    $dossierId = static fn (string $nom, int $projet = 0): int => (int) ($dossiers($projet)[$nom] ?? 0);
    $parentDe = static fn (int $dossier): string => (string) (bd_valeur('SELECT COALESCE(parent_id, \'racine\') FROM projet_dossiers WHERE id = ?', [$dossier]) ?? '-');
    $ou = static fn (string $nomFichier): string => (string) (bd_valeur('SELECT COALESCE(dossier_id, \'racine\') FROM projet_fichiers WHERE projet_id = ? AND nom_origine = ?', [$GLOBALS['p'], $nomFichier]) ?? '-');
    $racine = 'travaux/' . $p . '/fichiers';

    echo "\n1. L'onglet « Fichiers » avec des dossiers\n";
    [$cod, $page] = $appel('a', $racine);
    $dire('la page vide : le chemin « Fichiers », « + Nouveau dossier » (qui ouvre une fenêtre), « Aucun fichier pour l\'instant »',
        $cod . ' · ' . $oui(preg_match('#<strong>📎 Fichiers</strong>#u', $page) === 1 && preg_match('#<a class="bouton bouton--petit" href="[^"]*/travaux/' . $p . '/dossiers/nouveau" data-fenetre>\+ Nouveau dossier</a>#', $page) === 1 && str_contains($page, 'Aucun fichier pour l’instant')), '200 · oui');
    [$cod, $formulaire] = $appel('a', 'travaux/' . $p . '/dossiers/nouveau');
    $dire('la page du formulaire : « Nom du dossier », « Il sera créé dans : Fichiers », « Créer le dossier », « Annuler »',
        $cod . ' · ' . $oui(str_contains($formulaire, '📁 Nouveau dossier') && str_contains($formulaire, 'name="nom"') && str_contains($formulaire, 'Il sera créé dans : Fichiers') && str_contains($formulaire, 'Créer le dossier') && str_contains($formulaire, '>Annuler</a>')
            && str_contains($formulaire, 'action="/mon_appli/appli/travaux/' . $p . '/dossiers"')), '200 · oui');
    [, $fragment] = $appel('a', 'travaux/' . $p . '/dossiers/nouveau?fenetre=1');
    $dire('en fenêtre : le contenu seul, l\'envoi et « Annuler » restent dans la fenêtre', $oui(!str_contains($fragment, '<html') && str_contains($fragment, 'data-envoi-fenetre') && str_contains($fragment, 'data-fermer')), 'oui');
    [$cod] = $appel('c', 'travaux/' . $p . '/dossiers/nouveau');
    $dire('quelqu\'un du dehors : 404 (le formulaire et un dossier qui n\'existe pas)', $cod . ' · ' . $appel('a', $racine . '?dossier=999999')[0], '404 · 404');

    echo "\n2. Créer des dossiers\n";
    $ta = $jeton($formulaire);
    [, $vide, $adresseVide] = $appel('a', 'travaux/' . $p . '/dossiers', ['_csrf' => $ta, 'nom' => '   ']);
    $dire('sans nom : « Donnez un nom au dossier. », on reste sur le formulaire, rien n\'est créé', $oui(str_contains($vide, 'Donnez un nom au dossier.') && str_ends_with($adresseVide, '/dossiers/nouveau')) . ' · ' . count($dossiers()), 'oui · 0');
    [, $cree, $adresseCree] = $appel('a', 'travaux/' . $p . '/dossiers', ['_csrf' => $ta, 'nom' => 'Sources']);
    $dire('« Dossier créé. » : « Sources » à la racine, on revient aux fichiers où il est listé (« 0 fichier »)',
        $oui(str_contains($cree, 'Dossier créé.') && str_ends_with($adresseCree, '/travaux/' . $p . '/fichiers') && preg_match('#class="fichier__nom" href="[^"]*dossier=\d+"[^>]*>Sources</a>.*?0 fichier#su', $cree) === 1) . ' · ' . $parentDe($dossierId('Sources')), 'oui · racine');
    $sources = $dossierId('Sources');
    [, $double] = $appel('a', 'travaux/' . $p . '/dossiers', ['_csrf' => $ta, 'nom' => 'sources']);
    $dire('le même nom (majuscules ou non) au même endroit : « Un dossier porte déjà ce nom ici. »', $oui(str_contains($double, 'Un dossier porte déjà ce nom ici.')) . ' · ' . count($dossiers()), 'oui · 1');
    $appel('a', 'travaux/' . $p . '/dossiers', ['_csrf' => $ta, 'nom' => 'Brouillons', 'parent' => (string) $sources]);
    $appel('a', 'travaux/' . $p . '/dossiers', ['_csrf' => $ta, 'nom' => 'Diapositives']);
    $brouillons = $dossierId('Brouillons');
    $diapos = $dossierId('Diapositives');
    $dire('« Brouillons » dans « Sources » ; « Diapositives » à la racine', $parentDe($brouillons) . ' · ' . $parentDe($diapos) . ' · ' . $sources, $sources . ' · racine · ' . $sources);
    [, $memeNomAilleurs] = $appel('a', 'travaux/' . $p . '/dossiers', ['_csrf' => $ta, 'nom' => 'Brouillons']);
    $dire('mais un nom déjà pris dans un autre dossier est permis (« Brouillons » à la racine)', $oui(str_contains($memeNomAilleurs, 'Dossier créé.')) . ' · ' . $combien(), 'oui · 4');
    bd_run('DELETE FROM projet_dossiers WHERE projet_id = ? AND parent_id IS NULL AND nom = ?', [$p, 'Brouillons']);
    $appel('b', 'travaux/' . $p . '/dossiers', ['_csrf' => $csrf('b', $racine), 'nom' => 'Rapport', 'parent' => (string) $diapos]);
    $dire('un simple membre crée aussi des dossiers (« Rapport » dans « Diapositives »)', $parentDe($dossierId('Rapport')) . ' · ' . bd_valeur('SELECT cree_par FROM projet_dossiers WHERE nom = ?', ['Rapport']), $diapos . ' · ' . $id('b'));
    $appel('a', 'travaux/' . $p . '/dossiers', ['_csrf' => $ta, 'nom' => str_repeat('é', 200)]);
    $long = (string) bd_valeur('SELECT nom FROM projet_dossiers WHERE projet_id = ? ORDER BY id DESC LIMIT 1', [$p]);
    $dire('un nom trop long est coupé à 80 caractères', (string) mb_strlen($long), '80');
    bd_run('DELETE FROM projet_dossiers WHERE projet_id = ? AND CHAR_LENGTH(nom) = 80', [$p]);
    [$cod] = $appel('c', 'travaux/' . $p . '/dossiers', ['_csrf' => $csrf('c', 'compte'), 'nom' => 'Intrus']);
    [$cod2, $refusAutre] = $appel('a', 'travaux/' . $p . '/dossiers', ['_csrf' => $ta, 'nom' => 'Chez les autres', 'parent' => (string) $chezAutre]);
    $dire('quelqu\'un du dehors n\'en crée pas (404) ; un dossier parent qui n\'est pas de ce projet est refusé', $cod . ' · ' . $oui(str_contains($refusAutre, 'Ce dossier est introuvable.')) . ' · ' . $dossierId('Chez les autres') . $dossierId('Intrus'), '404 · oui · 00');
    // la profondeur : six niveaux au plus
    $parent = 0;
    $profondeur = [];
    for ($niveau = 1; $niveau <= 8; $niveau++) {
        $parentPost = $parent > 0 ? ['parent' => (string) $parent] : [];
        [, $reponse] = $appel('a', 'travaux/' . $p . '/dossiers', ['_csrf' => $ta, 'nom' => 'Niveau ' . $niveau] + $parentPost);
        $profondeur[] = str_contains($reponse, 'Les dossiers ne vont pas plus loin que 6 niveaux.') ? 'refusé' : 'ok';
        $parent = $dossierId('Niveau ' . $niveau);
        if ($parent === 0) { break; }
    }
    $dire('au plus six niveaux : le septième est refusé (« Les dossiers ne vont pas plus loin que 6 niveaux. »)', implode(' ', $profondeur), 'ok ok ok ok ok ok refusé');
    bd_run("DELETE FROM projet_dossiers WHERE projet_id = ? AND nom LIKE 'Niveau %' AND parent_id IS NULL", [$p]);
    $reste = Travaux_essai_max('DOSSIERS_MAX') - count($dossiers());
    for ($i = 0; $i < $reste; $i++) {
        bd_run('INSERT INTO projet_dossiers (projet_id, nom, created_at) VALUES (?, ?, NOW())', [$p, 'Rempli ' . $i]);
    }
    [, $trop] = $appel('a', 'travaux/' . $p . '/dossiers', ['_csrf' => $ta, 'nom' => 'De trop']);
    $dire('au plus ' . Travaux_essai_max('DOSSIERS_MAX') . ' dossiers par projet', $oui(str_contains($trop, 'Un projet a au plus ' . Travaux_essai_max('DOSSIERS_MAX') . ' dossiers.')) . ' · ' . count($dossiers()), 'oui · ' . Travaux_essai_max('DOSSIERS_MAX'));
    bd_run("DELETE FROM projet_dossiers WHERE projet_id = ? AND nom LIKE 'Rempli %'", [$p]);

    echo "\n3. S'y repérer\n";
    [, $racinePage] = $appel('a', $racine);
    $dire('à la racine : « Sources » et « Diapositives » (sans « Brouillons » ni « Rapport », qui sont plus bas), avec leurs compteurs',
        $oui(str_contains($racinePage, '>Sources</a><br>') && str_contains($racinePage, '>Diapositives</a><br>') && !str_contains($racinePage, '>Brouillons</a>') && !str_contains($racinePage, '>Rapport</a>')
            && preg_match('#>Sources</a><br>\s*<span class="fichier__meta">0 fichier · 1 dossier</span>#u', $racinePage) === 1), 'oui');
    [, $dansSources] = $appel('a', $racine . '?dossier=' . $sources);
    $dire('dans « Sources » : le chemin « Fichiers › 📁 Sources » (le premier est un lien), « Brouillons », « + Nouveau dossier » créé ici (parent=…)',
        $oui(preg_match('#<a href="[^"]*/travaux/' . $p . '/fichiers">📎 Fichiers</a>\s*<span aria-hidden="true">›</span>\s*<strong>📁 Sources</strong>#u', $dansSources) === 1 && str_contains($dansSources, '>Brouillons</a>')
            && str_contains($dansSources, '/dossiers/nouveau?parent=' . $sources)), 'oui');
    [, $profond] = $appel('a', $racine . '?dossier=' . $brouillons);
    $dire('dans « Brouillons » : le chemin complet, des liens pour remonter, « Ce dossier est vide »',
        $oui(preg_match('#📎 Fichiers</a>\s*<span aria-hidden="true">›</span>\s*<a href="[^"]*dossier=' . $sources . '"[^>]*>📁 Sources</a>\s*<span aria-hidden="true">›</span>\s*<strong>📁 Brouillons</strong>#u', $profond) === 1 && str_contains($profond, 'Ce dossier est vide')), 'oui');
    [, $formDansDossier] = $appel('a', 'travaux/' . $p . '/dossiers/nouveau?parent=' . $brouillons);
    $dire('« + Nouveau dossier » depuis « Brouillons » : « Il sera créé dans : Sources / Brouillons »', $oui(str_contains($formDansDossier, 'Il sera créé dans : Sources / Brouillons') && str_contains($formDansDossier, 'name="parent" value="' . $brouillons . '"')), 'oui');

    echo "\n4. Déposer dans un dossier\n";
    $tb = $csrf('b', $racine);
    [, $apresDepot, $adresseDepot] = $appel('a', $racine, ['_csrf' => $ta, 'dossier' => (string) $brouillons, 'fichiers[0]' => $fichier('plan.txt', 'Le plan.')]);
    $dire('« Brouillons » : « plan.txt » y est (dans la base, dans la page, on y reste), et le dépôt annonce le dossier',
        $ou('plan.txt') . ' · ' . $oui(str_contains($apresDepot, 'plan.txt') && str_ends_with($adresseDepot, '/fichiers?dossier=' . $brouillons)), $brouillons . ' · oui');
    $appel('a', $racine, ['_csrf' => $ta, 'fichiers[0]' => $fichier('racine.txt', 'A la racine.')]);
    $dire('sans dossier : à la racine', $ou('racine.txt'), 'racine');
    $appel('a', $racine, ['_csrf' => $ta, 'dossier' => (string) $autre, 'fichiers[0]' => $fichier('ailleurs.txt', 'x')]);
    $dire('avec un dossier qui n\'est pas du projet (ou qui n\'existe pas) : à la racine, pas perdu', $ou('ailleurs.txt'), 'racine');
    [, $racinePage] = $appel('a', $racine);
    [, $dansBrouillons] = $appel('a', $racine . '?dossier=' . $brouillons);
    $dire('la racine liste « racine.txt » (pas « plan.txt ») ; « Brouillons » liste « plan.txt » (pas « racine.txt »)',
        $oui(str_contains($racinePage, 'racine.txt') && !str_contains($racinePage, 'plan.txt') && str_contains($dansBrouillons, 'plan.txt') && !str_contains($dansBrouillons, 'racine.txt')), 'oui');
    $dire('« Sources » compte 0 fichier et 1 dossier ; « Brouillons » (vu depuis « Sources ») : 1 fichier',
        $oui(preg_match('#>Brouillons</a><br>\s*<span class="fichier__meta">1 fichier</span>#u', $appel('a', $racine . '?dossier=' . $sources)[1]) === 1), 'oui');
    $dire('le dépôt, dans un dossier, dit où il va', $oui(str_contains($dansBrouillons, 'Dans le dossier « Brouillons ».') && str_contains($dansBrouillons, 'name="dossier" value="' . $brouillons . '"')), 'oui');

    echo "\n5. Déplacer un fichier\n";
    $planId = (int) bd_valeur('SELECT id FROM projet_fichiers WHERE nom_origine = ?', ['plan.txt']);
    $racineId = (int) bd_valeur('SELECT id FROM projet_fichiers WHERE nom_origine = ?', ['racine.txt']);
    $dire('chaque fichier a un menu « ⋮ » (« Options pour « plan.txt » ») : « 📁 Déplacer » (fenêtre, depuis le dossier où l\'on est), « ⬇ Télécharger », « 🗑️ Supprimer » (avec confirmation) — et plus de boutons séparés',
        $oui(preg_match('#<summary class="ami-menu__bouton" aria-label="Options pour « plan.txt »"#u', $dansBrouillons) === 1
            && str_contains($dansBrouillons, 'data-fenetre href="/mon_appli/appli/travaux/fichiers/' . $planId . '/deplacer?depuis=' . $brouillons . '"')
            && str_contains($dansBrouillons, 'href="/mon_appli/appli/travaux/fichiers/' . $planId . '?telecharger=1"') && str_contains($dansBrouillons, '</span> Télécharger')
            && str_contains($dansBrouillons, 'action="/mon_appli/appli/travaux/fichiers/' . $planId . '/supprimer"') && str_contains($dansBrouillons, 'Supprimer « plan.txt » pour tout le groupe ?')
            && !str_contains($dansBrouillons, 'fichier__actions') && !str_contains($dansBrouillons, 'acces-panneau') && !str_contains($dansBrouillons, 'id="deplacer-')), 'oui');
    [$cod, $formDeplacer] = $appel('a', 'travaux/fichiers/' . $planId . '/deplacer?depuis=' . $brouillons);
    $dire('la page « Déplacer le fichier » : son nom, le menu (la racine, puis les dossiers avec leur chemin, le sien choisi), « Déplacer », « Annuler », d\'où l\'on vient',
        $cod . ' · ' . $oui(str_contains($formDeplacer, '📁 Déplacer le fichier') && str_contains($formDeplacer, 'plan.txt') && str_contains($formDeplacer, '<option value="0">📎 Fichiers</option>') && str_contains($formDeplacer, '<option value="' . $brouillons . '" selected>📁 Sources / Brouillons</option>')
            && str_contains($formDeplacer, '<option value="' . $diapos . '">📁 Diapositives</option>') && str_contains($formDeplacer, 'name="depuis" value="' . $brouillons . '"') && str_contains($formDeplacer, '>Annuler</a>')
            && str_contains($formDeplacer, 'action="/mon_appli/appli/travaux/fichiers/' . $planId . '/deplacer"')), '200 · oui');
    [, $fragmentDeplacer] = $appel('a', 'travaux/fichiers/' . $planId . '/deplacer?fenetre=1');
    [$codC] = $appel('c', 'travaux/fichiers/' . $planId . '/deplacer');
    $dire('en fenêtre : le contenu seul ; quelqu\'un du dehors : 404', $oui(!str_contains($fragmentDeplacer, '<html') && str_contains($fragmentDeplacer, 'data-envoi-fenetre') && str_contains($fragmentDeplacer, 'data-fermer')) . ' · ' . $codC, 'oui · 404');
    [, $racineSansDossiers] = $appel('a', $racine);
    $dire('à la racine, le fichier « racine.txt » a le même menu (Déplacer avec depuis=0)', $oui(str_contains($racineSansDossiers, '/travaux/fichiers/' . $racineId . '/deplacer?depuis=0')), 'oui');
    // L'aperçu : le nom du fichier ouvre une fenêtre d'aperçu (texte, vide, type inconnu = comme avant), ouverte à tout le groupe seulement.
    $dire('le nom d\'un fichier qu\'on sait montrer (« plan.txt ») ouvre l\'aperçu dans une fenêtre, plus le fichier brut',
        $oui(preg_match('#<a class="fichier__nom" href="/mon_appli/appli/travaux/fichiers/' . $planId . '/apercu" data-fenetre>plan\.txt</a>#', $dansBrouillons) === 1
            && !str_contains($dansBrouillons, 'href="/mon_appli/appli/travaux/fichiers/' . $planId . '" target="_blank"')), 'oui');
    [$codA, $apercuPlan] = $appel('a', 'travaux/fichiers/' . $planId . '/apercu');
    $dire('la page d\'aperçu de « plan.txt » : son contenu (« Le plan. »), « Télécharger », le retour aux fichiers',
        $codA . ' · ' . $oui(str_contains($apercuPlan, 'Le plan.') && str_contains($apercuPlan, '<pre class="apercu-brut">') && str_contains($apercuPlan, 'travaux/fichiers/' . $planId . '?telecharger=1') && str_contains($apercuPlan, '/travaux/' . $p . '/fichiers')), '200 · oui');
    [, $fragApercu] = $appel('a', 'travaux/fichiers/' . $planId . '/apercu?fenetre=1');
    $dire('en fenêtre : le contenu seul, large', $oui(!str_contains($fragApercu, '<html') && str_contains($fragApercu, 'data-document') && str_contains($fragApercu, 'Le plan.')), 'oui');
    [$codB] = $appel('b', 'travaux/fichiers/' . $planId . '/apercu');
    [$codD] = $appel('c', 'travaux/fichiers/' . $planId . '/apercu');
    $dire('un autre membre du groupe y accède ; quelqu\'un du dehors : 404', $codB . ' · ' . $codD, '200 · 404');
    $appel('a', $racine, ['_csrf' => $ta, 'fichiers[0]' => $fichier('vide.txt', ''), 'fichiers[1]' => $fichier('archive.zip', 'PK')]);
    $videId = (int) bd_valeur('SELECT id FROM projet_fichiers WHERE nom_origine = ?', ['vide.txt']);
    $zipId = (int) bd_valeur('SELECT id FROM projet_fichiers WHERE nom_origine = ?', ['archive.zip']);
    [, $racineAvecVide] = $appel('a', $racine);
    $dire('un fichier vide s\'ouvre en aperçu et le dit (« rien que l\'aperçu sache montrer ») ; une archive garde le lien d\'avant (onglet), sans aperçu',
        $oui(str_contains($racineAvecVide, 'travaux/fichiers/' . $videId . '/apercu') && str_contains($appel('a', 'travaux/fichiers/' . $videId . '/apercu')[1], 'ne contient rien que l’aperçu sache montrer')
            && !str_contains($racineAvecVide, 'travaux/fichiers/' . $zipId . '/apercu') && str_contains($racineAvecVide, 'href="/mon_appli/appli/travaux/fichiers/' . $zipId . '" target="_blank"')), 'oui');
    [, , $adresseZip] = $appel('a', 'travaux/fichiers/' . $zipId . '/apercu');
    $dire('l\'aperçu d\'une archive renvoie le fichier lui-même', $oui(str_ends_with($adresseZip, '/travaux/fichiers/' . $zipId)), 'oui');
    foreach ([$videId, $zipId] as $aRetirer) {
        $appel('a', 'travaux/fichiers/' . $aRetirer . '/supprimer', ['_csrf' => $ta]);
    }
    [, $fragLivr] = $appel('a', 'travaux/' . $p . '/livrables');
    $dire('l\'onglet Livrables se charge toujours (le nom des fichiers y est un lien partagé)', $oui(!str_contains($fragLivr, 'Fatal error') && !str_contains($fragLivr, 'Warning:')), 'oui');
    [$codX] = $appel('a', 'travaux/fichiers/999999999/apercu');
    $dire('un fichier qui n\'existe pas : 404', (string) $codX, '404');
    [, $deplace, $adresseDeplace] = $appel('b', 'travaux/fichiers/' . $planId . '/deplacer', ['_csrf' => $tb, 'dossier' => (string) $diapos]);
    $dire('b déplace « plan.txt » dans « Diapositives » : « Fichier déplacé. », on revient d\'où il part (« Brouillons »)',
        $oui(str_contains($deplace, 'Fichier déplacé.') && str_ends_with($adresseDeplace, '/fichiers?dossier=' . $brouillons)) . ' · ' . $ou('plan.txt'), 'oui · ' . $diapos);
    [, , $adresseDepuis] = $appel('a', 'travaux/fichiers/' . $planId . '/deplacer', ['_csrf' => $ta, 'dossier' => '0', 'depuis' => (string) $diapos]);
    $dire('et à la racine (0), depuis « Diapositives » : on y revient (le « depuis » du formulaire)', $ou('plan.txt') . ' · ' . $oui(str_ends_with($adresseDepuis, '/fichiers?dossier=' . $diapos)), 'racine · oui');
    $appel('a', 'travaux/fichiers/' . $planId . '/deplacer', ['_csrf' => $ta, 'dossier' => (string) $brouillons]);
    $dire('et de nouveau dans « Brouillons »', $ou('plan.txt'), (string) $brouillons);
    [$cod] = $appel('a', 'travaux/fichiers/' . $planId . '/deplacer', ['_csrf' => $ta, 'dossier' => (string) $chezAutre]);
    $dire('vers un dossier d\'un autre projet : refusé (404), le fichier ne bouge pas', $cod . ' · ' . $ou('plan.txt'), '404 · ' . $brouillons);
    [$cod] = $appel('c', 'travaux/fichiers/' . $planId . '/deplacer', ['_csrf' => $csrf('c', 'compte'), 'dossier' => '0']);
    $dire('quelqu\'un du dehors ne déplace rien (404)', $cod . ' · ' . $ou('plan.txt'), '404 · ' . $brouillons);
    [$cod, $contenu] = $appel('b', 'travaux/fichiers/' . $planId);
    $dire('déplacé ou non, le fichier se télécharge toujours (même adresse)', $cod . ' · ' . $oui(str_contains($contenu, 'Le plan.')), '200 · oui');

    echo "\n6. Retirer un fichier d'un dossier\n";
    $appel('a', $racine, ['_csrf' => $ta, 'dossier' => (string) $diapos, 'fichiers[0]' => $fichier('a-retirer.txt', 'R')]);
    $retirer = (int) bd_valeur('SELECT id FROM projet_fichiers WHERE nom_origine = ?', ['a-retirer.txt']);
    $stocke = (string) bd_valeur('SELECT nom_stocke FROM projet_fichiers WHERE id = ?', [$retirer]);
    [, $apresSuppression, $adresseSupp] = $appel('a', 'travaux/fichiers/' . $retirer . '/supprimer', ['_csrf' => $ta]);
    $dire('supprimé depuis « Diapositives » : on y reste, le fichier a disparu de la base et du disque',
        $oui(str_ends_with($adresseSupp, '/fichiers?dossier=' . $diapos) && !str_contains(preg_replace('#<div class="flash.*?</div>#s', '', $apresSuppression), 'a-retirer.txt')) . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_fichiers WHERE id = ?', [$retirer]) . ' · ' . $oui(!$existe($stocke)), 'oui · 0 · oui');

    echo "\n7. Renommer un dossier\n";
    [, $panneau] = $appel('a', $racine . '?dossier=' . $sources);
    [, $pageRacine7] = $appel('a', $racine);
    $dire('à la racine : chaque dossier a un menu « ⋮ » (« Options du dossier… ») avec « ✎ Renommer » (fenêtre, depuis=0) et « 🗑️ Supprimer le dossier » (une fenêtre de confirmation)',
        (string) preg_match_all('#<details class="ami-menu">\\s*<summary class="ami-menu__bouton" aria-label="Options du dossier « [^"]+ »"#u', $pageRacine7) . ' · ' . $oui(str_contains($pageRacine7, 'href="/mon_appli/appli/travaux/dossiers/' . $sources . '/renommer?depuis=0"') && str_contains($pageRacine7, 'data-fenetre href="/mon_appli/appli/travaux/dossiers/' . $sources . '/renommer?depuis=0"')
            && preg_match('#data-fenetre href="/mon_appli/appli/travaux/dossiers/' . $sources . '/supprimer\\?depuis=0">\\s*<span aria-hidden="true">🗑️</span> Supprimer le dossier#u', $pageRacine7) === 1 && !str_contains($pageRacine7, 'action="/mon_appli/appli/travaux/dossiers/' . $sources . '/supprimer"')), '2 · oui');
    $dire('dans « Sources » : le même menu, juste après le nom dans le chemin (plus de carte en dessous) et sur « Brouillons », avec depuis=' . $sources . ' ; sans l\'ancien panneau « Modifier »',
        (string) preg_match_all('#renommer\\?depuis=' . $sources . '"#', $panneau) . ' · ' . $oui(!str_contains($panneau, 'id="dossier-ouvrir"') && !str_contains($panneau, '✎ Modifier') && str_contains($panneau, 'dossiers/' . $brouillons . '/supprimer?depuis=' . $sources)
            && preg_match('#<strong>📁 Sources</strong>\\s*<details class="ami-menu">#u', $panneau) === 1 && !str_contains($panneau, '<h2>📁 Sources</h2>')), '2 · oui');
    [$cod, $formRenommer] = $appel('a', 'travaux/dossiers/' . $sources . '/renommer?depuis=' . $sources);
    $dire('la page « Renommer le dossier » : le nom rempli, son chemin, « Enregistrer », « Annuler », et d\'où l\'on vient',
        $cod . ' · ' . $oui(str_contains($formRenommer, '✎ Renommer le dossier') && str_contains($formRenommer, 'value="Sources"') && str_contains($formRenommer, 'name="depuis" value="' . $sources . '"') && str_contains($formRenommer, 'Enregistrer') && str_contains($formRenommer, '>Annuler</a>')
            && str_contains($formRenommer, 'action="/mon_appli/appli/travaux/dossiers/' . $sources . '/modifier"')), '200 · oui');
    [, $fragmentRenommer] = $appel('a', 'travaux/dossiers/' . $sources . '/renommer?depuis=0&fenetre=1');
    [$cod2] = $appel('c', 'travaux/dossiers/' . $sources . '/renommer');
    $dire('en fenêtre : le contenu seul ; quelqu\'un du dehors : 404', $oui(!str_contains($fragmentRenommer, '<html') && str_contains($fragmentRenommer, 'data-envoi-fenetre') && str_contains($fragmentRenommer, 'data-fermer')) . ' · ' . $cod2, 'oui · 404');
    [, $renommeRacine, $adresseRacine] = $appel('a', 'travaux/dossiers/' . $diapos . '/modifier', ['_csrf' => $ta, 'nom' => 'Diaporamas', 'depuis' => '0']);
    $dire('renommé depuis la racine (depuis=0) : « Dossier renommé. », on revient à la racine', $oui(str_contains($renommeRacine, 'Dossier renommé.') && str_ends_with($adresseRacine, '/travaux/' . $p . '/fichiers')) . ' · ' . bd_valeur('SELECT nom FROM projet_dossiers WHERE id = ?', [$diapos]), 'oui · Diaporamas');
    $appel('a', 'travaux/dossiers/' . $diapos . '/modifier', ['_csrf' => $ta, 'nom' => 'Diapositives', 'depuis' => '0']);
    [, $renommeDedans, $adresseDedans] = $appel('a', 'travaux/dossiers/' . $brouillons . '/modifier', ['_csrf' => $ta, 'nom' => 'Brouillons v2', 'depuis' => (string) $sources]);
    $dire('renommé depuis « Sources » (depuis=…) : on revient dans « Sources »', $oui(str_ends_with($adresseDedans, '/fichiers?dossier=' . $sources) && str_contains($renommeDedans, 'Brouillons v2')), 'oui');
    $appel('a', 'travaux/dossiers/' . $brouillons . '/modifier', ['_csrf' => $ta, 'nom' => 'Brouillons', 'depuis' => (string) $sources]);
    [, $refuseDepuis, $adresseRefus] = $appel('a', 'travaux/dossiers/' . $brouillons . '/modifier', ['_csrf' => $ta, 'nom' => ' ', 'depuis' => (string) $sources]);
    $dire('un nom vide : « Donnez un nom au dossier. », on reste sur la page « Renommer » (avec d\'où l\'on vient)', $oui(str_contains($refuseDepuis, 'Donnez un nom au dossier.') && str_ends_with($adresseRefus, '/renommer?depuis=' . $sources)) . ' · ' . bd_valeur('SELECT nom FROM projet_dossiers WHERE id = ?', [$brouillons]), 'oui · Brouillons');
    [, $autreDepuis, $adresseAutre] = $appel('a', 'travaux/dossiers/' . $brouillons . '/modifier', ['_csrf' => $ta, 'nom' => 'Brouillons', 'depuis' => (string) $chezAutre]);
    $dire('un « depuis » qui n\'est pas de ce projet : on revient à la racine, sans rien casser', $oui(str_contains($autreDepuis, 'Dossier renommé.') && str_ends_with($adresseAutre, '/travaux/' . $p . '/fichiers')), 'oui');
    [, $renomme1, $adresseRen] = $appel('b', 'travaux/dossiers/' . $sources . '/modifier', ['_csrf' => $tb, 'nom' => 'Sources et notes']);
    $dire('un membre renomme : « Dossier renommé. », on reste dans le dossier', $oui(str_contains($renomme1, 'Dossier renommé.') && str_ends_with($adresseRen, '/fichiers?dossier=' . $sources)) . ' · ' . bd_valeur('SELECT nom FROM projet_dossiers WHERE id = ?', [$sources]), 'oui · Sources et notes');
    [, $renomme2] = $appel('a', 'travaux/dossiers/' . $sources . '/modifier', ['_csrf' => $ta, 'nom' => 'diapositives']);
    $dire('le nom d\'un autre dossier du même niveau : refusé', $oui(str_contains($renomme2, 'Un dossier porte déjà ce nom ici.')) . ' · ' . bd_valeur('SELECT nom FROM projet_dossiers WHERE id = ?', [$sources]), 'oui · Sources et notes');
    [, $renomme3] = $appel('a', 'travaux/dossiers/' . $sources . '/modifier', ['_csrf' => $ta, 'nom' => 'SOURCES ET NOTES']);
    $dire('mais le sien, autrement écrit, est permis', $oui(str_contains($renomme3, 'Dossier renommé.')) . ' · ' . bd_valeur('SELECT nom FROM projet_dossiers WHERE id = ?', [$sources]), 'oui · SOURCES ET NOTES');
    $appel('a', 'travaux/dossiers/' . $sources . '/modifier', ['_csrf' => $ta, 'nom' => 'Sources']);
    [$cod] = $appel('c', 'travaux/dossiers/' . $sources . '/modifier', ['_csrf' => $csrf('c', 'compte'), 'nom' => 'Piraté']);
    $dire('quelqu\'un du dehors ne renomme rien (404)', $cod . ' · ' . bd_valeur('SELECT nom FROM projet_dossiers WHERE id = ?', [$sources]), '404 · Sources');

    echo "\n8. Supprimer un dossier : avec ce qu'il contient, en le redemandant\n";
    // « plan.txt » sort de « Brouillons » (il sert plus loin) ; on met un fichier de chacun dans « Sources » et « Brouillons ».
    $appel('a', 'travaux/fichiers/' . $planId . '/deplacer', ['_csrf' => $ta, 'dossier' => '0']);
    $appel('a', $racine, ['_csrf' => $ta, 'dossier' => (string) $sources, 'fichiers[0]' => $fichier('src-a.txt', 'A.')]);
    $appel('b', $racine, ['_csrf' => $tb, 'dossier' => (string) $brouillons, 'fichiers[0]' => $fichier('src-b.txt', 'B.')]);
    $stockePlan = (string) bd_valeur('SELECT nom_stocke FROM projet_fichiers WHERE id = ?', [$planId]);
    $stockeA = (string) bd_valeur('SELECT nom_stocke FROM projet_fichiers WHERE nom_origine = ?', ['src-a.txt']);
    $stockeB = (string) bd_valeur('SELECT nom_stocke FROM projet_fichiers WHERE nom_origine = ?', ['src-b.txt']);
    $lignesFichiers = static fn (string ...$noms): int => (int) bd_valeur('SELECT COUNT(*) FROM projet_fichiers WHERE projet_id = ? AND nom_origine IN (' . implode(',', array_fill(0, count($noms), '?')) . ')', array_merge([$GLOBALS['p']], $noms));
    $urlSupp = static fn (int $d): string => 'travaux/dossiers/' . $d . '/supprimer';

    [$cod, $pageSupp] = $appel('a', $urlSupp($sources) . '?depuis=0');
    $dire('l\'administrateur ouvre la page : le titre, l\'avertissement, ce qui part (2 fichiers, 1 sous-dossier), leurs noms, le nom à retaper, la dernière confirmation',
        $cod . ' · ' . $oui(str_contains($pageSupp, 'Supprimer le dossier « Sources » ?') && str_contains($pageSupp, 'sera supprimé pour tout le groupe, définitivement') && str_contains($pageSupp, '2 fichiers (') && str_contains($pageSupp, '1 sous-dossier')
            && str_contains($pageSupp, 'src-a.txt') && str_contains($pageSupp, 'src-b.txt') && str_contains($pageSupp, 'Pour confirmer, tapez le nom du dossier : Sources') && str_contains($pageSupp, 'name="confirmation"') && str_contains($pageSupp, 'Supprimer définitivement')
            && str_contains($pageSupp, 'action="/mon_appli/appli/travaux/dossiers/' . $sources . '/supprimer"') && str_contains($pageSupp, 'data-confirmation="Dernière vérification : supprimer définitivement le dossier « Sources » et tout ce qu’il contient ?"')), '200 · oui');
    [, $fragSupp] = $appel('a', $urlSupp($sources) . '?fenetre=1&depuis=0');
    [$codC] = $appel('c', $urlSupp($sources));
    $dire('en fenêtre : le contenu seul ; quelqu\'un du dehors : 404', $oui(!str_contains($fragSupp, '<html') && str_contains($fragSupp, 'data-envoi-fenetre') && str_contains($fragSupp, 'data-fermer')) . ' · ' . $codC, 'oui · 404');
    [, $pageSuppB] = $appel('b', $urlSupp($sources));
    $dire('b (ni le créateur de « Sources », ni administrateur) ouvre la page : le refus, et pas de champ pour confirmer', $oui(str_contains($pageSuppB, 'Seul celui qui a créé le dossier, ou un administrateur, peut le supprimer.') && !str_contains($pageSuppB, 'name="confirmation"')), 'oui');
    [, $refusB] = $appel('b', $urlSupp($sources), ['_csrf' => $tb, 'confirmation' => 'Sources']);
    $dire('et même en postant le bon nom : rien ne part (4 dossiers, 2 fichiers)', $oui(str_contains($refusB, 'Seul celui qui a créé le dossier')) . ' · ' . $combien() . ' · ' . $lignesFichiers('src-a.txt', 'src-b.txt'), 'oui · 4 · 2');
    [, $sansNom, $adresseSans] = $appel('a', $urlSupp($sources), ['_csrf' => $ta, 'confirmation' => '', 'depuis' => '0']);
    $dire('l\'administrateur sans retaper le nom : « Le nom tapé n’est pas celui du dossier », on reste sur la page, rien ne part',
        $oui(str_contains($sansNom, 'Le nom tapé n’est pas celui du dossier : rien n’a été supprimé.') && str_ends_with($adresseSans, '/supprimer?depuis=0')) . ' · ' . $combien() . ' · ' . $lignesFichiers('src-a.txt', 'src-b.txt'), 'oui · 4 · 2');
    [, $malNom] = $appel('a', $urlSupp($sources), ['_csrf' => $ta, 'confirmation' => 'Diapositives', 'depuis' => '0']);
    $dire('avec le nom d\'un autre dossier : pareil', $oui(str_contains($malNom, 'Le nom tapé n’est pas celui du dossier')) . ' · ' . $combien() . ' · ' . $lignesFichiers('src-a.txt', 'src-b.txt') . ' · ' . $oui($existe($stockeA) && $existe($stockeB)), 'oui · 4 · 2 · oui');
    [, $supprime, $adresseSupprime] = $appel('a', $urlSupp($sources), ['_csrf' => $ta, 'confirmation' => '  sources ', 'depuis' => '0']);
    $dire('avec le bon nom (majuscules et espaces sans importance) : « Dossier supprimé, avec ses 2 fichiers. », retour à la racine',
        $oui(str_contains($supprime, 'Dossier supprimé, avec ses 2 fichiers.') && str_ends_with($adresseSupprime, '/travaux/' . $p . '/fichiers')), 'oui');
    $dire('« Sources » et « Brouillons » ont disparu, avec leurs deux fichiers — dans la base ET sur le disque',
        bd_valeur('SELECT COUNT(*) FROM projet_dossiers WHERE id IN (?, ?)', [$sources, $brouillons]) . ' · ' . $lignesFichiers('src-a.txt', 'src-b.txt') . ' · ' . $oui(!$existe($stockeA) && !$existe($stockeB)), '0 · 0 · oui');
    $dire('« plan.txt » (à la racine) et « Diapositives » ne sont pas touchés', $ou('plan.txt') . ' · ' . $oui($existe($stockePlan)) . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_dossiers WHERE id = ?', [$diapos]), 'racine · oui · 1');

    // Qui a créé un dossier, sans être administrateur, ne supprime pas le travail des autres.
    $rapport = $dossierId('Rapport');
    $appel('a', $racine, ['_csrf' => $ta, 'dossier' => (string) $rapport, 'fichiers[0]' => $fichier('chez-b.txt', 'x')]);
    $chezB = (int) bd_valeur('SELECT id FROM projet_fichiers WHERE nom_origine = ?', ['chez-b.txt']);
    $stockeChezB = (string) bd_valeur('SELECT nom_stocke FROM projet_fichiers WHERE id = ?', [$chezB]);
    [, $pageB2] = $appel('b', $urlSupp($rapport));
    [, $refusAutres] = $appel('b', $urlSupp($rapport), ['_csrf' => $tb, 'confirmation' => 'Rapport']);
    $dire('b a créé « Rapport » mais un fichier de a y est : « seul un administrateur… », ni champ ni suppression',
        $oui(str_contains($pageB2, 'déposés par d’autres membres') && !str_contains($pageB2, 'name="confirmation"') && str_contains($refusAutres, 'déposés par d’autres membres')) . ' · ' . $oui($dossierId('Rapport') === $rapport) . ' · ' . $oui($existe($stockeChezB)), 'oui · oui · oui');
    $appel('a', 'travaux/fichiers/' . $chezB . '/supprimer', ['_csrf' => $ta]);
    $appel('b', $racine, ['_csrf' => $tb, 'dossier' => (string) $rapport, 'fichiers[0]' => $fichier('mien-b.txt', 'y')]);
    $stockeMien = (string) bd_valeur('SELECT nom_stocke FROM projet_fichiers WHERE nom_origine = ?', ['mien-b.txt']);
    [, $pageB3] = $appel('b', $urlSupp($rapport));
    $dire('quand tous les fichiers sont les siens, b peut : la page propose le champ du nom (1 fichier)', $oui(str_contains($pageB3, 'name="confirmation"') && str_contains($pageB3, '1 fichier (') && str_contains($pageB3, 'mien-b.txt')), 'oui');
    $appel('b', $urlSupp($rapport), ['_csrf' => $tb, 'confirmation' => 'autre chose']);
    $dire('mais pas sans le bon nom', $oui($dossierId('Rapport') === $rapport) . ' · ' . $lignesFichiers('mien-b.txt'), 'oui · 1');
    [, $supprimeB] = $appel('b', $urlSupp($rapport), ['_csrf' => $tb, 'confirmation' => 'Rapport', 'depuis' => (string) $rapport]);
    $dire('avec le bon nom : « Dossier supprimé, avec son fichier. », et l\'on revient dans « Diapositives » (son parent : le dossier où l\'on était n\'existe plus)',
        $oui(str_contains($supprimeB, 'Dossier supprimé, avec son fichier.')) . ' · ' . $oui($dossierId('Rapport') === 0) . ' · ' . $lignesFichiers('mien-b.txt') . ' · ' . $oui(!$existe($stockeMien)), 'oui · oui · 0 · oui');

    // Un dossier vide : une simple confirmation, sans nom à retaper.
    $appel('b', 'travaux/' . $p . '/dossiers', ['_csrf' => $tb, 'nom' => 'Vide']);
    $vide = $dossierId('Vide');
    [, $pageVide] = $appel('b', $urlSupp($vide));
    $dire('un dossier vide : « Ce dossier est vide : il sera simplement retiré. », pas de nom à retaper', $oui(str_contains($pageVide, 'Ce dossier est vide : il sera simplement retiré.') && !str_contains($pageVide, 'name="confirmation"') && str_contains($pageVide, 'Supprimer le dossier</button>')), 'oui');
    [, $supprimeVide, $adresseVide] = $appel('b', $urlSupp($vide), ['_csrf' => $tb, 'depuis' => '0']);
    $dire('et il part sans le nom : « Dossier supprimé. »', $oui(str_contains($supprimeVide, 'Dossier supprimé.') && !str_contains($supprimeVide, 'avec ses') && str_ends_with($adresseVide, '/travaux/' . $p . '/fichiers')) . ' · ' . $oui($dossierId('Vide') === 0), 'oui · oui');

    // Les sous-dossiers, à tous les niveaux : tout part, pas seulement le premier étage.
    $appel('a', 'travaux/' . $p . '/dossiers', ['_csrf' => $ta, 'nom' => 'Niveau 1']);
    $n1 = $dossierId('Niveau 1');
    $appel('a', 'travaux/' . $p . '/dossiers', ['_csrf' => $ta, 'nom' => 'Niveau 2', 'parent' => (string) $n1]);
    $n2 = $dossierId('Niveau 2');
    $appel('a', 'travaux/' . $p . '/dossiers', ['_csrf' => $ta, 'nom' => 'Niveau 3', 'parent' => (string) $n2]);
    $n3 = $dossierId('Niveau 3');
    $appel('a', $racine, ['_csrf' => $ta, 'dossier' => (string) $n3, 'fichiers[0]' => $fichier('tout-au-fond.txt', 'z')]);
    $stockeFond = (string) bd_valeur('SELECT nom_stocke FROM projet_fichiers WHERE nom_origine = ?', ['tout-au-fond.txt']);
    [, $pageNiveaux] = $appel('a', $urlSupp($n1));
    $dire('« Niveau 1 » contient 2 sous-dossiers et le fichier du fond (3 niveaux plus bas) : la page les compte', $oui(str_contains($pageNiveaux, '1 fichier (') && str_contains($pageNiveaux, '2 sous-dossiers') && str_contains($pageNiveaux, 'tout-au-fond.txt')), 'oui');
    $appel('a', $urlSupp($n1), ['_csrf' => $ta, 'confirmation' => 'Niveau 1', 'depuis' => (string) $n3]);
    $dire('supprimé depuis « Niveau 3 » (qui disparaît) : les trois niveaux et le fichier ont disparu, base et disque',
        bd_valeur('SELECT COUNT(*) FROM projet_dossiers WHERE id IN (?, ?, ?)', [$n1, $n2, $n3]) . ' · ' . $lignesFichiers('tout-au-fond.txt') . ' · ' . $oui(!$existe($stockeFond)), '0 · 0 · oui');
    [$codX] = $appel('c', $urlSupp($diapos), ['_csrf' => $csrf('c', 'compte'), 'confirmation' => 'Diapositives']);
    $dire('quelqu\'un du dehors ne supprime rien (404)', $codX . ' · ' . bd_valeur('SELECT COUNT(*) FROM projet_dossiers WHERE id = ?', [$diapos]), '404 · 1');
    $appel('a', 'travaux/' . $p . '/dossiers', ['_csrf' => $ta, 'nom' => 'Tmp']);
    $tmp = $dossierId('Tmp');
    $appel('a', 'travaux/fichiers/' . $planId . '/deplacer', ['_csrf' => $ta, 'dossier' => (string) $tmp]);
    bd_run('DELETE FROM projet_dossiers WHERE id = ?', [$tmp]);
    $dire('et si un dossier disparaît directement de la base, ses fichiers retombent à la racine (rien de perdu)', $ou('plan.txt') . ' · ' . $oui($existe($stockePlan)), 'racine · oui');

    echo "\n9. Ce qui reste à part, et ce que voit le lien public\n";
    bd_run('INSERT INTO projet_livrables (projet_id, titre, position, created_at) VALUES (?, ?, 1, NOW())', [$p, 'Un livrable']);
    $livrable = (int) bd_valeur('SELECT id FROM projet_livrables WHERE projet_id = ?', [$p]);
    $appel('a', 'travaux/livrables/' . $livrable . '/fichiers', ['_csrf' => $ta, 'fichiers[0]' => $fichier('livrable-final.txt', 'Final.')]);
    $livraison = bd_all('SELECT dossier_id FROM projet_fichiers WHERE nom_origine = ?', ['livrable-final.txt'])[0] ?? ['dossier_id' => 'absent'];
    [, $racinePage] = $appel('a', $racine);
    $dire('un document de livrable n\'est dans aucun dossier ni dans « Fichiers » (il reste dans son livrable)', ($livraison['dossier_id'] ?? 'racine') . ' · ' . $oui(!str_contains($racinePage, 'livrable-final.txt')), 'racine · oui');
    [, $public] = $appel('a', 'g/' . $lienPublic);
    $dire('le lien public liste tous les fichiers du projet, à plat (« plan.txt » rangé ou non, « racine.txt »), sans les dossiers ni les livrables',
        $oui(str_contains($public, 'plan.txt') && str_contains($public, 'racine.txt') && !str_contains($public, 'livrable-final.txt') && !str_contains($public, '📁')), 'oui');
    [$cod, $servi] = $appel('a', 'g/' . $lienPublic . '/fichiers/' . $planId);
    $dire('et sert un fichier rangé dans un dossier', $cod . ' · ' . $oui(str_contains($servi, 'Le plan.')), '200 · oui');

    echo "\n10. L'onglet retiré, la migration absente\n";
    bd_run('UPDATE projets SET onglets = ? WHERE id = ?', ['document', $p]);
    [$c1] = $appel('a', $racine);
    [$c2] = $appel('a', 'travaux/' . $p . '/dossiers/nouveau');
    bd_run('UPDATE projets SET onglets = NULL WHERE id = ?', [$p]);
    $dire('sans l\'onglet « Fichiers » : la page des fichiers et le formulaire de dossier répondent 404', $c1 . ' · ' . $c2, '404 · 404');
    bd_run('RENAME TABLE projet_dossiers TO projet_dossiers_essai');
    $renomme = true;
    [$c3, $sansMigration] = $appel('a', $racine);
    [$c4] = $appel('a', 'travaux/' . $p . '/dossiers/nouveau');
    [, $deposeSans] = $appel('a', $racine, ['_csrf' => $ta, 'fichiers[0]' => $fichier('sans-dossiers.txt', 'S')]);
    [$c5] = $appel('a', 'travaux/fichiers/' . $planId . '/deplacer', ['_csrf' => $ta, 'dossier' => '0']);
    bd_run('RENAME TABLE projet_dossiers_essai TO projet_dossiers');
    $renomme = false;
    $dire('sans la migration des dossiers : « Fichiers » liste tous les fichiers en vrac (rangés ou non), sans « Nouveau dossier » ; dépôt possible ; le formulaire et « Déplacer » répondent 404',
        $c3 . ' · ' . $oui(str_contains($sansMigration, 'plan.txt') && str_contains($sansMigration, 'racine.txt') && !str_contains($sansMigration, 'Nouveau dossier') && !str_contains($sansMigration, 'Déplacer') && str_contains($deposeSans, 'sans-dossiers.txt')) . ' · ' . $c4 . ' · ' . $c5, '200 · oui · 404 · 404');

    echo "\n11. Les quatre langues\n";
    $appel('a', 'travaux/' . $p . '/dossiers', ['_csrf' => $ta, 'nom' => 'Pour les langues']);
    $pourLesLangues = $dossierId('Pour les langues');
    $attendus = [
        'en' => ['Files', '+ New folder', 'New folder', 'Folder name', 'It will be created in: Files', 'Create the folder', 'Move', 'Move to', 'Delete the folder', 'In the “Pour les langues” folder.', 'This folder is empty', 'Folder created.', 'Folder renamed.', 'Give the folder a name.'],
        'es' => ['Archivos', '+ Nueva carpeta', 'Nueva carpeta', 'Nombre de la carpeta', 'Se creará en: Archivos', 'Crear la carpeta', 'Mover', 'Mover a', 'Eliminar la carpeta', 'En la carpeta «Pour les langues».', 'Esta carpeta está vacía', 'Carpeta creada.', 'Carpeta renombrada.', 'Ponle un nombre a la carpeta.'],
        'de' => ['Dateien', '+ Neuer Ordner', 'Neuer Ordner', 'Name des Ordners', 'Er wird angelegt in: Dateien', 'Ordner anlegen', 'Verschieben', 'Verschieben nach', 'Ordner löschen', 'Im Ordner „Pour les langues“.', 'Dieser Ordner ist leer', 'Ordner angelegt.', 'Ordner umbenannt.', 'Gib dem Ordner einen Namen.'],
    ];
    foreach ($attendus as $langue => $mots) {
        $appel('a', 'compte/langue', ['_csrf' => $csrf('a', 'compte'), 'langue' => $langue]);
        [, $listeLangue] = $appel('a', $racine);
        [, $dossierLangue] = $appel('a', $racine . '?dossier=' . $pourLesLangues);
        [, $formLangue] = $appel('a', 'travaux/' . $p . '/dossiers/nouveau');
        [, $creeLangue] = $appel('a', 'travaux/' . $p . '/dossiers', ['_csrf' => $jeton($formLangue), 'nom' => 'Dossier ' . $langue]);
        [, $renommeLangue] = $appel('a', 'travaux/dossiers/' . $pourLesLangues . '/modifier', ['_csrf' => $jeton($formLangue), 'nom' => 'Pour les langues']);
        [, $videLangue] = $appel('a', 'travaux/' . $p . '/dossiers', ['_csrf' => $jeton($formLangue), 'nom' => ' ']);
        $tout = $listeLangue . $dossierLangue . $formLangue . $creeLangue . $renommeLangue . $videLangue;
        // « Déplacer » demande un fichier : on en met un dans le dossier des langues, le temps de la page
        $manque = array_values(array_filter(array_diff($mots, ['Move', 'Move to', 'Mover', 'Mover a', 'Verschieben', 'Verschieben nach']), static fn (string $m): bool => !str_contains($tout, $m)));
        $dire($langue . ' : la page des fichiers, les dossiers et leurs messages', $manque === [] ? 'tout y est' : 'manque : ' . implode(' | ', $manque), 'tout y est');
        $dire($langue . ' : ni clé brute ni français resté', $oui(!preg_match('/\btr\.fd\.[a-z_.]+/', $tout) && !str_contains($tout, 'Nouveau dossier') && !str_contains($tout, 'Dossier créé') && !str_contains($tout, 'Donnez un nom au dossier')), 'oui');
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
    if ($renomme) {
        try { bd_run('RENAME TABLE projet_dossiers_essai TO projet_dossiers'); } catch (Throwable) {}
    }
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
    foreach (glob(__DIR__ . '/ck_tdos_*.txt') ?: [] as $fichierCookie) { @unlink($fichierCookie); }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'tdos-_@exemple-test.fr'")
        . ' · dossiers d’essai restants ' . bd_valeur("SELECT COUNT(*) FROM projet_dossiers WHERE nom IN ('Sources', 'Diapositives', 'Pour les langues', 'Les anciens')") . "\n";
}

/** Un plafond de l'application, lu dans son code (le script d'essai ne charge rien d'elle). */
function Travaux_essai_max(string $nom): int
{
    return preg_match('/' . $nom . ' = (\d+)/', (string) file_get_contents(dirname(__DIR__, 2) . '/src/Travaux.php'), $m) ? (int) $m[1] : 0;
}
