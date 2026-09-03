<?php
/**
 * @var array $garnies    les cours dont la fiche contient quelque chose
 * @var array $vides      ceux dont la fiche est encore blanche
 * @var string $recherche ce qui est cherché, vide sinon
 * @var array $termes     les mots de la recherche, pour le surlignage
 * @var array $matieres   les matières de l'utilisateur, pour le filtre
 * @var ?int $matiereId   la matière retenue, null pour toutes
 */
$matiereChoisie = null;
foreach ($matieres as $m) {
    if ((int) $m['id'] === $matiereId) {
        $matiereChoisie = $m['nom'];
    }
}
$filtreActif = $recherche !== '' || $matiereId !== null;

/** Les quatre compteurs d'une fiche, sans les zéros. */
$compteurs = static function (array $c): array {
    $lignes = [];
    foreach ([
        'nb_fichiers'   => ['📎', 'fichier', 'fichiers'],
        'nb_liens'      => ['🔗', 'lien', 'liens'],
        'nb_renvois'    => ['📘', 'renvoi', 'renvois'],
        'nb_evenements' => ['📅', 'échéance', 'échéances'],
    ] as $cle => [$icone, $singulier, $pluriel]) {
        $n = (int) $c[$cle];
        if ($n > 0) {
            $lignes[] = $icone . ' ' . $n . ' ' . ($n > 1 ? $pluriel : $singulier);
        }
    }
    return $lignes;
};
?>

<div class="entete-page">
  <div>
    <h1>📝 Révision</h1>
    <p>
      <?php if ($filtreActif): ?>
        <?= count($garnies) ?> fiche<?= count($garnies) > 1 ? 's' : '' ?>
        <?php if ($recherche !== ''): ?>pour « <?= e($recherche) ?> »<?php endif; ?>
        <?php if ($matiereChoisie !== null): ?>en <?= e($matiereChoisie) ?><?php endif; ?>.
        <a href="<?= url('revision') ?>">Tout revoir</a>
      <?php elseif ($garnies === []): ?>
        Vos fiches de révision se retrouveront ici.
      <?php else: ?>
        <?= count($garnies) ?> fiche<?= count($garnies) > 1 ? 's' : '' ?>,
        regroupée<?= count($garnies) > 1 ? 's' : '' ?> par matière.
      <?php endif; ?>
    </p>
  </div>
</div>

