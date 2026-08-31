<?php
/**
 * @var string $nom
 * @var array $entetes, $colonnes, $mapping, $lignes, $categories
 * @var int $valides, $doublons, $invalides
 */
$csrf = Session::jetonCsrf();

/** Liste de choix d'une colonne du fichier. */
$choixColonne = static function (string $champ, ?int $actif) use ($colonnes): string {
    $html = '<select name="' . e($champ) . '" aria-label="Colonne ' . e($champ) . '">';
    $html .= '<option value="">— aucune —</option>';
    foreach ($colonnes as $i => $nom) {
        $html .= '<option value="' . (int) $i . '"' . ($actif === (int) $i ? ' selected' : '') . '>'
               . e($nom) . '</option>';
    }
    return $html . '</select>';
};
?>

<?= Vue::rendre('budget/_onglets', ['onglet' => 'import']) ?>

<div class="entete-page">
  <div>
    <p class="discret" style="margin-bottom:.35rem">
      <a href="<?= url('budget/import') ?>">← Choisir un autre fichier</a>
    </p>
    <h1>Aperçu de l'import</h1>
    <p><?= e($nom) ?> — <?= count($lignes) ?> ligne<?= count($lignes) > 1 ? 's' : '' ?> lue<?= count($lignes) > 1 ? 's' : '' ?>.</p>
  </div>
</div>

<div class="grille grille--4" style="margin-bottom:1.25rem">
  <div class="carte stat">
    <div class="stat__valeur" style="color:var(--succes)"><?= $valides ?></div>
    <div class="stat__libelle">à importer</div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur" style="color:var(--info)"><?= $doublons ?></div>
    <div class="stat__libelle">déjà en base</div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur" style="color:<?= $invalides > 0 ? 'var(--erreur)' : 'inherit' ?>"><?= $invalides ?></div>
    <div class="stat__libelle">non exploitable<?= $invalides > 1 ? 's' : '' ?></div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur"><?= count($lignes) ?></div>
    <div class="stat__libelle">lignes lues</div>
  </div>
</div>

<section class="carte" style="margin-bottom:1.25rem">
  <h2>Colonnes reconnues</h2>
  <p class="discret" style="margin:.2rem 0 .8rem">
    Si l'aperçu ci-dessous est de travers, corrigez la correspondance ici.
  </p>

  <form method="get" action="<?= url('budget/import/apercu') ?>" class="filtres" style="margin:0">
    <input type="hidden" name="ajuste" value="1">

    <div class="champ">
      <label for="m-mode">Montants</label>
      <select id="m-mode" name="mode">
        <option value="montant"<?= ($mapping['mode'] ?? 'montant') === 'montant' ? ' selected' : '' ?>>
          Une colonne signée
        </option>
        <option value="debit_credit"<?= ($mapping['mode'] ?? '') === 'debit_credit' ? ' selected' : '' ?>>
          Débit et crédit séparés
        </option>
      </select>
    </div>

    <div class="champ">
      <label for="m-date">Date</label>
      <?= str_replace('<select', '<select id="m-date"', $choixColonne('date', $mapping['date'] ?? null)) ?>
    </div>

    <div class="champ">
      <label for="m-libelle">Libellé</label>
      <?= str_replace('<select', '<select id="m-libelle"', $choixColonne('libelle', $mapping['libelle'] ?? null)) ?>
    </div>

    <?php if (($mapping['mode'] ?? 'montant') === 'debit_credit'): ?>
      <div class="champ">
        <label for="m-debit">Débit</label>
        <?= str_replace('<select', '<select id="m-debit"', $choixColonne('debit', $mapping['debit'] ?? null)) ?>
      </div>
      <div class="champ">
        <label for="m-credit">Crédit</label>
        <?= str_replace('<select', '<select id="m-credit"', $choixColonne('credit', $mapping['credit'] ?? null)) ?>
      </div>
    <?php else: ?>
      <div class="champ">
        <label for="m-montant">Montant</label>
        <?= str_replace('<select', '<select id="m-montant"', $choixColonne('montant', $mapping['montant'] ?? null)) ?>
      </div>
    <?php endif; ?>

    <button class="bouton bouton--secondaire" type="submit">Appliquer</button>
  </form>
