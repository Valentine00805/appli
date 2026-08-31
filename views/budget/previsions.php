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
    <h1>Prévisions</h1>
    <p>Le solde prévisionnel d'un mois devient le solde de départ du suivant.</p>
  </div>
  <div class="actions">
    <a class="bouton bouton--secondaire bouton--petit"
       href="<?= url('budget/previsions', ['mois' => $precedent]) ?>" aria-label="Mois précédent">←</a>
    <?php if ($periode !== $moisCourant): ?>
      <a class="bouton bouton--secondaire bouton--petit"
         href="<?= url('budget/previsions', ['mois' => $moisCourant]) ?>">Ce mois-ci</a>
    <?php endif; ?>
    <a class="bouton bouton--secondaire bouton--petit"
       href="<?= url('budget/previsions', ['mois' => $suivant]) ?>" aria-label="Mois suivant">→</a>
    <h2 class="cal-titre" style="text-transform:capitalize">
      <?= e(strtolower(nom_mois((int) $mois->format('n'))) . ' ' . $mois->format('Y')) ?>
    </h2>
  </div>
</div>

<?php if ($sansAncrage): ?>
  <div class="carte" style="border-color:var(--accent);margin-bottom:1.25rem">
    <h2>Pour commencer, indiquez votre solde actuel</h2>
    <p class="discret">
      C'est le point de départ du calcul : le montant que vous avez réellement sur votre compte
      au début de ce mois. Tout le reste s'en déduit, et les mois suivants s'enchaînent tout seuls.
    </p>
    <form method="post" action="<?= url('budget/previsions/solde') ?>" style="max-width:420px">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="periode" value="<?= e($periode) ?>">
      <div class="champ">
        <label for="montant-depart">Solde au 1<sup>er</sup> <?= e(strtolower(nom_mois((int) $mois->format('n')))) ?></label>
        <input type="text" id="montant-depart" name="montant" required inputmode="decimal" autofocus
               placeholder="1250,40">
      </div>
      <button class="bouton" type="submit">Enregistrer le solde de départ</button>
    </form>
  </div>
<?php endif; ?>

