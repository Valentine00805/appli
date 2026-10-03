<?php
/**
 * @var string $nom
 * @var array $lignes, $rubriques, $categories, $controles, $personnes
 * @var int $ignorees, $doublons
 */
$csrf = Session::jetonCsrf();
$aImporter = count(array_filter($lignes, static fn (array $l): bool => !$l['doublon']));
$ecarts = array_filter($controles, static fn (array $c): bool => $c['ecart'] !== null && abs($c['ecart']) >= 0.02);
?>

<?= Vue::rendre('budget/_onglets', ['onglet' => 'import']) ?>

<div class="entete-page">
  <div>
    <p class="discret" style="margin-bottom:.35rem">
      <a href="<?= url('budget/import') ?>"><?= e(t('apc.autre_fichier')) ?></a>
    </p>
    <h1><?= e(t('cls.titre')) ?></h1>
    <p><?= e(tn('cls.depenses', count($lignes), ['nom' => $nom])) ?></p>
  </div>
</div>

<div class="grille grille--4" style="margin-bottom:1.25rem">
  <div class="carte stat">
    <div class="stat__valeur" style="color:var(--succes)"><?= $aImporter ?></div>
    <div class="stat__libelle"><?= e(t('cls.a_reprendre')) ?></div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur" style="color:var(--info)"><?= $doublons ?></div>
    <div class="stat__libelle"><?= e(t('apc.deja_en_base')) ?></div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur"><?= count($rubriques) ?></div>
    <div class="stat__libelle"><?= e(tn('cls.rubriques', count($rubriques))) ?></div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur" style="color:<?= $ignorees > 0 ? 'var(--erreur)' : 'inherit' ?>"><?= $ignorees ?></div>
    <div class="stat__libelle"><?= e(tn('cls.illisibles', $ignorees)) ?></div>
  </div>
</div>

