<?php
/**
 * Les serveurs façon Discord, de bout en bout (HTTP + base) : création avec son salon « général », invitations (acceptées, refusées,
 * annulées ; seulement des amis), entrée dans tous les salons, salons (créer, renommer, supprimer, jamais le dernier), rôles
 * (propriétaire, administrateur, membre), retraits, départ, suppression, et ce qu'un salon ne doit pas pouvoir faire comme un groupe
 * (renommer, ajouter des gens, quitter seul). Un étranger n'entre pas. Les quatre langues.
 *
 * Quatre comptes d'essai : A (propriétaire), B, C (amis de A), D (étranger). Rien n'est chargé du projet : HTTP et PDO seulement.
 */
require __DIR__ . '/base.php';

$anomalies = 0;
$termine = false;   // faux si le script s'arrête en route (erreur fatale) : le bilan ne doit pas dire « aucune anomalie »
$dire = static function (string $quoi, string $obtenu, string $attendu) use (&$anomalies): void {
    $bon = $obtenu === $attendu;
    if (!$bon) { $anomalies++; }
    printf("   %s %-74s %s%s\n", $bon ? '✓' : '✗', $quoi, $bon ? $obtenu : substr($obtenu, 0, 110), $bon ? '' : "\n       attendu : " . $attendu);
};
$oui = static fn (bool $b): string => $b ? 'oui' : 'non';

$emails = ['srv-a@exemple-test.fr', 'srv-b@exemple-test.fr', 'srv-c@exemple-test.fr', 'srv-d@exemple-test.fr'];
$nettoyer = static function () use ($emails): void {
    foreach ($emails as $e) {
        // Les serveurs de ces comptes d'abord : leurs salons (conversations) partent avec eux.
        bd_run('DELETE s FROM serveurs s JOIN users u ON u.id = s.cree_par WHERE u.email = ? AND u.email LIKE ?', [$e, '%@exemple-test.fr']);
    }
    foreach ($emails as $e) { bd_run('DELETE FROM users WHERE email = ? AND email LIKE ?', [$e, '%@exemple-test.fr']); }
};
$nettoyer();
foreach ($emails as $i => $e) {
    bd_run('INSERT INTO users (email, pseudo, password_hash, nom) VALUES (?, ?, ?, ?)',
        [$e, 'Serveur_' . $i, password_hash('MotDePasse!2026', PASSWORD_DEFAULT), 'Essai serveurs']);
}
[$idA, $idB, $idC, $idD] = array_map(static fn (string $e): int => (int) bd_valeur('SELECT id FROM users WHERE email = ?', [$e]), $emails);
foreach ([[$idA, $idB], [$idA, $idC]] as [$x, $y]) {
    bd_run("INSERT INTO amities (demandeur_id, destinataire_id, petit_id, grand_id, statut, created_at, acceptee_le) VALUES (?, ?, ?, ?, 'acceptee', UTC_TIMESTAMP(), UTC_TIMESTAMP())",
        [$x, $y, min($x, $y), max($x, $y)]);
}

$cookies = array_combine($emails, array_map(static fn (string $c): string => __DIR__ . '/ck_srv_' . $c . '.txt', ['a', 'b', 'c', 'd']));
foreach ($cookies as $f) { @unlink($f); }
/** @return array{0: string, 1: string, 2: int} corps, adresse finale, code */
$appel = static function (string $compte, string $chemin, ?array $post = null, array $entetes = [], bool $suivre = true) use ($cookies): array {
    usleep(250000);
    $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $suivre, CURLOPT_TIMEOUT => 60,
        CURLOPT_COOKIEJAR => $cookies[$compte], CURLOPT_COOKIEFILE => $cookies[$compte], CURLOPT_HTTPHEADER => $entetes]);
    if ($post !== null) { curl_setopt($h, CURLOPT_POST, true); curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $corps = (string) curl_exec($h);
    $r = [$corps, (string) curl_getinfo($h, CURLINFO_EFFECTIVE_URL), (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE)];
    unset($h);
    return $r;
};
$jeton = static fn (string $html): string => preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '';

$serveur = static fn (): int => (int) bd_valeur('SELECT id FROM serveurs WHERE cree_par = ? ORDER BY id DESC LIMIT 1', [$idA]);
$role = static fn (int $s, int $u): string => (string) bd_valeur('SELECT role FROM serveur_membres WHERE serveur_id = ? AND user_id = ?', [$s, $u]);
$salons = static fn (int $s): array => array_map(static fn (array $l): string => (string) $l['nom'],
    bd()->query('SELECT nom FROM conversations WHERE serveur_id = ' . $s . ' ORDER BY position, id')->fetchAll());
$salonId = static fn (int $s, string $nom): int => (int) bd_valeur('SELECT id FROM conversations WHERE serveur_id = ? AND nom = ?', [$s, $nom]);
$dansSalon = static fn (int $salon, int $u): bool => bd_valeur('SELECT 1 FROM conversation_membres WHERE conversation_id = ? AND user_id = ?', [$salon, $u]) !== null;
$evenement = static fn (int $salon, string $ev): int => (int) bd_valeur('SELECT COUNT(*) FROM conversation_messages WHERE conversation_id = ? AND evenement = ?', [$salon, $ev]);

