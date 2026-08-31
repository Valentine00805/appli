<?php
/**
 * @var array $lignes, $rubriques, $parMois, $totaux, $periode, $personnes, $statuts
 * @var string $debut, $fin, $personne
 * @var ?string $statut
 * @var float $aReclamerGlobal
 */
$csrf = Session::jetonCsrf();
$filtres = array_filter([
    'depuis'   => $periode['depuis'],
    'jusqu'    => $periode['jusqu'],
    'personne' => $personne !== '' ? $personne : null,
    'statut'   => $statut,
], static fn ($v): bool => $v !== null && $v !== '');

$titrePeriode = $periode['depuis'] === $periode['jusqu']
    ? strtolower(nom_mois((int) substr($periode['depuis'], 5, 2))) . ' ' . substr($periode['depuis'], 0, 4)
    : 'de ' . strtolower(nom_mois((int) substr($periode['depuis'], 5, 2))) . ' ' . substr($periode['depuis'], 0, 4)
      . ' à ' . strtolower(nom_mois((int) substr($periode['jusqu'], 5, 2))) . ' ' . substr($periode['jusqu'], 0, 4);
?>

<?= Vue::rendre('budget/_onglets', ['onglet' => 'remboursements']) ?>

<div class="entete-page">
  <div>
    <h1>Remboursements</h1>
    <p>Ce que vous avez avancé et qui doit vous être rendu, <?= e($titrePeriode) ?>.</p>
  </div>
  <div class="actions sans-impression">
    <button class="bouton bouton--secondaire" type="button" onclick="window.print()">🖨 Imprimer</button>
    <a class="bouton bouton--secondaire" href="<?= url('budget') ?>">Voir les opérations</a>
  </div>
</div>

<div class="grille grille--4" style="margin-bottom:1.5rem">
  <div class="carte stat" style="border-color:var(--accent)">
    <div class="stat__valeur" style="color:var(--accent-fonce)"><?= e(montant_fr($totaux['attente'])) ?></div>
    <div class="stat__libelle"><strong>reste à réclamer</strong> sur la période</div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur" style="color:var(--succes)"><?= e(montant_fr($totaux['regle'])) ?></div>
    <div class="stat__libelle">déjà remboursé</div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur"><?= e(montant_fr($totaux['paye'])) ?></div>
    <div class="stat__libelle">avancé au total</div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur"><?= (int) $totaux['nb'] ?></div>
    <div class="stat__libelle">ligne<?= (int) $totaux['nb'] > 1 ? 's' : '' ?> cochée<?= (int) $totaux['nb'] > 1 ? 's' : '' ?></div>
  </div>
</div>

<form class="filtres sans-impression" method="get" action="<?= url('budget/remboursements') ?>" data-auto-envoi>
  <div class="champ">
    <label for="f-depuis">De</label>
    <input type="month" id="f-depuis" name="depuis" value="<?= e($periode['depuis']) ?>">
  </div>
  <div class="champ">
    <label for="f-jusqu">À</label>
    <input type="month" id="f-jusqu" name="jusqu" value="<?= e($periode['jusqu']) ?>">
  </div>
  <?php if ($personnes !== []): ?>
    <div class="champ">
      <label for="f-personne">Qui rembourse</label>
      <select id="f-personne" name="personne">
        <option value="">Tout le monde</option>
        <?php foreach ($personnes as $p): ?>
          <option value="<?= e($p) ?>"<?= $personne === $p ? ' selected' : '' ?>><?= e($p) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  <div class="champ">
    <label for="f-statut">Statut</label>
    <select id="f-statut" name="statut">
      <option value="">Tous</option>
      <?php foreach ($statuts as $cle => $libelle): ?>
        <option value="<?= e($cle) ?>"<?= $statut === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="bouton bouton--secondaire" type="submit">Filtrer</button>
</form>

