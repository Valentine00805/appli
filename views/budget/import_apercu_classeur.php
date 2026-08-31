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
      <a href="<?= url('budget/import') ?>">← Choisir un autre fichier</a>
    </p>
    <h1>Aperçu du classeur</h1>
    <p><?= e($nom) ?> — <?= count($lignes) ?> dépense<?= count($lignes) > 1 ? 's' : '' ?> reconnue<?= count($lignes) > 1 ? 's' : '' ?>.</p>
  </div>
</div>

<div class="grille grille--4" style="margin-bottom:1.25rem">
  <div class="carte stat">
    <div class="stat__valeur" style="color:var(--succes)"><?= $aImporter ?></div>
    <div class="stat__libelle">à reprendre</div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur" style="color:var(--info)"><?= $doublons ?></div>
    <div class="stat__libelle">déjà en base</div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur"><?= count($rubriques) ?></div>
    <div class="stat__libelle">rubrique<?= count($rubriques) > 1 ? 's' : '' ?> trouvée<?= count($rubriques) > 1 ? 's' : '' ?></div>
  </div>
  <div class="carte stat">
    <div class="stat__valeur" style="color:<?= $ignorees > 0 ? 'var(--erreur)' : 'inherit' ?>"><?= $ignorees ?></div>
    <div class="stat__libelle">ligne<?= $ignorees > 1 ? 's' : '' ?> illisible<?= $ignorees > 1 ? 's' : '' ?></div>
  </div>
</div>

