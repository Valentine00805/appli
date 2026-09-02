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

<div class="<?= $dossiers === [] ? '' : 'cours-vue' ?>">

<?php if ($dossiers !== []): ?>
  <?php
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
  $parNiveau = [];
  foreach ($dossiers as $d) {
      $parNiveau[(int) ($d['parent_id'] ?? 0)][] = $d;
  }
  ?>
  <aside class="cours-dossiers" data-dossiers-cibles>
    <p class="cours-dossiers__titre">Dossiers</p>

    <a class="dossier-cible<?= $dossierId === null ? ' dossier-cible--active' : '' ?>"
       href="<?= $lienDossier(null) ?>">
      <span aria-hidden="true">🗃️</span>
      <span style="flex:1;min-width:0">Tous les cours</span>
      <span class="dossier-cible__compte"><?= (int) $total ?></span>
    </a>

    <?php
    $rendreCible = function (array $d, int $profondeur) use (&$rendreCible, $parNiveau, $dossierId, $lienDossier): void {
        ?>
        <a class="dossier-cible<?= $dossierId === (int) $d['id'] ? ' dossier-cible--active' : '' ?>"
           href="<?= $lienDossier((int) $d['id']) ?>"
           style="padding-left:<?= 0.7 + $profondeur * 0.9 ?>rem"
           data-dossier="<?= (int) $d['id'] ?>"
           title="Déposez un cours ici pour le ranger dans « <?= e($d['nom']) ?> »">
          <span aria-hidden="true"><?= e($d['icone']) ?></span>
          <span style="flex:1;min-width:0"><?= e($d['nom']) ?></span>
          <span class="dossier-cible__compte"><?= (int) $d['nb_cours'] ?></span>
        </a>
        <?php
        foreach ($parNiveau[(int) $d['id']] ?? [] as $enfant) {
            $rendreCible($enfant, $profondeur + 1);
        }
    };
    foreach ($parNiveau[0] ?? [] as $racine) {
        $rendreCible($racine, 0);
    }
    ?>

    <a class="dossier-cible" href="<?= $lienDossier(null) ?>" data-dossier=""
       title="Déposez un cours ici pour le sortir de son dossier">
      <span aria-hidden="true">➖</span>
      <span style="flex:1;min-width:0">Sans dossier</span>
      <span class="dossier-cible__compte"><?= (int) $sansDossier ?></span>
    </a>

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
<?php if ($cours === []): ?>
  <div class="vide">
    <span class="vide__icone">📄</span>
    <?php if ($recherche !== '' || $matiereId !== null || $tagId !== null || $dossierId !== null || $favoris): ?>
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
            <span class="pastille" title="Dossier"><?= e($c['dossier_icone'] . ' ' . $c['dossier_nom']) ?></span>
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
