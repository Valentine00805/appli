<?php
/**
 * Le choix de la couleur du fond des initiales : « automatique » (une couleur déduite du nom), des pastilles, ou une couleur libre.
 * Le script de l'aperçu (app.js, [data-couleurs]) colore le logo d'essai à chaque choix.
 *
 * @var ?string $choisie  la couleur actuelle (#rrggbb), ou null : automatique
 * @var string $automatique  la couleur que donne le nom, pour l'aperçu
 */
$choisie = Serveurs::couleurValide($choisie ?? null);
$estPastille = $choisie !== null && in_array($choisie, Serveurs::COULEURS, true);
$estLibre = $choisie !== null && !$estPastille;
?>
<fieldset class="serveur-couleurs" data-couleurs data-automatique="<?= e($automatique) ?>">
  <legend class="sr-only"><?= e(t('srv.couleur')) ?></legend>
  <div class="serveur-couleurs__liste">
    <label class="serveur-couleurs__choix serveur-couleurs__choix--auto" title="<?= e(t('srv.couleur_auto')) ?>">
      <input type="radio" name="couleur" value=""<?= $choisie === null ? ' checked' : '' ?>>
      <span style="background: <?= e($automatique) ?>" aria-hidden="true">A</span>
      <span class="sr-only"><?= e(t('srv.couleur_auto')) ?></span>
    </label>
    <?php foreach (Serveurs::COULEURS as $c): ?>
      <label class="serveur-couleurs__choix" title="<?= e($c) ?>">
        <input type="radio" name="couleur" value="<?= e($c) ?>"<?= $choisie === $c ? ' checked' : '' ?>>
        <span style="background: <?= e($c) ?>" aria-hidden="true"></span>
        <span class="sr-only"><?= e($c) ?></span>
      </label>
    <?php endforeach; ?>
    <label class="serveur-couleurs__choix serveur-couleurs__choix--libre" title="<?= e(t('srv.couleur_perso')) ?>">
      <input type="radio" name="couleur" value="perso"<?= $estLibre ? ' checked' : '' ?>>
      <input type="color" name="couleur_perso" value="<?= e($estLibre ? $choisie : '#5865f2') ?>" aria-label="<?= e(t('srv.couleur_perso')) ?>">
    </label>
  </div>
  <p class="champ__aide"><?= e(t('srv.couleur_aide')) ?></p>
</fieldset>