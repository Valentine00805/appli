<?php
/**
 * @var string $vue
 * @var DateTimeImmutable $ancre, $debut, $fin
 * @var array $evenements, $parJour, $matieres, $types, $aVenir
 * @var ?int $matiereId, $typeId
 */
$aujourdhui = (new DateTimeImmutable('today'))->format('Y-m-d');

$filtresUrl = array_filter([
    'matiere' => $matiereId,
    'type'    => $typeId,
], static fn ($v): bool => $v !== null);

/** Lien de navigation en gardant les filtres. */
$lien = static function (string $vueCible, DateTimeInterface $date) use ($filtresUrl): string {
    return url('calendrier', $filtresUrl + ['vue' => $vueCible, 'date' => $date->format('Y-m-d')]);
};

// Les flèches avancent du pas de la vue : un jour, une semaine, un mois.
$pas = match ($vue) {
    'jour'    => '1 day',
    'semaine' => '7 days',
    default   => '1 month',
};
$precedent = $ancre->modify('-' . $pas);
$suivant   = $ancre->modify('+' . $pas);

if ($vue === 'jour') {
    $titre = ucfirst(date_fr($ancre->format('Y-m-d') . ' 00:00:00', false));
} elseif ($vue === 'semaine') {
    $titre = 'Semaine du ' . $debut->format('j') . ' au ' . $fin->format('j') . ' '
        . strtolower(nom_mois((int) $fin->format('n'))) . ' ' . $fin->format('Y');
} else {
    $titre = nom_mois((int) $ancre->format('n')) . ' ' . $ancre->format('Y');
}

/**
 * Où mène un élément du calendrier : un vrai évènement s'ouvre en
 * modification, une échéance de tâche ouvre sa liste.
 */
$destination = static function (array $evt): string {
    return empty($evt['est_tache'])
        ? url('evenements/' . $evt['id'] . '/modifier')
        : url('taches', ['liste' => $evt['liste_id']]);
};

/** Rend une puce d'évènement pour la grille mensuelle. */
$puce = static function (array $evt) use ($destination): string {
    $couleur = couleur_evenement($evt);
    $fond = 'color-mix(in srgb, ' . $couleur . ' 16%, transparent)';
    $heure = $evt['journee_entiere'] ? '' : '<span class="evt__heure">' . date('H:i', strtotime($evt['debut'])) . '</span> ';
    $classes = 'evt' . ($evt['termine'] ? ' evt--termine' : '') . (empty($evt['est_tache']) ? '' : ' evt--tache');
    return '<a class="' . $classes . '"'
        . ' href="' . $destination($evt) . '"'
        . ' style="background:' . e($fond) . ';border-left-color:' . e($couleur) . ';color:inherit"'
        . ' title="' . e(libelle_type($evt) . ' · ' . $evt['titre']) . '">'
        . $heure . e(icone_evenement($evt)) . ' ' . e($evt['titre'])
        . '</a>';
};
?>

<div class="entete-page">
  <div>
    <h1>Calendrier</h1>
    <p>Vos évènements et les échéances de vos tâches, au même endroit.</p>
  </div>
  <div class="actions">
    <a class="bouton bouton--secondaire" href="<?= url('organisation/types') ?>">Gérer les types</a>
    <a class="bouton" href="<?= url('evenements/nouveau') ?>">+ Évènement</a>
  </div>
</div>

