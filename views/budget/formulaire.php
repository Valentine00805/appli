<?php
/** @var array $operation, $categories, $moyens */
$retour = url('budget', ['mois' => substr((string) $operation['date_operation'], 0, 7)]);
?>

<div class="entete-page">
  <div>
    <p class="discret" style="margin-bottom:.35rem">
      <a href="<?= $retour ?>">← Retour au budget</a>
    </p>
    <h1>Modifier l'opération</h1>
  </div>
</div>

<form method="post" action="<?= url('budget/operations/' . $operation['id'] . '/modifier') ?>">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

  <div class="colonnes">
    <div class="carte">
      <fieldset style="margin-bottom:1rem">
        <legend>Sens</legend>
        <div style="display:flex;gap:1rem">
          <label class="case">
            <input type="radio" name="sens" value="depense"<?= $operation['sens'] === 'depense' ? ' checked' : '' ?>>
            Dépense
          </label>
          <label class="case">
            <input type="radio" name="sens" value="recette"<?= $operation['sens'] === 'recette' ? ' checked' : '' ?>>
            Recette
          </label>
        </div>
      </fieldset>

      <div class="champ">
        <label for="libelle">Intitulé</label>
        <input type="text" id="libelle" name="libelle" required maxlength="160" autofocus
               value="<?= e($operation['libelle']) ?>">
      </div>

      <div class="ligne-champs">
        <div class="champ">
          <label for="montant">Montant</label>
          <input type="text" id="montant" name="montant" required inputmode="decimal"
                 value="<?= e(montant_fr($operation['montant'], false)) ?>">
        </div>
        <div class="champ">
          <label for="date_operation">Date</label>
          <input type="date" id="date_operation" name="date_operation" required
                 value="<?= e($operation['date_operation']) ?>">
        </div>
      </div>

      <div class="champ">
        <label for="note">Note</label>
        <textarea id="note" name="note" style="min-height:110px"
                  placeholder="Détail, contexte…"><?= e((string) $operation['note']) ?></textarea>
      </div>
    </div>

    <div class="pile">
      <div class="carte">
        <div class="champ">
          <label for="categorie_id">Catégorie</label>
          <select id="categorie_id" name="categorie_id">
            <option value="">— Aucune —</option>
            <optgroup label="Dépenses">
              <?php foreach ($categories as $c): ?>
                <?php if ($c['sens'] === 'depense'): ?>
                  <option value="<?= (int) $c['id'] ?>"<?= (int) $operation['categorie_id'] === (int) $c['id'] ? ' selected' : '' ?>>
                    <?= e($c['icone'] . ' ' . $c['nom']) ?>
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </optgroup>
            <optgroup label="Recettes">
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
            Une catégorie qui ne correspond pas au sens choisi est ignorée.
          </span>
        </div>

        <div class="champ">
          <label for="moyen">Moyen de paiement</label>
          <select id="moyen" name="moyen">
            <option value="">— Non précisé —</option>
            <?php foreach ($moyens as $m): ?>
              <option value="<?= e($m) ?>"<?= $operation['moyen'] === $m ? ' selected' : '' ?>><?= e($m) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="carte">
        <h2 style="font-size:1.05rem">Remboursement</h2>

        <label class="case">
          <input type="checkbox" id="a_rembourser" name="a_rembourser" value="1"
                 <?= (int) $operation['a_rembourser'] === 1 ? ' checked' : '' ?>>
          🧾 À me faire rembourser
        </label>

        <div id="bloc-remboursement" style="margin-top:.9rem">
          <div class="champ">
            <label for="rembourse_par">Par qui</label>
            <input type="text" id="rembourse_par" name="rembourse_par" maxlength="80"
                   list="liste-personnes" placeholder="Parents"
                   value="<?= e((string) $operation['rembourse_par']) ?>">
          </div>

          <div class="champ">
            <label for="part_rembourser">Part à réclamer</label>
            <input type="text" id="part_rembourser" name="part_rembourser" inputmode="decimal"
                   placeholder="<?= e(montant_fr($operation['montant'], false)) ?> (tout)"
                   value="<?= $operation['part_rembourser'] !== null
                       ? e(montant_fr($operation['part_rembourser'], false)) : '' ?>">
            <span class="champ__aide">
              Vide = tout le montant. La moitié ferait
              <?= e(montant_fr(round((float) $operation['montant'] / 2, 2))) ?>.
            </span>
          </div>

          <div class="ligne-champs">
            <div class="champ">
              <label for="statut_remb">Statut</label>
              <select id="statut_remb" name="statut_remb">
                <?php foreach ($statuts as $cle => $libelle): ?>
                  <option value="<?= e($cle) ?>"<?= $operation['statut_remb'] === $cle ? ' selected' : '' ?>>
                    <?= e($libelle) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <span class="champ__aide">« Hors total » garde la ligne visible sans la compter.</span>
            </div>
            <div class="champ">
              <label for="date_remboursement">Remboursé le</label>
              <input type="date" id="date_remboursement" name="date_remboursement"
                     value="<?= e((string) $operation['date_remboursement']) ?>">
            </div>
          </div>
        </div>

        <datalist id="liste-personnes">
          <?php foreach ($personnes as $p): ?>
            <option value="<?= e($p) ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>

      <button class="bouton bouton--bloc" type="submit">Enregistrer</button>
      <a class="bouton bouton--secondaire bouton--bloc" href="<?= $retour ?>">Annuler</a>
    </div>
  </div>
</form>

<form method="post" action="<?= url('budget/operations/' . $operation['id'] . '/supprimer') ?>"
      data-confirmation="Supprimer cette opération ?" style="margin-top:1rem;max-width:320px">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <button class="bouton bouton--danger" type="submit">Supprimer cette opération</button>
</form>
