<?php
/** @var array $listes, $taches, $compteurs, $palette, $icones @var ?int $listeOuverte @var string $vue */
$csrf = Session::jetonCsrf();

$onglets = [
    'tout'       => ['Mes listes',   $compteurs['a_faire']],
    'retard'     => ['En retard',    $compteurs['retard']],
    'aujourdhui' => ["Aujourd'hui",  $compteurs['aujourdhui']],
    'semaine'    => ['Cette semaine', $compteurs['semaine']],
    'terminees'  => ['Terminées',    $compteurs['terminees']],
];

/** La liste actuellement ouverte dans le volet. */
$ouverte = null;
foreach ($listes as $l) {
    if ((int) $l['id'] === (int) $listeOuverte) {
        $ouverte = $l;
        break;
    }
}

$aFaire    = array_filter($taches, static fn (array $t): bool => (int) $t['faite'] === 0);
$terminees = array_filter($taches, static fn (array $t): bool => (int) $t['faite'] === 1);

/** Le contexte à conserver quand un formulaire renvoie sur cette page. */
$contexte = static function () use ($vue, $listeOuverte, $csrf): string {
    $html  = '<input type="hidden" name="_csrf" value="' . e($csrf) . '">';
    $html .= '<input type="hidden" name="vue" value="' . e($vue) . '">';
    if ($listeOuverte !== null) {
        $html .= '<input type="hidden" name="liste" value="' . (int) $listeOuverte . '">';
    }
    return $html;
};

