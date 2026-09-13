<?php
/**
 * Une sous-tâche, en lecture.
 *
 * Ouverte depuis le tableau, dans une fenêtre : ce qu'on veut savoir d'une
 * carte sans quitter le tableau — sa liste, son échéance, où elle en est, et
 * la remarque qu'on y a laissée. Comme la fiche d'un évènement, une ligne qui
 * n'a rien à dire ne s'affiche pas.
 *
 * @var array  $tache   la sous-tâche, avec liste_nom, liste_couleur, liste_icone
 * @var string $colonne la colonne du tableau où elle se trouve
 * @var bool   $dansUneFenetre  rendue seule, pour être posée dans une fenêtre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$faite = (int) $tache['faite'] === 1;
$echeance = $tache['echeance'] !== null ? (string) $tache['echeance'] : '';
$etape = KanbanController::COLONNES[$colonne] ?? KanbanController::COLONNES['a_faire'];

/** Une ligne de la fiche, tue quand elle n'a rien à dire. */
$ligne = static function (string $etiquette, string $valeur): string {
    if (trim(strip_tags($valeur)) === '') {
        return '';
    }

    return '<div class="fiche__ligne"><span class="fiche__etiquette">' . e($etiquette)
        . '</span><span class="fiche__valeur">' . $valeur . '</span></div>';
};
?>

<div class="entete-page">
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p class="discret" style="margin-bottom:.35rem">
        <a href="<?= url('tableau') ?>">← Tableau</a>
      </p>
    <?php endif; ?>
    <h1 style="display:flex;align-items:center;gap:.6rem">
      <span class="fiche__teinte" style="background:<?= e((string) $tache['liste_couleur']) ?>"></span>
      <?= e((string) $tache['titre']) ?>
    </h1>
  </div>

  <div class="actions">
    <?php // La modifier se fait dans sa liste, où sont tous ses réglages. ?>
    <a class="bouton" href="<?= url('taches', ['liste' => (int) $tache['liste_id']]) ?>">↗ Ouvrir dans Tâches</a>
  </div>
</div>

<div class="pile"<?= $dansUneFenetre ? '' : ' style="max-width:44rem"' ?>>
  <section class="carte fiche">
    <?= $ligne('Liste', e(((string) $tache['liste_icone'] !== '' ? $tache['liste_icone'] : '📋')
        . ' ' . $tache['liste_nom'])) ?>

    <?= $ligne('Échéance', $echeance === ''
        ? '<span class="discret">Sans échéance</span>'
        : e(ucfirst(date_fr($echeance, false)))
          . ' <span class="echeance echeance--' . e(echeance_etat($echeance, $faite)) . '">'
          . e(echeance_libelle($echeance, $faite)) . '</span>') ?>

    <?= $ligne('Au tableau', e($etape['icone'] . ' ' . $etape['titre'])) ?>

    <?php if ($faite && $tache['faite_le'] !== null): ?>
      <?= $ligne('Terminée le', e(date_fr((string) $tache['faite_le']))) ?>
    <?php endif; ?>

    <?php if ((string) ($tache['note'] ?? '') !== ''): ?>
      <div class="fiche__notes">
        <span class="fiche__etiquette">Remarque</span>
        <p><?= e((string) $tache['note']) ?></p>
      </div>
    <?php endif; ?>
  </section>
</div>
