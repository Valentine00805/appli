<?php
/** @var array $matieres, $palette @var int $sansMatiere */
$csrf = Session::jetonCsrf();
?>

<?= Vue::rendre('organisation/_onglets', ['onglet' => 'matieres']) ?>

<div class="entete-page">
  <div>
    <h1><?= e(t('mat.titre')) ?></h1>
    <p><?= e(t('mat.sous_titre')) ?></p>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <?php if ($matieres === []): ?>
      <div class="vide">
        <span class="vide__icone">🎨</span>
        <p><?= e(t('mat.aucune')) ?></p>
      </div>
    <?php else: ?>
      <?php foreach ($matieres as $m): ?>
        <section class="carte">
          <div class="matiere-carte">
            <span class="matiere-pastille" style="background:<?= e($m['couleur']) ?>"></span>
            <div style="flex:1;min-width:0">
              <h2 style="margin-bottom:.15rem"><?= e($m['nom']) ?></h2>
              <p class="discret" style="margin:0">
                <?= e(tn('mat.nb_cours', (int) $m['nb_cours'])) ?> · <?= e(tn('types.nb_evenements', (int) $m['nb_evenements'])) ?>
                <?= $m['enseignant'] ? ' · ' . e($m['enseignant']) : '' ?>
              </p>
            </div>
            <div class="actions">
              <a class="bouton bouton--discret bouton--petit" href="<?= url('cours', ['matiere' => $m['id']]) ?>"><?= e(t('mat.voir_cours')) ?></a>
              <button class="bouton bouton--secondaire bouton--petit" type="button"
                      data-bascule="edition-<?= (int) $m['id'] ?>"><?= e(t('evt.modifier')) ?></button>
            </div>
          </div>

          <div id="edition-<?= (int) $m['id'] ?>" hidden style="margin-top:1rem">
            <hr class="separateur" style="margin:.75rem 0">
            <form method="post" action="<?= url('matieres/' . $m['id'] . '/modifier') ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

              <div class="ligne-champs">
                <div class="champ">
                  <label for="nom-<?= (int) $m['id'] ?>"><?= e(t('commun.nom')) ?></label>
                  <input type="text" id="nom-<?= (int) $m['id'] ?>" name="nom" required maxlength="120"
                         value="<?= e($m['nom']) ?>">
                </div>
                <div class="champ">
                  <label for="ens-<?= (int) $m['id'] ?>"><?= e(t('mat.enseignant')) ?></label>
                  <input type="text" id="ens-<?= (int) $m['id'] ?>" name="enseignant" maxlength="120"
                         value="<?= e((string) $m['enseignant']) ?>">
                </div>
              </div>

              <div class="champ">
                <span class="legende"><?= e(t('commun.couleur')) ?></span>
                <div class="choix-couleurs">
                  <?php foreach ($palette as $i => $couleur): ?>
                    <?php $id = 'c-' . $m['id'] . '-' . $i; ?>
                    <input type="radio" id="<?= $id ?>" name="couleur" value="<?= e($couleur) ?>"
                           <?= strtolower((string) $m['couleur']) === $couleur ? ' checked' : '' ?>>
                    <label for="<?= $id ?>" style="background:<?= e($couleur) ?>"
                           title="<?= e($couleur) ?>"><span class="sr-only"></span></label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="actions">
                <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
              </div>
            </form>

            <form method="post" action="<?= url('matieres/' . $m['id'] . '/supprimer') ?>" style="margin-top:.75rem"
                  data-confirmation="<?= e(t('mat.supprimer_sur')) ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('mat.supprimer')) ?></button>
            </form>
          </div>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($sansMatiere > 0): ?>
      <p class="discret">
        <?= e(tn('mat.sans_matiere', $sansMatiere)) ?>
        <a href="<?= url('cours') ?>"><?= e(t('mat.sans_matiere_lien')) ?></a>.
      </p>
    <?php endif; ?>
  </div>

  <div class="carte">
    <h2><?= e(t('mat.nouvelle')) ?></h2>
    <form method="post" action="<?= url('matieres') ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

      <div class="champ">
        <label for="nom"><?= e(t('commun.nom')) ?></label>
        <input type="text" id="nom" name="nom" required maxlength="120" placeholder="<?= e(t('mat.nom_exemple')) ?>">
      </div>

      <div class="champ">
        <label for="enseignant"><?= e(t('mat.enseignant')) ?> <span class="discret"><?= e(t('commun.facultatif')) ?></span></label>
        <input type="text" id="enseignant" name="enseignant" maxlength="120" placeholder="<?= e(t('mat.enseignant_exemple')) ?>">
      </div>

      <div class="champ">
        <span class="legende"><?= e(t('commun.couleur')) ?></span>
        <div class="choix-couleurs">
          <?php foreach ($palette as $i => $couleur): ?>
            <input type="radio" id="nc-<?= $i ?>" name="couleur" value="<?= e($couleur) ?>"<?= $i === 0 ? ' checked' : '' ?>>
            <label for="nc-<?= $i ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
          <?php endforeach; ?>
        </div>
      </div>

      <button class="bouton bouton--bloc" type="submit"><?= e(t('mat.creer')) ?></button>
    </form>
  </div>
</div>
