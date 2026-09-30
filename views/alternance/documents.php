<?php
/**
 * Les documents de l'alternance, rangés par catégorie.
 *
 * @var array $parCategorie  catégorie => documents
 * @var string $onglet
 * @var array|null $situation
 */
$csrf = Session::jetonCsrf();
$total = array_sum(array_map('count', $parCategorie));
?>
<?= Vue::rendre('alternance/_onglets', ['onglet' => $onglet, 'situation' => $situation]) ?>

<div class="entete-page">
  <div>
    <h1><?= e(t('alt.do.titre')) ?></h1>
    <p><?= e(t('alt.do.aide')) ?></p>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <?php if ($total === 0): ?>
      <div class="vide">
        <span class="vide__icone">📁</span>
        <p><?= e(t('alt.do.aucun')) ?></p>
      </div>
    <?php endif; ?>

    <?php foreach (Alternance::categories() as $cle => $cat): ?>
      <?php if ($parCategorie[$cle] === []) { continue; } ?>
      <section class="carte">
        <h2><?= $cat['icone'] ?> <?= e($cat['nom']) ?> <span class="discret">(<?= count($parCategorie[$cle]) ?>)</span></h2>
        <ul class="liste-fichiers">
          <?php foreach ($parCategorie[$cle] as $d): ?>
            <li class="fichier">
              <span class="fichier__icone" aria-hidden="true"><?= Fichiers::icone($d['mime'], $d['nom_origine']) ?></span>
              <span style="min-width:0">
                <a class="fichier__nom" href="<?= url('alternance/documents/' . (int) $d['id']) ?>" target="_blank" rel="noopener">
                  <?= e($d['nom_origine']) ?>
                </a><br>
                <span class="fichier__meta">
                  <?= e(taille_lisible((int) $d['taille'])) ?> · <?= e(t('alt.do.depose_le', ['date' => date_fr((string) $d['created_at'], false)])) ?>
                </span>
              </span>
              <span class="fichier__actions">
                <a class="bouton bouton--discret bouton--petit"
                   href="<?= url('alternance/documents/' . (int) $d['id'], ['telecharger' => 1]) ?>"
                   title="<?= e(t('alt.do.telecharger')) ?>" aria-label="<?= e(t('alt.do.telecharger_nom', ['nom' => (string) $d['nom_origine']])) ?>">⬇</a>
                <form method="post" action="<?= url('alternance/documents/' . (int) $d['id'] . '/categorie') ?>"
                      class="en-ligne alternance-ranger" data-auto-envoi>
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <label class="sr-only" for="ranger-<?= (int) $d['id'] ?>"><?= e(t('alt.do.ranger_dans_nom', ['nom' => (string) $d['nom_origine']])) ?></label>
                  <select id="ranger-<?= (int) $d['id'] ?>" name="categorie">
                    <?php foreach (Alternance::categories() as $autre => $c): ?>
                      <option value="<?= e($autre) ?>"<?= $autre === $cle ? ' selected' : '' ?>><?= e($c['nom']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <noscript><button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('alt.do.ranger')) ?></button></noscript>
                </form>
                <form method="post" action="<?= url('alternance/documents/' . (int) $d['id'] . '/supprimer') ?>" class="en-ligne"
                      data-confirmation="<?= e(t('alt.do.supprimer_confirmation', ['nom' => (string) $d['nom_origine']])) ?>">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <button class="bouton bouton--discret bouton--petit" type="submit" title="<?= e(t('alt.do.supprimer')) ?>"
                          aria-label="<?= e(t('alt.do.supprimer_nom', ['nom' => (string) $d['nom_origine']])) ?>">✕</button>
                </form>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
  </div>

  <div class="pile">
    <section class="carte">
      <h2><?= e(t('alt.do.deposer')) ?></h2>
      <form method="post" action="<?= url('alternance/documents') ?>" enctype="multipart/form-data" class="depot" data-depot>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="champ">
          <label for="categorie"><?= e(t('alt.do.ranger_dans')) ?></label>
          <select id="categorie" name="categorie">
            <?php foreach (Alternance::categories() as $cle => $cat): ?>
              <option value="<?= e($cle) ?>"><?= $cat['icone'] ?> <?= e($cat['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <label class="depot__zone" for="depot-alternance">
          <span class="depot__icone" aria-hidden="true">📎</span>
          <span>
            <strong><?= e(t('alt.do.zone')) ?></strong><br>
            <span class="discret"><?= e(t('alt.do.zone_aide', ['taille' => taille_lisible(Fichiers::tailleMax())])) ?></span>
          </span>
        </label>
        <input type="file" id="depot-alternance" name="fichiers[]" multiple class="depot__champ" data-depot-champ>
        <button class="bouton bouton--petit bouton--bloc" type="submit" data-depot-envoi><?= e(t('alt.do.deposer')) ?></button>
      </form>
    </section>
  </div>
</div>
