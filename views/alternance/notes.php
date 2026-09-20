<?php
/**
 * Les notes de l'alternance : ce qu'on retient d'une réunion, d'un outil,
 * d'une procédure de l'entreprise.
 *
 * @var array $notes
 * @var string $recherche  ce qu'on cherche, ou une chaîne vide
 * @var int $combien       le nombre de notes en tout, recherche comprise
 * @var string $onglet
 * @var array|null $situation
 */
$csrf = Session::jetonCsrf();
?>
<?= Vue::rendre('alternance/_onglets', ['onglet' => $onglet, 'situation' => $situation]) ?>

<div class="entete-page">
  <div>
    <h1>🗒️ Notes d’alternance</h1>
    <p>Ce que vous voulez garder de l’entreprise : une réunion, un outil, une procédure, une idée pour le rapport.</p>
  </div>
  <a class="bouton" href="<?= url('alternance/notes/nouvelle') ?>" data-fenetre>+ Nouvelle note</a>
</div>

<?php if ($combien > 0): ?>
  <form method="get" action="<?= url('alternance') ?>" class="filtres" role="search" style="margin-bottom:1rem">
    <label class="sr-only" for="q">Chercher dans mes notes</label>
    <input type="search" id="q" name="q" value="<?= e($recherche) ?>"
           placeholder="Chercher dans le titre et le texte…">
    <button class="bouton bouton--secondaire" type="submit">Chercher</button>
    <?php if ($recherche !== ''): ?>
      <a class="bouton bouton--discret" href="<?= url('alternance') ?>">Tout revoir</a>
    <?php endif; ?>
  </form>
<?php endif; ?>

<?php if ($notes === []): ?>
  <div class="vide">
    <span class="vide__icone">🗒️</span>
    <?php if ($recherche !== ''): ?>
      <p>Aucune note ne parle de « <?= e($recherche) ?> ».</p>
      <p><a class="bouton bouton--secondaire" href="<?= url('alternance') ?>">Revoir toutes les notes</a></p>
    <?php else: ?>
      <p>Aucune note pour l’instant.</p>
      <p><a class="bouton bouton--secondaire" href="<?= url('alternance/notes/nouvelle') ?>" data-fenetre>Écrire la première</a></p>
    <?php endif; ?>
  </div>
<?php else: ?>
  <?php if ($recherche !== ''): ?>
    <p class="discret"><?= count($notes) ?> note<?= count($notes) > 1 ? 's' : '' ?> sur <?= (int) $combien ?>.</p>
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
          <span class="discret alternance-note__date">Modifiée le <?= e(date_fr((string) $n['updated_at'])) ?></span>
        </a>
        <form method="post" action="<?= url('alternance/notes/' . (int) $n['id'] . '/epingler') ?>" class="alternance-note__epingle">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="retour" value="<?= e(url('alternance', $recherche === '' ? [] : ['q' => $recherche])) ?>">
          <button class="bouton bouton--discret bouton--petit" type="submit"
                  title="<?= $epinglee ? 'Décrocher cette note' : 'Épingler en haut de la liste' ?>"
                  aria-label="<?= $epinglee ? 'Décrocher' : 'Épingler' ?> « <?= e($n['titre']) ?> »">
            <?= $epinglee ? '📌' : '📍' ?>
          </button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
