<?php
/**
 * Modifier une tâche du groupe, dans une fenêtre par-dessus le tableau.
 *
 * @var array $projet
 * @var array $tache
 * @var list<array> $membres  ceux à qui l'on peut la confier
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$envoi = $dansUneFenetre ? ' data-envoi-fenetre data-fermer-apres' : '';
$id = (int) $tache['id'];
$retour = url('travaux/' . (int) $projet['id']);
$choisi = $tache['membre_id'] === null ? null : (int) $tache['membre_id'];
?>
<div class="entete-page" data-large>
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p style="margin:0 0 .3rem"><a href="<?= $retour ?>">← <?= e((string) $projet['nom']) ?></a></p>
    <?php endif; ?>
    <h1>✎ <?= e((string) $tache['titre']) ?></h1>
    <p><?= e((string) $projet['nom']) ?></p>
  </div>
</div>

<form method="post"<?= $envoi ?> action="<?= url('travaux/taches/' . $id . '/modifier') ?>" class="carte travaux-formulaire">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <div class="champ">
    <label for="titre-tache"><?= e(t('tr.tc.tache')) ?></label>
    <input type="text" id="titre-tache" name="titre" required maxlength="200" value="<?= e((string) $tache['titre']) ?>">
  </div>
  <div class="ligne-champs">
    <div class="champ">
      <label for="membre-tache"><?= e(t('tr.tc.qui')) ?></label>
      <select id="membre-tache" name="membre_id">
        <option value=""><?= e(t('tr.tc.personne')) ?></option>
        <?php foreach ($membres as $m): ?>
          <option value="<?= (int) $m['id'] ?>"<?= (int) $m['id'] === $choisi ? ' selected' : '' ?>><?= e((string) $m['nom_affiche']) ?><?= (int) $m['id'] === (int) $projet['mon_membre_id'] ? e(t('tr.tc.moi')) : '' ?><?= $m['user_id'] === null ? e(t('tr.tc.sans_compte')) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="champ">
      <label for="echeance-tache"><?= e(t('tr.tc.pour_le')) ?> <span class="discret"><?= e(t('tr.ec.facultatif')) ?></span></label>
      <input type="date" id="echeance-tache" name="echeance" value="<?= e((string) ($tache['echeance'] ?? '')) ?>">
    </div>
  </div>
  <div class="champ">
    <label for="note-tache"><?= e(t('tr.tc.precisions')) ?> <span class="discret"><?= e(t('tr.ec.facultatif')) ?></span></label>
    <textarea id="note-tache" name="note" rows="3" maxlength="2000"><?= e((string) ($tache['note'] ?? '')) ?></textarea>
  </div>
  <p class="actions">
    <button class="bouton" type="submit"><?= e(t('tr.tc.enregistrer')) ?></button>
    <a class="bouton bouton--secondaire" href="<?= $retour ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>><?= e(t('tr.ty.annuler')) ?></a>
  </p>
</form>

<form method="post"<?= $envoi ?> action="<?= url('travaux/taches/' . $id . '/supprimer') ?>" style="margin-top:.75rem"
      data-confirmation="<?= e(t('tr.tc.supprimer_confirmation', ['titre' => (string) $tache['titre']])) ?>">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('tr.tc.supprimer')) ?></button>
</form>
