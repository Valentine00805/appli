<?php
/**
 * @var DateTimeImmutable $mois
 * @var array $operations, $totaux, $parCategorie, $categories, $moyens, $historique
 * @var ?int $categorieId
 * @var ?string $sens
 */
$csrf = Session::jetonCsrf();
$precedent = $mois->modify('-1 month');
$suivant   = $mois->modify('+1 month');
$moisCourant = (new DateTimeImmutable('today'))->format('Y-m');

$filtres = array_filter([
    'categorie' => $categorieId,
    'sens'      => $sens,
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
    <h1>Budget</h1>
    <p>Vos recettes et vos dépenses, mois par mois.</p>
  </div>
  <div class="actions">
    <a class="bouton bouton--secondaire bouton--petit" href="<?= $lienMois($precedent) ?>"
       aria-label="Mois précédent">←</a>
    <?php if ($mois->format('Y-m') !== $moisCourant): ?>
      <a class="bouton bouton--secondaire bouton--petit"
         href="<?= url('budget', $filtres + ['mois' => $moisCourant]) ?>">Ce mois-ci</a>
    <?php endif; ?>
    <a class="bouton bouton--secondaire bouton--petit" href="<?= $lienMois($suivant) ?>"
       aria-label="Mois suivant">→</a>
    <h2 class="cal-titre" style="text-transform:capitalize">
      <?= e(strtolower(nom_mois((int) $mois->format('n'))) . ' ' . $mois->format('Y')) ?>
    </h2>
  </div>
</div>

<div class="grille grille--4" style="margin-bottom:1.5rem">
  <div class="carte stat">
    <div class="stat__valeur" style="color:var(--succes)">+ <?= e(montant_fr($totaux['recettes'])) ?></div>
    <div class="stat__libelle">recettes du mois</div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur" style="color:var(--erreur)">− <?= e(montant_fr($totaux['depenses'])) ?></div>
    <div class="stat__libelle">dépenses du mois</div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur" style="color:<?= $totaux['solde'] >= 0 ? 'var(--succes)' : 'var(--erreur)' ?>">
      <?= $totaux['solde'] >= 0 ? '+ ' : '− ' ?><?= e(montant_fr(abs($totaux['solde']))) ?>
    </div>
    <div class="stat__libelle">solde</div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur"><?= (int) $totaux['nb'] ?></div>
    <div class="stat__libelle">opération<?= (int) $totaux['nb'] > 1 ? 's' : '' ?></div>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <?php if ($categories !== []): ?>
      <form class="filtres" method="get" action="<?= url('budget') ?>" data-auto-envoi>
        <input type="hidden" name="mois" value="<?= e($mois->format('Y-m')) ?>">
        <div class="champ">
          <label for="f-sens">Sens</label>
          <select id="f-sens" name="sens">
            <option value="">Tout</option>
            <option value="depense"<?= $sens === 'depense' ? ' selected' : '' ?>>Dépenses</option>
            <option value="recette"<?= $sens === 'recette' ? ' selected' : '' ?>>Recettes</option>
          </select>
        </div>
        <div class="champ">
          <label for="f-cat">Catégorie</label>
          <select id="f-cat" name="categorie">
            <option value="">Toutes</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int) $c['id'] ?>"<?= $categorieId === (int) $c['id'] ? ' selected' : '' ?>>
                <?= e($c['icone'] . ' ' . $c['nom']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($filtres !== []): ?>
          <a class="bouton bouton--discret" href="<?= url('budget', ['mois' => $mois->format('Y-m')]) ?>">
            Réinitialiser
          </a>
        <?php endif; ?>
        <noscript><button class="bouton bouton--secondaire bouton--petit" type="submit">Filtrer</button></noscript>
      </form>
    <?php endif; ?>

    <?php if ($operations === []): ?>
      <div class="vide">
        <span class="vide__icone">💶</span>
        <p>Aucune opération <?= $filtres !== [] ? 'ne correspond à ces filtres' : 'ce mois-ci' ?>.</p>
        <?php if ($filtres !== []): ?>
          <a class="bouton bouton--secondaire" href="<?= url('budget', ['mois' => $mois->format('Y-m')]) ?>">
            Voir tout le mois
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
                  <?= e(libelle_categorie($op)) ?><?= $op['moyen'] ? ' · ' . e($op['moyen']) : '' ?>
                </span>
                <?php if ($op['note']): ?>
                  <br><span class="evt-ligne__meta"><?= e(extrait($op['note'], 90)) ?></span>
                <?php endif; ?>
              </span>

              <span class="evt-ligne__droite">
                <strong style="font-variant-numeric:tabular-nums;white-space:nowrap;color:<?=
                    $op['sens'] === 'recette' ? 'var(--succes)' : 'var(--erreur)' ?>">
                  <?= $op['sens'] === 'recette' ? '+' : '−' ?> <?= e(montant_fr($op['montant'])) ?>
                </strong>
                <a class="bouton bouton--discret bouton--petit"
                   href="<?= url('budget/operations/' . $op['id'] . '/modifier') ?>" title="Modifier">✎</a>
                <form method="post" action="<?= url('budget/operations/' . $op['id'] . '/supprimer') ?>"
                      class="en-ligne" data-confirmation="Supprimer cette opération ?">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <button class="bouton bouton--discret bouton--petit" type="submit" title="Supprimer">✕</button>
                </form>
              </span>
            </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="pile">
    <div class="carte">
      <h2>Ajouter une opération</h2>
      <form method="post" action="<?= url('budget/operations') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

        <fieldset style="margin-bottom:1rem">
          <legend>Sens</legend>
          <div style="display:flex;gap:1rem">
            <label class="case"><input type="radio" name="sens" value="depense" checked> Dépense</label>
            <label class="case"><input type="radio" name="sens" value="recette"> Recette</label>
          </div>
        </fieldset>

        <div class="champ">
          <label for="libelle">Intitulé</label>
          <input type="text" id="libelle" name="libelle" required maxlength="160" placeholder="Courses Lidl">
        </div>

        <div class="ligne-champs">
          <div class="champ">
            <label for="montant">Montant</label>
            <input type="text" id="montant" name="montant" required inputmode="decimal" placeholder="12,50">
          </div>
          <div class="champ">
            <label for="date_operation">Date</label>
            <input type="date" id="date_operation" name="date_operation" required
                   value="<?= e($mois->format('Y-m') === $moisCourant ? date('Y-m-d') : $mois->format('Y-m-01')) ?>">
          </div>
        </div>

        <div class="champ">
          <label for="categorie_id">Catégorie</label>
          <select id="categorie_id" name="categorie_id">
            <option value="">— Aucune —</option>
            <optgroup label="Dépenses">
              <?php foreach ($categories as $c): ?>
                <?php if ($c['sens'] === 'depense'): ?>
                  <option value="<?= (int) $c['id'] ?>"><?= e($c['icone'] . ' ' . $c['nom']) ?></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </optgroup>
            <optgroup label="Recettes">
              <?php foreach ($categories as $c): ?>
                <?php if ($c['sens'] === 'recette'): ?>
                  <option value="<?= (int) $c['id'] ?>"><?= e($c['icone'] . ' ' . $c['nom']) ?></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </optgroup>
          </select>
          <span class="champ__aide">
            Elle doit correspondre au sens choisi. <a href="<?= url('budget/categories') ?>">Gérer les catégories</a>
          </span>
        </div>

        <div class="champ">
          <label for="moyen">Moyen de paiement</label>
          <select id="moyen" name="moyen">
            <option value="">— Non précisé —</option>
            <?php foreach ($moyens as $m): ?>
              <option value="<?= e($m) ?>"><?= e($m) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button class="bouton bouton--bloc" type="submit">Enregistrer</button>
      </form>
    </div>

    <?php if ($parCategorie !== []): ?>
      <div class="carte">
        <h2>Dépenses par catégorie</h2>
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
                  <strong><?= e(montant_fr($total)) ?></strong>
                  <?php if ($plafond !== null): ?>
                    <span class="discret">/ <?= e(montant_fr($plafond)) ?></span>
                  <?php endif; ?>
                </span>
              </div>
              <div class="jauge" title="<?= $plafond !== null
                  ? e(round($part) . ' % du plafond')
                  : e(round($part) . ' % des dépenses du mois') ?>">
                <span style="width:<?= number_format($part, 1, '.', '') ?>%;background:<?=
                    $depassement ? 'var(--erreur)' : e($c['couleur']) ?>"></span>
              </div>
              <?php if ($depassement): ?>
                <span class="discret" style="color:var(--erreur)">
                  Plafond dépassé de <?= e(montant_fr($total - $plafond)) ?>
                </span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="carte">
      <h2>Les 12 derniers mois</h2>
      <div class="histogramme" role="img"
           aria-label="Recettes et dépenses des douze derniers mois">
        <?php foreach ($historique as $h): ?>
          <?php
          $hr = $plafondHistorique > 0 ? ($h['recettes'] / $plafondHistorique) * 100 : 0;
          $hd = $plafondHistorique > 0 ? ($h['depenses'] / $plafondHistorique) * 100 : 0;
          ?>
          <a class="histogramme__mois<?= $h['periode'] === $mois->format('Y-m') ? ' est-actif' : '' ?>"
             href="<?= url('budget', ['mois' => $h['periode']]) ?>"
             title="<?= e(nom_mois((int) $h['mois']->format('n')) . ' ' . $h['mois']->format('Y')
                 . ' — recettes ' . montant_fr($h['recettes']) . ', dépenses ' . montant_fr($h['depenses'])) ?>">
            <span class="histogramme__barres">
              <span class="histogramme__recette" style="height:<?= number_format($hr, 1, '.', '') ?>%"></span>
              <span class="histogramme__depense" style="height:<?= number_format($hd, 1, '.', '') ?>%"></span>
            </span>
            <span class="histogramme__libelle"><?= e(mb_substr(nom_mois((int) $h['mois']->format('n')), 0, 1)) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <p class="discret" style="margin:.6rem 0 0;font-size:.8rem">
        <span style="color:var(--succes)">▮</span> recettes
        <span style="color:var(--erreur);margin-left:.5rem">▮</span> dépenses
      </p>
    </div>
  </div>
</div>
