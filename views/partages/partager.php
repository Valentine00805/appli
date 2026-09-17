<?php
/**
 * La fenêtre « Partager » d'un cours ou d'un fichier : d'abord à ses amis (et
 * ses groupes), puis par un lien, pour ceux qui n'ont pas de compte.
 *
 * @var string $type    « cours » ou « fichier »
 * @var string $mot     le même, tel qu'il s'écrit dans l'adresse
 * @var array $cible
 * @var list<array> $amis
 * @var list<array> $groupes
 * @var list<array{id: int, pseudo: string, droit: string}> $destinataires
 * @var list<array> $commentaires  ce qu'on a écrit sous ce document
 * @var ?string $lien
 * @var int $vues
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$surPlace = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$csrf = Session::jetonCsrf();
$base = 'partager/' . $mot . '/' . (int) $cible['id'];
$ontAcces = array_flip(array_column($destinataires, 'id'));
$icone = match ($type) {
    'cours' => '📘',
    'fiche' => '📝',
    'dossier' => (string) $cible['icone'],
    default => Fichiers::icone((string) $cible['mime'], (string) $cible['nom_origine']),
};
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <h1 style="margin:0"><?= Partages::icone() ?> Partager</h1>
    <p class="discret" style="margin:.15rem 0 0"><?= e($icone) ?> <?= e((string) $cible['titre']) ?></p>
  </div>
</div>

<section class="carte partage-section">
  <h2 style="margin-top:0">👥 Avec mes amis</h2>
  <?php if ($amis === [] && $groupes === []): ?>
    <p class="discret" style="margin:0">Pas encore d’amis à qui l’envoyer. <a href="<?= url('amis') ?>">Chercher un pseudo</a></p>
  <?php else: ?>
    <form method="post" action="<?= url($base . '/amis') ?>"<?= $surPlace ?>>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <label class="discussions-recherche">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
          <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
        </svg>
        <span class="sr-only">Rechercher un ami ou un groupe</span>
        <input type="search" placeholder="Rechercher" autocomplete="off" data-filtre-liste="[data-liste-partage]">
      </label>
      <ul class="groupe-choix__liste partage-liste" data-liste-partage>
        <?php foreach ($amis as $a): ?>
          <li data-nom="<?= e(mb_strtolower((string) $a['pseudo'])) ?>">
            <label class="groupe-choix__ami">
              <input type="checkbox" name="amis[]" value="<?= (int) $a['id'] ?>">
              <?= Amis::avatar((int) $a['id'], (string) $a['pseudo']) ?>
              <span class="partage-liste__nom"><?= e((string) $a['pseudo']) ?></span>
              <?php if (isset($ontAcces[(int) $a['id']])): ?><span class="pastille">A accès</span><?php endif; ?>
            </label>
          </li>
        <?php endforeach; ?>
        <?php foreach ($groupes as $g): ?>
          <li data-nom="<?= e(mb_strtolower((string) $g['nom'])) ?>">
            <label class="groupe-choix__ami">
              <input type="checkbox" name="groupes[]" value="<?= (int) $g['id'] ?>">
              <?= Conversations::avatar((int) $g['id'], $g['photo_nom'] ?? null) ?>
              <span class="partage-liste__nom"><?= e((string) $g['nom']) ?> <span class="discret">· groupe</span></span>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="discret" data-filtre-vide hidden style="margin:.4rem 0 0">Personne ne correspond.</p>
      <?php // Ce qu'ils pourront en faire : le partage le dit dès l'envoi. ?>
      <fieldset class="champ partage-droits" style="margin-top:.75rem">
        <legend class="legende">Ce qu’ils pourront faire</legend>
        <?php foreach (Partages::DROITS as $rang => $unDroit): ?>
          <?php if ($type === 'fichier' && $unDroit === 'modification') { continue; } ?>
          <label class="partage-droits__choix">
            <input type="radio" name="droit" value="<?= e($unDroit) ?>"<?= $rang === 0 ? ' checked' : '' ?>>
            <span>
              <strong><?= e(Partages::libelleDroit($unDroit)) ?></strong>
              <span class="discret"><?= e(Partages::expliqueDroit($unDroit)) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </fieldset>
      <div class="champ" style="margin-top:.75rem">
        <label for="partage-texte">Message (facultatif)</label>
        <textarea id="partage-texte" name="texte" rows="2" maxlength="<?= Amis::MESSAGE_MAX ?>" placeholder="<?= match ($type) { 'cours' => 'Regarde ce cours…', 'fiche' => 'Regarde ma fiche…', 'dossier' => 'Regarde ce dossier…', default => 'Regarde ce fichier…' } ?>"></textarea>
      </div>
      <button class="bouton" type="submit">Envoyer</button>
      <p class="champ__aide" style="margin-bottom:0">
        Ils le reçoivent dans votre discussion et dans « Partagés avec moi », avec le droit choisi ci-dessus — toujours à jour,
        et ils peuvent en faire une copie.<?= match ($type) {
            'cours' => ' Les fichiers joints du cours sont compris ; pas la fiche de révision.',
            'fiche' => ' Les fichiers et les liens de la fiche sont compris ; pas le contenu du cours.',
            'dossier' => ' Tous les cours du dossier et de ses sous-dossiers sont compris, avec leurs fichiers joints ; pas leurs fiches de révision. Ce que vous y rangerez ensuite sera partagé aussi.',
            default => '',
        } ?>
      </p>
    </form>
  <?php endif; ?>

  <?php if ($destinataires !== []): ?>
    <h3 class="groupe-sous-titre">Ont accès</h3>
    <ul class="groupe-membres">
      <?php foreach ($destinataires as $d): ?>
        <li class="groupe-membres__ligne">
          <?= Amis::avatar($d['id'], $d['pseudo']) ?>
          <span class="groupe-membres__nom"><?= e($d['pseudo']) ?></span>
          <?php // Le droit se change sur place : la liste l'envoie d'elle-même. ?>
          <form method="post" action="<?= url($base . '/acces/' . $d['id'] . '/droit') ?>"<?= $surPlace ?> class="en-ligne">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <label class="sr-only" for="droit-<?= (int) $d['id'] ?>">Ce que <?= e($d['pseudo']) ?> peut faire</label>
            <select id="droit-<?= (int) $d['id'] ?>" name="droit">
              <?php foreach (Partages::DROITS as $unDroit): ?>
                <?php if ($type === 'fichier' && $unDroit === 'modification') { continue; } ?>
                <option value="<?= e($unDroit) ?>"<?= $d['droit'] === $unDroit ? ' selected' : '' ?>><?= e(Partages::libelleDroit($unDroit)) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="bouton bouton--discret bouton--petit" type="submit">Changer</button>
          </form>
          <form method="post" action="<?= url($base . '/acces/' . $d['id'] . '/retirer') ?>"<?= $surPlace ?>>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button class="bouton bouton--discret bouton--petit" type="submit">Retirer l’accès</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php if ($commentaires !== []): ?>
  <?php // Ce qu'on a écrit sous ce document : ici, faute d'y être chez soi. ?>
  <section class="carte partage-section">
    <h2 style="margin-top:0">💬 Commentaires <span class="discret">(<?= count($commentaires) ?>)</span></h2>
    <ul class="partage-commentaires">
      <?php foreach ($commentaires as $c): ?>
        <li>
          <div class="partage-commentaires__qui">
            <?= Amis::avatar((int) $c['user_id'], (string) $c['pseudo'], 'avatar--mini') ?>
            <strong><?= e((string) $c['pseudo']) ?></strong>
            <span class="discret"><?= e(date_fr(Amis::local((string) $c['created_at'])->format('Y-m-d H:i:s'))) ?></span>
            <form method="post" action="<?= url('partages/commentaires/' . (int) $c['id'] . '/retirer') ?>"<?= $surPlace ?>
                  data-confirmation="Retirer ce commentaire ?">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--discret bouton--petit" type="submit">Retirer</button>
            </form>
          </div>
          <p class="partage-commentaires__texte"><?= nl2br(e((string) $c['texte'])) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
    <form method="post" action="<?= url($base . '/commentaires') ?>"<?= $surPlace ?>>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <div class="champ">
        <label class="sr-only" for="partage-reponse">Votre commentaire</label>
        <textarea id="partage-reponse" name="texte" rows="2" maxlength="<?= Amis::MESSAGE_MAX ?>"
                  placeholder="Répondre…"></textarea>
      </div>
      <button class="bouton bouton--petit" type="submit">Commenter</button>
    </form>
  </section>
<?php endif; ?>

<section class="carte partage-section">
  <h2 style="margin-top:0">🔗 Avec un lien</h2>
  <p class="discret" style="margin-top:0">Pour ceux qui n’ont pas de compte : toute personne qui a le lien peut voir et télécharger ce document.</p>
  <?php if ($lien === null): ?>
    <form method="post" action="<?= url($base . '/lien') ?>"<?= $surPlace ?>>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <button class="bouton bouton--secondaire" type="submit">Créer un lien de partage</button>
    </form>
  <?php else: ?>
    <div class="partage-lien" data-partage-lien>
      <label class="sr-only" for="partage-lien-adresse">Lien de partage</label>
      <input type="text" id="partage-lien-adresse" readonly value="<?= e($lien) ?>" data-partage-adresse>
      <button class="bouton" type="button" data-partage-copier>Copier</button>
      <button class="bouton bouton--secondaire" type="button" data-partage-natif hidden
              data-titre="<?= e((string) $cible['titre']) ?>"><?= Partages::icone() ?> Envoyer…</button>
    </div>
    <p class="champ__aide" data-partage-etat aria-live="polite">Ouvert <?= $vues ?> fois.</p>
    <form method="post" action="<?= url($base . '/lien/desactiver') ?>"<?= $surPlace ?>>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <button class="bouton bouton--discret bouton--petit" type="submit">Désactiver le lien</button>
    </form>
  <?php endif; ?>
</section>