<section class="carte" style="margin-bottom:1.25rem">
  <h2>Contrôle avec les totaux de votre feuille</h2>
  <p class="discret" style="margin:.2rem 0 .8rem">
    Le total recalculé est comparé à celui que vous aviez inscrit. Un écart n'est
    pas forcément une erreur : il signale souvent une ligne que vous comptiez
    autrement — à vous de voir.
  </p>
  <table class="tableau" style="max-width:560px">
    <thead>
      <tr>
        <th scope="col">Mois</th>
        <th scope="col" class="nombre">Recalculé</th>
        <th scope="col" class="nombre">Votre feuille</th>
        <th scope="col">Écart</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($controles as $c): ?>
        <tr>
          <th scope="row" style="font-weight:500;text-transform:capitalize">
            <?= e(strtolower(nom_mois((int) substr($c['mois'], 5, 2))) . ' ' . substr($c['mois'], 0, 4)) ?>
          </th>
          <td class="nombre"><?= e(montant_fr($c['recalcule'])) ?></td>
          <td class="nombre"><?= $c['feuille'] === null
              ? '<span class="discret">non indiqué</span>'
              : e(montant_fr($c['feuille'])) ?></td>
          <td>
            <?php if ($c['ecart'] === null): ?>
              <span class="discret">—</span>
            <?php elseif (abs($c['ecart']) < 0.02): ?>
              <span class="pastille" style="background:var(--succes-doux);color:var(--succes)">identique</span>
            <?php else: ?>
              <span class="pastille" style="background:var(--erreur-doux);color:var(--erreur)">
                <?= $c['ecart'] > 0 ? '+' : '−' ?> <?= e(montant_fr(abs($c['ecart']))) ?>
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
      <h2>Rubriques du classeur</h2>
      <p class="discret" style="margin:.2rem 0 .8rem">
        Vos rubriques ont été déduites des lignes « Total … ». Rattachez-les à vos
        catégories, ou laissez l'application les créer.
      </p>
      <div class="grille grille--2">
        <?php foreach ($rubriques as $r): ?>
          <div class="champ" style="margin:0">
            <label for="rub-<?= e(md5($r['nom'])) ?>">
              <?= e($r['nom']) ?> <span class="discret">(<?= (int) $r['nb'] ?> ligne<?= (int) $r['nb'] > 1 ? 's' : '' ?>)</span>
            </label>
            <select id="rub-<?= e(md5($r['nom'])) ?>" name="rubrique[<?= e($r['nom']) ?>]">
              <option value="creer"<?= $r['categorie'] === null ? ' selected' : '' ?>>
                ➕ Créer la catégorie « <?= e($r['nom']) ?> »
              </option>
              <?php foreach ($categories as $c): ?>
                <?php if ($c['sens'] === 'depense'): ?>
                  <option value="<?= (int) $c['id'] ?>"<?= $r['categorie'] === (int) $c['id'] ? ' selected' : '' ?>>
                    <?= e($c['icone'] . ' ' . $c['nom']) ?>
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
              <option value="aucune">— Sans catégorie —</option>
            </select>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="carte" style="margin-bottom:1.25rem">
    <h2>Réglages de la reprise</h2>
    <div class="grille grille--2">
      <div class="champ" style="margin:0">
        <label for="rembourse_par">Qui devait vous rembourser</label>
        <input type="text" id="rembourse_par" name="rembourse_par" maxlength="80"
               list="liste-personnes" value="Parents">
        <datalist id="liste-personnes">
          <?php foreach ($personnes as $p): ?>
            <option value="<?= e($p) ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <span class="champ__aide">Toutes les lignes reprises seront cochées « à me faire rembourser ».</span>
      </div>
      <div class="champ" style="margin:0">
        <span class="legende">Ces dépenses sont-elles soldées ?</span>
        <label class="case">
          <input type="checkbox" name="deja_rembourse" value="1" checked>
          Oui, marquer comme déjà remboursées
        </label>
        <span class="champ__aide">
          À décocher si vous attendez encore le remboursement de ce classeur.
          Les lignes « hors total » gardent leur statut dans tous les cas.
        </span>
      </div>
    </div>
  </section>

  <section class="carte">
    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:.5rem;flex-wrap:wrap">
      <h2 style="margin:0">Dépenses reconnues</h2>
      <span class="discret">Les lignes déjà présentes en base sont décochées.</span>
    </div>

    <div style="overflow-x:auto;margin-top:.8rem">
      <table class="tableau tableau--import">
        <thead>
          <tr>
            <th scope="col"><span class="sr-only">Reprendre</span>✓</th>
            <th scope="col">Date</th>
            <th scope="col">Libellé</th>
            <th scope="col" class="nombre">Payé</th>
            <th scope="col" class="nombre">Réclamé</th>
            <th scope="col">Rubrique</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lignes as $l): ?>
            <tr<?= $l['doublon'] ? ' class="est-doublon"' : ($l['statut'] === 'hors_total' ? ' class="est-hors-total"' : '') ?>>
              <td>
                <input type="checkbox" name="ligne[]" value="<?= (int) $l['index'] ?>"
                       <?= $l['doublon'] ? '' : ' checked' ?>
                       aria-label="Reprendre <?= e($l['libelle']) ?>">
              </td>
              <td style="white-space:nowrap"><?= e(date('d/m/Y', strtotime($l['date']))) ?></td>
              <td>
                <?= e($l['libelle']) ?>
                <?php if ($l['doublon']): ?>
                  <span class="pastille" style="background:var(--info-doux);color:var(--info)">déjà reprise</span>
                <?php endif; ?>
                <?php if ($l['statut'] === 'hors_total'): ?>
                  <span class="pastille">hors total</span>
                <?php endif; ?>
              </td>
              <td class="nombre"><?= e(montant_fr($l['montant'])) ?></td>
              <td class="nombre">
                <?php if ($l['part'] !== null): ?>
                  <strong><?= e(montant_fr($l['part'])) ?></strong>
                  <span class="discret" title="Bloc partagé en deux dans votre feuille">◐</span>
                <?php else: ?>
                  <?= e(montant_fr($l['montant'])) ?>
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
      Reprendre les lignes cochées
    </button>
    <span class="discret">Chaque ligne restera modifiable et supprimable ensuite.</span>
  </div>
</form>

<form method="post" action="<?= url('budget/import/abandonner') ?>" style="margin-top:1rem">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <button class="bouton bouton--discret" type="submit">Abandonner cet import</button>
</form>
