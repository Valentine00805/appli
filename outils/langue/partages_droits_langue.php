<?php
/**
 * Les droits d'un partage, pour tous ou un par personne (HTTP + base) : les fenêtres « Partager » et « Partager plusieurs » (le choix, le menu de
 * chaque ami et de chaque groupe, sans « modification » pour un fichier), l'enregistrement (le même droit pour tous ; un droit à chacun, avec le
 * droit commun pour qui n'en a pas ; un droit invalide ramené à la lecture ; celui d'une personne non cochée ignoré ; celui d'un groupe pour tous
 * ses membres), l'envoi de plusieurs documents, et les quatre langues.
 */
require __DIR__ . '/base.php';

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
    'a' => ['email' => 'pdr-a@exemple-test.fr', 'pseudo' => 'Pdr_un'],       // partage
    'b' => ['email' => 'pdr-b@exemple-test.fr', 'pseudo' => 'Pdr_deux'],     // ami
    'c' => ['email' => 'pdr-c@exemple-test.fr', 'pseudo' => 'Pdr_trois'],    // ami
    'd' => ['email' => 'pdr-d@exemple-test.fr', 'pseudo' => 'Pdr_quatre'],   // membre du groupe
    'f' => ['email' => 'pdr-f@exemple-test.fr', 'pseudo' => 'Pdr_cinq'],     // membre du groupe
];
foreach ($comptes as $c => $d) {
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$d['email'], '%@exemple-test.fr']);
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$d['email'], $d['pseudo'], password_hash($mdp, PASSWORD_DEFAULT), 'Essai ' . $c]);
    $comptes[$c]['id'] = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$d['email']]);
}
$id = static fn (string $c): int => $comptes[$c]['id'];
$cookie = __DIR__ . '/ck_pdr.txt';
@unlink($cookie);

