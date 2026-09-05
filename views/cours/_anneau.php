<?php
/**
 * Un anneau de progression.
 *
 * Le rayon vaut 15,915 pour que la circonférence tombe à 100 : la longueur du
 * trait est alors directement le pourcentage, sans calcul à faire.
 *
 * @var ?int $pourcentage  null quand on ne sait pas encore
 * @var string $titre      ce que l'anneau mesure, pour la lecture d'écran
 */
$pourcentage = $pourcentage ?? null;
$connu = $pourcentage !== null;
$valeur = $connu ? max(0, min(100, (int) $pourcentage)) : 0;
?>
<span class="anneau<?= $connu ? '' : ' anneau--inconnu' ?><?= $valeur >= 100 ? ' anneau--fini' : '' ?>"
      role="img" aria-label="<?= e($titre ?? 'Avancement') ?> : <?= $connu ? $valeur . ' %' : 'pas encore commencé' ?>">
  <svg viewBox="0 0 36 36" aria-hidden="true">
    <circle class="anneau__fond" cx="18" cy="18" r="15.915"></circle>
    <circle class="anneau__part" cx="18" cy="18" r="15.915"
            stroke-dasharray="<?= $valeur ?> 100"></circle>
  </svg>
  <span class="anneau__texte"><?= $connu ? $valeur . '<span class="anneau__pourcent">%</span>' : '–' ?></span>
</span>
