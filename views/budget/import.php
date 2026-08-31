<?php /** @var ?array $dernierImport */ ?>

<?= Vue::rendre('budget/_onglets', ['onglet' => 'import']) ?>

<div class="entete-page">
  <div>
    <h1>Importer un relevé</h1>
    <p>Vos dépenses du mois sont préremplies à partir du fichier de votre banque.</p>
  </div>
  <div class="actions">
    <a class="bouton bouton--secondaire" href="<?= url('budget') ?>">Voir les opérations</a>
  </div>
</div>

<div class="colonnes">
  <div class="carte">
    <h2>Déposer le fichier</h2>

    <form method="post" action="<?= url('budget/import') ?>" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

      <div class="champ">
        <label for="releve">Relevé de compte</label>
        <input type="file" id="releve" name="releve"
               accept=".csv,.txt,.tsv,.xlsx,text/csv" required>
        <span class="champ__aide">
          Un relevé bancaire au format <strong>CSV</strong>, ou un ancien classeur
          <strong>.xlsx</strong> tenu à la main. 4 Mo maximum.
        </span>
      </div>

      <button class="bouton bouton--bloc" type="submit">Analyser le fichier</button>
      <p class="champ__aide" style="margin-top:.6rem">
        Rien n'est enregistré à cette étape : vous verrez d'abord un aperçu, que
        vous pourrez corriger et dont vous choisirez les lignes.
      </p>
    </form>

    <?php if ($dernierImport !== null && $dernierImport['date'] !== null): ?>
      <hr class="separateur">
      <p class="discret" style="margin:0">
        Dernier import : <?= e(date_fr((string) $dernierImport['date'])) ?>.
        <?= (int) $dernierImport['nb'] ?> opération<?= (int) $dernierImport['nb'] > 1 ? 's' : '' ?>
        provienne<?= (int) $dernierImport['nb'] > 1 ? 'nt' : '' ?> d'un relevé au total.
      </p>
    <?php endif; ?>
  </div>

  <div class="pile">
    <div class="carte">
      <h2>Un relevé de banque</h2>
      <p class="discret" style="margin-bottom:.6rem">
        Sur le site ou l'application de votre banque, ouvrez l'historique du compte
        et cherchez « Exporter », « Télécharger les opérations » ou une icône de
        téléchargement. Choisissez le format <strong>CSV</strong> plutôt qu'Excel
        ou PDF.
      </p>
      <p class="discret" style="margin:0">
        Peu importe l'ordre des colonnes ou le format des dates : l'écran suivant
        vous montre ce qui a été compris et vous laisse corriger.
      </p>
    </div>

    <div class="carte">
      <h2>Ce qui est prévu</h2>
      <ul class="discret" style="margin:0;padding-left:1.1rem;display:grid;gap:.35rem">
        <li>Les dates à la française comme à l'anglaise.</li>
        <li>Un montant signé, ou deux colonnes débit et crédit.</li>
        <li>Les accents mal encodés des exports Windows.</li>
        <li>Les lignes d'en-tête et le préambule de certaines banques.</li>
        <li>Les doublons, si vous réimportez un relevé qui se chevauche.</li>
      </ul>
    </div>

    <div class="carte">
      <h2>Un ancien classeur</h2>
      <p class="discret" style="margin-bottom:.6rem">
        Vos anciens fichiers de comptes au format <strong>.xlsx</strong> se reprennent
        aussi, avec leur structure : une date, un libellé et un montant dans les trois
        premières colonnes, et des lignes « Total … » à droite dont sont déduites les
        rubriques.
      </p>
      <p class="discret" style="margin:0">
        Les mentions « divisé par 2 » et « pas dans le total » sont reconnues, et le
        total recalculé est comparé à celui de votre feuille avant validation.
      </p>
    </div>

    <div class="carte">
      <h2>La saisie manuelle reste là</h2>
      <p class="discret" style="margin:0">
        L'import ne remplace rien : vous pouvez continuer à ajouter des opérations
        à la main depuis l'onglet <a href="<?= url('budget') ?>">Opérations</a>, et
        <strong>modifier ou supprimer</strong> une ligne importée exactement comme
        une autre.
      </p>
    </div>
  </div>
</div>
