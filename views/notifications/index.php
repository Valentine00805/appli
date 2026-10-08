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
 * @var ?string $adresseEnvoi l'adresse que la tâche planifiée appelle chaque minute ; null pour qui n'administre pas le site
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
      <p class="discret" style="margin-bottom:.35rem"><a href="<?= url('compte') ?>"><?= e(t('notif.retour_compte')) ?></a></p>
    <?php endif; ?>
    <h1><?= e(t('notif.titre')) ?></h1>
    <p><?= e(t('notif.sous_titre')) ?></p>
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
      <h2 style="margin-top:0"><?= e(t('notif.cet_appareil')) ?></h2>
      <p class="notifications__etat" data-notifications-etat role="status">
        <noscript><?= e(t('notif.js_requis')) ?></noscript>
        <?= e(t('cpt.verification')) ?>
      </p>
      <p class="actions">
        <button class="bouton" type="button" data-notifications-activer hidden><?= e(t('notif.activer')) ?></button>
        <button class="bouton bouton--secondaire" type="button" data-notifications-essai hidden><?= e(t('notif.essai')) ?></button>
        <button class="bouton bouton--discret" type="button" data-notifications-desactiver hidden><?= e(t('notif.desactiver')) ?></button>
      </p>
      <p class="champ__aide" data-notifications-aide hidden></p>
      <p class="champ__aide" style="margin-bottom:0">
        <?= e(t('notif.un_compte_aide')) ?>
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
      <h2 style="margin-top:0"><?= e(t('notif.ce_que_je_recois')) ?></h2>
      <form method="post" action="<?= url('notifications/choix') ?>"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <p style="margin:0 0 .6rem">
          <button class="bouton bouton--discret bouton--petit" type="button"
                  data-cocher-tout="[data-liste-notifications]"><?= e(t('focus.tout_cocher')) ?></button>
        </p>
        <ul class="notifications-choix" data-liste-notifications>
          <?php foreach (FileNotifications::CATEGORIES as $cle => $categorie): ?>
            <?php $coupee = array_key_exists($cle, $coupures); $fin = $coupures[$cle] ?? null; ?>
            <li>
              <label class="notifications-choix__ligne">
                <input type="checkbox" name="recevoir[]" value="<?= e($cle) ?>"<?= $coupee ? '' : ' checked' ?>>
                <span aria-hidden="true"><?= $categorie['icone'] ?></span>
                <span>
                  <strong><?= e(FileNotifications::nomCategorie((string) $cle)) ?></strong><br>
                  <span class="discret"><?= e(FileNotifications::aideCategorie((string) $cle)) ?></span>
                  <?php if ($coupee): ?>
                    <br><span class="notifications-choix__coupure">🔕 <?= e(FileNotifications::texteCoupure($fin)) ?></span>
                  <?php endif; ?>
                </span>
              </label>
              <?php // Décochée, pour combien de temps : ensuite, elle revient d'elle-même. ?>
              <label class="notifications-choix__duree">
                <span class="discret"><?= e(t('notif.coupee_label')) ?></span>
                <select name="duree[<?= e($cle) ?>]">
                  <?php if ($coupee && $fin !== null): ?>
                    <option value="garder" selected><?= e(t('notif.comme_maintenant')) ?></option>
                  <?php endif; ?>
                  <option value="toujours"<?= $coupee && $fin === null ? ' selected' : '' ?>><?= e(t('notif.jusqua_recoche')) ?></option>
                  <?php foreach (array_keys(FileNotifications::DUREES) as $d): ?>
                    <option value="<?= e($d) ?>"><?= e(t('notif.pendant', ['duree' => FileNotifications::nomDuree((string) $d)])) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </li>
          <?php endforeach; ?>
        </ul>
        <button class="bouton" type="submit" style="margin-top:.8rem"><?= e(t('notif.enregistrer_choix')) ?></button>
        <p class="champ__aide" style="margin-bottom:0">
          <?= e(t('notif.rappels_aide')) ?>
        </p>
      </form>
    </section>
  </div>

  <div class="pile">
    <section class="carte">
      <h2 style="margin-top:0"><?= e(t('notif.appareils')) ?></h2>
      <?php if ($appareils === []): ?>
        <p class="discret"><?= e(t('notif.appareils_aucun')) ?></p>
      <?php else: ?>
        <ul class="liste-fichiers">
          <?php foreach ($appareils as $a): ?>
            <li class="fichier">
              <span class="fichier__icone" aria-hidden="true">📱</span>
              <span style="min-width:0">
                <span class="fichier__nom"><?= e((string) ($a['appareil'] ?: t('notif.appareil'))) ?></span><br>
                <span class="fichier__meta">
                  <?= e(t('notif.abonne_le', ['date' => date_fr((string) $a['created_at'], false)])) ?>
                  <?= $a['dernier_envoi'] !== null ? e(t('notif.dernier_rappel', ['date' => date_fr((string) $a['dernier_envoi'])])) : '' ?>
                </span>
              </span>
              <span class="fichier__actions">
                <form method="post" action="<?= url('notifications/desabonnement') ?>" class="en-ligne"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>
                      data-confirmation="<?= e(t('notif.retirer_sur')) ?>">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                  <button class="bouton bouton--discret bouton--petit" type="submit" title="<?= e(t('commun.retirer')) ?>">✕</button>
                </form>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <?php if ($adresseEnvoi !== null): ?>
    <section class="carte">
      <h2 style="margin-top:0"><?= e(t('notif.appli_fermee')) ?></h2>
      <p class="discret" style="margin-top:0">
        <?= e(t('notif.appli_fermee_aide')) ?>
      </p>
      <label class="legende" for="adresse-envoi"><?= e(t('notif.adresse_envoi')) ?></label>
      <input type="text" id="adresse-envoi" readonly value="<?= e($adresseEnvoi) ?>" onclick="this.select()">
    </section>
    <?php endif; ?>
  </div>
</div>
