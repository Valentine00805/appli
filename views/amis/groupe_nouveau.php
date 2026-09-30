<?php
/**
 * Créer un groupe : un nom, et les amis à y inviter.
 *
 * @var list<array> $amis
 * @var bool $aUnPseudo
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <h1 style="margin:0"><?= e(t('grp.nouveau_titre')) ?></h1>
    <p class="discret" style="margin:.15rem 0 0"><?= e(t('grp.sous_titre')) ?></p>
  </div>
</div>

<?php if (!$aUnPseudo): ?>
  <div class="flash flash--info">
    <?= e(t('grp.pseudo_dabord')) ?>
    <a href="<?= url('compte') ?>"><?= e(t('ami.choisir_pseudo')) ?></a>
  </div>
<?php elseif ($amis === []): ?>
  <p class="discret"><?= e(t('grp.un_ami_minimum')) ?></p>
<?php else: ?>
  <form method="post" action="<?= url('groupes') ?>" class="carte groupe-formulaire">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <div class="champ">
      <label for="groupe-nom"><?= e(t('grp.nom')) ?></label>
      <input type="text" id="groupe-nom" name="nom" required maxlength="<?= Conversations::NOM_MAX ?>"
             placeholder="<?= e(t('grp.nom_exemple')) ?>" autocomplete="off">
    </div>
    <fieldset class="groupe-choix">
      <legend class="legende"><?= e(t('grp.amis_a_ajouter')) ?></legend>
      <ul class="groupe-choix__liste">
        <?php foreach ($amis as $a): ?>
          <li>
            <label class="groupe-choix__ami">
              <input type="checkbox" name="membres[]" value="<?= (int) $a['id'] ?>">
              <?= Amis::avatar((int) $a['id'], (string) $a['pseudo']) ?>
              <span><?= e((string) $a['pseudo']) ?></span>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="champ__aide"><?= e(t('grp.au_moins_un', ['max' => Conversations::MEMBRES_MAX - 1])) ?></p>
    </fieldset>
    <div class="actions">
      <button class="bouton" type="submit"><?= e(t('grp.creer')) ?></button>
    </div>
  </form>
<?php endif; ?>
