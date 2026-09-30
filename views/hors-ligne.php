<?php
/**
 * La page qui s'affiche sans réseau, pour une page jamais ouverte.
 *
 * Elle est gardée en cache dès l'installation : c'est la seule qui répond à
 * coup sûr. Elle ne dit donc rien qu'elle ne puisse tenir — pas de données,
 * pas de session —, et propose ce qui, lui, est probablement gardé.
 */
?>
<div class="carte" style="max-width:560px;margin:2rem auto;text-align:center">
  <p style="font-size:2.5rem;margin:0">🔌</p>
  <h1 style="margin-top:.4rem"><?= e(t('horsligne.titre')) ?></h1>
  <p><?= e(t('horsligne.texte')) ?></p>
  <p class="discret"><?= e(t('horsligne.aide')) ?></p>

  <p class="actions" style="justify-content:center">
    <button class="bouton" type="button" onclick="location.reload()"><?= e(t('horsligne.reessayer')) ?></button>
    <a class="bouton bouton--secondaire" href="<?= url('') ?>"><?= e(t('horsligne.accueil')) ?></a>
  </p>

  <ul style="list-style:none;padding:0;margin:1.2rem 0 0;display:flex;gap:.5rem;flex-wrap:wrap;justify-content:center">
    <li><a class="pastille" href="<?= url('calendrier') ?>"><?= e(t('horsligne.calendrier')) ?></a></li>
    <li><a class="pastille" href="<?= url('cours') ?>"><?= e(t('horsligne.cours')) ?></a></li>
    <li><a class="pastille" href="<?= url('taches') ?>"><?= e(t('horsligne.taches')) ?></a></li>
    <li><a class="pastille" href="<?= url('alternance') ?>"><?= e(t('horsligne.alternance')) ?></a></li>
  </ul>
</div>