<div class="grille grille--4" style="margin-bottom:1.5rem">
  <div class="carte stat">
    <div class="stat__valeur" style="color:<?= $couleurSolde($courant['solde_depart'] ?? null) ?>">
      <?= $courant === null || $courant['solde_depart'] === null
          ? '—' : e(montant_fr($courant['solde_depart'])) ?>
    </div>
    <div class="stat__libelle">
      solde de départ
      <?php if ($courant !== null && $courant['origine'] === 'reporte'): ?>
        <span title="Repris du solde prévisionnel du mois précédent">· reporté</span>
      <?php elseif ($courant !== null && $courant['origine'] === 'saisi'): ?>
        <span title="Vous avez saisi ce montant à la main">· saisi</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="carte stat">
    <div class="stat__valeur" style="color:var(--succes)">
      + <?= e(montant_fr(($courant['reel_recettes'] ?? 0) + ($courant['prevu_recettes'] ?? 0))) ?>
    </div>
    <div class="stat__libelle">
      recettes
      <?php if (($courant['prevu_recettes'] ?? 0) > 0): ?>
        <span class="discret">dont <?= e(montant_fr($courant['prevu_recettes'])) ?> à venir</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="carte stat">
    <div class="stat__valeur" style="color:var(--erreur)">
      − <?= e(montant_fr(($courant['reel_depenses'] ?? 0) + ($courant['prevu_depenses'] ?? 0))) ?>
    </div>
    <div class="stat__libelle">
      dépenses
      <?php if (($courant['prevu_depenses'] ?? 0) > 0): ?>
        <span class="discret">dont <?= e(montant_fr($courant['prevu_depenses'])) ?> à venir</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="carte stat" style="border-color:var(--accent)">
    <div class="stat__valeur" style="color:<?= $couleurSolde($courant['solde_previsionnel'] ?? null) ?>">
      <?= $courant === null || $courant['solde_previsionnel'] === null
          ? '—' : e(montant_fr($courant['solde_previsionnel'])) ?>
    </div>
    <div class="stat__libelle"><strong>solde prévisionnel</strong> fin de mois</div>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <section class="carte">
      <div style="display:flex;justify-content:space-between;align-items:baseline;gap:.5rem;flex-wrap:wrap">
        <h2 style="margin:0">Charges fixes et revenus réguliers</h2>
        <?php if ($aVenir !== []): ?>
          <form method="post" action="<?= url('budget/previsions/pointer-tout') ?>" class="en-ligne"
                data-confirmation="Créer les opérations correspondant à toutes les lignes restantes de ce mois ?">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="periode" value="<?= e($periode) ?>">
            <button class="bouton bouton--secondaire bouton--petit" type="submit">
              Tout saisir (<?= count($aVenir) ?>)
            </button>
          </form>
        <?php endif; ?>
      </div>

      <?php if ($recurrences === []): ?>
        <p class="discret" style="margin:.75rem 0 0">
          Aucune ligne fixe. Ajoutez votre loyer, vos abonnements, votre bourse…
          avec le formulaire ci-contre : ils seront comptés automatiquement chaque mois.
        </p>
      <?php else: ?>
        <p class="discret" style="margin:.4rem 0 1rem">
          « À venir » signifie que la ligne est comptée dans la prévision mais pas encore
          saisie dans les opérations réelles. La saisir évite de la compter deux fois.
        </p>

        <?php foreach ([['Charges fixes', $fixes], ['Revenus réguliers', $reguliers]] as [$titre, $liste]): ?>
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
                      le <?= (int) $r['jour_du_mois'] ?> du mois
                      <?= $r['categorie_nom'] ? ' · ' . e($r['categorie_nom']) : '' ?>
                      <?php if ((int) $r['actif'] === 0): ?> · en pause<?php endif; ?>
                    </span>
                  </span>

                  <span class="evt-ligne__droite">
                    <?php if ((int) $r['actif'] === 1): ?>
                      <span class="pastille" style="<?= $enAttente
                          ? 'background:var(--info-doux);color:var(--info)'
                          : 'background:var(--succes-doux);color:var(--succes)' ?>">
                        <?= $enAttente ? 'à venir' : 'saisie' ?>
                      </span>
                    <?php endif; ?>

                    <strong style="font-variant-numeric:tabular-nums;white-space:nowrap;color:<?=
                        $r['sens'] === 'recette' ? 'var(--succes)' : 'var(--erreur)' ?>">
                      <?= $r['sens'] === 'recette' ? '+' : '−' ?> <?= e(montant_fr($r['montant'])) ?>
                    </strong>

                    <?php if ($enAttente && (int) $r['actif'] === 1): ?>
                      <form method="post" action="<?= url('budget/previsions/recurrences/' . $r['id'] . '/pointer') ?>"
                            class="en-ligne">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="periode" value="<?= e($periode) ?>">
                        <button class="bouton bouton--discret bouton--petit" type="submit"
                                title="Créer l'opération réelle pour ce mois">✓</button>
                      </form>
                    <?php endif; ?>

                    <button class="bouton bouton--discret bouton--petit" type="button"
                            data-bascule="edition-rec-<?= (int) $r['id'] ?>" title="Modifier">✎</button>
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
                        <label for="lib-<?= (int) $r['id'] ?>">Intitulé</label>
                        <input type="text" id="lib-<?= (int) $r['id'] ?>" name="libelle" required maxlength="160"
                               value="<?= e($r['libelle']) ?>">
                      </div>
                      <div class="champ">
                        <label for="mnt-<?= (int) $r['id'] ?>">Montant</label>
                        <input type="text" id="mnt-<?= (int) $r['id'] ?>" name="montant" required inputmode="decimal"
                               value="<?= e(montant_fr($r['montant'], false)) ?>">
                      </div>
                      <div class="champ">
                        <label for="jour-<?= (int) $r['id'] ?>">Jour</label>
                        <input type="number" id="jour-<?= (int) $r['id'] ?>" name="jour_du_mois" min="1" max="31"
                               value="<?= (int) $r['jour_du_mois'] ?>">
                      </div>
                    </div>

                    <div class="champ">
                      <label for="cat-<?= (int) $r['id'] ?>">Catégorie</label>
                      <select id="cat-<?= (int) $r['id'] ?>" name="categorie_id">
                        <option value="">— Aucune —</option>
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
                      Active — décochez pour la mettre en pause sans la supprimer
                    </label>

                    <div class="actions">
                      <button class="bouton bouton--petit" type="submit">Enregistrer</button>
                    </div>
                  </form>

                  <form method="post" action="<?= url('budget/previsions/recurrences/' . $r['id'] . '/supprimer') ?>"
                        style="margin-top:.6rem"
                        data-confirmation="Supprimer cette ligne du prévisionnel ? Les opérations déjà saisies sont conservées.">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="periode" value="<?= e($periode) ?>">
                    <button class="bouton bouton--danger bouton--petit" type="submit">Supprimer</button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <section class="carte">
      <h2>Projection</h2>

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
        Chaque mois part du solde prévisionnel du précédent, auquel s'ajoutent
        les lignes fixes et les opérations déjà saisies.
      </p>
      <div style="overflow-x:auto">
        <table class="tableau">
          <thead>
            <tr>
              <th scope="col">Mois</th>
              <th scope="col" class="nombre">Départ</th>
              <th scope="col" class="nombre">Recettes</th>
              <th scope="col" class="nombre">Dépenses</th>
              <th scope="col" class="nombre">Prévisionnel</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($chaine as $p => $ligne): ?>
              <?php if ($p < $periode) { continue; } ?>
              <tr<?= $p === $periode ? ' class="est-actif"' : '' ?>>
                <th scope="row" style="font-weight:600;text-transform:capitalize">
                  <a href="<?= url('budget/previsions', ['mois' => $p]) ?>" style="text-decoration:none;color:inherit">
                    <?= e(strtolower(nom_mois((int) $ligne['mois']->format('n'))) . ' ' . $ligne['mois']->format('Y')) ?>
                  </a>
                  <?php if ($ligne['origine'] === 'saisi'): ?>
                    <span class="discret" title="Solde saisi à la main">✎</span>
                  <?php endif; ?>
                </th>
                <td class="nombre"><?= $ligne['solde_depart'] === null ? '—' : e(montant_fr($ligne['solde_depart'])) ?></td>
                <td class="nombre" style="color:var(--succes)">
                  <?= e(montant_fr($ligne['reel_recettes'] + $ligne['prevu_recettes'])) ?>
                </td>
                <td class="nombre" style="color:var(--erreur)">
                  <?= e(montant_fr($ligne['reel_depenses'] + $ligne['prevu_depenses'])) ?>
                </td>
                <td class="nombre">
                  <strong style="color:<?= $couleurSolde($ligne['solde_previsionnel']) ?>">
                    <?= $ligne['solde_previsionnel'] === null ? '—' : e(montant_fr($ligne['solde_previsionnel'])) ?>
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
      <h2>Ajouter une ligne fixe</h2>
      <form method="post" action="<?= url('budget/previsions/recurrences') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="periode" value="<?= e($periode) ?>">

        <fieldset style="margin-bottom:1rem">
          <legend>Nature</legend>
          <div style="display:flex;gap:1rem">
            <label class="case"><input type="radio" name="sens" value="depense" checked> Charge</label>
            <label class="case"><input type="radio" name="sens" value="recette"> Revenu</label>
          </div>
        </fieldset>

        <div class="champ">
          <label for="libelle">Intitulé</label>
          <input type="text" id="libelle" name="libelle" required maxlength="160" placeholder="Loyer, Netflix, Bourse…">
        </div>

        <div class="ligne-champs">
          <div class="champ">
            <label for="montant">Montant</label>
            <input type="text" id="montant" name="montant" required inputmode="decimal" placeholder="420,00">
          </div>
          <div class="champ">
            <label for="jour_du_mois">Jour du mois</label>
            <input type="number" id="jour_du_mois" name="jour_du_mois" min="1" max="31" value="1">
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
        </div>

        <div class="champ">
          <label for="moyen">Moyen de paiement</label>
          <select id="moyen" name="moyen">
            <option value="">— Non précisé —</option>
            <?php foreach ($moyens as $m): ?>
              <option value="<?= e($m) ?>"<?= $m === 'Prélèvement' ? ' selected' : '' ?>><?= e($m) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button class="bouton bouton--bloc" type="submit">Ajouter</button>
      </form>
    </div>

    <div class="carte">
      <h2>Solde de départ</h2>
      <?php if ($ancrage !== null): ?>
        <p class="discret" style="margin:0 0 .75rem">
          Dernier solde saisi : <strong><?= e(montant_fr($ancrage['montant'])) ?></strong>
          en <?= e(strtolower(nom_mois((int) substr((string) $ancrage['periode'], 5, 2)))) ?>
          <?= e(substr((string) $ancrage['periode'], 0, 4)) ?>.
          <?php if ($ancrage['periode'] !== $periode): ?>
            Les mois suivants s'enchaînent à partir de là.
          <?php endif; ?>
        </p>
      <?php endif; ?>

      <form method="post" action="<?= url('budget/previsions/solde') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="periode" value="<?= e($periode) ?>">
        <div class="champ">
          <label for="montant-solde">
            Forcer le solde de <?= e(strtolower(nom_mois((int) $mois->format('n')))) ?>
          </label>
          <input type="text" id="montant-solde" name="montant" required inputmode="decimal"
                 value="<?= $soldeSaisi !== null ? e(montant_fr($soldeSaisi['montant'], false)) : '' ?>"
                 placeholder="<?= $courant !== null && $courant['solde_depart'] !== null
                     ? e(montant_fr($courant['solde_depart'], false)) : '0,00' ?>">
          <span class="champ__aide">
            À utiliser pour se recaler sur le vrai solde bancaire. Les mois suivants
            repartent de cette valeur.
          </span>
        </div>
        <button class="bouton bouton--bloc" type="submit">
          <?= $soldeSaisi !== null ? 'Mettre à jour' : 'Fixer ce solde' ?>
        </button>
      </form>

      <?php if ($soldeSaisi !== null): ?>
        <form method="post" action="<?= url('budget/previsions/solde/supprimer') ?>" style="margin-top:.6rem"
              data-confirmation="Supprimer ce solde saisi ? Le mois repartira du solde reporté.">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="periode" value="<?= e($periode) ?>">
          <button class="bouton bouton--discret bouton--bloc" type="submit">
            Revenir au solde reporté
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
