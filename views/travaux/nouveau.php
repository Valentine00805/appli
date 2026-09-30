<?php
/**
 * Un nouveau travail de groupe, ouvert en fenêtre par-dessus la liste.
 *
 * @var list<array> $amis
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
?>
<div class="entete-page">
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p style="margin:0 0 .3rem"><a href="<?= url('travaux') ?>"><?= e(t('tr.on.retour')) ?></a></p>
    <?php endif; ?>
    <h1><?= e(t('tr.nv.titre')) ?></h1>
  </div>
</div>

<form method="post" action="<?= url('travaux') ?>" class="carte travaux-formulaire"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <div class="champ">
    <label for="nom-projet"><?= e(t('tr.me.nom')) ?></label>
    <input type="text" id="nom-projet" name="nom" required autofocus maxlength="<?= Travaux::NOM_MAX ?>"
           placeholder="<?= e(t('tr.nv.nom_exemple')) ?>">
  </div>
  <div class="champ">
    <label for="description-projet"><?= e(t('tr.nv.sujet')) ?> <span class="discret"><?= e(t('tr.nv.facultatif')) ?></span></label>
    <textarea id="description-projet" name="description" rows="3" maxlength="2000"></textarea>
  </div>
  <div class="champ">
    <span class="legende"><?= e(t('tr.nv.inviter')) ?> <span class="discret"><?= e(t('tr.nv.acceptent')) ?></span></span>
    <?php if ($amis === []): ?>
      <p class="discret" style="margin:.3rem 0 0"><?= e(t('tr.nv.pas_damis')) ?>
        <a href="<?= url('amis') ?>"><?= e(t('tr.me.en_ajouter')) ?></a><?= e(t('tr.nv.pas_damis_suite')) ?></p>
    <?php else: ?>
      <label class="discussions-recherche">
        <span class="sr-only"><?= e(t('tr.me.chercher_ami')) ?></span>
        <input type="search" placeholder="<?= e(t('tr.me.chercher_ami')) ?>" autocomplete="off" data-filtre-liste="[data-liste-amis-projet]">
      </label>
      <ul class="groupe-choix__liste partage-liste" data-liste-amis-projet>
        <?php foreach ($amis as $a): ?>
          <li data-nom="<?= e(mb_strtolower((string) $a['pseudo'])) ?>">
            <label class="groupe-choix__ami">
              <input type="checkbox" name="amis[]" value="<?= (int) $a['id'] ?>">
              <?= Amis::avatar((int) $a['id'], (string) $a['pseudo'], 'avatar--mini') ?>
              <span class="partage-liste__nom"><?= e((string) $a['pseudo']) ?></span>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="discret" data-filtre-vide hidden style="margin:.4rem 0 0"><?= e(t('tr.me.aucun_ami_nom')) ?></p>
    <?php endif; ?>
  </div>
  <p class="actions">
    <button class="bouton" type="submit"><?= e(t('tr.nv.creer')) ?></button>
    <a class="bouton bouton--secondaire" href="<?= url('travaux') ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>><?= e(t('tr.nv.annuler')) ?></a>
  </p>
</form>
