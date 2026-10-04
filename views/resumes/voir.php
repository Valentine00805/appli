<?php
/**
 * Un résumé écrit par l'IA : son texte, ce qui a été lu, sa voix.
 *
 * @var array $resume    la ligne de resumes_ia
 * @var array $sources   ce qui a été lu : [['cours' => titre, 'lu' => [étiquettes]]]
 * @var ?string $cleFin  la fin de la clé Gemini de l'utilisateur, ou null
 * @var bool $dansUneFenetre  rendu seul, pour être posé dans une fenêtre
 */
$id = (int) $resume['id'];
$dansUneFenetre = $dansUneFenetre ?? false;
// Dans une fenêtre, la voix et l'effacement s'y font sans la quitter : le résumé reste là, à jour.
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
?>

<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <?php // Dans une fenêtre, la liste est juste derrière : la croix y ramène. ?>
    <?php if (!$dansUneFenetre): ?>
      <p class="discret" style="margin-bottom:.35rem"><a href="<?= url('resumes') ?>"><?= e(t('ria.retour')) ?></a></p>
    <?php endif; ?>
    <h1><?= e($resume['titre']) ?></h1>
    <p class="discret">
      <?= e(t('ria.genre.' . $resume['genre'])) ?> · <?= e(t('ria.long.' . $resume['longueur'])) ?> ·
      <?= e(date_fr((string) $resume['created_at'])) ?>
      <?php if ($resume['modele'] !== null): ?> · <?= e((string) $resume['modele']) ?><?php endif; ?>
    </p>
  </div>
</div>

<section class="carte ria-texte">
  <?= Markdown::html((string) $resume['contenu']) ?>
</section>
<p class="champ__aide"><?= e(t('ria.avertissement_ia')) ?></p>

<?php if ($sources !== []): ?>
  <section class="carte">
    <h2><?= e(t('ria.sources')) ?></h2>
    <ul class="ria-liste">
      <?php foreach ($sources as $s): ?>
        <li><strong><?= e((string) $s['cours']) ?></strong>
          <span class="discret">— <?= e(implode(', ', array_map('strval', (array) $s['lu']))) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<section class="carte">
  <h2><?= e(t('ria.audio')) ?></h2>

  <?php if ($resume['audio_nom'] !== null): ?>
    <audio controls preload="none" src="<?= url('resumes/' . $id . '/audio') ?>" style="width:100%"></audio>
    <p class="actions">
      <a class="bouton bouton--secondaire bouton--petit" href="<?= url('resumes/' . $id . '/audio', ['telecharger' => 1]) ?>"><?= e(t('ria.telecharger')) ?></a>
    </p>
  <?php endif; ?>

  <?php if ($cleFin === null): ?>
    <p><?= e(t('ria.pas_de_cle')) ?> <a href="<?= url('compte') ?>#gemini"><?= e(t('ria.ajouter_cle')) ?></a></p>
  <?php else: ?>
    <form method="post" action="<?= url('resumes/' . $id . '/voix') ?>" class="fabrique__form"
          data-attente="<?= e(t('ria.voix_en_cours')) ?>"<?= $envoi ?>>
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <div class="champ">
        <label for="voix"><?= e(t('ria.voix')) ?></label>
        <select id="voix" name="voix">
          <?php foreach (Gemini::VOIX as $voix): ?>
            <option value="<?= e($voix) ?>" <?= ($resume['audio_voix'] ?? Gemini::VOIX[0]) === $voix ? 'selected' : '' ?>><?= e($voix) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <p class="champ__aide"><?= e(t('ria.voix_aide')) ?></p>
      <button class="bouton bouton--secondaire" type="submit">
        <?= e($resume['audio_nom'] === null ? t('ria.voix_generer') : t('ria.voix_refaire')) ?>
      </button>
    </form>
  <?php endif; ?>
</section>

<form method="post" action="<?= url('resumes/' . $id . '/supprimer') ?>" data-confirmation="<?= e(t('ria.supprimer_sur')) ?>"<?= $envoi ?>>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <button class="bouton bouton--discret" type="submit"><?= e(t('ria.supprimer')) ?></button>
</form>
