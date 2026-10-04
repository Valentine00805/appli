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

<?php
/*
 * Des flash cards (genre « questions ») : une carte par paire, à retourner d'un clic. Un ancien résumé de ce
 * genre, resté en prose, ou un modèle qui n'a pas suivi le format : ni cartes ni paquet, le texte s'affiche tel quel.
 */
$cartes = ResumeIa::cartesDe($resume);
$coursDuPaquet = array_values(array_filter($sources, static fn ($s): bool => (int) ($s['id'] ?? 0) > 0));
?>
<?php if ($cartes !== null): ?>
  <section class="carte">
    <h2><?= e(tn('ria.cartes_titre', count($cartes))) ?></h2>
    <p class="champ__aide"><?= e(t('ria.carte.retourner')) ?></p>
    <div class="flashcards">
      <?php foreach ($cartes as $carte): ?>
        <button type="button" class="flashcard" data-flashcard aria-pressed="false">
          <span class="flashcard__face" data-recto>
            <small class="flashcard__etiquette"><?= e(t('ria.carte.question')) ?></small>
            <?= e($carte['question']) ?>
          </span>
          <span class="flashcard__face flashcard__face--verso" data-verso hidden>
            <small class="flashcard__etiquette"><?= e(t('ria.carte.reponse')) ?></small>
            <?= e($carte['reponse']) ?>
          </span>
        </button>
      <?php endforeach; ?>
    </div>

    <?php if ($coursDuPaquet !== []): ?>
      <form method="post" action="<?= url('resumes/' . $id . '/cartes') ?>" class="fabrique__form"<?= $envoi ?>>
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
        <?php if (count($coursDuPaquet) === 1): ?>
          <input type="hidden" name="cours" value="<?= (int) $coursDuPaquet[0]['id'] ?>">
        <?php else: ?>
          <div class="champ">
            <label for="cours_paquet"><?= e(t('ria.cartes_vers')) ?></label>
            <select id="cours_paquet" name="cours">
              <?php foreach ($coursDuPaquet as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= e((string) $c['cours']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <p class="champ__aide"><?= e(t('ria.cartes_aide')) ?></p>
        <button class="bouton bouton--secondaire" type="submit"><?= e(t('ria.cartes_ajouter')) ?><?php if (count($coursDuPaquet) === 1): ?> — <?= e((string) $coursDuPaquet[0]['cours']) ?><?php endif; ?></button>
      </form>
    <?php endif; ?>
  </section>
<?php else: ?>
  <section class="carte ria-texte">
    <?= Markdown::html((string) $resume['contenu']) ?>
  </section>

  <?php if ($coursDuPaquet !== []): ?>
    <?php // Le texte peut rejoindre la fiche de révision d'un des cours lus (à la suite de ce qui y est déjà). ?>
    <section class="carte">
      <form method="post" action="<?= url('resumes/' . $id . '/fiche') ?>" class="fabrique__form"<?= $envoi ?>>
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
        <?php if (count($coursDuPaquet) === 1): ?>
          <input type="hidden" name="cours" value="<?= (int) $coursDuPaquet[0]['id'] ?>">
        <?php else: ?>
          <div class="champ">
            <label for="cours_fiche"><?= e(t('ria.fiche_vers')) ?></label>
            <select id="cours_fiche" name="cours">
              <?php foreach ($coursDuPaquet as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= e((string) $c['cours']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <p class="champ__aide"><?= e(t('ria.fiche_aide')) ?></p>
        <button class="bouton bouton--secondaire" type="submit"><?= e(t('ria.fiche_ajouter')) ?><?php if (count($coursDuPaquet) === 1): ?> — <?= e((string) $coursDuPaquet[0]['cours']) ?><?php endif; ?></button>
      </form>
    </section>
  <?php endif; ?>
<?php endif; ?>
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

<?php
/*
 * Des flash cards se retournent et se versent au paquet : pas de voix à proposer. Seul un audio déjà fait (demandé
 * avec la case « Audio » à l'écriture) reste, à écouter — sans formulaire pour en refaire un.
 */
?>
<?php if ($cartes === null || $resume['audio_nom'] !== null): ?>
<section class="carte">
  <h2><?= e(t('ria.audio')) ?></h2>

  <?php if ($resume['audio_nom'] !== null): ?>
    <audio controls preload="none" src="<?= url('resumes/' . $id . '/audio') ?>" style="width:100%"></audio>
    <p class="actions">
      <a class="bouton bouton--secondaire bouton--petit" href="<?= url('resumes/' . $id . '/audio', ['telecharger' => 1]) ?>"><?= e(t('ria.telecharger')) ?></a>
    </p>
  <?php endif; ?>

  <?php if ($cartes !== null): ?>
    <?php // Des flash cards : l'audio existant se lit, mais on n'en refait pas ici. ?>
  <?php elseif ($cleFin === null): ?>
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
<?php endif; ?>

<form method="post" action="<?= url('resumes/' . $id . '/supprimer') ?>" data-confirmation="<?= e(t('ria.supprimer_sur')) ?>"<?= $envoi ?>>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <button class="bouton bouton--discret" type="submit"><?= e(t('ria.supprimer')) ?></button>
</form>
