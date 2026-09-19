<?php
/**
 * Les notes de l'alternance : ce qu'on retient d'une réunion, d'un outil,
 * d'une procédure de l'entreprise.
 *
 * @var array $notes
 * @var string $onglet
 * @var array|null $situation
 */
?>
<?= Vue::rendre('alternance/_onglets', ['onglet' => $onglet, 'situation' => $situation]) ?>

<div class="entete-page">
  <div>
    <h1>🗒️ Notes d’alternance</h1>
    <p>Ce que vous voulez garder de l’entreprise : une réunion, un outil, une procédure, une idée pour le rapport.</p>
  </div>
  <a class="bouton" href="<?= url('alternance/notes/nouvelle') ?>" data-fenetre>+ Nouvelle note</a>
</div>

<?php if ($notes === []): ?>
  <div class="vide">
    <span class="vide__icone">🗒️</span>
    <p>Aucune note pour l’instant.</p>
    <p><a class="bouton bouton--secondaire" href="<?= url('alternance/notes/nouvelle') ?>" data-fenetre>Écrire la première</a></p>
  </div>
<?php else: ?>
  <div class="alternance-notes">
    <?php foreach ($notes as $n): ?>
      <a class="carte alternance-note" href="<?= url('alternance/notes/' . (int) $n['id']) ?>">
        <strong class="alternance-note__titre"><?= e($n['titre']) ?></strong>
        <?php $texte = extrait(TexteRiche::versTexte($n['contenu']), 180); ?>
        <?php if ($texte !== ''): ?>
          <span class="alternance-note__extrait"><?= e($texte) ?></span>
        <?php endif; ?>
        <span class="discret alternance-note__date">Modifiée le <?= e(date_fr((string) $n['updated_at'])) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
