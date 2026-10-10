<?php
/**
 * Le droit d'une personne (ou d'un groupe), dans la liste des destinataires : affiché d'office (« Un droit par personne »), caché dès qu'on
 * choisit de donner le même droit à tous.
 *
 * @var string $champ  « droits_amis » ou « droits_groupes »
 * @var int $qui  l'identifiant de l'ami ou du groupe
 * @var string $nom  son nom, pour l'étiquette
 * @var list<string> $droitsPossibles
 */
?>
<select name="<?= e($champ) ?>[<?= (int) $qui ?>]" class="droit-perso" data-droit-perso
        aria-label="<?= e(t('pt.ce_que_peut_faire', ['qui' => $nom])) ?>">
  <?php foreach (Partages::DROITS as $unDroit): ?>
    <?php if (!in_array($unDroit, $droitsPossibles, true)) { continue; } ?>
    <option value="<?= e($unDroit) ?>"><?= e(Partages::libelleDroit($unDroit)) ?></option>
  <?php endforeach; ?>
</select>
