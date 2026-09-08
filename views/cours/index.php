<?php
/**
 * @var array $cours, $matieres, $tags, $dossiers
 * @var int $sansDossier, $total
 * @var string $recherche, $tri
 * @var ?int $matiereId, $tagId, $dossierId
 * @var bool $favoris
 */
?>

<div class="entete-page">
  <div>
    <h1>Mes cours</h1>
    <p><?= count($cours) ?> cours affiché<?= count($cours) > 1 ? 's' : '' ?></p>
  </div>
  <div class="actions">
    <?php
    /*
     * Déposer un dossier entier : un cours par fichier, l'arborescence reprise.
     *
     * Le bloc ne se montre qu'avec JavaScript, car un envoi ordinaire ne
     * transmet que le nom des fichiers et jamais leur chemin : les
     * sous-dossiers seraient perdus. Sans script, le dépôt fichier par fichier
     * fait le même travail, en plus long.
     *
     * Il est ici, et non dans la colonne des dossiers, parce que celle-ci ne
     * paraît qu'une fois qu'on a des dossiers — et l'import est justement ce
     * qui en crée les premiers.
     */
    ?>
    <span class="import-dossier" data-import-dossier hidden
          data-url="<?= url('cours/depot-dossier') ?>"
          data-jeton="<?= e(Session::jetonCsrf()) ?>"
          data-dossier="<?= $dossierId === null ? '' : (int) $dossierId ?>">
      <label class="bouton bouton--secondaire" for="import-dossier-champ">📁 Importer un dossier</label>
      <input type="file" id="import-dossier-champ" class="sr-only"
             data-import-champ webkitdirectory directory multiple>
      <span class="import-dossier__etat" data-import-etat>
        Un cours par fichier, vos sous-dossiers repris.
      </span>
    </span>
    <a class="bouton" href="<?= url('cours/nouveau') ?>">+ Nouveau cours</a>
  </div>
</div>

