<?php
/**
 * @var DateTimeImmutable $aujourdhui
 * @var array $duJour, $semaine, $examens, $derniersCours, $stats
 */
$types = types_evenement();

/** Petit rendu d'une ligne d'évènement. */
$ligneEvenement = static function (array $evt) use ($types): string {
    $couleur = $evt['matiere_couleur'] ?? '#94a3b8';
    $heure = $evt['journee_entiere']
        ? 'Journée'
        : date('H:i', strtotime($evt['debut'])) . ' – ' . date('H:i', strtotime($evt['fin']));

    $html = '<a class="evt-ligne' . ($evt['termine'] ? ' evt-ligne--termine' : '') . '" href="'
        . url('evenements/' . $evt['id'] . '/modifier') . '">';
    $html .= '<span class="evt-ligne__barre" style="background:' . e($couleur) . '"></span>';
    $html .= '<span class="evt-ligne__heure">' . e($heure) . '</span>';
    $html .= '<span><span class="evt-ligne__titre">' . e($evt['titre']) . '</span><br>';
    $html .= '<span class="evt-ligne__meta">' . e($types[$evt['type']]['icone'] . ' ' . $types[$evt['type']]['libelle']);
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
  <a class="carte stat" href="<?= url('matieres') ?>" style="text-decoration:none;color:inherit">
    <div class="stat__valeur"><?= (int) $stats['matieres'] ?></div><div class="stat__libelle">matières</div>
  </a>
  <a class="carte stat" href="<?= url('calendrier') ?>" style="text-decoration:none;color:inherit">
    <div class="stat__valeur"><?= (int) $stats['aVenir'] ?></div><div class="stat__libelle">évènements à venir</div>
  </a>
  <div class="carte stat">
    <div class="stat__valeur"><?= (int) $stats['fichiers'] ?></div><div class="stat__libelle">fichiers joints</div>
  </div>
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
      <h2>Examens &amp; devoirs</h2>
      <?php if ($examens === []): ?>
        <p class="discret">Aucune échéance enregistrée.</p>
      <?php else: ?>
        <div class="pile">
          <?php foreach ($examens as $evt):
              $jours = (int) floor((strtotime((string) $evt['debut']) - time()) / 86400); ?>
            <a class="evt-ligne" href="<?= url('evenements/' . $evt['id'] . '/modifier') ?>">
              <span class="evt-ligne__barre" style="background:<?= e($evt['matiere_couleur'] ?? '#dc2626') ?>"></span>
              <span>
                <span class="evt-ligne__titre"><?= e($types[$evt['type']]['icone'] . ' ' . $evt['titre']) ?></span><br>
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
