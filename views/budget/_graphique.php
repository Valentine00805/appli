<?php
/**
 * Courbe du solde prévisionnel, en SVG généré côté serveur.
 * Le trait est plein sur les mois passés, pointillé sur les mois à venir.
 *
 * @var array  $points   liste de ['periode', 'mois', 'solde', 'origine']
 * @var string $periode  mois affiché, qui sépare le passé du prévisionnel
 */
$points = array_values(array_filter($points, static fn (array $p): bool => $p['solde'] !== null));

if (count($points) < 2) {
    return;
}

/* --- Géométrie ---------------------------------------------------------- */

$largeur = 760;
$hauteur = 280;
$gauche = 64;
$droite = 18;
$haut = 18;
$bas = 40;

$zoneL = $largeur - $gauche - $droite;
$zoneH = $hauteur - $haut - $bas;

$valeurs = array_map(static fn (array $p): float => (float) $p['solde'], $points);
$min = min($valeurs);
$max = max($valeurs);

// Le zéro est toujours visible : un découvert doit se voir.
$min = min($min, 0.0);
$max = max($max, 0.0);
if ($max - $min < 1) {
    $max = $min + 1;
}
$marge = ($max - $min) * 0.12;
$min -= $marge;
$max += $marge;

$nb = count($points);
$x = static fn (int $i): float => $gauche + ($nb === 1 ? $zoneL / 2 : ($i * $zoneL) / ($nb - 1));
$y = static fn (float $v): float => $haut + $zoneH - (($v - $min) / ($max - $min)) * $zoneH;
$n = static fn (float $v): string => number_format($v, 1, '.', '');

/* --- Découpage passé / prévisionnel ------------------------------------- */

$indexCourant = null;
foreach ($points as $i => $p) {
    if ($p['periode'] === $periode) {
        $indexCourant = $i;
        break;
    }
}
if ($indexCourant === null) {
    $indexCourant = $nb - 1;
}

$cheminPasse = '';
$cheminFutur = '';
foreach ($points as $i => $p) {
    $coord = $n($x($i)) . ',' . $n($y((float) $p['solde']));
    if ($i <= $indexCourant) {
        $cheminPasse .= ($cheminPasse === '' ? 'M' : ' L') . $coord;
    }
    if ($i >= $indexCourant) {
        $cheminFutur .= ($cheminFutur === '' ? 'M' : ' L') . $coord;
    }
}

// Aire sous la courbe complète, refermée sur la ligne du zéro.
$aire = 'M' . $n($x(0)) . ',' . $n($y((float) $points[0]['solde']));
foreach ($points as $i => $p) {
    $aire .= ' L' . $n($x($i)) . ',' . $n($y((float) $p['solde']));
}
$aire .= ' L' . $n($x($nb - 1)) . ',' . $n($y(0.0))
       . ' L' . $n($x(0)) . ',' . $n($y(0.0)) . ' Z';

/* --- Graduations --------------------------------------------------------- */

$graduations = [];
for ($i = 0; $i <= 3; $i++) {
    $graduations[] = $min + (($max - $min) * $i) / 3;
}

$dernier = end($points);
$resume = sprintf(
    'Solde prévisionnel de %s à %s, de %s à %s.',
    strtolower(nom_mois((int) $points[0]['mois']->format('n'))) . ' ' . $points[0]['mois']->format('Y'),
    strtolower(nom_mois((int) $dernier['mois']->format('n'))) . ' ' . $dernier['mois']->format('Y'),
    montant_fr(min($valeurs)),
    montant_fr(max($valeurs))
);
?>

<figure class="graphique">
  <svg viewBox="0 0 <?= $largeur ?> <?= $hauteur ?>" role="img" aria-label="<?= e($resume) ?>"
       preserveAspectRatio="xMidYMid meet">

    <?php foreach ($graduations as $g): ?>
      <line class="graphique__grille" x1="<?= $n((float) $gauche) ?>" y1="<?= $n($y($g)) ?>"
            x2="<?= $n((float) ($largeur - $droite)) ?>" y2="<?= $n($y($g)) ?>" />
      <text class="graphique__graduation" x="<?= $gauche - 8 ?>" y="<?= $n($y($g) + 4) ?>" text-anchor="end">
        <?= e(number_format($g, 0, ',', ' ')) ?> €
      </text>
    <?php endforeach; ?>

    <?php if ($min < 0): ?>
      <line class="graphique__zero" x1="<?= $n((float) $gauche) ?>" y1="<?= $n($y(0.0)) ?>"
            x2="<?= $n((float) ($largeur - $droite)) ?>" y2="<?= $n($y(0.0)) ?>" />
    <?php endif; ?>

    <path class="graphique__aire" d="<?= $aire ?>" />

    <?php if ($indexCourant > 0): ?>
      <path class="graphique__trait" d="<?= $cheminPasse ?>" />
    <?php endif; ?>
    <?php if ($indexCourant < $nb - 1): ?>
      <path class="graphique__trait graphique__trait--prevu" d="<?= $cheminFutur ?>" />
    <?php endif; ?>

    <?php foreach ($points as $i => $p): ?>
      <?php $solde = (float) $p['solde']; ?>
      <g class="graphique__point<?= $i === $indexCourant ? ' est-actif' : '' ?><?= $solde < 0 ? ' est-negatif' : '' ?>">
        <circle cx="<?= $n($x($i)) ?>" cy="<?= $n($y($solde)) ?>" r="<?= $i === $indexCourant ? 5 : 3.5 ?>" />
        <title><?= e(
            strtolower(nom_mois((int) $p['mois']->format('n'))) . ' ' . $p['mois']->format('Y')
            . ' : ' . montant_fr($solde)
            . ($p['origine'] === 'saisi' ? ' (solde saisi)' : '')
        ) ?></title>
      </g>

      <?php if ($nb <= 14): ?>
        <text class="graphique__mois<?= $i === $indexCourant ? ' est-actif' : '' ?>"
              x="<?= $n($x($i)) ?>" y="<?= $hauteur - 18 ?>" text-anchor="middle">
          <?= e(nom_mois_court((int) $p['mois']->format('n'))) ?>
        </text>
        <?php if ($i === 0 || $p['mois']->format('n') === '1'): ?>
          <text class="graphique__annee" x="<?= $n($x($i)) ?>" y="<?= $hauteur - 5 ?>" text-anchor="middle">
            <?= e($p['mois']->format('Y')) ?>
          </text>
        <?php endif; ?>
      <?php endif; ?>
    <?php endforeach; ?>
  </svg>

  <figcaption class="graphique__legende">
    <span><span class="graphique__cle graphique__cle--plein"></span> réalisé</span>
    <span><span class="graphique__cle graphique__cle--pointille"></span> prévisionnel</span>
    <?php if (min($valeurs) < 0): ?>
      <span style="color:var(--erreur)">⚠ passage en négatif</span>
    <?php endif; ?>
  </figcaption>
</figure>
