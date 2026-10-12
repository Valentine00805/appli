<?php
/**
 * Le lien public d'un travail de groupe, vu par quelqu'un qui n'a pas de compte (HTTP + base) : il faut dire son prénom pour entrer (sans cela,
 * ni la page ni les fichiers), ce qui est refusé comme prénom, le cookie qui le reconnaît ensuite, « Ce n'est pas vous ? », ce que voient les
 * membres (qui est entré par le lien, quand, « Nouveau »), retirer quelqu'un (un administrateur seulement), un lien fermé ou refait qui oublie
 * tout le monde, la limite de monde, puis ce que le lien montre : le calendrier commun du projet — évènements et tâches datées du mois, en
 * lecture seule —, la navigation de mois en mois, un projet sans calendrier, un lien mort, un prénom déjà pris (la même personne qui revient, ou
 * une autre qui se distingue ; plusieurs appareils pour une personne, au plus cinq), le membre sans compte que devient d'office quelqu'un qui entre
 * par le lien (créé, ou repris s'il y en avait déjà un du même nom ; retiré avec lui ou à part ; pas de membre si le projet est plein), et les quatre langues.
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
$comptes = ['a' => ['email' => 'tpub-a@exemple-test.fr', 'pseudo' => 'Tpub_un'], 'b' => ['email' => 'tpub-b@exemple-test.fr', 'pseudo' => 'Tpub_deux']];
foreach ($comptes as $c => $d) {
    bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$d['email'], '%@exemple-test.fr']);
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)', [$d['email'], $d['pseudo'], password_hash($mdp, PASSWORD_DEFAULT), 'Essai ' . $c]);
    $comptes[$c]['id'] = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$d['email']]);
}
$id = static fn (string $c): int => $comptes[$c]['id'];

/** Un navigateur = un fichier de cookies. Rend [code, corps, adresse finale]. */
$jar = static fn (string $nom): string => __DIR__ . '/ck_tpub_' . $nom . '.txt';
$appel = static function (string $navigateur, string $chemin, ?array $post = null, string $langue = 'fr') use ($jar): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $jar($navigateur), CURLOPT_COOKIEFILE => $jar($navigateur), CURLOPT_HTTPHEADER => ['Accept-Language: ' . $langue]]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    $r = [(int) curl_getinfo($h, CURLINFO_RESPONSE_CODE), $corps, (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';
/** Un visiteur dit son prénom : [code, corps]. */
$entrer = static function (string $navigateur, string $lien, string $prenom, string $langue = 'fr') use ($appel, $jeton): array {
    [, $entree] = $appel($navigateur, 'g/' . $lien, null, $langue);
    [$code, $corps] = $appel($navigateur, 'g/' . $lien . '/entrer', ['_csrf' => $jeton($entree), 'prenom' => $prenom], $langue);
    return [$code, $corps];
};

/** Quelqu'un répond à « est-ce vous ? » (« moi » ou « autre ») pour un prénom déjà pris : [code, corps]. */
$repondre = static function (string $navigateur, string $lien, string $prenom, string $reponse, string $langue = 'fr') use ($appel, $jeton): array {
    [, $entree] = $appel($navigateur, 'g/' . $lien, null, $langue);
    return $appel($navigateur, 'g/' . $lien . '/entrer', ['_csrf' => $jeton($entree), 'prenom' => $prenom, 'reponse' => $reponse], $langue);
};

$projets = [];
try {
    $mois = new DateTimeImmutable('first day of this month midnight');
    $suivant = $mois->modify('+1 month');
    $precedent = $mois->modify('-1 month');
    $jour = static fn (DateTimeImmutable $m, int $j): string => $m->format('Y-m-') . sprintf('%02d', $j);

    $jetonAvec = bin2hex(random_bytes(16));
    $jetonSans = bin2hex(random_bytes(16));
    foreach ([['Projet avec calendrier', $jetonAvec], ['Projet sans calendrier', $jetonSans]] as [$nom, $lien]) {
        bd_run('INSERT INTO projets (nom, cree_par, jeton) VALUES (?, ?, ?)', [$nom, $id('a'), $lien]);
        $projets[$nom] = (int) bd_valeur('SELECT id FROM projets WHERE cree_par = ? AND nom = ?', [$id('a'), $nom]);
        bd_run('INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, ?, ?), (?, ?, ?, ?)',
            [$projets[$nom], $id('a'), 'admin', 'membre', $projets[$nom], $id('b'), 'membre', 'membre']);
    }
    $p = $projets['Projet avec calendrier'];
    $q = $projets['Projet sans calendrier'];
    $membre = (int) bd_valeur('SELECT id FROM projet_membres WHERE projet_id = ? AND user_id = ?', [$p, $id('a')]);
    bd_run('INSERT INTO calendriers_amis (proprietaire_id, projet_id, nom, couleur) VALUES (?, ?, ?, ?)', [$id('a'), $p, 'Calendrier du groupe', '#e11d48']);
    $cal = (int) bd_valeur('SELECT id FROM calendriers_amis WHERE projet_id = ?', [$p]);
    bd_run('INSERT INTO calendrier_amis_evenements (calendrier_id, auteur_id, titre, description, lieu, debut, fin, journee_entiere) VALUES (?, ?, ?, ?, ?, ?, ?, 0), (?, ?, ?, ?, ?, ?, ?, 1), (?, ?, ?, ?, ?, ?, ?, 0)',
        [$cal, $id('a'), 'Réunion de groupe', 'NOTE-SECRETE-DE-L-EVENEMENT', 'Salle B12', $jour($mois, 15) . ' 14:00:00', $jour($mois, 15) . ' 15:30:00',
         $cal, $id('a'), 'Rendu du rapport', null, null, $jour($mois, 20) . ' 00:00:00', $jour($mois, 20) . ' 23:59:59',
         $cal, $id('a'), 'Soutenance du mois prochain', null, null, $jour($suivant, 5) . ' 09:00:00', $jour($suivant, 5) . ' 10:00:00']);
    bd_run('INSERT INTO projet_taches (projet_id, titre, membre_id, echeance, statut) VALUES (?, ?, ?, ?, ?), (?, ?, ?, ?, ?), (?, ?, NULL, ?, ?)',
        [$p, 'Rédiger la conclusion', $membre, $jour($mois, 18), 'a_faire', $p, 'Relire le plan', $membre, $jour($mois, 10), 'fait', $p, 'Tâche sans personne', $jour($mois, 22), 'a_faire']);
    bd_run('INSERT INTO projet_fichiers (projet_id, user_id, nom_origine, nom_stocke, mime, taille) VALUES (?, ?, ?, ?, ?, ?)', [$p, $id('a'), 'plan-secret.pdf', 'essai-tpub-1.pdf', 'application/pdf', 10]);
    $fichier = (int) bd_valeur('SELECT id FROM projet_fichiers WHERE projet_id = ?', [$p]);
    $nbVisiteurs = static fn (int $projet): int => (int) bd_valeur('SELECT COUNT(*) FROM projet_visiteurs WHERE projet_id = ?', [$projet]);

    echo "\n1. Sans prénom, on n'entre pas\n";
    [$cod, $entree] = $appel('anonyme', 'g/' . $jetonAvec);
    $dire('la page du lien ne montre que le nom du projet et le champ « Votre prénom »', $cod . ' · ' . $oui(str_contains($entree, 'Projet avec calendrier')
        && str_contains($entree, 'name="prenom"') && str_contains($entree, 'Votre prénom') && str_contains($entree, 'Accéder au projet') && str_contains($entree, 'autocomplete="given-name"')), '200 · oui');
    $dire('rien du projet n\'y est (ni tâches, ni calendrier, ni fichiers, ni membres)',
        $oui(!str_contains($entree, 'Qui fait quoi') && !str_contains($entree, 'calendrier-public') && !str_contains($entree, 'Réunion de groupe')
            && !str_contains($entree, 'plan-secret.pdf') && !str_contains($entree, 'Tpub_un') && !str_contains($entree, 'Rédiger la conclusion')), 'oui');
    [$cod, $fichierSans] = $appel('anonyme', 'g/' . $jetonAvec . '/fichiers/' . $fichier);
    $dire('un fichier du projet ne se sert pas non plus sans prénom', $cod . ' · ' . $oui(!str_contains($fichierSans, '%PDF')), '404 · oui');
    $dire('et personne n\'est encore entré', (string) $nbVisiteurs($p), '0');

    echo "\n2. Un prénom qui n'en est pas un est refusé\n";
    foreach (['' => 'vide', '   ' => 'des espaces', 'Léa123' => 'avec des chiffres', '<script>alert(1)</script>' => 'avec du HTML', 'http://piege.example' => 'une adresse',
        str_repeat('a', 41) => 'trop long (41 lettres)', '- Léa' => 'qui commence par un tiret'] as $prenomInvalide => $quoi) {
        [$cod, $refus] = $entrer('anonyme', $jetonAvec, (string) $prenomInvalide);
        $dire("« $quoi » : refusé, et on le dit", $cod . ' · ' . $oui(str_contains($refus, 'Écrivez un prénom') && str_contains($refus, 'name="prenom"')) . ' · ' . $nbVisiteurs($p), '422 · oui · 0');
    }
    [, $apresRefus] = $entrer('anonyme', $jetonAvec, 'Léa42');
    $dire('ce qui a été tapé est remis dans le champ', $oui(str_contains($apresRefus, 'value="Léa42"')), 'oui');
    $appel('anonyme', 'g/' . $jetonAvec . '/entrer', ['prenom' => 'Léa']);
    $dire('sans le bon jeton CSRF : rien', (string) $nbVisiteurs($p), '0');

    echo "\n3. Dire son prénom : on entre\n";
    [$cod, $page] = $entrer('lea', $jetonAvec, '  Léa   ');
    $dire('« Léa » (espaces en trop effacés) : on voit le projet, avec « Bonjour Léa »',
        $cod . ' · ' . $oui(str_contains($page, 'Qui fait quoi') && str_contains($page, 'Bonjour Léa') && str_contains($page, 'Ce n’est pas vous ?') && !str_contains($page, 'name="prenom"')), '200 · oui');
    $dire('son prénom est gardé, avec sa première visite', (string) bd_valeur('SELECT CONCAT(prenom, \'|\', visites) FROM projet_visiteurs WHERE projet_id = ?', [$p]), 'Léa|1');
    $dire('le jeton n\'est pas gardé tel quel : seulement son empreinte (64 caractères), sur un appareil de sa personne',
        (string) bd_valeur('SELECT CHAR_LENGTH(a.empreinte) FROM projet_visiteur_appareils a JOIN projet_visiteurs v ON v.id = a.visiteur_id WHERE v.projet_id = ?', [$p]), '64');
    [$cod, $encore] = $appel('lea', 'g/' . $jetonAvec);
    $dire('au retour (même navigateur), il n\'a plus à le redire', $cod . ' · ' . $oui(str_contains($encore, 'Bonjour Léa') && str_contains($encore, 'Qui fait quoi')), '200 · oui');
    [, $autreNavigateur] = $appel('autre', 'g/' . $jetonAvec);
    $dire('mais un autre navigateur, lui, le redemande', $oui(str_contains($autreNavigateur, 'name="prenom"') && !str_contains($autreNavigateur, 'Qui fait quoi')), 'oui');
    [, $accents] = $entrer('jp', $jetonAvec, 'Jean-Pierre');
    [, $apostrophe] = $entrer('dart', $jetonAvec, 'D’Artagnan');
    [, $droite] = $entrer('dart2', $jetonAvec, "D'Artagnan");
    $dire('un prénom composé (« Jean-Pierre »), avec apostrophe (« D’Artagnan »), est accepté',
        $oui(str_contains($accents, 'Bonjour Jean-Pierre') && str_contains($apostrophe, 'Qui fait quoi') && str_contains($droite, 'Qui fait quoi')), 'oui');
    $dire('quatre personnes sont entrées', (string) $nbVisiteurs($p), '4');
    [$cod, $sans2] = $appel('lea', 'g/' . $jetonSans);
    $dire('chaque projet a sa propre entrée : le prénom donné pour l\'un n\'ouvre pas l\'autre lien', $oui(str_contains($sans2, 'name="prenom"') && !str_contains($sans2, 'Qui fait quoi')), 'oui');

    echo "\n3b. Un membre du projet n'a pas à se présenter\n";
    [, $connexionA] = $appel('membre', 'connexion');
    $appel('membre', 'connexion', ['_csrf' => $jeton($connexionA), 'identifiant' => $comptes['a']['email'], 'mot_de_passe' => $mdp]);
    [$cod, $pageMembre] = $appel('membre', 'g/' . $jetonAvec);
    $dire('connecté, un membre ouvre le lien de son projet sans prénom à donner, et sans « Bonjour »',
        $cod . ' · ' . $oui(str_contains($pageMembre, 'Qui fait quoi') && !str_contains($pageMembre, 'name="prenom"') && !str_contains($pageMembre, 'Ce n’est pas vous ?')) . ' · ' . $nbVisiteurs($p), '200 · oui · 4');
    echo "\n3c. Un compte ? Se connecter, ou en créer un\n";
    $inscriptionOuverte = str_contains((string) file_get_contents(dirname(__DIR__, 2) . '/config/parametres.php'), "'inscription_ouverte' => true");
    $dire('la page d\'entrée demande « Vous avez déjà un compte ? » avec un bouton « Se connecter » qui passe par le lien',
        $oui(str_contains($entree, 'Vous avez déjà un compte ?') && preg_match('#<a class="bouton bouton--secondaire bouton--petit" href="[^"]*/g/' . $jetonAvec . '/se-connecter">Se connecter</a>#', $entree) === 1), 'oui');
    $dire('et, si les inscriptions sont ouvertes, « Pas encore de compte ? » avec « Créer un compte » vers l\'inscription',
        $oui($inscriptionOuverte ? preg_match('#Pas encore de compte \?</span>\s*<a class="bouton bouton--petit entree-lien__creer" href="[^"]*/inscription">\s*<svg[^>]*>.*?</svg>\s*Créer un compte\s*</a>#s', $entree) === 1
            : !str_contains($entree, 'Créer un compte')), 'oui');
    $dire('la page « une autre personne du même prénom » les propose aussi', $oui(str_contains($apresRefus, 'Vous avez déjà un compte ?')), 'oui');
    [$cod, $versConnexion, $adresse] = $appel('retour', 'g/' . $jetonAvec . '/se-connecter');
    $dire('« Se connecter » mène à la page de connexion', $cod . ' · ' . $oui(str_ends_with($adresse, '/connexion') && str_contains($versConnexion, 'name="mot_de_passe"')), '200 · oui');
    [$cod, $apresConnexion, $adresse] = $appel('retour', 'connexion', ['_csrf' => $jeton($versConnexion), 'identifiant' => $comptes['a']['email'], 'mot_de_passe' => $mdp]);
    $dire('une fois connecté (membre du projet), on revient au lien, et on y entre sans prénom',
        $cod . ' · ' . $oui(str_ends_with($adresse, '/g/' . $jetonAvec) && str_contains($apresConnexion, 'Qui fait quoi') && !str_contains($apresConnexion, 'name="prenom"')), '200 · oui');
    [, $comptePage] = $appel('retour', 'compte');
    $appel('retour', 'deconnexion', ['_csrf' => $jeton($comptePage)]);
    [, $connexionBis] = $appel('retour', 'connexion');
    [, , $adresseBis] = $appel('retour', 'connexion', ['_csrf' => $jeton($connexionBis), 'identifiant' => $comptes['a']['email'], 'mot_de_passe' => $mdp]);
    $dire('et la destination ne sert qu\'une fois : une connexion d\'un autre jour (après déconnexion) ne ramène pas au lien',
        $oui(str_contains($connexionBis, 'name="mot_de_passe"') && !str_ends_with($adresseBis, '/g/' . $jetonAvec)), 'oui');
    [$cod, , $adresse] = $appel('anonyme', 'g/' . bin2hex(random_bytes(16)) . '/se-connecter');
    $dire('un lien inconnu mène à la connexion tout simplement (rien n\'est retenu)', $cod . ' · ' . $oui(str_ends_with($adresse, '/connexion')), '200 · oui');
    echo "\n3d. Quelqu'un qui entre par le lien devient membre sans compte\n";
    $membresSansCompte = static fn (int $projet): array => array_column(bd_all(
        "SELECT nom FROM projet_membres WHERE projet_id = ? AND user_id IS NULL AND role = 'membre' AND statut = 'membre' ORDER BY nom", [$projet]), 'nom');
    $dire('Léa, Jean-Pierre et les deux D’Artagnan sont membres sans compte, sans doublon', implode(' · ', $membresSansCompte($p)), 'D\'Artagnan · D’Artagnan · Jean-Pierre · Léa');
    $dire('chacune est reliée à son membre, créé par le lien',
        (string) bd_valeur('SELECT COUNT(*) FROM projet_visiteurs v JOIN projet_membres m ON m.id = v.membre_id WHERE v.projet_id = ? AND v.membre_cree = 1', [$p]), '4');
    // (L'administrateur ne s'est pas encore connecté dans cet essai : on le fait ici ; la connexion de plus bas n'y change rien.)
    [, $connexionAdmin] = $appel('a', 'connexion');
    $appel('a', 'connexion', ['_csrf' => $jeton($connexionAdmin), 'identifiant' => $comptes['a']['email'], 'mot_de_passe' => $mdp]);
    [$cod, $pageMembres] = $appel('a', 'travaux/' . $p . '/membres');
    $dire('la page des membres les montre « Sans compte » et « 👋 Par le lien »', $cod . ' · ' . $oui(substr_count($pageMembres, '👋 Par le lien</span>') === 4 && substr_count($pageMembres, 'pastille--muette">Sans compte</span>') === 4), '200 · oui');
    echo "\n4. « Ce n'est pas vous ? »\n";
    [, $page] = $appel('lea', 'g/' . $jetonAvec);
    $appel('lea', 'g/' . $jetonAvec . '/sortir', ['_csrf' => $jeton($page)]);
    [, $apresSortie] = $appel('lea', 'g/' . $jetonAvec);
    $dire('le navigateur oublie le prénom : le lien le redemande, et la ligne reste pour les membres', $oui(str_contains($apresSortie, 'name="prenom"')) . ' · ' . $nbVisiteurs($p), 'oui · 4');

    echo "\n5. Ce que voient les membres\n";
    foreach (['a', 'b'] as $c) {
        [, $connexion] = $appel($c, 'connexion');
        $appel($c, 'connexion', ['_csrf' => $jeton($connexion), 'identifiant' => $comptes[$c]['email'], 'mot_de_passe' => $mdp]);
    }
    [, $membresA] = $appel('a', 'travaux/' . $p . '/membres');
    $dire('la carte du lien public liste ceux qui sont entrés : « Entrés par le lien (4) », leurs prénoms et « Arrivé le … »',
        $oui(str_contains($membresA, 'Entrés par le lien') && str_contains($membresA, '(4)') && str_contains($membresA, 'Léa') && str_contains($membresA, 'Jean-Pierre')
            && substr_count($membresA, 'Arrivé le') === 4), 'oui');
    $dire('les arrivées des dernières 24 h portent « Nouveau »', $oui(substr_count($membresA, '>Nouveau<') === 4), 'oui');
    $dire('un administrateur peut en retirer un (✕)', $oui(substr_count($membresA, '/visiteurs/') === 4 && str_contains($membresA, 'Retirer Léa de la liste')), 'oui');
    [, $membresB] = $appel('b', 'travaux/' . $p . '/membres');
    $dire('un membre qui n\'est pas administrateur voit la liste, sans le bouton de retrait', $oui(str_contains($membresB, 'Léa') && !str_contains($membresB, '/visiteurs/')), 'oui');
    bd_run('UPDATE projet_visiteurs SET created_at = ?, derniere_visite = ?, visites = 3 WHERE prenom = ? AND projet_id = ?',
        [date('Y-m-d H:i:s', strtotime('-5 days')), date('Y-m-d H:i:s', strtotime('-1 day')), 'Léa', $p]);
    [, $membresVieux] = $appel('a', 'travaux/' . $p . '/membres');
    $dire('une arrivée plus ancienne n\'est plus « Nouveau », et dit sa dernière visite', $oui(substr_count($membresVieux, '>Nouveau<') === 3 && str_contains($membresVieux, 'dernière visite le')), 'oui');

    echo "\n6. Retirer quelqu'un, fermer le lien\n";
    $idJp = (int) bd_valeur('SELECT id FROM projet_visiteurs WHERE prenom = ? AND projet_id = ?', ['Jean-Pierre', $p]);
    $appel('b', 'travaux/' . $p . '/visiteurs/' . $idJp . '/retirer', ['_csrf' => $jeton($membresB)]);
    $dire('un membre simple ne retire personne', (string) $nbVisiteurs($p), '4');
    $appel('a', 'travaux/' . $p . '/visiteurs/' . $idJp . '/retirer', ['_csrf' => $jeton($membresA)]);
    $dire('un administrateur retire Jean-Pierre', (string) $nbVisiteurs($p), '3');
    [, $jpApres] = $appel('jp', 'g/' . $jetonAvec);
    $dire('et son navigateur doit redire son prénom', $oui(str_contains($jpApres, 'name="prenom"') && !str_contains($jpApres, 'Qui fait quoi')), 'oui');
    $idDArt = (int) bd_valeur('SELECT id FROM projet_visiteurs WHERE prenom = ? AND projet_id = ? ORDER BY id LIMIT 1', ['D’Artagnan', $p]);
    $appel('a', 'travaux/' . $q . '/visiteurs/' . $idDArt . '/retirer', ['_csrf' => $jeton($membresA)]);
    $dire('retirer en passant par un autre projet ne retire rien', (string) $nbVisiteurs($p), '3');
    [, $dArtApres] = $appel('dart', 'g/' . $jetonAvec);
    $dire('D’Artagnan est toujours reconnu', $oui(str_contains($dArtApres, 'Qui fait quoi')), 'oui');
    $appel('a', 'travaux/' . $p . '/lien/fermer', ['_csrf' => $jeton($membresA)]);
    $dire('fermer le lien efface la liste, et son adresse ne mène plus à rien', $nbVisiteurs($p) . ' · ' . $appel('dart', 'g/' . $jetonAvec)[0], '0 · 404');
    $appel('a', 'travaux/' . $p . '/lien', ['_csrf' => $jeton($membresA)]);
    $nouveau = (string) bd_valeur('SELECT jeton FROM projets WHERE id = ?', [$p]);
    [, $refaitDart] = $appel('dart', 'g/' . $nouveau);
    $dire('un nouveau lien : ceux d\'avant (leur cookie) doivent redire leur prénom', $oui($nouveau !== '' && $nouveau !== $jetonAvec && str_contains($refaitDart, 'name="prenom"')), 'oui');
    bd_run('UPDATE projets SET jeton = ? WHERE id = ?', [$jetonAvec, $p]);

    echo "\n7. La limite de monde\n";
    for ($i = 0; $i < 300; $i++) {
        bd_run('INSERT INTO projet_visiteurs (projet_id, prenom, created_at, derniere_visite) VALUES (?, ?, NOW(), NOW())', [$q, 'Foule' . $i]);
    }
    [$cod, $plein] = $entrer('trop', $jetonSans, 'Retardataire');
    $dire('au-delà de 300 personnes : refusé, avec la raison', $cod . ' · ' . $oui(str_contains($plein, 'Trop de personnes sont déjà entrées')) . ' · ' . $nbVisiteurs($q), '422 · oui · 300');
    bd_run('DELETE FROM projet_visiteurs WHERE projet_id = ?', [$q]);

    echo "\n8. Ce que le lien montre : le calendrier commun du projet\n";
    $entrer('visiteur', $jetonAvec, 'Camille');
    [$cod, $page] = $appel('visiteur', 'g/' . $jetonAvec);
    $dire('la carte « Calendrier » est là, avec le mois courant', $cod . ' · ' . $oui(str_contains($page, 'id="calendrier-public"') && str_contains($page, '📅 Calendrier')
        && str_contains($page, ucfirst(nom_mois_en_phrase_test((int) $mois->format('n'))) . ' ' . $mois->format('Y'))), '200 · oui');
    $dire('l\'évènement : titre, heures, lieu',
        $oui(str_contains($page, 'Réunion de groupe') && str_contains($page, '14h00 – 15h30') && str_contains($page, '📍 Salle B12')), 'oui');
    $dire('une journée entière : « Toute la journée »', $oui(preg_match('#Rendu du rapport</strong>\s*<span class="discret">\s*Toute la journée#', $page) === 1), 'oui');
    $dire('les tâches datées du projet y sont aussi (✅), avec qui s\'en occupe ; celle de personne sans nom',
        $oui(str_contains($page, '✅ Rédiger la conclusion') && str_contains($page, 'Tpub_un') && str_contains($page, '✅ Tâche sans personne')), 'oui');
    $dire('une tâche faite est barrée', $oui(preg_match('#calendrier-public__evenement--fait[^>]*>\s*<strong>✅ Relire le plan#', $page) === 1), 'oui');
    $dire('les jours se suivent dans l\'ordre (10, 15, 18, 20, 22)',
        $oui(preg_match_all('#calendrier-public__numero">(\d+)<#', $page, $jours) === 5 && $jours[1] === ['10', '15', '18', '20', '22']), 'oui');
    $dire('la couleur du calendrier colore les évènements', $oui(str_contains($page, '--couleur-evt:#e11d48')), 'oui');
    $dire('ce qui n\'a pas à y être n\'y est pas : les notes de l\'évènement', $oui(!str_contains($page, 'NOTE-SECRETE-DE-L-EVENEMENT')), 'oui');
    $dire('rien à modifier : pas de formulaire dans la carte', $oui(preg_match('#id="calendrier-public".*?</section>#s', $page, $carte) === 1 && !str_contains($carte[0], '<form') && !str_contains($carte[0], 'data-fenetre')), 'oui');
    $dire('les liens « mois précédent » et « mois suivant »',
        $oui(str_contains($page, 'mois=' . $precedent->format('Y-m') . '#calendrier-public') && str_contains($page, 'mois=' . $suivant->format('Y-m') . '#calendrier-public')), 'oui');
    [, $prochain] = $appel('visiteur', 'g/' . $jetonAvec . '?mois=' . $suivant->format('Y-m'));
    // (Dans la carte seulement : « Qui fait quoi » liste toujours toutes les tâches du projet, quel que soit le mois.)
    $dire('le mois suivant : sa soutenance, et plus les évènements ni les tâches de ce mois-ci dans le calendrier',
        $oui(preg_match('#id="calendrier-public".*?</section>#s', $prochain, $carteSuivante) === 1 && str_contains($carteSuivante[0], 'Soutenance du mois prochain')
            && !str_contains($carteSuivante[0], 'Réunion de groupe') && !str_contains($carteSuivante[0], 'Rédiger la conclusion')), 'oui');
    [, $vide] = $appel('visiteur', 'g/' . $jetonAvec . '?mois=' . $mois->modify('-5 months')->format('Y-m'));
    $dire('un mois sans rien : « Rien de prévu ce mois-ci. »', $oui(str_contains($vide, 'Rien de prévu ce mois-ci.') && !str_contains($vide, 'calendrier-public__jours')), 'oui');
    [, $illisible] = $appel('visiteur', 'g/' . $jetonAvec . '?mois=abc');
    [, $horsBornes] = $appel('visiteur', 'g/' . $jetonAvec . '?mois=1999-01');
    $dire('un mois illisible ou hors des bornes : le mois courant', $oui(str_contains($illisible, 'Réunion de groupe') && str_contains($horsBornes, 'Réunion de groupe')), 'oui');
    [, $injection] = $appel('visiteur', 'g/' . $jetonAvec . '?mois=2026-10%27%20OR%201=1');
    $dire('et une valeur piégée n\'est jamais prise telle quelle', $oui(str_contains($injection, 'Réunion de groupe') && !str_contains($injection, 'OR 1=1')), 'oui');
    $entrer('sansCal', $jetonSans, 'Camille');
    [$cod, $sans] = $appel('sansCal', 'g/' . $jetonSans);
    $dire('un projet sans calendrier commun : pas de carte « Calendrier »', $cod . ' · ' . $oui(!str_contains($sans, 'calendrier-public') && str_contains($sans, 'Qui fait quoi')), '200 · oui');
    [$cod] = $appel('anonyme', 'g/' . bin2hex(random_bytes(16)));
    $dire('un lien inconnu : 404', (string) $cod, '404');

    echo "\n8b. Un prénom déjà pris : la même personne, ou une autre\n";
    $nbPrenom = static fn (int $projet, string $prenom): int => (int) bd_valeur('SELECT COUNT(*) FROM projet_visiteurs WHERE projet_id = ? AND prenom = ?', [$projet, $prenom]);
    $nbAppareils = static fn (int $projet, string $prenom): int => (int) bd_valeur(
        'SELECT COUNT(*) FROM projet_visiteur_appareils a JOIN projet_visiteurs v ON v.id = a.visiteur_id WHERE v.projet_id = ? AND v.prenom = ?', [$projet, $prenom]);
    [, $premiere] = $entrer('mael1', $jetonAvec, 'Maël');
    $dire('Maël entre une première fois : une ligne, un appareil', $oui(str_contains($premiere, 'Bonjour Maël')) . ' · ' . $nbPrenom($p, 'Maël') . ' · ' . $nbAppareils($p, 'Maël'), 'oui · 1 · 1');
    [$cod, $question] = $entrer('mael2', $jetonAvec, 'Maël');
    $dire('un autre navigateur redit « Maël » : pas de nouvelle ligne, mais la question « Est-ce vous ? »',
        $cod . ' · ' . $oui(str_contains($question, 'est déjà entré par ce lien') && str_contains($question, 'Oui, c’est moi') && str_contains($question, 'Non, une autre personne')
            && !str_contains($question, 'Qui fait quoi') && str_contains($question, 'value="Maël"')) . ' · ' . $nbPrenom($p, 'Maël'), '200 · oui · 1');
    [, $majuscules] = $entrer('mael2', $jetonAvec, 'MAEL');
    [, $sansAccent] = $entrer('mael2', $jetonAvec, 'maël');
    $dire('les majuscules et les accents ne font pas un autre prénom (« MAEL », « maël » : la même question)',
        $oui(str_contains($majuscules, 'Oui, c’est moi') && str_contains($sansAccent, 'Oui, c’est moi')) . ' · ' . $nbPrenom($p, 'Maël'), 'oui · 1');
    [$cod, $repris] = $repondre('mael2', $jetonAvec, 'Maël', 'moi');
    $dire('« Oui, c\'est moi » : il retrouve sa place (« Bonjour Maël »), sans nouvelle ligne mais avec un second appareil',
        $cod . ' · ' . $oui(str_contains($repris, 'Bonjour Maël') && str_contains($repris, 'Qui fait quoi')) . ' · ' . $nbPrenom($p, 'Maël') . ' · ' . $nbAppareils($p, 'Maël'), '200 · oui · 1 · 2');
    [, $toujours] = $appel('mael1', 'g/' . $jetonAvec);
    [, $aussi] = $appel('mael2', 'g/' . $jetonAvec);
    $dire('les deux appareils sont reconnus (une même personne peut se servir de deux navigateurs)', $oui(str_contains($toujours, 'Bonjour Maël') && str_contains($aussi, 'Bonjour Maël')), 'oui');
    $dire('et c\'est la liste des membres qui n\'a qu\'un Maël', (string) $nbPrenom($p, 'Maël'), '1');
    $dire('« Oui, c\'est moi » n\'a pas créé de second membre : Maël n\'est membre sans compte qu\'une fois', (string) (int) bd_valeur('SELECT COUNT(*) FROM projet_membres WHERE projet_id = ? AND nom = ?', [$p, 'Maël']), '1');
    [, $unAutre] = $entrer('mael3', $jetonAvec, 'Maël');
    [$cod, $distinguer] = $repondre('mael3', $jetonAvec, 'Maël', 'autre');
    $dire('« Non, une autre personne » : on lui demande de se distinguer, le champ déjà rempli « Maël » + une espace',
        $cod . ' · ' . $oui(str_contains($unAutre, 'Oui, c’est moi') && str_contains($distinguer, 'Ajoutez l’initiale de votre nom') && str_contains($distinguer, 'value="Maël "')
            && !str_contains($distinguer, 'Qui fait quoi')) . ' · ' . $nbPrenom($p, 'Maël'), '200 · oui · 1');
    [$cod, $distincte] = $entrer('mael3', $jetonAvec, 'Maël P.');
    $dire('avec « Maël P. » : c\'est une autre personne, une seconde ligne', $cod . ' · ' . $oui(str_contains($distincte, 'Bonjour Maël P.')) . ' · ' . $nbPrenom($p, 'Maël P.') . ' · ' . $nbPrenom($p, 'Maël'), '200 · oui · 1 · 1');
    [, $meme] = $entrer('mael4', $jetonAvec, 'Maël P.');
    $dire('et « Maël P. » redit se voit poser la même question', $oui(str_contains($meme, 'Oui, c’est moi') && str_contains($meme, 'Maël P.')), 'oui');
    [$cod, $nouveauMoi] = $repondre('nouvelle', $jetonAvec, 'Inès', 'moi');
    $dire('répondre « moi » pour un prénom que personne n\'a pris n\'invente rien : on entre comme un nouveau', $cod . ' · ' . $oui(str_contains($nouveauMoi, 'Bonjour Inès')) . ' · ' . $nbPrenom($p, 'Inès') . ' · ' . $nbAppareils($p, 'Inès'), '200 · oui · 1 · 1');
    [, $autreProjet] = $entrer('mael-autre', $jetonSans, 'Maël');
    $dire('le même prénom dans un autre projet est une autre personne : aucune question', $oui(str_contains($autreProjet, 'Bonjour Maël') && !str_contains($autreProjet, 'Oui, c’est moi')), 'oui');
    foreach (['a1', 'a2', 'a3', 'a4', 'a5'] as $appareil) { $repondre($appareil, $jetonAvec, 'Maël', 'moi'); }
    [, $premierAppareil] = $appel('mael1', 'g/' . $jetonAvec);
    $dire('au plus cinq appareils par personne : les plus anciens sont oubliés (le premier doit redire son prénom)',
        $nbAppareils($p, 'Maël') . ' · ' . $oui(str_contains($premierAppareil, 'name="prenom"') || str_contains($premierAppareil, 'Oui, c’est moi')), '5 · oui');
    [, $dernierAppareil] = $appel('a5', 'g/' . $jetonAvec);
    $dire('le plus récent, lui, est reconnu', $oui(str_contains($dernierAppareil, 'Bonjour Maël')), 'oui');
    [, $membresMael] = $appel('a', 'travaux/' . $p . '/membres');
    $dire('côté membres : un seul « Maël » dans la liste (et « Maël P. » à part)', $oui(substr_count($membresMael, '<span class="visiteur-ligne__prenom">Maël') === 2 && str_contains($membresMael, 'Maël P.')), 'oui');
    $idMael = (int) bd_valeur('SELECT id FROM projet_visiteurs WHERE projet_id = ? AND prenom = ?', [$p, 'Maël']);
    $appel('a', 'travaux/' . $p . '/visiteurs/' . $idMael . '/retirer', ['_csrf' => $jeton($membresMael)]);
    $dire('retirer Maël efface aussi tous ses appareils', $nbPrenom($p, 'Maël') . ' · ' . $nbAppareils($p, 'Maël'), '0 · 0');
    $dire('Maël P. et Inès, eux, restent', (string) ($nbPrenom($p, 'Maël P.') + $nbPrenom($p, 'Inès')), '2');
    echo "\n8c. Le membre sans compte : repris, retiré, plein\n";
    $membre = static fn (int $projet, string $nom): ?array => bd_all('SELECT id, user_id FROM projet_membres WHERE projet_id = ? AND nom = ? ORDER BY id', [$projet, $nom])[0] ?? null;
    $nbMembresNommes = static fn (int $projet, string $nom): int => (int) bd_valeur('SELECT COUNT(*) FROM projet_membres WHERE projet_id = ? AND nom = ?', [$projet, $nom]);
    $liaison = static fn (int $projet, string $prenom): string => (string) (bd_all('SELECT CONCAT(COALESCE(membre_id, \'-\'), \'|\', membre_cree) AS l FROM projet_visiteurs WHERE projet_id = ? AND prenom = ?', [$projet, $prenom])[0]['l'] ?? 'absent');
    $dire('« Maël P. » et « Inès » (entrée par « Oui, c\'est moi » pour un prénom libre) sont chacun membre sans compte, une seule fois', $nbMembresNommes($p, 'Maël P.') . ' · ' . $nbMembresNommes($p, 'Inès'), '1 · 1');
    bd_run("INSERT INTO projet_membres (projet_id, user_id, nom, role, statut, invite_par) VALUES (?, NULL, 'Zora', 'membre', 'membre', ?), (?, NULL, 'Noé', 'membre', 'membre', ?)", [$p, $id('a'), $p, $id('a')]);
    $zora = (int) $membre($p, 'Zora')['id'];
    $noe = (int) $membre($p, 'Noé')['id'];
    [, $zoraEntre] = $entrer('zora', $jetonAvec, 'Zora');
    $dire('un nom sans compte déjà là (ajouté par un administrateur) est repris : pas de doublon, relié sans être « créé par le lien »',
        $oui(str_contains($zoraEntre, 'Bonjour Zora')) . ' · ' . $nbMembresNommes($p, 'Zora') . ' · ' . $liaison($p, 'Zora'), 'oui · 1 · ' . $zora . '|0');
    $entrer('noe', $jetonAvec, 'Noé');
    $dire('de même pour Noé', $nbMembresNommes($p, 'Noé') . ' · ' . $liaison($p, 'Noé'), '1 · ' . $noe . '|0');
    [, $pageA] = $appel('a', 'travaux/' . $p . '/membres');
    $idInes = (int) bd_valeur('SELECT membre_id FROM projet_visiteurs WHERE projet_id = ? AND prenom = ?', [$p, 'Inès']);
    $appel('a', 'travaux/membres/' . $zora . '/retirer', ['_csrf' => $jeton($pageA)]);
    $dire('retirer Zora des membres : le membre part, mais la personne reste dans la liste du lien, déliée',
        $oui($membre($p, 'Zora') === null) . ' · ' . $liaison($p, 'Zora'), 'oui · -|0');
    [, $zoraRevient] = $appel('zora', 'g/' . $jetonAvec);
    $dire('et son accès est intact (son navigateur est reconnu)', $oui(str_contains($zoraRevient, 'Bonjour Zora') && str_contains($zoraRevient, 'Qui fait quoi')), 'oui');
    $repondre('zora2', $jetonAvec, 'Zora', 'moi');
    $dire('un autre appareil de Zora (« Oui, c\'est moi ») ne la remet pas dans les membres : on l\'a retirée', (string) $nbMembresNommes($p, 'Zora'), '0');
    $appel('a', 'travaux/' . $p . '/visiteurs/' . (int) bd_valeur('SELECT id FROM projet_visiteurs WHERE projet_id = ? AND prenom = ?', [$p, 'Noé']) . '/retirer', ['_csrf' => $jeton($pageA)]);
    $dire('retirer Noé de la liste du lien : la personne part, son nom sans compte (ajouté à la main) reste', $liaison($p, 'Noé') . ' · ' . $nbMembresNommes($p, 'Noé'), 'absent · 1');
    $appel('a', 'travaux/' . $p . '/visiteurs/' . (int) bd_valeur('SELECT id FROM projet_visiteurs WHERE projet_id = ? AND prenom = ?', [$p, 'Inès']) . '/retirer', ['_csrf' => $jeton($pageA)]);
    $dire('retirer Inès de la liste du lien : son membre, créé par le lien, part avec elle', (string) $nbMembresNommes($p, 'Inès') . ' · ' . (int) bd_valeur('SELECT COUNT(*) FROM projet_membres WHERE id = ?', [$idInes]), '0 · 0');
    $dire('et ses tâches ne sont pas perdues avec elle (un membre retiré laisse les siennes, sans personne)',
        (string) bd_valeur('SELECT COUNT(*) FROM projet_taches WHERE projet_id = ?', [$p]), '3');
    bd_run("DELETE FROM projet_membres WHERE projet_id = ? AND user_id IS NULL AND nom IN ('Noé')", [$p]);
    // Le projet est rempli jusqu'à 30 membres (ce que les essais d'avant y ont déjà mis compte).
    for ($i = (int) bd_valeur('SELECT COUNT(*) FROM projet_membres WHERE projet_id = ?', [$q]); $i < 30; $i++) {
        bd_run("INSERT INTO projet_membres (projet_id, user_id, nom, role, statut) VALUES (?, NULL, ?, 'membre', 'membre')", [$q, 'Plein' . $i]);
    }
    [, $pleinEntre] = $entrer('plein', $jetonSans, 'Dernier');
    $dire('projet plein (30 membres) : la personne entre quand même, sans devenir membre',
        $oui(str_contains($pleinEntre, 'Bonjour Dernier')) . ' · ' . $nbMembresNommes($q, 'Dernier') . ' · ' . (int) bd_valeur('SELECT COUNT(*) FROM projet_membres WHERE projet_id = ?', [$q]) . ' · ' . $liaison($q, 'Dernier'), 'oui · 0 · 30 · -|0');
    bd_run("DELETE FROM projet_membres WHERE projet_id = ? AND user_id IS NULL", [$q]);
    echo "\n9. Les quatre langues\n";
    foreach (['en' => ['Zoé', 'Your first name', 'Open the project', 'Hello Zoé', 'Calendar', 'All day', 'Next month', 'has already joined through this link', 'Yes, it’s me', 'No, someone else'],
        'es' => ['Zélie', 'Tu nombre', 'Abrir el proyecto', 'Hola Zélie', 'Calendario', 'Todo el día', 'Mes siguiente', 'ya ha entrado por este enlace', 'Sí, soy yo', 'No, otra persona'],
        'de' => ['Zita', 'Dein Vorname', 'Projekt öffnen', 'Hallo Zita', 'Kalender', 'Ganztägig', 'Nächster Monat', 'ist schon über diesen Link gekommen', 'Ja, das bin ich', 'Nein, jemand anderes'],
        'fr' => ['Zoran', 'Votre prénom', 'Accéder au projet', 'Bonjour Zoran', 'Calendrier', 'Toute la journée', 'Mois suivant', 'est déjà entré par ce lien', 'Oui, c’est moi', 'Non, une autre personne']] as $langue => $mots) {
        [, $gate] = $appel('langue-' . $langue, 'g/' . $jetonAvec, null, $langue);
        [, $vue] = $entrer('langue-' . $langue, $jetonAvec, $mots[0], $langue);
        [, $question] = $entrer('langue2-' . $langue, $jetonAvec, $mots[0], $langue);
        [, $distingue] = $repondre('langue2-' . $langue, $jetonAvec, $mots[0], 'autre', $langue);
        $dire("$langue : l'entrée et le projet sont traduits, sans clé brute",
            $oui(str_contains($gate, $mots[1]) && str_contains($gate, $mots[2]) && str_contains($vue, $mots[3]) && str_contains($vue, $mots[4]) && str_contains($vue, $mots[5])
                && str_contains($vue, 'aria-label="' . $mots[6] . '"') && !preg_match('/\btr\.(pu|me|fl|notif)\.[a-z_]+/', $gate . $vue)), 'oui');
        $dire("$langue : la question « est-ce vous ? » et l'invitation à se distinguer aussi",
            $oui(str_contains($question, $mots[7]) && str_contains($question, $mots[8]) && str_contains($question, $mots[9]) && str_contains($distingue, 'value="' . $mots[0] . ' "')
                && !preg_match('/\btr\.pu\.[a-z_]+/', $question . $distingue)), 'oui');
    }
    $termine = true;
} finally {
    // Ménage : les projets d'essai (leurs calendriers, évènements, tâches, fichiers et visiteurs partent avec eux), puis les comptes.
    foreach ($projets as $idProjet) {
        bd_run('DELETE FROM calendriers_amis WHERE projet_id = ?', [$idProjet]);
        bd_run('DELETE FROM projet_membres WHERE projet_id = ?', [$idProjet]);
        bd_run('DELETE FROM projets WHERE id = ? AND cree_par = ?', [$idProjet, $id('a')]);
    }
    foreach ($comptes as $c => $d) {
        if (isset($d['id'])) { bd_run('DELETE FROM users WHERE id = ? AND email = ? AND email LIKE ?', [$d['id'], $d['email'], '%@exemple-test.fr']); }
    }
    foreach (glob(__DIR__ . '/ck_tpub_*.txt') ?: [] as $fichierCookie) { @unlink($fichierCookie); }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'tpub-_@exemple-test.fr'") . "\n";
}

/** Le nom du mois en français, minuscule (le script d'essai ne charge rien de l'application). */
function nom_mois_en_phrase_test(int $mois): string
{
    return ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'][$mois - 1];
}
