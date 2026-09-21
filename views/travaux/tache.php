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
    <label for="titre-tache">Tâche</label>
    <input type="text" id="titre-tache" name="titre" required maxlength="200" value="<?= e((string) $tache['titre']) ?>">
  </div>
  <div class="ligne-champs">
    <div class="champ">
      <label for="membre-tache">Qui s’en occupe</label>
      <select id="membre-tache" name="membre_id">
        <option value="">Personne pour l’instant</option>
        <?php foreach ($membres as $m): ?>
          <option value="<?= (int) $m['id'] ?>"<?= (int) $m['id'] === $choisi ? ' selected' : '' ?>><?= e((string) $m['nom_affiche']) ?><?= (int) $m['id'] === (int) $projet['mon_membre_id'] ? ' (moi)' : '' ?><?= $m['user_id'] === null ? ' (sans compte)' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="champ">
      <label for="echeance-tache">Pour le <span class="discret">(facultatif)</span></label>
      <input type="date" id="echeance-tache" name="echeance" value="<?= e((string) ($tache['echeance'] ?? '')) ?>">
    </div>
  </div>
  <div class="champ">
    <label for="note-tache">Précisions <span class="discret">(facultatif)</span></label>
    <textarea id="note-tache" name="note" rows="3" maxlength="2000"><?= e((string) ($tache['note'] ?? '')) ?></textarea>
  </div>
  <p class="actions">
    <button class="bouton" type="submit">Enregistrer</button>
    <a class="bouton bouton--secondaire" href="<?= $retour ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>>Annuler</a>
  </p>
</form>

<form method="post"<?= $envoi ?> action="<?= url('travaux/taches/' . $id . '/supprimer') ?>" style="margin-top:.75rem"
      data-confirmation="Supprimer la tâche « <?= e((string) $tache['titre']) ?> » pour tout le groupe ?">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <button class="bouton bouton--danger bouton--petit" type="submit">Supprimer cette tâche</button>
</form>
