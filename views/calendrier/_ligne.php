<?php
/**
 * Une ligne d'évènement réutilisable.
 * @var array $evt
 * @var bool $avecDate
 */
$avecDate = $avecDate ?? false;
$couleur = couleur_evenement($evt);
$heure = $evt['journee_entiere']
    ? 'Journée'
    : date('H:i', strtotime((string) $evt['debut'])) . ' – ' . date('H:i', strtotime((string) $evt['fin']));
?>
<div class="evt-ligne<?= (int) $evt['termine'] === 1 ? ' evt-ligne--termine' : '' ?>">
  <span class="evt-ligne__barre" style="background:<?= e($couleur) ?>"></span>

  <span class="evt-ligne__heure">
    <?= e($avecDate ? date('d/m', strtotime((string) $evt['debut'])) . ' ' . substr($heure, 0, 5) : $heure) ?>
  </span>

  <span style="min-width:0">
    <a class="evt-ligne__titre" href="<?= url('evenements/' . $evt['id'] . '/modifier') ?>"
       style="text-decoration:none;color:inherit">
      <?= e(icone_evenement($evt) . ' ' . $evt['titre']) ?>
    </a><br>
    <span class="evt-ligne__meta">
      <?= e(libelle_type($evt)) ?><?php
        if (!empty($evt['matiere_nom'])) { echo ' · ' . e($evt['matiere_nom']); }
        if (!empty($evt['lieu'])) { echo ' · 📍 ' . e($evt['lieu']); }
        if (!empty($evt['cours_titre'])) { echo ' · 📘 ' . e($evt['cours_titre']); }
      ?>
    </span>
    <?php if (!empty($evt['description'])): ?>
      <br><span class="evt-ligne__meta"><?= e(extrait($evt['description'], 120)) ?></span>
    <?php endif; ?>
  </span>

  <span class="evt-ligne__droite">
    <form method="post" action="<?= url('evenements/' . $evt['id'] . '/termine') ?>" class="en-ligne">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <input type="hidden" name="retour" value="<?= e(ROUTE === '' ? '' : ROUTE) ?>">
      <button class="bouton bouton--discret bouton--petit" type="submit"
              title="<?= (int) $evt['termine'] === 1 ? 'Marquer comme à faire' : 'Marquer comme terminé' ?>">
        <?= (int) $evt['termine'] === 1 ? '☑' : '☐' ?>
      </button>
    </form>
    <a class="bouton bouton--discret bouton--petit" href="<?= url('evenements/' . $evt['id'] . '/modifier') ?>"
       title="Modifier">✎</a>
  </span>
</div>
