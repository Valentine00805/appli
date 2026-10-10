<?php
/**
 * Le choix de la matière d'un dossier : les cours qu'on y range la reçoivent s'ils n'en ont pas.
 *
 * @var list<array> $matieres  les matières du compte
 * @var ?int $choisie  la matière actuelle du dossier
 * @var string $idChamp
 * @var bool $court  sans l'aide sous le champ (formulaire étroit)
 */
$court = $court ?? false;
?>
<div class="champ">
  <label for="<?= e($idChamp) ?>"><?= e(t('dos.matiere')) ?></label>
  <select id="<?= e($idChamp) ?>" name="matiere_id">
    <option value=""><?= e(t('dos.matiere_aucune')) ?></option>
    <?php foreach ($matieres as $m): ?>
      <option value="<?= (int) $m['id'] ?>"<?= $choisie === (int) $m['id'] ? ' selected' : '' ?>><?= e((string) $m['nom']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if (!$court): ?><span class="champ__aide"><?= e(t('dos.matiere_aide')) ?></span><?php endif; ?>
</div>