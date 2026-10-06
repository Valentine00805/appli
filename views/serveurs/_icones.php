<?php
/**
 * Le choix de l'icône d'un serveur : quelques pictogrammes, un seul retenu.
 *
 * @var string $choisie
 * @var string $idPrefixe  distingue les champs si la page en porte plusieurs
 */
?>
<fieldset class="serveur-icones">
  <legend class="legende"><?= e(t('srv.icone')) ?></legend>
  <div class="serveur-icones__liste">
    <?php foreach (Serveurs::ICONES as $i => $icone): ?>
      <label class="serveur-icones__choix">
        <input type="radio" name="icone" value="<?= e($icone) ?>" id="icone-<?= e($idPrefixe) ?>-<?= $i ?>"<?= $icone === $choisie ? ' checked' : '' ?>>
        <span aria-hidden="true"><?= e($icone) ?></span>
        <span class="sr-only"><?= e($icone) ?></span>
      </label>
    <?php endforeach; ?>
  </div>
</fieldset>
