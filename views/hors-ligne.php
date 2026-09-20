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
  <h1 style="margin-top:.4rem">Pas de réseau</h1>
  <p>Cette page n’a pas encore été ouverte sur cet appareil : il n’y en a pas de copie ici.</p>
  <p class="discret">Ce que vous avez déjà consulté reste lisible, et ce que vous écrivez
    est gardé puis envoyé dès que la connexion revient.</p>

  <p class="actions" style="justify-content:center">
    <button class="bouton" type="button" onclick="location.reload()">Réessayer</button>
    <a class="bouton bouton--secondaire" href="<?= url('') ?>">Aller à l’accueil</a>
  </p>

  <ul style="list-style:none;padding:0;margin:1.2rem 0 0;display:flex;gap:.5rem;flex-wrap:wrap;justify-content:center">
    <li><a class="pastille" href="<?= url('calendrier') ?>">📅 Calendrier</a></li>
    <li><a class="pastille" href="<?= url('cours') ?>">📘 Mes cours</a></li>
    <li><a class="pastille" href="<?= url('taches') ?>">✅ Tâches</a></li>
    <li><a class="pastille" href="<?= url('alternance') ?>">🏢 Alternance</a></li>
  </ul>
</div>
