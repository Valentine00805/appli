<?php
/** @var array $tags @var string $tri @var int $inutilises */
$csrf = Session::jetonCsrf();
$total = count($tags);
?>

<?= Vue::rendre('organisation/_onglets', ['onglet' => 'tags']) ?>

<div class="entete-page">
  <div>
    <h1><?= e(t('tags.titre')) ?></h1>
    <p>
      <?= e(tn('tags.nb', $total)) ?>
      <?= e(t('tags.sous_titre')) ?>
    </p>
  </div>
  <div class="actions">
    <a class="bouton bouton--secondaire" href="<?= url('cours') ?>"><?= e(t('mat.voir_cours')) ?></a>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <?php if ($tags === []): ?>
      <div class="vide">
        <span class="vide__icone">🏷️</span>
        <p><?= e(t('tags.aucun')) ?></p>
        <p class="discret">
          <?= e(t('tags.aucun_aide')) ?>
        </p>
      </div>
    <?php else: ?>
      <div class="filtres" style="margin-bottom:.75rem">
        <span class="discret"><?= e(t('tags.trier')) ?></span>
        <a class="pastille" href="<?= url('organisation/tags', ['tri' => 'nom']) ?>"
           <?= $tri === 'nom' ? 'style="background:var(--accent-doux);color:var(--accent-fonce)"' : '' ?>>A → Z</a>
        <a class="pastille" href="<?= url('organisation/tags', ['tri' => 'usage']) ?>"
           <?= $tri === 'usage' ? 'style="background:var(--accent-doux);color:var(--accent-fonce)"' : '' ?>><?= e(t('tags.plus_utilises')) ?></a>
      </div>

      <?php foreach ($tags as $t): ?>
        <section class="carte">
          <div class="matiere-carte">
            <span class="matiere-pastille"
                  style="background:var(--fond-doux);display:grid;place-items:center;font-size:1.1rem;color:var(--texte-doux)">#</span>

            <div style="flex:1;min-width:0">
              <h2 style="margin-bottom:.15rem"><?= e($t['nom']) ?></h2>
              <p class="discret" style="margin:0">
                <?php if ((int) $t['nb_cours'] === 0): ?>
                  <?= e(t('tags.sur_aucun_cours')) ?>
                <?php else: ?>
                  <?= e(tn('tags.sur_cours', (int) $t['nb_cours'])) ?>
                <?php endif; ?>
              </p>
            </div>

            <div class="actions">
              <?php if ((int) $t['nb_cours'] > 0): ?>
                <a class="bouton bouton--discret bouton--petit"
                   href="<?= url('cours', ['tag' => $t['id']]) ?>"><?= e(t('commun.voir')) ?></a>
              <?php endif; ?>
              <button class="bouton bouton--secondaire bouton--petit" type="button"
                      data-bascule="edition-tag-<?= (int) $t['id'] ?>"><?= e(t('evt.modifier')) ?></button>
            </div>
          </div>

          <div id="edition-tag-<?= (int) $t['id'] ?>" hidden style="margin-top:1rem">
            <hr class="separateur" style="margin:.75rem 0">

            <form method="post" action="<?= url('tags/' . $t['id'] . '/modifier') ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <div class="champ">
                <label for="nom-tag-<?= (int) $t['id'] ?>"><?= e(t('tags.renommer')) ?></label>
                <input type="text" id="nom-tag-<?= (int) $t['id'] ?>" name="nom" required maxlength="60"
                       value="<?= e($t['nom']) ?>">
                <span class="champ__aide"><?= e(t('tags.renommer_aide')) ?></span>
              </div>
              <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
            </form>

            <?php if ($total > 1): ?>
              <hr class="separateur" style="margin:1rem 0">
              <form method="post" action="<?= url('tags/' . $t['id'] . '/fusionner') ?>"
                    data-confirmation="<?= e(t('tags.fusionner_sur')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="champ">
                  <label for="cible-<?= (int) $t['id'] ?>"><?= e(t('tags.fusionner_dans')) ?></label>
                  <select id="cible-<?= (int) $t['id'] ?>" name="cible_id" required>
                    <option value=""><?= e(t('tags.choisir')) ?></option>
                    <?php foreach ($tags as $autre): ?>
                      <?php if ((int) $autre['id'] !== (int) $t['id']): ?>
                        <option value="<?= (int) $autre['id'] ?>"><?= e($autre['nom']) ?></option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </select>
                  <span class="champ__aide">
                    <?= e(t('tags.fusionner_aide')) ?>
                  </span>
                </div>
                <button class="bouton bouton--secondaire" type="submit"><?= e(t('tags.fusionner')) ?></button>
              </form>
            <?php endif; ?>

            <form method="post" action="<?= url('tags/' . $t['id'] . '/supprimer') ?>" style="margin-top:1rem"
                  data-confirmation="<?= e(t('tags.supprimer_sur')) ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('tags.supprimer')) ?></button>
            </form>
          </div>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="pile">
    <div class="carte">
      <h2><?= e(t('tags.nouveaux')) ?></h2>
      <form method="post" action="<?= url('tags') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="champ">
          <label for="nom"><?= e(t('commun.nom')) ?></label>
          <input type="text" id="nom" name="nom" required maxlength="300"
                 placeholder="<?= e(t('tags.nom_exemple')) ?>">
          <span class="champ__aide">
            <?= e(t('tags.plusieurs_aide')) ?>
          </span>
        </div>
        <button class="bouton bouton--bloc" type="submit"><?= e(t('tags.creer')) ?></button>
      </form>
    </div>

    <?php if ($inutilises > 0): ?>
      <div class="carte">
        <h2><?= e(t('tags.menage')) ?></h2>
        <p class="discret">
          <?= e(tn('tags.inutilises', $inutilises)) ?>
        </p>
        <form method="post" action="<?= url('tags/nettoyer') ?>"
              data-confirmation="<?= e(t('tags.nettoyer_sur')) ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--secondaire bouton--bloc" type="submit">
            <?= e(t('tags.nettoyer')) ?>
          </button>
        </form>
      </div>
    <?php endif; ?>

    <div class="carte">
      <h2><?= e(t('tags.comment')) ?></h2>
      <p class="discret" style="margin-bottom:.6rem">
        <?= e(t('tags.comment_1')) ?>
      </p>
      <p class="discret" style="margin:0">
        <?= e(t('tags.comment_2')) ?>
      </p>
    </div>
  </div>
</div>