try {
    [$a, $b, $c, $d] = $emails;
    foreach ($emails as $e) {
        [$p] = $appel($e, 'connexion');
        $appel($e, 'connexion', ['_csrf' => $jeton($p), 'identifiant' => $e, 'mot_de_passe' => 'MotDePasse!2026']);
    }
    $csrf = [];
    foreach ($emails as $e) { [$p] = $appel($e, 'serveurs'); $csrf[$e] = $jeton($p); }
    $poster = static fn (string $compte, string $chemin, array $champs = []): array => $appel($compte, $chemin, ['_csrf' => $csrf[$compte]] + $champs);

    echo "\n1. Créer un serveur\n";
    [$page] = $appel($a, 'serveurs');
    $dire('la page « Serveurs » s\'ouvre, vide, avec le bouton de création', $oui(str_contains($page, 'Mes serveurs') && str_contains($page, 'serveurs/nouveau')), 'oui');
    [$fenetre] = $appel($a, 'serveurs/nouveau?fenetre=1');
    $dire('le formulaire de création propose un nom et une photo facultative, sans icônes', $oui(str_contains($fenetre, 'name="nom"') && str_contains($fenetre, 'name="photo"') && !str_contains($fenetre, 'name="icone"') && !preg_match('/name="photo"[^>]*required/', $fenetre)), 'oui');
    $poster($a, 'serveurs', ['nom' => '   ']);
    $dire('un nom vide est refusé', (string) bd_valeur('SELECT COUNT(*) FROM serveurs WHERE cree_par = ?', [$idA]), '0');
    $poster($a, 'serveurs', ['nom' => str_repeat('x', 61)]);
    $dire('un nom de plus de 60 caractères est refusé', (string) bd_valeur('SELECT COUNT(*) FROM serveurs WHERE cree_par = ?', [$idA]), '0');
    [, $adresse] = $poster($a, 'serveurs', ['nom' => "  Licence   2  "]);
    $s = $serveur();
    $dire('le serveur est créé, son nom nettoyé', (string) bd_valeur('SELECT nom FROM serveurs WHERE id = ?', [$s]), 'Licence 2');
    $dire('sans photo, aucun fichier n\'est gardé', (string) bd_valeur('SELECT COUNT(*) FROM serveurs WHERE id = ? AND photo_nom IS NOT NULL', [$s]), '0');
    $dire('le créateur en est le propriétaire', $role($s, $idA), 'proprietaire');
    $dire('il naît avec un seul salon « général »', implode(',', $salons($s)), 'général');
    $general = $salonId($s, 'général');
    $dire('on arrive dans ce salon', $oui(str_ends_with($adresse, 'groupes/' . $general)), 'oui');
    $dire('le propriétaire est membre du salon', $oui($dansSalon($general, $idA)), 'oui');
    $dire('la page du salon montre le côté du serveur (nom, salons, membres)', $oui(str_contains($adresse . '', 'groupes') && str_contains($page = $appel($a, 'groupes/' . $general)[0], 'chat__serveur')
        && str_contains($page, '# général') && str_contains($page, 'Licence 2') && str_contains($page, 'Serveur_0')), 'oui');
    $dire('le « ＋ » des salons ouvre leur propre fenêtre (sans « # » dans l\'adresse, qui perdrait le marquage de la fenêtre)',
        $oui(str_contains($page, "serveurs/$s/salons\"") && !str_contains($page, '#salons')), 'oui');
    [$fragment] = $appel($a, "serveurs/$s/salons?fenetre=1");
    $dire('cette fenêtre ne montre que les salons : liste, renommer, supprimer, ajouter (un fragment, sans le reste des réglages)',
        $oui(!str_contains($fragment, '<html') && str_contains($fragment, 'name="nom"') && str_contains($fragment, '/renommer')
            && !str_contains($fragment, 'data-photo-carte') && !str_contains($fragment, 'serveur-couleur') && !str_contains($fragment, 'groupe-formulaire')), 'oui');
    $dire('chaque salon se lit d\'abord : « Modifier » ouvre le champ, qui s\'enregistre ou s\'annule',
        $oui(str_contains($fragment, 'data-reglage-modifier') && str_contains($fragment, 'data-reglage-edition hidden') && str_contains($fragment, 'data-reglage-annuler')
            && str_contains($fragment, 'data-valeur-actuelle="général"')), 'oui');
    [$reglagesA] = $appel($a, "serveurs/$s/reglages?fenetre=1");
    $dire('les réglages ne gardent des salons que la liste et un bouton « Gérer les salons »', $oui(str_contains($reglagesA, "serveurs/$s/salons\"") && !str_contains($reglagesA, '/renommer')), 'oui');
    $dire('le salon n\'apparaît pas dans la liste des discussions de groupe', $oui(!str_contains($appel($a, 'amis')[0], 'groupes/' . $general)), 'oui');
    $dire('l\'entrée « Serveurs » est dans la page Messages', $oui(str_contains($appel($a, 'amis')[0], 'href="http://localhost/mon_appli/appli/serveurs"')
        || str_contains($appel($a, 'amis')[0], '/serveurs"')), 'oui');

    echo "\n2. Inviter (des amis seulement), accepter, refuser\n";
    [$reg] = $appel($a, "serveurs/$s/reglages?fenetre=1");
    $dire('les réglages listent les amis à inviter, pas l\'étranger', $oui(str_contains($reg, 'Serveur_1') && str_contains($reg, 'Serveur_2') && !str_contains($reg, 'Serveur_3')), 'oui');
    $poster($a, "serveurs/$s/inviter", ['amis' => [$idD]]);
    $dire('un étranger (pas un ami) n\'est pas invité', (string) bd_valeur('SELECT COUNT(*) FROM serveur_invitations WHERE serveur_id = ?', [$s]), '0');
    $poster($a, "serveurs/$s/inviter", []);
    $dire('inviter sans rien cocher ne fait rien', (string) bd_valeur('SELECT COUNT(*) FROM serveur_invitations WHERE serveur_id = ?', [$s]), '0');
    $poster($a, "serveurs/$s/inviter", ['amis' => [$idB, $idC]]);
    $dire('B et C sont invités', (string) bd_valeur('SELECT COUNT(*) FROM serveur_invitations WHERE serveur_id = ?', [$s]), '2');
    $dire('et ne sont pas encore membres', $role($s, $idB) . $role($s, $idC), '');
    $poster($a, "serveurs/$s/inviter", ['amis' => [$idB]]);
    $dire('inviter deux fois la même personne ne double rien', (string) bd_valeur('SELECT COUNT(*) FROM serveur_invitations WHERE serveur_id = ?', [$s]), '2');
    [$pageB] = $appel($b, 'serveurs');
    $dire('B voit l\'invitation, avec qui l\'a invité', $oui(str_contains($pageB, 'Invitations') && str_contains($pageB, 'Licence 2') && str_contains($pageB, 'Serveur_0')), 'oui');
    $dire('et le compteur de la page Messages', $oui(str_contains($appel($b, 'amis')[0], 'serveurs')), 'oui');
    $poster($b, "serveurs/$s/membres/$idB/admin");
    $dire('B, pas encore membre, ne peut rien régler', $role($s, $idB), '');
    $poster($b, "serveurs/$s/refuser");
    $dire('B refuse : l\'invitation disparaît, B n\'est pas dedans', (string) bd_valeur('SELECT COUNT(*) FROM serveur_invitations WHERE serveur_id = ? AND user_id = ?', [$s, $idB]) . $role($s, $idB), '0');
    $poster($a, "serveurs/$s/inviter", ['amis' => [$idB]]);
    $poster($a, "serveurs/$s/invitations/$idC/annuler");
    $dire('A annule l\'invitation de C', (string) bd_valeur('SELECT COUNT(*) FROM serveur_invitations WHERE serveur_id = ? AND user_id = ?', [$s, $idC]), '0');
    $poster($c, "serveurs/$s/accepter");
    $dire('C ne peut pas accepter une invitation annulée', $role($s, $idC), '');
    $poster($d, "serveurs/$s/accepter");
    $dire('D ne peut pas accepter une invitation qu\'il n\'a pas reçue', $role($s, $idD), '');
    $poster($b, "serveurs/$s/accepter");
    $dire('B accepte : il est membre', $role($s, $idB), 'membre');
    $dire('l\'invitation est consommée', (string) bd_valeur('SELECT COUNT(*) FROM serveur_invitations WHERE serveur_id = ? AND user_id = ?', [$s, $idB]), '0');
    $dire('B est dans le salon « général »', $oui($dansSalon($general, $idB)), 'oui');
    $dire('une note « a rejoint » est écrite dans le salon', (string) $evenement($general, 'srv_rejoint'), '1');
    [$salonB] = $appel($b, 'groupes/' . $general);
    $dire('B voit le salon, la note en français, et le serveur', $oui(str_contains($salonB, 'rejoint le serveur') && str_contains($salonB, 'chat__serveur')), 'oui');

    // La colonne du serveur
    [$pageA] = $appel($a, 'groupes/' . $general);
    [$pageB] = $appel($b, 'groupes/' . $general);
    $dire('la colonne du serveur n\'a plus de lien « ← Mes serveurs »', $oui(!str_contains($pageA, '← Mes serveurs')), 'oui');
    $dire('l\'administrateur a un « ＋ » à côté des membres, avec leur nombre ; le simple membre n\'en a pas',
        $oui(str_contains($pageA, "serveurs/$s/inviter\"") && str_contains($pageA, 'serveur-sous-titre__nombre') && !str_contains($pageB, "serveurs/$s/inviter\"")), 'oui');
    [$fenInviter] = $appel($a, "serveurs/$s/inviter?fenetre=1");
    $dire('ce « ＋ » ouvre une fenêtre d\'invitation : les amis à inviter, rien d\'autre', $oui(!str_contains($fenInviter, '<html') && str_contains($fenInviter, 'name="amis[]"')
        && str_contains($fenInviter, 'name="retour"') && !str_contains($fenInviter, 'data-photo-carte') && !str_contains($fenInviter, 'serveur-couleur')), 'oui');
    [$fenB] = $appel($b, "serveurs/$s/inviter?fenetre=1");
    $dire('un simple membre n\'y accède pas', $oui(!str_contains($fenB, 'name="amis[]"')), 'oui');
    [$corpsInv, $adresseInv] = $poster($a, "serveurs/$s/inviter", ['amis' => [$idC], 'retour' => 'inviter', 'fenetre' => '1']);
    $dire('inviter depuis cette fenêtre y reste, avec la liste des invitations en attente', $oui(str_contains($adresseInv, "serveurs/$s/inviter") && str_contains($adresseInv, 'fenetre=1')
        && str_contains($corpsInv, 'Invitations en attente') && !str_contains($corpsInv, '<html')), 'oui');
    [, $adresseAnnul] = $poster($a, "serveurs/$s/invitations/$idC/annuler", ['retour' => 'inviter', 'fenetre' => '1']);
    $dire('annuler une invitation y reste aussi', $oui(str_contains($adresseAnnul, "serveurs/$s/inviter") && $role($s, $idC) === ''), 'oui');

    echo "\n3. Les salons\n";
    $poster($b, "serveurs/$s/salons", ['nom' => 'Pirate']);
    $dire('un simple membre ne crée pas de salon', implode(',', $salons($s)), 'général');
    $poster($a, "serveurs/$s/salons", ['nom' => '  #Cours de   Maths ']);
    $dire('un nom de salon devient « cours-de-maths »', implode(',', $salons($s)), 'général,cours-de-maths');
    $maths = $salonId($s, 'cours-de-maths');
    $dire('les membres sont dans le nouveau salon', $oui($dansSalon($maths, $idA) && $dansSalon($maths, $idB)), 'oui');
    $poster($a, "serveurs/$s/salons", ['nom' => 'cours de maths']);
    $dire('un doublon est refusé', (string) count($salons($s)), '2');
    $poster($a, "serveurs/$s/salons", ['nom' => '###']);
    $dire('un nom sans lettre est refusé', (string) count($salons($s)), '2');
    $poster($a, "serveurs/$s/salons/$maths/renommer", ['nom' => 'Révisions']);
    $dire('renommer un salon', implode(',', $salons($s)), 'général,révisions');
    $poster($b, "serveurs/$s/salons/$maths/renommer", ['nom' => 'pirate']);
    $dire('un membre ne renomme pas', implode(',', $salons($s)), 'général,révisions');
    [$salonB] = $appel($b, 'groupes/' . $maths);
    $dire('la page d\'un autre salon affiche son nom avec #', $oui(str_contains($salonB, '# révisions')), 'oui');
    // Un message dans le salon : écrit par B, lu par A.
    $poster($b, "groupes/$maths/messages", ['texte' => 'Bonjour le salon']);
    $dire('B écrit dans le salon', (string) bd_valeur('SELECT COUNT(*) FROM conversation_messages WHERE conversation_id = ? AND texte = ?', [$maths, 'Bonjour le salon']), '1');
    [$pageA] = $appel($a, 'groupes/' . $general);
    $dire('A voit « 1 » non lu sur le salon « révisions »', $oui((bool) preg_match('/révisions<\/span>\s*<span class="compteur">1<\/span>/u', $pageA)), 'oui');
    [$pageA] = $appel($a, 'groupes/' . $maths);
    $dire('A lit le message de B', $oui(str_contains($pageA, 'Bonjour le salon')), 'oui');

    echo "\n4. Ce qui se règle depuis le serveur, pas depuis le salon\n";
    $poster($b, "groupes/$maths/nom", ['nom' => 'Pirate']);
    $poster($a, "groupes/$maths/nom", ['nom' => 'Pirate']);
    $dire('on ne renomme pas un salon comme un groupe', implode(',', $salons($s)), 'général,révisions');
    $poster($a, "groupes/$maths/membres", ['membres' => [$idC]]);
    $dire('on n\'y ajoute personne en passant par les routes du groupe', $oui($dansSalon($maths, $idC)), 'non');
    $poster($b, "groupes/$maths/quitter");
    $dire('on ne quitte pas un seul salon', $oui($dansSalon($maths, $idB) && $role($s, $idB) === 'membre'), 'oui');
    $poster($a, "groupes/$maths/inviter", ['compte' => $idC]);
    $dire('on n\'y invite personne non plus', (string) bd_valeur('SELECT COUNT(*) FROM conversation_invitations WHERE conversation_id = ?', [$maths]), '0');
    [$regSalon] = $appel($a, "groupes/$maths/reglages?fenetre=1");
    $dire('les réglages du salon : notifications et fond, ni membres ni départ', $oui(str_contains($regSalon, 'Salon du serveur')
        && !str_contains($regSalon, 'groupe-membres') && !str_contains($regSalon, 'confirmer-depart-groupe') && !str_contains($regSalon, 'photo-groupe')), 'oui');
    $dire('et un lien vers les réglages du serveur', $oui(str_contains($regSalon, "serveurs/$s/reglages")), 'oui');

    echo "\n5. Les rôles\n";
    $poster($a, "serveurs/$s/inviter", ['amis' => [$idC]]);
    $poster($c, "serveurs/$s/accepter");
    $dire('C entre à son tour, dans tous les salons', $oui($role($s, $idC) === 'membre' && $dansSalon($general, $idC) && $dansSalon($maths, $idC)), 'oui');
    [$msg] = $appel($c, 'groupes/' . $maths);
    $dire('C voit l\'historique d\'avant son arrivée', $oui(str_contains($msg, 'Bonjour le salon')), 'oui');
    $poster($b, "serveurs/$s/membres/$idC/admin");
    $dire('un membre ne nomme pas d\'administrateur', $role($s, $idC), 'membre');
    $poster($a, "serveurs/$s/membres/$idB/admin");
    $dire('le propriétaire nomme B administrateur', $role($s, $idB), 'admin');
    $poster($b, "serveurs/$s/salons", ['nom' => 'annonces']);
    $dire('B, administrateur, crée un salon', implode(',', $salons($s)), 'général,révisions,annonces');
    $poster($b, "serveurs/$s/membres/$idC/admin");
    $dire('mais ne nomme pas d\'administrateur', $role($s, $idC), 'membre');
    $poster($a, "serveurs/$s/membres/$idC/admin");
    $poster($b, "serveurs/$s/membres/$idC/retirer");
    $dire('B ne retire pas un autre administrateur', $role($s, $idC), 'admin');
    $poster($b, "serveurs/$s/membres/$idA/retirer");
    $dire('ni le propriétaire', $role($s, $idA), 'proprietaire');
    $poster($b, "serveurs/$s/membres/$idC/membre");
    $dire('ni ne lui retire son rôle', $role($s, $idC), 'admin');
    $poster($a, "serveurs/$s/membres/$idC/membre");
    $dire('le propriétaire retire le rôle d\'administrateur', $role($s, $idC), 'membre');
    $poster($b, "serveurs/$s/supprimer");
    $dire('B ne supprime pas le serveur', (string) bd_valeur('SELECT COUNT(*) FROM serveurs WHERE id = ?', [$s]), '1');
    $poster($c, "serveurs/$s/salons", ['nom' => 'pirate']);
    $poster($c, "serveurs/$s/inviter", ['amis' => [$idD]]);
    $dire('C, simple membre, ne crée ni n\'invite', implode(',', $salons($s)) . '|' . bd_valeur('SELECT COUNT(*) FROM serveur_invitations WHERE serveur_id = ?', [$s]), 'général,révisions,annonces|0');
    [$regC] = $appel($c, "serveurs/$s/reglages?fenetre=1");
    $dire('ses réglages n\'offrent ni création, ni invitation, ni retrait', $oui(!str_contains($regC, 'name="amis[]"') && !str_contains($regC, '/salons"') && !str_contains($regC, '/retirer')), 'oui');

    echo "\n6. Retirer, quitter\n";
    $poster($b, "serveurs/$s/membres/$idC/retirer");
    $dire('B retire C du serveur', $role($s, $idC), '');
    $dire('C n\'est plus dans aucun salon', $oui($dansSalon($general, $idC) || $dansSalon($maths, $idC)), 'non');
    $dire('une note « retiré » est écrite', (string) $evenement($general, 'srv_retrait'), '1');
    [$vue] = $appel($c, 'groupes/' . $general);
    $dire('C n\'ouvre plus le salon', $oui(!str_contains($vue, 'Bonjour le salon') && !str_contains($vue, 'chat__serveur')), 'oui');
    [$vueA] = $appel($a, 'groupes/' . $general);
    $dire('la note se lit en français', $oui(str_contains($vueA, 'a retiré') && str_contains($vueA, 'du serveur')), 'oui');
    $poster($a, "serveurs/$s/inviter", ['amis' => [$idC]]);
    $poster($c, "serveurs/$s/accepter");
    $poster($c, "serveurs/$s/quitter");
    $dire('C revient puis quitte lui-même', $role($s, $idC), '');
    $dire('le départ laisse une note', (string) $evenement($general, 'srv_depart'), '1');
    $poster($a, "serveurs/$s/quitter");
    $dire('le propriétaire ne peut pas quitter tant qu\'il reste du monde', $role($s, $idA), 'proprietaire');
    $poster($a, "serveurs/$s/salons/" . $salonId($s, 'annonces') . '/supprimer');
    $poster($a, "serveurs/$s/salons/$maths/supprimer");
    $dire('un salon supprimé s\'efface avec ses messages', (string) bd_valeur('SELECT COUNT(*) FROM conversations WHERE id = ?', [$maths]) . bd_valeur('SELECT COUNT(*) FROM conversation_messages WHERE conversation_id = ?', [$maths]), '00');
    $poster($a, "serveurs/$s/salons/$general/supprimer");
    $dire('le dernier salon reste', implode(',', $salons($s)), 'général');

    echo "\n7. Un étranger n'entre pas\n";
    [$r, $adresse] = $appel($d, "serveurs/$s");
    $dire('D ne peut pas ouvrir le serveur', $oui(!str_contains($adresse, 'groupes/')), 'oui');
    [$r] = $appel($d, "serveurs/$s/reglages?fenetre=1");
    $dire('ni ses réglages', $oui(!str_contains($r, 'Licence 2')), 'oui');
    [$r] = $appel($d, 'groupes/' . $general);
    $dire('ni le salon', $oui(!str_contains($r, 'chat__serveur') && !str_contains($r, 'Licence 2')), 'oui');
    $poster($d, "groupes/$general/messages", ['texte' => 'intrus']);
    $dire('ni n\'y écrire', (string) bd_valeur('SELECT COUNT(*) FROM conversation_messages WHERE conversation_id = ? AND texte = ?', [$general, 'intrus']), '0');
    $poster($d, "serveurs/$s/quitter");
    $poster($d, "serveurs/$s/salons", ['nom' => 'pirate']);
    $dire('ni y agir', implode(',', $salons($s)), 'général');

    echo "\n8. Régler le serveur, le supprimer\n";
    $poster($b, "serveurs/$s/modifier", ['nom' => 'Master 1']);
    $dire('un administrateur renomme le serveur', (string) bd_valeur('SELECT nom FROM serveurs WHERE id = ?', [$s]), 'Master 1');
    $poster($b, "serveurs/$s/membres/$idB/membre");
    $dire('un administrateur ne se retire pas son rôle seul', $role($s, $idB), 'admin');
    $poster($b, "serveurs/$s/quitter");
    $dire('un administrateur peut quitter', $role($s, $idB), '');
    $poster($a, "serveurs/$s/supprimer");
    $dire('le propriétaire supprime le serveur', (string) bd_valeur('SELECT COUNT(*) FROM serveurs WHERE id = ?', [$s]), '0');
    $dire('ses salons et ses membres partent avec', (string) bd_valeur('SELECT COUNT(*) FROM conversations WHERE id = ?', [$general]) . bd_valeur('SELECT COUNT(*) FROM serveur_membres WHERE serveur_id = ?', [$s]), '00');
    $poster($a, 'serveurs', ['nom' => 'Solo']);
    $s2 = $serveur();
    $poster($a, "serveurs/$s2/quitter");
    $dire('un propriétaire seul qui quitte efface le serveur', (string) bd_valeur('SELECT COUNT(*) FROM serveurs WHERE id = ?', [$s2]), '0');
    $poster($a, 'serveurs', ['nom' => 'Troisième']);
    $s3 = $serveur();
    $dire('on peut en créer d\'autres', $oui($s3 > 0 && $role($s3, $idA) === 'proprietaire'), 'oui');

    echo "\n8 bis. Le logo\n";
    $dossier = dirname(__DIR__, 2) . '/storage/messages/';
    $png = tempnam(sys_get_temp_dir(), 'logo') . '.png';
    $image = imagecreatetruecolor(60, 60);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 40, 90));
    imagepng($image, $png);
    unset($image);
    $envoyer = static function (string $compte, string $chemin, string $fichier) use ($cookies, $csrf): array {
        $h = curl_init('http://localhost/mon_appli/appli/' . $chemin);
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60, CURLOPT_COOKIEJAR => $cookies[$compte],
            CURLOPT_COOKIEFILE => $cookies[$compte], CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['_csrf' => $csrf[$compte], 'photo' => new CURLFile($fichier, 'image/png', 'logo.png')]]);
        $corps = (string) curl_exec($h);
        $code = (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE);
        unset($h);
        return [$corps, $code];
    };
    $photoNom = static fn (int $srv): ?string => bd_valeur('SELECT photo_nom FROM serveurs WHERE id = ?', [$srv]);
    $poster($a, "serveurs/$s3/inviter", ['amis' => [$idB]]);
    $poster($b, "serveurs/$s3/accepter");
    [$corpsInv, $adresseInv] = $poster($a, "serveurs/$s3/inviter", ['amis' => [$idC], 'fenetre' => '1']);
    $dire('inviter depuis la fenêtre y reste (fragment, pas la page entière)', $oui(str_contains($adresseInv, 'fenetre=1') && !str_contains($corpsInv, '<html') && str_contains($corpsInv, 'Invitations en attente')), 'oui');
    $poster($a, "serveurs/$s3/invitations/$idC/annuler");
    $envoyer($b, "serveurs/$s3/photo", $png);
    $dire('un simple membre ne change pas le logo', $oui($photoNom($s3) === null), 'oui');
    $envoyer($a, "serveurs/$s3/photo", $png);
    $nom = (string) $photoNom($s3);
    $dire('un administrateur pose un logo, rangé sur le disque', $oui($nom !== '' && is_file($dossier . $nom)), 'oui');
    $h = curl_init("http://localhost/mon_appli/appli/serveurs/$s3/photo");
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookies[$b], CURLOPT_COOKIEFILE => $cookies[$b]]);
    $contenu = (string) curl_exec($h);
    $type = (string) curl_getinfo($h, CURLINFO_CONTENT_TYPE);
    unset($h);
    $dire('un membre lit le logo (une image)', $oui(str_starts_with($type, 'image/') && strlen($contenu) > 50), 'oui');
    [, , $codeD] = $appel($d, "serveurs/$s3/photo");
    $dire('un étranger ne le lit pas (404)', (string) $codeD, '404');
    [$barre] = $appel($b, 'amis');
    $dire('la barre des serveurs montre le logo à la place des initiales', $oui(str_contains($barre, "serveurs/$s3/photo")), 'oui');
    [$reg] = $appel($a, "serveurs/$s3/reglages?fenetre=1");
    $dire('les réglages le montrent et proposent de le retirer', $oui(str_contains($reg, "serveurs/$s3/photo/retirer")), 'oui');
    $ancien = $nom;
    $envoyer($a, "serveurs/$s3/photo", $png);
    $dire('un nouveau logo remplace l\'ancien, dont le fichier est effacé', $oui($photoNom($s3) !== $ancien && !is_file($dossier . $ancien)), 'oui');
    $poster($b, "serveurs/$s3/photo/retirer");
    $dire('un membre ne retire pas le logo', $oui($photoNom($s3) !== null), 'oui');
    $nom = (string) $photoNom($s3);
    $poster($a, "serveurs/$s3/photo/retirer");
    $dire('le propriétaire le retire : les initiales reviennent, le fichier part', $oui($photoNom($s3) === null && !is_file($dossier . $nom)), 'oui');
    $envoyer($a, "serveurs/$s3/photo", $png);
    $nom = (string) $photoNom($s3);
    $poster($a, "serveurs/$s3/supprimer");
    $dire('supprimer le serveur efface aussi le fichier du logo', $oui($nom !== '' && !is_file($dossier . $nom)), 'oui');
    @unlink($png);
    $poster($a, 'serveurs', ['nom' => 'Troisième bis']);
    $s3 = $serveur();

    echo "\n8 ter. Photo à la création, initiales sans photo\n";
    $creerAvecFichier = static function (string $nom, string $fichier, string $mime) use ($cookies, $csrf, $a): string {
        $h = curl_init('http://localhost/mon_appli/appli/serveurs');
        curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60, CURLOPT_COOKIEJAR => $cookies[$a],
            CURLOPT_COOKIEFILE => $cookies[$a], CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['_csrf' => $csrf[$a], 'nom' => $nom, 'photo' => new CURLFile($fichier, $mime, 'logo.png')]]);
        $corps = (string) curl_exec($h);
        unset($h);
        return $corps;
    };
    $png2 = tempnam(sys_get_temp_dir(), 'logo') . '.png';
    $im = imagecreatetruecolor(50, 50);
    imagepng($im, $png2);
    unset($im);
    $creerAvecFichier('Avec logo', $png2, 'image/png');
    $avecLogo = (int) bd_valeur('SELECT id FROM serveurs WHERE nom = ? AND cree_par = ?', ['Avec logo', $idA]);
    $nomLogo = (string) bd_valeur('SELECT photo_nom FROM serveurs WHERE id = ?', [$avecLogo]);
    $dire('une photo choisie à la création est gardée', $oui($avecLogo > 0 && $nomLogo !== '' && is_file($dossier . $nomLogo)), 'oui');
    $faux = tempnam(sys_get_temp_dir(), 'faux') . '.png';
    file_put_contents($faux, "ceci n'est pas une image");
    $creerAvecFichier('Faux logo', $faux, 'image/png');
    $dire('un fichier qui n\'est pas une image refuse la création (rien n\'est créé)', (string) bd_valeur('SELECT COUNT(*) FROM serveurs WHERE nom = ?', ['Faux logo']), '0');
    @unlink($png2);
    @unlink($faux);
    // La couleur du fond des initiales
    $couleur = static fn (int $srv): string => (string) bd_valeur('SELECT COALESCE(couleur, \'auto\') FROM serveurs WHERE id = ?', [$srv]);
    [$reg] = $appel($a, "serveurs/$s3/reglages?fenetre=1");
    $dire('les réglages proposent la couleur (sans photo) : automatique, pastilles, couleur libre', $oui(str_contains($reg, 'data-couleurs') && substr_count($reg, 'name="couleur"') === 14 && str_contains($reg, 'name="couleur_perso"')), 'oui');
    $dire('par défaut la couleur est automatique', $couleur($s3), 'auto');
    $poster($a, "serveurs/$s3/couleur", ['couleur' => '#ff0000']);
    $dire('une pastille choisie est enregistrée', $couleur($s3), '#ff0000');
    $poster($a, "serveurs/$s3/couleur", ['couleur' => 'perso', 'couleur_perso' => '#00FF88']);
    $dire('une couleur libre aussi (ramenée en minuscules)', $couleur($s3), '#00ff88');
    $poster($a, "serveurs/$s3/couleur", ['couleur' => 'perso', 'couleur_perso' => 'javascript:alert(1)']);
    $poster($a, "serveurs/$s3/couleur", ['couleur' => 'rouge']);
    $dire('une valeur qui n\'est pas une couleur est refusée', $couleur($s3), '#00ff88');
    $poster($b, "serveurs/$s3/couleur", ['couleur' => '#000000']);
    $dire('un simple membre ne change pas la couleur', $couleur($s3), '#00ff88');
    [$barre] = $appel($a, 'amis');
    $dire('la barre des serveurs porte la couleur choisie', $oui(str_contains($barre, '--couleur: #00ff88')), 'oui');
    $poster($a, "serveurs/$s3/couleur", ['couleur' => '#f1c40f']);
    [$barre] = $appel($a, 'amis');
    $dire('sur un fond clair, le texte devient sombre (lisible)', $oui(str_contains($barre, '--couleur: #f1c40f; --texte-logo: #1f2937')), 'oui');
    $poster($a, "serveurs/$s3/couleur", ['couleur' => '#2c3e50']);
    [$barre] = $appel($a, 'amis');
    $dire('sur un fond sombre, le texte reste blanc', $oui(str_contains($barre, '--couleur: #2c3e50; --texte-logo: #fff')), 'oui');
    $poster($a, "serveurs/$s3/couleur", ['couleur' => '']);
    $dire('« automatique » rend la couleur du nom', $couleur($s3), 'auto');
    $poster($a, 'serveurs', ['nom' => 'Coloré', 'couleur' => 'perso', 'couleur_perso' => '#123456']);
    $dire('on choisit la couleur dès la création', (string) bd_valeur('SELECT couleur FROM serveurs WHERE nom = ? AND cree_par = ?', ['Coloré', $idA]), '#123456');
    foreach (['Maths', 'Cours de maths', 'élèves', 'Licence 2 — groupe A'] as $n) { $poster($a, 'serveurs', ['nom' => $n]); }
    [$barre] = $appel($a, 'amis');
    foreach ([
        'un seul mot : ses deux premières lettres (Maths → Ma)' => '>Ma<',
        'plusieurs mots : la première lettre de chacun (Cours de maths → CDM)' => '>CDM<',
        'les accents suivent (élèves → Él)' => '>Él<',
        'les signes ne comptent pas (Licence 2 — groupe A → L2GA)' => '>L2GA<',
    ] as $quoi => $attendu) {
        $dire($quoi, $oui(str_contains($barre, $attendu)), 'oui');
    }
    $dire('le logo avec photo montre l\'image, pas des initiales', $oui(str_contains($barre, "serveurs/$avecLogo/photo")), 'oui');
    $dire('chaque serveur sans photo a sa couleur', $oui((bool) preg_match_all('/--couleur: hsl\(\d+ 52% 40%\)/', $barre) >= 4), 'oui');
    $poster($a, "serveurs/$avecLogo/supprimer");
    $dire('supprimer le serveur efface le fichier de la photo choisie à la création', $oui(!is_file($dossier . $nomLogo)), 'oui');

    echo "\n9. Les quatre langues\n";
    foreach ([
        'en' => ['Servers', 'My servers', 'Create a server'],
        'es' => ['Servidores', 'Mis servidores', 'Crear un servidor'],
        'de' => ['Server', 'Meine Server', 'Server erstellen'],
        'fr' => ['Serveurs', 'Mes serveurs', 'Créer un serveur'],
    ] as $langue => $mots) {
        $poster($a, 'compte/langue', ['langue' => $langue]);
        [$page] = $appel($a, 'serveurs');
        [$reg] = $appel($a, "serveurs/$s3/reglages?fenetre=1");
        [$sal] = $appel($a, 'groupes/' . $salonId($s3, 'général'));
        $dire("$langue : la page, ses boutons, les réglages et le salon sont traduits",
            $oui(str_contains($page, $mots[0]) && str_contains($page, $mots[1]) && str_contains($page, $mots[2])
                && !preg_match('/>\s*(srv|grpevt)\.[a-z_.]+\s*</', $page . $reg . $sal) && !preg_match('/(?:placeholder|title|aria-label)="(srv|grpevt)\.[a-z_.]+"/', $page . $reg . $sal)), 'oui');
    }
    $termine = true;
} finally {
    $nettoyer();
    foreach ($cookies as $f) { @unlink($f); }
    if (!$termine) { $anomalies++; echo "\n   ✗ le script s'est arrêté avant la fin\n"; }
    echo "\n" . ($anomalies === 0 ? 'Aucune anomalie' : $anomalies . ' anomalie(s)')
        . ' · comptes d’essai restants ' . bd_valeur('SELECT COUNT(*) FROM users WHERE email LIKE ?', ['%@exemple-test.fr'])
        . ' · serveurs orphelins ' . bd_valeur('SELECT COUNT(*) FROM serveurs WHERE cree_par IS NULL')
        . ' · salons orphelins ' . bd_valeur('SELECT COUNT(*) FROM conversations WHERE serveur_id IS NOT NULL AND serveur_id NOT IN (SELECT id FROM serveurs)') . "\n";
}
