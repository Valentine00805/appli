<?php
/**
 * Les notes de l'alternance : ce qu'on retient d'une réunion, d'un outil,
 * d'une procédure de l'entreprise.
 *
 * @var array $notes
 * @var string $recherche  ce qu'on cherche, ou une chaîne vide
 * @var string $etiquette  l'étiquette dont on ne veut que les notes
 * @var array<string, int> $etiquettes  celles qu'on a déjà posées, et combien de fois
 * @var int $combien       le nombre de notes en tout, recherche comprise
 * @var string $onglet
 * @var array|null $situation
 */
$csrf = Session::jetonCsrf();
?>
<?= Vue::rendre('alternance/_onglets', ['onglet' => $onglet, 'situation' => $situation]) ?>

<div class="entete-page">
  <div>
    <h1><?= e(t('alt.no.titre')) ?></h1>
    <p><?= e(t('alt.no.aide')) ?></p>
  </div>
  <a class="bouton" href="<?= url('alternance/notes/nouvelle') ?>" data-fenetre><?= e(t('alt.no.nouvelle')) ?></a>
</div>

<?php if ($combien > 0): ?>
  <form method="get" action="<?= url('alternance') ?>" class="filtres" role="search" style="margin-bottom:1rem">
    <label class="sr-only" for="q"><?= e(t('alt.no.chercher_label')) ?></label>
    <input type="search" id="q" name="q" value="<?= e($recherche) ?>"
           placeholder="<?= e(t('alt.no.chercher_exemple')) ?>">
    <button class="bouton bouton--secondaire" type="submit"><?= e(t('alt.jo.chercher')) ?></button>
    <?php if ($recherche !== ''): ?>
      <a class="bouton bouton--discret" href="<?= url('alternance') ?>"><?= e(t('alt.jo.tout_revoir')) ?></a>
    <?php endif; ?>
  </form>
<?php endif; ?>

<?php if ($etiquettes !== []): ?>
  <?php // Les étiquettes posées sur ses notes : un clic filtre. ?>
  <p class="alternance-etiquettes">
    <?php foreach ($etiquettes as $nom => $combien): ?>
      <a class="pastille<?= mb_strtolower($nom) === mb_strtolower($etiquette) ? ' pastille--active' : '' ?>"
         href="<?= url('alternance', mb_strtolower($nom) === mb_strtolower($etiquette) ? [] : ['etiquette' => $nom]) ?>">
        <?= e($nom) ?> <span class="discret"><?= (int) $combien ?></span>
      </a>
    <?php endforeach; ?>
  </p>
<?php endif; ?>

<?php if ($notes === []): ?>
  <div class="vide">
    <span class="vide__icone">🗒️</span>
    <?php if ($recherche !== ''): ?>
      <p><?= e(t('alt.no.rien_trouve', ['recherche' => $recherche])) ?></p>
      <p><a class="bouton bouton--secondaire" href="<?= url('alternance') ?>"><?= e(t('alt.no.revoir_tout')) ?></a></p>
    <?php else: ?>
      <p><?= e(t('alt.no.aucune')) ?></p>
      <p><a class="bouton bouton--secondaire" href="<?= url('alternance/notes/nouvelle') ?>" data-fenetre><?= e(t('alt.no.ecrire_premiere')) ?></a></p>
    <?php endif; ?>
  </div>
<?php else: ?>
  <?php if ($recherche !== ''): ?>
    <p class="discret"><?= e(tn('alt.no.combien', count($notes), ['total' => (int) $combien])) ?></p>
  <?php endif; ?>
  <div class="alternance-notes">
    <?php foreach ($notes as $n): ?>
      <?php $epinglee = (int) $n['epinglee'] === 1; ?>
      <div class="carte alternance-note<?= $epinglee ? ' alternance-note--epinglee' : '' ?>">
        <a class="alternance-note__lien" href="<?= url('alternance/notes/' . (int) $n['id']) ?>">
          <strong class="alternance-note__titre">
            <?= $epinglee ? '<span aria-hidden="true">📌</span> ' : '' ?><?= e($n['titre']) ?>
          </strong>
          <?php $texte = extrait(TexteRiche::versTexte($n['contenu']), 180); ?>
          <?php if ($texte !== ''): ?>
            <span class="alternance-note__extrait"><?= e($texte) ?></span>
          <?php endif; ?>
          <?php $siennes = Alternance::etiquettes($n['etiquettes'] ?? null); ?>
          <?php if ($siennes !== []): ?>
            <span class="alternance-note__etiquettes">
              <?php foreach ($siennes as $une): ?><span class="pastille"><?= e($une) ?></span><?php endforeach; ?>
            </span>
          <?php endif; ?>
          <span class="discret alternance-note__date"><?= e(t('alt.no.modifiee_le', ['date' => date_fr((string) $n['updated_at'])])) ?></span>
        </a>
        <form method="post" action="<?= url('alternance/notes/' . (int) $n['id'] . '/epingler') ?>" class="alternance-note__epingle">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="retour" value="<?= e(url('alternance', $recherche === '' ? [] : ['q' => $recherche])) ?>">
          <button class="bouton bouton--discret bouton--petit" type="submit"
                  title="<?= e(t($epinglee ? 'alt.no.decrocher_titre' : 'alt.no.epingler_titre')) ?>"
                  aria-label="<?= e(t($epinglee ? 'alt.no.decrocher_nom' : 'alt.no.epingler_nom', ['titre' => (string) $n['titre']])) ?>">
            <?= $epinglee ? '📌' : '📍' ?>
          </button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
