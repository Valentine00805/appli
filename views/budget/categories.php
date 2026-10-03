<?php
/** @var array $depenses, $recettes, $palette, $icones, $suggestions @var int $sansCategorie */
$csrf = Session::jetonCsrf();

/** Rend le bloc d'une catégorie, avec son panneau de modification. */
$carte = static function (array $c) use ($csrf, $palette, $icones): string {
    ob_start(); ?>
    <section class="carte">
      <div class="matiere-carte">
        <span class="matiere-pastille"
              style="background:<?= e($c['couleur']) ?>;display:grid;place-items:center;font-size:1.2rem">
          <?= e($c['icone']) ?>
        </span>

        <div style="flex:1;min-width:0">
          <h3 style="margin-bottom:.15rem;font-size:1.05rem"><?= e($c['nom']) ?></h3>
          <p class="discret" style="margin:0">
            <?= e(tn('cat.nb_operations', (int) $c['nb_operations'])) ?>
            <?php if ((int) $c['nb_operations'] > 0): ?>
              <?= e(t('cat.total', ['montant' => montant_lisible($c['total'])])) ?>
            <?php endif; ?>
            <?php if ($c['plafond_mensuel'] !== null): ?>
              <?= e(t('cat.plafond', ['montant' => montant_lisible($c['plafond_mensuel'])])) ?>
            <?php endif; ?>
          </p>
        </div>

        <div class="actions">
          <a class="bouton bouton--discret bouton--petit"
             href="<?= url('budget', ['categorie' => $c['id']]) ?>"><?= e(t('commun.voir')) ?></a>
          <button class="bouton bouton--secondaire bouton--petit" type="button"
                  data-bascule="edition-cat-<?= (int) $c['id'] ?>"><?= e(t('evt.modifier')) ?></button>
        </div>
      </div>

      <div id="edition-cat-<?= (int) $c['id'] ?>" hidden style="margin-top:1rem">
        <hr class="separateur" style="margin:.75rem 0">

        <form method="post" action="<?= url('budget/categories/' . $c['id'] . '/modifier') ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

          <div class="champ">
            <label for="nom-c-<?= (int) $c['id'] ?>"><?= e(t('commun.nom')) ?></label>
            <input type="text" id="nom-c-<?= (int) $c['id'] ?>" name="nom" required maxlength="60"
                   value="<?= e($c['nom']) ?>">
          </div>

          <div class="champ">
            <span class="legende"><?= e(t('commun.icone')) ?></span>
            <div class="choix-icones">
              <?php foreach ($icones as $j => $icone): ?>
                <?php $id = 'ic-' . $c['id'] . '-' . $j; ?>
                <input type="radio" id="<?= $id ?>" name="icone" value="<?= e($icone) ?>"
                       <?= $c['icone'] === $icone ? ' checked' : '' ?>>
                <label for="<?= $id ?>"><?= e($icone) ?></label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="champ">
            <span class="legende"><?= e(t('commun.couleur')) ?></span>
            <div class="choix-couleurs">
              <?php foreach ($palette as $j => $couleur): ?>
                <?php $id = 'cc-' . $c['id'] . '-' . $j; ?>
                <input type="radio" id="<?= $id ?>" name="couleur" value="<?= e($couleur) ?>"
                       <?= strtolower((string) $c['couleur']) === $couleur ? ' checked' : '' ?>>
                <label for="<?= $id ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
              <?php endforeach; ?>
            </div>
          </div>

          <?php if ($c['sens'] === 'depense'): ?>
            <div class="champ">
              <label for="plafond-<?= (int) $c['id'] ?>"><?= e(t('cat.plafond_mensuel')) ?></label>
              <input type="text" id="plafond-<?= (int) $c['id'] ?>" name="plafond_mensuel" inputmode="decimal"
                     placeholder="<?= e(t('cat.plafond_vide')) ?>"
                     value="<?= $c['plafond_mensuel'] !== null ? e(montant_lisible($c['plafond_mensuel'], false)) : '' ?>">
              <span class="champ__aide">
                <?= e(t('cat.plafond_aide')) ?>
              </span>
            </div>
          <?php endif; ?>

          <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
        </form>

        <form method="post" action="<?= url('budget/categories/' . $c['id'] . '/supprimer') ?>" style="margin-top:.75rem"
              data-confirmation="<?= e(t('cat.supprimer_sur')) ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('cat.supprimer')) ?></button>
        </form>
      </div>
    </section>
    <?php
    return (string) ob_get_clean();
};
?>

