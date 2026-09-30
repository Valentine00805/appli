<?php
/**
 * @var string $nom
 * @var array $entetes, $colonnes, $mapping, $lignes, $categories
 * @var int $valides, $doublons, $invalides
 */
$csrf = Session::jetonCsrf();

/** Liste de choix d'une colonne du fichier. */
$choixColonne = static function (string $champ, ?int $actif) use ($colonnes): string {
    $html = '<select name="' . e($champ) . '" aria-label="' . e(t('apc.colonne', ['champ' => $champ])) . '">';
    $html .= '<option value="">' . e(t('apc.aucune')) . '</option>';
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
      <a href="<?= url('budget/import') ?>"><?= e(t('apc.autre_fichier')) ?></a>
    </p>
    <h1><?= e(t('apc.titre')) ?></h1>
    <p><?= e(tn('apc.lignes_lues', count($lignes), ['nom' => $nom])) ?></p>
  </div>
</div>

<div class="grille grille--4" style="margin-bottom:1.25rem">
  <div class="carte stat">
    <div class="stat__valeur" style="color:var(--succes)"><?= $valides ?></div>
    <div class="stat__libelle"><?= e(t('apc.a_importer')) ?></div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur" style="color:var(--info)"><?= $doublons ?></div>
    <div class="stat__libelle"><?= e(t('apc.deja_en_base')) ?></div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur" style="color:<?= $invalides > 0 ? 'var(--erreur)' : 'inherit' ?>"><?= $invalides ?></div>
    <div class="stat__libelle"><?= e(tn('apc.non_exploitable', $invalides)) ?></div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur"><?= count($lignes) ?></div>
    <div class="stat__libelle"><?= e(t('apc.lignes_lues_stat')) ?></div>
  </div>
</div>

<section class="carte" style="margin-bottom:1.25rem">
  <h2><?= e(t('apc.colonnes_reconnues')) ?></h2>
  <p class="discret" style="margin:.2rem 0 .8rem">
    <?= e(t('apc.colonnes_aide')) ?>
  </p>

  <form method="get" action="<?= url('budget/import/apercu') ?>" class="filtres" style="margin:0">
    <input type="hidden" name="ajuste" value="1">

    <div class="champ">
      <label for="m-mode"><?= e(t('apc.montants')) ?></label>
      <select id="m-mode" name="mode">
        <option value="montant"<?= ($mapping['mode'] ?? 'montant') === 'montant' ? ' selected' : '' ?>>
          <?= e(t('apc.colonne_signee')) ?>
        </option>
        <option value="debit_credit"<?= ($mapping['mode'] ?? '') === 'debit_credit' ? ' selected' : '' ?>>
          <?= e(t('apc.debit_credit')) ?>
        </option>
      </select>
    </div>

    <div class="champ">
      <label for="m-date"><?= e(t('bud.date')) ?></label>
      <?= str_replace('<select', '<select id="m-date"', $choixColonne('date', $mapping['date'] ?? null)) ?>
    </div>

    <div class="champ">
      <label for="m-libelle"><?= e(t('remb.col_libelle')) ?></label>
      <?= str_replace('<select', '<select id="m-libelle"', $choixColonne('libelle', $mapping['libelle'] ?? null)) ?>
    </div>

    <?php if (($mapping['mode'] ?? 'montant') === 'debit_credit'): ?>
      <div class="champ">
        <label for="m-debit"><?= e(t('apc.debit')) ?></label>
        <?= str_replace('<select', '<select id="m-debit"', $choixColonne('debit', $mapping['debit'] ?? null)) ?>
      </div>
      <div class="champ">
        <label for="m-credit"><?= e(t('apc.credit')) ?></label>
        <?= str_replace('<select', '<select id="m-credit"', $choixColonne('credit', $mapping['credit'] ?? null)) ?>
      </div>
    <?php else: ?>
      <div class="champ">
        <label for="m-montant"><?= e(t('bud.montant')) ?></label>
        <?= str_replace('<select', '<select id="m-montant"', $choixColonne('montant', $mapping['montant'] ?? null)) ?>
      </div>
    <?php endif; ?>

    <button class="bouton bouton--secondaire" type="submit"><?= e(t('apc.appliquer')) ?></button>
  </form>
</section>

<form method="post" action="<?= url('budget/import/confirmer') ?>">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

  <section class="carte">
    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:.5rem;flex-wrap:wrap">
      <h2 style="margin:0"><?= e(t('apc.lignes_releve')) ?></h2>
      <span class="discret">
        <?= e(t('apc.decochez')) ?>
      </span>
    </div>

    <div style="overflow-x:auto;margin-top:.8rem">
      <table class="tableau tableau--import">
        <thead>
          <tr>
            <th scope="col"><span class="sr-only"><?= e(t('apc.importer_col')) ?></span>✓</th>
            <th scope="col"><?= e(t('bud.date')) ?></th>
            <th scope="col"><?= e(t('remb.col_libelle')) ?></th>
            <th scope="col" class="nombre"><?= e(t('bud.montant')) ?></th>
            <th scope="col"><?= e(t('bud.categorie')) ?></th>
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
                         aria-label="<?= e(t('apc.importer_ligne', ['n' => (int) $l['index'] + 1])) ?>">
                <?php else: ?>
                  <span title="<?= e(t('apc.illisible')) ?>">—</span>
                <?php endif; ?>
              </td>

              <td style="white-space:nowrap">
                <?= $l['date'] !== null
                    ? e(date('d/m/Y', strtotime($l['date'])))
                    : '<span class="discret">' . e(t('apc.date_inconnue')) . '</span>' ?>
              </td>

              <td>
                <?= $l['libelle'] !== '' ? e($l['libelle']) : '<span class="discret">' . e(t('apc.libelle_inconnu')) . '</span>' ?>
                <?php if ($l['doublon']): ?>
                  <span class="pastille" style="background:var(--info-doux);color:var(--info)"><?= e(t('apc.deja_importee')) ?></span>
                <?php endif; ?>
              </td>

              <td class="nombre" style="color:<?= $l['sens'] === 'recette' ? 'var(--succes)' : 'var(--erreur)' ?>">
                <?php if ($l['montant'] !== null): ?>
                  <?= $l['sens'] === 'recette' ? '+' : '−' ?> <?= e(montant_fr($l['montant'])) ?>
                <?php else: ?>
                  <span class="discret"><?= e(t('apc.montant_inconnu')) ?></span>
                <?php endif; ?>
              </td>

              <td>
                <?php if ($l['valide']): ?>
                  <select name="categorie[<?= (int) $l['index'] ?>]" aria-label="<?= e(t('apc.categorie_ligne', ['n' => (int) $l['index'] + 1])) ?>">
                    <option value=""><?= e(t('bud.aucune_categorie')) ?></option>
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
      <?= e(t('apc.importer_cochees')) ?>
    </button>
    <span class="discret">
      <?= e(t('apc.modifier_ensuite')) ?>
    </span>
  </div>
</form>

<form method="post" action="<?= url('budget/import/abandonner') ?>" style="margin-top:1rem">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <button class="bouton bouton--discret" type="submit"><?= e(t('apc.abandonner')) ?></button>
</form>
