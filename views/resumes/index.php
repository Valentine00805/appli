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
 * @var ?array $aOuvrir          le résumé qui vient d'être écrit, à ouvrir aussitôt
 * @var ?array $carteAOuvrir     la carte mentale qui vient d'être écrite ou créée, à ouvrir aussitôt
 * @var array $cartesMentales    ses cartes mentales (elles se retrouvent aussi dans la fiche de chaque cours)
 */
// La carte mentale n'est pas un résumé : elle se range dans la fiche du cours, mais se demande ici, avec les autres.
$genres = [...ResumeIa::GENRES, 'carte'];
$longueurs = ['court', 'moyen', 'long'];
$pret = $cleConfiguree && $cleFin !== null;
?>

<div class="entete-page">
  <div>
    <h1><?= e(t('ria.titre')) ?></h1>
    <p><?= e(t('ria.sous_titre')) ?></p>
  </div>
</div>

<?php if ($aOuvrir !== null): ?>
  <?php // Le script clique ici tout seul : le résumé s'ouvre en fenêtre. Sans script, le lien reste, à cliquer. ?>
  <p class="carte"><a href="<?= url('resumes/' . (int) $aOuvrir['id']) ?>" data-fenetre data-ouvrir-auto>
    <?= e(t('ria.ouvrir', ['titre' => (string) $aOuvrir['titre']])) ?></a></p>
<?php endif; ?>

<?php if ($carteAOuvrir !== null): ?>
  <?php // Comme le résumé : le script clique ici tout seul, et la carte s'ouvre en fenêtre. ?>
  <p class="carte"><a href="<?= url('cartes-mentales/' . (int) $carteAOuvrir['id']) ?>" data-fenetre data-ouvrir-auto>
    <?= e(t('cm.ouvrir', ['titre' => (string) $carteAOuvrir['titre']])) ?></a></p>
<?php endif; ?>

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
          data-attente="<?= e(t('ria.en_cours')) ?>" data-attente-audio="<?= e(t('ria.en_cours_audio')) ?>">
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
        <?php // L'audio ne remplace rien : il s'ajoute à ce qui est coché (ou à un résumé simple si rien ne l'est). ?>
        <label class="sources__choix">
          <input type="checkbox" name="audio" value="1">
          <span><?= e(t('ria.audio_choix')) ?>
            <span class="discret"><?= e(t('ria.audio_choix_aide')) ?></span></span>
        </label>
      </fieldset>

      <?php // Le choix de la voix n'a de sens qu'avec l'audio : le script ne le montre que si la case est cochée. ?>
      <div class="champ" data-voix-si-audio>
        <label for="voix_lot"><?= e(t('ria.voix')) ?></label>
        <select id="voix_lot" name="voix">
          <?php foreach (Gemini::VOIX as $voix): ?>
            <option value="<?= e($voix) ?>"><?= e($voix) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

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

<?php
/*
 * Une carte mentale à remplir soi-même : pas de clé, pas d'IA. Elle part de l'idée centrale (le titre du cours) et se
 * range, comme les autres, dans la fiche de révision de ce cours.
 */
?>
<?php if ($cours !== []): ?>
  <section class="carte">
    <h2><?= e(t('cm.vierge_titre')) ?></h2>
    <p class="champ__aide"><?= e(t('cm.vierge_aide')) ?></p>
    <form method="post" action="<?= url('cartes-mentales') ?>" class="fabrique__form">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <div class="champ">
        <label for="cours_vierge"><?= e(t('ria.cours')) ?></label>
        <select id="cours_vierge" name="cours">
          <?php foreach ($cours as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $choisi === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['titre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="bouton bouton--secondaire" type="submit"><?= e(t('cm.nouvelle')) ?></button>
    </form>
  </section>
<?php endif; ?>

<section class="carte">
  <h2><?= e(t('cm.titre_liste')) ?></h2>
  <?php if ($cartesMentales === []): ?>
    <p class="discret"><?= e(t('cm.aucune_liste')) ?></p>
  <?php else: ?>
    <ul class="ria-liste">
      <?php foreach ($cartesMentales as $cm): ?>
        <li>
          <a href="<?= url('cartes-mentales/' . (int) $cm['id']) ?>" data-fenetre><strong><?= e($cm['titre']) ?></strong></a>
          <span class="discret">
            — <a href="<?= url('revision/' . (int) $cm['cours_id']) ?>"><?= e($cm['cours_titre']) ?></a>
            · <?= e(date_fr((string) $cm['updated_at'])) ?>
            <?php if ((int) $cm['ia'] === 1): ?> · <?= e(t('cm.ia_badge')) ?><?php endif; ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
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
          <a href="<?= url('resumes/' . (int) $r['id']) ?>" data-fenetre><strong><?= e($r['titre']) ?></strong></a>
          <span class="discret">
            <?= e(t('ria.long.' . $r['longueur'])) ?> · <?= e(date_fr((string) $r['created_at'])) ?>
            <?php if ((int) $r['a_audio'] === 1): ?> · 🔊<?php endif; ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