<?= Vue::rendre('budget/_onglets', ['onglet' => 'categories']) ?>

<div class="entete-page">
  <div>
    <h1><?= e(t('cat.titre')) ?></h1>
    <p><?= e(t('cat.sous_titre')) ?></p>
  </div>
  <div class="actions">
    <a class="bouton bouton--secondaire" href="<?= url('budget') ?>"><?= e(t('remb.voir_operations')) ?></a>
  </div>
</div>

<section class="carte" style="margin-bottom:1.5rem;border-color:var(--accent)">
  <div style="display:flex;justify-content:space-between;align-items:baseline;gap:.6rem;flex-wrap:wrap">
    <h2 style="margin:0"><?= e(t('cat.budget_propose')) ?></h2>
    <?php if ($suggestions['suffisant']): ?>
      <span class="discret">
        <?= e(t('cat.dapres', [
            'n' => (int) $suggestions['mois_disponibles'], 'periode' => (string) $suggestions['periode'],
        ])) ?>
      </span>
    <?php endif; ?>
  </div>

  <?php if (!$suggestions['suffisant']): ?>
    <?php $manque = SuggestionBudget::MOIS_MINIMUM - (int) $suggestions['mois_disponibles']; ?>
    <p class="discret" style="margin:.5rem 0 0">
      <?php if ((int) $suggestions['mois_disponibles'] === 0): ?>
        <?= e(t('cat.aucune_depense_classee', ['n' => SuggestionBudget::MOIS_MINIMUM])) ?>
      <?php else: ?>
        <?= e(tn('cat.mois_enregistres', (int) $suggestions['mois_disponibles'])) ?>
        <?= e(tn('cat.encore_mois', $manque, ['min' => SuggestionBudget::MOIS_MINIMUM])) ?>
      <?php endif; ?>
    </p>
    <div class="jauge" style="margin-top:.6rem;max-width:300px">
      <span style="width:<?= min(100, ((int) $suggestions['mois_disponibles'] / SuggestionBudget::MOIS_MINIMUM) * 100) ?>%;background:var(--accent)"></span>
    </div>

  <?php else: ?>
    <p class="discret" style="margin:.4rem 0 1rem">
      <?= e(t('cat.fourchette_aide')) ?>
      <?php if (!$suggestions['fiable']): ?>
        <?= t('cat.pincettes', ['n' => SuggestionBudget::MOIS_CONFIANCE]) ?>
      <?php endif; ?>
    </p>

    <div style="overflow-x:auto">
      <table class="tableau">
        <thead>
          <tr>
            <th scope="col"><?= e(t('cat.col_poste')) ?></th>
            <th scope="col" class="nombre"><?= e(t('cat.col_fourchette')) ?></th>
            <th scope="col" class="nombre"><?= e(t('cat.col_a_prevoir')) ?></th>
            <th scope="col"><?= e(t('cat.col_observe')) ?></th>
            <th scope="col"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($suggestions['categories'] as $s): ?>
            <tr>
              <th scope="row" style="font-weight:600">
                <span aria-hidden="true"><?= e($s['icone']) ?></span> <?= e($s['nom']) ?>
                <?php if ($s['regulier']): ?>
                  <span class="pastille" style="background:var(--succes-doux);color:var(--succes)"
                        title="<?= e(t('cat.regulier_titre')) ?>"><?= e(t('cat.regulier')) ?></span>
                <?php endif; ?>
              </th>
              <td class="nombre">
                <?= e(montant_lisible($s['bas'])) ?> <span class="discret"><?= e(t('cat.a')) ?></span> <?= e(montant_lisible($s['haut'])) ?>
              </td>
              <td class="nombre"><strong><?= e(montant_lisible($s['conseille'])) ?></strong></td>
              <td class="discret" style="font-size:.82rem;white-space:nowrap">
                <?= e(t('cat.mois_de_a', [
                    'n' => (int) $s['mois'], 'mini' => montant_lisible($s['mini']), 'maxi' => montant_lisible($s['maxi']),
                ])) ?>
              </td>
              <td>
                <?php if ($s['plafond'] !== null && abs($s['plafond'] - $s['conseille']) < 0.005): ?>
                  <span class="pastille" style="background:var(--succes-doux);color:var(--succes)"><?= e(t('cat.applique')) ?></span>
                <?php else: ?>
                  <form method="post" action="<?= url('budget/suggestions/' . $s['id'] . '/appliquer') ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button class="bouton bouton--secondaire bouton--petit" type="submit"
                            title="<?= e(t('cat.fixer_plafond_titre')) ?>">
                      <?= e(t('cat.utiliser')) ?><?= $s['plafond'] !== null ? e(t('cat.utiliser_remplace', ['montant' => montant_lisible($s['plafond'])])) : '' ?>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr style="border-top:2px solid var(--bordure-forte)">
            <th scope="row"><?= e(t('cat.ensemble')) ?></th>
            <td class="nombre">
              <?= e(montant_lisible($suggestions['total_bas'])) ?>
              <span class="discret"><?= e(t('cat.a')) ?></span>
              <?= e(montant_lisible($suggestions['total_haut'])) ?>
            </td>
            <td class="nombre"><strong><?= e(montant_lisible($suggestions['total_conseille'])) ?></strong></td>
            <td colspan="2"></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="actions" style="margin-top:1rem">
      <form method="post" action="<?= url('budget/suggestions/appliquer') ?>"
            data-confirmation="<?= e(t('cat.appliquer_tout_sur')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <button class="bouton" type="submit"><?= e(t('cat.appliquer_tout')) ?></button>
      </form>
      <span class="discret">
        <?= e(t('cat.modifiables')) ?>
      </span>
    </div>
  <?php endif; ?>
