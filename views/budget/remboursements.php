<?php
/**
 * Chaque mois se lit seul : aucun cumul d'un mois sur l'autre.
 *
 * @var DateTimeImmutable $mois
 * @var array $lignes, $rubriques, $totaux, $moisRenseignes, $personnes, $statuts, $recettes
 * @var ?array $reglement
 * @var string $personne
 * @var ?array $groupe   le groupe regardé, s'il y en a un
 * @var array $groupes   tous les groupes du compte, avec leurs membres
 * @var ?string $statut
 * @var float $aReclamerGlobal
 */
$csrf = Session::jetonCsrf();
$periode = $mois->format('Y-m');
$moisCourant = (new DateTimeImmutable('today'))->format('Y-m');

// Sur qui l'on regarde, dit d'un seul paramètre : « p:Nom » ou « g:12 ».
$qui = $groupe !== null ? 'g:' . $groupe['id'] : ($personne !== '' ? 'p:' . $personne : '');

$filtres = array_filter([
    'mois'   => $periode,
    'qui'    => $qui !== '' ? $qui : null,
    'statut' => $statut,
], static fn ($v): bool => $v !== null && $v !== '');

$titrePeriode = nom_mois_en_phrase((int) $mois->format('n')) . ' ' . $mois->format('Y');
$lien = static fn (string $m): string => url('budget/remboursements',
    array_filter(['mois' => $m, 'qui' => $qui ?: null, 'statut' => $statut]));
?>

<?= Vue::rendre('budget/_onglets', ['onglet' => 'remboursements']) ?>

<div class="entete-page">
  <div>
    <h1><?= e(t('remb.titre')) ?></h1>
    <p><?= e(t('remb.sous_titre', ['mois' => $titrePeriode])) ?></p>
  </div>
  <div class="actions sans-impression">
    <a class="bouton bouton--secondaire bouton--petit"
       href="<?= $lien($mois->modify('-1 month')->format('Y-m')) ?>" aria-label="<?= e(t('bud.mois_precedent')) ?>">←</a>
    <?php if ($periode !== $moisCourant): ?>
      <a class="bouton bouton--secondaire bouton--petit" href="<?= $lien($moisCourant) ?>"><?= e(t('bud.ce_mois')) ?></a>
    <?php endif; ?>
    <a class="bouton bouton--secondaire bouton--petit"
       href="<?= $lien($mois->modify('+1 month')->format('Y-m')) ?>" aria-label="<?= e(t('bud.mois_suivant')) ?>">→</a>
    <a class="bouton" href="<?= url('budget/remboursements/export', $filtres) ?>"><?= e(t('remb.exporter')) ?></a>
    <button class="bouton bouton--secondaire" type="button" onclick="window.print()"><?= e(t('remb.imprimer')) ?></button>
    <a class="bouton bouton--secondaire" href="<?= url('budget') ?>"><?= e(t('remb.voir_operations')) ?></a>
  </div>
</div>

<div class="grille grille--4" style="margin-bottom:1.5rem">
  <div class="carte stat" style="border-color:var(--accent)">
    <div class="stat__valeur" style="color:var(--accent-fonce)"><?= e(montant_lisible($totaux['attente'])) ?></div>
    <div class="stat__libelle"><?= t('remb.reste_a_reclamer') ?></div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur" style="color:var(--succes)"><?= e(montant_lisible($totaux['regle'])) ?></div>
    <div class="stat__libelle"><?= e(t('remb.deja_rembourse')) ?></div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur"><?= e(montant_lisible($totaux['paye'])) ?></div>
    <div class="stat__libelle"><?= e(t('remb.avance_total')) ?></div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur"><?= (int) $totaux['nb'] ?></div>
    <div class="stat__libelle"><?= e(tn('remb.lignes_cochees', (int) $totaux['nb'])) ?></div>
  </div>
</div>

