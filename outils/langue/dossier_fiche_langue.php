<?php
/**
 * La fiche de révision d'un dossier de « Mes cours » (HTTP + base) : un bouton « 📝 Fiche de révision » sur le dossier ouvert, une page où l'on écrit ce qu'il
 * faut retenir de tout le dossier — avec, en regard, ce qu'il contient (cours, fichiers, sous-dossiers, et leurs fiches) —, l'enregistrement, le PDF, la
 * fiche dans la liste des fiches ; elle ne crée aucun cours et n'en transforme aucun. Chacun les siennes (404), un site dont la migration n'est pas
 * passée, et les quatre langues.
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

$emails = ['dfi-a@exemple-test.fr', 'dfi-b@exemple-test.fr'];
$nettoyer = static function () use ($emails): void {
    foreach ($emails as $e) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']); }
};
$nettoyer();
foreach ($emails as $i => $e) {
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)', [$e, 'Dfi_' . $i, password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai dossier']);
}
[$idA, $idB] = array_map(static fn (string $e): int => (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$e]), $emails);

bd_run('INSERT INTO matieres (user_id, nom, couleur) VALUES (?, ?, ?)', [$idA, 'Architecture logicielle', '#8e24aa']);
$matiere = (int) bd_valeur('SELECT id FROM matieres WHERE user_id = ?', [$idA]);
$dossierNouveau = static function (int $u, string $nom, ?int $parent = null, ?int $matiere = null): int {
    bd_run('INSERT INTO dossiers (user_id, parent_id, matiere_id, nom, icone) VALUES (?, ?, ?, ?, ?)', [$u, $parent, $matiere, $nom, '🏛️']);
    return (int) bd_valeur('SELECT MAX(id) FROM dossiers WHERE user_id = ?', [$u]);
};
$dossier = $dossierNouveau($idA, 'Architecture', null, $matiere);
$annexes = $dossierNouveau($idA, 'Annexes', $dossier);
$vide = $dossierNouveau($idA, 'Dossier vide');
$deB = $dossierNouveau($idB, 'Dossier de B');
$cours = static function (int $u, string $titre, ?int $dossier, bool $seul = false, ?string $fiche = null, string $nomFichier = '', string $mime = ''): int {
    bd_run('INSERT INTO cours (user_id, dossier_id, titre, contenu, fiche_revision, est_fichier) VALUES (?, ?, ?, ?, ?, ?)', [$u, $dossier, $titre, $seul ? null : 'Du texte.', $fiche, $seul ? 1 : 0]);
    $c = (int) bd_valeur('SELECT MAX(id) FROM cours WHERE user_id = ?', [$u]);
    if ($seul) {
        bd_run('INSERT INTO fichiers (user_id, cours_id, nom_origine, nom_stocke, mime, taille) VALUES (?, ?, ?, ?, ?, ?)', [$u, $c, $nomFichier, 'zz_dfi_' . $c . '.bin', $mime, 1150000]);
    }
    return $c;
};
$coursOrdinaire = $cours($idA, 'Cours de conception', $dossier);
$coursAvecFiche = $cours($idA, 'Cours d\'architecture hexagonale', $dossier, false, 'À retenir : ports et adaptateurs.');
$simple = $cours($idA, 'Cours_Architecture_logicielle.pdf', $dossier, true, null, 'Cours_Architecture_logicielle.pdf', 'application/pdf');
$archive = $cours($idA, 'sources.zip', $dossier, true, null, 'sources.zip', 'application/zip');
$dansAnnexes = $cours($idA, 'Annexe 1', $annexes);
$horsDossier = $cours($idA, 'Hors dossier', null);
$coursDeB = $cours($idB, 'Cours de B', $deB);
$fichierSimple = (int) bd_valeur('SELECT id FROM fichiers WHERE cours_id = ?', [$simple]);

$cookies = [$emails[0] => __DIR__ . '/ck_dfi_a.txt', $emails[1] => __DIR__ . '/ck_dfi_b.txt'];
foreach ($cookies as $f) { @unlink($f); }
/** @return array{0: string, 1: int, 2: string, 3: array<string, string>} corps, code, adresse finale, en-têtes */
$appel = static function (string $compte, string $chemin, ?array $post = null) use ($cookies): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $cookies[$compte], CURLOPT_COOKIEFILE => $cookies[$compte]]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $brut = (string) curl_exec($h);
    $taille = (int) curl_getinfo($h, CURLINFO_HEADER_SIZE);
    $entetes = [];
    foreach (explode("\r\n", substr($brut, 0, $taille)) as $ligne) {
        if (str_contains($ligne, ':')) { [$cle, $valeur] = explode(':', $ligne, 2); $entetes[strtolower(trim($cle))] = trim($valeur); }
    }
    $r = [substr($brut, $taille), (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL), $entetes];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
