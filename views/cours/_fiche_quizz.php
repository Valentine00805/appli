<?php
/**
 * Le rayon « Quizz » d'une fiche de révision : des pages web (.html) qu'on joue sur place, dans un cadre.
 *
 * Le même fragment sert la fiche d'un cours et celle d'un dossier. Un quizz est un fichier .html déposé dans la fiche (les autres pièces jointes
 * restent dans « Fichiers et images »). Ses scripts s'exécutent, mais enfermés : le serveur lui donne une origine à part, sans aucun réseau (voir
 * Fichiers::envoyer) — le cadre n'a donc pas d'attribut « sandbox », qui le rendrait vide.
 *
 * Le quizz ne peut parler qu'à la page qui le contient, par « postMessage » : il signale les questions faites, les bonnes réponses et le total de la
 * série (voir Quizz). Le script de la page peint alors deux anneaux — l'avancement et le score — et prévient le serveur. Les nombres affichés ici
 * sont ceux du dernier état gardé.
 *
 * @var array  $fichiersFiche   les fichiers de la fiche (on n'y prend que les pages web)
 * @var string $actionDepot     l'adresse qui reçoit les fichiers déposés
 * @var string $idDepot         de quoi donner un identifiant unique au champ de dépôt
 * @var string $surPlace        ' data-envoi-fenetre' dans une fenêtre
 * @var string $champPage       le champ caché qui dit où revenir après un retrait
 * @var bool   $dansUneFenetre
 */
$quizz = array_values(array_filter($fichiersFiche, static fn (array $f): bool => Fichiers::estPageWeb((string) $f['nom_origine'])));
$mesurable = Quizz::disponible();
?>
  <div class="fiche__rayon<?= $quizz === [] ? ' fiche__rayon--vide' : '' ?>" data-quizz>
    <h4 class="fiche__titre"><?= e(t('fiche.quizz')) ?></h4>

    <?php if ($quizz === []): ?>
      <p class="discret fiche__vide"><?= e(t('fiche.quizz_aucun')) ?></p>
    <?php else: ?>
      <?php foreach ($quizz as $q): ?>
        <?php $mesure = $mesurable ? Quizz::mesure($q) : null; ?>
        <div class="quizz">
          <div class="quizz__entete">
            <span class="fichier__icone" aria-hidden="true">🌐</span>
            <strong class="quizz__nom"><?= e((string) $q['nom_origine']) ?></strong>
            <span class="fichier__actions">
              <a class="bouton bouton--discret bouton--petit" href="<?= url('fichiers/' . (int) $q['id'], ['jouer' => 1]) ?>"
                 target="_blank" rel="noopener" title="<?= e(t('fiche.quizz_grand_aide')) ?>">↗ <?= e(t('fiche.quizz_grand')) ?></a>
              <?= Vue::rendre('cours/_telecharger', ['fichier' => $q, 'compact' => true]) ?>
              <form<?= $surPlace ?> method="post" action="<?= url('fichiers/' . (int) $q['id'] . '/supprimer') ?>" class="en-ligne"
                    data-confirmation="<?= e(t('fiche.retirer_fichier_sur')) ?>">
                <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>"><?= $champPage ?>
                <button class="bouton bouton--discret bouton--petit" type="submit" title="<?= e(t('commun.retirer')) ?>">✕</button>
              </form>
            </span>
          </div>

          <?php if ($mesurable): ?>
            <?php
            /*
             * Deux mesures, un anneau chacune : l'avancement (questions faites / total) et le score (bonnes réponses / total). Le score est celui qui
             * rejoint l'anneau de toute la fiche, comme un enregistrement écouté ou un PDF lu : il porte donc « data-avancement » dès que le quizz
             * a signalé quelque chose (le script le pose au premier message).
             */
            ?>
            <div class="quizz__mesures" data-quizz-mesures="<?= (int) $q['id'] ?>" data-quizz-url="<?= url('fichiers/' . (int) $q['id'] . '/quizz') ?>"
                 data-modele-fait="<?= e(t('fiche.quizz_fait', ['fait' => '{fait}', 'total' => '{total}'])) ?>"
                 data-modele-bonnes="<?= e(t('fiche.quizz_bonnes', ['bonnes' => '{bonnes}'])) ?>">
              <span class="quizz__mesure" data-quizz-avancement>
                <?= Vue::rendre('cours/_anneau', ['pourcentage' => $mesure['avancement'] ?? null, 'titre' => t('fiche.quizz_avancement')]) ?>
                <span class="quizz__mesure-texte">
                  <strong><?= e(t('fiche.quizz_avancement')) ?></strong><br>
                  <span class="discret" data-quizz-texte-fait><?= $mesure === null ? e(t('fiche.quizz_pas_joue')) : e(t('fiche.quizz_fait', ['fait' => $mesure['fait'], 'total' => $mesure['total']])) ?></span>
                </span>
              </span>
              <span class="quizz__mesure" data-quizz-score<?= $mesure !== null ? ' data-avancement="quizz-' . (int) $q['id'] . '"' : '' ?>>
                <?= Vue::rendre('cours/_anneau', ['pourcentage' => $mesure['score'] ?? null, 'titre' => t('fiche.quizz_score')]) ?>
                <span class="quizz__mesure-texte">
                  <strong><?= e(t('fiche.quizz_score')) ?></strong><br>
                  <span class="discret" data-quizz-texte-bonnes><?= $mesure === null ? '–' : e(t('fiche.quizz_bonnes', ['bonnes' => $mesure['bonnes']])) ?></span>
                </span>
              </span>
            </div>
          <?php endif; ?>

          <iframe class="quizz__cadre" src="<?= url('fichiers/' . (int) $q['id'], ['jouer' => 1]) ?>" referrerpolicy="no-referrer" loading="lazy"
                  title="<?= e((string) $q['nom_origine']) ?>"></iframe>
        </div>
      <?php endforeach; ?>
      <p class="champ__aide"><?= e(t('fiche.quizz_aide')) ?></p>
    <?php endif; ?>

    <?php if ($mesurable): ?>
      <details class="fiche__ajout">
        <summary><?= e(t('fiche.quizz_signal_titre')) ?></summary>
        <p class="champ__aide"><?= e(t('fiche.quizz_signal_aide')) ?></p>
        <?php // Le code est le même dans toutes les langues : c'est celui qu'on colle dans le fichier .html du quizz. ?>
        <pre class="quizz__code" tabindex="0"><code>function signaler(fait, bonnes, total) {
  // fait = questions répondues · bonnes = réponses justes · total = questions de la série
  try { parent.postMessage({ quizz: true, fait: fait, bonnes: bonnes, total: total }, "*"); } catch (e) {}
}</code></pre>
      </details>
    <?php endif; ?>

    <form<?= $surPlace ?> method="post" action="<?= $actionDepot ?>" enctype="multipart/form-data" class="depot depot--mince" data-depot>
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>"><?= $champPage ?>
      <label class="depot__zone" for="depot-quizz-<?= e($idDepot) ?>">
        <span class="depot__icone" aria-hidden="true">🎯</span>
        <span><strong><?= e(t('fiche.deposez')) ?></strong>
          <span class="discret"><?= e(t('fiche.quizz_deposez_aide')) ?></span></span>
      </label>
      <input type="file" id="depot-quizz-<?= e($idDepot) ?>" name="fichiers[]" multiple accept=".html,.htm,text/html"
             class="depot__champ" data-depot-champ>
      <button class="bouton bouton--petit bouton--bloc" type="submit" data-depot-envoi><?= e(t('fiche.quizz_ajouter')) ?></button>
    </form>
  </div>
