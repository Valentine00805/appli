<?php
/**
 * @var DateTimeImmutable $mois
 * @var string $periode
 * @var ?array $courant, $soldeSaisi, $ancrage
 * @var array $chaine, $recurrences, $aVenir, $categories, $moyens
 */
$csrf = Session::jetonCsrf();
$precedent = $mois->modify('-1 month')->format('Y-m');
$suivant   = $mois->modify('+1 month')->format('Y-m');
$moisCourant = (new DateTimeImmutable('today'))->format('Y-m');
$sansAncrage = $courant === null || $courant['origine'] === 'inconnu';

$signe = static fn (float $v): string => $v >= 0 ? '+ ' : '− ';
$couleurSolde = static fn (?float $v): string
    => $v === null ? 'inherit' : ($v >= 0 ? 'var(--succes)' : 'var(--erreur)');

$fixes = array_values(array_filter($recurrences, static fn (array $r): bool => $r['sens'] === 'depense'));
$reguliers = array_values(array_filter($recurrences, static fn (array $r): bool => $r['sens'] === 'recette'));
$aVenirIds = array_map(static fn (array $r): int => (int) $r['id'], $aVenir);
?>

<?= Vue::rendre('budget/_onglets', ['onglet' => 'previsions']) ?>

<div class="entete-page">
  <div>
    <h1><?= e(t('prev.titre')) ?></h1>
    <p><?= e(t('prev.sous_titre')) ?></p>
  </div>
  <div class="actions">
    <a class="bouton bouton--secondaire bouton--petit"
       href="<?= url('budget/previsions', ['mois' => $precedent]) ?>" aria-label="<?= e(t('bud.mois_precedent')) ?>">←</a>
    <?php if ($periode !== $moisCourant): ?>
      <a class="bouton bouton--secondaire bouton--petit"
         href="<?= url('budget/previsions', ['mois' => $moisCourant]) ?>"><?= e(t('bud.ce_mois')) ?></a>
    <?php endif; ?>
    <a class="bouton bouton--secondaire bouton--petit"
       href="<?= url('budget/previsions', ['mois' => $suivant]) ?>" aria-label="<?= e(t('bud.mois_suivant')) ?>">→</a>
    <h2 class="cal-titre" style="text-transform:capitalize">
      <?= e(nom_mois_en_phrase((int) $mois->format('n')) . ' ' . $mois->format('Y')) ?>
    </h2>
  </div>
</div>

<?php if ($sansAncrage): ?>
  <div class="carte" style="border-color:var(--accent);margin-bottom:1.25rem">
    <h2><?= e(t('prev.commencer')) ?></h2>
    <p class="discret">
      <?= e(t('prev.commencer_aide')) ?>
    </p>
    <form method="post" action="<?= url('budget/previsions/solde') ?>" style="max-width:420px">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="periode" value="<?= e($periode) ?>">
      <div class="champ">
        <label for="montant-depart"><?= t('prev.solde_au_1er', ['mois' => e(nom_mois_en_phrase((int) $mois->format('n')))]) ?></label>
        <input type="text" id="montant-depart" name="montant" required inputmode="decimal" autofocus
               placeholder="1250,40">
      </div>
      <button class="bouton" type="submit"><?= e(t('prev.enregistrer_depart')) ?></button>
    </form>
  </div>
<?php endif; ?>