<?php if ($lignes === []): ?>
  <div class="vide">
    <span class="vide__icone">🧾</span>
    <p>Aucune dépense cochée sur cette période.</p>
    <p class="discret">
      Dans l'onglet <a href="<?= url('budget') ?>">Opérations</a>, cochez 🧾 sur une dépense
      pour la faire apparaître ici.
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
            <strong style="font-size:1.05rem"><?= e(montant_fr($rubrique['total'])) ?></strong>
            <?php if (abs($rubrique['total'] - $rubrique['paye']) > 0.005): ?>
              <span class="discret">sur <?= e(montant_fr($rubrique['paye'])) ?> avancés</span>
            <?php endif; ?>
          </span>
        </div>

        <div style="overflow-x:auto;margin-top:.7rem">
          <table class="tableau tableau--remb">
            <thead>
              <tr>
                <th scope="col" class="sans-impression"><span class="sr-only">Sélectionner</span>✓</th>
                <th scope="col">Date</th>
                <th scope="col">Libellé</th>
                <th scope="col" class="nombre">Payé</th>
                <th scope="col" class="nombre">Réclamé</th>
                <th scope="col">Statut</th>
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
                             aria-label="Sélectionner <?= e($l['libelle']) ?>">
                    <?php endif; ?>
                  </td>
                  <td style="white-space:nowrap"><?= e(date('d/m/Y', strtotime((string) $l['date_operation']))) ?></td>
                  <td>
                    <?= e($l['libelle']) ?>
                    <?php if ($l['rembourse_par']): ?>
                      <span class="discret">· <?= e($l['rembourse_par']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="nombre"><?= e(montant_fr($l['montant'])) ?></td>
                  <td class="nombre">
                    <strong><?= e(montant_fr($l['montant_reclame'])) ?></strong>
                    <?php if ($partiel): ?>
                      <span class="discret" title="Part réclamée différente du montant payé">◐</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="pastille" style="<?= match ($l['statut_remb']) {
                        'rembourse'  => 'background:var(--succes-doux);color:var(--succes)',
                        'hors_total' => 'background:var(--fond-doux);color:var(--texte-doux)',
                        default      => 'background:var(--accent-doux);color:var(--accent-fonce)',
                    } ?>"><?= e($statuts[$l['statut_remb']]) ?></span>
                    <?php if ($l['date_remboursement']): ?>
                      <span class="discret">le <?= e(date('d/m/Y', strtotime((string) $l['date_remboursement']))) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="sans-impression">
                    <button class="bouton bouton--discret bouton--petit" type="button"
                            data-bascule="remb-<?= (int) $l['id'] ?>" title="Modifier cette ligne">✎</button>
                  </td>
                </tr>

                <tr id="remb-<?= (int) $l['id'] ?>" hidden class="sans-impression">
                  <td colspan="7" style="background:var(--fond-doux)">
                    <div class="ligne-champs" style="align-items:end">
                      <div class="champ">
                        <label for="part-<?= (int) $l['id'] ?>">Part réclamée</label>
                        <input type="text" id="part-<?= (int) $l['id'] ?>" form="f-<?= (int) $l['id'] ?>"
                               name="part_rembourser" inputmode="decimal"
                               placeholder="<?= e(montant_fr($l['montant'], false)) ?> (tout)"
                               value="<?= $partiel ? e(montant_fr($l['part_rembourser'], false)) : '' ?>">
                        <span class="champ__aide">
                          Vide = tout. Moitié : <?= e(montant_fr(round((float) $l['montant'] / 2, 2), false)) ?>
                        </span>
                      </div>
                      <div class="champ">
                        <label for="qui-<?= (int) $l['id'] ?>">Qui rembourse</label>
                        <input type="text" id="qui-<?= (int) $l['id'] ?>" form="f-<?= (int) $l['id'] ?>"
                               name="rembourse_par" maxlength="80" list="liste-personnes"
                               value="<?= e((string) $l['rembourse_par']) ?>">
                      </div>
                      <div class="champ">
                        <label for="st-<?= (int) $l['id'] ?>">Statut</label>
                        <select id="st-<?= (int) $l['id'] ?>" form="f-<?= (int) $l['id'] ?>" name="statut_remb">
                          <?php foreach ($statuts as $cle => $libelle): ?>
                            <option value="<?= e($cle) ?>"<?= $l['statut_remb'] === $cle ? ' selected' : '' ?>>
                              <?= e($libelle) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="champ">
                        <label for="dt-<?= (int) $l['id'] ?>">Remboursé le</label>
                        <input type="date" id="dt-<?= (int) $l['id'] ?>" form="f-<?= (int) $l['id'] ?>"
                               name="date_remboursement" value="<?= e((string) $l['date_remboursement']) ?>">
                      </div>
                      <div class="champ">
                        <button class="bouton" type="submit" form="f-<?= (int) $l['id'] ?>">Enregistrer</button>
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
        <h2 style="margin:0">Total <?= e($titrePeriode) ?></h2>
        <strong style="font-size:1.5rem;color:var(--accent-fonce);font-variant-numeric:tabular-nums">
          <?= e(montant_fr($totaux['reclame'])) ?>
        </strong>
      </div>

      <?php if (count($parMois) > 1): ?>
        <hr class="separateur">
        <table class="tableau" style="max-width:420px">
          <tbody>
            <?php foreach ($parMois as $cle => $montant): ?>
              <tr>
                <th scope="row" style="font-weight:500;text-transform:capitalize">
                  <?= e(strtolower(nom_mois((int) substr($cle, 5, 2))) . ' ' . substr($cle, 0, 4)) ?>
                </th>
                <td class="nombre"><?= e(montant_fr($montant)) ?></td>
              </tr>
            <?php endforeach; ?>
            <tr style="border-top:2px solid var(--bordure-forte)">
              <th scope="row">Total des <?= count($parMois) ?> mois</th>
              <td class="nombre"><strong><?= e(montant_fr($totaux['reclame'])) ?></strong></td>
            </tr>
          </tbody>
        </table>
      <?php endif; ?>

      <?php if ($totaux['hors_total'] > 0): ?>
        <p class="discret" style="margin:.8rem 0 0">
          <?= e(montant_fr($totaux['hors_total'])) ?> mis de côté en « hors total », non compté ci-dessus.
        </p>
      <?php endif; ?>
    </div>

    <div class="actions sans-impression">
      <div class="champ" style="margin:0">
        <label for="date-reglement">Remboursé le</label>
        <input type="date" id="date-reglement" name="date_remboursement" value="<?= e(date('Y-m-d')) ?>">
      </div>
      <button class="bouton" type="submit">Marquer les lignes cochées comme remboursées</button>
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

  <datalist id="liste-personnes">
    <?php foreach ($personnes as $p): ?>
      <option value="<?= e($p) ?>"></option>
    <?php endforeach; ?>
  </datalist>
<?php endif; ?>

<?php if ($aReclamerGlobal > $totaux['attente'] + 0.005): ?>
  <p class="discret sans-impression" style="margin-top:1rem">
    Hors de cette période, il reste <?= e(montant_fr($aReclamerGlobal - $totaux['attente'])) ?>
    à réclamer. Élargissez les dates pour les voir.
  </p>
<?php endif; ?>
