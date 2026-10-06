<?php
/*
 * Une notification se lit dans la langue de son destinataire, et non dans celle
 * de qui la déclenche. Trois comptes, trois langues, et la file le prouve.
 *
 * On charge les classes de l'application plutôt que de passer par le serveur :
 * ce qu'on veut mesurer est ce que FileNotifications::ajouter() écrit en base,
 * pas ce qu'une page affiche.
 */
declare(strict_types=1);

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Paris');

$racine = dirname(__DIR__, 2);
foreach (['Config', 'Depot', 'Session', 'Langue', 'Auth', 'Courriel', 'Fichiers',
          'WebPush', 'FileNotifications', 'Amis', 'Conversations', 'Travaux',
          'Partages', 'Difference', 'Requete', 'helpers'] as $classe) {
    require $racine . '/src/' . $classe . '.php';
}

Config::charger([
    'app' => ['nom' => 'Mes Cours', 'dossier_uploads' => $racine . '/storage/uploads'],
]);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/mon_appli/appli/';
$_SERVER['SCRIPT_NAME'] = '/mon_appli/appli/index.php';

define('BASE_URL', Requete::base());

$anomalies = 0;
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-50s %s%s\n", $bon ? '✓' : '✗', $quoi, $obtenu, $bon ? '' : "\n       attendu : " . $attendu);
};

