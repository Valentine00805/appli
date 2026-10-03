<?php
/*
 * Les listes que l'application tient pour elle-même — « Révisions », « Alternance » — se retrouvent
 * par leur rôle, jamais par leur nom.
 *
 * Elles se retrouvaient par leur nom : « WHERE nom = ? ». Or le nom est écrit dans la langue de son
 * propriétaire, et celui-ci peut le changer. Au premier changement de langue ou de nom, la liste
 * devenait introuvable, une seconde naissait, et la première restait avec ses tâches, orpheline.
 *
 * On vérifie ici qu'elle survit à l'un comme à l'autre, qu'une liste faite à la main — ou venue d'une
 * archive d'avant le rôle — est reconnue plutôt que doublée, et que la clé unique tient.
 */
declare(strict_types=1);

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Paris');

$racine = dirname(__DIR__, 2);
foreach (['Config', 'Depot', 'Session', 'Langue', 'Auth', 'Requete', 'helpers', 'Focus', 'Alternance'] as $classe) {
    require_once $racine . '/src/' . $classe . '.php';
}
Config::charger([
    'db' => ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'mon_appli_cours',
             'user' => 'root', 'pass' => '', 'charset' => 'utf8mb4'],
    'app' => ['nom' => 'Mes Cours', 'dossier_uploads' => $racine . '/storage/uploads'],
]);

$anomalies = 0;
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-58s %s%s\n", $bon ? '✓' : '✗', $quoi, $obtenu, $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$mdp = 'MotDePasse!2026';
$adresses = [];
$compte = static function (string $court) use (&$adresses, $mdp): int {
    $email = 'lsy-' . $court . '@exemple-test.fr';
    $adresses[] = $email;
    Database::run('DELETE FROM users WHERE email = ?', [$email]);
    Database::run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$email, 'Lsy_' . $court, password_hash($mdp, PASSWORD_DEFAULT), 'Essai listes ' . $court]);

    return (int) Database::valeur('SELECT id FROM users WHERE email = ?', [$email]);
};
$listes = static fn (int $u): array => Database::all('SELECT id, nom, role FROM listes_taches WHERE user_id = ? ORDER BY id', [$u]);
$nombre = static fn (int $u): int => count($listes($u));
$ligne = static fn (int $id): array => (array) Database::one('SELECT nom, role FROM listes_taches WHERE id = ?', [$id]);

$u = ['a' => $compte('a'), 'b' => $compte('b'), 'c' => $compte('c'), 'd' => $compte('d'), 'e' => $compte('e')];

