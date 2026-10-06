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
  <form method="post" action="<?= url('serveurs') ?>" enctype="multipart/form-data" class="carte groupe-formulaire">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <div class="champ">
      <label for="serveur-nom"><?= e(t('srv.nom')) ?></label>
      <input type="text" id="serveur-nom" name="nom" required data-serveur-nom maxlength="<?= Serveurs::NOM_MAX ?>"
             placeholder="<?= e(t('srv.nom_exemple')) ?>" autocomplete="off">
    </div>
    <?php // Le logo, facultatif : choisi, il s'essaie dans l'aperçu ; sinon les initiales du nom en tiennent lieu. ?>
    <div class="champ" data-photo-carte>
      <span class="legende"><?= e(t('srv.logo_creation')) ?></span>
      <div class="photo-groupe">
        <span class="photo-groupe__apercu" data-photo-apercu>
          <span class="avatar avatar--apercu serveur-apercu serveur-logo serveur-logo--initiales serveur-logo--n2" style="--couleur: var(--accent)" aria-hidden="true" data-serveur-initiales>Ab</span>
        </span>
        <div class="fond-reglage__infos">
          <div class="fond-reglage__choix" data-photo-formulaire>
            <input type="file" name="photo" id="serveur-photo-creation" class="sr-only"
                   accept="image/jpeg,image/png,image/gif,image/webp" data-photo-fichier>
            <label class="bouton bouton--secondaire" for="serveur-photo-creation">📷 <?= e(t('srv.logo_choisir')) ?></label>
            <span class="fond-reglage__nouveau" data-photo-nouveau hidden>
              <button class="bouton bouton--discret" type="button" data-photo-annuler><?= e(t('srv.logo_annuler')) ?></button>
            </span>
          </div>
          <p class="champ__aide" style="margin-bottom:0"><?= e(t('srv.logo_creation_aide', ['mo' => intdiv(Amis::IMAGE_MAX_OCTETS, 1024 * 1024)])) ?></p>
        </div>
      </div>
    </div>
    <p class="champ__aide"><?= e(t('srv.nouveau_aide', ['salon' => Serveurs::SALON_PAR_DEFAUT])) ?></p>
    <div class="actions">
      <button class="bouton" type="submit"><?= e(t('srv.creer_bouton')) ?></button>
    </div>
  </form>
<?php endif; ?>
