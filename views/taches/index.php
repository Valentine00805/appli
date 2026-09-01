<?php
/** @var array $listes, $parListe, $compteurs, $palette, $icones @var string $vue */
$csrf = Session::jetonCsrf();

$onglets = [
    'tout'       => ['À faire',      $compteurs['a_faire']],
    'retard'     => ['En retard',    $compteurs['retard']],
    'aujourdhui' => ["Aujourd'hui",  $compteurs['aujourdhui']],
    'semaine'    => ['Cette semaine', $compteurs['semaine']],
    'terminees'  => ['Terminées',    $compteurs['terminees']],
];

/** Une ligne de tâche : la case, le libellé, l'échéance, les actions. */
$ligneTache = static function (array $t) use ($csrf, $vue, $listes): string {
    $faite = (int) $t['faite'] === 1;
    $etat  = echeance_etat($t['echeance'], $faite);
    $texte = echeance_libelle($t['echeance'], $faite);
    ob_start(); ?>
    <li class="tache<?= $faite ? ' tache--faite' : '' ?>">
      <form method="post" action="<?= url('taches/' . $t['id'] . '/cocher') ?>" class="tache__bascule">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="vue" value="<?= e($vue) ?>">
        <button type="submit" class="tache__case" aria-pressed="<?= $faite ? 'true' : 'false' ?>"
                title="<?= $faite ? 'Décocher' : 'Marquer comme faite' ?>">
          <span aria-hidden="true"><?= $faite ? '✓' : '' ?></span>
          <span class="sr-only"><?= $faite ? 'Décocher' : 'Marquer comme faite' ?> : <?= e($t['titre']) ?></span>
        </button>
      </form>

      <span class="tache__titre"><?= e($t['titre']) ?></span>

      <?php if ($texte !== ''): ?>
        <span class="echeance echeance--<?= e($etat) ?>"><?= e($texte) ?></span>
      <?php endif; ?>

      <span class="tache__actions">
        <button class="bouton bouton--discret bouton--petit" type="button"
                data-bascule="tache-<?= (int) $t['id'] ?>">Modifier</button>
      </span>
    </li>

    <li id="tache-<?= (int) $t['id'] ?>" hidden class="tache-edition">
      <form method="post" action="<?= url('taches/' . $t['id'] . '/modifier') ?>" class="tache-edition__forme">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="champ">
          <label for="titre-<?= (int) $t['id'] ?>">Tâche</label>
          <input type="text" id="titre-<?= (int) $t['id'] ?>" name="titre" required maxlength="200"
                 value="<?= e($t['titre']) ?>">
        </div>
        <div class="ligne-champs">
          <div class="champ">
            <label for="ech-<?= (int) $t['id'] ?>">Échéance</label>
            <input type="date" id="ech-<?= (int) $t['id'] ?>" name="echeance"
                   value="<?= e((string) $t['echeance']) ?>">
          </div>
          <div class="champ">
            <label for="lst-<?= (int) $t['id'] ?>">Liste</label>
            <select id="lst-<?= (int) $t['id'] ?>" name="liste_id">
              <?php foreach ($listes as $l): ?>
                <option value="<?= (int) $l['id'] ?>"<?= (int) $l['id'] === (int) $t['liste_id'] ? ' selected' : '' ?>>
                  <?= e($l['icone'] . ' ' . $l['nom']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="actions">
          <button class="bouton bouton--petit" type="submit">Enregistrer</button>
        </div>
      </form>

      <form method="post" action="<?= url('taches/' . $t['id'] . '/supprimer') ?>"
            data-confirmation="Supprimer définitivement cette tâche ?">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="vue" value="<?= e($vue) ?>">
        <button class="bouton bouton--danger bouton--petit" type="submit">Supprimer</button>
      </form>
    </li>
    <?php
    return (string) ob_get_clean();
};
?>

<div class="entete-page">
  <div>
    <h1>Mes tâches</h1>
    <p>Des listes, une échéance quand il en faut une, et une case à cocher quand c'est fait.</p>
  </div>
</div>

<?php if ($listes !== []): ?>
  <nav class="onglets" aria-label="Filtrer les tâches">
    <?php foreach ($onglets as $cle => [$libelle, $nombre]): ?>
      <a href="<?= $cle === 'tout' ? url('taches') : url('taches', ['vue' => $cle]) ?>"
         <?= $vue === $cle ? ' aria-current="page"' : '' ?>>
        <?= e($libelle) ?>
        <?php if ($nombre > 0): ?>
          <span class="onglets__compteur<?= $cle === 'retard' ? ' onglets__compteur--alerte' : '' ?>"><?= $nombre ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<div class="colonnes">
  <div class="pile">
    <?php if ($listes === []): ?>
      <div class="vide">
        <span class="vide__icone">📋</span>
        <p>
          Aucune liste pour l'instant. Créez-en une à droite — « Cette semaine »,
          « Dossier d'inscription », « Courses »… — puis ajoutez-y vos tâches.
        </p>
      </div>
    <?php else: ?>
      <?php foreach ($listes as $liste): ?>
        <?php
        $taches   = $parListe[(int) $liste['id']] ?? [];
        $aFaire   = array_filter($taches, static fn (array $t): bool => (int) $t['faite'] === 0);
        $terminees = array_filter($taches, static fn (array $t): bool => (int) $t['faite'] === 1);
        ?>
        <section class="carte liste-taches" style="border-left:4px solid <?= e($liste['couleur']) ?>">
          <div class="liste-taches__entete">
            <span class="liste-taches__icone" aria-hidden="true"><?= e($liste['icone']) ?></span>
            <div style="flex:1;min-width:0">
              <h2 style="margin:0"><?= e($liste['nom']) ?></h2>
              <p class="discret" style="margin:.15rem 0 0;font-size:.84rem">
                <?php if ((int) $liste['reste'] === 0 && (int) $liste['finies'] > 0): ?>
                  Tout est fait 🎉
                <?php else: ?>
                  <?= (int) $liste['reste'] ?> à faire
                <?php endif; ?>
                <?php if ((int) $liste['en_retard'] > 0): ?>
                  · <strong style="color:var(--erreur)"><?= (int) $liste['en_retard'] ?> en retard</strong>
                <?php elseif ($liste['prochaine'] !== null): ?>
                  · prochaine : <?= e(echeance_libelle((string) $liste['prochaine'])) ?>
                <?php endif; ?>
                <?php if ((int) $liste['finies'] > 0): ?>
                  · <?= (int) $liste['finies'] ?> terminée<?= (int) $liste['finies'] > 1 ? 's' : '' ?>
                <?php endif; ?>
              </p>
            </div>
            <button class="bouton bouton--secondaire bouton--petit" type="button"
                    data-bascule="liste-<?= (int) $liste['id'] ?>">Modifier</button>
          </div>

          <div id="liste-<?= (int) $liste['id'] ?>" hidden class="liste-taches__reglages">
            <hr class="separateur" style="margin:.75rem 0">
            <form method="post" action="<?= url('taches/listes/' . $liste['id'] . '/modifier') ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <div class="champ">
                <label for="ln-<?= (int) $liste['id'] ?>">Nom de la liste</label>
                <input type="text" id="ln-<?= (int) $liste['id'] ?>" name="nom" required maxlength="120"
                       value="<?= e($liste['nom']) ?>">
              </div>

              <div class="champ">
                <span class="legende">Icône</span>
                <div class="choix-icones">
                  <?php foreach ($icones as $i => $icone): ?>
                    <?php $idIcone = 'li-' . $liste['id'] . '-' . $i; ?>
                    <input type="radio" id="<?= $idIcone ?>" name="icone" value="<?= e($icone) ?>"
                           <?= $liste['icone'] === $icone ? ' checked' : '' ?>>
                    <label for="<?= $idIcone ?>"><?= e($icone) ?></label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="champ">
                <span class="legende">Couleur</span>
                <div class="choix-couleurs">
                  <?php foreach ($palette as $i => $couleur): ?>
                    <?php $idCouleur = 'lc-' . $liste['id'] . '-' . $i; ?>
                    <input type="radio" id="<?= $idCouleur ?>" name="couleur" value="<?= e($couleur) ?>"
                           <?= strtolower((string) $liste['couleur']) === $couleur ? ' checked' : '' ?>>
                    <label for="<?= $idCouleur ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="actions">
                <button class="bouton bouton--petit" type="submit">Enregistrer</button>
              </div>
            </form>

            <div class="actions" style="margin-top:.75rem">
              <?php if ((int) $liste['finies'] > 0): ?>
                <form method="post" action="<?= url('taches/listes/' . $liste['id'] . '/vider') ?>"
                      data-confirmation="Retirer les tâches déjà cochées de cette liste ?">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <button class="bouton bouton--discret bouton--petit" type="submit">
                    Retirer les <?= (int) $liste['finies'] ?> terminée<?= (int) $liste['finies'] > 1 ? 's' : '' ?>
                  </button>
                </form>
              <?php endif; ?>
              <form method="post" action="<?= url('taches/listes/' . $liste['id'] . '/supprimer') ?>"
                    data-confirmation="Supprimer cette liste et toutes ses tâches ? C'est définitif.">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button class="bouton bouton--danger bouton--petit" type="submit">Supprimer la liste</button>
              </form>
            </div>
          </div>

          <?php if ($aFaire === [] && $terminees === []): ?>
            <p class="discret" style="margin:.9rem 0 0">
              <?php if ($vue === 'tout'): ?>
                Rien dans cette liste. Ajoutez une tâche ci-dessous.
              <?php else: ?>
                Rien à afficher avec ce filtre.
              <?php endif; ?>
            </p>
          <?php else: ?>
            <?php if ($aFaire !== []): ?>
              <ul class="taches">
                <?php foreach ($aFaire as $t): ?><?= $ligneTache($t) ?><?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <?php if ($terminees !== []): ?>
              <details class="taches-terminees"<?= $vue === 'terminees' ? ' open' : '' ?>>
                <summary><?= count($terminees) ?> terminée<?= count($terminees) > 1 ? 's' : '' ?></summary>
                <ul class="taches">
                  <?php foreach ($terminees as $t): ?><?= $ligneTache($t) ?><?php endforeach; ?>
                </ul>
              </details>
            <?php endif; ?>
          <?php endif; ?>

          <?php if ($vue === 'tout'): ?>
            <form method="post" action="<?= url('taches') ?>" class="tache-ajout">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="liste_id" value="<?= (int) $liste['id'] ?>">
              <input type="text" name="titre" required maxlength="200" placeholder="Ajouter une tâche…"
                     aria-label="Nouvelle tâche dans <?= e($liste['nom']) ?>">
              <input type="date" name="echeance" aria-label="Échéance (facultative)">
              <button class="bouton bouton--petit" type="submit">Ajouter</button>
            </form>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="pile">
    <div class="carte">
      <h2>Nouvelle liste</h2>
      <form method="post" action="<?= url('taches/listes') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

        <div class="champ">
          <label for="nom">Nom</label>
          <input type="text" id="nom" name="nom" required maxlength="120" placeholder="Cette semaine">
        </div>

        <div class="champ">
          <span class="legende">Icône</span>
          <div class="choix-icones">
            <?php foreach ($icones as $i => $icone): ?>
              <input type="radio" id="ni-<?= $i ?>" name="icone" value="<?= e($icone) ?>"<?= $i === 0 ? ' checked' : '' ?>>
              <label for="ni-<?= $i ?>"><?= e($icone) ?></label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="champ">
          <span class="legende">Couleur</span>
          <div class="choix-couleurs">
            <?php foreach ($palette as $i => $couleur): ?>
              <input type="radio" id="nc-<?= $i ?>" name="couleur" value="<?= e($couleur) ?>"<?= $i === 0 ? ' checked' : '' ?>>
              <label for="nc-<?= $i ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
            <?php endforeach; ?>
          </div>
        </div>

        <button class="bouton bouton--bloc" type="submit">Créer la liste</button>
      </form>
    </div>

    <?php if ($compteurs['retard'] > 0): ?>
      <div class="carte" style="border-color:var(--erreur)">
        <h2 style="color:var(--erreur)">⏰ En retard</h2>
        <p class="discret" style="margin:0">
          <?= $compteurs['retard'] ?> tâche<?= $compteurs['retard'] > 1 ? 's ont' : ' a' ?> dépassé son échéance.
          <a href="<?= url('taches', ['vue' => 'retard']) ?>">Les voir</a>.
        </p>
      </div>
    <?php endif; ?>

    <div class="carte">
      <h2>Comment ça marche</h2>
      <p class="discret" style="margin-bottom:.6rem">
        L'échéance est facultative : une liste de courses n'en a pas besoin, un
        dossier à rendre si.
      </p>
      <p class="discret" style="margin:0">
        Cocher une tâche ne l'efface pas — elle descend dans « terminées », et
        vous pouvez la décocher. Le ménage se fait à la main, quand vous voulez.
      </p>
    </div>
  </div>
</div>