<div class="cal-barre">
  <div class="actions">
    <a class="bouton bouton--secondaire bouton--petit" href="<?= $lien($vue, $precedent) ?>" aria-label="Période précédente">←</a>
    <a class="bouton bouton--secondaire bouton--petit" href="<?= $lien($vue, new DateTimeImmutable('today')) ?>">Aujourd'hui</a>
    <a class="bouton bouton--secondaire bouton--petit" href="<?= $lien($vue, $suivant) ?>" aria-label="Période suivante">→</a>
    <h2 class="cal-titre"><?= e($titre) ?></h2>
  </div>

  <div class="actions">
    <form method="get" action="<?= url('calendrier') ?>" data-auto-envoi>
      <input type="hidden" name="vue" value="<?= e($vue) ?>">
      <input type="hidden" name="date" value="<?= e($ancre->format('Y-m-d')) ?>">
      <select name="matiere" aria-label="Filtrer par matière">
        <option value="">Toutes les matières</option>
        <?php foreach ($matieres as $m): ?>
          <option value="<?= (int) $m['id'] ?>"<?= $matiereId === (int) $m['id'] ? ' selected' : '' ?>>
            <?= e($m['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select name="type" aria-label="Filtrer par type">
        <option value="">Tous les types</option>
        <?php foreach ($types as $t): ?>
          <option value="<?= (int) $t['id'] ?>"<?= $typeId === (int) $t['id'] ? ' selected' : '' ?>>
            <?= e($t['icone'] . ' ' . $t['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="bouton bouton--secondaire bouton--petit" type="submit">OK</button></noscript>
    </form>

    <nav class="cal-onglets" aria-label="Type d'affichage">
      <a href="<?= $lien('jour', $ancre) ?>"<?= $vue === 'jour' ? ' aria-current="page"' : '' ?>>Jour</a>
      <a href="<?= $lien('semaine', $ancre) ?>"<?= $vue === 'semaine' ? ' aria-current="page"' : '' ?>>Semaine</a>
      <a href="<?= $lien('mois', $ancre) ?>"<?= $vue === 'mois' ? ' aria-current="page"' : '' ?>>Mois</a>
      <a href="<?= $lien('liste', $ancre) ?>"<?= $vue === 'liste' ? ' aria-current="page"' : '' ?>>Liste</a>
    </nav>
  </div>
</div>

<?php if ($vue === 'jour'): ?>

  <?php
  $cle = $ancre->format('Y-m-d');
  $duJour = $parJour[$cle] ?? [];
  ?>
  <section class="jour-bloc jour-bloc--seul<?= $cle === $aujourdhui ? ' jour-bloc--aujourdhui' : '' ?>">
    <header class="jour-bloc__entete">
      <span class="jour-bloc__titre">
        <?= $cle === $aujourdhui ? "Aujourd'hui" : e(ucfirst(date_fr($cle . ' 00:00:00', false))) ?>
      </span>
      <a class="discret" href="<?= url('evenements/nouveau', ['date' => $cle]) ?>">+ ajouter</a>
    </header>
    <div class="jour-bloc__corps">
      <?php if ($duJour === []): ?>
        <span class="discret">Rien de prévu ce jour-là.</span>
      <?php else: ?>
        <?php foreach ($duJour as $evt): ?>
          <?= Vue::rendre('calendrier/_ligne', ['evt' => $evt]) ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

<?php elseif ($vue === 'mois'): ?>

  <div class="cal-grille">
    <?php foreach (jours_semaine() as $jour): ?>
      <div class="cal-entete-jour"><?= e($jour) ?></div>
    <?php endforeach; ?>

    <?php
    $curseur = $debut;
    $moisAffiche = (int) $ancre->format('n');
    while ($curseur <= $fin):
        $cle = $curseur->format('Y-m-d');
        $duJour = $parJour[$cle] ?? [];
        $classes = 'cal-jour';
        if ((int) $curseur->format('n') !== $moisAffiche) {
            $classes .= ' cal-jour--hors';
        } elseif ((int) $curseur->format('N') >= 6) {
            $classes .= ' cal-jour--weekend';
        }
        if ($cle === $aujourdhui) {
            $classes .= ' cal-jour--aujourdhui';
        }
        ?>
        <div class="<?= $classes ?>">
          <div class="cal-jour__haut">
            <span class="cal-jour__numero"><?= (int) $curseur->format('j') ?></span>
            <a class="cal-jour__ajout" href="<?= url('evenements/nouveau', ['date' => $cle]) ?>"
               title="Ajouter un évènement le <?= e($curseur->format('d/m/Y')) ?>">+</a>
          </div>
          <?php foreach (array_slice($duJour, 0, 4) as $evt): ?>
            <?= $puce($evt) ?>
          <?php endforeach; ?>
          <?php if (count($duJour) > 4): ?>
            <a class="discret" style="font-size:.72rem" href="<?= $lien('semaine', $curseur) ?>">
              +<?= count($duJour) - 4 ?> autre<?= count($duJour) - 4 > 1 ? 's' : '' ?>
            </a>
          <?php endif; ?>
        </div>
        <?php
        $curseur = $curseur->modify('+1 day');
    endwhile;
    ?>
  </div>

<?php elseif ($vue === 'semaine'): ?>

  <div class="cal-semaine">
    <?php
    $curseur = $debut;
    while ($curseur <= $fin):
        $cle = $curseur->format('Y-m-d');
        $duJour = $parJour[$cle] ?? [];
        ?>
        <section class="jour-bloc<?= $cle === $aujourdhui ? ' jour-bloc--aujourdhui' : '' ?>">
          <header class="jour-bloc__entete">
            <span class="jour-bloc__titre"><?= e(date_fr($cle . ' 00:00:00', false)) ?></span>
            <a class="discret" href="<?= url('evenements/nouveau', ['date' => $cle]) ?>">+ ajouter</a>
          </header>
          <div class="jour-bloc__corps">
            <?php if ($duJour === []): ?>
              <span class="discret">Rien de prévu.</span>
            <?php else: ?>
              <?php foreach ($duJour as $evt): ?>
                <?= Vue::rendre('calendrier/_ligne', ['evt' => $evt]) ?>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>
        <?php
        $curseur = $curseur->modify('+1 day');
    endwhile;
    ?>
  </div>

<?php else: ?>

  <?php if ($evenements === []): ?>
    <div class="vide">
      <span class="vide__icone">🗓️</span>
      <p>Aucun évènement à partir de <?= e(strtolower(nom_mois((int) $ancre->format('n')))) ?>
         <?= e($ancre->format('Y')) ?>.</p>
      <a class="bouton" href="<?= url('evenements/nouveau') ?>">Planifier quelque chose</a>
    </div>
  <?php else: ?>
    <div class="pile">
      <?php
      $jourCourant = null;
      foreach ($evenements as $evt):
          $jour = substr((string) $evt['debut'], 0, 10);
          if ($jour !== $jourCourant):
              $jourCourant = $jour; ?>
              <h3 style="margin:1rem 0 .25rem;text-transform:capitalize">
                <?= e(date_fr($evt['debut'], false)) ?>
              </h3>
          <?php endif; ?>
          <?= Vue::rendre('calendrier/_ligne', ['evt' => $evt]) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php endif; ?>

<div class="legende-types" style="margin-top:1rem">
  <?php foreach ($types as $t): ?>
    <a class="pastille" href="<?= url('calendrier', ['vue' => $vue, 'date' => $ancre->format('Y-m-d'), 'type' => $t['id']]) ?>"
       style="background:<?= e($t['couleur']) ?>;color:<?= e(couleur_texte($t['couleur'])) ?>">
      <?= e($t['icone'] . ' ' . $t['nom']) ?>
    </a>
  <?php endforeach; ?>
  <a class="pastille" href="<?= url('organisation/types') ?>">⚙ Gérer</a>
</div>

<?php if ($aVenir !== []): ?>
  <section class="carte" style="margin-top:1.5rem">
    <h2>Prochainement</h2>
    <p class="discret" style="margin:-.4rem 0 .75rem">Les prochains évènements et échéances, quels que soient les filtres ci-dessus.</p>
    <div class="pile">
      <?php foreach ($aVenir as $evt): ?>
        <?= Vue::rendre('calendrier/_ligne', ['evt' => $evt, 'avecDate' => true]) ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
