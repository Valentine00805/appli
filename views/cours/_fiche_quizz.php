<?php
/**
 * Le rayon « Quizz » d'une fiche de révision : des pages web (.html) qu'on joue sur place, dans un cadre.
 *
 * Le même fragment sert la fiche d'un cours et celle d'un dossier. Un quizz est un fichier .html déposé dans la fiche (les autres pièces jointes
 * restent dans « Fichiers et images »). Ses scripts s'exécutent, mais enfermés : le serveur lui donne une origine à part, sans aucun réseau (voir
 * Fichiers::envoyer) — le cadre n'a donc pas d'attribut « sandbox », qui le rendrait vide.
 *
 * @var array  $fichiersFiche   les fichiers de la fiche (on n'y prend que les pages web)
 * @var string $actionDepot     l'adresse qui reçoit les fichiers déposés
 * @var string $idDepot         de quoi donner un identifiant unique au champ de dépôt
 * @var string $surPlace        ' data-envoi-fenetre' dans une fenêtre
 * @var string $champPage       le champ caché qui dit où revenir après un retrait
 * @var bool   $dansUneFenetre
 */
$quizz = array_values(array_filter($fichiersFiche, static fn (array $f): bool => Fichiers::estPageWeb((string) $f['nom_origine'])));
?>
  <div class="fiche__rayon<?= $quizz === [] ? ' fiche__rayon--vide' : '' ?>" data-quizz>
    <h4 class="fiche__titre"><?= e(t('fiche.quizz')) ?></h4>

    <?php if ($quizz === []): ?>
      <p class="discret fiche__vide"><?= e(t('fiche.quizz_aucun')) ?></p>
    <?php else: ?>
      <?php foreach ($quizz as $q): ?>
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
          <iframe class="quizz__cadre" src="<?= url('fichiers/' . (int) $q['id'], ['jouer' => 1]) ?>" referrerpolicy="no-referrer" loading="lazy"
                  title="<?= e((string) $q['nom_origine']) ?>"></iframe>
        </div>
      <?php endforeach; ?>
      <p class="champ__aide"><?= e(t('fiche.quizz_aide')) ?></p>
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
