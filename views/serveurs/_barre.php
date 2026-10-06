<?php
/**
 * La barre des serveurs, à gauche de la messagerie, façon Discord : un bouton « Messages », mes serveurs en icônes (le serveur
 * ouvert est marqué, un point signale ce qui n'est pas lu, une pastille rouge le compte), « ＋ » pour en créer un, et l'accès à
 * la liste de mes serveurs et de mes invitations.
 *
 * @var string|int $barreActive  « messages », « serveurs » ou l'identifiant du serveur ouvert
 */
$moiBarre = Auth::id();
$barreActive = $barreActive ?? 'messages';
$serveursBarre = Serveurs::liste($moiBarre);
$invitationsBarre = Serveurs::nombreInvitations($moiBarre);
// Les messages d'une personne ou d'un groupe qu'on n'a pas lus : le bouton « Messages » les compte, comme Discord.
$nonLusMessages = Amis::enAttente($moiBarre) - $invitationsBarre;
foreach ($serveursBarre as $sb) { $nonLusMessages -= $sb['non_lus']; }
?>
<nav class="barre-serveurs" aria-label="<?= e(t('srv.barre')) ?>">
  <a class="barre-serveurs__bouton barre-serveurs__bouton--messages<?= $barreActive === 'messages' ? ' barre-serveurs__bouton--actif' : '' ?>"
     href="<?= url('amis') ?>" title="<?= e(t('srv.barre_messages')) ?>" aria-label="<?= e(t('srv.barre_messages')) ?>"<?= $barreActive === 'messages' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">💬</span>
    <?php if ($nonLusMessages > 0): ?><span class="barre-serveurs__pastille"><?= $nonLusMessages > 99 ? '99+' : $nonLusMessages ?></span><?php endif; ?>
  </a>
  <span class="barre-serveurs__separateur" aria-hidden="true"></span>

  <?php foreach ($serveursBarre as $sb): ?>
    <?php $ouvert = (string) $barreActive === (string) $sb['id']; ?>
    <a class="barre-serveurs__bouton<?= $ouvert ? ' barre-serveurs__bouton--actif' : '' ?><?= !$ouvert && $sb['non_lus'] > 0 ? ' barre-serveurs__bouton--non-lu' : '' ?>"
       href="<?= url('serveurs/' . $sb['id']) ?>" title="<?= e($sb['nom']) ?>" aria-label="<?= e($sb['nom']) ?>"<?= $ouvert ? ' aria-current="page"' : '' ?>>
      <span class="barre-serveurs__visuel" aria-hidden="true"><?= Serveurs::visuel($sb['icone'], $sb['photo']) ?></span>
      <?php if ($sb['non_lus'] > 0 && !$ouvert): ?><span class="barre-serveurs__pastille"><?= $sb['non_lus'] > 99 ? '99+' : $sb['non_lus'] ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>

  <a class="barre-serveurs__bouton barre-serveurs__bouton--ajout" href="<?= url('serveurs/nouveau') ?>" data-fenetre
     title="<?= e(t('srv.creer')) ?>" aria-label="<?= e(t('srv.creer')) ?>"><span aria-hidden="true">＋</span></a>
  <a class="barre-serveurs__bouton barre-serveurs__bouton--liste<?= $barreActive === 'serveurs' ? ' barre-serveurs__bouton--actif' : '' ?>"
     href="<?= url('serveurs') ?>" title="<?= e(t('srv.barre_liste')) ?>" aria-label="<?= e(t('srv.barre_liste')) ?>"<?= $barreActive === 'serveurs' ? ' aria-current="page"' : '' ?>>
    <span aria-hidden="true">🧭</span>
    <?php if ($invitationsBarre > 0): ?><span class="barre-serveurs__pastille"><?= $invitationsBarre ?></span><?php endif; ?>
  </a>
</nav>
