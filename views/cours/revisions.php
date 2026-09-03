<?php
/**
 * @var array $garnies  les cours dont la fiche contient quelque chose
 * @var array $vides    ceux dont la fiche est encore blanche
 */

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
      <?php if ($garnies === []): ?>
        Vos fiches de révision se retrouveront ici.
      <?php else: ?>
        <?= count($garnies) ?> fiche<?= count($garnies) > 1 ? 's' : '' ?>,
        regroupée<?= count($garnies) > 1 ? 's' : '' ?> par matière.
      <?php endif; ?>
    </p>
  </div>
</div>

<?php if ($garnies === [] && $vides === []): ?>
  <div class="vide">
    <span class="vide__icone">📝</span>
    <p>Vous n'avez pas encore de cours. Une fiche de révision se rédige depuis un cours.</p>
    <a class="bouton" href="<?= url('cours/nouveau') ?>">Créer un cours</a>
  </div>
<?php else: ?>

  <?php if ($garnies === []): ?>
    <div class="vide">
      <span class="vide__icone">📝</span>
      <p>Aucune fiche pour l'instant. Ouvrez un cours et cliquez sur
         <strong>📝 Révision</strong> pour commencer la sienne.</p>
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
        <h3 class="fiche-carte__titre"><?= e($c['titre']) ?></h3>

        <?php $texte = trim((string) $c['fiche_revision']); ?>
        <?php if ($texte !== ''): ?>
          <p class="fiche-carte__extrait"><?= e(mb_substr($texte, 0, 240)) ?><?= mb_strlen($texte) > 240 ? '…' : '' ?></p>
        <?php else: ?>
          <p class="fiche-carte__extrait discret">Pas de texte — seulement des éléments rattachés.</p>
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
              <span class="evt-ligne__titre"><?= e($c['titre']) ?></span><br>
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