$fiche = static fn (int $d): mixed => bd_valeur('SELECT fiche_revision FROM dossiers WHERE id = ?', [$d]);
$nbCours = static fn (int $u): int => (int) bd_valeur('SELECT COUNT(*) FROM cours WHERE user_id = ?', [$u]);
$renomme = false;
$renommeFichiers = false;
$renommeChoix = false;

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
    $url = 'dossiers/' . $dossier . '/revision';

    echo "\n1. Le bouton, sur le dossier ouvert\n";
    [$ouvert] = $appel($a, 'cours?dossier=' . $dossier);
    $dire('« 📝 Fiche de révision » (fenêtre) à côté de « Partager le dossier », sans « • » tant qu\'il n\'y a pas de fiche',
        $oui(preg_match('~<a class="bouton bouton--secondaire" href="[^"]*/' . $url . '"\s+data-fenetre>📝 Fiche de révision</a>~u', $ouvert) === 1), 'oui');
    $dire('rien de tel sans dossier ouvert (liste de tous les cours)', $oui(!str_contains($page, '/revision"') || !str_contains($page, 'dossiers/' . $dossier . '/revision')), 'oui');

    echo "\n2. La page de la fiche\n";
    [$fen, $code] = $appel($a, $url . '?fenetre=1');
    $dire('elle s\'ouvre en fenêtre : le nom et l\'icône du dossier, sa matière, « Fiche de révision du dossier », le champ du texte, « Enregistrer »',
        $code . ' · ' . $oui(str_contains($fen, '🏛️ Architecture') && str_contains($fen, 'Architecture logicielle</span>') && str_contains($fen, 'Fiche de révision du dossier') && str_contains($fen, 'name="fiche_revision"') && str_contains($fen, 'Enregistrer') && !str_contains($fen, '<html') && str_contains($fen, 'data-envoi-fenetre')), '200 · oui');
    $dire('sans fiche, pas de bouton « PDF » ; mais imprimer est là', $oui(!str_contains($fen, '/revision/pdf') && str_contains($fen, 'Imprimer')), 'oui');
    $conditions = [
        'titre' => str_contains($fen, 'Reliés à la fiche'),
        'cours ordinaire' => str_contains($fen, '>Cours de conception</a>') && str_contains($fen, 'href="/mon_appli/appli/cours/' . $coursOrdinaire . '"'),
        'cours avec sa fiche' => preg_match('~architecture hexagonale</a><br>\s*<span class="fichier__meta">\s*<a href="[^"]*/revision/' . $coursAvecFiche . '"[^>]*>📝 Sa fiche de révision</a>~u', $fen) === 1,
        'fichier pdf (icône, aperçu)' => str_contains($fen, '📕') && str_contains($fen, '/fichiers/' . $fichierSimple . '/apercu'),
        'archive (onglet)' => preg_match('~href="[^"]*/fichiers/' . (int) bd_valeur('SELECT id FROM fichiers WHERE cours_id = ?', [$archive]) . '" target="_blank" rel="noopener">sources\.zip</a>~', $fen) === 1,
        'sous-dossier' => str_contains($fen, 'dossiers/' . $annexes . '/revision') && str_contains($fen, '1 cours ou fichier'),
    ];
    $manquants = array_keys(array_filter($conditions, static fn (bool $b): bool => !$b));
    $dire('« Reliés à la fiche » : le cours, le cours avec fiche (« Sa fiche de révision »), le fichier PDF (son aperçu), l\'archive (dans un onglet), le sous-dossier', $manquants === [] ? 'tout y est' : 'manque : ' . implode(' | ', $manquants), 'tout y est');
    $dire('tant qu\'on n\'a rien choisi, tout le dossier est relié, sous-dossiers compris (« Annexe 1 ») ; jamais « Hors dossier » (listé seulement dans le choix des renvois) ni le cours de B', $oui(str_contains($fen, '>Annexe 1</a>') && !str_contains($fen, '>Hors dossier</a>') && !str_contains($fen, 'Cours de B')), 'oui');
    [$feVide] = $appel($a, 'dossiers/' . $vide . '/revision?fenetre=1');
    $dire('un dossier vide le dit', $oui(str_contains($feVide, 'Ce dossier est vide pour l’instant')), 'oui');
    [$pleine, $codePleine] = $appel($a, $url);
    $dire('en page entière : le retour au dossier, avec la mise en page du site', $codePleine . ' · ' . $oui(str_contains($pleine, '<html') && str_contains($pleine, 'cours?dossier=' . $dossier)), '200 · oui');

    echo "\n2b. Les éléments rattachés, comme sur la fiche d'un cours\n";
    $titresRayons = ['Éléments rattachés', 'Diaporamas', 'Fichiers et images', 'Cartes', 'Cartes mentales', 'Liens', 'Autres cours', 'Au calendrier', 'Reliés à la fiche'];
    $positions = array_map(static fn (string $titre): int|false => strpos(html_entity_decode($fen, ENT_QUOTES, 'UTF-8'), $titre), $titresRayons);
    $dire('la colonne de gauche reprend les rubriques de la fiche d\'un cours, dans le même ordre, puis « Reliés à la fiche »',
        $oui(!in_array(false, $positions, true) && $positions === (static function (array $p): array { $s = $p; sort($s); return $s; })($positions)), 'oui');
    $dire('le dépôt de fichiers part vers le dossier, les formulaires de liens et de renvois aussi',
        $oui(str_contains($fen, 'action="/mon_appli/appli/' . $url . '/fichiers"') && substr_count($fen, 'action="/mon_appli/appli/' . $url . '/elements"') === 2 && str_contains($fen, 'data-depot-champ') && str_contains($fen, 'data-jeton-lecture')), 'oui');
    $dire('le choix des renvois ne propose que MES cours (pas « Cours de B »)', $oui(str_contains($fen, '>Hors dossier</option>') && !str_contains($fen, 'Cours de B')), 'oui');

    // Un lien web : http(s) seulement, rattaché au dossier (aucun cours)
    $avantCours = $nbCours($idA);
    $appel($a, $url . '/elements', ['_csrf' => $csrfA, 'type' => 'lien', 'url' => 'https://exemple.fr/ressource', 'libelle' => 'Ma ressource']);
    $appel($a, $url . '/elements', ['_csrf' => $csrfA, 'type' => 'lien', 'url' => 'javascript:alert(1)', 'libelle' => 'Piège']);
    $liens = bd_all('SELECT id, cours_id, dossier_id, libelle FROM fiche_elements WHERE user_id = ? AND type = ?', [$idA, 'lien']);
    $dire('un lien https est rattaché au dossier (sans cours) ; « javascript: » est refusé',
        count($liens) . ' · ' . (int) ($liens[0]['dossier_id'] ?? 0) . '/' . (array_key_exists('cours_id', $liens[0] ?? []) ? var_export($liens[0]['cours_id'], true) : 'x') . ' · ' . ($liens[0]['libelle'] ?? ''), '1 · ' . $dossier . '/NULL · Ma ressource');
    [$avecLien] = $appel($a, $url . '?fenetre=1');
    $dire('la page le montre (lien externe sûr, avec son site)', $oui(preg_match('~<a href="https://exemple\.fr/ressource" target="_blank" rel="noopener noreferrer">\s*Ma ressource ↗</a>\s*<span class="fiche__url discret">exemple\.fr</span>~u', $avecLien) === 1), 'oui');
    $idLien = (int) ($liens[0]['id'] ?? 0);
    // Sans le moindre texte, un lien suffit : comme pour un cours, le dossier est une fiche de la liste « Révision » (et non « sans fiche »)
    [$listeLien] = $appel($a, 'revision');
    [$ouvertLien] = $appel($a, 'cours?dossier=' . $dossier);
    $posSansFiche = (int) strpos($listeLien, 'Cours sans fiche');
    $posCarte = strpos($listeLien, 'dossiers/' . $dossier . '/revision" data-fenetre');
    $dire('un dossier sans texte mais avec un lien est une fiche de « Révision » (carte « Dossier · 1 lien », avant « Cours sans fiche »), et son bouton porte « • »',
        $oui($posCarte !== false && $posCarte < $posSansFiche && preg_match('~fiche-carte__compteurs">Dossier · 🔗 1 lien</p>~u', $listeLien) === 1 && str_contains($ouvertLien, '📝 Fiche de révision •')), 'oui');

    // Un renvoi vers un autre cours : le mien, une seule fois ; jamais celui de B
    $appel($a, $url . '/elements', ['_csrf' => $csrfA, 'type' => 'cours', 'cible' => $horsDossier, 'libelle' => 'À relire']);
    $appel($a, $url . '/elements', ['_csrf' => $csrfA, 'type' => 'cours', 'cible' => $horsDossier]);
    $appel($a, $url . '/elements', ['_csrf' => $csrfA, 'type' => 'cours', 'cible' => $coursDeB]);
    $dire('un renvoi vers mon cours, une seule fois ; celui vers le cours de B est refusé',
        (string) bd_valeur('SELECT COUNT(*) FROM fiche_elements WHERE dossier_id = ? AND type = ?', [$dossier, 'cours']) . ' · ' . (string) bd_valeur('SELECT COUNT(*) FROM fiche_elements WHERE cible_cours_id = ?', [$coursDeB]), '1 · 0');

    // Un évènement du calendrier
    bd_run('INSERT INTO evenements (user_id, titre, debut, fin) VALUES (?, ?, ?, ?)', [$idA, 'Partiel d\'architecture', '2031-06-10 09:00:00', '2031-06-10 11:00:00']);
    $evenement = (int) bd_valeur('SELECT MAX(id) FROM evenements WHERE user_id = ?', [$idA]);
    $appel($a, $url . '/elements', ['_csrf' => $csrfA, 'type' => 'evenement', 'cible' => $evenement]);
    [$avecTout] = $appel($a, $url . '?fenetre=1');
    $dire('l\'évènement est rattaché, et la page montre renvoi et évènement', $oui(str_contains(html_entity_decode($avecTout, ENT_QUOTES, 'UTF-8'), 'Partiel d\'architecture') && str_contains($avecTout, 'À relire') && str_contains($avecTout, '>Hors dossier</a>')), 'oui');
    [, $codeInconnu] = $appel($a, $url . '/elements', ['_csrf' => $csrfA, 'type' => 'inconnu']);
    $dire('un type inconnu n\'ajoute rien', (string) bd_valeur('SELECT COUNT(*) FROM fiche_elements WHERE dossier_id = ?', [$dossier]), '3');
    [, $codeB4] = $appel($b, $url . '/elements', ['_csrf' => $csrfB, 'type' => 'lien', 'url' => 'https://piratage.fr/']);
    [, $codeB5] = $appel($b, $url . '/fichiers', ['_csrf' => $csrfB]);
    [, $codeSansJeton] = $appel($a, $url . '/elements', ['type' => 'lien', 'url' => 'https://sans-jeton.fr/']);
    $dire('B ne peut rien rattacher au dossier de A (404) ; sans jeton CSRF, rien non plus (400)',
        $codeB4 . ' · ' . $codeB5 . ' · ' . $codeSansJeton . ' · ' . bd_valeur('SELECT COUNT(*) FROM fiche_elements WHERE dossier_id = ?', [$dossier]), '404 · 404 · 400 · 3');

    // Retirer : l'élément part, on revient à la fiche du dossier
    [, $codeRetrait, $apresRetrait] = $appel($a, 'revision/element/' . $idLien . '/supprimer', ['_csrf' => $csrfA]);
    $dire('retirer un lien le supprime et ramène à la fiche du dossier',
        $codeRetrait . ' · ' . $oui(str_ends_with($apresRetrait, '/' . $url)) . ' · ' . bd_valeur('SELECT COUNT(*) FROM fiche_elements WHERE id = ?', [$idLien]), '200 · oui · 0');
    [, $codeRetraitB] = $appel($b, 'revision/element/' . (int) bd_valeur('SELECT id FROM fiche_elements WHERE dossier_id = ? LIMIT 1', [$dossier]) . '/supprimer', ['_csrf' => $csrfB]);
    $dire('B ne retire pas l\'élément de A (404)', $codeRetraitB . ' · ' . bd_valeur('SELECT COUNT(*) FROM fiche_elements WHERE dossier_id = ?', [$dossier]), '404 · 2');

    // Un fichier déposé : une petite image PNG
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $envoyer = static function (string $compte, string $chemin, string $nom, string $contenu, string $jeton) use ($cookies): array {
        $temp = tempnam(sys_get_temp_dir(), 'dfi');
        file_put_contents($temp, $contenu);
        $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60, CURLOPT_COOKIEJAR => $cookies[$compte], CURLOPT_COOKIEFILE => $cookies[$compte],
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => ['_csrf' => $jeton, 'fichiers[]' => new CURLFile($temp, 'image/png', $nom)]]);
        $corps = (string) curl_exec($h);
        $code = (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE);
        unset($h);
        @unlink($temp);
        return [$corps, $code];
    };
    usleep(250000);
    [, $codeDepot] = $envoyer($a, $url . '/fichiers', 'schema-dossier.png', $png, $csrfA);
    $f = bd_all('SELECT * FROM fichiers WHERE user_id = ? AND dossier_id = ?', [$idA, $dossier])[0] ?? null;
    $racine = dirname(__DIR__, 2);
    $surDisque = $f !== null && is_file($racine . '/storage/uploads/' . basename((string) $f['nom_stocke']));
    $dire('un fichier déposé tient au dossier (sans cours), dans la fiche, et il est bien enregistré sur le disque',
        $codeDepot . ' · ' . ($f === null ? 'aucune ligne' : var_export($f['cours_id'], true) . '/' . (int) $f['dossier_id'] . '/pour_fiche=' . (int) $f['pour_fiche'] . '/' . $f['nom_origine']) . ' · ' . $oui($surDisque),
        '200 · NULL/' . $dossier . '/pour_fiche=1/schema-dossier.png · oui');
    $idFichier = (int) ($f['id'] ?? 0);
    [$avecFichier] = $appel($a, $url . '?fenetre=1');
    $dire('la page l\'affiche comme sur une fiche de cours : l\'image à feuilleter, son nom, de quoi la retirer',
        $oui(str_contains($avecFichier, 'src="/mon_appli/appli/fichiers/' . $idFichier . '"') && str_contains($avecFichier, 'data-images') && str_contains($avecFichier, 'fichiers/' . $idFichier . '/supprimer') && str_contains($avecFichier, 'schema-dossier.png')), 'oui');
    [$octets, $codeImage] = $appel($a, 'fichiers/' . $idFichier);
    [, $codeImageB] = $appel($b, 'fichiers/' . $idFichier);
    $dire('l\'image se sert à A (200, un PNG) et pas à B (404)', $codeImage . ' · ' . $oui(str_starts_with($octets, "\x89PNG")) . ' · ' . $codeImageB, '200 · oui · 404');
    [, $codePosition] = $appel($a, 'fichiers/' . $idFichier . '/position', ['_csrf' => $csrfA, 'position' => 1, 'duree' => 1]);
    $dire('la position de lecture s\'enregistre comme pour un fichier de cours (204, sans contenu)', (string) $codePosition, '204');
    [$listeCours] = $appel($a, 'cours');
    [$listeCartes, $codeCartes] = $appel($a, 'cartes');
    [$listeResumes, $codeResumes] = $appel($a, 'resumes');
    $dire('ce fichier n\'apparaît ni dans « Mes cours », ni dans les choix des cartes et des résumés (et ces pages se chargent)',
        $oui(!str_contains($listeCours, 'schema-dossier.png') && !str_contains($listeCartes, 'schema-dossier.png') && !str_contains($listeResumes, 'schema-dossier.png') && !str_contains($listeCartes, 'Fatal error') && !str_contains($listeResumes, 'Fatal error')) . ' · ' . $codeCartes . ' · ' . $codeResumes, 'oui · 200 · 200');
    $dire('et aucun cours n\'a été créé par tout cela', (string) ($nbCours($idA) - $avantCours), '0');
    [$choixEdition, $codeApercu] = $appel($a, 'fichiers/' . $idFichier . '/apercu');
    $dire('l\'aperçu d\'un fichier du dossier ne plante pas (une image n\'a pas d\'aperçu : on est renvoyé au fichier)', (string) $codeApercu, '200');
    [, $codeRetireFichier, $apresFichier] = $appel($a, 'fichiers/' . $idFichier . '/supprimer', ['_csrf' => $csrfA]);
    usleep(1500000);   // Windows : le fichier qu'on vient de servir peut rester verrouillé un instant
    clearstatcache(true);   // PHP garde en mémoire le résultat d'is_file : sans cela, on lirait l'état d'avant la suppression
    $dire('retirer le fichier l\'efface (base et disque) et ramène à la fiche du dossier',
        $codeRetireFichier . ' · ' . $oui(str_ends_with($apresFichier, '/' . $url)) . ' · ' . bd_valeur('SELECT COUNT(*) FROM fichiers WHERE id = ?', [$idFichier]) . ' · ' . $oui(!is_file($racine . '/storage/uploads/' . basename((string) ($f['nom_stocke'] ?? 'x')))), '200 · oui · 0 · oui');

    // Une page web (.html) déposée dans la fiche du dossier : un quizz, joué sur place dans un cadre enfermé par le serveur
    $envoyer($a, $url . '/fichiers', 'page-dossier.html', '<p>Dans la fiche du dossier</p><script>alert(1)</script>', $csrfA);
    $page = bd_all('SELECT id, cours_id, dossier_id FROM fichiers WHERE user_id = ? AND nom_origine = ?', [$idA, 'page-dossier.html'])[0] ?? null;
    [$avecPage] = $appel($a, $url . '?fenetre=1');
    [, , , $entetesPage] = $appel($a, 'fichiers/' . (int) ($page['id'] ?? 0) . '?jouer=1');
    $dire('une page web déposée dans la fiche du dossier s\'y joue dans la rubrique « Quizz » (cadre sans attribut « sandbox », scripts enfermés par le serveur), sans cours créé',
        $oui($page !== null && $page['cours_id'] === null && (int) $page['dossier_id'] === $dossier
            && preg_match('#<div class="quizz">.*?<iframe class="quizz__cadre" src="[^"]*/fichiers/' . (int) $page['id'] . '\?jouer=1" referrerpolicy="no-referrer" loading="lazy"#s', $avecPage) === 1
            && !str_contains($avecPage, 'sandbox=') && !str_contains($avecPage, '<script>alert(1)') && str_contains($avecPage, '🎯 Quizz')
            && str_starts_with($entetesPage['content-security-policy'] ?? '', 'sandbox allow-scripts;') && !str_contains($entetesPage['content-security-policy'] ?? '', 'allow-same-origin')), 'oui');
    [, $codeJB] = $appel($b, 'fichiers/' . (int) ($page['id'] ?? 0) . '?jouer=1');
    $dire('B ne joue pas le quizz du dossier de A (404)', (string) $codeJB, '404');
    if ($page !== null) {
        $appel($a, 'fichiers/' . (int) $page['id'] . '/supprimer', ['_csrf' => $csrfA]);
    }

    // Ce que les cours du dossier ont déjà : cartes, cartes mentales, diaporamas
    bd_run('INSERT INTO cartes (user_id, cours_id, question, reponse, origine, empreinte, boite, revoir_le) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())', [$idA, $coursOrdinaire, 'Qu\'est-ce qu\'un port ?', 'Une interface.', 'main', md5('port'), 1]);
    bd_run('INSERT INTO cartes (user_id, cours_id, question, reponse, origine, empreinte, boite, revoir_le) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())', [$idA, $dansAnnexes, 'Question des annexes', 'Hors du dossier.', 'main', md5('annexe'), 1]);
    bd_run('INSERT INTO cartes_mentales (user_id, cours_id, titre, arbre) VALUES (?, ?, ?, ?)', [$idA, $coursOrdinaire, 'Les couches', json_encode(['t' => 'Couches', 'c' => [['t' => 'Domaine', 'c' => []]]])]);
    bd_run('INSERT INTO diaporamas (user_id, titre, diapos) VALUES (?, ?, ?)', [$idA, 'Les ports en images', json_encode([['t' => 'Port', 'p' => ['Une interface'], 'c' => 'Texte']])]);
    $diaporama = (int) bd_valeur('SELECT MAX(id) FROM diaporamas WHERE user_id = ?', [$idA]);
    bd_run('INSERT INTO diaporama_cours (diaporama_id, cours_id, user_id) VALUES (?, ?, ?)', [$diaporama, $coursOrdinaire, $idA]);
    [$synthese] = $appel($a, $url . '?fenetre=1');
    $dire('tant que tout est relié, les cartes du dossier ET de son sous-dossier sont comptées (2 cartes, à revoir) ; la carte mentale et le diaporama des cours y sont listés',
        $oui(str_contains($synthese, '<strong>2</strong> cartes') && str_contains($synthese, '2 à revoir') && str_contains($synthese, 'Les couches') && str_contains($synthese, 'Les ports en images') && str_contains($synthese, 'cartes/seance?dossier=' . $dossier)), 'oui');
    [$seance, $codeSeance] = $appel($a, 'cartes/seance?dossier=' . $dossier . '&fenetre=1');
    $dire('« Réviser » ouvre une séance avec les cartes reliées (le dossier et son sous-dossier)',
        $codeSeance . ' · ' . $oui(str_contains($seance, 'Qu&#039;est-ce qu&#039;un port ?') || str_contains($seance, 'Qu\'est-ce qu\'un port ?')) . ' · ' . $oui(str_contains($seance, 'Question des annexes')), '200 · oui · oui');
    [$seanceB] = $appel($b, 'cartes/seance?dossier=' . $dossier . '&fenetre=1');
    $dire('B, avec le numéro du dossier de A, ne voit aucune de ses cartes', $oui(!str_contains($seanceB, 'port ?') && !str_contains($seanceB, 'Question des annexes')), 'oui');

    echo "\n2c. Choisir ce que la fiche relie\n";
    $cases = static fn (string $html, bool $cochees): int => preg_match_all('~<input type="checkbox" name="(?:cours|dossiers)\[\]" value="\d+"' . ($cochees ? ' checked>' : '( checked)?>') . '~', $html);
    [$avantChoix] = $appel($a, $url . '?fenetre=1');
    $dire('tant qu\'on n\'a rien choisi : « Tout le dossier est relié », et toutes les cases du choix sont cochées',
        $oui(str_contains($avantChoix, 'Tout le dossier est relié') && $cases($avantChoix, false) >= 6 && $cases($avantChoix, true) === $cases($avantChoix, false)) . ' · ' . $cases($avantChoix, false), 'oui · ' . $cases($avantChoix, false));
    $liens = static fn (): array => bd_all('SELECT cours_id, sous_dossier_id FROM dossier_fiche_liens WHERE dossier_id = ? ORDER BY id', [$dossier]);
    $choix = static fn (): int => (int) bd_valeur('SELECT fiche_choix FROM dossiers WHERE id = ?', [$dossier]);

    // Étape 1 : un cours, un fichier, et le sous-dossier (dont un cours est coché aussi : il est déjà couvert par son dossier)
    $appel($a, $url . '/liens', ['_csrf' => $csrfA, 'cours' => [$coursOrdinaire, $simple, $dansAnnexes], 'dossiers' => [$annexes]]);
    $stockes = array_map(static fn (array $l): string => $l['cours_id'] !== null ? 'c' . $l['cours_id'] : 's' . $l['sous_dossier_id'], $liens());
    sort($stockes);
    $attendus = ['c' . $coursOrdinaire, 'c' . $simple, 's' . $annexes];
    sort($attendus);
    $dire('le choix est enregistré : le cours, le fichier et le sous-dossier ; le cours déjà couvert par son dossier n\'est pas retenu à part',
        $choix() . ' · ' . implode(',', $stockes), '1 · ' . implode(',', $attendus));
    [$choisie] = $appel($a, $url . '?fenetre=1');
    $dire('la page ne montre plus que ce qui est relié : « Seuls … choisis », le cours, le fichier PDF, le sous-dossier (et « Annexe 1 », avec son dossier) — pas le cours avec fiche ni l\'archive',
        $oui(str_contains($choisie, 'Seuls les fichiers et dossiers choisis') && str_contains($choisie, '>Cours de conception</a>') && str_contains($choisie, 'Cours_Architecture_logicielle.pdf</a>')
            && str_contains($choisie, 'dossiers/' . $annexes . '/revision') && str_contains($choisie, '>Annexe 1</a>') && str_contains($choisie, 'avec son dossier')
            && !str_contains($choisie, 'revision/' . $coursAvecFiche . '"') && !str_contains($choisie, '>sources.zip</a>')), 'oui');
    $dire('et le choix montre toutes les cases, cochées seulement celles qui sont reliées (4 cochées sur ' . $cases($choisie, false) . ')',
        (string) $cases($choisie, true) . ' / ' . $cases($choisie, false), '4 / ' . $cases($choisie, false));
    [$syn2] = $appel($a, $url . '?fenetre=1');
    $dire('les cartes comptées sont celles des cours reliés (celle du cours + celle du sous-dossier : 2)', $oui(str_contains($syn2, '<strong>2</strong> cartes')), 'oui');

    // Étape 2 : seulement le cours de conception : les cartes du sous-dossier ne comptent plus
    $appel($a, $url . '/liens', ['_csrf' => $csrfA, 'cours' => [$coursOrdinaire]]);
    [$seul] = $appel($a, $url . '?fenetre=1');
    [$seanceSeul] = $appel($a, 'cartes/seance?dossier=' . $dossier . '&fenetre=1');
    $dire('avec le seul cours de conception relié : une carte, une séance sans les questions du sous-dossier, et « Annexes » décoché dans le choix',
        $oui(str_contains($seul, '<strong>1</strong> carte') && str_contains($seanceSeul, 'port ?')) . ' · ' . $oui(!str_contains($seanceSeul, 'Question des annexes')) . ' · ' . $oui(str_contains($seul, 'name="dossiers[]" value="' . $annexes . '">')), 'oui · oui · oui');

    // Jamais un cours ou un dossier d'ailleurs, ni d'un autre compte
    $appel($a, $url . '/liens', ['_csrf' => $csrfA, 'cours' => [$coursOrdinaire, $coursDeB, $horsDossier], 'dossiers' => [$deB, $vide]]);
    $dire('un cours d\'un autre compte, un cours hors du dossier, un dossier d\'ailleurs : rien n\'est retenu que le cours du dossier',
        implode(',', array_map(static fn (array $l): string => $l['cours_id'] !== null ? 'c' . $l['cours_id'] : 's' . $l['sous_dossier_id'], $liens())), 'c' . $coursOrdinaire);
    [, $codeB6] = $appel($b, $url . '/liens', ['_csrf' => $csrfB, 'cours' => [$coursDeB]]);
    [, $codeSans2] = $appel($a, $url . '/liens', ['cours' => [$simple]]);
    $dire('B ne choisit rien sur le dossier de A (404) ; sans jeton CSRF, rien non plus (400)', $codeB6 . ' · ' . $codeSans2 . ' · ' . count($liens()), '404 · 400 · 1');

    // Étape 3 : tout relier à nouveau
    $appel($a, $url . '/liens', ['_csrf' => $csrfA, 'tout' => '1']);
    [$retour] = $appel($a, $url . '?fenetre=1');
    $dire('« Tout relier » revient à tout le dossier : plus de choix, plus de lignes, toutes les cases cochées',
        $choix() . ' · ' . count($liens()) . ' · ' . $oui(str_contains($retour, 'Tout le dossier est relié') && $cases($retour, true) === $cases($retour, false)), '0 · 0 · oui');
    $appel($a, $url . '/liens', ['_csrf' => $csrfA, 'cours' => [$coursOrdinaire]]);
    $dire('et supprimer un cours relié le retire du choix sans rien casser',
        (string) bd_run('DELETE FROM cours WHERE id = ?', [$coursOrdinaire]) . ' · ' . count($liens()) . ' · ' . $oui(!str_contains($appel($a, $url . '?fenetre=1')[0], 'Fatal error')), '1 · 0 · oui');
    $coursOrdinaire = $cours($idA, 'Cours de conception', $dossier);
    $appel($a, $url . '/liens', ['_csrf' => $csrfA, 'tout' => '1']);

    // Supprimer le dossier : ses fichiers partent aussi du disque
    $envoyer($a, $url . '/fichiers', 'a-supprimer.png', $png, $csrfA);
    $aEffacer = bd_all('SELECT id, nom_stocke FROM fichiers WHERE user_id = ? AND dossier_id = ?', [$idA, $dossier])[0] ?? null;
    $present = $aEffacer !== null && is_file($racine . '/storage/uploads/' . basename((string) $aEffacer['nom_stocke']));
    $dossierASupprimer = $dossierNouveau($idA, 'À supprimer');
    bd_run('INSERT INTO fichiers (user_id, cours_id, dossier_id, pour_fiche, nom_origine, nom_stocke, mime, taille) VALUES (?, NULL, ?, 1, ?, ?, ?, ?)', [$idA, $dossierASupprimer, 'orphelin.png', 'zz_dfi_orphelin.png', 'image/png', strlen($png)]);
    file_put_contents($racine . '/storage/uploads/zz_dfi_orphelin.png', $png);
    $appel($a, 'dossiers/' . $dossierASupprimer . '/supprimer', ['_csrf' => $csrfA]);
    clearstatcache(true);
    $dire('supprimer un dossier emporte les fichiers de sa fiche, base et disque',
        $oui(bd_valeur('SELECT COUNT(*) FROM fichiers WHERE dossier_id = ?', [$dossierASupprimer]) == 0 && !is_file($racine . '/storage/uploads/zz_dfi_orphelin.png')) . ' · ' . $oui($present), 'oui · oui');
    if ($aEffacer !== null) {
        @unlink($racine . '/storage/uploads/' . basename((string) $aEffacer['nom_stocke']));
        bd_run('DELETE FROM fichiers WHERE id = ?', [(int) $aEffacer['id']]);
    }
    @unlink($racine . '/storage/uploads/zz_dfi_orphelin.png');
    bd_run('DELETE FROM fiche_elements WHERE dossier_id = ?', [$dossier]);

    echo "\n3. Écrire la fiche\n";
    [, , $adresse] = $appel($a, $url, ['_csrf' => $csrfA, 'fiche_revision' => 'À retenir de tout le dossier : le cœur ne dépend de rien.']);
    $dire('la fiche est enregistrée sur le dossier, et l\'on reste sur sa page', $oui(str_contains((string) $fiche($dossier), 'le cœur ne dépend de rien')) . ' · ' . $oui(str_ends_with($adresse, '/' . $url)), 'oui · oui');
    $dire('rien n\'a été créé ni transformé : les mêmes cours, aucun cours de plus (et ceux du dossier gardent leur état)',
        $nbCours($idA) . ' · ' . bd_valeur('SELECT COUNT(*) FROM cours WHERE dossier_id = ?', [$dossier]) . ' · ' . bd_valeur('SELECT COUNT(*) FROM cours WHERE id = ? AND est_fichier = 1', [$simple]), $avantTout . ' · 4 · 1');
    $dire('et les autres dossiers n\'ont pas de fiche', var_export($fiche($annexes), true) . ' · ' . var_export($fiche($vide), true), 'NULL · NULL');
    [$ouvert2] = $appel($a, 'cours?dossier=' . $dossier);
    [$fen2] = $appel($a, $url . '?fenetre=1');
    $dire('le bouton montre « • », la fiche rouverte montre le texte, et le bouton « ⬇ PDF » apparaît',
        $oui(str_contains($ouvert2, '📝 Fiche de révision •') && str_contains($fen2, 'le cœur ne dépend de rien') && str_contains($fen2, $url . '/pdf')), 'oui');
    [$pdf, $codePdf, , $entetes] = $appel($a, $url . '/pdf');
    $dire('le PDF : un vrai PDF, téléchargé, sous le nom « Fiche — Architecture.pdf »',
        $codePdf . ' · ' . $oui(str_starts_with($pdf, '%PDF') && str_starts_with($entetes['content-type'] ?? '', 'application/pdf') && str_contains(rawurldecode($entetes['content-disposition'] ?? ''), 'Fiche — Architecture.pdf')), '200 · oui');
    // La liste « Révision » : la fiche du dossier y est une carte de fiche comme les autres (rangée avec les fiches de cours de la même matière).
    // Les dates, fixées : la fiche du cours date de 2020, celle du dossier vient d'être écrite (le tri par date est alors sans ambiguïté).
    bd_run('UPDATE cours SET matiere_id = ?, updated_at = ? WHERE id = ?', [$matiere, '2020-01-01 00:00:00', $coursAvecFiche]);
    bd_run('INSERT INTO matieres (user_id, nom, couleur) VALUES (?, ?, ?)', [$idA, 'Maths', '#1e88e5']);
    $maths = (int) bd_valeur('SELECT id FROM matieres WHERE user_id = ? AND nom = ?', [$idA, 'Maths']);
    [$liste] = $appel($a, 'revision');
    $carteDossier = '~<a class="carte fiche-carte" href="[^"]*/' . $url . '" data-fenetre>\s*<h3 class="fiche-carte__titre">🏛️ Architecture</h3>\s*(?:<p class="fiche-carte__ecoute">.*?</p>\s*)?<p class="fiche-carte__extrait">À retenir de tout le dossier : le cœur ne dépend de rien\.</p>\s*<p class="fiche-carte__compteurs">Dossier(?: · [^<]*)?</p>\s*</a>~us';
    $dire('elle paraît dans « Révision » comme une fiche classique : la même carte (titre avec l\'icône, extrait du texte, étiquette « Dossier »), qui s\'ouvre en fenêtre',
        $oui(preg_match($carteDossier, $liste) === 1), 'oui');
    $positionH2 = strpos($liste, '<h2 class="revisions__matiere">');
    $posDossier = (int) strpos($liste, 'dossiers/' . $dossier . '/revision" data-fenetre');
    $posCours = (int) strpos($liste, 'revision/' . $coursAvecFiche . '" data-fenetre');
    $grilles = $positionH2 === false ? 0 : substr_count(substr($liste, $positionH2, max($posDossier, $posCours) - $positionH2), '<div class="grille grille--fiches">');
    $conditions = [
        'un titre de matière' => $positionH2 !== false,
        'carte du dossier après' => $positionH2 !== false && $posDossier > $positionH2,
        'carte du cours après' => $positionH2 !== false && $posCours > $positionH2,
        'une seule matière (' . substr_count($liste, '<h2 class="revisions__matiere">') . ')' => substr_count($liste, '<h2 class="revisions__matiere">') === 1,
        'pas de bloc à part' => !str_contains($liste, 'Fiches de dossiers'),
        'une seule grille entre les deux (' . $grilles . ')' => $grilles === 1,
    ];
    $manquants = array_keys(array_filter($conditions, static fn (bool $b): bool => !$b));
    $dire('rangée sous sa matière « Architecture logicielle », dans la même grille que la fiche du cours de cette matière (pas dans un bloc à part)', $manquants === [] ? 'oui' : 'manque : ' . implode(' | ', $manquants), 'oui');
    $dire('et le compte en tête de page la compte (2 fiches)', $oui(str_contains($liste, '2 fiches')), 'oui');
    [$parDate] = $appel($a, 'revision?tri=recent');
    $pD = strpos($parDate, 'dossiers/' . $dossier . '/revision');
    $pC = strpos($parDate, 'revision/' . $coursAvecFiche . '" data-fenetre');
    $conditions = [
        'carte du dossier' => $pD !== false,
        'carte du cours' => $pC !== false,
        'dossier avant le cours' => $pD !== false && $pC !== false && $pD < $pC,
        'entête avec matière et date' => str_contains($parDate, 'fiche-carte__entete'),
        'à plat (sans titres de matière)' => !str_contains($parDate, '<h2 class="revisions__matiere">'),
    ];
    $manquants = array_keys(array_filter($conditions, static fn (bool $b): bool => !$b));
    $dire('triée par date : la carte du dossier est à plat, avec sa matière et « modifiée le » comme les autres, la plus récente (la sienne) d\'abord', $manquants === [] ? 'oui' : 'manque : ' . implode(' | ', $manquants), 'oui');
    [$parAncien] = $appel($a, 'revision?tri=ancien');
    $dire('et par « plus ancienne » : la fiche du cours (2020) passe avant celle du dossier', $oui(strpos($parAncien, 'revision/' . $coursAvecFiche . '" data-fenetre') < strpos($parAncien, 'dossiers/' . $dossier . '/revision')), 'oui');
    [$parMatiere] = $appel($a, 'revision?matiere=' . $matiere);
    [$autreMatiere] = $appel($a, 'revision?matiere=' . $maths);
    $dire('le filtre par matière la retrouve (et pas sous « Maths »)', $oui(str_contains($parMatiere, 'dossiers/' . $dossier . '/revision') && !str_contains($autreMatiere, 'dossiers/' . $dossier . '/revision')), 'oui');
    [$cherche] = $appel($a, 'revision?q=' . rawurlencode('cœur'));
    [$pasTrouve] = $appel($a, 'revision?q=zzz');
    $dire('la recherche la trouve par son texte (« cœur ») — surlignée —, et pas pour un mot absent', $oui(str_contains($cherche, 'dossiers/' . $dossier . '/revision') && str_contains($cherche, '<mark') && !str_contains($pasTrouve, 'dossiers/' . $dossier . '/revision')), 'oui');
    // L'avancement de révision : l'anneau de la carte du dossier, comme celui d'un cours (fichiers suivis de sa fiche + cartes des cours reliés)
    bd_run('DELETE FROM cartes WHERE user_id = ?', [$idA]);
    $anneauDe = static function () use ($appel, $a, $carteDossier): string {
        [$l] = $appel($a, 'revision');
        return preg_match($carteDossier, $l, $m) === 1 ? $m[0] : 'CARTE ABSENTE';
    };
    $carte0 = $anneauDe();
    $dire('sans fichier suivi ni carte, la carte du dossier n\'a pas d\'anneau (rien à mesurer)', $oui($carte0 !== 'CARTE ABSENTE' && !str_contains($carte0, 'anneau')), 'oui');
    bd_run('INSERT INTO cartes (user_id, cours_id, question, reponse, origine, empreinte, boite, revoir_le) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())', [$idA, $coursAvecFiche, 'Q avancement', 'R', 'main', md5('avancement'), 5]);
    $carte1 = $anneauDe();
    $dire('une carte apprise (boîte 5) dans un cours du dossier : l\'anneau de la carte du dossier affiche 100 %, « 1 élément »',
        $oui(str_contains($carte1, 'fiche-carte__ecoute') && str_contains($carte1, '>100<span class="anneau__pourcent">%</span>') && str_contains($carte1, '1 élément')), 'oui');
    bd_run('INSERT INTO fichiers (user_id, cours_id, dossier_id, pour_fiche, nom_origine, nom_stocke, mime, taille, position_lecture, duree_lecture) VALUES (?, NULL, ?, 1, ?, ?, ?, ?, ?, ?)', [$idA, $dossier, 'cours-audio.mp3', 'zz_dfi_audio.mp3', 'audio/mpeg', 10, 50, 100]);
    $carte2 = $anneauDe();
    $dire('avec en plus un enregistrement de la fiche du dossier écouté à moitié : la moyenne des deux anneaux, 75 %, « 2 éléments »',
        $oui(str_contains($carte2, '>75<span class="anneau__pourcent">%</span>') && str_contains($carte2, '2 éléments')), 'oui');
    // Les cartes d'un cours que la fiche ne relie plus ne comptent plus
    $appel($a, $url . '/liens', ['_csrf' => $csrfA, 'cours' => [$coursOrdinaire]]);
    $carte3 = $anneauDe();
    $dire('si la fiche ne relie plus que le cours sans carte, l\'anneau ne mesure plus que l\'enregistrement : 50 %',
        $oui(str_contains($carte3, '>50<span class="anneau__pourcent">%</span>')), 'oui');
    $appel($a, $url . '/liens', ['_csrf' => $csrfA, 'tout' => '1']);
    // En page de fiche, la moyenne de la fiche entière en tête, comme pour un cours
    [$pageFiche] = $appel($a, $url . '?fenetre=1');
    $dire('et la page de la fiche porte l\'anneau de tout (75 %) en tête, comme celle d\'un cours', $oui(str_contains($pageFiche, 'data-total-fiche') && str_contains($pageFiche, '>75<span class="anneau__pourcent">%</span>')), 'oui');
    bd_run('DELETE FROM fichiers WHERE nom_stocke = ?', ['zz_dfi_audio.mp3']);
    bd_run('DELETE FROM cartes WHERE user_id = ?', [$idA]);
    $dire('les dossiers sans fiche vont avec « Cours sans fiche » : « Annexes » et « Dossier vide », étiquetés « Dossier », pour en commencer une',
        $oui(preg_match('~href="[^"]*/dossiers/' . $vide . '/revision" data-fenetre>\s*<span>\s*<span class="evt-ligne__titre">🏛️ Dossier vide</span><br>\s*<span class="evt-ligne__meta">\s*Dossier · ~u', $liste) === 1 && str_contains($liste, 'dossiers/' . $annexes . '/revision')), 'oui');
    $appel($a, $url, ['_csrf' => $csrfA, 'fiche_revision' => '']);
    [$apresVide] = $appel($a, 'revision');
    $dire('vidée, la fiche est retirée : plus de carte, le dossier rejoint les « sans fiche » (et le bouton perd son « • »)',
        var_export($fiche($dossier), true) . ' · ' . $oui(preg_match($carteDossier, $apresVide) === 0 && str_contains($apresVide, 'dossiers/' . $dossier . '/revision')) . ' · ' . $oui(!str_contains($appel($a, 'cours?dossier=' . $dossier)[0], '📝 Fiche de révision •')), 'NULL · oui · oui');
    [, $codeSansPdf] = $appel($a, $url . '/pdf');
    $dire('et son PDF reste possible (vide)', (string) $codeSansPdf, '200');

    echo "\n4. Chacun les siennes\n";
    $appel($a, $url, ['_csrf' => $csrfA, 'fiche_revision' => 'Ma fiche à moi.']);
    [, $codeB1] = $appel($b, $url);
    [, $codeB2] = $appel($b, $url, ['_csrf' => $csrfB, 'fiche_revision' => 'Piraté']);
    [, $codeB3] = $appel($b, $url . '/pdf');
    $dire('B n\'ouvre, n\'écrit ni ne télécharge la fiche du dossier de A (404)', $codeB1 . ' · ' . $codeB2 . ' · ' . $codeB3 . ' · ' . $fiche($dossier), '404 · 404 · 404 · Ma fiche à moi.');
    [, $codeA2] = $appel($a, 'dossiers/' . $deB . '/revision');
    $dire('et A n\'ouvre pas celle de B (404)', (string) $codeA2, '404');
    [, $codeSans] = $appel($a, $url, ['fiche_revision' => 'Sans jeton']);
    $dire('sans le jeton de sécurité (CSRF), rien n\'est enregistré (400)', $codeSans . ' · ' . $fiche($dossier), '400 · Ma fiche à moi.');
    [, $codeAbsent] = $appel($a, 'dossiers/999999999/revision');
    $dire('un dossier qui n\'existe pas : 404', (string) $codeAbsent, '404');
    $dire('B voit sa propre fiche sans rien de A', $oui(str_contains($appel($b, 'dossiers/' . $deB . '/revision?fenetre=1')[0], 'Cours de B')), 'oui');

    echo "\n5. Un site dont la migration n'est pas passée\n";
    // Un simple renommage de la colonne (les fiches déjà écrites ne sont pas touchées) : le site la croit absente.
    bd_run('ALTER TABLE dossiers CHANGE fiche_revision fiche_revision_essai LONGTEXT NULL');
    $renomme = true;
    [$sans, $codeSansMigration] = $appel($a, 'cours?dossier=' . $dossier);
    [, $codeFiche] = $appel($a, $url);
    [$sansListe, $codeListe] = $appel($a, 'revision');
    $dire('« Mes cours » se charge sans le bouton, la page de la fiche répond 404, la liste des fiches marche',
        $codeSansMigration . ' · ' . $oui(!str_contains($sans, 'dossiers/' . $dossier . '/revision') && !str_contains($sans, 'Fatal error')) . ' · ' . $codeFiche . ' · ' . $codeListe . ' · ' . $oui(!str_contains($sansListe, 'Fatal error') && !str_contains($sansListe, 'Warning:')), '200 · oui · 404 · 200 · oui');
    bd_run('ALTER TABLE dossiers CHANGE fiche_revision_essai fiche_revision LONGTEXT NULL');
    $renomme = false;

    // La seconde migration (les éléments rattachés) manquante : la fiche reste celle du texte et du contenu, sans erreur.
    bd_run('ALTER TABLE fichiers CHANGE dossier_id dossier_id_essai INT UNSIGNED NULL');
    $renommeFichiers = true;
    [$sansEl, $codeSansEl] = $appel($a, $url . '?fenetre=1');
    $dire('sans la seconde migration : la fiche s\'ouvre (200), avec « Reliés à la fiche » mais sans les rubriques de fichiers, liens ni cartes',
        $codeSansEl . ' · ' . $oui(str_contains($sansEl, 'Reliés à la fiche') && !str_contains($sansEl, 'Fichiers et images') && !str_contains($sansEl, 'Au calendrier') && !str_contains($sansEl, 'Fatal error')), '200 · oui');
    [, $codeSansEl2] = $appel($a, $url . '/elements', ['_csrf' => $csrfA, 'type' => 'lien', 'url' => 'https://exemple.fr/']);
    $dire('et l\'ajout d\'un élément répond 404 plutôt que de planter', (string) $codeSansEl2, '404');
    bd_run('ALTER TABLE fichiers CHANGE dossier_id_essai dossier_id INT UNSIGNED NULL');
    $renommeFichiers = false;

    // La troisième migration (le choix des éléments reliés) manquante : la fiche relie tout le dossier, sans le choix.
    bd_run('ALTER TABLE dossiers CHANGE fiche_choix fiche_choix_essai TINYINT(1) NOT NULL DEFAULT 0');
    $renommeChoix = true;
    [$sansChoix, $codeSansChoix] = $appel($a, $url . '?fenetre=1');
    [, $codeSansChoix2] = $appel($a, $url . '/liens', ['_csrf' => $csrfA, 'tout' => '1']);
    $dire('sans la troisième migration : la fiche s\'ouvre (200) avec « Dans ce dossier » et tout son contenu, sans le choix ; l\'envoi du choix répond 404',
        $codeSansChoix . ' · ' . $oui(str_contains($sansChoix, 'Dans ce dossier') && !str_contains($sansChoix, 'Choisir les fichiers') && !str_contains($sansChoix, 'Fatal error')) . ' · ' . $codeSansChoix2, '200 · oui · 404');
    bd_run('ALTER TABLE dossiers CHANGE fiche_choix_essai fiche_choix TINYINT(1) NOT NULL DEFAULT 0');
    $renommeChoix = false;

    echo "\n6. Les quatre langues\n";
    $appel($a, $url, ['_csrf' => $csrfA, 'fiche_revision' => 'Une fiche pour les langues.']);
    [$pCompte] = $appel($a, 'compte');
    $tokenCompte = $jeton($pCompte);
    foreach (['en' => ['Revision sheet', 'Folder revision sheet', 'Linked to the sheet', 'Folder'], 'es' => ['Ficha de repaso', 'Ficha de repaso de la carpeta', 'Vinculado a la ficha', 'Carpeta'], 'de' => ['Lernblatt', 'Lernblatt des Ordners', 'Mit dem Lernblatt verknüpft', 'Ordner'], 'fr' => ['Fiche de révision', 'Fiche de révision du dossier', 'Reliés à la fiche', 'Dossier']] as $langue => [$bouton, $sousTitre, $contenu, $rev]) {
        $appel($a, 'compte/langue', ['_csrf' => $tokenCompte, 'langue' => $langue]);
        [$ou] = $appel($a, 'cours?dossier=' . $dossier);
        [$fi] = $appel($a, $url . '?fenetre=1');
        [$li] = $appel($a, 'revision');
        $tout = html_entity_decode($ou . $fi . $li, ENT_QUOTES, 'UTF-8');
        $manque = array_values(array_filter(['📝 ' . $bouton, $sousTitre, $contenu, $rev], static fn (string $mot): bool => !str_contains($tout, $mot)));
        $dire($langue . ' : le bouton, la page, « ' . $contenu . ' » et l\'étiquette de la liste', $manque === [] ? 'tout y est' : 'manque : ' . implode(' | ', $manque), 'tout y est');
        $dire($langue . ' : pas de clé brute', $oui(!preg_match('/\bdosf\.[a-z_.]+/', $tout)), 'oui');
    }
    foreach (['fr', 'en', 'es', 'de'] as $langue) {
        $texte = (string) file_get_contents(dirname(__DIR__, 2) . '/lang/' . $langue . '.php');
        preg_match_all("/^    '([^']+)' =>/m", $texte, $cles);
        $doubles = array_keys(array_filter(array_count_values($cles[1]), static fn (int $n): bool => $n > 1));
        $dire($langue . ' : fichier de langue en UTF-8 valide, sans clé écrite deux fois', $oui(mb_check_encoding($texte, 'UTF-8')) . ' · ' . ($doubles === [] ? 'aucune' : implode(', ', array_slice($doubles, 0, 5))), 'oui · aucune');
    }
    $termine = true;
} finally {
    if ($renomme) {
        try { bd_run('ALTER TABLE dossiers CHANGE fiche_revision_essai fiche_revision LONGTEXT NULL'); } catch (Throwable) {}
    }
    if ($renommeFichiers) {
        try { bd_run('ALTER TABLE fichiers CHANGE dossier_id_essai dossier_id INT UNSIGNED NULL'); } catch (Throwable) {}
    }
    if ($renommeChoix) {
        try { bd_run('ALTER TABLE dossiers CHANGE fiche_choix_essai fiche_choix TINYINT(1) NOT NULL DEFAULT 0'); } catch (Throwable) {}
    }
    $nettoyer();
    foreach ($cookies as $f) { @unlink($f); }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'dfi-_@exemple-test.fr'")
        . ' · colonne de la fiche présente : ' . (bd_valeur("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'dossiers' AND column_name = 'fiche_revision'") ? 'oui' : 'NON') . "\n";
}