<div class="grille grille--4" style="margin-bottom:1.5rem">
  <div class="carte stat">
    <div class="stat__valeur" style="color:<?= $couleurSolde($courant['solde_depart'] ?? null) ?>">
      <?= $courant === null || $courant['solde_depart'] === null
          ? '—' : e(montant_lisible($courant['solde_depart'])) ?>
    </div>
    <div class="stat__libelle">
      <?= e(t('prev.solde_depart')) ?>
      <?php if ($courant !== null && $courant['origine'] === 'reporte'): ?>
        <span title="<?= e(t('prev.reporte_titre')) ?>"><?= e(t('prev.reporte')) ?></span>
      <?php elseif ($courant !== null && $courant['origine'] === 'saisi'): ?>
        <span title="<?= e(t('prev.saisi_titre')) ?>"><?= e(t('prev.saisi')) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <div class="carte stat">
    <div class="stat__valeur" style="color:var(--succes)">
      + <?= e(montant_lisible(($courant['reel_recettes'] ?? 0) + ($courant['prevu_recettes'] ?? 0))) ?>
    </div>
    <div class="stat__libelle">
      <?= e(t('prev.recettes')) ?>
      <?php if (($courant['prevu_recettes'] ?? 0) > 0): ?>
        <span class="discret"><?= e(t('prev.dont_a_venir', ['montant' => montant_lisible($courant['prevu_recettes'])])) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <div class="carte stat">
    <div class="stat__valeur" style="color:var(--erreur)">
      − <?= e(montant_lisible(($courant['reel_depenses'] ?? 0) + ($courant['prevu_depenses'] ?? 0))) ?>
    </div>
    <div class="stat__libelle">
      <?= e(t('prev.depenses')) ?>
      <?php if (($courant['prevu_depenses'] ?? 0) > 0): ?>
        <span class="discret"><?= e(t('prev.dont_a_venir', ['montant' => montant_lisible($courant['prevu_depenses'])])) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <div class="carte stat" style="border-color:var(--accent)">
    <div class="stat__valeur" style="color:<?= $couleurSolde($courant['solde_previsionnel'] ?? null) ?>">
      <?= $courant === null || $courant['solde_previsionnel'] === null
          ? '—' : e(montant_lisible($courant['solde_previsionnel'])) ?>
    </div>
    <div class="stat__libelle"><?= t('prev.solde_previsionnel') ?></div>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <section class="carte">
      <div style="display:flex;justify-content:space-between;align-items:baseline;gap:.5rem;flex-wrap:wrap">
        <h2 style="margin:0"><?= e(t('prev.charges_fixes_titre')) ?></h2>
        <?php if ($aVenir !== []): ?>
          <form method="post" action="<?= url('budget/previsions/pointer-tout') ?>" class="en-ligne"
                data-confirmation="<?= e(t('prev.tout_saisir_sur')) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="periode" value="<?= e($periode) ?>">
            <button class="bouton bouton--secondaire bouton--petit" type="submit">
              <?= e(t('prev.tout_saisir', ['n' => count($aVenir)])) ?>
            </button>
          </form>
        <?php endif; ?>
      </div>

      <?php if ($recurrences === []): ?>
        <p class="discret" style="margin:.75rem 0 0">
          <?= e(t('prev.aucune_ligne')) ?>
        </p>
      <?php else: ?>
        <p class="discret" style="margin:.4rem 0 1rem">
          <?= e(t('prev.a_venir_aide')) ?>
        </p>

        <?php foreach ([[t('prev.charges_fixes'), $fixes], [t('prev.revenus_reguliers'), $reguliers]] as [$titre, $liste]): ?>
          <?php if ($liste !== []): ?>
            <h3 style="font-size:.9rem;color:var(--texte-doux);margin:.9rem 0 .4rem"><?= e($titre) ?></h3>
            <div class="pile" style="gap:.5rem">
              <?php foreach ($liste as $r): ?>
                <?php $enAttente = in_array((int) $r['id'], $aVenirIds, true); ?>
                <div class="evt-ligne<?= (int) $r['actif'] === 0 ? ' evt-ligne--termine' : '' ?>">
                  <span class="evt-ligne__barre"
                        style="background:<?= e($r['categorie_couleur'] ?? '#94a3b8') ?>"></span>

                  <span style="min-width:0;flex:1">
                    <span class="evt-ligne__titre"><?= e($r['libelle']) ?></span><br>
                    <span class="evt-ligne__meta">
                      <?= e(t('prev.le_jour_du_mois', ['n' => (int) $r['jour_du_mois']])) ?>
                      <?= $r['categorie_nom'] ? ' · ' . e($r['categorie_nom']) : '' ?>
                      <?php if ((int) $r['actif'] === 0): ?><?= e(t('prev.en_pause')) ?><?php endif; ?>
                    </span>
                  </span>

                  <span class="evt-ligne__droite">
                    <?php if ((int) $r['actif'] === 1): ?>
                      <span class="pastille" style="<?= $enAttente
                          ? 'background:var(--info-doux);color:var(--info)'
                          : 'background:var(--succes-doux);color:var(--succes)' ?>">
                        <?= e(t($enAttente ? 'prev.a_venir' : 'prev.saisie')) ?>
                      </span>
                    <?php endif; ?>

                    <strong style="font-variant-numeric:tabular-nums;white-space:nowrap;color:<?=
                        $r['sens'] === 'recette' ? 'var(--succes)' : 'var(--erreur)' ?>">
                      <?= $r['sens'] === 'recette' ? '+' : '−' ?> <?= e(montant_lisible($r['montant'])) ?>
                    </strong>

                    <?php if ($enAttente && (int) $r['actif'] === 1): ?>
                      <form method="post" action="<?= url('budget/previsions/recurrences/' . $r['id'] . '/pointer') ?>"
                            class="en-ligne">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="periode" value="<?= e($periode) ?>">
                        <button class="bouton bouton--discret bouton--petit" type="submit"
                                title="<?= e(t('prev.pointer_titre')) ?>">✓</button>
                      </form>
                    <?php endif; ?>

                    <button class="bouton bouton--discret bouton--petit" type="button"
                            data-bascule="edition-rec-<?= (int) $r['id'] ?>" title="<?= e(t('evt.modifier')) ?>">✎</button>
                  </span>
                </div>

                <div id="edition-rec-<?= (int) $r['id'] ?>" hidden
                     style="padding:.75rem;border:1px solid var(--bordure);border-radius:var(--rayon-s)">
                  <form method="post" action="<?= url('budget/previsions/recurrences/' . $r['id'] . '/modifier') ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="periode" value="<?= e($periode) ?>">
                    <input type="hidden" name="sens" value="<?= e($r['sens']) ?>">

                    <div class="ligne-champs">
                      <div class="champ">
                        <label for="lib-<?= (int) $r['id'] ?>"><?= e(t('bud.intitule')) ?></label>
                        <input type="text" id="lib-<?= (int) $r['id'] ?>" name="libelle" required maxlength="160"
                               value="<?= e($r['libelle']) ?>">
                      </div>
                      <div class="champ">
                        <label for="mnt-<?= (int) $r['id'] ?>"><?= e(t('bud.montant')) ?></label>
                        <input type="text" id="mnt-<?= (int) $r['id'] ?>" name="montant" required inputmode="decimal"
                               value="<?= e(montant_lisible($r['montant'], false)) ?>">
                      </div>
                      <div class="champ">
                        <label for="jour-<?= (int) $r['id'] ?>"><?= e(t('prev.jour')) ?></label>
                        <input type="number" id="jour-<?= (int) $r['id'] ?>" name="jour_du_mois" min="1" max="31"
                               value="<?= (int) $r['jour_du_mois'] ?>">
                      </div>
                    </div>

                    <div class="champ">
                      <label for="cat-<?= (int) $r['id'] ?>"><?= e(t('bud.categorie')) ?></label>
                      <select id="cat-<?= (int) $r['id'] ?>" name="categorie_id">
                        <option value=""><?= e(t('bud.aucune_categorie')) ?></option>
                        <?php foreach ($categories as $c): ?>
                          <?php if ($c['sens'] === $r['sens']): ?>
                            <option value="<?= (int) $c['id'] ?>"<?= (int) $r['categorie_id'] === (int) $c['id'] ? ' selected' : '' ?>>
                              <?= e($c['icone'] . ' ' . $c['nom']) ?>
                            </option>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <label class="case" style="margin-bottom:.75rem">
                      <input type="checkbox" name="actif" value="1"<?= (int) $r['actif'] === 1 ? ' checked' : '' ?>>
                      <?= e(t('prev.actif_case')) ?>
                    </label>

                    <div class="actions">
                      <button class="bouton bouton--petit" type="submit"><?= e(t('commun.enregistrer')) ?></button>
                    </div>
                  </form>

                  <form method="post" action="<?= url('budget/previsions/recurrences/' . $r['id'] . '/supprimer') ?>"
                        style="margin-top:.6rem"
                        data-confirmation="<?= e(t('prev.supprimer_ligne_sur')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="periode" value="<?= e($periode) ?>">
                    <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('commun.supprimer')) ?></button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <section class="carte">
      <h2><?= e(t('prev.projection')) ?></h2>

      <?php
      // Fenetre affichee : jusqu'a six mois avant le mois courant, et les six suivants.
      $borneBasse = $mois->modify('-6 months')->format('Y-m');
      $pointsGraphique = [];
      foreach ($chaine as $p => $ligne) {
          if ($p < $borneBasse) {
              continue;
          }
          $pointsGraphique[] = [
              'periode' => $p,
              'mois'    => $ligne['mois'],
              'solde'   => $ligne['solde_previsionnel'],
              'origine' => $ligne['origine'],
          ];
      }
      ?>
      <?= Vue::rendre('budget/_graphique', ['points' => $pointsGraphique, 'periode' => $periode]) ?>

      <p class="discret" style="margin:.2rem 0 .8rem">
        <?= e(t('prev.projection_aide')) ?>
      </p>
      <div style="overflow-x:auto">
        <table class="tableau">
          <thead>
            <tr>
              <th scope="col"><?= e(t('prev.col_mois')) ?></th>
              <th scope="col" class="nombre"><?= e(t('prev.col_depart')) ?></th>
              <th scope="col" class="nombre"><?= e(t('bud.recettes')) ?></th>
              <th scope="col" class="nombre"><?= e(t('bud.depenses')) ?></th>
              <th scope="col" class="nombre"><?= e(t('prev.col_previsionnel')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($chaine as $p => $ligne): ?>
              <?php if ($p < $periode) { continue; } ?>
              <tr<?= $p === $periode ? ' class="est-actif"' : '' ?>>
                <th scope="row" style="font-weight:600;text-transform:capitalize">
                  <a href="<?= url('budget/previsions', ['mois' => $p]) ?>" style="text-decoration:none;color:inherit">
                    <?= e(nom_mois_en_phrase((int) $ligne['mois']->format('n')) . ' ' . $ligne['mois']->format('Y')) ?>
                  </a>
                  <?php if ($ligne['origine'] === 'saisi'): ?>
                    <span class="discret" title="<?= e(t('prev.solde_saisi_titre')) ?>">✎</span>
                  <?php endif; ?>
                </th>
                <td class="nombre"><?= $ligne['solde_depart'] === null ? '—' : e(montant_lisible($ligne['solde_depart'])) ?></td>
                <td class="nombre" style="color:var(--succes)">
                  <?= e(montant_lisible($ligne['reel_recettes'] + $ligne['prevu_recettes'])) ?>
                </td>
                <td class="nombre" style="color:var(--erreur)">
                  <?= e(montant_lisible($ligne['reel_depenses'] + $ligne['prevu_depenses'])) ?>
                </td>
                <td class="nombre">
                  <strong style="color:<?= $couleurSolde($ligne['solde_previsionnel']) ?>">
                    <?= $ligne['solde_previsionnel'] === null ? '—' : e(montant_lisible($ligne['solde_previsionnel'])) ?>
                  </strong>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <div class="pile">
    <div class="carte">
      <h2><?= e(t('prev.ajouter_ligne')) ?></h2>
      <form method="post" action="<?= url('budget/previsions/recurrences') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="periode" value="<?= e($periode) ?>">

        <fieldset style="margin-bottom:1rem">
          <legend><?= e(t('prev.nature')) ?></legend>
          <div style="display:flex;gap:1rem">
            <label class="case"><input type="radio" name="sens" value="depense" checked> <?= e(t('prev.charge')) ?></label>
            <label class="case"><input type="radio" name="sens" value="recette"> <?= e(t('prev.revenu')) ?></label>
          </div>
        </fieldset>

        <div class="champ">
          <label for="libelle"><?= e(t('bud.intitule')) ?></label>
          <input type="text" id="libelle" name="libelle" required maxlength="160" placeholder="<?= e(t('prev.ligne_exemple')) ?>">
        </div>

        <div class="ligne-champs">
          <div class="champ">
            <label for="montant"><?= e(t('bud.montant')) ?></label>
            <input type="text" id="montant" name="montant" required inputmode="decimal" placeholder="420,00">
          </div>
          <div class="champ">
            <label for="jour_du_mois"><?= e(t('prev.jour_du_mois')) ?></label>
            <input type="number" id="jour_du_mois" name="jour_du_mois" min="1" max="31" value="1">
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
        </div>

        <div class="champ">
          <label for="moyen"><?= e(t('bud.moyen')) ?></label>
          <select id="moyen" name="moyen">
            <option value=""><?= e(t('bud.non_precise')) ?></option>
            <?php foreach ($moyens as $cle => $m): ?>
              <option value="<?= e($cle) ?>"<?= $cle === 'prelevement' ? ' selected' : '' ?>><?= e($m) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button class="bouton bouton--bloc" type="submit"><?= e(t('prev.ajouter')) ?></button>
      </form>
    </div>

    <div class="carte">
      <h2><?= e(t('prev.solde_depart_titre')) ?></h2>
      <?php if ($ancrage !== null): ?>
        <p class="discret" style="margin:0 0 .75rem">
          <?= t('prev.dernier_saisi', [
              'montant' => e(montant_lisible($ancrage['montant'])),
              'mois' => e(nom_mois_en_phrase((int) substr((string) $ancrage['periode'], 5, 2))),
              'annee' => e(substr((string) $ancrage['periode'], 0, 4)),
          ]) ?>
          <?php if ($ancrage['periode'] !== $periode): ?>
            <?= e(t('prev.enchainent')) ?>
          <?php endif; ?>
        </p>
      <?php endif; ?>

      <form method="post" action="<?= url('budget/previsions/solde') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="periode" value="<?= e($periode) ?>">
        <div class="champ">
          <label for="montant-solde">
            <?= e(t('prev.forcer_solde', ['mois' => nom_mois_en_phrase((int) $mois->format('n'))])) ?>
          </label>
          <input type="text" id="montant-solde" name="montant" required inputmode="decimal"
                 value="<?= $soldeSaisi !== null ? e(montant_lisible($soldeSaisi['montant'], false)) : '' ?>"
                 placeholder="<?= $courant !== null && $courant['solde_depart'] !== null
                     ? e(montant_lisible($courant['solde_depart'], false)) : '0,00' ?>">
          <span class="champ__aide">
            <?= e(t('prev.forcer_aide')) ?>
          </span>
        </div>
        <button class="bouton bouton--bloc" type="submit">
          <?= e(t($soldeSaisi !== null ? 'prev.mettre_a_jour' : 'prev.fixer_solde')) ?>
        </button>
      </form>

      <?php if ($soldeSaisi !== null): ?>
        <form method="post" action="<?= url('budget/previsions/solde/supprimer') ?>" style="margin-top:.6rem"
              data-confirmation="<?= e(t('prev.supprimer_solde_sur')) ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="periode" value="<?= e($periode) ?>">
          <button class="bouton bouton--discret bouton--bloc" type="submit">
            <?= e(t('prev.revenir_reporte')) ?>
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
