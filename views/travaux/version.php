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
    <p style="margin:0 0 .3rem"><a href="<?= url('travaux/' . (int) $version['projet_id'] . '/document') ?>"<?= $lien ?>>← Le document actuel</a></p>
    <h1>📝 Version du <?= e(date_fr((string) $version['created_at'])) ?></h1>
    <p class="discret"><?= e((string) ($projet['nom'] ?? '')) ?> · écrite par <?= e((string) ($version['auteur'] ?? 'un ancien membre')) ?></p>
  </div>
  <form method="post"<?= $envoi ?> action="<?= url('travaux/versions/' . (int) $version['id'] . '/restaurer') ?>"
        data-confirmation="Revenir à cette version ? Le document actuel restera dans l’historique.">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <button class="bouton" type="submit">↺ Restaurer cette version</button>
  </form>
</div>

<article class="carte texte-riche-affiche" style="max-width:52rem"><?= TexteRiche::versHtml((string) $version['contenu']) ?></article>
