<?php
/**
 * Une version précédente du document commun, à relire ou à restaurer.
 *
 * @var array $version
 * @var ?array $projet
 */
$dansUneFenetre = $dansUneFenetre ?? false;
// Dans la fenêtre, on y reste : les formulaires s'y enregistrent, les liens s'y ouvrent.
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$lien = $dansUneFenetre ? ' data-fenetre' : '';
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-document' : '' ?>>
  <div>
    <?php // Le retour au document : une pastille, avec sa flèche dans un rond, plutôt qu'un lien souligné. ?>
    <p style="margin:0 0 .5rem">
      <a class="retour-pastille" href="<?= url('travaux/' . (int) $version['projet_id'] . '/document') ?>"<?= $lien ?>>
        <span class="retour-pastille__fleche" aria-hidden="true">←</span>
        <span><?= e(t('tr.ve.retour')) ?></span>
      </a>
    </p>
    <h1><?= e(t('tr.ve.titre', ['date' => date_fr((string) $version['created_at'])])) ?></h1>
    <p class="discret"><?= e(t('tr.ve.ecrite_par', [
        'projet' => (string) ($projet['nom'] ?? ''),
        'qui' => (string) ($version['auteur'] ?? t('tr.ancien_membre')),
    ])) ?></p>
  </div>
  <form method="post"<?= $envoi ?> action="<?= url('travaux/versions/' . (int) $version['id'] . '/restaurer') ?>"
        data-confirmation="<?= e(t('tr.ve.restaurer_confirmation')) ?>">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <button class="bouton" type="submit"><?= e(t('tr.ve.restaurer')) ?></button>
  </form>
</div>

<article class="carte texte-riche-affiche" style="max-width:52rem"><?= TexteRiche::versHtml((string) $version['contenu']) ?></article>
