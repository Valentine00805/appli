<?php
/**
 * @var DateTimeImmutable $aujourdhui
 * @var array $planning  la journée d'aujourd'hui, disposée par PlanningJour
 * @var array $examens, $taches, $stats
 * @var array|null $alternance  le résumé de l’alternance, ou null si l’on n’en fait pas
 * @var array $focus  le temps de révision d’aujourd’hui, et la série de jours
 */
?>

<div class="entete-page">
  <div>
    <?php // Par son pseudo ; à défaut, par son prénom. ?>
    <h1>Bonjour <?= e((string) (Auth::utilisateur()['pseudo'] ?? '') !== ''
        ? (string) Auth::utilisateur()['pseudo'] : explode(' ', (string) Auth::utilisateur()['nom'])[0]) ?> 👋</h1>
    <p>Nous sommes le <?= e(date_fr($aujourdhui->format('Y-m-d H:i:s'), false)) ?>.<?php if ($focus['aujourdhui'] > 0): ?>
      <span class="discret">· <?= e(Focus::duree((int) $focus['aujourdhui'])) ?> de révision aujourd’hui<?php if ($focus['serie'] > 1): ?>, <?= (int) $focus['serie'] ?> jours d’affilée 🔥<?php endif; ?>.</span><?php endif; ?></p>
  </div>
  <div class="actions">
    <?php // Réviser d’un clic : la session reprend le dernier cours révisé. ?>
    <a class="bouton bouton--secondaire" href="<?= url('focus') ?>">🎯 Réviser 25 min</a>
    <a class="bouton bouton--secondaire" href="<?= url('cours/nouveau') ?>" data-fenetre>+ Nouveau cours</a>
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

    <?php if ($alternance !== null): ?>
      <?php
      /*
       * L'alternance en trois lignes : où l'on est, ce qui reste à écrire,
       * la prochaine date du contrat. Rien de tout cela n'existe pour qui
       * n'en fait pas — la carte n'apparaît alors pas du tout.
       */
      $maintenant = $alternance['situation']['maintenant'] ?? null;
      $ensuite = $alternance['situation']['ensuite'] ?? null;
      ?>
      <section class="carte">
        <h2><a href="<?= url('alternance') ?>">Mon alternance</a></h2>
        <?php if ($alternance['entreprise'] !== ''): ?>
          <p class="discret" style="margin:0 0 .5rem"><?= e($alternance['entreprise']) ?></p>
        <?php endif; ?>

        <?php if ($maintenant !== null): ?>
          <?php $lieu = Alternance::LIEUX[$maintenant['lieu']]; ?>
          <p style="margin:0 0 .4rem">
            Aujourd’hui : <strong><?= $lieu['icone'] ?> <?= e($lieu['dans']) ?></strong>
            <?= $maintenant['fin'] === date('Y-m-d') ? '(dernier jour)'
                : 'jusqu’au ' . e(Alternance::jourCourt($maintenant['fin'])) ?>
          </p>
        <?php elseif ($ensuite !== null): ?>
          <?php $lieu = Alternance::LIEUX[$ensuite['lieu']]; ?>
          <p style="margin:0 0 .4rem">
            Ensuite : <strong><?= $lieu['icone'] ?> <?= e($lieu['dans']) ?></strong>
            à partir du <?= e(rtrim(Alternance::jourCourt($ensuite['debut']), '.')) ?>.
          </p>
        <?php endif; ?>

        <?php if ($alternance['aEcrire'] !== null): ?>
          <p style="margin:0 0 .4rem">
            ✍️ <a href="<?= url('alternance/journal/semaine', ['semaine' => $alternance['aEcrire']]) ?>" data-fenetre>
              Écrire la semaine du <?= e(Alternance::jourCourt($alternance['aEcrire'])) ?>
            </a>
          </p>
        <?php endif; ?>

        <?php if ($alternance['prochaine'] !== null): ?>
          <p class="discret" style="margin:0">
            📅 <?= e($alternance['prochaine']['libelle']) ?> :
            <?= e(Alternance::jourCourt($alternance['prochaine']['jour'])) ?>
          </p>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
</div>