try {
    echo "\n1. La liste naît, dans la langue de son propriétaire\n";
    Langue::imposer('fr');
    $revisions = Focus::listeDesRevisions($u['a']);
    $dire('en français : « Révisions »', $ligne($revisions)['nom'], 'Révisions');
    $dire('  son rôle', (string) $ligne($revisions)['role'], 'revisions');
    $dire('  un second appel rend la même', (string) Focus::listeDesRevisions($u['a']), (string) $revisions);

    echo "\n2. Il change de langue : c'est la même liste\n";
    Langue::imposer('en');
    $dire('en anglais, la même liste', (string) Focus::listeDesRevisions($u['a']), (string) $revisions);
    $dire('  et aucune jumelle', (string) $nombre($u['a']), '1');
    $dire('  elle garde son nom : c’est une donnée', $ligne($revisions)['nom'], 'Révisions');
    $dire('  et les messages la citent par ce nom', Focus::nomDeLaListe($u['a']), 'Révisions');

    echo "\n3. Il la renomme : c'est encore la même\n";
    Database::run('UPDATE listes_taches SET nom = ? WHERE id = ?', ['Mon plan de révision', $revisions]);
    Langue::imposer('de');
    $dire('renommée, retrouvée', (string) Focus::listeDesRevisions($u['a']), (string) $revisions);
    $dire('  toujours une seule liste', (string) $nombre($u['a']), '1');
    $dire('  les messages disent son nom à lui', Focus::nomDeLaListe($u['a']), 'Mon plan de révision');

    echo "\n4. Elle est supprimée : elle renaît, dans la langue du moment\n";
    Database::run('DELETE FROM listes_taches WHERE id = ? AND user_id = ?', [$revisions, $u['a']]);
    $dire('avant la recréation, le nom de départ', Focus::nomDeLaListe($u['a']), 'Wiederholung');
    $nouvelle = Focus::listeDesRevisions($u['a']);
    $dire('une nouvelle liste', $oui($nouvelle !== $revisions), 'oui');
    $dire('  en allemand', $ligne($nouvelle)['nom'], 'Wiederholung');

    echo "\n5. L'alternance a la sienne, sans rien emprunter\n";
    Langue::imposer('en');
    $alternance = Alternance::listeDesTaches($u['a']);
    $dire('« Apprenticeship »', $ligne($alternance)['nom'], 'Apprenticeship');
    $dire('  son rôle', (string) $ligne($alternance)['role'], 'alternance');
    $dire('  distincte des révisions', $oui($alternance !== $nouvelle), 'oui');
    $dire('  deux listes, pas davantage', (string) $nombre($u['a']), '2');
    Langue::imposer('es');
    $dire('en espagnol, la même', (string) Alternance::listeDesTaches($u['a']), (string) $alternance);

    echo "\n6. Lire ne crée rien\n";
    Langue::imposer('en');
    $dire('les tâches à faire d’un compte sans liste', (string) count(Alternance::tachesAFaire($u['b'])), '0');
    $dire('  et aucune liste n’est née', (string) $nombre($u['b']), '0');

    echo "\n7. Une liste du même nom, faite à la main, est reconnue\n";
    Langue::imposer('es');
    Database::run('INSERT INTO listes_taches (user_id, nom, position) VALUES (?, ?, 1)', [$u['b'], 'Repaso']);
    $faite = (int) Database::valeur('SELECT id FROM listes_taches WHERE user_id = ?', [$u['b']]);
    $dire('« Repaso » faite à la main : adoptée', (string) Focus::listeDesRevisions($u['b']), (string) $faite);
    $dire('  elle a pris son rôle', (string) $ligne($faite)['role'], 'revisions');
    $dire('  et pas de jumelle', (string) $nombre($u['b']), '1');

    echo "\n8. Une liste d'une archive d'avant le rôle est reconnue\n";
    Langue::imposer('en');
    // Une archive ancienne : « Alternance », en français, sans rôle — alors que le compte est en anglais.
    Database::run('INSERT INTO listes_taches (user_id, nom, position) VALUES (?, ?, 2)', [$u['b'], 'Alternance']);
    $ancienne = (int) Database::valeur('SELECT id FROM listes_taches WHERE user_id = ? AND nom = ?', [$u['b'], 'Alternance']);
    $dire('« Alternance » sans rôle, compte en anglais : adoptée', (string) Alternance::listeDesTaches($u['b']), (string) $ancienne);
    $dire('  elle a pris son rôle', (string) $ligne($ancienne)['role'], 'alternance');
    $dire('  deux listes en tout', (string) $nombre($u['b']), '2');
    // Lire adopte aussi : le tableau de l'alternance affiche ses tâches dès la restauration.
    Database::run('INSERT INTO listes_taches (user_id, nom, position) VALUES (?, ?, 1)', [$u['d'], 'Alternance']);
    $lue = (int) Database::valeur('SELECT id FROM listes_taches WHERE user_id = ?', [$u['d']]);
    Alternance::tachesAFaire($u['d']);
    $dire('lue sans être créée : elle prend son rôle quand même', (string) $ligne($lue)['role'], 'alternance');

    echo "\n9. Le nom est pris par l'autre liste de l'application\n";
    Langue::imposer('de');
    // Chez ce compte, la liste d'alternance a été renommée « Wiederholung » — le nom des révisions en allemand.
    Database::run("INSERT INTO listes_taches (user_id, nom, role, position) VALUES (?, 'Wiederholung', 'alternance', 1)", [$u['c']]);
    $revisionsC = Focus::listeDesRevisions($u['c']);
    $dire('les révisions naissent sans refus', $oui($revisionsC > 0), 'oui');
    $dire('  sous un nom libre', $ligne($revisionsC)['nom'], 'Wiederholung (2)');
    $dire('  chacune son rôle', implode(' · ', array_column($listes($u['c']), 'role')), 'alternance · revisions');

    echo "\n10. La clé unique tient\n";
    $refus = '';
    try {
        Database::run("INSERT INTO listes_taches (user_id, nom, role, position) VALUES (?, 'Autre', 'revisions', 9)", [$u['a']]);
    } catch (PDOException $e) {
        $refus = (string) $e->getCode();
    }
    $dire('une seconde liste « revisions » est refusée par la base', $refus, '23000');
    Database::run("INSERT INTO listes_taches (user_id, nom, position) VALUES (?, 'Libre 1', 8), (?, 'Libre 2', 9)", [$u['a'], $u['a']]);
    $dire('  alors que plusieurs listes sans rôle cohabitent', (string) Database::valeur(
        'SELECT COUNT(*) FROM listes_taches WHERE user_id = ? AND role IS NULL', [$u['a']]), '2');
    $dire('  chaque compte a les siennes', $oui(
        Focus::listeDesRevisions($u['a']) !== Focus::listeDesRevisions($u['b'])), 'oui');

    echo "\n11. Les tâches de révision : titre traduit, et jamais en double\n";
    Database::run('INSERT INTO cours (user_id, titre) VALUES (?, ?)', [$u['e'], 'Algebra']);
    $cours = (int) Database::valeur('SELECT id FROM cours WHERE user_id = ?', [$u['e']]);
    $titresDe = static fn (): array => array_column(Database::all(
        'SELECT titre FROM taches WHERE user_id = ? ORDER BY id', [$u['e']]), 'titre');
    Langue::imposer('en');
    $bilan = Focus::programmerRevisions($u['e'], $cours);
    $dire('en anglais : trois révisions posées', (string) $bilan['posees'], '3');
    $dire('  leur titre est anglais', implode(' | ', array_unique($titresDe())), 'Review: Algebra');
    Langue::imposer('fr');
    $bilan = Focus::programmerRevisions($u['e'], $cours);
    $dire('il passe au français et les repose : rien de neuf', $bilan['posees'] . ' posées · ' . $bilan['connues'] . ' connues', '0 posées · 3 connues');
    $dire('  trois tâches, pas six', (string) count($titresDe()), '3');
    Langue::imposer('de');
    $dire('en allemand, de même', Focus::programmerRevisions($u['e'], $cours)['posees'] . ' posées', '0 posées');
    // Une tâche d'avant la traduction : son titre est français, et on la reconnaît encore.
    Database::run('UPDATE taches SET titre = ? WHERE user_id = ?', ['Revoir : Algebra', $u['e']]);
    $dire('les anciennes, en français, sont reconnues', Focus::programmerRevisions($u['e'], $cours)['posees'] . ' posées', '0 posées');

    echo "\n12. Par la page, avec les messages qui citent la liste\n";
    $mdp2 = $mdp;
    $ck = __DIR__ . '/ck_listes.txt';
    @unlink($ck);
    $appel = static function (string $chemin, ?array $post = null) use ($ck): string {
        usleep(300000);
        $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
            CURLOPT_COOKIEJAR => $ck, CURLOPT_COOKIEFILE => $ck]);
        if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
        return (string) curl_exec($h);
    };
    $jeton = static fn (string $h): string => preg_match('/name="_csrf" value="([^"]+)"/', $h, $m) === 1 ? $m[1] : '';
    $phrase = static fn (string $langue, string $cle, string $liste): string => htmlspecialchars(
        str_replace('{liste}', $liste, (string) (require $racine . '/lang/' . $langue . '.php')[$cle]), ENT_QUOTES);

    $emailF = 'lsy-f@exemple-test.fr';
    $adresses[] = $emailF;
    Database::run('DELETE FROM users WHERE email = ?', [$emailF]);
    Database::run('INSERT INTO users (email, pseudo, password_hash, nom, langue) VALUES (?, ?, ?, ?, ?)',
        [$emailF, 'Lsy_f', password_hash($mdp2, PASSWORD_DEFAULT), 'Essai listes f', 'en']);
    $u['f'] = (int) Database::valeur('SELECT id FROM users WHERE email = ?', [$emailF]);
    Database::run('INSERT INTO cours (user_id, titre) VALUES (?, ?)', [$u['f'], 'Algebra']);
    $coursF = (int) Database::valeur('SELECT id FROM cours WHERE user_id = ?', [$u['f']]);

    $appel('connexion', ['_csrf' => $jeton($appel('connexion')), 'identifiant' => $emailF, 'mot_de_passe' => $mdp2]);
    $csrf = $jeton($appel('compte'));
    // L'aide sur les révisions espacées ne s'affiche qu'après une session terminée : on en simule une.
    $session = Focus::demarrer($u['f'], [$coursF], null, 25);
    // terminer() plafonne la durée au temps écoulé : la session a donc commencé il y a une demi-heure.
    Database::run('UPDATE sessions_revision SET debut = NOW() - INTERVAL 30 MINUTE WHERE id = ?', [$session]);
    Focus::terminer($u['f'], $session, 1500, 0, null);
    $page = $appel('focus');
    $dire('avant toute liste : l’aide cite le nom de départ, en anglais',
        $oui(str_contains($page, $phrase('en', 'focus.espacer_aide', 'Revision'))), 'oui');
    $dire('  et pas « Révisions »', $oui(!str_contains($page, '« Révisions »') && !str_contains($page, '“Révisions”')), 'oui');
    $suite = $appel('focus/espacer', ['_csrf' => $csrf, 'cours_id' => $coursF]);
    $dire('les poser : le message cite « Revision »',
        $oui(str_contains($suite, htmlspecialchars('3 revisions set in “Revision”', ENT_QUOTES))), 'oui');
    $dire('  la liste est née, avec son rôle', Database::valeur(
        'SELECT nom FROM listes_taches WHERE user_id = ? AND role = ?', [$u['f'], 'revisions']) . '', 'Revision');
    // Il la renomme, puis change de langue, puis repose : le message cite SON nom, et rien ne double.
    Database::run('UPDATE listes_taches SET nom = ? WHERE user_id = ? AND role = ?', ['Mon plan', $u['f'], 'revisions']);
    $appel('compte/langue', ['_csrf' => $csrf, 'langue' => 'de']);
    $suite = $appel('focus/espacer', ['_csrf' => $csrf, 'cours_id' => $coursF]);
    $dire('en allemand, renommée : le message cite « Mon plan »',
        $oui(str_contains($suite, $phrase('de', 'flash.focus_deja_posees', 'Mon plan'))), 'oui');
    $dire('  une seule liste, trois tâches', Database::valeur('SELECT COUNT(*) FROM listes_taches WHERE user_id = ?', [$u['f']])
        . ' · ' . Database::valeur('SELECT COUNT(*) FROM taches WHERE user_id = ?', [$u['f']]), '1 · 3');
    $dire('  et l’aide de la page cite son nom aussi',
        $oui(str_contains($appel('focus'), $phrase('de', 'focus.espacer_aide', 'Mon plan'))), 'oui');
    @unlink($ck);
} finally {
    // Ménage : ces comptes d'essai seuls, et ce qu'ils ont semé.
    foreach ($u as $id) {
        if ($id > 0) {
            Database::run('DELETE FROM taches WHERE user_id = ?', [$id]);
            Database::run('DELETE FROM listes_taches WHERE user_id = ?', [$id]);
        }
    }
    foreach ($adresses as $adresse) {
        Database::run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$adresse, '%@exemple-test.fr']);
    }
    $restants = 0;
    foreach ($adresses as $adresse) {
        $restants += (int) Database::valeur('SELECT COUNT(*) FROM users WHERE email = ?', [$adresse]);
    }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . $restants . "\n";
}
