<?php
/**
 * @var DateTimeImmutable $mois
 * @var array $operations, $totaux, $parCategorie, $categories, $moyens, $historique
 * @var ?int $categorieId
 * @var ?string $sens, $origine
 */
$csrf = Session::jetonCsrf();
$precedent = $mois->modify('-1 month');
$suivant   = $mois->modify('+1 month');
$moisCourant = (new DateTimeImmutable('today'))->format('Y-m');

$filtres = array_filter([
    'categorie' => $categorieId,
    'sens'      => $sens,
    'origine'   => $origine,
], static fn ($v): bool => $v !== null);

$lienMois = static fn (DateTimeInterface $m): string
    => url('budget', $filtres + ['mois' => $m->format('Y-m')]);

$totalDepenses = (float) $totaux['depenses'];
$plafondHistorique = max(array_merge([1.0], array_map(
    static fn (array $h): float => max($h['recettes'], $h['depenses']),
    $historique
)));
?>

<?= Vue::rendre('budget/_onglets', ['onglet' => 'operations']) ?>

<div class="entete-page">
  <div>
    <h1><?= e(t('bud.titre')) ?></h1>
    <p><?= e(t('bud.sous_titre')) ?></p>
  </div>
  <div class="actions">
    <a class="bouton bouton--secondaire" href="<?= url('budget/import') ?>"><?= e(t('bud.importer')) ?></a>
    <a class="bouton bouton--secondaire bouton--petit" href="<?= $lienMois($precedent) ?>"
       aria-label="<?= e(t('bud.mois_precedent')) ?>">←</a>
    <?php if ($mois->format('Y-m') !== $moisCourant): ?>
      <a class="bouton bouton--secondaire bouton--petit"
         href="<?= url('budget', $filtres + ['mois' => $moisCourant]) ?>"><?= e(t('bud.ce_mois')) ?></a>
    <?php endif; ?>
    <a class="bouton bouton--secondaire bouton--petit" href="<?= $lienMois($suivant) ?>"
       aria-label="<?= e(t('bud.mois_suivant')) ?>">→</a>
    <h2 class="cal-titre" style="text-transform:capitalize">
      <?= e(strtolower(nom_mois((int) $mois->format('n'))) . ' ' . $mois->format('Y')) ?>
    </h2>
  </div>
</div>

