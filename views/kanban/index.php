<?php
/** @var array $parColonne, $colonnes, $matieres, $types @var ?int $matiereId, $typeId @var string $source */
$csrf = Session::jetonCsrf();
$total = array_sum(array_map('count', $parColonne));
?>

<div class="entete-page">
  <div>
    <h1>Tableau</h1>
    <p>Vos sous-tâches et vos évènements — révisions, devoirs, examens — au même endroit.</p>
  </div>
  <div class="actions">
    <a class="bouton bouton--secondaire" href="<?= url('taches') ?>">Mes tâches</a>
    <a class="bouton" href="<?= url('evenements/nouveau') ?>">+ Évènement</a>
  </div>
</div>

<form class="cal-barre" method="get" action="<?= url('tableau') ?>" data-auto-envoi>
  <div class="actions">
    <select name="source" aria-label="Ce que le tableau contient">
      <option value="tout"<?= $source === 'tout' ? ' selected' : '' ?>>Tout</option>
      <option value="taches"<?= $source === 'taches' ? ' selected' : '' ?>>Sous-tâches seulement</option>
      <option value="evenements"<?= $source === 'evenements' ? ' selected' : '' ?>>Évènements seulement</option>
    </select>
    <select name="matiere" aria-label="Filtrer par matière">
      <option value="">Toutes les matières</option>
      <?php foreach ($matieres as $m): ?>
        <option value="<?= (int) $m['id'] ?>"<?= $matiereId === (int) $m['id'] ? ' selected' : '' ?>>
          <?= e($m['nom']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <select name="type" aria-label="Filtrer par type d'évènement">
      <option value="">Tous les types</option>
      <?php foreach ($types as $t): ?>
        <option value="<?= (int) $t['id'] ?>"<?= $typeId === (int) $t['id'] ? ' selected' : '' ?>>
          <?= e($t['icone'] . ' ' . $t['nom']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="bouton bouton--secondaire bouton--petit" type="submit">OK</button></noscript>
  </div>
  <p class="discret" style="margin:0">
    <?= $total ?> carte<?= $total > 1 ? 's' : '' ?>
    <?php if ($matiereId !== null || $typeId !== null): ?>
      · <a href="<?= url('tableau', ['source' => $source]) ?>">retirer les filtres</a>
    <?php endif; ?>
  </p>
</form>

<?php if ($total === 0): ?>
  <div class="vide">
    <span class="vide__icone">🗂️</span>
    <p>
      Rien à afficher. Ajoutez des sous-tâches depuis « Mes tâches », ou des
      révisions et des devoirs depuis le calendrier : ils apparaîtront ici.
    </p>
  </div>
<?php else: ?>

<div class="kanban" data-kanban>
  <?php foreach ($colonnes as $cle => $colonne): ?>
    <?php $cartes = $parColonne[$cle]; ?>
    <section class="kanban__colonne" data-colonne="<?= e($cle) ?>">
      <header class="kanban__entete">
        <span aria-hidden="true"><?= e($colonne['icone']) ?></span>
        <h2><?= e($colonne['titre']) ?></h2>
        <span class="kanban__compteur"><?= count($cartes) ?></span>
      </header>

      <div class="kanban__pile">
        <?php if ($cartes === []): ?>
          <p class="kanban__vide">Déposez une carte ici.</p>
        <?php endif; ?>

        <?php foreach ($cartes as $carte): ?>
          <?php
          $etat  = echeance_etat($carte['echeance'], $cle === 'termine');
          $texte = echeance_libelle($carte['echeance'], $cle === 'termine');
          ?>
          <article class="kanban-carte<?= $cle === 'termine' ? ' kanban-carte--faite' : '' ?>"
                   style="border-left-color:<?= e($carte['couleur']) ?>"
                   data-carte="<?= (int) $carte['id'] ?>"
                   data-nature="<?= e($carte['nature']) ?>">
            <a class="kanban-carte__titre" href="<?= e($carte['lien']) ?>">
              <span aria-hidden="true"><?= e($carte['icone']) ?></span>
              <?= e($carte['titre']) ?>
            </a>
            <p class="kanban-carte__meta"><?= e($carte['origine']) ?></p>
            <?php if ($texte !== ''): ?>
              <span class="echeance echeance--<?= e($etat) ?>"><?= e($texte) ?></span>
            <?php else: ?>
              <span class="echeance">Sans échéance</span>
            <?php endif; ?>

            <?php
            // Une remarque enregistrée s'affiche simplement : on clique dessus
            // pour la reprendre, la corbeille l'efface. Le formulaire ne
            // réapparaît qu'à la demande.
            $champNote = 'note-' . $carte['nature'] . '-' . $carte['id'];
            $aUneNote  = $carte['note'] !== '';
            ?>
            <div class="kanban-note<?= $aUneNote ? ' kanban-note--remplie' : '' ?>">
              <details>
                <summary title="<?= $aUneNote ? 'Modifier la remarque' : 'Ajouter une remarque' ?>">
                  <?php if ($aUneNote): ?>
                    <span class="kanban-note__texte">📝 <?= e($carte['note']) ?></span>
                  <?php else: ?>
                    <span class="kanban-note__ajout">📝 Ajouter une remarque</span>
                  <?php endif; ?>
                  <span class="kanban-note__icone" aria-hidden="true"><?= $aUneNote ? '✎' : '+' ?></span>
                </summary>

                <form method="post" action="<?= url('tableau/note') ?>">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
                  <input type="hidden" name="carte" value="<?= (int) $carte['id'] ?>">
                  <input type="hidden" name="nature" value="<?= e($carte['nature']) ?>">
                  <label class="sr-only" for="<?= e($champNote) ?>">
                    Remarque sur « <?= e($carte['titre']) ?> »
                  </label>
                  <textarea id="<?= e($champNote) ?>" name="note" rows="3" maxlength="500"
                            placeholder="Revoir la partie 3 avant de rendre…"><?= e($carte['note']) ?></textarea>
                  <button class="bouton bouton--petit bouton--bloc" type="submit">Enregistrer</button>
                </form>
              </details>

              <?php if ($aUneNote): ?>
                <form method="post" action="<?= url('tableau/note') ?>" class="kanban-note__supprimer"
                      data-confirmation="Supprimer cette remarque ?">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
                  <input type="hidden" name="carte" value="<?= (int) $carte['id'] ?>">
                  <input type="hidden" name="nature" value="<?= e($carte['nature']) ?>">
                  <input type="hidden" name="note" value="">
                  <button type="submit" title="Supprimer la remarque"
                          aria-label="Supprimer la remarque de « <?= e($carte['titre']) ?> »">🗑</button>
                </form>
              <?php endif; ?>
            </div>

            <?php // Sans glisser-déposer, ces boutons déplacent la carte. ?>
            <div class="kanban-carte__deplacer">
              <?php foreach ($colonnes as $vers => $autre): ?>
                <?php if ($vers === $cle) { continue; } ?>
                <form method="post" action="<?= url('tableau/deplacer') ?>">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
                  <input type="hidden" name="carte" value="<?= (int) $carte['id'] ?>">
                  <input type="hidden" name="nature" value="<?= e($carte['nature']) ?>">
                  <input type="hidden" name="colonne" value="<?= e($vers) ?>">
                  <button type="submit" title="Déplacer vers « <?= e($autre['titre']) ?> »">
                    <?= e($autre['icone']) ?>
                  </button>
                </form>
              <?php endforeach; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>

<?php // Le glisser-déposer poste ici la carte et sa colonne d'arrivée. ?>
<form method="post" action="<?= url('tableau/deplacer') ?>" id="forme-kanban" hidden>
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
  <input type="hidden" name="carte" value="">
  <input type="hidden" name="nature" value="">
  <input type="hidden" name="colonne" value="">
</form>

<?php endif; ?>
