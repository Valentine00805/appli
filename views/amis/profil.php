<?php
/**
 * Le profil d'un ami, ouvert depuis la discussion : ce que vous avez échangé
 * (photos, fichiers) et, tout en bas, de quoi le retirer de vos amis.
 *
 * Retirer, comme bloquer, demande une confirmation : le bouton ouvre une
 * fenêtre par-dessus le profil, et c'est un second bouton — nommé, sans
 * ambiguïté — qui agit vraiment. Comme les autres fenêtres de l'application,
 * elle ne se ferme que par sa croix ou par « Annuler ».
 *
 * @var array{id: int, pseudo: string} $ami
 * @var ?string $amisDepuis
 * @var list<array> $photos
 * @var list<array> $fichiersPartages
 * @var int $messages
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$pseudo = (string) $ami['pseudo'];
?>

<div class="entete-page profil-ami"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div class="profil-ami__identite">
    <span class="avatar avatar--grand" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($pseudo, 0, 1))) ?></span>
    <div>
      <?php if (!$dansUneFenetre): ?>
        <p class="discret" style="margin:0 0 .2rem"><a href="<?= url('amis/' . (int) $ami['id']) ?>">← Retour à la discussion</a></p>
      <?php endif; ?>
      <h1 style="margin:0"><?= e($pseudo) ?></h1>
      <p class="discret" style="margin:.15rem 0 0">
        <?php if ($amisDepuis !== null): ?>Amis depuis le <?= e($amisDepuis) ?> · <?php endif; ?>
        <?= $messages ?> message<?= $messages > 1 ? 's' : '' ?> échangé<?= $messages > 1 ? 's' : '' ?>
      </p>
    </div>
  </div>
</div>

<section class="carte profil-ami__section">
  <h2 style="margin-top:0">📷 Photos <span class="discret profil-ami__nombre"><?= count($photos) ?></span></h2>
  <?php if ($photos === []): ?>
    <p class="discret" style="margin:0">Aucune photo échangée pour l’instant.</p>
  <?php else: ?>
    <div class="profil-ami__photos">
      <?php foreach ($photos as $p): ?>
        <a class="profil-ami__photo" href="<?= e($p['url']) ?>" target="_blank" rel="noopener" data-visionneuse
           title="<?= e(($p['moi'] ? 'Envoyée par vous' : 'Envoyée par ' . $pseudo) . ' · ' . $p['date']) ?>">
          <img src="<?= e($p['url']) ?>" alt="Photo du <?= e($p['date']) ?>" loading="lazy">
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="carte profil-ami__section">
  <h2 style="margin-top:0">📎 Fichiers <span class="discret profil-ami__nombre"><?= count($fichiersPartages) ?></span></h2>
  <?php if ($fichiersPartages === []): ?>
    <p class="discret" style="margin:0">Aucun fichier échangé pour l’instant.</p>
  <?php else: ?>
    <ul class="profil-ami__fichiers">
      <?php foreach ($fichiersPartages as $f): ?>
        <li class="profil-ami__fichier">
          <span class="bulle__fichier-icone" aria-hidden="true"><?= e($f['icone']) ?></span>
          <span class="profil-ami__fichier-infos">
            <a class="bulle__fichier-nom" href="<?= e($f['url']) ?>" target="_blank" rel="noopener"><?= e($f['nom']) ?></a>
            <span class="discret" style="font-size:.78rem">
              <?= e($f['taille']) ?> · <?= $f['moi'] ? 'envoyé par vous' : 'envoyé par ' . e($pseudo) ?> · <?= e($f['date']) ?>
            </span>
          </span>
          <a class="bouton bouton--secondaire bouton--petit" href="<?= e($f['telecharger']) ?>"
             aria-label="Télécharger <?= e($f['nom']) ?>" title="Télécharger">⬇</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="carte profil-ami__section profil-ami__danger">
  <button class="bouton bouton--danger" type="button" data-ouvrir-dialogue="confirmer-retrait-ami">Retirer <?= e($pseudo) ?> de mes amis</button>
  <button class="bouton bouton--danger" type="button" data-ouvrir-dialogue="confirmer-blocage-ami">🚫 Bloquer <?= e($pseudo) ?></button>
</section>

<dialog class="confirmation" id="confirmer-retrait-ami" data-confirmation-dialogue aria-labelledby="titre-retrait-ami">
  <button class="fenetre__fermer" type="button" data-fermer-dialogue aria-label="Fermer">✕</button>
  <h2 class="confirmation__titre" id="titre-retrait-ami">Retirer <?= e($pseudo) ?> de vos amis ?</h2>
  <p class="confirmation__texte">
    Vous ne pourrez plus vous écrire, ni voir les photos et fichiers échangés.
    Vos messages sont gardés : ils reviendront si vous redevenez amis.
  </p>
  <form method="post" action="<?= url('amis/' . (int) $ami['id'] . '/retirer') ?>" class="confirmation__choix">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <button class="bouton bouton--danger" type="submit">Oui, retirer <?= e($pseudo) ?></button>
    <button class="bouton bouton--discret" type="button" data-fermer-dialogue>Annuler</button>
  </form>
</dialog>

<dialog class="confirmation" id="confirmer-blocage-ami" data-confirmation-dialogue aria-labelledby="titre-blocage-ami">
  <button class="fenetre__fermer" type="button" data-fermer-dialogue aria-label="Fermer">✕</button>
  <h2 class="confirmation__titre" id="titre-blocage-ami">🚫 Bloquer <?= e($pseudo) ?> ?</h2>
  <p class="confirmation__texte">
    Vous ne serez plus amis. <?= e($pseudo) ?> ne pourra plus vous trouver par votre pseudo, ni vous écrire,
    ni vous redemander en ami — sans qu’on le lui dise. Vous pourrez le débloquer depuis la page « Amis ».
  </p>
  <form method="post" action="<?= url('amis/' . (int) $ami['id'] . '/bloquer') ?>" class="confirmation__choix">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <button class="bouton bouton--danger" type="submit">Oui, bloquer <?= e($pseudo) ?></button>
    <button class="bouton bouton--discret" type="button" data-fermer-dialogue>Annuler</button>
  </form>
</dialog>
