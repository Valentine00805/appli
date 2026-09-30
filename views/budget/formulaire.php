<?php
/** @var array $operation, $categories, $moyens */
$retour = url('budget', ['mois' => substr((string) $operation['date_operation'], 0, 7)]);
?>

<div class="entete-page">
  <div>
    <p class="discret" style="margin-bottom:.35rem">
      <a href="<?= $retour ?>"><?= e(t('form.retour_budget')) ?></a>
    </p>
    <h1><?= e(t('form.modifier_operation')) ?></h1>
  </div>
</div>

<form method="post" action="<?= url('budget/operations/' . $operation['id'] . '/modifier') ?>">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

  <div class="colonnes">
    <div class="carte">
      <fieldset style="margin-bottom:1rem">
        <legend><?= e(t('bud.sens')) ?></legend>
        <div style="display:flex;gap:1rem">
          <label class="case">
            <input type="radio" name="sens" value="depense"<?= $operation['sens'] === 'depense' ? ' checked' : '' ?>>
            <?= e(t('bud.depense')) ?>
          </label>
          <label class="case">
            <input type="radio" name="sens" value="recette"<?= $operation['sens'] === 'recette' ? ' checked' : '' ?>>
            <?= e(t('bud.recette')) ?>
          </label>
        </div>
      </fieldset>

      <div class="champ">
        <label for="libelle"><?= e(t('bud.intitule')) ?></label>
        <input type="text" id="libelle" name="libelle" required maxlength="160" autofocus
               value="<?= e($operation['libelle']) ?>">
      </div>

      <div class="ligne-champs">
        <div class="champ">
          <label for="montant"><?= e(t('bud.montant')) ?></label>
          <input type="text" id="montant" name="montant" required inputmode="decimal"
                 value="<?= e(montant_fr($operation['montant'], false)) ?>">
        </div>
        <div class="champ">
          <label for="date_operation"><?= e(t('bud.date')) ?></label>
          <input type="date" id="date_operation" name="date_operation" required
                 value="<?= e($operation['date_operation']) ?>">
        </div>
      </div>

      <div class="champ">
        <label for="note"><?= e(t('form.note')) ?></label>
        <textarea id="note" name="note" style="min-height:110px"
                  placeholder="<?= e(t('form.note_exemple')) ?>"><?= e((string) $operation['note']) ?></textarea>
      </div>
    </div>

    <div class="pile">
      <div class="carte">
        <div class="champ">
          <label for="categorie_id"><?= e(t('bud.categorie')) ?></label>
          <select id="categorie_id" name="categorie_id">
            <option value=""><?= e(t('bud.aucune_categorie')) ?></option>
            <optgroup label="<?= e(t('bud.depenses')) ?>">
              <?php foreach ($categories as $c): ?>
                <?php if ($c['sens'] === 'depense'): ?>
                  <option value="<?= (int) $c['id'] ?>"<?= (int) $operation['categorie_id'] === (int) $c['id'] ? ' selected' : '' ?>>
                    <?= e($c['icone'] . ' ' . $c['nom']) ?>
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </optgroup>
            <optgroup label="<?= e(t('bud.recettes')) ?>">
              <?php foreach ($categories as $c): ?>
                <?php if ($c['sens'] === 'recette'): ?>
                  <option value="<?= (int) $c['id'] ?>"<?= (int) $operation['categorie_id'] === (int) $c['id'] ? ' selected' : '' ?>>
                    <?= e($c['icone'] . ' ' . $c['nom']) ?>
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </optgroup>
          </select>
          <span class="champ__aide">
            <?= e(t('form.categorie_aide')) ?>
          </span>
        </div>

        <div class="champ">
          <label for="moyen"><?= e(t('bud.moyen')) ?></label>
          <select id="moyen" name="moyen">
            <option value=""><?= e(t('bud.non_precise')) ?></option>
            <?php foreach ($moyens as $m): ?>
              <option value="<?= e($m) ?>"<?= $operation['moyen'] === $m ? ' selected' : '' ?>><?= e($m) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="carte">
        <h2 style="font-size:1.05rem"><?= e(t('bud.remboursement')) ?></h2>

        <label class="case">
          <input type="checkbox" id="a_rembourser" name="a_rembourser" value="1"
                 <?= (int) $operation['a_rembourser'] === 1 ? ' checked' : '' ?>>
          <?= e(t('bud.a_rembourser_case')) ?>
        </label>

        <div id="bloc-remboursement" style="margin-top:.9rem">
          <?= Vue::rendre('budget/_qui_rembourse', [
              'personnes' => $personnes,
              'valeur'    => $operation['rembourse_par'],
          ]) ?>

          <div class="champ">
            <label for="part_rembourser"><?= e(t('bud.part_reclamer')) ?></label>
            <input type="text" id="part_rembourser" name="part_rembourser" inputmode="decimal"
                   placeholder="<?= e(t('remb.tout_suffixe', ['montant' => montant_fr($operation['montant'], false)])) ?>"
                   value="<?= $operation['part_rembourser'] !== null
                       ? e(montant_fr($operation['part_rembourser'], false)) : '' ?>">
            <span class="champ__aide">
              <?= e(t('form.part_aide', ['montant' => montant_fr(round((float) $operation['montant'] / 2, 2))])) ?>
            </span>
          </div>

          <div class="ligne-champs">
            <div class="champ">
              <label for="statut_remb"><?= e(t('remb.statut')) ?></label>
              <select id="statut_remb" name="statut_remb">
                <?php foreach ($statuts as $cle => $libelle): ?>
                  <option value="<?= e($cle) ?>"<?= $operation['statut_remb'] === $cle ? ' selected' : '' ?>>
                    <?= e($libelle) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <span class="champ__aide"><?= e(t('form.hors_total_aide')) ?></span>
            </div>
            <div class="champ">
              <label for="date_remboursement"><?= e(t('remb.rembourse_le')) ?></label>
              <input type="date" id="date_remboursement" name="date_remboursement"
                     value="<?= e((string) $operation['date_remboursement']) ?>">
            </div>
          </div>
        </div>

      </div>

      <button class="bouton bouton--bloc" type="submit"><?= e(t('commun.enregistrer')) ?></button>
      <a class="bouton bouton--secondaire bouton--bloc" href="<?= $retour ?>"><?= e(t('commun.annuler')) ?></a>
    </div>
  </div>
</form>

<form method="post" action="<?= url('budget/operations/' . $operation['id'] . '/supprimer') ?>"
      data-confirmation="<?= e(t('bud.supprimer_operation_sur')) ?>" style="margin-top:1rem;max-width:320px">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <button class="bouton bouton--danger" type="submit"><?= e(t('form.supprimer_operation')) ?></button>
</form>
