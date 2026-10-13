<?php
/**
 * Un nouveau livrable, ouvert en fenêtre par-dessus la liste des livrables.
 *
 * @var array $projet
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$retour = url('travaux/' . (int) $projet['id'] . '/livrables');
?>
<div class="entete-page">
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p style="margin:0 0 .3rem"><a href="<?= $retour ?>">← <?= e((string) $projet['nom']) ?></a></p>
    <?php endif; ?>
    <h1><?= e(t('tr.lv.nouveau_titre')) ?></h1>
    <p><?= e((string) $projet['nom']) ?></p>
  </div>
</div>

<form method="post" action="<?= url('travaux/' . (int) $projet['id'] . '/livrables') ?>" class="carte travaux-formulaire"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <div class="champ">
    <label for="titre-livrable"><?= e(t('tr.lv.titre_label')) ?></label>
    <input type="text" id="titre-livrable" name="titre" required maxlength="<?= Travaux::LIVRABLE_TITRE_MAX ?>" autofocus placeholder="<?= e(t('tr.lv.titre_exemple')) ?>" autocomplete="off">
  </div>
  <div class="ligne-champs">
    <div class="champ">
      <label for="date-livrable"><?= e(t('tr.lv.date_label')) ?> <span class="discret"><?= e(t('tr.ec.facultatif')) ?></span></label>
      <input type="date" id="date-livrable" name="echeance">
    </div>
    <div class="champ">
      <label for="note-livrable"><?= e(t('tr.lv.note_label')) ?> <span class="discret"><?= e(t('tr.ec.facultatif')) ?></span></label>
      <input type="text" id="note-livrable" name="note" maxlength="<?= Travaux::LIVRABLE_NOTE_MAX ?>" placeholder="<?= e(t('tr.lv.note_exemple')) ?>" autocomplete="off">
    </div>
  </div>
  <p class="actions">
    <button class="bouton" type="submit"><?= e(t('tr.lv.creer')) ?></button>
    <a class="bouton bouton--secondaire" href="<?= $retour ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>><?= e(t('tr.ty.annuler')) ?></a>
  </p>
</form>