</section>

<form method="post" action="<?= url('budget/import/confirmer') ?>">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

  <section class="carte">
    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:.5rem;flex-wrap:wrap">
      <h2 style="margin:0">Lignes du relevé</h2>
      <span class="discret">
        Décochez ce que vous ne voulez pas. Les doublons et les lignes illisibles
        sont déjà décochés.
      </span>
    </div>

    <div style="overflow-x:auto;margin-top:.8rem">
      <table class="tableau tableau--import">
        <thead>
          <tr>
            <th scope="col"><span class="sr-only">Importer</span>✓</th>
            <th scope="col">Date</th>
            <th scope="col">Libellé</th>
            <th scope="col" class="nombre">Montant</th>
            <th scope="col">Catégorie</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lignes as $l): ?>
            <?php $importable = $l['valide'] && !$l['doublon']; ?>
            <tr<?= !$l['valide'] ? ' class="est-invalide"' : ($l['doublon'] ? ' class="est-doublon"' : '') ?>>
              <td>
                <?php if ($l['valide']): ?>
                  <input type="checkbox" name="ligne[]" value="<?= (int) $l['index'] ?>"
                         <?= $importable ? ' checked' : '' ?>
                         aria-label="Importer la ligne <?= (int) $l['index'] + 1 ?>">
                <?php else: ?>
                  <span title="Date ou montant illisible">—</span>
                <?php endif; ?>
              </td>

              <td style="white-space:nowrap">
                <?= $l['date'] !== null
                    ? e(date('d/m/Y', strtotime($l['date'])))
                    : '<span class="discret">date ?</span>' ?>
              </td>

              <td>
                <?= $l['libelle'] !== '' ? e($l['libelle']) : '<span class="discret">libellé ?</span>' ?>
                <?php if ($l['doublon']): ?>
                  <span class="pastille" style="background:var(--info-doux);color:var(--info)">déjà importée</span>
                <?php endif; ?>
              </td>

              <td class="nombre" style="color:<?= $l['sens'] === 'recette' ? 'var(--succes)' : 'var(--erreur)' ?>">
                <?php if ($l['montant'] !== null): ?>
                  <?= $l['sens'] === 'recette' ? '+' : '−' ?> <?= e(montant_fr($l['montant'])) ?>
                <?php else: ?>
                  <span class="discret">montant ?</span>
                <?php endif; ?>
              </td>

              <td>
                <?php if ($l['valide']): ?>
                  <select name="categorie[<?= (int) $l['index'] ?>]" aria-label="Catégorie de la ligne <?= (int) $l['index'] + 1 ?>">
                    <option value="">— Aucune —</option>
                    <?php foreach ($categories as $c): ?>
                      <?php if ($c['sens'] === $l['sens']): ?>
                        <option value="<?= (int) $c['id'] ?>"<?= $l['categorie'] === (int) $c['id'] ? ' selected' : '' ?>>
                          <?= e($c['icone'] . ' ' . $c['nom']) ?>
                        </option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </select>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <div class="actions" style="margin-top:1rem">
    <button class="bouton" type="submit"<?= $valides === 0 ? ' disabled' : '' ?>>
      Importer les lignes cochées
    </button>
    <span class="discret">
      Vous pourrez modifier ou supprimer chaque ligne ensuite, comme une saisie manuelle.
    </span>
  </div>
</form>

<form method="post" action="<?= url('budget/import/abandonner') ?>" style="margin-top:1rem">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <button class="bouton bouton--discret" type="submit">Abandonner cet import</button>
</form>
