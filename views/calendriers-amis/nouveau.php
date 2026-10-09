<?php
/**
 * Créer un calendrier partagé : un nom, une couleur, et les amis qui y entrent.
 *
 * @var list<array> $amis  mes amis
 * @var bool $aUnPseudo
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <h1 style="margin:0">👥 <?= e(t('cam.nouveau_titre')) ?></h1>
    <p class="discret" style="margin:.15rem 0 0"><?= e(t('cam.nouveau_sous_titre')) ?></p>
  </div>
</div>

<?php if (!$aUnPseudo): ?>
  <div class="flash flash--info">
    <?= e(t('grp.pseudo_dabord')) ?>
    <a href="<?= url('compte') ?>"><?= e(t('ami.choisir_pseudo')) ?></a>
  </div>
<?php else: ?>
  <form method="post" action="<?= url('calendriers-amis') ?>" class="carte groupe-formulaire"<?= $envoi ?>>
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <div class="champ">
      <label for="cam-nom"><?= e(t('cam.nom')) ?></label>
      <input type="text" id="cam-nom" name="nom" required maxlength="<?= CalendriersAmis::NOM_MAX ?>"
             placeholder="<?= e(t('cam.nom_exemple')) ?>" autocomplete="off">
    </div>
    <div class="champ">
      <span class="legende"><?= e(t('cam.couleur')) ?></span>
      <?= Vue::rendre('calendriers-amis/_couleurs', ['choisie' => null]) ?>
    </div>
    <div class="champ">
      <span class="legende"><?= e(t('cam.amis_choisis')) ?></span>
      <?php if ($amis === []): ?>
        <p class="champ__aide" style="margin:0"><?= e(t('cam.pas_d_amis')) ?></p>
      <?php else: ?>
        <ul class="groupe-choix__liste">
          <?php foreach ($amis as $a): ?>
            <li>
              <label class="groupe-choix__ami">
                <input type="checkbox" name="amis[]" value="<?= (int) $a['id'] ?>">
                <?= Amis::avatar((int) $a['id'], (string) $a['pseudo']) ?>
                <span><?= e((string) $a['pseudo']) ?></span>
              </label>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="champ__aide"><?= e(t('cam.amis_aide')) ?></p>
      <?php endif; ?>
    </div>
    <div class="actions">
      <button class="bouton" type="submit"><?= e(t('cam.creer_bouton')) ?></button>
    </div>
  </form>
<?php endif; ?>