<section class="carte" style="margin-bottom:1.25rem">
  <h2><?= e(t('cls.controle')) ?></h2>
  <p class="discret" style="margin:.2rem 0 .8rem">
    <?= e(t('cls.controle_aide')) ?>
  </p>
  <table class="tableau" style="max-width:560px">
    <thead>
      <tr>
        <th scope="col"><?= e(t('remb.mois')) ?></th>
        <th scope="col" class="nombre"><?= e(t('cls.col_recalcule')) ?></th>
        <th scope="col" class="nombre"><?= e(t('cls.col_feuille')) ?></th>
        <th scope="col"><?= e(t('cls.col_ecart')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($controles as $c): ?>
        <tr>
          <th scope="row" style="font-weight:500;text-transform:capitalize">
            <?= e(nom_mois_en_phrase((int) substr($c['mois'], 5, 2)) . ' ' . substr($c['mois'], 0, 4)) ?>
          </th>
          <td class="nombre"><?= e(montant_lisible($c['recalcule'])) ?></td>
          <td class="nombre"><?= $c['feuille'] === null
              ? '<span class="discret">' . e(t('cls.non_indique')) . '</span>'
              : e(montant_lisible($c['feuille'])) ?></td>
          <td>
            <?php if ($c['ecart'] === null): ?>
              <span class="discret">—</span>
            <?php elseif (abs($c['ecart']) < 0.02): ?>
              <span class="pastille" style="background:var(--succes-doux);color:var(--succes)"><?= e(t('cls.identique')) ?></span>
            <?php else: ?>
              <span class="pastille" style="background:var(--erreur-doux);color:var(--erreur)">
                <?= $c['ecart'] > 0 ? '+' : '−' ?> <?= e(montant_lisible(abs($c['ecart']))) ?>
              </span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<form method="post" action="<?= url('budget/import/confirmer') ?>">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

  <?php if ($rubriques !== []): ?>
    <section class="carte" style="margin-bottom:1.25rem">
      <h2><?= e(t('cls.rubriques_titre')) ?></h2>
      <p class="discret" style="margin:.2rem 0 .8rem">
        <?= e(t('cls.rubriques_aide')) ?>
      </p>
      <div class="grille grille--2">
        <?php foreach ($rubriques as $r): ?>
          <div class="champ" style="margin:0">
            <label for="rub-<?= e(md5($r['nom'])) ?>">
              <?= e($r['nom']) ?> <span class="discret"><?= e(tn('cls.nb_lignes', (int) $r['nb'])) ?></span>
            </label>
            <select id="rub-<?= e(md5($r['nom'])) ?>" name="rubrique[<?= e($r['nom']) ?>]">
              <option value="creer"<?= $r['categorie'] === null ? ' selected' : '' ?>>
                <?= e(t('cls.creer_categorie', ['nom' => $r['nom']])) ?>
              </option>
              <?php foreach ($categories as $c): ?>
                <?php if ($c['sens'] === 'depense'): ?>
                  <option value="<?= (int) $c['id'] ?>"<?= $r['categorie'] === (int) $c['id'] ? ' selected' : '' ?>>
                    <?= e($c['icone'] . ' ' . $c['nom']) ?>
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
              <option value="aucune"><?= e(t('cls.sans_categorie')) ?></option>
            </select>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="carte" style="margin-bottom:1.25rem">
    <h2><?= e(t('cls.reglages')) ?></h2>
    <div class="grille grille--2">
      <div>
        <?= Vue::rendre('budget/_qui_rembourse', [
            'personnes' => $personnes,
            'valeur'    => 'Parents',
            'libelle'   => t('cls.qui_devait'),
        ]) ?>
        <span class="champ__aide"><?= e(t('cls.toutes_cochees')) ?></span>
      </div>
      <div class="champ" style="margin:0">
        <span class="legende"><?= e(t('cls.soldees')) ?></span>
        <label class="case">
          <input type="checkbox" name="deja_rembourse" value="1" checked>
          <?= e(t('cls.oui_remboursees')) ?>
        </label>
        <span class="champ__aide">
          <?= e(t('cls.soldees_aide')) ?>
        </span>
      </div>
    </div>
  </section>

  <section class="carte">
    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:.5rem;flex-wrap:wrap">
      <h2 style="margin:0"><?= e(t('cls.depenses_reconnues')) ?></h2>
      <span class="discret"><?= e(t('cls.deja_decochees')) ?></span>
    </div>

    <div style="overflow-x:auto;margin-top:.8rem">
      <table class="tableau tableau--import">
        <thead>
          <tr>
            <th scope="col"><span class="sr-only"><?= e(t('cls.reprendre_col')) ?></span>✓</th>
            <th scope="col"><?= e(t('bud.date')) ?></th>
            <th scope="col"><?= e(t('remb.col_libelle')) ?></th>
            <th scope="col" class="nombre"><?= e(t('remb.col_paye')) ?></th>
            <th scope="col" class="nombre"><?= e(t('remb.col_reclame')) ?></th>
            <th scope="col"><?= e(t('cls.col_rubrique')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lignes as $l): ?>
            <tr<?= $l['doublon'] ? ' class="est-doublon"' : ($l['statut'] === 'hors_total' ? ' class="est-hors-total"' : '') ?>>
              <td>
                <input type="checkbox" name="ligne[]" value="<?= (int) $l['index'] ?>"
                       <?= $l['doublon'] ? '' : ' checked' ?>
                       aria-label="<?= e(t('cls.reprendre_ligne', ['nom' => $l['libelle']])) ?>">
              </td>
              <td style="white-space:nowrap"><?= e(date_numerique($l['date'])) ?></td>
              <td>
                <?= e($l['libelle']) ?>
                <?php if ($l['doublon']): ?>
                  <span class="pastille" style="background:var(--info-doux);color:var(--info)"><?= e(t('cls.deja_reprise')) ?></span>
                <?php endif; ?>
                <?php if ($l['statut'] === 'hors_total'): ?>
                  <span class="pastille"><?= e(t('cls.hors_total')) ?></span>
                <?php endif; ?>
              </td>
              <td class="nombre"><?= e(montant_lisible($l['montant'])) ?></td>
              <td class="nombre">
                <?php if ($l['part'] !== null): ?>
                  <strong><?= e(montant_lisible($l['part'])) ?></strong>
                  <span class="discret" title="<?= e(t('cls.bloc_partage')) ?>">◐</span>
                <?php else: ?>
                  <?= e(montant_lisible($l['montant'])) ?>
                <?php endif; ?>
              </td>
              <td class="discret"><?= e($l['rubrique'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <div class="actions" style="margin-top:1rem">
    <button class="bouton" type="submit"<?= $aImporter === 0 ? ' disabled' : '' ?>>
      <?= e(t('cls.reprendre_cochees')) ?>
    </button>
    <span class="discret"><?= e(t('cls.modifiable_ensuite')) ?></span>
  </div>
</form>

<form method="post" action="<?= url('budget/import/abandonner') ?>" style="margin-top:1rem">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <button class="bouton bouton--discret" type="submit"><?= e(t('apc.abandonner')) ?></button>
</form>
