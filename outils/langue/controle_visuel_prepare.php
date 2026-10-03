<?php
/*
 * Prépare le contrôle visuel : un compte d'essai garni de quelques données, et sa session.
 *
 * Le compte est inscrit par le vrai formulaire (pour que ses matières, types d'évènement et
 * catégories de départ existent), puis garni : trois cours, deux évènements, une liste de tâches,
 * quatre opérations de budget. Il est connecté par curl ; ce script écrit l'identifiant de
 * session, que le navigateur reprend — le mot de passe de personne n'est saisi, nulle part.
 *
 *     php outils/langue/controle_visuel_prepare.php
 *
 * Dans le navigateur, sur une page de l'application :
 *
 *     document.cookie = "MESCOURS_SESSID=<identifiant>; path=/mon_appli/appli"; location.reload();
 *
 * Pour changer la langue du compte, entre deux mesures (ce compte seul, par son adresse) :
 *
 *     UPDATE users SET langue = 'de' WHERE email = 'vis-ctrl@exemple-test.fr';
 *
 * « php outils/langue/menage.php --efface » le supprime ensuite.
 */
require __DIR__ . '/base.php';

$email = 'vis-ctrl@exemple-test.fr';
$mdp = 'MotDePasse!2026';
bd_run('DELETE FROM users WHERE email = ?', [$email]);

$jar = tempnam(sys_get_temp_dir(), 'ctrl');
$base = 'http://localhost/mon_appli/appli/';
$appel = static function (string $chemin, ?array $post = null) use ($jar, $base): string {
    usleep(300000);
    $h = curl_init($base . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    return (string) curl_exec($h);
};
$jeton = static fn (string $h): string => preg_match('/name="_csrf" value="([^"]+)"/', $h, $m) === 1 ? $m[1] : '';

try {
    // L'inscription, en français : le compte part de la langue de référence.
    $page = $appel('inscription');
    $appel('inscription', ['_csrf' => $jeton($page), 'nom' => 'Controle Visuel', 'pseudo' => 'Controle_visuel',
        'email' => $email, 'mot_de_passe' => $mdp, 'mot_de_passe_confirmation' => $mdp]);
    $id = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$email]);
    if ($id <= 0) {
        fwrite(STDERR, "L'inscription a échoué.\n");
        exit(1);
    }

    $aujourdhui = date('Y-m-d');
    $demain = date('Y-m-d', strtotime('+1 day'));
    $matiere = (int) bd_valeur('SELECT id FROM matieres WHERE user_id = ? ORDER BY id LIMIT 1', [$id]);
    $examen = (int) bd_valeur('SELECT id FROM types_evenement WHERE user_id = ? ORDER BY id LIMIT 1 OFFSET 1', [$id]);

    // Des titres longs : c'est ce qui déborde en premier.
    foreach (['Algèbre linéaire : espaces vectoriels et applications linéaires', 'Histoire du droit', 'Biochimie — cycle de Krebs'] as $titre) {
        bd_run('INSERT INTO cours (user_id, matiere_id, titre, contenu) VALUES (?, ?, ?, ?)', [$id, $matiere, $titre, '<p>Notes de cours.</p>']);
    }
    bd_run('INSERT INTO evenements (user_id, titre, debut, fin, type_id, matiere_id, lieu) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$id, 'Examen de fin de semestre', $aujourdhui . ' 09:00:00', $aujourdhui . ' 11:00:00', $examen, $matiere, 'Amphi B']);
    bd_run('INSERT INTO evenements (user_id, titre, debut, fin, journee_entiere) VALUES (?, ?, ?, ?, 1)',
        [$id, 'Rendu du rapport de stage', $demain . ' 00:00:00', $demain . ' 23:59:59']);
    bd_run('INSERT INTO listes_taches (user_id, nom, couleur, icone, echeance, position) VALUES (?, ?, ?, ?, ?, 1)',
        [$id, 'Dossier de stage', '#4f46e5', '📋', $demain]);
    $liste = (int) bd_valeur('SELECT id FROM listes_taches WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$id]);
    foreach (['Relire le plan', 'Envoyer la convention', 'Préparer la soutenance'] as $rang => $tache) {
        bd_run('INSERT INTO taches (user_id, liste_id, titre, echeance, position) VALUES (?, ?, ?, ?, ?)',
            [$id, $liste, $tache, $demain, $rang + 1]);
    }

    // Le budget passe par le formulaire : ses catégories existent déjà.
    $budget = $appel('budget');
    foreach ([['Courses du mois', '84,30', 'depense', 'carte'], ['Loyer', '1 234,50', 'depense', 'virement'],
              ['Bourse', '450', 'recette', 'virement'], ['Abonnement transports', '39,90', 'depense', 'prelevement']] as [$libelle, $montant, $sens, $moyen]) {
        $appel('budget/operations', ['_csrf' => $jeton($budget), 'libelle' => $libelle, 'montant' => $montant,
            'date_operation' => $aujourdhui, 'sens' => $sens, 'moyen' => $moyen]);
    }

    $session = '';
    foreach (preg_split('/\R/', (string) file_get_contents($jar)) ?: [] as $ligne) {
        if (str_contains($ligne, 'MESCOURS_SESSID')) {
            $session = trim((string) preg_replace('/^.*MESCOURS_SESSID\s+/', '', $ligne));
        }
    }
    echo 'Compte ' . $email . ' prêt (identifiant ' . $id . ') : '
        . bd_valeur('SELECT COUNT(*) FROM cours WHERE user_id = ?', [$id]) . ' cours, '
        . bd_valeur('SELECT COUNT(*) FROM operations WHERE user_id = ?', [$id]) . " opérations.\n";
    echo $session === '' ? "Aucune session.\n" : "MESCOURS_SESSID=" . $session . "\n";
} finally {
    @unlink($jar);
}