$comptes = [
    'fr' => ['email' => 'notif-fr@exemple-test.fr', 'pseudo' => 'Notif_fr', 'langue' => 'fr'],
    'en' => ['email' => 'notif-en@exemple-test.fr', 'pseudo' => 'Notif_en', 'langue' => 'en'],
    'de' => ['email' => 'notif-de@exemple-test.fr', 'pseudo' => 'Notif_de', 'langue' => 'de'],
];
$ids = [];
foreach ($comptes as $cle => $c) {
    Database::run('DELETE FROM users WHERE email = ?', [$c['email']]);
    Database::run('INSERT INTO users (email, pseudo, password_hash, nom, langue) VALUES (?, ?, ?, ?, ?)',
        [$c['email'], $c['pseudo'], password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai notif', $c['langue']]);
    $ids[$cle] = (int) Database::valeur('SELECT id FROM users WHERE email = ?', [$c['email']]);
}

try {
    /*
     * Une notification ne part qu'à qui a un appareil abonné : on en inscrit un
     * faux pour chacun. Rien ne sera envoyé — on ne lit que ce que la file écrit.
     */
    foreach ($ids as $id) {
        Database::run(
            'INSERT INTO abonnements_push (user_id, point_final, empreinte, cle_p256dh, cle_auth, created_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())',
            [$id, 'https://exemple-test.fr/push/' . $id, md5('essai' . $id),
             str_repeat('a', 87), str_repeat('b', 22)]);
    }

    $lire = static fn (int $id): array => (array) Database::one(
        'SELECT titre, corps FROM notifications_file WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$id]);

    echo "\n1. Une demande d’ami : le texte suit qui la reçoit\n";
    Langue::imposer('fr');
    Amis::notifierDemande($ids['fr'], $ids['en']);
    Amis::notifierDemande($ids['fr'], $ids['de']);
    $en = $lire($ids['en']);
    $de = $lire($ids['de']);
    $dire('l’anglais : « New friend request »', (string) $en['titre'], '👋 New friend request');
    $dire('  et le corps suit', (string) $en['corps'], 'Notif_fr wants to add you as a friend.');
    $dire('l’allemand : « Neue Freundschaftsanfrage »', (string) $de['titre'], '👋 Neue Freundschaftsanfrage');
    $dire('  et le corps suit', (string) $de['corps'], 'Notif_fr möchte dich als Freund hinzufügen.');
    $dire('la langue de qui déclenche est rendue intacte', Langue::courante(), 'fr');

    echo "\n2. Une demande acceptée, dans l’autre sens\n";
    Langue::imposer('de');
    Amis::notifierAcceptation($ids['de'], $ids['fr']);
    $fr = $lire($ids['fr']);
    $dire('le français : « Demande acceptée »', (string) $fr['titre'], '🤝 Demande acceptée');
    $dire('  et le corps aussi', (string) $fr['corps'],
        'Notif_de a accepté votre demande : vous pouvez discuter.');
    $dire('la langue imposée est toujours celle d’avant', Langue::courante(), 'de');

    echo "\n3. Un message et ses pièces jointes\n";
    Langue::imposer('fr');
    Amis::notifier($ids['fr'], $ids['en'], '', true);
    $dire('l’aperçu d’une photo, en anglais', (string) $lire($ids['en'])['corps'], '📷 Photo');
    Amis::notifier($ids['fr'], $ids['de'], 'Hallo', false, null, 75);
    $dire('un vocal, en allemand', (string) $lire($ids['de'])['corps'],
        '🎤 Sprachnachricht (1:15) · Hallo');

    echo "\n4. Deux langues dans la même boucle\n";
    $groupe = Conversations::creer($ids['fr'], 'Essai langue', []);
    Conversations::notifierAjout($ids['fr'], (int) $groupe, [$ids['en'], $ids['de']]);
    $corpsEn = (string) $lire($ids['en'])['corps'];
    $corpsDe = (string) $lire($ids['de'])['corps'];
    echo '     en : ' . $corpsEn . "\n";
    echo '     de : ' . $corpsDe . "\n";
    $dire('chacun a bien sa langue', $corpsEn === $corpsDe ? 'la même pour les deux' : 'chacun la sienne',
        'chacun la sienne');
    $dire('  et le pseudo de qui ajoute y est', str_contains($corpsEn, 'Notif_fr') ? 'oui' : 'non', 'oui');

    echo "\n5. Un partage de document, lu par chacun dans sa langue\n";
    /*
     * Le compte français partage un cours avec l'anglais et l'allemand. L'annonce
     * se fabriquait avant l'envoi, donc dans la langue de l'expéditeur : l'anglais
     * lisait « a partagé le cours… ». L'un reçoit dans la discussion, l'autre
     * dans l'onglet « Partagés » : les deux chemins sont couverts.
     */
    foreach (['en', 'de'] as $langue) {
        Database::run(
            'INSERT IGNORE INTO amities (demandeur_id, destinataire_id, petit_id, grand_id, statut, acceptee_le)
             VALUES (?, ?, ?, ?, \'acceptee\', UTC_TIMESTAMP())',
            [$ids['fr'], $ids[$langue], min($ids['fr'], $ids[$langue]), max($ids['fr'], $ids[$langue])]);
    }
    Database::run('UPDATE users SET partages_dans_discussion = 1 WHERE id = ?', [$ids['en']]);
    Database::run('UPDATE users SET partages_dans_discussion = 0 WHERE id = ?', [$ids['de']]);
    Database::run('INSERT INTO cours (user_id, titre) VALUES (?, ?)', [$ids['fr'], 'Algèbre']);
    $coursId = (int) Database::valeur('SELECT id FROM cours WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$ids['fr']]);

    Langue::imposer('fr');
    [$atteints, $refus] = Partages::partagerAvecAmis($ids['fr'], 'cours', $coursId, [$ids['en'], $ids['de']], [], '');
    $dire('le partage est parti', (string) $atteints . ($refus === null ? '' : ' — ' . $refus), '2');
    $dire('la langue de qui partage est rendue intacte', Langue::courante(), 'fr');

    $en = $lire($ids['en']);
    $de = $lire($ids['de']);
    $dire('dans la discussion, en anglais', (string) $en['corps'],
        '🔗 Notif_fr shared the course “Algèbre”');
    $dire('dans l’onglet, en allemand', (string) $de['corps'],
        'Notif_fr hat den Kurs „Algèbre“ geteilt');
} finally {
    // Ménage : ces trois comptes d'essai seuls, et ce qu'ils ont semé.
    foreach ($ids as $cle => $id) {
        if ($id <= 0) { continue; }
        foreach (['notifications_file', 'abonnements_push', 'discussions_etat',
                  'conversation_membres', 'cours', 'matieres', 'types_evenement'] as $table) {
            Database::run("DELETE FROM $table WHERE user_id = ?", [$id]);
        }
        Database::run('DELETE FROM amities WHERE demandeur_id = ? OR destinataire_id = ?', [$id, $id]);
        Database::run('DELETE FROM conversation_messages WHERE expediteur_id = ?', [$id]);
        Database::run('DELETE FROM conversations WHERE cree_par = ?', [$id]);
        Database::run('DELETE FROM users WHERE id = ? AND email = ?', [$id, $comptes[$cle]['email']]);
    }
    $restants = 0;
    foreach ($comptes as $c) {
        $restants += (int) Database::valeur('SELECT COUNT(*) FROM users WHERE email = ?', [$c['email']]);
    }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . $restants . "\n";
}
