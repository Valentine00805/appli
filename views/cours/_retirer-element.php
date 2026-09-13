<?php
/**
 * Le bouton qui retire un élément d'une fiche de révision.
 * @var array $element
 * @var bool $surPage  la fiche est-elle affichée seule, hors du cours ?
 */
$surPage = $surPage ?? false;
// Dans une fenêtre, le retrait s'y enregistre.
$surPlace = ($dansUneFenetre ?? false) ? ' data-envoi-fenetre' : '';
?>
<form<?= $surPlace ?> method="post" action="<?= url('revision/element/' . (int) $element['id'] . '/supprimer') ?>" class="en-ligne"
      data-confirmation="Retirer cet élément de la fiche ?">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <?php if ($surPage): ?><input type="hidden" name="page" value="fiche"><?php endif; ?>
  <button class="bouton bouton--discret bouton--petit" type="submit" title="Retirer de la fiche">✕</button>
</form>
