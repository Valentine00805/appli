<?php
/*
 * Efface les comptes d'essai restés en base, un par un, par leur adresse.
 *
 * Une suite interrompue — un refus du serveur, une coupure, l'antivirus
 * autrefois — ne fait pas son ménage de fin. Ce script le fait après coup, et
 * ne touche qu'à des adresses en « @exemple-test.fr » : les vrais comptes ne
 * peuvent pas entrer dans sa requête.
 *
 *     php outils/langue/menage.php            dit ce qu'il voit
 *     php outils/langue/menage.php --efface   l'efface
 */
require __DIR__ . '/base.php';

const DOMAINE = '@exemple-test.fr';

/*
 * Ce qui dépend d'un compte, des feuilles vers la racine. On passe par les
 * tables plutôt que par une clé étrangère en cascade : le schéma n'en a pas
 * partout, et on veut pouvoir lire ce qu'on efface.
 */
$parUserId = ['notifications_file', 'abonnements_push', 'discussions_etat', 'conversation_membres',
    'conversation_epingles', 'taches', 'listes_taches', 'evenements', 'cours', 'matieres',
    'types_evenement', 'tags', 'dossiers', 'operations', 'categories_budget', 'recurrences',
    'soldes_saisis', 'personnes', 'groupes', 'partages_amis', 'partages_calendrier',
    'alternance_documents', 'projet_membres'];

$efface = in_array('--efface', $argv, true);
$comptes = bd_all('SELECT id, email, pseudo FROM users WHERE email LIKE ? ORDER BY id', ['%' . DOMAINE]);

if ($comptes === []) {
    echo 'Aucun compte d’essai en base.' . PHP_EOL;
    exit;
}

echo count($comptes) . ' compte(s) d’essai :' . PHP_EOL;
foreach ($comptes as $c) {
    echo '  ' . $c['id'] . '  ' . $c['email'] . '  (' . $c['pseudo'] . ')' . PHP_EOL;
}

if (!$efface) {
    echo PHP_EOL . 'Rien n’a été effacé. « --efface » le fait.' . PHP_EOL;
    exit;
}

echo PHP_EOL;
foreach ($comptes as $c) {
    $id = (int) $c['id'];
    $email = (string) $c['email'];

    // Deux garde-fous : l'adresse doit porter le domaine d'essai, et la
    // suppression finale porte à la fois sur l'identifiant et sur l'adresse.
    if (!str_ends_with($email, DOMAINE)) {
        echo '  ✗ ' . $email . ' : ce n’est pas une adresse d’essai, on n’y touche pas.' . PHP_EOL;
        continue;
    }

    bd_run('DELETE FROM evenement_revision_cours WHERE evenement_id IN
            (SELECT id FROM evenements WHERE user_id = ?)', [$id]);
    bd_run('DELETE FROM conversation_messages WHERE expediteur_id = ?', [$id]);
    foreach ($parUserId as $table) {
        try {
            bd_run("DELETE FROM $table WHERE user_id = ?", [$id]);
        } catch (PDOException) {
            // Une table absente de ce schéma n'a rien à nous apprendre.
        }
    }
    bd_run('DELETE FROM amities WHERE demandeur_id = ? OR destinataire_id = ?', [$id, $id]);
    bd_run('DELETE FROM conversations WHERE cree_par = ?', [$id]);
    bd_run('DELETE FROM projets WHERE cree_par = ?', [$id]);
    bd_run('DELETE FROM users WHERE id = ? AND email = ?', [$id, $email]);

    echo '  ✓ ' . $email . ' effacé.' . PHP_EOL;
}

$reste = (int) bd_valeur('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%' . DOMAINE]);
echo PHP_EOL . 'Comptes d’essai restants : ' . $reste . PHP_EOL;
echo 'Comptes en base : ' . (int) bd_valeur('SELECT COUNT(*) FROM users') . PHP_EOL;