/** Une ligne de tâche : la case, le libellé, l'échéance, les actions. */
$ligneTache = static function (array $t) use ($csrf, $contexte, $listes, $vue): string {
    $faite = (int) $t['faite'] === 1;
    $etat  = echeance_etat($t['echeance'], $faite);
    $texte = echeance_libelle($t['echeance'], $faite);
    ob_start(); ?>
    <li class="tache<?= $faite ? ' tache--faite' : '' ?>">
      <form method="post" action="<?= url('taches/' . $t['id'] . '/cocher') ?>" class="tache__bascule">
        <?= $contexte() ?>
        <input type="checkbox" class="tache__case" id="case-<?= (int) $t['id'] ?>"
               data-envoi-immediat<?= $faite ? ' checked' : '' ?>
               title="<?= $faite ? 'Décocher' : 'Marquer comme faite' ?>">
        <noscript><button class="bouton bouton--petit" type="submit">OK</button></noscript>
      </form>

      <label class="tache__titre" for="case-<?= (int) $t['id'] ?>">
        <?= e($t['titre']) ?>
        <?php if (isset($t['liste_nom'])): ?>
          <span class="tache__liste"><?= e($t['liste_icone'] . ' ' . $t['liste_nom']) ?></span>
        <?php endif; ?>
      </label>

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
        <?= $contexte() ?>
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
    <p>Une liste est une tâche principale. Cliquez dessus pour voir ce qu'elle contient.</p>
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

<div class="taches-vue">

  <!-- Colonne de gauche : rien que les tâches principales. -->
  <div class="pile">
    <?php if ($listes === []): ?>
      <div class="vide">
        <span class="vide__icone">📋</span>
        <p>
          Aucune liste pour l'instant. Créez-en une ci-contre — « Cette semaine »,
          « Dossier d'inscription », « Courses »… — puis ajoutez-y vos tâches.
        </p>
      </div>
    <?php else: ?>
      <?php foreach ($listes as $liste): ?>
        <?php
        $total     = (int) $liste['reste'] + (int) $liste['finies'];
        $terminee  = $total > 0 && (int) $liste['reste'] === 0;
        $partielle = (int) $liste['finies'] > 0 && (int) $liste['reste'] > 0;
        $active    = (int) $liste['id'] === (int) $listeOuverte && $vue === 'tout';
        ?>
        <div class="liste-carte<?= $active ? ' liste-carte--active' : '' ?><?= $terminee ? ' liste-carte--faite' : '' ?>"
             style="border-left-color:<?= e($liste['couleur']) ?>">
          <form method="post" action="<?= url('taches/listes/' . $liste['id'] . '/cocher') ?>"
                class="tache__bascule">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="vue" value="<?= e($vue) ?>">
            <input type="hidden" name="liste" value="<?= (int) $liste['id'] ?>">
            <input type="checkbox" class="tache__case tache__case--liste"
                   data-envoi-immediat<?= $terminee ? ' checked' : '' ?><?= $partielle ? ' data-partiel' : '' ?>
                   <?= $total === 0 ? ' disabled' : '' ?>
                   aria-label="<?= $terminee ? 'Rouvrir' : 'Terminer' ?> la liste <?= e($liste['nom']) ?>"
                   title="<?= $total === 0
                       ? 'Liste vide'
                       : ($terminee ? 'Rouvrir toute la liste' : 'Terminer toute la liste') ?>">
            <noscript><button class="bouton bouton--petit" type="submit">OK</button></noscript>
          </form>

          <a class="liste-carte__lien" href="<?= url('taches', ['liste' => $liste['id']]) ?>#volet"
             aria-label="Ouvrir la liste <?= e($liste['nom']) ?>"
             <?= $active ? ' aria-current="true"' : '' ?>>
            <span class="liste-carte__icone" aria-hidden="true"><?= e($liste['icone']) ?></span>
            <span style="flex:1;min-width:0">
              <span class="liste-carte__nom"><?= e($liste['nom']) ?></span>
              <span class="liste-carte__meta">
                <?php if ($total === 0): ?>
                  vide
                <?php elseif ($terminee): ?>
                  tout est fait 🎉
                <?php else: ?>
                  <?= (int) $liste['reste'] ?> à faire
                  <?php if ((int) $liste['en_retard'] > 0): ?>
                    · <strong class="alerte"><?= (int) $liste['en_retard'] ?> en retard</strong>
                  <?php elseif ($liste['prochaine'] !== null): ?>
                    · <?= e(echeance_libelle((string) $liste['prochaine'])) ?>
                  <?php endif; ?>
                <?php endif; ?>
              </span>
            </span>
            <span class="liste-carte__chevron" aria-hidden="true">›</span>
          </a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <details class="carte nouvelle-liste"<?= $listes === [] ? ' open' : '' ?>>
      <summary>+ Nouvelle liste</summary>
      <form method="post" action="<?= url('taches/listes') ?>" style="margin-top:.9rem">
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
    </details>
  </div>

  <!-- Volet de droite : la tâche principale ouverte, et ses sous-tâches. -->
  <div class="volet" id="volet">
    <?php if ($vue !== 'tout'): ?>
      <?php $titres = ['retard' => '⏰ En retard', 'aujourdhui' => "📅 Aujourd'hui",
                       'semaine' => '🗓️ Cette semaine', 'terminees' => '✓ Terminées']; ?>
      <section class="carte">
        <div class="volet__entete">
          <div style="flex:1;min-width:0">
            <h2 style="margin:0"><?= e($titres[$vue] ?? '') ?></h2>
            <p class="discret" style="margin:.15rem 0 0;font-size:.84rem">
              <?= count($taches) ?> tâche<?= count($taches) > 1 ? 's' : '' ?>, toutes listes confondues
            </p>
          </div>
          <a class="bouton bouton--discret bouton--petit" href="<?= url('taches') ?>">← Mes listes</a>
        </div>

        <?php if ($taches === []): ?>
          <p class="discret" style="margin:1rem 0 0">Rien à afficher ici. Bonne nouvelle.</p>
        <?php else: ?>
          <ul class="taches">
            <?php foreach ($taches as $t): ?><?= $ligneTache($t) ?><?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

    <?php elseif ($ouverte === null): ?>
      <div class="vide">
        <span class="vide__icone">👈</span>
        <p>
          <?= $listes === []
              ? 'Créez votre première liste : elle s’ouvrira ici.'
              : 'Cliquez sur une tâche principale à gauche pour voir ce qu’elle contient.' ?>
        </p>
      </div>

    <?php else: ?>
      <?php
      $total     = (int) $ouverte['reste'] + (int) $ouverte['finies'];
      $terminee  = $total > 0 && (int) $ouverte['reste'] === 0;
      $partielle = (int) $ouverte['finies'] > 0 && (int) $ouverte['reste'] > 0;
      ?>
      <section class="carte volet__carte<?= $terminee ? ' liste-taches--faite' : '' ?>"
               style="border-top:4px solid <?= e($ouverte['couleur']) ?>">

        <div class="volet__entete">
          <form method="post" action="<?= url('taches/listes/' . $ouverte['id'] . '/cocher') ?>"
                class="tache__bascule">
            <?= $contexte() ?>
            <input type="checkbox" class="tache__case tache__case--liste" id="volet-case"
                   data-envoi-immediat<?= $terminee ? ' checked' : '' ?><?= $partielle ? ' data-partiel' : '' ?>
                   <?= $total === 0 ? ' disabled' : '' ?>
                   title="<?= $total === 0
                       ? 'Liste vide'
                       : ($terminee ? 'Rouvrir toute la liste' : 'Terminer toute la liste') ?>">
            <noscript><button class="bouton bouton--petit" type="submit">OK</button></noscript>
          </form>

          <span class="volet__icone" aria-hidden="true"><?= e($ouverte['icone']) ?></span>

          <div style="flex:1;min-width:0">
            <h2 style="margin:0"><label for="volet-case"><?= e($ouverte['nom']) ?></label></h2>
            <p class="discret" style="margin:.15rem 0 0;font-size:.84rem">
              <?php if ($total === 0): ?>
                Aucune sous-tâche pour l'instant
              <?php elseif ($terminee): ?>
                Tout est fait 🎉
              <?php else: ?>
                <?= (int) $ouverte['reste'] ?> à faire sur <?= $total ?>
                <?php if ((int) $ouverte['en_retard'] > 0): ?>
                  · <strong class="alerte"><?= (int) $ouverte['en_retard'] ?> en retard</strong>
                <?php elseif ($ouverte['prochaine'] !== null): ?>
                  · prochaine : <?= e(echeance_libelle((string) $ouverte['prochaine'])) ?>
                <?php endif; ?>
              <?php endif; ?>
            </p>
          </div>

          <button class="bouton bouton--secondaire bouton--petit" type="button"
                  data-bascule="reglages-liste">Modifier</button>
        </div>

        <div id="reglages-liste" hidden class="liste-taches__reglages">
          <hr class="separateur" style="margin:.75rem 0">
          <form method="post" action="<?= url('taches/listes/' . $ouverte['id'] . '/modifier') ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="champ">
              <label for="ln">Nom de la liste</label>
              <input type="text" id="ln" name="nom" required maxlength="120" value="<?= e($ouverte['nom']) ?>">
            </div>

            <div class="champ">
              <span class="legende">Icône</span>
              <div class="choix-icones">
                <?php foreach ($icones as $i => $icone): ?>
                  <input type="radio" id="li-<?= $i ?>" name="icone" value="<?= e($icone) ?>"
                         <?= $ouverte['icone'] === $icone ? ' checked' : '' ?>>
                  <label for="li-<?= $i ?>"><?= e($icone) ?></label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="champ">
              <span class="legende">Couleur</span>
              <div class="choix-couleurs">
                <?php foreach ($palette as $i => $couleur): ?>
                  <input type="radio" id="lc-<?= $i ?>" name="couleur" value="<?= e($couleur) ?>"
                         <?= strtolower((string) $ouverte['couleur']) === $couleur ? ' checked' : '' ?>>
                  <label for="lc-<?= $i ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="actions">
              <button class="bouton bouton--petit" type="submit">Enregistrer</button>
            </div>
          </form>

          <div class="actions" style="margin-top:.75rem">
            <?php if ((int) $ouverte['finies'] > 0): ?>
              <form method="post" action="<?= url('taches/listes/' . $ouverte['id'] . '/vider') ?>"
                    data-confirmation="Retirer les tâches déjà cochées de cette liste ?">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button class="bouton bouton--discret bouton--petit" type="submit">
                  Retirer les <?= (int) $ouverte['finies'] ?> terminée<?= (int) $ouverte['finies'] > 1 ? 's' : '' ?>
                </button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= url('taches/listes/' . $ouverte['id'] . '/supprimer') ?>"
                  data-confirmation="Supprimer cette liste et toutes ses tâches ? C'est définitif.">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--danger bouton--petit" type="submit">Supprimer la liste</button>
            </form>
          </div>
        </div>

        <?php if ($aFaire !== []): ?>
          <ul class="taches">
            <?php foreach ($aFaire as $t): ?><?= $ligneTache($t) ?><?php endforeach; ?>
          </ul>
        <?php elseif ($terminees === []): ?>
          <p class="discret" style="margin:1rem 0 0">
            Aucune sous-tâche. Ajoutez la première ci-dessous.
          </p>
        <?php endif; ?>

        <?php if ($terminees !== []): ?>
          <details class="taches-terminees">
            <summary><?= count($terminees) ?> terminée<?= count($terminees) > 1 ? 's' : '' ?></summary>
            <ul class="taches">
              <?php foreach ($terminees as $t): ?><?= $ligneTache($t) ?><?php endforeach; ?>
            </ul>
          </details>
        <?php endif; ?>

        <form method="post" action="<?= url('taches') ?>" class="tache-ajout">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="liste_id" value="<?= (int) $ouverte['id'] ?>">
          <input type="text" name="titre" required maxlength="200" placeholder="Ajouter une sous-tâche…"
                 aria-label="Nouvelle sous-tâche dans <?= e($ouverte['nom']) ?>">
          <input type="date" name="echeance" aria-label="Échéance (facultative)">
          <button class="bouton bouton--petit" type="submit">Ajouter</button>
        </form>
      </section>
    <?php endif; ?>
  </div>
</div>
