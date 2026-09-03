<?php
/**
 * @var array $garnies    les cours dont la fiche contient quelque chose
 * @var array $vides      ceux dont la fiche est encore blanche
 * @var string $recherche ce qui est cherché, vide sinon
 * @var array $termes     les mots de la recherche, pour le surlignage
 * @var array $matieres   les matières de l'utilisateur, pour le filtre
 * @var ?int $matiereId   la matière retenue, null pour toutes
 * @var string $tri       'matiere', 'recent' ou 'ancien'
 */
$matiereChoisie = null;
foreach ($matieres as $m) {
    if ((int) $m['id'] === $matiereId) {
        $matiereChoisie = $m['nom'];
    }
}
$filtreActif = $recherche !== '' || $matiereId !== null;

// Trié par date, le regroupement par matière n'a plus de sens : la liste est
// alors à plat, et chaque carte porte sa matière et sa date.
$parMatiere = $tri === 'matiere';

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
        <?php
        // La phrase est assemblée ici : un « if » par morceau laisserait une
        // espace avant le point dès qu'un des deux filtres manque.
        $morceaux = [count($garnies) . ' fiche' . (count($garnies) > 1 ? 's' : '')];
        if ($recherche !== '') {
            $morceaux[] = 'pour « ' . $recherche . ' »';
        }
        if ($matiereChoisie !== null) {
            $morceaux[] = 'en ' . $matiereChoisie;
        }
        ?>
        <?= e(implode(' ', $morceaux)) ?>.
        <a href="<?= url('revision') ?>">Tout revoir</a>
      <?php elseif ($garnies === []): ?>
        Vos fiches de révision se retrouveront ici.
      <?php else: ?>
        <?php $s = count($garnies) > 1 ? 's' : ''; ?>
        <?= count($garnies) ?> fiche<?= $s ?>,
        <?= match ($tri) {
            'recent' => 'de la plus récente à la plus ancienne.',
            'ancien' => 'de la plus ancienne à la plus récente.',
            default  => 'regroupée' . $s . ' par matière.',
        } ?>
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

  <div class="champ">
    <label for="f-fiche-tri">Trier par</label>
    <select id="f-fiche-tri" name="tri">
      <option value="matiere"<?= $tri === 'matiere' ? ' selected' : '' ?>>Matière</option>
      <option value="recent"<?= $tri === 'recent' ? ' selected' : '' ?>>Modifiée récemment</option>
      <option value="ancien"<?= $tri === 'ancien' ? ' selected' : '' ?>>Plus ancienne d'abord</option>
    </select>
  </div>

  <button class="bouton bouton--secondaire" type="submit">Filtrer</button>
  <?php if ($filtreActif || $tri !== 'matiere'): ?>
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
    <?php if (!$parMatiere): ?>
      <div class="grille grille--fiches">
    <?php endif; ?>

    <?php $matiereEnCours = false; ?>
    <?php foreach ($garnies as $rang => $c): ?>
      <?php if ($parMatiere && $c['matiere_nom'] !== $matiereEnCours): ?>
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

      <a class="carte fiche-carte" href="<?= url('revision/' . $c['id']) ?>">
        <?php if (!$parMatiere): ?>
          <p class="fiche-carte__entete">
            <?php if ($c['matiere_nom'] !== null): ?>
              <span class="pastille" style="background:<?= e($c['matiere_couleur']) ?>;color:<?= e(couleur_texte($c['matiere_couleur'])) ?>">
                <?= e($c['matiere_nom']) ?>
              </span>
            <?php else: ?>
              <span class="discret">Sans matière</span>
            <?php endif; ?>
            <span class="discret">modifiée le <?= e(date_fr((string) $c['modifiee_le'])) ?></span>
          </p>
        <?php endif; ?>

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

      <?php if ($parMatiere): ?>
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
      <?php endif; ?>
    <?php endforeach; ?>

    <?php if (!$parMatiere): ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($vides !== []): ?>
    <details class="carte" style="margin-top:1.5rem">
      <summary><strong>Cours sans fiche</strong> <span class="discret">(<?= count($vides) ?>)</span></summary>
      <div class="pile" style="margin-top:.85rem">
        <?php foreach ($vides as $c): ?>
          <a class="evt-ligne" href="<?= url('revision/' . $c['id']) ?>">
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
