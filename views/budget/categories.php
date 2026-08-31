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
            <?= (int) $c['nb_operations'] ?> opération<?= (int) $c['nb_operations'] > 1 ? 's' : '' ?>
            <?php if ((int) $c['nb_operations'] > 0): ?>
              · <?= e(montant_fr($c['total'])) ?> au total
            <?php endif; ?>
            <?php if ($c['plafond_mensuel'] !== null): ?>
              · plafond <?= e(montant_fr($c['plafond_mensuel'])) ?>/mois
            <?php endif; ?>
          </p>
        </div>

        <div class="actions">
          <a class="bouton bouton--discret bouton--petit"
             href="<?= url('budget', ['categorie' => $c['id']]) ?>">Voir</a>
          <button class="bouton bouton--secondaire bouton--petit" type="button"
                  data-bascule="edition-cat-<?= (int) $c['id'] ?>">Modifier</button>
        </div>
      </div>

      <div id="edition-cat-<?= (int) $c['id'] ?>" hidden style="margin-top:1rem">
        <hr class="separateur" style="margin:.75rem 0">

        <form method="post" action="<?= url('budget/categories/' . $c['id'] . '/modifier') ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

          <div class="champ">
            <label for="nom-c-<?= (int) $c['id'] ?>">Nom</label>
            <input type="text" id="nom-c-<?= (int) $c['id'] ?>" name="nom" required maxlength="60"
                   value="<?= e($c['nom']) ?>">
          </div>

          <div class="champ">
            <span class="legende">Icône</span>
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
            <span class="legende">Couleur</span>
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
              <label for="plafond-<?= (int) $c['id'] ?>">Plafond mensuel</label>
              <input type="text" id="plafond-<?= (int) $c['id'] ?>" name="plafond_mensuel" inputmode="decimal"
                     placeholder="laisser vide pour aucun"
                     value="<?= $c['plafond_mensuel'] !== null ? e(montant_fr($c['plafond_mensuel'], false)) : '' ?>">
              <span class="champ__aide">
                Affiche une jauge et vous signale le dépassement sur la page des opérations.
              </span>
            </div>
          <?php endif; ?>

          <button class="bouton" type="submit">Enregistrer</button>
        </form>

        <form method="post" action="<?= url('budget/categories/' . $c['id'] . '/supprimer') ?>" style="margin-top:.75rem"
              data-confirmation="Supprimer cette catégorie ? Les opérations sont conservées, sans catégorie.">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--danger bouton--petit" type="submit">Supprimer cette catégorie</button>
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
    <h1>Catégories de budget</h1>
    <p>Elles classent vos opérations et servent aux totaux et aux plafonds.</p>
  </div>
  <div class="actions">
    <a class="bouton bouton--secondaire" href="<?= url('budget') ?>">Voir les opérations</a>
  </div>
</div>

<section class="carte" style="margin-bottom:1.5rem;border-color:var(--accent)">
  <div style="display:flex;justify-content:space-between;align-items:baseline;gap:.6rem;flex-wrap:wrap">
    <h2 style="margin:0">💡 Budget proposé</h2>
    <?php if ($suggestions['suffisant']): ?>
      <span class="discret">
        d'après <?= (int) $suggestions['mois_disponibles'] ?> mois de dépenses,
        de <?= e((string) $suggestions['periode']) ?>
      </span>
    <?php endif; ?>
  </div>

  <?php if (!$suggestions['suffisant']): ?>
    <?php $manque = SuggestionBudget::MOIS_MINIMUM - (int) $suggestions['mois_disponibles']; ?>
    <p class="discret" style="margin:.5rem 0 0">
      <?php if ((int) $suggestions['mois_disponibles'] === 0): ?>
        Aucune dépense classée sur les mois écoulés. Saisissez vos dépenses en les
        rangeant par catégorie : au bout de <?= SuggestionBudget::MOIS_MINIMUM ?> mois,
        une proposition de budget apparaîtra ici.
      <?php else: ?>
        <?= (int) $suggestions['mois_disponibles'] ?> mois de dépenses enregistré<?= (int) $suggestions['mois_disponibles'] > 1 ? 's' : '' ?>.
        Encore <?= $manque ?> mois avant une proposition — il en faut au moins
        <?= SuggestionBudget::MOIS_MINIMUM ?> pour dégager une habitude.
      <?php endif; ?>
    </p>
    <div class="jauge" style="margin-top:.6rem;max-width:300px">
      <span style="width:<?= min(100, ((int) $suggestions['mois_disponibles'] / SuggestionBudget::MOIS_MINIMUM) * 100) ?>%;background:var(--accent)"></span>
    </div>

  <?php else: ?>
    <p class="discret" style="margin:.4rem 0 1rem">
      Une fourchette, pas un chiffre : la marge est calculée sur la régularité de
      chaque poste. Un poste stable donne une fourchette serrée, un poste en dents
      de scie une fourchette large.
      <?php if (!$suggestions['fiable']): ?>
        <strong>À prendre avec des pincettes</strong> : avec moins de
        <?= SuggestionBudget::MOIS_CONFIANCE ?> mois, l'estimation reste fragile.
      <?php endif; ?>
    </p>

    <div style="overflow-x:auto">
      <table class="tableau">
        <thead>
          <tr>
            <th scope="col">Poste</th>
            <th scope="col" class="nombre">Fourchette</th>
            <th scope="col" class="nombre">À prévoir</th>
            <th scope="col">Observé</th>
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
                        title="Dépenses régulières d'un mois à l'autre">régulier</span>
                <?php endif; ?>
              </th>
              <td class="nombre">
                <?= e(montant_fr($s['bas'])) ?> <span class="discret">à</span> <?= e(montant_fr($s['haut'])) ?>
              </td>
              <td class="nombre"><strong><?= e(montant_fr($s['conseille'])) ?></strong></td>
              <td class="discret" style="font-size:.82rem;white-space:nowrap">
                <?= (int) $s['mois'] ?> mois ·
                de <?= e(montant_fr($s['mini'])) ?> à <?= e(montant_fr($s['maxi'])) ?>
              </td>
              <td>
                <?php if ($s['plafond'] !== null && abs($s['plafond'] - $s['conseille']) < 0.005): ?>
                  <span class="pastille" style="background:var(--succes-doux);color:var(--succes)">appliqué</span>
                <?php else: ?>
                  <form method="post" action="<?= url('budget/suggestions/' . $s['id'] . '/appliquer') ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button class="bouton bouton--secondaire bouton--petit" type="submit"
                            title="Fixer le plafond mensuel de cette catégorie">
                      Utiliser<?= $s['plafond'] !== null ? ' (remplace ' . e(montant_fr($s['plafond'])) . ')' : '' ?>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr style="border-top:2px solid var(--bordure-forte)">
            <th scope="row">Ensemble des postes</th>
            <td class="nombre">
              <?= e(montant_fr($suggestions['total_bas'])) ?>
              <span class="discret">à</span>
              <?= e(montant_fr($suggestions['total_haut'])) ?>
            </td>
            <td class="nombre"><strong><?= e(montant_fr($suggestions['total_conseille'])) ?></strong></td>
            <td colspan="2"></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="actions" style="margin-top:1rem">
      <form method="post" action="<?= url('budget/suggestions/appliquer') ?>"
            data-confirmation="Fixer le plafond mensuel de tous ces postes à la valeur proposée ?">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <button class="bouton" type="submit">Appliquer toutes les propositions</button>
      </form>
      <span class="discret">
        Les plafonds restent modifiables un par un, plus bas.
      </span>
    </div>
  <?php endif; ?>