<form class="filtres" method="get" action="<?= url('cours') ?>" data-auto-envoi>
  <div class="champ">
    <label for="f-q">Rechercher</label>
    <input type="search" id="f-q" name="q" value="<?= e($recherche) ?>" placeholder="titre, contenu…">
  </div>

  <div class="champ">
    <label for="f-matiere">Matière</label>
    <select id="f-matiere" name="matiere">
      <option value="">Toutes</option>
      <?php foreach ($matieres as $m): ?>
        <option value="<?= (int) $m['id'] ?>"<?= $matiereId === (int) $m['id'] ? ' selected' : '' ?>>
          <?= e($m['nom']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <?php if ($tags !== []): ?>
    <div class="champ">
      <label for="f-tag">Tag</label>
      <select id="f-tag" name="tag">
        <option value="">Tous</option>
        <?php foreach ($tags as $t): ?>
          <option value="<?= (int) $t['id'] ?>"<?= $tagId === (int) $t['id'] ? ' selected' : '' ?>>
            <?= e($t['nom']) ?> (<?= (int) $t['nb'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <?php // Le dossier se choisit dans la colonne de gauche ; on le conserve ici. ?>
  <?php if ($dossierId !== null): ?>
    <input type="hidden" name="dossier" value="<?= (int) $dossierId ?>">
  <?php endif; ?>

  <div class="champ">
    <label for="f-tri">Trier par</label>
    <select id="f-tri" name="tri">
      <option value="recent"<?= $tri === 'recent' ? ' selected' : '' ?>>Modifié récemment</option>
      <option value="titre"<?= $tri === 'titre' ? ' selected' : '' ?>>Titre (A→Z)</option>
      <option value="ancien"<?= $tri === 'ancien' ? ' selected' : '' ?>>Plus ancien d'abord</option>
    </select>
  </div>

  <label class="case" style="padding-bottom:.55rem">
    <input type="checkbox" name="favoris" value="1"<?= $favoris ? ' checked' : '' ?>
           onchange="this.form.submit()"> Favoris
  </label>

  <button class="bouton bouton--secondaire" type="submit">Filtrer</button>
  <?php if ($recherche !== '' || $matiereId !== null || $tagId !== null || $dossierId !== null || $favoris): ?>
    <a class="bouton bouton--discret" href="<?= url('cours') ?>">Réinitialiser</a>
  <?php endif; ?>
</form>

<?php
/*
 * Le lien vers un dossier garde les filtres en cours : changer de dossier ne
 * doit pas défaire la recherche ou le tri qu'on venait de poser.
 */
$lienDossier = static function (?int $id) use ($recherche, $matiereId, $tagId, $tri, $favoris): string {
    $params = array_filter([
        'q' => $recherche !== '' ? $recherche : null,
        'matiere' => $matiereId,
        'tag' => $tagId,
        'dossier' => $id,
        'tri' => $tri !== 'recent' ? $tri : null,
        'favoris' => $favoris ? '1' : null,
    ], static fn ($v): bool => $v !== null);
    return url('cours', $params);
};

// Les dossiers par identifiant, et les enfants de chacun : de quoi savoir
// où l'on est, et ce qu'on peut ouvrir depuis là.
$dossierParId = [];
$enfantsDe = [];
foreach ($dossiers as $d) {
    $dossierParId[(int) $d['id']] = $d;
    $enfantsDe[(int) ($d['parent_id'] ?? 0)][] = $d;
}
?>

<div class="<?= $dossiers === [] ? '' : 'cours-vue' ?>">

<?php if ($dossiers !== []): ?>
  <?php
  $parNiveau = [];
  foreach ($dossiers as $d) {
      $parNiveau[(int) ($d['parent_id'] ?? 0)][] = $d;
  }
  ?>
  <aside class="cours-dossiers" data-dossiers-cibles>
    <p class="cours-dossiers__titre">Dossiers</p>

    <?php
    /*
     * Chaque ligne tient dans un rang, avec devant elle la place du bouton
     * qui replie. Le bouton ne paraît qu'avec JavaScript : sans lui, la
     * colonne reste dépliée, et la place reste vide pour que tout s'aligne.
     */
    ?>
    <div class="dossier-rang">
      <span class="dossier-plier dossier-plier--vide" aria-hidden="true" hidden></span>
      <a class="dossier-cible<?= $dossierId === null ? ' dossier-cible--active' : '' ?>"
         href="<?= $lienDossier(null) ?>">
        <span aria-hidden="true">🗃️</span>
        <span style="flex:1;min-width:0">Tous les cours</span>
        <span class="dossier-cible__compte"><?= (int) $total ?></span>
      </a>
    </div>

    <?php
    $rendreCible = function (array $d, int $profondeur) use (&$rendreCible, $parNiveau, $dossierId, $lienDossier): void {
        $enfants = $parNiveau[(int) $d['id']] ?? [];
        ?>
        <div class="dossier-rang" data-rang="<?= (int) $d['id'] ?>"
             data-parent="<?= (int) ($d['parent_id'] ?? 0) ?>"
             data-nom="<?= e($d['nom']) ?>"
             style="padding-left:<?= $profondeur * 0.9 ?>rem">
          <?php if ($enfants !== []): ?>
            <button type="button" class="dossier-plier" data-plier="<?= (int) $d['id'] ?>"
                    aria-expanded="true" hidden>▾</button>
          <?php else: ?>
            <span class="dossier-plier dossier-plier--vide" aria-hidden="true" hidden></span>
          <?php endif; ?>
          <a class="dossier-cible<?= $dossierId === (int) $d['id'] ? ' dossier-cible--active' : '' ?>"
             href="<?= $lienDossier((int) $d['id']) ?>"
             data-dossier="<?= (int) $d['id'] ?>"
             title="Déposez un cours ici pour le ranger dans « <?= e($d['nom']) ?> »">
            <span aria-hidden="true"><?= e($d['icone']) ?></span>
            <span style="flex:1;min-width:0"><?= e($d['nom']) ?></span>
            <span class="dossier-cible__compte"><?= (int) $d['nb_cours'] ?></span>
          </a>

          <?php
          /*
           * Renommer sur place. Le formulaire renvoie aussi le parent, la
           * couleur et l'icône : la mise à jour réécrit toute la ligne, et ce
           * qu'on ne lui redonne pas serait perdu — le dossier remonterait à
           * la racine avec les couleurs d'origine.
           *
           * Un « details » plutôt qu'un script : le champ s'ouvre et se ferme
           * sans JavaScript, comme partout ailleurs dans l'application.
           */
          ?>
          <details class="dossier-renommer">
            <summary title="Renommer « <?= e($d['nom']) ?> »">
              <span aria-hidden="true">✎</span>
              <span class="sr-only">Renommer <?= e($d['nom']) ?></span>
            </summary>
            <div class="dossier-renommer__panneau">
              <form class="dossier-renommer__ligne" method="post"
                    action="<?= url('dossiers/' . (int) $d['id'] . '/modifier') ?>">
                <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
                <input type="hidden" name="parent_id"
                       value="<?= $d['parent_id'] === null ? '' : (int) $d['parent_id'] ?>">
                <input type="hidden" name="couleur" value="<?= e($d['couleur']) ?>">
                <input type="hidden" name="icone" value="<?= e($d['icone']) ?>">
                <label class="sr-only" for="renommer-<?= (int) $d['id'] ?>">Nouveau nom</label>
                <input type="text" id="renommer-<?= (int) $d['id'] ?>" name="nom"
                       value="<?= e($d['nom']) ?>" maxlength="120" required>
                <button class="bouton bouton--petit" type="submit">Renommer</button>
              </form>

              <?php
              /*
               * Supprimer un dossier n'emporte rien : ses cours restent, sans
               * dossier, et ses sous-dossiers remontent à la racine. La
               * question le dit, pour qu'on ne l'imagine pas plus grave qu'il
               * n'est — ni moins.
               */
              $garde = ['Supprimer le dossier « ' . $d['nom'] . ' » ?'];
              if ((int) $d['nb_cours'] > 0) {
                  $garde[] = 'Ses ' . (int) $d['nb_cours'] . ' cours '
                      . ((int) $d['nb_cours'] > 1 ? 'seront conservés' : 'sera conservé') . ', sans dossier.';
              }
              if ($enfants !== []) {
                  $garde[] = 'Ses ' . count($enfants) . ' sous-dossier'
                      . (count($enfants) > 1 ? 's remonteront' : ' remontera') . ' à la racine.';
              }
              ?>
              <form method="post" action="<?= url('dossiers/' . (int) $d['id'] . '/supprimer') ?>"
                    data-confirmation="<?= e(implode(' ', $garde)) ?>">
                <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
                <button class="bouton bouton--petit bouton--danger bouton--bloc" type="submit">
                  Supprimer le dossier
                </button>
              </form>
            </div>
          </details>
        </div>
        <?php
        foreach ($enfants as $enfant) {
            $rendreCible($enfant, $profondeur + 1);
        }
    };
    foreach ($parNiveau[0] ?? [] as $racine) {
        $rendreCible($racine, 0);
    }
    ?>

    <div class="dossier-rang">
      <span class="dossier-plier dossier-plier--vide" aria-hidden="true" hidden></span>
      <a class="dossier-cible" href="<?= $lienDossier(null) ?>" data-dossier=""
         title="Déposez un cours ici pour le sortir de son dossier">
        <span aria-hidden="true">➖</span>
        <span style="flex:1;min-width:0">Sans dossier</span>
        <span class="dossier-cible__compte"><?= (int) $sansDossier ?></span>
      </a>
    </div>

    <?php
    /*
     * Créer un dossier sans quitter la page. « Ranger dans » part du dossier
     * ouvert, qui est presque toujours le bon : on crée un sous-dossier là où
     * l'on se trouve. La liste reste modifiable, pour les fois où ce n'est pas
     * le cas — et elle dit, du même coup, où le dossier ira.
     */
    ?>
    <details class="nouveau-dossier">
      <summary class="bouton bouton--secondaire bouton--petit bouton--bloc">＋ Nouveau dossier</summary>
      <form class="nouveau-dossier__panneau" method="post" action="<?= url('dossiers') ?>">
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
        <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">

        <label class="sr-only" for="nouveau-dossier-nom">Nom du dossier</label>
        <input type="text" id="nouveau-dossier-nom" name="nom" maxlength="120"
               placeholder="Nom du dossier" required>

        <label for="nouveau-dossier-parent">Ranger dans</label>
        <select id="nouveau-dossier-parent" name="parent_id">
          <option value="">— À la racine</option>
          <?php foreach ($dossiers as $d): ?>
            <option value="<?= (int) $d['id'] ?>"<?= $dossierId === (int) $d['id'] ? ' selected' : '' ?>>
              <?= retrait_dossier($d) ?><?= e($d['nom']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <button class="bouton bouton--petit bouton--bloc" type="submit">Créer le dossier</button>
      </form>
    </details>

    <p class="champ__aide" style="margin:.6rem .2rem 0">
      Faites glisser un cours sur un dossier pour l'y ranger. Un fichier déposé
      sur un dossier y crée un cours qui le contient.
    </p>
  </aside>

  <?php // Un fichier venu du bureau passe par ici : un cours par fichier. ?>
  <form method="post" action="<?= url('cours/depot') ?>" enctype="multipart/form-data"
        id="forme-depot-dossier" hidden>
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
    <input type="hidden" name="dossier" value="">
    <input type="file" name="fichiers[]" multiple>
  </form>

  <?php // Le glisser-déposer poste ici le cours et son dossier d'arrivée. ?>
  <form method="post" action="<?= url('cours/ranger') ?>" id="forme-ranger-cours" hidden>
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
    <input type="hidden" name="cours" value="">
    <input type="hidden" name="dossier" value="">
  </form>
<?php endif; ?>

<div>
<?php
/*
 * Un dossier ouvert montre d'abord ce qu'il contient d'autres dossiers.
 *
 * La colonne de gauche donne l'arborescence entière ; ici on ne montre que
 * l'étage où l'on se trouve, avec de quoi remonter. Sans cela, un dossier qui
 * ne sert qu'à en ranger d'autres paraissait vide.
 */
$courant = $dossierId === null ? null : ($dossierParId[$dossierId] ?? null);
$sousDossiers = $courant === null ? [] : ($enfantsDe[(int) $courant['id']] ?? []);

$chemin = [];
for ($haut = $courant; $haut !== null;) {
    array_unshift($chemin, $haut);
    $dessus = $haut['parent_id'] === null ? null : (int) $haut['parent_id'];
    $haut = $dessus === null ? null : ($dossierParId[$dessus] ?? null);
}
?>
<?php if ($courant !== null): ?>
  <nav class="fil-dossiers" aria-label="Chemin du dossier">
    <a href="<?= $lienDossier(null) ?>">Tous les cours</a>
    <?php foreach ($chemin as $rang => $etape): ?>
      <span class="fil-dossiers__separateur" aria-hidden="true">›</span>
      <?php if ($rang === count($chemin) - 1): ?>
        <span aria-current="page"><?= e($etape['icone']) ?> <?= e($etape['nom']) ?></span>
      <?php else: ?>
        <a href="<?= $lienDossier((int) $etape['id']) ?>"><?= e($etape['nom']) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<?php if ($sousDossiers !== []): ?>
  <div class="dossiers-enfants">
    <?php foreach ($sousDossiers as $enfant): ?>
      <?php
      $dedans = count($enfantsDe[(int) $enfant['id']] ?? []);
      $lus = (int) $enfant['nb_cours'];
      ?>
      <a class="dossier-enfant" href="<?= $lienDossier((int) $enfant['id']) ?>"
         data-dossier="<?= (int) $enfant['id'] ?>"
         title="Ouvrir « <?= e($enfant['nom']) ?> »">
        <span class="dossier-enfant__icone" aria-hidden="true"><?= e($enfant['icone']) ?></span>
        <span class="dossier-enfant__nom"><?= e($enfant['nom']) ?></span>
        <span class="dossier-enfant__compte">
          <?= $lus ?> cours<?= $dedans > 0 ? ' · ' . $dedans . ' dossier' . ($dedans > 1 ? 's' : '') : '' ?>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($cours === []): ?>
  <div class="vide">
    <span class="vide__icone">📄</span>
    <?php if ($sousDossiers !== []): ?>
      <p>Ce dossier ne contient que des sous-dossiers. Ouvrez-en un ci-dessus.</p>
    <?php elseif ($recherche !== '' || $matiereId !== null || $tagId !== null || $dossierId !== null || $favoris): ?>
      <p>Aucun cours ne correspond à ces critères.</p>
      <a class="bouton bouton--secondaire" href="<?= url('cours') ?>">Voir tous les cours</a>
    <?php else: ?>
      <p>Vous n'avez pas encore de cours enregistré.</p>
      <a class="bouton" href="<?= url('cours/nouveau') ?>">Créer mon premier cours</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="grille grille--3">
    <?php foreach ($cours as $c): ?>
      <a class="carte cours-carte" href="<?= url('cours/' . $c['id']) ?>"
         data-cours="<?= (int) $c['id'] ?>">
        <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap">
          <?php if ($c['matiere_nom'] !== null): ?>
            <span class="pastille" style="background:<?= e($c['matiere_couleur']) ?>;color:<?= e(couleur_texte($c['matiere_couleur'])) ?>">
              <?= e($c['matiere_nom']) ?>
            </span>
          <?php else: ?>
            <span class="pastille">Sans matière</span>
          <?php endif; ?>
          <?php if ($c['dossier_nom'] !== null): ?>
            <span class="pastille" title="Dossier : <?= e((string) $c['dossier_nom']) ?>"><?= e($c['dossier_icone'] . ' ' . $c['dossier_nom']) ?></span>
          <?php endif; ?>
          <?php if ((int) $c['favori'] === 1): ?><span title="Favori">⭐</span><?php endif; ?>
        </div>

        <div class="cours-carte__titre"><?= e($c['titre']) ?></div>
        <p class="cours-carte__extrait"><?= e(extrait($c['contenu'])) ?></p>

        <div class="cours-carte__bas">
          <span>Modifié le <?= e(date_fr($c['updated_at'], false)) ?></span>
          <?php if ((int) $c['nb_fichiers'] > 0): ?>
            <span>· 📎 <?= (int) $c['nb_fichiers'] ?></span>
          <?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
</div>
