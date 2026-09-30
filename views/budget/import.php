<?php /** @var ?array $dernierImport */ ?>

<?= Vue::rendre('budget/_onglets', ['onglet' => 'import']) ?>

<div class="entete-page">
  <div>
    <h1><?= e(t('imp.titre')) ?></h1>
    <p><?= e(t('imp.sous_titre')) ?></p>
  </div>
  <div class="actions">
    <a class="bouton bouton--secondaire" href="<?= url('budget') ?>"><?= e(t('remb.voir_operations')) ?></a>
  </div>
</div>

<div class="colonnes">
  <div class="carte">
    <h2><?= e(t('imp.deposer')) ?></h2>

    <form method="post" action="<?= url('budget/import') ?>" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

      <div class="champ">
        <label for="releve"><?= e(t('imp.releve')) ?></label>
        <input type="file" id="releve" name="releve"
               accept=".csv,.txt,.tsv,.xlsx,text/csv" required>
        <span class="champ__aide">
          <?= t('imp.releve_aide') ?>
        </span>
      </div>

      <button class="bouton bouton--bloc" type="submit"><?= e(t('imp.analyser')) ?></button>
      <p class="champ__aide" style="margin-top:.6rem">
        <?= e(t('imp.rien_enregistre')) ?>
      </p>
    </form>

    <?php if ($dernierImport !== null && $dernierImport['date'] !== null): ?>
      <hr class="separateur">
      <p class="discret" style="margin:0">
        <?= e(t('imp.dernier', ['date' => date_fr((string) $dernierImport['date'])])) ?>
        <?= e(tn('imp.provenance', (int) $dernierImport['nb'])) ?>
      </p>
    <?php endif; ?>
  </div>

  <div class="pile">
    <div class="carte">
      <h2><?= e(t('imp.releve_banque')) ?></h2>
      <p class="discret" style="margin-bottom:.6rem">
        <?= t('imp.releve_banque_1') ?>
      </p>
      <p class="discret" style="margin:0">
        <?= e(t('imp.releve_banque_2')) ?>
      </p>
    </div>

    <div class="carte">
      <h2><?= e(t('imp.prevu')) ?></h2>
      <ul class="discret" style="margin:0;padding-left:1.1rem;display:grid;gap:.35rem">
        <li><?= e(t('imp.prevu_1')) ?></li>
        <li><?= e(t('imp.prevu_2')) ?></li>
        <li><?= e(t('imp.prevu_3')) ?></li>
        <li><?= e(t('imp.prevu_4')) ?></li>
        <li><?= e(t('imp.prevu_5')) ?></li>
      </ul>
    </div>

    <div class="carte">
      <h2><?= e(t('imp.ancien_classeur')) ?></h2>
      <p class="discret" style="margin-bottom:.6rem">
        <?= t('imp.ancien_classeur_1') ?>
      </p>
      <p class="discret" style="margin:0">
        <?= e(t('imp.ancien_classeur_2')) ?>
      </p>
    </div>

    <div class="carte">
      <h2><?= e(t('imp.saisie_manuelle')) ?></h2>
      <p class="discret" style="margin:0">
        <?= e(t('imp.saisie_manuelle_1')) ?> <a href="<?= url('budget') ?>"><?= e(t('bud.onglet.operations')) ?></a><?= t('imp.saisie_manuelle_2') ?>
      </p>
    </div>
  </div>
</div>
