<?php
/**
 * @var DateTimeImmutable $aujourdhui
 * @var array $duJour, $semaine, $examens, $taches, $derniersCours, $stats
 */
/** Petit rendu d'une ligne : un évènement, ou une échéance de tâche. */
$ligneEvenement = static function (array $evt): string {
    $couleur = couleur_evenement($evt);
    // Une échéance n'a pas d'heure, et se gère depuis « Tâches ».
    $estTache = !empty($evt['est_tache']);
    $heure = $evt['journee_entiere']
        ? ($estTache ? 'Échéance' : 'Journée')
        : date('H:i', strtotime($evt['debut'])) . ' – ' . date('H:i', strtotime($evt['fin']));

    $destination = $estTache
        ? url('taches', ['liste' => $evt['liste_id']])
        : url('evenements/' . $evt['id'] . '/modifier');

    $html = '<a class="evt-ligne' . ($evt['termine'] ? ' evt-ligne--termine' : '')
        . ($estTache ? ' evt-ligne--tache' : '') . '" href="' . $destination . '">';
    $html .= '<span class="evt-ligne__barre" style="background:' . e($couleur) . '"></span>';
    $html .= '<span class="evt-ligne__heure">' . e($heure) . '</span>';
    $html .= '<span><span class="evt-ligne__titre">' . e($evt['titre']) . '</span><br>';
    $html .= '<span class="evt-ligne__meta">' . e(icone_evenement($evt) . ' ' . libelle_type($evt));
    if ($estTache && !empty($evt['detail_tache'])) {
        $html .= ' · ' . e((string) $evt['detail_tache']);
    }
    if (!empty($evt['matiere_nom'])) {
        $html .= ' · ' . e($evt['matiere_nom']);
    }
    if (!empty($evt['lieu'])) {
        $html .= ' · ' . e($evt['lieu']);
    }
    $html .= '</span></span></a>';
    return $html;
};
?>

<div class="entete-page">
  <div>
    <h1>Bonjour <?= e(explode(' ', (string) Auth::utilisateur()['nom'])[0]) ?> 👋</h1>
    <p>Nous sommes le <?= e(date_fr($aujourdhui->format('Y-m-d H:i:s'), false)) ?>.</p>
  </div>
  <div class="actions">
    <a class="bouton bouton--secondaire" href="<?= url('cours/nouveau') ?>">+ Nouveau cours</a>
    <a class="bouton" href="<?= url('evenements/nouveau') ?>">+ Nouvel évènement</a>
  </div>
</div>

<div class="grille grille--4" style="margin-bottom:1.5rem">
  <a class="carte stat" href="<?= url('cours') ?>" style="text-decoration:none;color:inherit">
    <div class="stat__valeur"><?= (int) $stats['cours'] ?></div><div class="stat__libelle">cours enregistrés</div>
  </a>
  <a class="carte stat" href="<?= url('organisation/matieres') ?>" style="text-decoration:none;color:inherit">
    <div class="stat__valeur"><?= (int) $stats['matieres'] ?></div><div class="stat__libelle">matières</div>
  </a>
  <a class="carte stat" href="<?= url('calendrier') ?>" style="text-decoration:none;color:inherit">
    <div class="stat__valeur"><?= (int) $stats['aVenir'] ?></div><div class="stat__libelle">évènements à venir</div>
  </a>
  <a class="carte stat" href="<?= url('taches') ?>" style="text-decoration:none;color:inherit">
    <div class="stat__valeur"><?= (int) $stats['taches'] ?></div><div class="stat__libelle">tâches à faire</div>
  </a>
</div>

