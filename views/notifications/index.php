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
 * @var array<string, ?string> $coupures  les sortes coupées, et la fin de chacune (UTC ; null : sans fin)
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
      <p class="champ__aide" style="margin-bottom:0">
        Un navigateur reçoit les notifications d’un seul compte : celui qui les a activées
        le dernier. Pour deux comptes, activez-les dans deux navigateurs (ou profils) différents.
      </p>
    </section>

    <?php
    /*
     * Ce que je reçois : une case par sorte de notification, toutes cochées au
     * départ. Ce qui est décoché ne part plus, ni vers cet appareil ni vers
     * les autres — sans fin, ou pour le temps choisi dessous (le menu ne paraît
     * que pour une case décochée). Un rappel passé pendant ce temps ne revient pas après.
     */
    ?>
    <section class="carte">
      <h2 style="margin-top:0">Ce que je reçois</h2>
      <form method="post" action="<?= url('notifications/choix') ?>"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <p style="margin:0 0 .6rem">
          <button class="bouton bouton--discret bouton--petit" type="button"
                  data-cocher-tout="[data-liste-notifications]">Tout cocher, ou décocher</button>
        </p>
        <ul class="notifications-choix" data-liste-notifications>
          <?php foreach (FileNotifications::CATEGORIES as $cle => $categorie): ?>
            <?php $coupee = array_key_exists($cle, $coupures); $fin = $coupures[$cle] ?? null; ?>
            <li>
              <label class="notifications-choix__ligne">
                <input type="checkbox" name="recevoir[]" value="<?= e($cle) ?>"<?= $coupee ? '' : ' checked' ?>>
                <span aria-hidden="true"><?= $categorie['icone'] ?></span>
                <span>
                  <strong><?= e($categorie['nom']) ?></strong><br>
                  <span class="discret"><?= e($categorie['aide']) ?></span>
                  <?php if ($coupee): ?>
                    <br><span class="notifications-choix__coupure">🔕 <?= e(FileNotifications::texteCoupure($fin)) ?></span>
                  <?php endif; ?>
                </span>
              </label>
              <?php // Décochée, pour combien de temps : ensuite, elle revient d'elle-même. ?>
              <label class="notifications-choix__duree">
                <span class="discret">Coupée :</span>
                <select name="duree[<?= e($cle) ?>]">
                  <?php if ($coupee && $fin !== null): ?>
                    <option value="garder" selected>comme maintenant</option>
                  <?php endif; ?>
                  <option value="toujours"<?= $coupee && $fin === null ? ' selected' : '' ?>>jusqu’à ce que je la recoche</option>
                  <?php foreach (FileNotifications::DUREES as $d => $duree): ?>
                    <option value="<?= e($d) ?>">pendant <?= e($duree['nom']) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </li>
          <?php endforeach; ?>
        </ul>
        <button class="bouton" type="submit" style="margin-top:.8rem">Enregistrer mon choix</button>
        <p class="champ__aide" style="margin-bottom:0">
          Les rappels du calendrier partent 15 minutes avant par défaut ; chaque évènement règle les siens
          dans son formulaire. Un évènement « toute la journée » et une tâche sonnent à 8 h.
        </p>
      </form>
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
