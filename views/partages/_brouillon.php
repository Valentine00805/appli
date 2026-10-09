<?php
/**
 * Ce qu'on avait tapé quand quelqu'un a enregistré le même texte avant nous : gardé à côté de l'éditeur, pour reprendre ce qui manque.
 *
 * @var ?string $brouillon  le texte tel que l'éditeur l'a envoyé (voir Partages::garderBrouillon), ou null
 */
if (!is_string($brouillon ?? null) || trim($brouillon) === '') {
    return;
}
?>
<section class="carte travaux-brouillon" style="margin-top:1rem">
  <h2 style="margin-top:0"><?= e(t('tr.do.non_enregistree')) ?></h2>
  <p class="discret"><?= e(t('tr.do.quelquun_avant')) ?></p>
  <div class="texte-riche-affiche"><?= TexteRiche::versHtml(TexteRiche::depuisFormulaire($brouillon)) ?></div>
</section>