<form class="filtres" method="get" action="<?= url('revision') ?>" data-auto-envoi>
  <div class="champ">
    <label for="f-fiche-q">Rechercher</label>
    <input type="search" id="f-fiche-q" name="q" value="<?= e($recherche) ?>"
           placeholder="texte, lien, fichier…">
  </div>

  <div class="champ">
    <label for="f-fiche-matiere">Matière</label>
    <select id="f-fiche-matiere" name="matiere">
      <option value="">Toutes</option>
      <?php foreach ($matieres as $m): ?>
        <option value="<?= (int) $m['id'] ?>"<?= $matiereId === (int) $m['id'] ? ' selected' : '' ?>>
          <?= e($m['nom']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <button class="bouton bouton--secondaire" type="submit">Filtrer</button>
  <?php if ($filtreActif): ?>
    <a class="bouton bouton--discret" href="<?= url('revision') ?>">Réinitialiser</a>
  <?php endif; ?>
</form>

<?php if ($filtreActif && $garnies === [] && $vides === []): ?>
  <div class="vide">
    <span class="vide__icone">🔍</span>
    <?php if ($recherche !== ''): ?>
      <p>Rien pour « <?= e($recherche) ?> »<?php if ($matiereChoisie !== null): ?>
         en <?= e($matiereChoisie) ?><?php endif; ?> — ni dans le texte des fiches,
         ni dans les titres, les liens ou les noms de fichiers rattachés.</p>
    <?php else: ?>
      <p>Aucun cours en <?= e((string) $matiereChoisie) ?>.</p>
    <?php endif; ?>
    <a class="bouton bouton--secondaire" href="<?= url('revision') ?>">Revoir toutes les fiches</a>
  </div>
<?php elseif ($garnies === [] && $vides === []): ?>
  <div class="vide">
    <span class="vide__icone">📝</span>
    <p>Vous n'avez pas encore de cours. Une fiche de révision se rédige depuis un cours.</p>
    <a class="bouton" href="<?= url('cours/nouveau') ?>">Créer un cours</a>
  </div>
<?php else: ?>

  <?php if ($garnies === []): ?>
    <div class="vide">
      <span class="vide__icone">📝</span>
      <?php if ($filtreActif): ?>
        <p>Aucune fiche ne correspond. Le ou les cours ci-dessous n'ont pas
           encore la leur.</p>
      <?php else: ?>
        <p>Aucune fiche pour l'instant. Ouvrez un cours et cliquez sur
           <strong>📝 Révision</strong> pour commencer la sienne.</p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php $matiereEnCours = false; ?>
    <?php foreach ($garnies as $rang => $c): ?>
      <?php if ($c['matiere_nom'] !== $matiereEnCours): ?>
        <?php $matiereEnCours = $c['matiere_nom']; ?>
        <h2 class="revisions__matiere">
          <?php if ($c['matiere_nom'] !== null): ?>
            <span class="pastille" style="background:<?= e($c['matiere_couleur']) ?>;color:<?= e(couleur_texte($c['matiere_couleur'])) ?>">
              <?= e($c['matiere_nom']) ?>
            </span>
          <?php else: ?>
            <span class="discret">Sans matière</span>
          <?php endif; ?>
        </h2>
        <div class="grille grille--fiches">
      <?php endif; ?>

      <a class="carte fiche-carte" href="<?= url('cours/' . $c['id'], ['revision' => 1]) ?>">
        <h3 class="fiche-carte__titre"><?= surligner(e($c['titre']), $termes) ?></h3>

        <?php $texte = trim((string) $c['fiche_revision']); ?>
        <?php if ($texte !== ''): ?>
          <p class="fiche-carte__extrait"><?= surligner(e(extrait_autour($texte, $termes)), $termes) ?></p>
        <?php else: ?>
          <p class="fiche-carte__extrait discret">Pas de texte — seulement des éléments rattachés.</p>
        <?php endif; ?>

        <?php if (!empty($c['trouve_ailleurs'])): ?>
          <p class="fiche-carte__ailleurs">🔍 Trouvé dans un élément rattaché</p>
        <?php endif; ?>

        <?php $lignes = $compteurs($c); ?>
        <?php if ($lignes !== []): ?>
          <p class="fiche-carte__compteurs"><?= e(implode(' · ', $lignes)) ?></p>
        <?php endif; ?>
      </a>

      <?php
      // La grille se ferme au dernier cours de la matière. Le repère « fin »
      // ne peut pas être confondu avec une matière absente, qui vaut null.
      $matiereSuivante = array_key_exists($rang + 1, $garnies)
          ? $garnies[$rang + 1]['matiere_nom']
          : 'fin';
      ?>
      <?php if ($matiereSuivante !== $matiereEnCours): ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($vides !== []): ?>
    <details class="carte" style="margin-top:1.5rem">
      <summary><strong>Cours sans fiche</strong> <span class="discret">(<?= count($vides) ?>)</span></summary>
      <div class="pile" style="margin-top:.85rem">
        <?php foreach ($vides as $c): ?>
          <a class="evt-ligne" href="<?= url('cours/' . $c['id'], ['revision' => 1]) ?>">
            <span>
              <span class="evt-ligne__titre"><?= surligner(e($c['titre']), $termes) ?></span><br>
              <span class="evt-ligne__meta">
                <?= $c['matiere_nom'] !== null ? e($c['matiere_nom']) : 'Sans matière' ?>
                · commencer sa fiche
              </span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </details>
  <?php endif; ?>
<?php endif; ?>