</section>

<div class="colonnes">
  <div class="pile">
    <h2 style="margin-bottom:0">Dépenses</h2>
    <?php if ($depenses === []): ?>
      <p class="discret">Aucune catégorie de dépense.</p>
    <?php else: ?>
      <?php foreach ($depenses as $c): ?><?= $carte($c) ?><?php endforeach; ?>
    <?php endif; ?>

    <h2 style="margin:1rem 0 0">Recettes</h2>
    <?php if ($recettes === []): ?>
      <p class="discret">Aucune catégorie de recette.</p>
    <?php else: ?>
      <?php foreach ($recettes as $c): ?><?= $carte($c) ?><?php endforeach; ?>
    <?php endif; ?>

    <?php if ($sansCategorie > 0): ?>
      <p class="discret">
        <?= $sansCategorie ?> opération<?= $sansCategorie > 1 ? 's n\'ont' : ' n\'a' ?> pas de catégorie —
        <a href="<?= url('budget') ?>">les retrouver dans la liste</a>.
      </p>
    <?php endif; ?>
  </div>

  <div class="carte">
    <h2>Nouvelle catégorie</h2>
    <form method="post" action="<?= url('budget/categories') ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

      <fieldset style="margin-bottom:1rem">
        <legend>Type</legend>
        <div style="display:flex;gap:1rem">
          <label class="case"><input type="radio" name="sens" value="depense" checked> Dépense</label>
          <label class="case"><input type="radio" name="sens" value="recette"> Recette</label>
        </div>
      </fieldset>

      <div class="champ">
        <label for="nom">Nom</label>
        <input type="text" id="nom" name="nom" required maxlength="60" placeholder="Cantine, Loisirs…">
      </div>

      <div class="champ">
        <span class="legende">Icône</span>
        <div class="choix-icones">
          <?php foreach ($icones as $j => $icone): ?>
            <input type="radio" id="nic-<?= $j ?>" name="icone" value="<?= e($icone) ?>"<?= $j === 0 ? ' checked' : '' ?>>
            <label for="nic-<?= $j ?>"><?= e($icone) ?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="champ">
        <span class="legende">Couleur</span>
        <div class="choix-couleurs">
          <?php foreach ($palette as $j => $couleur): ?>
            <input type="radio" id="ncc-<?= $j ?>" name="couleur" value="<?= e($couleur) ?>"<?= $j === 0 ? ' checked' : '' ?>>
            <label for="ncc-<?= $j ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="champ">
        <label for="plafond_mensuel">Plafond mensuel <span class="discret">(dépenses seulement)</span></label>
        <input type="text" id="plafond_mensuel" name="plafond_mensuel" inputmode="decimal" placeholder="facultatif">
      </div>

      <button class="bouton bouton--bloc" type="submit">Créer la catégorie</button>
    </form>
  </div>
</div>
