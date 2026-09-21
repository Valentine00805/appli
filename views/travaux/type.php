<?php
/**
 * Un type d'échéance, nouveau ou à modifier, ouvert en fenêtre.
 *
 * @var array $projet
 * @var ?array $type
 * @var list<string> $palette
 * @var list<string> $icones
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$edition = $type !== null;
$retour = url('travaux/' . (int) $projet['id'] . '/types');
?>
<div class="entete-page" data-large>
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p style="margin:0 0 .3rem"><a href="<?= $retour ?>">← Types d’échéance</a></p>
    <?php endif; ?>
    <h1><?= $edition ? e((string) $type['icone']) . ' ' . e((string) $type['nom']) : '🏷️ Nouveau type' ?></h1>
    <p><?= e((string) $projet['nom']) ?></p>
  </div>
</div>

<form method="post"<?= $envoi ?> class="carte travaux-formulaire"
      action="<?= $edition ? url('travaux/types/' . (int) $type['id'] . '/modifier') : url('travaux/' . (int) $projet['id'] . '/types') ?>">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <?= Vue::rendre('travaux/_champs_type', [
      'suffixe' => $edition ? (string) (int) $type['id'] : 'nouveau',
      't' => $type, 'palette' => $palette, 'icones' => $icones,
  ]) ?>
  <p class="actions">
    <button class="bouton" type="submit"><?= $edition ? 'Enregistrer' : 'Créer le type' ?></button>
    <a class="bouton bouton--secondaire" href="<?= $retour ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>>Annuler</a>
  </p>
</form>

<?php if ($edition): ?>
  <form method="post"<?= $envoi ?> action="<?= url('travaux/types/' . (int) $type['id'] . '/supprimer') ?>" style="margin-top:.75rem"
        data-confirmation="Supprimer le type « <?= e((string) $type['nom']) ?> » ? Ses échéances restent, sans type.">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <button class="bouton bouton--danger bouton--petit" type="submit">Supprimer ce type</button>
  </form>
<?php endif; ?>
