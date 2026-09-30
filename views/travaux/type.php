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
      <p style="margin:0 0 .3rem"><a href="<?= $retour ?>"><?= e(t('tr.ty.retour_types')) ?></a></p>
    <?php endif; ?>
    <h1><?= $edition ? e((string) $type['icone']) . ' ' . e((string) $type['nom']) : e(t('tr.ty.nouveau_type')) ?></h1>
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
    <button class="bouton" type="submit"><?= e(t($edition ? 'tr.ty.enregistrer' : 'tr.ty.creer')) ?></button>
    <a class="bouton bouton--secondaire" href="<?= $retour ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>><?= e(t('tr.ty.annuler')) ?></a>
  </p>
</form>

<?php if ($edition): ?>
  <form method="post"<?= $envoi ?> action="<?= url('travaux/types/' . (int) $type['id'] . '/supprimer') ?>" style="margin-top:.75rem"
        data-confirmation="<?= e(t('tr.ty.supprimer_confirmation', ['nom' => (string) $type['nom']])) ?>">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('tr.ty.supprimer')) ?></button>
  </form>
<?php endif; ?>