</section>

<div class="colonnes">
  <div class="pile">
    <h2 style="margin-bottom:0"><?= e(t('bud.depenses')) ?></h2>
    <?php if ($depenses === []): ?>
      <p class="discret"><?= e(t('cat.aucune_depense_cat')) ?></p>
    <?php else: ?>
      <?php foreach ($depenses as $c): ?><?= $carte($c) ?><?php endforeach; ?>
    <?php endif; ?>

    <h2 style="margin:1rem 0 0"><?= e(t('bud.recettes')) ?></h2>
    <?php if ($recettes === []): ?>
      <p class="discret"><?= e(t('cat.aucune_recette_cat')) ?></p>
    <?php else: ?>
      <?php foreach ($recettes as $c): ?><?= $carte($c) ?><?php endforeach; ?>
    <?php endif; ?>

    <?php if ($sansCategorie > 0): ?>
      <p class="discret">
        <?= e(tn('cat.sans_categorie', $sansCategorie)) ?>
        <a href="<?= url('budget') ?>"><?= e(t('mat.sans_matiere_lien')) ?></a>.
      </p>
    <?php endif; ?>
  </div>

  <div class="carte">
    <h2><?= e(t('cat.nouvelle')) ?></h2>
    <form method="post" action="<?= url('budget/categories') ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

      <fieldset style="margin-bottom:1rem">
        <legend><?= e(t('cat.type')) ?></legend>
        <div style="display:flex;gap:1rem">
          <label class="case"><input type="radio" name="sens" value="depense" checked> <?= e(t('bud.depense')) ?></label>
          <label class="case"><input type="radio" name="sens" value="recette"> <?= e(t('bud.recette')) ?></label>
        </div>
      </fieldset>

      <div class="champ">
        <label for="nom"><?= e(t('commun.nom')) ?></label>
        <input type="text" id="nom" name="nom" required maxlength="60" placeholder="<?= e(t('cat.nom_exemple')) ?>">
      </div>

      <div class="champ">
        <span class="legende"><?= e(t('commun.icone')) ?></span>
        <div class="choix-icones">
          <?php foreach ($icones as $j => $icone): ?>
            <input type="radio" id="nic-<?= $j ?>" name="icone" value="<?= e($icone) ?>"<?= $j === 0 ? ' checked' : '' ?>>
            <label for="nic-<?= $j ?>"><?= e($icone) ?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="champ">
        <span class="legende"><?= e(t('commun.couleur')) ?></span>
        <div class="choix-couleurs">
          <?php foreach ($palette as $j => $couleur): ?>
            <input type="radio" id="ncc-<?= $j ?>" name="couleur" value="<?= e($couleur) ?>"<?= $j === 0 ? ' checked' : '' ?>>
            <label for="ncc-<?= $j ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="champ">
        <label for="plafond_mensuel"><?= e(t('cat.plafond_mensuel')) ?> <span class="discret"><?= e(t('cat.depenses_seulement')) ?></span></label>
        <input type="text" id="plafond_mensuel" name="plafond_mensuel" inputmode="decimal" placeholder="<?= e(t('commun.facultatif_mot')) ?>">
      </div>

      <button class="bouton bouton--bloc" type="submit"><?= e(t('cat.creer')) ?></button>
    </form>
  </div>
</div>
