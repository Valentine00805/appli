<?php
/**
 * Le lien public d'un travail de groupe, vu par quelqu'un qui n'a pas de compte (HTTP + base) : le calendrier commun du projet, s'il en a un —
 * ses évènements et les tâches datées du mois, jour par jour, en lecture seule —, la navigation de mois en mois (un mois illisible ou hors
 * bornes ramené au mois courant), un mois vide, un projet sans calendrier (pas de carte), ce qui ne doit pas s'y trouver (les notes d'un
 * évènement), un lien mort, et les quatre langues.
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

$email = 'tpub-a@exemple-test.fr';
bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$email, '%@exemple-test.fr']);
bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)', [$email, 'Tpub_un', password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai public']);
$idA = (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$email]);

/** Une page publique, sans compte ni cookie : @return array{0: int, 1: string} */
$visiteur = static function (string $chemin, string $langue = 'fr'): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['Accept-Language: ' . $langue]]);
    $corps = (string) curl_exec($h);
    $code = (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE);
    unset($h);
    return [$code, $corps];
};

$projets = [];
try {
    $mois = new DateTimeImmutable('first day of this month midnight');
    $suivant = $mois->modify('+1 month');
    $precedent = $mois->modify('-1 month');
    $jour = static fn (DateTimeImmutable $m, int $j): string => $m->format('Y-m-') . sprintf('%02d', $j);

    $jetonAvec = bin2hex(random_bytes(16));
    $jetonSans = bin2hex(random_bytes(16));
    foreach ([['Projet avec calendrier', $jetonAvec], ['Projet sans calendrier', $jetonSans]] as [$nom, $jeton]) {
        bd_run('INSERT INTO projets (nom, cree_par, jeton) VALUES (?, ?, ?)', [$nom, $idA, $jeton]);
        $projets[$nom] = (int) bd_valeur('SELECT id FROM projets WHERE cree_par = ? AND nom = ?', [$idA, $nom]);
        bd_run('INSERT INTO projet_membres (projet_id, user_id, role, statut) VALUES (?, ?, ?, ?)', [$projets[$nom], $idA, 'admin', 'membre']);
    }
    $p = $projets['Projet avec calendrier'];
    $membre = (int) bd_valeur('SELECT id FROM projet_membres WHERE projet_id = ? AND user_id = ?', [$p, $idA]);
    bd_run('INSERT INTO calendriers_amis (proprietaire_id, projet_id, nom, couleur) VALUES (?, ?, ?, ?)', [$idA, $p, 'Calendrier du groupe', '#e11d48']);
    $cal = (int) bd_valeur('SELECT id FROM calendriers_amis WHERE projet_id = ?', [$p]);
    bd_run('INSERT INTO calendrier_amis_evenements (calendrier_id, auteur_id, titre, description, lieu, debut, fin, journee_entiere) VALUES (?, ?, ?, ?, ?, ?, ?, 0), (?, ?, ?, ?, ?, ?, ?, 1), (?, ?, ?, ?, ?, ?, ?, 0)',
        [$cal, $idA, 'Réunion de groupe', 'NOTE-SECRETE-DE-L-EVENEMENT', 'Salle B12', $jour($mois, 15) . ' 14:00:00', $jour($mois, 15) . ' 15:30:00',
         $cal, $idA, 'Rendu du rapport', null, null, $jour($mois, 20) . ' 00:00:00', $jour($mois, 20) . ' 23:59:59',
         $cal, $idA, 'Soutenance du mois prochain', null, null, $jour($suivant, 5) . ' 09:00:00', $jour($suivant, 5) . ' 10:00:00']);
    bd_run('INSERT INTO projet_taches (projet_id, titre, membre_id, echeance, statut) VALUES (?, ?, ?, ?, ?), (?, ?, ?, ?, ?), (?, ?, NULL, ?, ?)',
        [$p, 'Rédiger la conclusion', $membre, $jour($mois, 18), 'a_faire', $p, 'Relire le plan', $membre, $jour($mois, 10), 'fait', $p, 'Tâche sans personne', $jour($mois, 22), 'a_faire']);

    echo "\n1. Le lien public d'un projet qui a un calendrier\n";
    [$cod, $page] = $visiteur('g/' . $jetonAvec);
    $dire('la carte « Calendrier » est là, avec le mois courant', $cod . ' · ' . $oui(str_contains($page, 'id="calendrier-public"') && str_contains($page, '📅 Calendrier')
        && str_contains($page, ucfirst(nom_mois_en_phrase_test((int) $mois->format('n'))) . ' ' . $mois->format('Y'))), '200 · oui');
    $dire('l\'évènement : titre, heures, lieu',
        $oui(str_contains($page, 'Réunion de groupe') && str_contains($page, '14h00 – 15h30') && str_contains($page, '📍 Salle B12')), 'oui');
    $dire('une journée entière : « Toute la journée »', $oui(preg_match('#Rendu du rapport</strong>\s*<span class="discret">\s*Toute la journée#', $page) === 1), 'oui');
    $dire('les tâches datées du projet y sont aussi (✅), avec qui s\'en occupe ; celle de personne sans nom',
        $oui(str_contains($page, '✅ Rédiger la conclusion') && str_contains($page, 'Tpub_un') && str_contains($page, '✅ Tâche sans personne')), 'oui');
    $dire('une tâche faite est barrée', $oui(preg_match('#calendrier-public__evenement--fait[^>]*>\s*<strong>✅ Relire le plan#', $page) === 1), 'oui');
    $dire('les jours se suivent dans l\'ordre (10, 15, 18, 20, 22), un jour avec son numéro',
        $oui(preg_match_all('#calendrier-public__numero">(\d+)<#', $page, $jours) === 5 && $jours[1] === ['10', '15', '18', '20', '22']), 'oui');
    $dire('la couleur du calendrier colore les évènements', $oui(str_contains($page, '--couleur-evt:#e11d48')), 'oui');
    $dire('ce qui n\'a pas à y être n\'y est pas : les notes de l\'évènement', $oui(!str_contains($page, 'NOTE-SECRETE-DE-L-EVENEMENT')), 'oui');
    $dire('rien à modifier : pas de formulaire dans la carte', $oui(preg_match('#id="calendrier-public".*?</section>#s', $page, $carte) === 1 && !str_contains($carte[0], '<form') && !str_contains($carte[0], 'data-fenetre')), 'oui');

    echo "\n2. De mois en mois\n";
    $dire('les liens « mois précédent » et « mois suivant »',
        $oui(str_contains($page, 'mois=' . $precedent->format('Y-m') . '#calendrier-public') && str_contains($page, 'mois=' . $suivant->format('Y-m') . '#calendrier-public')), 'oui');
    [, $prochain] = $visiteur('g/' . $jetonAvec . '?mois=' . $suivant->format('Y-m'));
    // (Dans la carte seulement : « Qui fait quoi » liste toujours toutes les tâches du projet, quel que soit le mois.)
    $dire('le mois suivant : sa soutenance, et plus les évènements ni les tâches de ce mois-ci dans le calendrier',
        $oui(preg_match('#id="calendrier-public".*?</section>#s', $prochain, $carteSuivante) === 1 && str_contains($carteSuivante[0], 'Soutenance du mois prochain')
            && !str_contains($carteSuivante[0], 'Réunion de groupe') && !str_contains($carteSuivante[0], 'Rédiger la conclusion')), 'oui');
    [, $vide] = $visiteur('g/' . $jetonAvec . '?mois=' . $mois->modify('-5 months')->format('Y-m'));
    $dire('un mois sans rien : « Rien de prévu ce mois-ci. »', $oui(str_contains($vide, 'Rien de prévu ce mois-ci.') && !str_contains($vide, 'calendrier-public__jours')), 'oui');
    [, $illisible] = $visiteur('g/' . $jetonAvec . '?mois=abc');
    [, $horsBornes] = $visiteur('g/' . $jetonAvec . '?mois=1999-01');
    $dire('un mois illisible ou hors des bornes : le mois courant', $oui(str_contains($illisible, 'Réunion de groupe') && str_contains($horsBornes, 'Réunion de groupe')), 'oui');
    [, $injection] = $visiteur('g/' . $jetonAvec . '?mois=2026-10%27%20OR%201=1');
    $dire('et une valeur piégée n\'est jamais prise telle quelle', $oui(str_contains($injection, 'Réunion de groupe') && !str_contains($injection, 'OR 1=1')), 'oui');

    echo "\n3. Un projet sans calendrier, un lien mort\n";
    [$cod, $sans] = $visiteur('g/' . $jetonSans);
    $dire('pas de calendrier commun : pas de carte « Calendrier »', $cod . ' · ' . $oui(!str_contains($sans, 'calendrier-public') && str_contains($sans, 'Qui fait quoi')), '200 · oui');
    [$cod] = $visiteur('g/' . bin2hex(random_bytes(16)));
    $dire('un lien inconnu : 404', (string) $cod, '404');

    echo "\n4. Les quatre langues\n";
    foreach (['en' => ['Calendar', 'All day', 'Next month'], 'es' => ['Calendario', 'Todo el día', 'Mes siguiente'], 'de' => ['Kalender', 'Ganztägig', 'Nächster Monat'],
        'fr' => ['Calendrier', 'Toute la journée', 'Mois suivant']] as $langue => $mots) {
        [, $vue] = $visiteur('g/' . $jetonAvec, $langue);
        $dire("$langue : la carte est traduite, sans clé brute", $oui(str_contains($vue, $mots[0]) && str_contains($vue, $mots[1]) && str_contains($vue, 'aria-label="' . $mots[2] . '"')
            && !preg_match('/\btr\.pu\.[a-z_]+/', $vue)), 'oui');
    }
    $termine = true;
} finally {
    // Ménage : les projets d'essai (leurs calendriers, évènements et tâches partent avec eux), puis le compte.
    foreach ($projets as $idProjet) {
        bd_run('DELETE FROM calendriers_amis WHERE projet_id = ?', [$idProjet]);
        bd_run('DELETE FROM projet_membres WHERE projet_id = ?', [$idProjet]);
        bd_run('DELETE FROM projets WHERE id = ? AND cree_par = ?', [$idProjet, $idA]);
    }
    bd_run('DELETE FROM users WHERE id = ? AND email = ? AND email LIKE ?', [$idA, $email, '%@exemple-test.fr']);
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur("SELECT COUNT(*) FROM users WHERE email LIKE 'tpub-_@exemple-test.fr'") . "\n";
}

/** Le nom du mois en français, minuscule (le script d'essai ne charge rien de l'application). */
function nom_mois_en_phrase_test(int $mois): string
{
    return ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'][$mois - 1];
}
