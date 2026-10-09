<?php
/**
 * Le choix de la couleur d'un calendrier partagé : une pastille, ou une couleur libre. Mêmes pastilles que celles du logo d'un
 * serveur (le script du formulaire fait cocher « couleur libre » dès qu'on y touche).
 *
 * @var ?string $choisie  la couleur actuelle (#rrggbb), ou null : la première pastille
 */
$choisie = Serveurs::couleurValide($choisie ?? null) ?? CalendriersAmis::COULEUR_DEFAUT;
$estPastille = in_array($choisie, CalendriersAmis::COULEURS, true);
?>
<fieldset class="serveur-couleurs cam-couleurs" data-couleurs data-automatique="<?= e($choisie) ?>">
  <legend class="sr-only"><?= e(t('cam.couleur')) ?></legend>
  <div class="serveur-couleurs__liste">
    <?php foreach (CalendriersAmis::COULEURS as $c): ?>
      <label class="serveur-couleurs__choix" title="<?= e($c) ?>">
        <input type="radio" name="couleur" value="<?= e($c) ?>"<?= $choisie === $c ? ' checked' : '' ?>>
        <span style="background: <?= e($c) ?>" aria-hidden="true"></span>
        <span class="sr-only"><?= e($c) ?></span>
      </label>
    <?php endforeach; ?>
    <?php // Une couleur libre : un anneau en arc-en-ciel, et le nuancier du navigateur au clic. ?>
    <label class="serveur-couleurs__choix serveur-couleurs__choix--libre cam-libre" title="<?= e(t('cam.couleur_libre')) ?>">
      <input type="radio" name="couleur" value="perso"<?= $estPastille ? '' : ' checked' ?>>
      <span class="cam-libre__anneau"><input type="color" name="couleur_perso" value="<?= e($choisie) ?>" aria-label="<?= e(t('cam.couleur_libre')) ?>"></span>
      <span class="cam-libre__texte"><?= e(t('cam.autre_couleur')) ?></span>
    </label>
  </div>
</fieldset>
