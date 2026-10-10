<?php
/**
 * Ce que les destinataires pourront faire : un droit par personne (le menu de chacun s'affiche dans la liste des amis et des groupes, voir
 * partages/_droit_perso), ou le même droit pour tous. Le premier est coché d'office.
 *
 * @var list<string> $droitsPossibles  les droits qu'on peut donner, du plus restreint au plus large
 */
?>
<fieldset class="champ partage-droits" style="margin-top:.75rem" data-droits>
  <legend class="legende"><?= e(t('pt.ce_quils_pourront')) ?></legend>
  <div class="partage-genres__onglets partage-droits__mode" role="radiogroup" aria-label="<?= e(t('pt.droits_mode')) ?>">
    <label class="partage-genre partage-genre--actif">
      <input type="radio" name="droits_mode" value="chacun" class="sr-only" checked>
      <span aria-hidden="true">🎚️</span> <?= e(t('pt.droits_chacun')) ?>
    </label>
    <label class="partage-genre">
      <input type="radio" name="droits_mode" value="tous" class="sr-only">
      <span aria-hidden="true">👥</span> <?= e(t('pt.droits_tous')) ?>
    </label>
  </div>
  <p class="discret" data-droits-chacun style="margin:0"><?= e(t('pt.droits_chacun_aide')) ?></p>
  <div class="partage-droits" data-droits-tous hidden>
    <?php foreach (Partages::DROITS as $rang => $unDroit): ?>
      <?php if (!in_array($unDroit, $droitsPossibles, true)) { continue; } ?>
      <label class="partage-droits__choix">
        <input type="radio" name="droit" value="<?= e($unDroit) ?>"<?= $rang === 0 ? ' checked' : '' ?>>
        <span>
          <strong><?= e(Partages::libelleDroit($unDroit)) ?></strong>
          <span class="discret"><?= e(Partages::expliqueDroit($unDroit)) ?></span>
        </span>
      </label>
    <?php endforeach; ?>
  </div>
</fieldset>
