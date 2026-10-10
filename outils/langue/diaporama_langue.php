<?php
/**
 * L'aperçu d'une présentation PowerPoint en diapositives (HTTP + base) : un .pptx fabriqué ici (deux diapositives dans l'ordre de la
 * liste, un titre qui prend sa taille et sa couleur de la mise en page, des puces et du gras, un rectangle en partie transparent, une
 * forme libre, une image, un tableau), son aperçu, ses images servies depuis l'archive — et rien d'autre —, le retour au texte seul, et
 * les quatre langues.
 *
 * La base locale est désignée par « config/parametres.test.php » (lu seulement depuis le poste, retiré à la fin).
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

$racine = dirname(__DIR__, 2);
$fichierEssai = $racine . '/config/parametres.test.php';
$sauvegarde = $fichierEssai . '.avant-essai';
if (is_file($fichierEssai)) { rename($fichierEssai, $sauvegarde); }
file_put_contents($fichierEssai, '<?php return ' . var_export([
    'db' => ['host' => '127.0.0.1', 'name' => 'mon_appli_cours', 'user' => 'root', 'pass' => ''],
], true) . ';');
sleep(3);   // le serveur web garde le fichier compilé quelques secondes (OPcache)

$emails = ['diapo-a@exemple-test.fr', 'diapo-b@exemple-test.fr'];
foreach ($emails as $i => $e) {
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']);
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$e, 'Diapo_' . $i, password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai diaporama']);
}
[$idA, $idB] = array_map(static fn (string $e): int => (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$e]), $emails);

$cookies = [__DIR__ . '/ck_diapo_a.txt', __DIR__ . '/ck_diapo_b.txt'];
foreach ($cookies as $f) { @unlink($f); }
/** @return array{0: string, 1: int, 2: string, 3: string} corps, code, adresse finale, type de contenu */
$appel = static function (int $qui, string $chemin, ?array $post = null) use ($cookies): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . ltrim($chemin, '/'));
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $cookies[$qui], CURLOPT_COOKIEFILE => $cookies[$qui]]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, $post); }
    $corps = (string) curl_exec($h);
    $r = [$corps, (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL), (string) curl_getinfo($h, CURLINFO_CONTENT_TYPE)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';

// --- Un .pptx minimal, fabriqué ici : de quoi éprouver chaque morceau du rendu sans dépendre d'un vrai fichier.
$png = static function (): string {
    $im = imagecreatetruecolor(8, 8);
    imagefill($im, 0, 0, imagecolorallocate($im, 200, 30, 30));
    ob_start();
    imagepng($im);
    return (string) ob_get_clean();
};
$octetsImage = $png();
$ns = 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"';
$rels = static fn (array $lignes): string => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . implode('', array_map(static fn (array $l): string => '<Relationship Id="' . $l[0] . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/' . $l[1] . '" Target="' . $l[2] . '"/>', $lignes)) . '</Relationships>';
$groupeVide = '<p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>';
$diapo1 = '<?xml version="1.0" encoding="UTF-8"?><p:sld ' . $ns . '><p:cSld><p:spTree>' . $groupeVide
    // Le titre : ni boîte ni taille ni couleur — tout vient de la mise en page.
    . '<p:sp><p:nvSpPr><p:cNvPr id="2" name="Titre"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr><p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:rPr lang="fr-FR"/><a:t>Titre de la présentation</a:t></a:r></a:p></p:txBody></p:sp>'
    // Un texte libre : une puce, du gras, une taille.
    . '<p:sp><p:nvSpPr><p:cNvPr id="3" name="Texte"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="914400" y="1500000"/><a:ext cx="4000000" cy="1200000"/></a:xfrm><a:prstGeom prst="rect"/></p:spPr><p:txBody><a:bodyPr/><a:lstStyle/>'
    . '<a:p><a:pPr marL="285750" indent="-285750"><a:buChar char="•"/></a:pPr><a:r><a:rPr lang="fr-FR" sz="2000" b="1"/><a:t>Point en gras</a:t></a:r></a:p></p:txBody></p:sp>'
    // Un rectangle à moitié transparent.
    . '<p:sp><p:nvSpPr><p:cNvPr id="4" name="Rectangle"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="5200000" y="1500000"/><a:ext cx="2000000" cy="1000000"/></a:xfrm><a:prstGeom prst="rect"/><a:solidFill><a:srgbClr val="FF0000"><a:alpha val="50000"/></a:srgbClr></a:solidFill></p:spPr></p:sp>'
    // Une forme libre.
    . '<p:sp><p:nvSpPr><p:cNvPr id="5" name="Libre"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="0" y="3800000"/><a:ext cx="9144000" cy="1000000"/></a:xfrm><a:custGeom><a:pathLst><a:path w="100" h="50"><a:moveTo><a:pt x="0" y="50"/></a:moveTo><a:lnTo><a:pt x="50" y="0"/></a:lnTo><a:lnTo><a:pt x="100" y="50"/></a:lnTo><a:close/></a:path></a:pathLst></a:custGeom><a:solidFill><a:srgbClr val="00AA00"/></a:solidFill></p:spPr></p:sp>'
    // Une image.
    . '<p:pic><p:nvPicPr><p:cNvPr id="6" name="Image"/><p:cNvPicPr/><p:nvPr/></p:nvPicPr><p:blipFill><a:blip r:embed="rId2"/></p:blipFill><p:spPr><a:xfrm><a:off x="7400000" y="3000000"/><a:ext cx="800000" cy="800000"/></a:xfrm></p:spPr></p:pic>'
    // Un tableau.
    . '<p:graphicFrame><p:nvGraphicFramePr><p:cNvPr id="7" name="Tableau"/><p:cNvGraphicFramePr/><p:nvPr/></p:nvGraphicFramePr><p:xfrm><a:off x="914400" y="2900000"/><a:ext cx="4000000" cy="600000"/></p:xfrm><a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table"><a:tbl><a:tblGrid><a:gridCol w="2000000"/><a:gridCol w="2000000"/></a:tblGrid>'
    . '<a:tr h="300000"><a:tc><a:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Case un</a:t></a:r></a:p></a:txBody></a:tc><a:tc><a:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Case deux</a:t></a:r></a:p></a:txBody></a:tc></a:tr></a:tbl></a:graphicData></a:graphic></p:graphicFrame>'
    . '</p:spTree></p:cSld></p:sld>';
$diapo2 = '<?xml version="1.0" encoding="UTF-8"?><p:sld ' . $ns . '><p:cSld><p:spTree>' . $groupeVide
    . '<p:sp><p:nvSpPr><p:cNvPr id="2" name="Texte"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="914400" y="914400"/><a:ext cx="4000000" cy="800000"/></a:xfrm></p:spPr><p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>Deuxième diapositive</a:t></a:r></a:p></p:txBody></p:sp>'
    . '</p:spTree></p:cSld></p:sld>';
$mise = '<?xml version="1.0" encoding="UTF-8"?><p:sldLayout ' . $ns . '><p:cSld><p:spTree>' . $groupeVide
    . '<p:sp><p:nvSpPr><p:cNvPr id="2" name="Titre"/><p:cNvSpPr/><p:nvPr><p:ph type="title"/></p:nvPr></p:nvSpPr><p:spPr><a:xfrm><a:off x="500000" y="300000"/><a:ext cx="8000000" cy="900000"/></a:xfrm></p:spPr><p:txBody><a:bodyPr anchor="ctr"/><a:lstStyle><a:lvl1pPr algn="ctr"><a:defRPr sz="4000"><a:solidFill><a:srgbClr val="0000FF"/></a:solidFill></a:defRPr></a:lvl1pPr></a:lstStyle><a:p><a:endParaRPr lang="fr-FR"/></a:p></p:txBody></p:sp>'
    . '</p:spTree></p:cSld></p:sldLayout>';
$masque = '<?xml version="1.0" encoding="UTF-8"?><p:sldMaster ' . $ns . '><p:cSld><p:bg><p:bgPr><a:solidFill><a:srgbClr val="FFFFE0"/></a:solidFill></p:bgPr></p:bg><p:spTree>' . $groupeVide . '</p:spTree></p:cSld>'
    . '<p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/>'
    . '<p:txStyles><p:titleStyle><a:lvl1pPr><a:defRPr sz="3200"/></a:lvl1pPr></p:titleStyle><p:bodyStyle><a:lvl1pPr><a:defRPr sz="2000"/></a:lvl1pPr></p:bodyStyle><p:otherStyle><a:lvl1pPr><a:defRPr sz="1800"/></a:lvl1pPr></p:otherStyle></p:txStyles></p:sldMaster>';
$theme = '<?xml version="1.0" encoding="UTF-8"?><a:theme ' . $ns . ' name="Essai"><a:themeElements><a:clrScheme name="Essai"><a:dk1><a:srgbClr val="000000"/></a:dk1><a:lt1><a:srgbClr val="FFFFFF"/></a:lt1><a:dk2><a:srgbClr val="444444"/></a:dk2><a:lt2><a:srgbClr val="EEEEEE"/></a:lt2>'
    . '<a:accent1><a:srgbClr val="3A81BA"/></a:accent1><a:accent2><a:srgbClr val="D89F39"/></a:accent2></a:clrScheme></a:themeElements></a:theme>';
$pptx = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'essai-diaporama-' . bin2hex(random_bytes(4)) . '.pptx';
$zip = new ZipArchive();
$zip->open($pptx, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/></Types>');
$zip->addFromString('_rels/.rels', $rels([['rId1', 'officeDocument', 'ppt/presentation.xml']]));
// L'ordre des diapositives est celui de la liste (rId3 puis rId2), pas celui des numéros de fichier.
$zip->addFromString('ppt/presentation.xml', '<?xml version="1.0" encoding="UTF-8"?><p:presentation ' . $ns . '><p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId1"/></p:sldMasterIdLst><p:sldIdLst><p:sldId id="257" r:id="rId3"/><p:sldId id="256" r:id="rId2"/></p:sldIdLst><p:sldSz cx="9144000" cy="5143500"/></p:presentation>');
$zip->addFromString('ppt/_rels/presentation.xml.rels', $rels([['rId1', 'slideMaster', 'slideMasters/slideMaster1.xml'], ['rId2', 'slide', 'slides/slide1.xml'], ['rId3', 'slide', 'slides/slide2.xml']]));
$zip->addFromString('ppt/slides/slide1.xml', $diapo1);
$zip->addFromString('ppt/slides/_rels/slide1.xml.rels', $rels([['rId1', 'slideLayout', '../slideLayouts/slideLayout1.xml'], ['rId2', 'image', '../media/image1.png']]));
$zip->addFromString('ppt/slides/slide2.xml', $diapo2);
$zip->addFromString('ppt/slides/_rels/slide2.xml.rels', $rels([['rId1', 'slideLayout', '../slideLayouts/slideLayout1.xml']]));
$zip->addFromString('ppt/slideLayouts/slideLayout1.xml', $mise);
$zip->addFromString('ppt/slideLayouts/_rels/slideLayout1.xml.rels', $rels([['rId1', 'slideMaster', '../slideMasters/slideMaster1.xml']]));
$zip->addFromString('ppt/slideMasters/slideMaster1.xml', $masque);
$zip->addFromString('ppt/slideMasters/_rels/slideMaster1.xml.rels', $rels([['rId1', 'theme', '../theme/theme1.xml']]));
$zip->addFromString('ppt/theme/theme1.xml', $theme);
$zip->addFromString('ppt/media/image1.png', $octetsImage);
$zip->close();

try {
    foreach ([0, 1] as $qui) {
        [$p] = $appel($qui, 'connexion');
        $appel($qui, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $emails[$qui], 'mot_de_passe' => 'MotDePasse!2026']);
    }
    [$page] = $appel(0, 'compte');
    $appel(0, 'cours/depot', ['_csrf' => $jeton($page), 'fichiers[0]' => new CURLFile($pptx, 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'Cours API.pptx')]);
    $idFichier = (int) bd_valeur('SELECT f.id FROM fichiers f JOIN cours c ON c.id = f.cours_id WHERE c.user_id = ?', [$idA]);
    $dire('la présentation est déposée', $oui($idFichier > 0), 'oui');

    echo "\n1. L'aperçu en diapositives\n";
    [$apercu] = $appel(0, 'fichiers/' . $idFichier . '/apercu?fenetre=1');
    $dire('deux diapositives dessinées', (string) substr_count($apercu, '<div class="diapo"'), '2');
    $dire('dans l\'ordre de la liste de la présentation (la deuxième d\'abord)', $oui(strpos($apercu, 'Deuxième diapositive') < strpos($apercu, 'Titre de la présentation')), 'oui');
    $dire('chacune a sa proportion (16/9 ici) et le fond du masque', $oui(str_contains($apercu, 'aspect-ratio:9144000/5143500') && str_contains($apercu, 'background:#ffffe0')), 'oui');
    $dire('le titre prend sa taille (40 pt) et sa couleur de la mise en page, et son alignement', $oui(str_contains($apercu, 'font-size:5.556cqw;color:#0000ff;') && str_contains($apercu, 'text-align:center')), 'oui');
    $dire('une puce, du gras, et la taille du texte (20 pt)', $oui(str_contains($apercu, 'diapo__puce') && str_contains($apercu, 'font-size:2.778cqw;font-weight:700;') && str_contains($apercu, 'Point en gras')), 'oui');
    $dire('un rectangle en partie transparent', $oui(str_contains($apercu, 'background:rgba(255,0,0,0.5)')), 'oui');
    $dire('une forme libre, dessinée en SVG', $oui(str_contains($apercu, 'class="diapo__libre"') && str_contains($apercu, 'M0 50 L50 0 L100 50 Z') && str_contains($apercu, 'fill="#00aa00"')), 'oui');
    $dire('un tableau, avec ses cases', $oui(str_contains($apercu, 'diapo__tableau') && str_contains($apercu, 'Case un') && str_contains($apercu, 'Case deux')), 'oui');
    $dire('la légende dit la place de chaque diapositive, et son titre', $oui(str_contains($apercu, 'Diapositive 1 sur 2') && str_contains($apercu, 'Diapositive 2 sur 2') && str_contains($apercu, '— Titre de la présentation')), 'oui');
    $dire('l\'aperçu propose le texte seul', $oui(str_contains($apercu, 'Voir le texte seul') && str_contains($apercu, 'texte=1')), 'oui');

    echo "\n2. Les images, servies depuis l'archive\n";
    $dire('l\'image est une adresse de l\'application, pas des octets dans la page', $oui(preg_match('#<img src="([^"]*diapo-media[^"]*)"#', $apercu, $m) === 1), 'oui');
    $src = html_entity_decode($m[1] ?? '');
    [$octets, $code, , $type] = $appel(0, substr($src, strlen('/mon_appli/appli/')));
    $dire('son propriétaire la reçoit (200, image/png, les mêmes octets)', $code . ' ' . strtok($type, ';') . ' ' . $oui($octets === $octetsImage), '200 image/png oui');
    [, $codeAutre] = $appel(1, substr($src, strlen('/mon_appli/appli/')));
    $dire('un autre compte ne la reçoit pas (404)', (string) $codeAutre, '404');
    foreach (['../../config/parametres.php', 'ppt/slides/slide1.xml', 'ppt/media/../slides/slide1.xml', 'ppt/media/inexistante.png', ''] as $mauvais) {
        [, $codeMauvais] = $appel(0, 'fichiers/' . $idFichier . '/diapo-media?m=' . rawurlencode($mauvais));
        $dire('« ' . ($mauvais === '' ? '(rien)' : $mauvais) . ' » : refusé (404)', (string) $codeMauvais, '404');
    }
    [, $codeVisiteur] = (function () use ($idFichier): array {
        $h = curl_init('http://localhost/mon_appli/appli/fichiers/' . $idFichier . '/diapo-media?m=ppt/media/image1.png');
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false]);
        curl_exec($h);
        $code = (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE);
        unset($h);
        return [null, $code];
    })();
    $dire('un visiteur sans compte est renvoyé vers la connexion', $oui($codeVisiteur === 302), 'oui');

    echo "\n3. Le texte seul, et le retour\n";
    [$texte] = $appel(0, 'fichiers/' . $idFichier . '/apercu?fenetre=1&texte=1');
    $dire('« ?texte=1 » : le texte, sans diapositives, avec le chemin du retour', $oui(!str_contains($texte, '<div class="diapo"') && str_contains($texte, 'Titre de la présentation') && str_contains($texte, 'Voir les diapositives')), 'oui');

    echo "\n4. Les quatre langues\n";
    [$page] = $appel(0, 'compte');
    $csrf = $jeton($page);
    foreach (['en' => ['Slide preview', 'Slide 1 of 2', 'View text only'], 'es' => ['Vista previa de las diapositivas', 'Diapositiva 1 de 2', 'Ver solo el texto'],
        'de' => ['Folienvorschau', 'Folie 1 von 2', 'Nur den Text anzeigen'], 'fr' => ['Aperçu des diapositives', 'Diapositive 1 sur 2', 'Voir le texte seul']] as $langue => $mots) {
        $appel(0, 'compte/langue', ['_csrf' => $csrf, 'langue' => $langue]);
        [$vue] = $appel(0, 'fichiers/' . $idFichier . '/apercu?fenetre=1');
        $dire("$langue : l'aperçu est traduit, sans clé brute", $oui(str_contains($vue, $mots[0]) && str_contains($vue, $mots[1]) && str_contains($vue, $mots[2]) && !preg_match('/\bap\.diapo_[a-z_]+/', $vue)), 'oui');
    }
    $termine = true;
} finally {
    foreach (bd_all('SELECT f.nom_stocke FROM fichiers f JOIN cours c ON c.id = f.cours_id WHERE c.user_id IN (?, ?)', [$idA, $idB]) as $f) {
        @unlink($racine . '/storage/uploads/' . basename((string) $f['nom_stocke']));
    }
    @unlink($pptx);
    @unlink($fichierEssai);
    if (is_file($sauvegarde)) { rename($sauvegarde, $fichierEssai); }
    foreach ($cookies as $f) { @unlink($f); }
    foreach ($emails as $e) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']); }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@exemple-test.fr'])
        . ' · fichier d’essai retiré : ' . (is_file($fichierEssai) ? 'NON' : 'oui') . "\n";
}
