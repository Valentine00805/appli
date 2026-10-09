<?php
/**
 * Mes calendriers partagés : ceux que j'ai créés, ceux où l'on m'a fait entrer.
 *
 * @var list<array<string, mixed>> $calendriers
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <h1 style="margin:0">👥 <?= e(t('cam.titre')) ?></h1>
    <p class="discret" style="margin:.15rem 0 0"><?= e(t('cam.sous_titre')) ?></p>
  </div>
  <div class="actions">
    <a class="bouton" href="<?= url('calendriers-amis/nouveau') ?>" data-fenetre>＋ <?= e(t('cam.nouveau')) ?></a>
  </div>
</div>

<?php if ($calendriers === []): ?>
  <section class="carte">
    <p class="discret" style="margin:0"><?= e(t('cam.aucun')) ?></p>
  </section>
<?php else: ?>
  <ul class="cam-liste">
    <?php foreach ($calendriers as $c): ?>
      <li class="carte cam-liste__ligne">
        <span class="cam-pastille" style="background:<?= e($c['couleur']) ?>" aria-hidden="true"></span>
        <span class="cam-liste__texte">
          <strong><?= e($c['nom']) ?></strong>
          <span class="discret">
            <?= e(tn('cam.membres_n', $c['membres'])) ?>
            · <?= e($c['est_proprietaire'] ? t('cam.cree_par_moi') : t('cam.cree_par', ['qui' => $c['proprietaire_pseudo']])) ?>
            <?php if (!$c['affiche']): ?>· <?= e(t('cam.masque')) ?><?php endif; ?>
          </span>
        </span>
        <span class="actions">
          <a class="bouton bouton--secondaire bouton--petit" href="<?= url('evenements/nouveau', ['agenda' => CalendriersAmis::cle($c['id'])]) ?>" data-fenetre>＋ <?= e(t('cam.evt_ajouter')) ?></a>
          <a class="bouton bouton--discret bouton--petit" href="<?= url('calendriers-amis/' . $c['id']) ?>" data-fenetre><?= e($c['est_proprietaire'] ? t('cam.gerer') : t('cam.ouvrir')) ?></a>
        </span>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