<form class="filtres sans-impression" method="get" action="<?= url('budget/remboursements') ?>" data-auto-envoi>
  <div class="champ">
    <label for="f-mois"><?= e(t('remb.mois')) ?></label>
    <input type="month" id="f-mois" name="mois" value="<?= e($periode) ?>">
  </div>
  <?php if ($personnes !== [] || $groupes !== []): ?>
    <div class="champ">
      <label for="f-qui"><?= e(t('remb.qui_rembourse')) ?></label>
      <select id="f-qui" name="qui">
        <option value=""><?= e(t('remb.tout_le_monde')) ?></option>
        <?php if ($groupes !== []): ?>
          <optgroup label="<?= e(t('remb.groupes')) ?>">
            <?php foreach ($groupes as $g): ?>
              <?php if ($g['membres'] === []) { continue; } ?>
              <option value="g:<?= (int) $g['id'] ?>"<?= $groupe !== null && $groupe['id'] === $g['id'] ? ' selected' : '' ?>>
                <?= e($g['nom']) ?> (<?= count($g['membres']) ?>)
              </option>
            <?php endforeach; ?>
          </optgroup>
        <?php endif; ?>
        <?php if ($personnes !== []): ?>
          <optgroup label="<?= e(t('remb.personnes')) ?>">
            <?php foreach ($personnes as $p): ?>
              <option value="p:<?= e($p) ?>"<?= $personne === $p ? ' selected' : '' ?>><?= e($p) ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endif; ?>
      </select>
    </div>
  <?php endif; ?>

  <?php if ($groupe !== null): ?>
    <?php
    /*
     * Un groupe ne se règle pas d'un bloc : un règlement se rattache à une
     * personne et crée sa recette en retour. Le dire ici évite de chercher le
     * bouton qui, plus bas, ne s'affichera pas.
     */
    ?>
    <p class="champ__aide" style="flex-basis:100%;margin:0">
      <?= e(t('remb.groupe_aide', [
          'nom' => $groupe['nom'], 'membres' => implode(', ', $groupe['membres']),
      ])) ?>
    </p>
  <?php endif; ?>
  <div class="champ">
    <label for="f-statut"><?= e(t('remb.statut')) ?></label>
    <select id="f-statut" name="statut">
      <option value=""><?= e(t('remb.tous')) ?></option>
      <?php foreach ($statuts as $cle => $libelle): ?>
        <option value="<?= e($cle) ?>"<?= $statut === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="bouton bouton--secondaire" type="submit"><?= e(t('bud.filtrer')) ?></button>
</form>

<?php if ($reglement !== null): ?>
  <section class="carte" style="margin-bottom:1.25rem;border-color:var(--succes)">
    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:1rem;flex-wrap:wrap">
      <div>
        <h2 style="margin:0;color:var(--succes)"><?= e(t('remb.mois_rembourse')) ?></h2>
        <p class="discret" style="margin:.35rem 0 0">
          <?= e(t('remb.regle_le', [
              'montant' => montant_lisible($reglement['montant']),
              'date' => date_fr((string) $reglement['date_reglement'] . ' 00:00:00', false),
          ])) ?>
          <?php if ($reglement['operation_id'] !== null): ?>
            <?= e(t('remb.recette_ajoutee')) ?>
            <a href="<?= url('budget', ['mois' => substr((string) $reglement['date_recette'], 0, 7)]) ?>">
              <?= e(nom_mois_en_phrase((int) substr((string) $reglement['date_recette'], 5, 2))
                  . ' ' . substr((string) $reglement['date_recette'], 0, 4)) ?></a>.
          <?php else: ?>
            <span style="color:var(--erreur)"><?= e(t('remb.recette_supprimee')) ?></span>
          <?php endif; ?>
        </p>
      </div>
      <form method="post" action="<?= url('budget/remboursements/reglements/' . $reglement['id'] . '/annuler') ?>"
            class="sans-impression"
            data-confirmation="<?= e(t('remb.annuler_sur')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <button class="bouton bouton--secondaire bouton--petit" type="submit"><?= e(t('remb.annuler_reglement')) ?></button>
      </form>
    </div>
  </section>

