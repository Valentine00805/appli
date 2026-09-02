<?php
/**
 * Une ligne d'évènement réutilisable.
 * @var array $evt
 * @var bool $avecDate
 */
$avecDate = $avecDate ?? false;
$couleur = couleur_evenement($evt);
// Une échéance de tâche n'a pas d'heure, et se modifie depuis « Tâches ».
$estTache = !empty($evt['est_tache']);
$heure = $evt['journee_entiere']
    ? ($estTache ? 'Échéance' : 'Journée')
    : date('H:i', strtotime((string) $evt['debut'])) . ' – ' . date('H:i', strtotime((string) $evt['fin']));
$lienEvt = $estTache
    ? url('taches', ['liste' => $evt['liste_id']])
    : url('evenements/' . $evt['id'] . '/modifier');
?>
<div class="evt-ligne<?= (int) $evt['termine'] === 1 ? ' evt-ligne--termine' : '' ?><?= $estTache ? ' evt-ligne--tache' : '' ?>">
  <span class="evt-ligne__barre" style="background:<?= e($couleur) ?>"></span>

  <span class="evt-ligne__heure">
    <?= e($avecDate ? date('d/m', strtotime((string) $evt['debut'])) . ' ' . substr($heure, 0, 5) : $heure) ?>
  </span>

  <span style="min-width:0">
    <a class="evt-ligne__titre" href="<?= e($lienEvt) ?>"
       style="text-decoration:none;color:inherit">
      <?= e(icone_evenement($evt) . ' ' . $evt['titre']) ?>
    </a><br>
    <span class="evt-ligne__meta">
      <?= e(libelle_type($evt)) ?><?php
        if ($estTache && !empty($evt['detail_tache'])) { echo ' · ' . e((string) $evt['detail_tache']); }
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
    <?php if ($estTache): ?>
      <?php
      // Cocher ici ramène sur le calendrier, au mois et aux filtres en cours.
      $estListe = ($evt['type_nom'] ?? '') === 'Tâche principale';
      $fait = (int) $evt['termine'] === 1;
      ?>
      <form method="post" action="<?= url((string) $evt['route_cocher']) ?>" class="en-ligne">
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
        <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
        <button class="bouton bouton--discret bouton--petit" type="submit"
                title="<?= $estListe
                    ? ($fait ? 'Rouvrir toute la liste' : 'Terminer toute la liste')
                    : ($fait ? 'Marquer comme à faire' : 'Marquer comme faite') ?>">
          <?= $fait ? '☑' : '☐' ?>
        </button>
      </form>
      <a class="bouton bouton--discret bouton--petit" href="<?= e($lienEvt) ?>"
         title="Ouvrir dans Tâches">↗</a>
    <?php else: ?>
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
    <?php endif; ?>
  </span>
</div>
