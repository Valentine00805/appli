<?php
/**
 * Une fiche de révision, seule : ni le contenu du cours, ni ses pièces jointes.
 * Le titre du cours reste en tête, pour savoir de quoi on révise.
 *
 * @var array $cours, $fichiersFiche, $parType, $autresCours, $evenementsChoix
 * @var string $fiche
 */
?>

<div class="entete-page">
  <div>
    <p class="discret" style="margin-bottom:.35rem">
      <a href="<?= url('revision') ?>">← Révision</a>
    </p>
    <h1><?= e($cours['titre']) ?></h1>
    <p>
      <?php if ($cours['matiere_nom'] !== null): ?>
        <span class="pastille" style="background:<?= e($cours['matiere_couleur']) ?>;color:<?= e(couleur_texte($cours['matiere_couleur'])) ?>">
          <?= e($cours['matiere_nom']) ?>
        </span>
      <?php endif; ?>
      <span class="discret">Fiche de révision</span>
    </p>
  </div>

  <div class="actions">
    <a class="bouton bouton--secondaire" href="<?= url('cours/' . $cours['id']) ?>">
      Voir le cours
    </a>
  </div>
</div>

<div class="fiche-seule">
  <?= Vue::rendre('cours/_fiche', [
      'cours' => $cours, 'fiche' => $fiche, 'fichiersFiche' => $fichiersFiche,
      'parType' => $parType, 'autresCours' => $autresCours,
      'evenementsChoix' => $evenementsChoix, 'surPage' => true,
  ]) ?>
</div>