<div class="grille grille--4" style="margin-bottom:1.5rem">
  <div class="carte stat">
    <div class="stat__valeur" style="color:var(--succes)">+ <?= e(montant_lisible($totaux['recettes'])) ?></div>
    <div class="stat__libelle"><?= e(t('bud.recettes_mois')) ?></div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur" style="color:var(--erreur)">− <?= e(montant_lisible($totaux['depenses'])) ?></div>
    <div class="stat__libelle"><?= e(t('bud.depenses_mois')) ?></div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur" style="color:<?= $totaux['solde'] >= 0 ? 'var(--succes)' : 'var(--erreur)' ?>">
      <?= $totaux['solde'] >= 0 ? '+ ' : '− ' ?><?= e(montant_lisible(abs($totaux['solde']))) ?>
    </div>
    <div class="stat__libelle"><?= e(t('bud.solde')) ?></div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur"><?= (int) $totaux['nb'] ?></div>
    <div class="stat__libelle"><?= e(tn('bud.operations', (int) $totaux['nb'])) ?></div>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <?php if ($categories !== []): ?>
      <form class="filtres" method="get" action="<?= url('budget') ?>" data-auto-envoi>
        <input type="hidden" name="mois" value="<?= e($mois->format('Y-m')) ?>">
        <div class="champ">
          <label for="f-sens"><?= e(t('bud.sens')) ?></label>
          <select id="f-sens" name="sens">
            <option value=""><?= e(t('bud.tout')) ?></option>
            <option value="depense"<?= $sens === 'depense' ? ' selected' : '' ?>><?= e(t('bud.depenses')) ?></option>
            <option value="recette"<?= $sens === 'recette' ? ' selected' : '' ?>><?= e(t('bud.recettes')) ?></option>
          </select>
        </div>
        <div class="champ">
          <label for="f-origine"><?= e(t('bud.origine')) ?></label>
          <select id="f-origine" name="origine">
            <option value=""><?= e(t('bud.toutes')) ?></option>
            <option value="manuelle"<?= $origine === 'manuelle' ? ' selected' : '' ?>><?= e(t('bud.saisies_main')) ?></option>
            <option value="import"<?= $origine === 'import' ? ' selected' : '' ?>><?= e(t('bud.importees')) ?></option>
          </select>
        </div>
        <div class="champ">
          <label for="f-cat"><?= e(t('bud.categorie')) ?></label>
          <select id="f-cat" name="categorie">
            <option value=""><?= e(t('bud.toutes')) ?></option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int) $c['id'] ?>"<?= $categorieId === (int) $c['id'] ? ' selected' : '' ?>>
                <?= e($c['icone'] . ' ' . $c['nom']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($filtres !== []): ?>
          <a class="bouton bouton--discret" href="<?= url('budget', ['mois' => $mois->format('Y-m')]) ?>">
            <?= e(t('bud.reinitialiser')) ?>
          </a>
        <?php endif; ?>
        <noscript><button class="bouton bouton--secondaire bouton--petit" type="submit"><?= e(t('bud.filtrer')) ?></button></noscript>
      </form>
    <?php endif; ?>

    <?php if ($operations === []): ?>
      <div class="vide">
        <span class="vide__icone">💶</span>
        <p><?= e(t($filtres !== [] ? 'bud.aucune_filtres' : 'bud.aucune_mois')) ?></p>
        <?php if ($filtres !== []): ?>
          <a class="bouton bouton--secondaire" href="<?= url('budget', ['mois' => $mois->format('Y-m')]) ?>">
            <?= e(t('bud.voir_tout_mois')) ?>
          </a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="pile">
        <?php
        $jourCourant = null;
        foreach ($operations as $op):
            if ($op['date_operation'] !== $jourCourant):
                $jourCourant = $op['date_operation'];
                ?>
                <h3 style="margin:.8rem 0 .1rem;font-size:.92rem;text-transform:capitalize;color:var(--texte-doux)">
                  <?= e(date_fr($op['date_operation'] . ' 00:00:00', false)) ?>
                </h3>
            <?php endif; ?>

            <div class="evt-ligne">
              <span class="evt-ligne__barre" style="background:<?= e(couleur_operation($op)) ?>"></span>
              <span style="font-size:1.15rem" aria-hidden="true"><?= e(icone_categorie($op)) ?></span>

              <span style="min-width:0;flex:1">
                <span class="evt-ligne__titre"><?= e($op['libelle']) ?></span><br>
                <span class="evt-ligne__meta">
                  <?= e(libelle_categorie($op)) ?><?= $op['moyen'] ? ' · ' . e(BudgetController::moyenNom((string) $op['moyen'])) : '' ?><?php
                    if (($op['source'] ?? 'manuelle') === 'import') {
                        echo ' · <span title="' . e(t('bud.importee_titre')) . '">📥 ' . e(t('bud.importee')) . '</span>';
                    }
                  ?>
                </span>
                <?php if ($op['note']): ?>
                  <br><span class="evt-ligne__meta"><?= e(extrait($op['note'], 90)) ?></span>
                <?php endif; ?>
              </span>

              <span class="evt-ligne__droite">
                <strong style="font-variant-numeric:tabular-nums;white-space:nowrap;color:<?=
                    $op['sens'] === 'recette' ? 'var(--succes)' : 'var(--erreur)' ?>">
                  <?= $op['sens'] === 'recette' ? '+' : '−' ?> <?= e(montant_lisible($op['montant'])) ?>
                </strong>
                <form method="post" action="<?= url('operations/' . $op['id'] . '/rembourser') ?>" class="en-ligne">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="retour" value="budget">
                  <input type="hidden" name="mois" value="<?= e($mois->format('Y-m')) ?>">
                  <button class="bouton bouton--discret bouton--petit" type="submit"
                          title="<?= e(t((int) $op['a_rembourser'] === 1 ? 'bud.retirer_remboursements' : 'bud.a_rembourser')) ?>">
                    <?= (int) $op['a_rembourser'] === 1 ? '🧾' : '<span style="opacity:.35">🧾</span>' ?>
                  </button>
                </form>
                <a class="bouton bouton--discret bouton--petit"
                   href="<?= url('budget/operations/' . $op['id'] . '/modifier') ?>" title="<?= e(t('evt.modifier')) ?>">✎</a>
                <form method="post" action="<?= url('budget/operations/' . $op['id'] . '/supprimer') ?>"
                      class="en-ligne" data-confirmation="<?= e(t('bud.supprimer_operation_sur')) ?>">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <button class="bouton bouton--discret bouton--petit" type="submit" title="<?= e(t('commun.supprimer')) ?>">✕</button>
                </form>
              </span>
            </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="pile">
    <div class="carte">
      <h2><?= e(t('bud.ajouter')) ?></h2>
      <form method="post" action="<?= url('budget/operations') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

        <fieldset style="margin-bottom:1rem">
          <legend><?= e(t('bud.sens')) ?></legend>
          <div style="display:flex;gap:1rem">
            <label class="case"><input type="radio" name="sens" value="depense" checked> <?= e(t('bud.depense')) ?></label>
            <label class="case"><input type="radio" name="sens" value="recette"> <?= e(t('bud.recette')) ?></label>
          </div>
        </fieldset>

        <div class="champ">
          <label for="libelle"><?= e(t('bud.intitule')) ?></label>
          <input type="text" id="libelle" name="libelle" required maxlength="160" placeholder="<?= e(t('bud.intitule_exemple')) ?>">
        </div>

        <div class="ligne-champs">
          <div class="champ">
            <label for="montant"><?= e(t('bud.montant')) ?></label>
            <input type="text" id="montant" name="montant" required inputmode="decimal" placeholder="12,50">
          </div>
          <div class="champ">
            <label for="date_operation"><?= e(t('bud.date')) ?></label>
            <input type="date" id="date_operation" name="date_operation" required
                   value="<?= e($mois->format('Y-m') === $moisCourant ? date('Y-m-d') : $mois->format('Y-m-01')) ?>">
          </div>
        </div>

        <div class="champ">
          <label for="categorie_id"><?= e(t('bud.categorie')) ?></label>
          <select id="categorie_id" name="categorie_id">
            <option value=""><?= e(t('bud.aucune_categorie')) ?></option>
            <optgroup label="<?= e(t('bud.depenses')) ?>">
              <?php foreach ($categories as $c): ?>
                <?php if ($c['sens'] === 'depense'): ?>
                  <option value="<?= (int) $c['id'] ?>"><?= e($c['icone'] . ' ' . $c['nom']) ?></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </optgroup>
            <optgroup label="<?= e(t('bud.recettes')) ?>">
              <?php foreach ($categories as $c): ?>
                <?php if ($c['sens'] === 'recette'): ?>
                  <option value="<?= (int) $c['id'] ?>"><?= e($c['icone'] . ' ' . $c['nom']) ?></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </optgroup>
          </select>
          <span class="champ__aide">
            <?= e(t('bud.categorie_aide')) ?> <a href="<?= url('budget/categories') ?>"><?= e(t('bud.gerer_categories')) ?></a>
          </span>
        </div>

        <div class="champ">
          <label for="moyen"><?= e(t('bud.moyen')) ?></label>
          <select id="moyen" name="moyen">
            <option value=""><?= e(t('bud.non_precise')) ?></option>
            <?php foreach ($moyens as $cle => $m): ?>
              <option value="<?= e($cle) ?>"><?= e($m) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <fieldset style="margin-bottom:1rem">
          <legend><?= e(t('bud.remboursement')) ?></legend>

          <label class="case">
            <input type="checkbox" id="a_rembourser" name="a_rembourser" value="1">
            <?= e(t('bud.a_rembourser_case')) ?>
          </label>

          <div id="bloc-remboursement" style="margin-top:.75rem">
            <?= Vue::rendre('budget/_qui_rembourse', ['personnes' => $personnes]) ?>
            <div class="champ" style="margin:0">
              <label for="part_rembourser"><?= e(t('bud.part_reclamer')) ?></label>
              <input type="text" id="part_rembourser" name="part_rembourser" inputmode="decimal"
                     placeholder="<?= e(t('bud.part_vide')) ?>">
              <span class="champ__aide">
                <?= e(t('bud.part_aide')) ?>
              </span>
            </div>
          </div>
        </fieldset>

        <button class="bouton bouton--bloc" type="submit"><?= e(t('commun.enregistrer')) ?></button>
      </form>
    </div>

    <?php if ($parCategorie !== []): ?>
      <div class="carte">
        <h2><?= e(t('bud.par_categorie')) ?></h2>
        <div class="pile" style="gap:.7rem">
          <?php foreach ($parCategorie as $c): ?>
            <?php
            $total = (float) $c['total'];
            $plafond = $c['plafond_mensuel'] !== null ? (float) $c['plafond_mensuel'] : null;
            $reference = $plafond ?? max($totalDepenses, 0.01);
            $part = $reference > 0 ? min(100, ($total / $reference) * 100) : 0;
            $depassement = $plafond !== null && $total > $plafond;
            ?>
            <div>
              <div style="display:flex;justify-content:space-between;gap:.5rem;font-size:.88rem">
                <span><?= e($c['icone'] . ' ' . $c['nom']) ?></span>
                <span style="font-variant-numeric:tabular-nums;white-space:nowrap">
                  <strong><?= e(montant_lisible($total)) ?></strong>
                  <?php if ($plafond !== null): ?>
                    <span class="discret">/ <?= e(montant_lisible($plafond)) ?></span>
                  <?php endif; ?>
                </span>
              </div>
              <div class="jauge" title="<?= e($plafond !== null
                  ? t('bud.part_plafond', ['n' => round($part)])
                  : t('bud.part_depenses', ['n' => round($part)])) ?>">
                <span style="width:<?= number_format($part, 1, '.', '') ?>%;background:<?=
                    $depassement ? 'var(--erreur)' : e($c['couleur']) ?>"></span>
              </div>
              <?php if ($depassement): ?>
                <span class="discret" style="color:var(--erreur)">
                  <?= e(t('bud.plafond_depasse', ['montant' => montant_lisible($total - $plafond)])) ?>
                </span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="carte">
      <h2><?= e(t('bud.douze_mois')) ?></h2>
      <div class="histogramme" role="img"
           aria-label="<?= e(t('bud.douze_mois_aria')) ?>">
        <?php foreach ($historique as $h): ?>
          <?php
          $hr = $plafondHistorique > 0 ? ($h['recettes'] / $plafondHistorique) * 100 : 0;
          $hd = $plafondHistorique > 0 ? ($h['depenses'] / $plafondHistorique) * 100 : 0;
          ?>
          <a class="histogramme__mois<?= $h['periode'] === $mois->format('Y-m') ? ' est-actif' : '' ?>"
             href="<?= url('budget', ['mois' => $h['periode']]) ?>"
             title="<?= e(t('bud.mois_detail', [
                 'mois' => nom_mois((int) $h['mois']->format('n')) . ' ' . $h['mois']->format('Y'),
                 'recettes' => montant_lisible($h['recettes']),
                 'depenses' => montant_lisible($h['depenses']),
             ])) ?>">
            <span class="histogramme__barres">
              <span class="histogramme__recette" style="height:<?= number_format($hr, 1, '.', '') ?>%"></span>
              <span class="histogramme__depense" style="height:<?= number_format($hd, 1, '.', '') ?>%"></span>
            </span>
            <span class="histogramme__libelle"><?= e(mb_substr(nom_mois((int) $h['mois']->format('n')), 0, 1)) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <p class="discret" style="margin:.6rem 0 0;font-size:.8rem">
        <span style="color:var(--succes)">▮</span> <?= e(t('bud.recettes')) ?>
        <span style="color:var(--erreur);margin-left:.5rem">▮</span> <?= e(t('bud.depenses')) ?>
      </p>
    </div>
  </div>
</div>