<?php elseif ($totaux['attente'] > 0 && $groupe === null): ?>
  <section class="carte sans-impression" style="margin-bottom:1.25rem;border-color:var(--accent)">
    <h2 style="margin:0"><?= e(t('remb.vous_a_t_il')) ?></h2>
    <p class="discret" style="margin:.35rem 0 .9rem">
      <?= t('remb.confirmer_aide', ['montant' => e(montant_lisible($totaux['attente']))]) ?>
    </p>

    <form method="post" action="<?= url('budget/remboursements/regler-mois') ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="periode" value="<?= e($periode) ?>">
      <input type="hidden" name="personne" value="<?= e($personne) ?>">

      <div class="ligne-champs" style="align-items:end">
        <div class="champ">
          <label for="date_recette"><?= e(t('remb.argent_recu_le')) ?></label>
          <input type="date" id="date_recette" name="date_recette"
                 value="<?= e($mois->modify('+1 month')->format('Y-m-01')) ?>">
          <span class="champ__aide"><?= t('remb.premier_du_mois') ?></span>
        </div>
        <div class="champ">
          <label for="categorie_recette"><?= e(t('remb.categorie_recette')) ?></label>
          <select id="categorie_recette" name="categorie_id">
            <option value=""><?= e(t('bud.aucune_categorie')) ?></option>
            <?php foreach ($recettes as $c): ?>
              <option value="<?= (int) $c['id'] ?>"><?= e($c['icone'] . ' ' . $c['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="champ">
          <button class="bouton" type="submit">
            <?= e(t('remb.oui_rembourses', ['montant' => montant_lisible($totaux['attente'])])) ?>
          </button>
        </div>
      </div>
    </form>
  </section>
<?php endif; ?>

<?php if ($lignes === []): ?>
  <div class="vide">
    <span class="vide__icone">🧾</span>
    <p><?= e(t('remb.aucune_depense', ['mois' => $titrePeriode])) ?></p>
    <p class="discret">
      <?= e(t('remb.aucune_aide_1')) ?> <a href="<?= url('budget') ?>"><?= e(t('bud.onglet.operations')) ?></a>
      <?= e(t('remb.aucune_aide_2')) ?>
    </p>
  </div>
<?php else: ?>

  <form method="post" action="<?= url('budget/remboursements/regler') ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <?php foreach ($filtres as $cle => $valeur): ?>
      <input type="hidden" name="<?= e($cle) ?>" value="<?= e((string) $valeur) ?>">
    <?php endforeach; ?>

    <?php foreach ($rubriques as $rubrique): ?>
      <section class="carte" style="margin-bottom:1rem">
        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:.6rem;flex-wrap:wrap">
          <h2 style="margin:0">
            <span aria-hidden="true"><?= e($rubrique['icone']) ?></span> <?= e($rubrique['nom']) ?>
          </h2>
          <span style="font-variant-numeric:tabular-nums">
            <strong style="font-size:1.05rem"><?= e(montant_lisible($rubrique['total'])) ?></strong>
            <?php if (abs($rubrique['total'] - $rubrique['paye']) > 0.005): ?>
              <span class="discret"><?= e(t('remb.sur_avances', ['montant' => montant_lisible($rubrique['paye'])])) ?></span>
            <?php endif; ?>
          </span>
        </div>

        <div style="overflow-x:auto;margin-top:.7rem">
          <table class="tableau tableau--remb">
            <thead>
              <tr>
                <th scope="col" class="sans-impression"><span class="sr-only"><?= e(t('remb.selectionner')) ?></span>✓</th>
                <th scope="col"><?= e(t('remb.col_date')) ?></th>
                <th scope="col"><?= e(t('remb.col_libelle')) ?></th>
                <th scope="col" class="nombre"><?= e(t('remb.col_paye')) ?></th>
                <th scope="col" class="nombre"><?= e(t('remb.col_reclame')) ?></th>
                <th scope="col"><?= e(t('remb.statut')) ?></th>
                <th scope="col" class="sans-impression"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rubrique['lignes'] as $l): ?>
                <?php $partiel = $l['part_rembourser'] !== null; ?>
                <tr<?= $l['statut_remb'] === 'hors_total' ? ' class="est-hors-total"' : '' ?><?= $l['statut_remb'] === 'rembourse' ? ' class="est-reglee"' : '' ?>>
                  <td class="sans-impression">
                    <?php if ($l['statut_remb'] === 'a_reclamer'): ?>
                      <input type="checkbox" name="ligne[]" value="<?= (int) $l['id'] ?>"
                             aria-label="<?= e(t('remb.selectionner_ligne', ['nom' => $l['libelle']])) ?>">
                    <?php endif; ?>
                  </td>
                  <td style="white-space:nowrap"><?= e(date_numerique((string) $l['date_operation'])) ?></td>
                  <td>
                    <?= e($l['libelle']) ?>
                    <?php if ($l['rembourse_par']): ?>
                      <span class="discret">· <?= e($l['rembourse_par']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="nombre"><?= e(montant_lisible($l['montant'])) ?></td>
                  <td class="nombre">
                    <strong><?= e(montant_lisible($l['montant_reclame'])) ?></strong>
                    <?php if ($partiel): ?>
                      <span class="discret" title="<?= e(t('remb.partiel_titre')) ?>">◐</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="pastille" style="<?= match ($l['statut_remb']) {
                        'rembourse'  => 'background:var(--succes-doux);color:var(--succes)',
                        'hors_total' => 'background:var(--fond-doux);color:var(--texte-doux)',
                        default      => 'background:var(--accent-doux);color:var(--accent-fonce)',
                    } ?>"><?= e($statuts[$l['statut_remb']]) ?></span>
                    <?php if ($l['date_remboursement']): ?>
                      <span class="discret"><?= e(t('remb.le_date', ['date' => date_numerique((string) $l['date_remboursement'])])) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="sans-impression">
                    <button class="bouton bouton--discret bouton--petit" type="button"
                            data-bascule="remb-<?= (int) $l['id'] ?>" title="<?= e(t('remb.modifier_ligne')) ?>">✎</button>
                  </td>
                </tr>

                <tr id="remb-<?= (int) $l['id'] ?>" hidden class="sans-impression">
                  <td colspan="7" style="background:var(--fond-doux)">
                    <div class="ligne-champs" style="align-items:end">
                      <div class="champ">
                        <label for="part-<?= (int) $l['id'] ?>"><?= e(t('remb.part_reclamee')) ?></label>
                        <input type="text" id="part-<?= (int) $l['id'] ?>" form="f-<?= (int) $l['id'] ?>"
                               name="part_rembourser" inputmode="decimal"
                               placeholder="<?= e(t('remb.tout_suffixe', ['montant' => montant_lisible($l['montant'], false)])) ?>"
                               value="<?= $partiel ? e(montant_lisible($l['part_rembourser'], false)) : '' ?>">
                        <span class="champ__aide">
                          <?= e(t('remb.moitie', ['montant' => montant_lisible(round((float) $l['montant'] / 2, 2), false)])) ?>
                        </span>
                      </div>
                      <?= Vue::rendre('budget/_qui_rembourse', [
                          'personnes' => $personnes,
                          'valeur'    => $l['rembourse_par'],
                          'libelle'   => t('remb.qui_rembourse'),
                          'cle'       => '-' . (int) $l['id'],
                          'form'      => 'f-' . (int) $l['id'],
                      ]) ?>
                      <div class="champ">
                        <label for="st-<?= (int) $l['id'] ?>"><?= e(t('remb.statut')) ?></label>
                        <select id="st-<?= (int) $l['id'] ?>" form="f-<?= (int) $l['id'] ?>" name="statut_remb">
                          <?php foreach ($statuts as $cle => $libelle): ?>
                            <option value="<?= e($cle) ?>"<?= $l['statut_remb'] === $cle ? ' selected' : '' ?>>
                              <?= e($libelle) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="champ">
                        <label for="dt-<?= (int) $l['id'] ?>"><?= e(t('remb.rembourse_le')) ?></label>
                        <input type="date" id="dt-<?= (int) $l['id'] ?>" form="f-<?= (int) $l['id'] ?>"
                               name="date_remboursement" value="<?= e((string) $l['date_remboursement']) ?>">
                      </div>
                      <div class="champ">
                        <button class="bouton" type="submit" form="f-<?= (int) $l['id'] ?>"><?= e(t('commun.enregistrer')) ?></button>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endforeach; ?>

    <div class="carte" style="margin-bottom:1rem">
      <div style="display:flex;justify-content:space-between;align-items:baseline;gap:1rem;flex-wrap:wrap">
        <h2 style="margin:0"><?= e(t('remb.total_de', ['mois' => $titrePeriode])) ?></h2>
        <strong style="font-size:1.5rem;color:var(--accent-fonce);font-variant-numeric:tabular-nums">
          <?= e(montant_lisible($totaux['reclame'])) ?>
        </strong>
      </div>

      <?php if ($totaux['hors_total'] > 0): ?>
        <p class="discret" style="margin:.8rem 0 0">
          <?= e(t('remb.hors_total_note', ['montant' => montant_lisible($totaux['hors_total'])])) ?>
        </p>
      <?php endif; ?>
    </div>

    <div class="actions sans-impression">
      <div class="champ" style="margin:0">
        <label for="date-reglement"><?= e(t('remb.rembourse_le')) ?></label>
        <input type="date" id="date-reglement" name="date_remboursement" value="<?= e(date('Y-m-d')) ?>">
      </div>
      <button class="bouton" type="submit"><?= e(t('remb.marquer_cochees')) ?></button>
    </div>
  </form>

  <?php /* Les formulaires de modification vivent hors du tableau : on ne peut pas
           imbriquer un formulaire dans un autre, l'attribut form= les relie. */ ?>
  <?php foreach ($lignes as $l): ?>
    <form id="f-<?= (int) $l['id'] ?>" method="post"
          action="<?= url('budget/remboursements/' . $l['id'] . '/modifier') ?>" hidden>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <?php foreach ($filtres as $cle => $valeur): ?>
        <input type="hidden" name="<?= e($cle) ?>" value="<?= e((string) $valeur) ?>">
      <?php endforeach; ?>
    </form>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($moisRenseignes !== []): ?>
  <section class="carte sans-impression" style="margin-top:1.5rem">
    <h2><?= e(t('remb.autres_mois')) ?></h2>
    <p class="discret" style="margin:.2rem 0 .8rem">
      <?= e(t('remb.autres_mois_aide')) ?>
    </p>
    <div style="display:flex;gap:.4rem;flex-wrap:wrap">
      <?php foreach ($moisRenseignes as $cle => $infos): ?>
        <a class="pastille" href="<?= $lien($cle) ?>"
           <?= $cle === $periode ? 'style="background:var(--accent);color:#fff"' : '' ?>>
          <?= e(nom_mois_en_phrase((int) substr($cle, 5, 2)) . ' ' . substr($cle, 0, 4)) ?>
          · <?= e(montant_lisible($infos['total'])) ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if ($aReclamerGlobal > $totaux['attente'] + 0.005): ?>
      <p class="discret" style="margin:.8rem 0 0">
        <?= e(t('remb.tous_mois', ['montant' => montant_lisible($aReclamerGlobal)])) ?>
      </p>
    <?php endif; ?>
  </section>
<?php endif; ?>
