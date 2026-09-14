<?php
/**
 * Les notifications de rappel : activer cet appareil, l'essayer, voir les autres.
 *
 * Le script de la page fait tout le travail avec le navigateur — permission,
 * abonnement, service worker. Sans lui, la page ne peut que le dire : une
 * notification ne s'active pas par un simple formulaire.
 *
 * @var list<array> $appareils
 * @var string $clePublique  la clé VAPID de l'application, en base64url
 * @var string $adresseEnvoi l'adresse que la tâche planifiée appelle chaque minute
 * @var bool $dansUneFenetre  rendue seule, pour être posée dans une fenêtre
 */
$csrf = Session::jetonCsrf();
$dansUneFenetre = $dansUneFenetre ?? false;
?>

<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <?php // Dans une fenêtre, « Mon compte » est juste derrière : la croix y ramène. ?>
    <?php if (!$dansUneFenetre): ?>
      <p class="discret" style="margin-bottom:.35rem"><a href="<?= url('compte') ?>">← Mon compte</a></p>
    <?php endif; ?>
    <h1>🔔 Notifications</h1>
    <p>Des rappels avant vos évènements et le matin de vos échéances — même application fermée.</p>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <section class="carte notifications"
             data-notifications
             data-cle-publique="<?= e($clePublique) ?>"
             data-service-worker="<?= e(url('service-worker.js')) ?>"
             data-portee="<?= e(url('')) ?>"
             data-abonner="<?= e(url('notifications/abonnement')) ?>"
             data-desabonner="<?= e(url('notifications/desabonnement')) ?>"
             data-essai="<?= e(url('notifications/essai')) ?>"
             data-jeton="<?= e($csrf) ?>">
      <h2 style="margin-top:0">Cet appareil</h2>
      <p class="notifications__etat" data-notifications-etat role="status">
        <noscript>Les notifications ont besoin de JavaScript pour être activées.</noscript>
        Vérification…
      </p>
      <p class="actions">
        <button class="bouton" type="button" data-notifications-activer hidden>Activer les notifications</button>
        <button class="bouton bouton--secondaire" type="button" data-notifications-essai hidden>Envoyer une notification d’essai</button>
        <button class="bouton bouton--discret" type="button" data-notifications-desactiver hidden>Désactiver sur cet appareil</button>
      </p>
      <p class="champ__aide" data-notifications-aide hidden></p>
    </section>

    <section class="carte">
      <h2 style="margin-top:0">Ce qui vous est rappelé</h2>
      <ul class="notifications__liste">
        <li><strong>Les évènements</strong> — ceux de l’application comme ceux d’Outlook et de Google —,
          15 minutes avant par défaut. Chaque évènement a son choix « Rappel » dans son formulaire.</li>
        <li><strong>Un évènement « toute la journée »</strong> : à 8 h le jour même, ou les jours d’avant
          pour un rappel d’un jour ou plus.</li>
        <li><strong>Les échéances de tâches</strong> : à 8 h le jour de l’échéance, pour une sous-tâche pas
          encore faite ou une tâche principale qui en a encore.</li>
      </ul>
    </section>
  </div>

  <div class="pile">
    <section class="carte">
      <h2 style="margin-top:0">Appareils abonnés</h2>
      <?php if ($appareils === []): ?>
        <p class="discret">Aucun pour l’instant. Activez les notifications sur chaque appareil où vous voulez les recevoir.</p>
      <?php else: ?>
        <ul class="liste-fichiers">
          <?php foreach ($appareils as $a): ?>
            <li class="fichier">
              <span class="fichier__icone" aria-hidden="true">📱</span>
              <span style="min-width:0">
                <span class="fichier__nom"><?= e((string) ($a['appareil'] ?: 'Appareil')) ?></span><br>
                <span class="fichier__meta">
                  Abonné le <?= e(date_fr((string) $a['created_at'], false)) ?>
                  <?= $a['dernier_envoi'] !== null ? '· dernier rappel le ' . e(date_fr((string) $a['dernier_envoi'])) : '' ?>
                </span>
              </span>
              <span class="fichier__actions">
                <form method="post" action="<?= url('notifications/desabonnement') ?>" class="en-ligne"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>
                      data-confirmation="Retirer cet appareil ? Il ne recevra plus de rappels.">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                  <button class="bouton bouton--discret bouton--petit" type="submit" title="Retirer">✕</button>
                </form>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="carte">
      <h2 style="margin-top:0">Rappels application fermée</h2>
      <p class="discret" style="margin-top:0">
        Pour que les rappels partent même quand aucun onglet n’est ouvert, cette adresse doit être
        appelée chaque minute — par une tâche planifiée sur l’ordinateur qui fait tourner
        l’application, ou par une tâche « cron » chez l’hébergeur une fois en ligne.
        Tant qu’un onglet est ouvert, la page s’en charge elle-même.
      </p>
      <label class="legende" for="adresse-envoi">Adresse d’envoi (gardez-la pour vous)</label>
      <input type="text" id="adresse-envoi" readonly value="<?= e($adresseEnvoi) ?>" onclick="this.select()">
    </section>
  </div>
</div>