/** @return array{0: int, 1: string} */
$appel = static function (string $chemin, ?array $post = null) use ($cookie): array {
    usleep(300000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    $code = (int) curl_getinfo($h, CURLINFO_HTTP_CODE);
    unset($h);
    return [$code, $corps];
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';

$groupe = null;
try {
    [, $p] = $appel('connexion');
    $appel('connexion', ['_csrf' => $jeton($p), 'identifiant' => $comptes['a']['email'], 'mot_de_passe' => $mdp]);
    [, $compte] = $appel('compte');
    $csrf = $jeton($compte);

    foreach (['b', 'c'] as $ami) {
        bd_run("INSERT INTO amities (demandeur_id, destinataire_id, petit_id, grand_id, statut, created_at, acceptee_le) VALUES (?, ?, ?, ?, 'acceptee', UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$id('a'), $id($ami), min($id('a'), $id($ami)), max($id('a'), $id($ami))]);
    }
    bd_run('INSERT INTO conversations (nom, cree_par, created_at) VALUES (?, ?, UTC_TIMESTAMP())', ['Groupe de droits', $id('a')]);
    $groupe = (int) bd_valeur('SELECT id FROM conversations WHERE cree_par = ? AND nom = ?', [$id('a'), 'Groupe de droits']);
    foreach (['a' => 'admin', 'd' => 'membre', 'f' => 'membre'] as $qui => $role) {
        bd_run('INSERT INTO conversation_membres (conversation_id, user_id, role, rejoint_le) VALUES (?, ?, ?, UTC_TIMESTAMP())', [$groupe, $id($qui), $role]);
    }
    bd_run('INSERT INTO cours (user_id, titre, contenu) VALUES (?, ?, ?), (?, ?, ?)', [$id('a'), 'Cours droits un', 'Texte un.', $id('a'), 'Cours droits deux', 'Texte deux.']);
    $cours = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$id('a'), 'Cours droits un']);
    $cours2 = (int) bd_valeur('SELECT id FROM cours WHERE user_id = ? AND titre = ?', [$id('a'), 'Cours droits deux']);
    bd_run('INSERT INTO fichiers (user_id, cours_id, pour_fiche, nom_origine, nom_stocke, mime, taille) VALUES (?, ?, 0, ?, ?, ?, 10)',
        [$id('a'), $cours, 'droits.pdf', 'essai-pdr-1.pdf', 'application/pdf']);
    $fichier = (int) bd_valeur('SELECT id FROM fichiers WHERE cours_id = ?', [$cours]);

    $droit = static fn (string $qui, string $type, int $cible): string => (string) (bd_valeur(
        'SELECT droit FROM partages_amis WHERE destinataire_id = ? AND cible_type = ? AND cible_id = ?', [$id($qui), $type, $cible]) ?? '-');
    $effacer = static fn () => bd_run('DELETE FROM partages_amis WHERE proprietaire_id = ?', [$id('a')]);
    $envoyer = static fn (array $plus) => $appel('partager/cours/' . $cours . '/amis', ['_csrf' => $GLOBALS['csrf']] + $plus);
    $GLOBALS['csrf'] = $csrf;

    echo "\n1. La fenêtre « Partager » d'un cours\n";
    [$cod, $fen] = $appel('partager/cours/' . $cours . '?fenetre=1');
    $dire('le choix : un droit par personne, ou les mêmes droits pour tous (dans cet ordre)', (string) $cod . ' · ' . $oui(str_contains($fen, 'name="droits_mode" value="tous"')
        && str_contains($fen, 'name="droits_mode" value="chacun"') && strpos($fen, 'value="chacun"') < strpos($fen, 'value="tous"')
        && str_contains($fen, 'Les mêmes droits pour tous') && str_contains($fen, 'Un droit par personne')), '200 · oui');
    $dire('« un droit par personne » est coché d\'office, et le choix « pour tous » est caché',
        $oui(preg_match('/value="chacun"[^>]*checked/', $fen) === 1 && preg_match('/value="tous"[^>]*checked/', $fen) !== 1
            && preg_match('/data-droits-tous hidden/', $fen) === 1 && str_contains($fen, 'name="droit" value="modification"')), 'oui');
    $dire('chaque ami a son menu de droits, visible et actif d\'office',
        $oui(preg_match('/name="droits_amis\[' . $id('b') . '\]"[^>]*data-droit-perso[^>]*>/s', $fen, $mb) === 1 && !str_contains($mb[0], 'hidden') && !str_contains($mb[0], 'disabled')
            && preg_match('/name="droits_amis\[' . $id('c') . '\]"[^>]*data-droit-perso[^>]*>/s', $fen, $mc) === 1 && !str_contains($mc[0], 'hidden') && !str_contains($mc[0], 'disabled')), 'oui');
    $dire('et chaque groupe aussi', $oui(preg_match('/name="droits_groupes\[' . $groupe . '\]"[^>]*data-droit-perso[^>]*>/s', $fen, $mg) === 1 && !str_contains($mg[0], 'hidden') && !str_contains($mg[0], 'disabled')), 'oui');
    $dire('le menu offre lecture, commentaire et modification',
        $oui(preg_match('/name="droits_amis\[' . $id('b') . '\]".*?<\/select>/s', $fen, $m) === 1 && str_contains($m[0], 'value="lecture"') && str_contains($m[0], 'value="commentaire"') && str_contains($m[0], 'value="modification"')), 'oui');
    [, $fenFichier] = $appel('partager/fichiers/' . $fichier . '?fenetre=1');
    $dire('pour un fichier : pas de « modification », ni pour tous ni par personne',
        $oui(str_contains($fenFichier, 'name="droits_amis[' . $id('b') . ']"') && !str_contains($fenFichier, 'value="modification"')), 'oui');

    echo "\n2. Le même droit pour tous\n";
    $envoyer(['amis' => [$id('b'), $id('c')], 'droits_mode' => 'tous', 'droit' => 'commentaire']);
    $dire('B et C reçoivent « commentaire »', $droit('b', 'cours', $cours) . '|' . $droit('c', 'cours', $cours), 'commentaire|commentaire');
    $effacer();
    $envoyer(['amis' => [$id('b'), $id('c')], 'droit' => 'modification']);
    $dire('l\'ancien formulaire (sans le choix) donne le même droit à tous', $droit('b', 'cours', $cours) . '|' . $droit('c', 'cours', $cours), 'modification|modification');
    $effacer();
    $envoyer(['amis' => [$id('b'), $id('c')], 'droits_mode' => 'tous', 'droit' => 'lecture', 'droits_amis' => [$id('b') => 'modification']]);
    $dire('« tous » : les droits par personne postés sont ignorés', $droit('b', 'cours', $cours) . '|' . $droit('c', 'cours', $cours), 'lecture|lecture');

    echo "\n3. Un droit par personne\n";
    $effacer();
    $envoyer(['amis' => [$id('b'), $id('c')], 'droits_mode' => 'chacun', 'droit' => 'commentaire',
        'droits_amis' => [$id('b') => 'modification', $id('c') => 'lecture']]);
    $dire('B reçoit « modification », C « lecture »', $droit('b', 'cours', $cours) . '|' . $droit('c', 'cours', $cours), 'modification|lecture');
    $envoyer(['amis' => [$id('b'), $id('c')], 'droits_mode' => 'chacun', 'droit' => 'commentaire', 'droits_amis' => [$id('b') => 'lecture']]);
    $dire('sans droit donné à C : il reçoit le droit commun (et B change)', $droit('b', 'cours', $cours) . '|' . $droit('c', 'cours', $cours), 'lecture|commentaire');
    $envoyer(['amis' => [$id('b')], 'droits_mode' => 'chacun', 'droit' => 'lecture', 'droits_amis' => [$id('b') => 'modification', $id('c') => 'modification']]);
    $dire('le droit d\'une personne non cochée est ignoré', $droit('b', 'cours', $cours) . '|' . $droit('c', 'cours', $cours), 'modification|commentaire');
    $envoyer(['amis' => [$id('b'), $id('c')], 'droits_mode' => 'chacun', 'droit' => 'lecture', 'droits_amis' => [$id('b') => 'administrateur', $id('c') => ['x']]]);
    $dire('un droit invalide est ramené à la lecture', $droit('b', 'cours', $cours) . '|' . $droit('c', 'cours', $cours), 'lecture|lecture');

    echo "\n4. Un groupe\n";
    $effacer();
    $envoyer(['groupes' => [$groupe], 'droits_mode' => 'chacun', 'droit' => 'lecture', 'droits_groupes' => [$groupe => 'modification']]);
    $dire('tous les membres du groupe reçoivent le droit du groupe', $droit('d', 'cours', $cours) . '|' . $droit('f', 'cours', $cours) . '|' . $droit('a', 'cours', $cours), 'modification|modification|-');
    $envoyer(['groupes' => [$groupe], 'droits_mode' => 'chacun', 'droit' => 'commentaire']);
    $dire('sans droit pour le groupe : le droit commun', $droit('d', 'cours', $cours) . '|' . $droit('f', 'cours', $cours), 'commentaire|commentaire');
    $effacer();
    $envoyer(['amis' => [$id('b')], 'groupes' => [$groupe], 'droits_mode' => 'chacun', 'droit' => 'lecture',
        'droits_amis' => [$id('b') => 'commentaire'], 'droits_groupes' => [$groupe => 'modification']]);
    $dire('un ami et un groupe en même temps, chacun son droit', $droit('b', 'cours', $cours) . '|' . $droit('d', 'cours', $cours), 'commentaire|modification');

    echo "\n5. « Partager plusieurs »\n";
    [$cod, $lot] = $appel('partager/plusieurs?fenetre=1');
    $dire('la fenêtre a le choix et le menu de chaque ami et groupe', $cod . ' · ' . $oui(str_contains($lot, 'name="droits_mode" value="chacun"')
        && str_contains($lot, 'name="droits_amis[' . $id('b') . ']"') && str_contains($lot, 'name="droits_groupes[' . $groupe . ']"')), '200 · oui');
    $effacer();
    $appel('partager/plusieurs/amis', ['_csrf' => $csrf, 'cours' => [$cours, $cours2], 'amis' => [$id('b'), $id('c')], 'groupes' => [$groupe],
        'droits_mode' => 'chacun', 'droit' => 'lecture', 'droits_amis' => [$id('b') => 'modification', $id('c') => 'commentaire'], 'droits_groupes' => [$groupe => 'commentaire']]);
    $dire('chaque document, un droit par ami', $droit('b', 'cours', $cours) . '|' . $droit('b', 'cours', $cours2) . '|' . $droit('c', 'cours', $cours) . '|' . $droit('c', 'cours', $cours2),
        'modification|modification|commentaire|commentaire');
    $dire('et le groupe', $droit('d', 'cours', $cours) . '|' . $droit('f', 'cours', $cours2), 'commentaire|commentaire');
    $effacer();
    $appel('partager/plusieurs/amis', ['_csrf' => $csrf, 'cours' => [$cours], 'amis' => [$id('b'), $id('c')], 'droits_mode' => 'tous', 'droit' => 'modification']);
    $dire('le même droit pour tous marche aussi pour plusieurs documents', $droit('b', 'cours', $cours) . '|' . $droit('c', 'cours', $cours), 'modification|modification');

    echo "\n6. Les quatre langues\n";
    foreach (['en' => ['One permission per person', 'The same permissions for everyone'], 'es' => ['Un permiso por persona', 'Los mismos permisos para todos'],
        'de' => ['Ein Recht pro Person', 'Dieselben Rechte für alle'], 'fr' => ['Un droit par personne', 'Les mêmes droits pour tous']] as $langue => $mots) {
        $appel('compte/langue', ['_csrf' => $csrf, 'langue' => $langue]);
        [, $un] = $appel('partager/cours/' . $cours . '?fenetre=1');
        [, $lot] = $appel('partager/plusieurs?fenetre=1');
        foreach ([['« Partager »', $un], ['« Partager plusieurs »', $lot]] as [$nom, $vue]) {
            $dire("$langue : $nom est traduit, sans clé brute", $oui(str_contains($vue, $mots[0]) && str_contains($vue, $mots[1]) && !preg_match('/\bpt\.droits_[a-z_]+/', $vue)), 'oui');
        }
    }
    $termine = true;
} finally {
    // Ménage : le groupe d'essai, puis les comptes (leurs cours, fichiers et partages partent avec eux).
    if ($groupe !== null && $groupe > 0) {
        bd_run('DELETE FROM conversations WHERE id = ? AND cree_par = ?', [$groupe, $id('a')]);
    }
    foreach ($comptes as $c => $d) {
        if (isset($d['id'])) { bd_run('DELETE FROM users WHERE id = ? AND email = ? AND email LIKE ?', [$d['id'], $d['email'], '%@exemple-test.fr']); }
    }
    @unlink($cookie);
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    $restants = (int) bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'pdr-_@exemple-test.fr'");
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . $restants . "\n";
}
