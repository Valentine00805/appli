<?php
/**
 * @var DateTimeImmutable $aujourdhui
 * @var array $planning  la journée d'aujourd'hui, disposée par PlanningJour
 * @var array $examens, $taches, $derniersCours, $stats
 */
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

<div class="colonnes">
  <div class="pile">
    <?php
    /*
     * Les tâches à venir, dans la colonne large.
     *
     * Elles y remplacent « Aujourd'hui » et « Les sept prochains jours » :
     * ces deux blocs redisaient en liste ce que la grille de droite montre
     * en place, et l'on relisait deux fois la même journée.
     */
    ?>
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
            <a class="evt-ligne" href="<?= url('taches', ['liste' => (int) $t['liste_id']]) ?>">
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
  </div>

  <div class="pile">
    <?php
    /*
     * La journée en grille, en plus petit.
     *
     * Le même partiel que le calendrier : on ouvre l'accueil pour savoir ce
     * qu'on fait aujourd'hui, et une grille le dit plus vite qu'une liste —
     * les heures libres s'y voient sans qu'on ait à les calculer.
     */
    echo Vue::rendre('calendrier/_planning', [
        'planning'      => $planning,
        'cle'           => $aujourdhui->format('Y-m-d'),
        'estAujourdhui' => true,
        'compact'       => true,
    ]);
    ?>
    <section class="carte">
      <h2>Examens &amp; devoirs</h2>
      <?php if ($examens === []): ?>
        <p class="discret">Aucune échéance enregistrée.</p>
      <?php else: ?>
        <div class="pile">
          <?php foreach ($examens as $evt):
              $jours = (int) floor((strtotime((string) $evt['debut']) - time()) / 86400); ?>
            <div class="evt-ligne">
              <span class="evt-ligne__barre" style="background:<?= e(couleur_evenement($evt)) ?>"></span>
              <span style="min-width:0">
                <span class="evt-ligne__titre"><?= e(icone_evenement($evt) . ' ' . $evt['titre']) ?></span><br>
                <span class="evt-ligne__meta"><?= e(date_fr($evt['debut'])) ?><?php
                  if (!empty($evt['cours_titre'])) { echo ' · 📘 ' . e((string) $evt['cours_titre']); }
                ?></span>
              </span>
              <span class="evt-ligne__droite">
                <span class="pastille">
                  <?= $jours <= 0 ? "aujourd'hui" : ($jours === 1 ? 'demain' : 'J-' . $jours) ?>
                </span>
                <?php // Avant une échéance, on va au cours ou à sa fiche. ?>
                <?php $coursId = (int) ($evt['cours_id'] ?? 0); ?>
                <?php if ($coursId > 0): ?>
                  <a class="bouton bouton--secondaire" href="<?= url('cours/' . $coursId) ?>"
                     title="Ouvrir le cours">📘 Cours</a>
                  <a class="bouton bouton--secondaire" href="<?= url('revision/' . $coursId) ?>"
                     title="Ouvrir la fiche de révision">📝 Révision</a>
                <?php endif; ?>
                <a class="bouton bouton--discret bouton--petit"
                   href="<?= url('evenements/' . $evt['id'] . '/modifier') ?>" title="Modifier">✎</a>
              </span>
            </div>
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
