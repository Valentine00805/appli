<?php
/**
 * Le journal des missions : une page par semaine, la plus récente en haut.
 *
 * @var array $pages
 * @var list<string> $aEcrire  les lundis des semaines en entreprise sans page
 * @var string $cetteSemaine   le lundi de cette semaine
 * @var string $recherche      ce qu’on cherche, ou une chaîne vide
 * @var int $combien           le nombre de semaines écrites en tout
 * @var string $onglet
 * @var array|null $situation
 */
$csrf = Session::jetonCsrf();
$ecrite = in_array($cetteSemaine, array_column($pages, 'semaine'), true);
?>
<?= Vue::rendre('alternance/_onglets', ['onglet' => $onglet, 'situation' => $situation]) ?>

<div class="entete-page">
  <div>
    <h1><?= e(t('alt.jo.titre')) ?></h1>
    <p><?= e(t('alt.jo.aide')) ?></p>
  </div>
  <span class="alternance-page__actions">
    <?php if ($pages !== []): ?>
      <a class="bouton bouton--secondaire" href="<?= url('alternance/journal/competences') ?>"><?= e(t('alt.jo.competences')) ?></a>
    <?php endif; ?>
    <a class="bouton" href="<?= url('alternance/journal/semaine', ['semaine' => $cetteSemaine]) ?>" data-fenetre>
      <?= e(t($ecrite ? 'alt.jo.completer' : 'alt.jo.ecrire')) ?>
    </a>
  </span>
</div>

<?php if ($combien > 0): ?>
  <form method="get" action="<?= url('alternance/journal') ?>" class="filtres" role="search" style="margin-bottom:1rem">
    <label class="sr-only" for="q"><?= e(t('alt.jo.chercher_label')) ?></label>
    <input type="search" id="q" name="q" value="<?= e($recherche) ?>"
           placeholder="<?= e(t('alt.jo.chercher_exemple')) ?>">
    <button class="bouton bouton--secondaire" type="submit"><?= e(t('alt.jo.chercher')) ?></button>
    <?php if ($recherche !== ''): ?>
      <a class="bouton bouton--discret" href="<?= url('alternance/journal') ?>"><?= e(t('alt.jo.tout_revoir')) ?></a>
    <?php endif; ?>
  </form>
<?php endif; ?>

<?php if ($pages !== [] && $recherche === ''): ?>
  <?php
  /*
   * Le journal en PDF : c'est ce qu'on recopie dans le livret, ou qu'on joint
   * au rapport. Les dates sont facultatives — sans elles, tout sort — et
   * proposent d'avance la première et la dernière semaine écrites.
   */
  $premiere = (string) $pages[count($pages) - 1]['semaine'];
  $derniere = (new DateTimeImmutable((string) $pages[0]['semaine']))->modify('+4 days')->format('Y-m-d');
  ?>
  <details class="carte alternance-export">
    <summary class="alternance-export__ouvrir"><?= e(t('alt.jo.exporter')) ?></summary>
    <form method="get" action="<?= url('alternance/journal/pdf') ?>">
      <div class="ligne-champs">
        <div class="champ">
          <label for="du"><?= e(t('alt.jo.depuis')) ?></label>
          <input type="date" id="du" name="du" value="<?= e($premiere) ?>">
        </div>
        <div class="champ">
          <label for="au"><?= e(t('alt.jo.jusquau')) ?></label>
          <input type="date" id="au" name="au" value="<?= e($derniere) ?>">
        </div>
      </div>
      <button class="bouton" type="submit"><?= e(t('alt.jo.telecharger')) ?></button>
      <p class="champ__aide"><?= e(t('alt.jo.export_aide')) ?></p>
    </form>
  </details>
<?php endif; ?>

<?php if ($aEcrire !== []): ?>
  <div class="carte alternance-rappel">
    <strong><?= e(tn('alt.jo.sans_page', count($aEcrire))) ?></strong>
    <?php foreach ($aEcrire as $lundi): ?>
      <a class="pastille" href="<?= url('alternance/journal/semaine', ['semaine' => $lundi]) ?>" data-fenetre>
        <?= e(t('alt.jo.semaine_du_court', ['date' => Alternance::jourCourt($lundi)])) ?>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($pages === []): ?>
  <div class="vide">
    <span class="vide__icone">📓</span>
    <?php if ($recherche !== ''): ?>
      <p><?= e(t('alt.jo.rien_trouve', ['recherche' => $recherche])) ?></p>
      <p><a class="bouton bouton--secondaire" href="<?= url('alternance/journal') ?>"><?= e(t('alt.jo.revoir_tout')) ?></a></p>
    <?php else: ?>
      <p><?= e(t('alt.jo.vide')) ?></p>
    <?php endif; ?>
  </div>
<?php else: ?>
  <?php if ($recherche !== ''): ?>
    <p class="discret"><?= e(tn('alt.jo.combien', count($pages), ['total' => (int) $combien])) ?></p>
  <?php endif; ?>
  <div class="pile">
    <?php foreach ($pages as $p): ?>
      <article class="carte alternance-page">
        <header class="alternance-page__entete">
          <h2><?= e(t('alt.jo.semaine_du', ['date' => Alternance::jourCourt((string) $p['semaine'])])) ?></h2>
          <span class="alternance-page__actions">
            <a class="bouton bouton--discret bouton--petit"
               href="<?= url('alternance/journal/semaine', ['semaine' => $p['semaine']]) ?>" data-fenetre><?= e(t('alt.jo.modifier')) ?></a>
            <form method="post" action="<?= url('alternance/journal/' . (int) $p['id'] . '/supprimer') ?>" class="en-ligne"
                  data-confirmation="<?= e(t('alt.jo.supprimer_confirmation', ['date' => Alternance::jourCourt((string) $p['semaine'])])) ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--discret bouton--petit" type="submit" title="<?= e(t('alt.jo.supprimer')) ?>"
                      aria-label="<?= e(t('alt.jo.supprimer_page')) ?>">✕</button>
            </form>
          </span>
        </header>
        <?php if ($p['missions']): ?>
          <div class="texte-riche-affiche"><?= TexteRiche::versHtml($p['missions']) ?></div>
        <?php endif; ?>
        <?php $competences = Alternance::competences($p['competences']); ?>
        <?php if ($competences !== []): ?>
          <p class="alternance-competences">
            <span class="discret"><?= e(t('alt.jo.competences_label')) ?></span>
            <?php foreach ($competences as $c): ?>
              <span class="pastille"><?= e($c) ?></span>
            <?php endforeach; ?>
          </p>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
