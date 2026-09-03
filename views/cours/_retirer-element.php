<?php
/**
 * Le bouton qui retire un élément d'une fiche de révision.
 * @var array $element
 */
?>
<form method="post" action="<?= url('revision/' . (int) $element['id'] . '/supprimer') ?>" class="en-ligne"
      data-confirmation="Retirer cet élément de la fiche ?">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <button class="bouton bouton--discret bouton--petit" type="submit" title="Retirer de la fiche">✕</button>
</form>