<div class="colonnes">
  <div class="pile">
    <section class="carte">
      <h2>Aujourd'hui</h2>
      <?php if ($duJour === []): ?>
        <p class="discret">Rien de prévu aujourd'hui. Profitez-en pour réviser 🙂</p>
      <?php else: ?>
        <div class="pile">
          <?php foreach ($duJour as $evt): ?><?= $ligneEvenement($evt) ?><?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="carte">
      <h2>Les 7 prochains jours</h2>
      <?php if ($semaine === []): ?>
        <p class="discret">Aucun évènement planifié.
          <a href="<?= url('evenements/nouveau') ?>">En ajouter un</a>.</p>
      <?php else: ?>
        <div class="pile">
          <?php
          $jourCourant = null;
          foreach ($semaine as $evt):
              $jour = substr((string) $evt['debut'], 0, 10);
              if ($jour !== $jourCourant):
                  $jourCourant = $jour;
                  ?>
                  <p class="discret" style="margin:.4rem 0 0;text-transform:capitalize">
                    <?= e(date_fr($evt['debut'], false)) ?>
                  </p>
              <?php endif; ?>
              <?= $ligneEvenement($evt) ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <div class="pile">
    <section class="carte">
      <h2>Mes tâches</h2>
      <?php if ($taches === []): ?>
        <p class="discret" style="margin:0">
          <?php if ((int) $stats['taches'] > 0): ?>
            <?= (int) $stats['taches'] ?> tâche<?= (int) $stats['taches'] > 1 ? 's' : '' ?> en attente, sans échéance proche.
            <a href="<?= url('taches') ?>">Voir mes listes</a>.
          <?php else: ?>
            Rien à faire dans les jours qui viennent.
            <a href="<?= url('taches') ?>">Ouvrir mes listes</a>.
          <?php endif; ?>
        </p>
      <?php else: ?>
        <div class="pile">
          <?php foreach ($taches as $t): ?>
            <?php $etat = echeance_etat($t['echeance']); ?>
            <a class="evt-ligne" href="<?= url('taches') ?>">
              <span class="evt-ligne__barre" style="background:<?= e($t['liste_couleur']) ?>"></span>
              <span>
                <span class="evt-ligne__titre"><?= e($t['titre']) ?></span><br>
                <span class="evt-ligne__meta"><?= e($t['liste_icone'] . ' ' . $t['liste_nom']) ?></span>
              </span>
              <span class="evt-ligne__droite">
                <span class="echeance echeance--<?= e($etat) ?>"><?= e(echeance_libelle($t['echeance'])) ?></span>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="carte">
      <h2>Examens &amp; devoirs</h2>
      <?php if ($examens === []): ?>
        <p class="discret">Aucune échéance enregistrée.</p>
      <?php else: ?>
        <div class="pile">
          <?php foreach ($examens as $evt):
              $jours = (int) floor((strtotime((string) $evt['debut']) - time()) / 86400); ?>
            <a class="evt-ligne" href="<?= url('evenements/' . $evt['id'] . '/modifier') ?>">
              <span class="evt-ligne__barre" style="background:<?= e(couleur_evenement($evt)) ?>"></span>
              <span>
                <span class="evt-ligne__titre"><?= e(icone_evenement($evt) . ' ' . $evt['titre']) ?></span><br>
                <span class="evt-ligne__meta"><?= e(date_fr($evt['debut'])) ?></span>
              </span>
              <span class="evt-ligne__droite">
                <span class="pastille">
                  <?= $jours <= 0 ? "aujourd'hui" : ($jours === 1 ? 'demain' : 'J-' . $jours) ?>
                </span>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="carte">
      <h2>Cours récents</h2>
      <?php if ($derniersCours === []): ?>
        <p class="discret">Vous n'avez pas encore de cours.
          <a href="<?= url('cours/nouveau') ?>">Créer le premier</a>.</p>
      <?php else: ?>
        <div class="pile">
          <?php foreach ($derniersCours as $c): ?>
            <a class="evt-ligne" href="<?= url('cours/' . $c['id']) ?>">
              <span class="evt-ligne__barre" style="background:<?= e($c['matiere_couleur'] ?? '#94a3b8') ?>"></span>
              <span>
                <span class="evt-ligne__titre"><?= e($c['titre']) ?></span><br>
                <span class="evt-ligne__meta">
                  <?= e($c['matiere_nom'] ?? 'Sans matière') ?> · modifié le <?= e(date_fr($c['updated_at'], false)) ?>
                </span>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>
