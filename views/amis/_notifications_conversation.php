<?php
/**
 * Recevoir, ou non, les notifications d'une conversation — à deux ou en groupe.
 * La case s'enregistre d'elle-même, sans recharger la page ni la fenêtre ;
 * les boutons « 1 heure », « 1 jour »… coupent pour ce temps-là seulement.
 *
 * @var string $action  l'adresse où l'envoyer
 * @var bool $muette  les notifications sont-elles coupées ?
 * @var string|null|false $coupure  la fin de la coupure (UTC), null si elle n'en a pas, false sans coupure
 * @var string $laquelle  « ce groupe », « cette conversation »
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$coupure = $coupure ?? ($muette ? null : false);
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
        <span class="discret" data-notifications-etat><?= e($muette
            ? FileNotifications::texteCoupure($coupure === false ? null : $coupure)
            : 'Un nouveau message ou une réaction à l’un des vôtres vous prévient.') ?></span>
      </span>
    </label>
    <?php // Couper pour un temps : les notifications reviennent d'elles-mêmes ensuite. ?>
    <p class="notifications-pendant">
      <span class="discret">Couper pendant</span>
      <?php foreach (FileNotifications::DUREES as $cle => $duree): ?>
        <button class="pastille notifications-pendant__choix" type="submit" name="pendant" value="<?= e($cle) ?>"><?= e($duree['nom']) ?></button>
      <?php endforeach; ?>
    </p>
    <noscript><button class="bouton bouton--petit" type="submit">Enregistrer</button></noscript>
    <p class="champ__aide" data-notifications-message aria-live="polite" style="margin:.4rem 0 0"></p>
  </form>
  <p class="champ__aide" style="margin-bottom:0">
    Pour toutes les conversations à la fois : <a href="<?= url('notifications') ?>"<?= $dansUneFenetre ? ' data-fenetre-dessus' : '' ?>>Régler les notifications</a>.
  </p>
</section>
