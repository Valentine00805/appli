<?php
/**
 * Une fiche de révision, seule : ni le contenu du cours, ni ses pièces jointes.
 * Le titre du cours reste en tête, pour savoir de quoi on révise.
 *
 * @var array $cours, $fichiersFiche, $parType, $autresCours, $evenementsChoix
 * @var string $fiche
 * @var bool $dansUneFenetre  rendue seule, pour être posée dans une fenêtre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
?>

<div class="entete-page"<?= $dansUneFenetre ? ' data-large data-document' : '' ?>>
  <div>
    <?php // Dans une fenêtre, on vient d'un cours ou de la liste : la croix y ramène. ?>
    <?php if (!$dansUneFenetre): ?>
      <p class="discret sans-impression" style="margin-bottom:.35rem">
        <a href="<?= url('revision') ?>"><?= e(t('fiche.retour_revision')) ?></a>
      </p>
    <?php endif; ?>
    <h1><?= e($cours['titre']) ?></h1>
    <p>
      <?php if ($cours['matiere_nom'] !== null): ?>
        <span class="pastille" style="background:<?= e($cours['matiere_couleur']) ?>;color:<?= e(couleur_texte($cours['matiere_couleur'])) ?>">
          <?= e($cours['matiere_nom']) ?>
        </span>
      <?php endif; ?>
      <span class="discret"><?= e(t('fiche.titre')) ?></span>
    </p>
  </div>

  <div class="actions">
    <?php // L'impression sort la fiche seule : ni menu, ni boutons, ni formulaires. ?>
    <?php if ($dansUneFenetre): ?>
      <?php
      /*
       * Une fenêtre s'imprime avec la page qu'elle recouvre. La fiche
       * s'ouvre donc seule dans un onglet, qui lance l'impression.
       */
      ?>
      <a class="bouton bouton--secondaire" href="<?= url('revision/' . $cours['id'], ['imprimer' => 1]) ?>"
         target="_blank" rel="noopener"><?= e(t('fiche.imprimer')) ?></a>
    <?php else: ?>
      <button class="bouton bouton--secondaire" type="button" onclick="window.print()"><?= e(t('fiche.imprimer')) ?></button>
    <?php endif; ?>
    <?php // Partager la fiche : à ses amis, ou par un lien. ?>
    <a class="bouton bouton--secondaire bouton-partage sans-impression" href="<?= url('partager/fiches/' . $cours['id']) ?>"
       <?= $dansUneFenetre ? 'data-fenetre-dessus' : 'data-fenetre' ?>><?= Partages::icone() ?> <?= e(t('evt.partager')) ?></a>
    <?php // La fiche enregistrée, à emporter : son texte et ce qui lui est rattaché. ?>
    <a class="bouton bouton--secondaire" href="<?= url('revision/' . $cours['id'] . '/pdf') ?>"
       title="<?= e(t('fiche.pdf_aide')) ?>">⬇ PDF</a>
    <a class="bouton bouton--secondaire" href="<?= url('cours/' . $cours['id']) ?>"
       <?= $dansUneFenetre ? 'data-fenetre' : '' ?>>
      <?= e(t('fiche.voir_cours')) ?>
    </a>
  </div>
</div>

<?php $nbModifications = Partages::nbModifications('fiche', (int) $cours['id']); ?>
<?php if ($nbModifications > 0): ?>
  <p class="discret sans-impression" style="margin:0 0 .8rem">
    🕘 <a href="<?= e(Partages::adresseHistorique('fiche', (int) $cours['id'])) ?>" <?= $dansUneFenetre ? 'data-fenetre-dessus' : 'data-fenetre' ?>><?= e(tn('cours.modifications', $nbModifications)) ?></a>
    <?= e(t('fiche.modifications_suite')) ?>
  </p>
<?php endif; ?>
<div class="fiche-seule">
  <?= Vue::rendre('cours/_fiche', [
      'cours' => $cours, 'fiche' => $fiche, 'fichiersFiche' => $fichiersFiche,
      'parType' => $parType, 'autresCours' => $autresCours,
      'evenementsChoix' => $evenementsChoix, 'cartes' => $cartes, 'surPage' => true,
      'dansUneFenetre' => $dansUneFenetre,
  ]) ?>
</div>
