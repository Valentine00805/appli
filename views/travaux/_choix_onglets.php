<?php
/**
 * Le choix des onglets d'un projet : « Qui fait quoi » et « Membres » sont toujours là, les autres se cochent. Sert à la création du projet et,
 * ensuite, aux réglages du groupe (l'administrateur).
 *
 * @var list<string> $proposes  les onglets au choix de ce site
 * @var list<string> $actifs    ceux qui sont cochés
 * @var string $suffixe         pour des identifiants uniques
 */
$suffixe = $suffixe ?? 'projet';
$icones = ['taches' => '✅', 'echeances' => '📅', 'fichiers' => '📎', 'cours' => '📘', 'document' => '📝', 'livrables' => '📤', 'listes' => '☑️', 'membres' => '👥'];
$noms = ['taches' => 'tr.on.qui_fait_quoi', 'echeances' => 'tr.on.echeances', 'fichiers' => 'tr.on.fichiers', 'cours' => 'tr.on.cours', 'document' => 'tr.on.document',
         'livrables' => 'tr.on.livrables', 'listes' => 'tr.on.listes', 'membres' => 'tr.on.membres'];
$ligne = static function (string $cle, bool $fixe, bool $coche) use ($icones, $noms, $suffixe): string {
    ob_start(); ?>
    <li>
      <label class="choix-onglet<?= $fixe ? ' choix-onglet--fixe' : '' ?>" for="onglet-<?= e($suffixe) ?>-<?= e($cle) ?>">
        <input type="checkbox" id="onglet-<?= e($suffixe) ?>-<?= e($cle) ?>"<?= $fixe ? ' checked disabled' : ' name="onglets[]" value="' . e($cle) . '"' . ($coche ? ' checked' : '') ?>>
        <span class="choix-onglet__icone" aria-hidden="true"><?= $icones[$cle] ?></span>
        <span class="choix-onglet__texte"><strong><?= e(t($noms[$cle])) ?></strong><br><span class="discret"><?= e(t('tr.ot.desc.' . $cle)) ?></span></span>
        <?php if ($fixe): ?><span class="pastille pastille--muette"><?= e(t('tr.ot.toujours')) ?></span><?php endif; ?>
      </label>
    </li>
    <?php return (string) ob_get_clean();
};
?>
<fieldset class="choix-onglets">
  <legend><?= e(t('tr.ot.titre')) ?></legend>
  <p class="champ__aide" style="margin:0 0 .6rem"><?= e(t('tr.ot.aide')) ?></p>
  <?php // Rien de coché est un choix aussi : ce champ dit que le choix a été fait. ?>
  <input type="hidden" name="onglets_choisis" value="1">
  <ul class="choix-onglets__liste">
    <?= $ligne('taches', true, true) ?>
    <?php foreach ($proposes as $cle): ?>
      <?= $ligne($cle, false, in_array($cle, $actifs, true)) ?>
    <?php endforeach; ?>
    <?= $ligne('membres', true, true) ?>
  </ul>
</fieldset>
