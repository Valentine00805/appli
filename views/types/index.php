<?php
/** @var array $types, $palette, $icones @var int $sansType */
$csrf = Session::jetonCsrf();
$dernier = count($types) - 1;
?>

<?= Vue::rendre('organisation/_onglets', ['onglet' => 'types']) ?>

<div class="entete-page">
  <div>
    <h1><?= e(t('types.titre')) ?></h1>
    <p><?= e(t('types.sous_titre')) ?></p>
  </div>
  <div class="actions">
    <a class="bouton bouton--secondaire" href="<?= url('calendrier') ?>"><?= e(t('types.voir_calendrier')) ?></a>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <?php if ($types === []): ?>
      <div class="vide">
        <span class="vide__icone">🏷️</span>
        <p><?= e(t('types.aucun')) ?></p>
      </div>
    <?php else: ?>
      <?php foreach ($types as $i => $t): ?>
        <section class="carte">
          <div class="matiere-carte">
            <span class="matiere-pastille"
                  style="background:<?= e($t['couleur']) ?>;display:grid;place-items:center;font-size:1.2rem">
              <?= e($t['icone']) ?>
            </span>

            <div style="flex:1;min-width:0">
              <h2 style="margin-bottom:.15rem"><?= e($t['nom']) ?></h2>
              <p class="discret" style="margin:0">
                <?= e(tn('types.nb_evenements', (int) $t['nb_evenements'])) ?>
                <?php if ((int) $t['est_echeance'] === 1): ?>
                  · <span title="<?= e(t('types.est_echeance_aide')) ?>"><?= e(t('types.est_echeance_puce')) ?></span>
                <?php endif; ?>
                <?php if ((int) $t['au_tableau'] === 0): ?>
                  · <span title="<?= e(t('types.hors_tableau_aide')) ?>"><?= e(t('types.hors_tableau_puce')) ?></span>
                <?php endif; ?>
              </p>
            </div>

            <div class="actions">
              <form method="post" action="<?= url('types/' . $t['id'] . '/deplacer') ?>" class="en-ligne">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="sens" value="haut">
                <button class="bouton bouton--discret bouton--petit" type="submit"
                        title="<?= e(t('commun.monter')) ?>"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
              </form>
              <form method="post" action="<?= url('types/' . $t['id'] . '/deplacer') ?>" class="en-ligne">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="sens" value="bas">
                <button class="bouton bouton--discret bouton--petit" type="submit"
                        title="<?= e(t('commun.descendre')) ?>"<?= $i === $dernier ? ' disabled' : '' ?>>↓</button>
              </form>
              <a class="bouton bouton--discret bouton--petit"
                 href="<?= url('calendrier', ['vue' => 'liste', 'type' => $t['id']]) ?>"><?= e(t('commun.voir')) ?></a>
              <button class="bouton bouton--secondaire bouton--petit" type="button"
                      data-bascule="edition-type-<?= (int) $t['id'] ?>"><?= e(t('evt.modifier')) ?></button>
            </div>
          </div>

          <div id="edition-type-<?= (int) $t['id'] ?>" hidden style="margin-top:1rem">
            <hr class="separateur" style="margin:.75rem 0">

            <form method="post" action="<?= url('types/' . $t['id'] . '/modifier') ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

              <div class="champ">
                <label for="nom-t-<?= (int) $t['id'] ?>"><?= e(t('commun.nom')) ?></label>
                <input type="text" id="nom-t-<?= (int) $t['id'] ?>" name="nom" required maxlength="60"
                       value="<?= e($t['nom']) ?>">
              </div>

              <div class="champ">
                <span class="legende"><?= e(t('commun.icone')) ?></span>
                <div class="choix-icones">
                  <?php foreach ($icones as $j => $icone): ?>
                    <?php $idIcone = 'i-' . $t['id'] . '-' . $j; ?>
                    <input type="radio" id="<?= $idIcone ?>" name="icone" value="<?= e($icone) ?>"
                           <?= $t['icone'] === $icone ? ' checked' : '' ?>>
                    <label for="<?= $idIcone ?>"><?= e($icone) ?></label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="champ">
                <span class="legende"><?= e(t('commun.couleur')) ?></span>
                <div class="choix-couleurs">
                  <?php foreach ($palette as $j => $couleur): ?>
                    <?php $idCouleur = 'ct-' . $t['id'] . '-' . $j; ?>
                    <input type="radio" id="<?= $idCouleur ?>" name="couleur" value="<?= e($couleur) ?>"
                           <?= strtolower((string) $t['couleur']) === $couleur ? ' checked' : '' ?>>
                    <label for="<?= $idCouleur ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
                  <?php endforeach; ?>
                </div>
                <span class="champ__aide"><?= e(t('types.couleur_aide')) ?></span>
              </div>

              <label class="case" style="margin-bottom:1rem">
                <input type="checkbox" name="est_echeance" value="1"<?= (int) $t['est_echeance'] === 1 ? ' checked' : '' ?>>
                <?= e(t('types.compte_echeance')) ?>
              </label>

              <span class="champ__aide" style="display:block;margin:-.75rem 0 1rem">
                <?= e(t('types.compte_echeance_aide')) ?>
              </span>

              <label class="case" style="margin-bottom:1rem">
                <input type="checkbox" name="au_tableau" value="1"<?= (int) $t['au_tableau'] === 1 ? ' checked' : '' ?>>
                <?= e(t('types.au_tableau')) ?>
              </label>
              <span class="champ__aide" style="display:block;margin:-.75rem 0 1rem">
                <?= e(t('types.au_tableau_aide')) ?>
              </span>

              <div class="actions">
                <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
              </div>
            </form>

            <form method="post" action="<?= url('types/' . $t['id'] . '/supprimer') ?>" style="margin-top:.75rem"
                  data-confirmation="<?= e(t('types.supprimer_sur')) ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('types.supprimer')) ?></button>
            </form>
          </div>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($sansType > 0): ?>
      <p class="discret">
        <?= e(tn('types.sans_type', $sansType)) ?>
        <a href="<?= url('calendrier', ['vue' => 'liste']) ?>"><?= e(t('types.sans_type_lien')) ?></a>
        <?= e(t('types.sans_type_suite')) ?>
      </p>
    <?php endif; ?>
  </div>

  <div class="carte">
    <h2><?= e(t('types.nouveau')) ?></h2>
    <form method="post" action="<?= url('types') ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

      <div class="champ">
        <label for="nom"><?= e(t('commun.nom')) ?></label>
        <input type="text" id="nom" name="nom" required maxlength="60" placeholder="<?= e(t('types.nom_exemple')) ?>">
      </div>

      <div class="champ">
        <span class="legende"><?= e(t('commun.icone')) ?></span>
        <div class="choix-icones">
          <?php foreach ($icones as $j => $icone): ?>
            <input type="radio" id="ni-<?= $j ?>" name="icone" value="<?= e($icone) ?>"<?= $j === 0 ? ' checked' : '' ?>>
            <label for="ni-<?= $j ?>"><?= e($icone) ?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="champ">
        <span class="legende"><?= e(t('commun.couleur')) ?></span>
        <div class="choix-couleurs">
          <?php foreach ($palette as $j => $couleur): ?>
            <input type="radio" id="nct-<?= $j ?>" name="couleur" value="<?= e($couleur) ?>"<?= $j === 0 ? ' checked' : '' ?>>
            <label for="nct-<?= $j ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
          <?php endforeach; ?>
        </div>
      </div>

      <label class="case" style="margin-bottom:1rem">
        <input type="checkbox" name="est_echeance" value="1">
        <?= e(t('types.compte_echeance')) ?>
      </label>

      <label class="case" style="margin-bottom:1rem">
        <input type="checkbox" name="au_tableau" value="1" checked>
        <?= e(t('types.au_tableau')) ?>
      </label>

      <button class="bouton bouton--bloc" type="submit"><?= e(t('types.creer')) ?></button>
    </form>
  </div>
</div>
