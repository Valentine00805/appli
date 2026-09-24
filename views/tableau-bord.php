<?php
/**
 * @var DateTimeImmutable $aujourdhui
 * @var array $planning  la journée d'aujourd'hui, disposée par PlanningJour
 * @var array $examens, $taches, $stats
 * @var array|null $alternance  le résumé de l’alternance, ou null si l’on n’en fait pas
 * @var array $travaux  mes tâches de groupe à faire, et les invitations reçues
 * @var array $focus  le temps de révision d’aujourd’hui, et la série de jours
 */
?>

<div class="entete-page">
  <div>
    <?php // Par son pseudo ; à défaut, par son prénom. ?>
    <h1><?= e(t('accueil.bonjour', ['nom' => (string) (Auth::utilisateur()['pseudo'] ?? '') !== ''
        ? (string) Auth::utilisateur()['pseudo'] : explode(' ', (string) Auth::utilisateur()['nom'])[0]])) ?></h1>
    <p><?= e(t('accueil.nous_sommes', ['date' => date_fr($aujourdhui->format('Y-m-d H:i:s'), false)])) ?><?php if ($focus['aujourdhui'] > 0): ?>
      <span class="discret">· <?= e(t('accueil.revision_du_jour', ['duree' => Focus::duree((int) $focus['aujourdhui'])])) ?><?php if ($focus['serie'] > 1): ?><?= e(t('accueil.serie', ['jours' => (int) $focus['serie']])) ?><?php endif; ?>.</span><?php endif; ?></p>
  </div>
  <div class="actions">
    <?php // Réviser d’un clic : la session reprend le dernier cours révisé. ?>
    <a class="bouton bouton--secondaire" href="<?= url('focus') ?>"><?= e(t('accueil.reviser')) ?></a>
    <a class="bouton bouton--secondaire" href="<?= url('cours/nouveau') ?>" data-fenetre><?= e(t('accueil.nouveau_cours')) ?></a>
    <a class="bouton" href="<?= url('evenements/nouveau') ?>"><?= e(t('accueil.nouvel_evenement')) ?></a>
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
      <h2><?= e(t('accueil.mes_taches')) ?></h2>
      <?php if ($taches === []): ?>
        <p class="discret" style="margin:0">
          <?php if ((int) $stats['taches'] > 0): ?>
            <?= e(t('accueil.taches_en_attente', ['n' => (int) $stats['taches']])) ?>
            <a href="<?= url('taches') ?>"><?= e(t('accueil.voir_listes')) ?></a>.
          <?php else: ?>
            <?= e(t('accueil.rien_a_faire')) ?>
            <a href="<?= url('taches') ?>"><?= e(t('accueil.ouvrir_listes')) ?></a>.
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
      <h2><?= e(t('accueil.examens')) ?></h2>
      <?php if ($examens === []): ?>
        <p class="discret"><?= e(t('accueil.aucune_echeance')) ?></p>
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
                  <?= e($jours <= 0 ? t('accueil.aujourdhui') : ($jours === 1 ? t('accueil.demain') : 'J-' . $jours)) ?>
                </span>
                <?php // Avant une échéance, on va au cours ou à sa fiche. ?>
                <?php $coursId = (int) ($evt['cours_id'] ?? 0); ?>
                <?php if ($coursId > 0): ?>
                  <a class="bouton bouton--secondaire" href="<?= url('cours/' . $coursId) ?>"
                     title="Ouvrir le cours"><?= e(t('accueil.cours')) ?></a>
                  <a class="bouton bouton--secondaire" href="<?= url('revision/' . $coursId) ?>"
                     title="Ouvrir la fiche de révision"><?= e(t('accueil.fiche_revision')) ?></a>
                <?php endif; ?>
                <a class="bouton bouton--discret bouton--petit"
                   href="<?= url('evenements/' . $evt['id'] . '/modifier') ?>" title="<?= e(t('accueil.modifier')) ?>">✎</a>
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
        <h2><a href="<?= url('alternance') ?>"><?= e(t('accueil.alternance')) ?></a></h2>
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

    <?php if ($travaux['taches'] !== [] || $travaux['invitations'] > 0): ?>
      <?php // Les travaux de groupe : ce qu'on m'a confié, et qui m'attend. ?>
      <section class="carte">
        <h2><a href="<?= url('travaux') ?>"><?= e(t('accueil.travaux')) ?></a></h2>
        <?php if ($travaux['invitations'] > 0): ?>
          <p style="margin:0 0 .5rem">✉️ <a href="<?= url('travaux') ?>"><?= (int) $travaux['invitations'] ?> invitation<?= $travaux['invitations'] > 1 ? 's' : '' ?> à un travail de groupe</a></p>
        <?php endif; ?>
        <?php if ($travaux['taches'] !== []): ?>
          <ul class="travaux-mes-taches">
            <?php foreach ($travaux['taches'] as $t): ?>
              <li>
                <a href="<?= url('travaux/' . (int) $t['projet_id']) ?>" data-fenetre><?= e((string) $t['titre']) ?></a>
                <span class="discret">· <?= e((string) $t['projet_nom']) ?></span>
                <?php $texte = echeance_libelle($t['echeance']); ?>
                <?php if ($texte !== ''): ?>
                  <span class="echeance echeance--<?= e(echeance_etat($t['echeance'])) ?>"><?= e($texte) ?></span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
</div>
