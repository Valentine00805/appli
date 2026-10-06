<?php
/**
 * Créer un serveur : un nom et une icône. Il naît avec un salon « général » ; on y invite ensuite des amis.
 *
 * @var bool $aUnPseudo
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <h1 style="margin:0">🏰 <?= e(t('srv.nouveau_titre')) ?></h1>
    <p class="discret" style="margin:.15rem 0 0"><?= e(t('srv.nouveau_sous_titre')) ?></p>
  </div>
</div>

<?php if (!$aUnPseudo): ?>
  <div class="flash flash--info">
    <?= e(t('grp.pseudo_dabord')) ?>
    <a href="<?= url('compte') ?>"><?= e(t('ami.choisir_pseudo')) ?></a>
  </div>
<?php else: ?>
  <form method="post" action="<?= url('serveurs') ?>" class="carte groupe-formulaire">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <div class="champ">
      <label for="serveur-nom"><?= e(t('srv.nom')) ?></label>
      <input type="text" id="serveur-nom" name="nom" required maxlength="<?= Serveurs::NOM_MAX ?>"
             placeholder="<?= e(t('srv.nom_exemple')) ?>" autocomplete="off">
    </div>
    <?= Vue::rendre('serveurs/_icones', ['choisie' => Serveurs::ICONES[0], 'idPrefixe' => 'nouveau']) ?>
    <p class="champ__aide"><?= e(t('srv.nouveau_aide', ['salon' => Serveurs::SALON_PAR_DEFAUT])) ?></p>
    <div class="actions">
      <button class="bouton" type="submit"><?= e(t('srv.creer_bouton')) ?></button>
    </div>
  </form>
<?php endif; ?>
