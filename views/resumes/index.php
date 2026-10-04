<?php
/**
 * Les résumés écrits par l'IA : de quoi en demander un, et ceux déjà faits.
 *
 * @var bool $cleConfiguree      le chiffrement des clés est-il en place sur cette installation ?
 * @var ?string $cleFin          la fin de la clé Gemini de l'utilisateur, ou null s'il n'en a pas
 * @var array $cours             ses cours, pour choisir quoi résumer
 * @var array $documentsParCours les fichiers de chaque cours
 * @var ?int $choisi             un cours à cocher d'avance
 * @var array $historique        les résumés déjà écrits
 */
$genres = ['resume', 'points', 'questions'];
$longueurs = ['court', 'moyen', 'long'];
$pret = $cleConfiguree && $cleFin !== null;
?>

<div class="entete-page">
  <div>
    <h1><?= e(t('ria.titre')) ?></h1>
    <p><?= e(t('ria.sous_titre')) ?></p>
  </div>
</div>

<section class="carte fabrique">
  <h2><?= e(t('ria.demander')) ?></h2>

  <?php if (!$cleConfiguree): ?>
    <p class="discret"><?= e(t('gemini.non_configure')) ?></p>
  <?php elseif ($cleFin === null): ?>
    <p><?= e(t('ria.pas_de_cle')) ?>
      <a href="<?= url('compte') ?>#gemini"><?= e(t('ria.ajouter_cle')) ?></a></p>
  <?php elseif ($cours === []): ?>
    <p class="discret"><?= e(t('crt.aucun_cours')) ?>
      <a href="<?= url('cours/nouveau') ?>" data-fenetre><?= e(t('crt.en_creer_un')) ?></a>.</p>
  <?php else: ?>
    <?php
    /*
     * Le texte de ce qu'on coche part chez Google. Le dire avant le bouton, pas dans une page d'aide :
     * un CV, une convention de stage ou des notes privées ne devraient pas y partir par distraction.
     */
    ?>
    <p class="champ__aide ria-avertissement" role="note"><?= e(t('ria.confidentialite')) ?></p>

    <form method="post" action="<?= url('resumes/generer') ?>" class="fabrique__form"
          data-attente="<?= e(t('ria.en_cours')) ?>">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

      <fieldset class="sources choix-cours">
        <legend><?= e(t('ria.cours')) ?></legend>
        <?php $matiere = false; ?>
        <?php foreach ($cours as $c): ?>
          <?php if ($c['matiere_nom'] !== $matiere): ?>
            <?php $matiere = $c['matiere_nom']; ?>
            <p class="documents__place"><?= e($matiere ?? t('focus.sans_matiere')) ?></p>
          <?php endif; ?>
          <label class="sources__choix">
            <input type="checkbox" name="cours[]" value="<?= (int) $c['id'] ?>"
                   data-choix-cours <?= $choisi === (int) $c['id'] ? 'checked' : '' ?>>
            <span><?= e($c['titre']) ?></span>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <?= Vue::rendre('cartes/_sources', ['cours' => null]) ?>

      <?php foreach ($cours as $c): ?>
        <?= Vue::rendre('cartes/_documents', [
            'coursId'    => (int) $c['id'],
            'coursTitre' => (string) $c['titre'],
            'documents'  => $documentsParCours[(int) $c['id']] ?? [],
        ]) ?>
      <?php endforeach; ?>

      <fieldset class="sources">
        <legend><?= e(t('ria.genre')) ?></legend>
        <?php foreach ($genres as $rang => $genre): ?>
          <label class="sources__choix">
            <input type="checkbox" name="genres[]" value="<?= e($genre) ?>" <?= $rang === 0 ? 'checked' : '' ?>>
            <span><?= e(t('ria.genre.' . $genre)) ?>
              <span class="discret"><?= e(t('ria.genre.' . $genre . '_aide')) ?></span></span>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <div class="champ">
        <label for="longueur"><?= e(t('ria.longueur')) ?></label>
        <select id="longueur" name="longueur">
          <?php foreach ($longueurs as $longueur): ?>
            <option value="<?= e($longueur) ?>" <?= $longueur === 'moyen' ? 'selected' : '' ?>><?= e(t('ria.long.' . $longueur)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <button class="bouton" type="submit"><?= e(t('ria.generer')) ?></button>
    </form>
  <?php endif; ?>
</section>

<section class="carte">
  <h2><?= e(t('ria.historique')) ?></h2>
  <?php if ($historique === []): ?>
    <p class="discret"><?= e(t('ria.aucun')) ?></p>
  <?php else: ?>
    <ul class="ria-liste">
      <?php foreach ($historique as $r): ?>
        <li>
          <a href="<?= url('resumes/' . (int) $r['id']) ?>"><strong><?= e($r['titre']) ?></strong></a>
          <span class="discret">
            <?= e(t('ria.long.' . $r['longueur'])) ?> · <?= e(date_fr((string) $r['created_at'])) ?>
            <?php if ((int) $r['a_audio'] === 1): ?> · 🔊<?php endif; ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
