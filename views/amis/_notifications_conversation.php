<?php
/**
 * Recevoir, ou non, les notifications d'une conversation — à deux ou en groupe.
 * La case s'enregistre d'elle-même, sans recharger la page ni la fenêtre.
 *
 * @var string $action  l'adresse où l'envoyer
 * @var bool $muette  les notifications sont-elles coupées ?
 * @var string $laquelle  « ce groupe », « cette conversation »
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
?>
<section class="carte profil-ami__section" id="notifications-conversation">
  <h2 style="margin-top:0"><span data-notifications-icone><?= $muette ? '🔕' : '🔔' ?></span> Notifications</h2>
  <?php // Le script l'enregistre sans recharger : les réglages autour restent tels quels. ?>
  <form method="post" action="<?= e($action) ?>" data-notifications-conversation>
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <?php // Décochée, la case n'envoie rien : le champ caché dit alors « non ». ?>
    <input type="hidden" name="recevoir" value="0">
    <label class="notifications-choix__ligne">
      <input type="checkbox" name="recevoir" value="1"<?= $muette ? '' : ' checked' ?>>
      <span>
        <strong>Recevoir les notifications de <?= e($laquelle) ?></strong><br>
        <span class="discret" data-notifications-etat><?= $muette
            ? 'Coupées : les messages et les réactions arrivent sans vous prévenir.'
            : 'Un nouveau message ou une réaction à l’un des vôtres vous prévient.' ?></span>
      </span>
    </label>
    <noscript><button class="bouton bouton--petit" type="submit" style="margin-top:.6rem">Enregistrer</button></noscript>
    <p class="champ__aide" data-notifications-message aria-live="polite" style="margin:.4rem 0 0"></p>
  </form>
  <p class="champ__aide" style="margin-bottom:0">
    Pour toutes les conversations à la fois : <a href="<?= url('notifications') ?>"<?= $dansUneFenetre ? ' data-fenetre-dessus' : '' ?>>Régler les notifications</a>.
  </p>
</section>
