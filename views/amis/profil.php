<?php
/**
 * Le profil d'un ami, ouvert depuis la discussion : ce que vous avez échangé
 * (photos, fichiers) et, tout en bas, de quoi le retirer de vos amis.
 *
 * Retirer, comme bloquer, demande une confirmation : le bouton ouvre une
 * fenêtre par-dessus le profil, et c'est un second bouton — nommé, sans
 * ambiguïté — qui agit vraiment. Comme les autres fenêtres de l'application,
 * elle ne se ferme que par sa croix ou par « Annuler ».
 *
 * @var array{id: int, pseudo: string} $ami
 * @var ?string $amisDepuis
 * @var list<array> $photos
 * @var list<array> $fichiersPartages
 * @var int $messages
 * @var ?array $fond
 * @var ?string $adresseFond
 * @var bool $muette  les notifications de la conversation sont-elles coupées ?
 * @var string|null|false $coupure  la fin de la coupure (UTC), null sans fin, false sans coupure
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$pseudo = (string) $ami['pseudo'];
?>

<div class="entete-page profil-ami"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div class="profil-ami__identite">
    <?= Amis::avatar((int) $ami['id'], $pseudo, 'avatar--grand') ?>
    <div>
      <?php if (!$dansUneFenetre): ?>
        <p class="discret" style="margin:0 0 .2rem"><a href="<?= url('amis/' . (int) $ami['id']) ?>">← Retour à la discussion</a></p>
      <?php endif; ?>
      <h1 style="margin:0"><?= e($pseudo) ?></h1>
      <p class="discret" style="margin:.15rem 0 0">
        <?php if ($amisDepuis !== null): ?>Amis depuis le <?= e($amisDepuis) ?> · <?php endif; ?>
        <?= $messages ?> message<?= $messages > 1 ? 's' : '' ?> échangé<?= $messages > 1 ? 's' : '' ?>
      </p>
    </div>
  </div>
</div>

<?php // Recevoir, ou non, les notifications de cette conversation. ?>
<?= Vue::rendre('amis/_notifications_conversation', [
    'action' => url('amis/' . (int) $ami['id'] . '/notifications'), 'muette' => $muette, 'coupure' => $coupure,
    'laquelle' => 'cette conversation', 'dansUneFenetre' => $dansUneFenetre,
]) ?>

<?php
/*
 * Le fond d'écran de la conversation : une image prise dans ses fichiers,
 * que les deux amis voient derrière leurs messages.
 */
?>
<section class="carte profil-ami__section" id="fond-discussion">
  <h2 style="margin-top:0">🖼️ Fond d’écran de la conversation</h2>
  <div class="fond-reglage">
    <div class="fond-reglage__apercu<?= $adresseFond === null ? ' fond-reglage__apercu--vide' : '' ?>" data-fond-apercu
         <?= $adresseFond !== null ? 'style="background-image: url(&quot;' . e($adresseFond) . '&quot;)"' : '' ?>>
      <span class="fond-reglage__bulle">Bonjour !</span>
      <span class="fond-reglage__bulle fond-reglage__bulle--moi">Coucou 👋</span>
    </div>
    <div class="fond-reglage__infos">
      <p class="discret" style="margin:0 0 .75rem" data-fond-etat>
        <?php if ($fond === null): ?>
          Aucun fond pour l’instant.
        <?php else: ?>
          Choisi par <?= (int) ($fond['choisi_par'] ?? 0) === Auth::id() ? 'vous' : e($pseudo) ?>
          le <?= e(date_fr(Amis::local((string) $fond['choisi_le'])->format('Y-m-d H:i:s'), false)) ?>.
        <?php endif; ?>
        <?= e($pseudo) ?> le voit aussi.
      </p>
      <form method="post" action="<?= url('amis/' . (int) $ami['id'] . '/fond') ?>" enctype="multipart/form-data" class="fond-reglage__choix" data-fond-formulaire>
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
        <input type="file" name="fond" id="fond-fichier" class="sr-only" required
               accept="image/jpeg,image/png,image/gif,image/webp" data-fond-fichier>
        <label class="bouton bouton--secondaire" for="fond-fichier">🖼️ <?= $fond === null ? 'Choisir une image' : 'Changer d’image' ?></label>
        <span class="fond-reglage__nouveau" data-fond-nouveau hidden>
          <button class="bouton" type="submit">Enregistrer</button>
          <button class="bouton bouton--discret" type="button" data-fond-annuler>Annuler</button>
        </span>
      </form>
      <?php if ($fond !== null): ?>
        <form method="post" action="<?= url('amis/' . (int) $ami['id'] . '/fond/retirer') ?>" style="margin-top:.5rem" data-fond-retirer>
          <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
          <button class="bouton bouton--discret" type="submit">Retirer le fond</button>
        </form>
      <?php endif; ?>
      <p class="champ__aide" style="margin-bottom:0">JPEG, PNG, GIF ou WebP, <?= intdiv(Amis::IMAGE_MAX_OCTETS, 1024 * 1024) ?> Mo au plus.</p>
    </div>
  </div>
</section>

<section class="carte profil-ami__section">
  <h2 style="margin-top:0">📷 Photos <span class="discret profil-ami__nombre"><?= count($photos) ?></span></h2>
  <?php if ($photos === []): ?>
    <p class="discret" style="margin:0">Aucune photo échangée pour l’instant.</p>
  <?php else: ?>
    <div class="profil-ami__photos">
      <?php foreach ($photos as $p): ?>
        <a class="profil-ami__photo" href="<?= e($p['url']) ?>" target="_blank" rel="noopener" data-visionneuse
           title="<?= e(($p['moi'] ? 'Envoyée par vous' : 'Envoyée par ' . $pseudo) . ' · ' . $p['date']) ?>">
          <img src="<?= e($p['url']) ?>" alt="Photo du <?= e($p['date']) ?>" loading="lazy">
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="carte profil-ami__section">
  <h2 style="margin-top:0">📎 Fichiers <span class="discret profil-ami__nombre"><?= count($fichiersPartages) ?></span></h2>
  <?php if ($fichiersPartages === []): ?>
    <p class="discret" style="margin:0">Aucun fichier échangé pour l’instant.</p>
  <?php else: ?>
    <ul class="profil-ami__fichiers">
      <?php foreach ($fichiersPartages as $f): ?>
        <li class="profil-ami__fichier">
          <span class="bulle__fichier-icone" aria-hidden="true"><?= e($f['icone']) ?></span>
          <span class="profil-ami__fichier-infos">
            <a class="bulle__fichier-nom" href="<?= e($f['url']) ?>" target="_blank" rel="noopener"><?= e($f['nom']) ?></a>
            <span class="discret" style="font-size:.78rem">
              <?= e($f['taille']) ?> · <?= $f['moi'] ? 'envoyé par vous' : 'envoyé par ' . e($pseudo) ?> · <?= e($f['date']) ?>
            </span>
          </span>
          <a class="bouton bouton--secondaire bouton--petit" href="<?= e($f['telecharger']) ?>"
             aria-label="Télécharger <?= e($f['nom']) ?>" title="Télécharger">⬇</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php
/*
 * Ce qu'on s'est partagé, dans les deux sens : qui, quoi, avec quel droit,
 * et de quoi l'ouvrir d'ici.
 */
$entreNous = $entreNous ?? ['recus' => [], 'envoyes' => []];
$nbPartages = count($entreNous['recus']) + count($entreNous['envoyes']);
?>
<section class="carte profil-ami__section" id="partages-entre-nous">
  <h2 style="margin-top:0"><?= Partages::icone(20) ?> Partages <span class="discret profil-ami__nombre"><?= $nbPartages ?></span></h2>
  <?php if ($nbPartages === 0): ?>
    <p class="discret" style="margin:0">Rien de partagé entre vous pour l’instant.</p>
  <?php else: ?>
    <?php foreach (['recus' => 'Partagé par ' . $pseudo, 'envoyes' => 'Partagé par vous'] as $sensPartage => $titreSens): ?>
      <?php if ($entreNous[$sensPartage] === []) { continue; } ?>
      <h3 class="groupe-sous-titre"><?= e($titreSens) ?> <span class="discret">(<?= count($entreNous[$sensPartage]) ?>)</span></h3>
      <ul class="partage-lignes">
        <?php foreach ($entreNous[$sensPartage] as $p): ?>
          <li class="partage-ligne">
            <a class="partage-ligne__lien" href="<?= e($p['url']) ?>"
               <?= $p['fenetre'] ? 'data-fenetre' : '' ?><?= $p['nouvelOnglet'] ? ' target="_blank" rel="noopener"' : '' ?>>
              <span class="partage-ligne__icone" aria-hidden="true"><?= e($p['icone']) ?></span>
              <span class="partage-ligne__texte">
                <span class="partage-ligne__titre"><?= e($p['titre']) ?></span>
                <span class="partage-ligne__detail">
                  <?= e(Partages::libelle($p['type'])) ?> · <?= e(mb_strtolower(Partages::libelleDroit($p['droit']))) ?>
                  · <?= e(date_fr(Amis::local($p['quand'])->format('Y-m-d H:i:s'), false)) ?>
                </span>
              </span>
            </a>
            <?php
            // Ce que je partage : je retire son accès. Ce qu'on me partage : je le retire de ma liste.
            $aMoi = $sensPartage === 'envoyes';
            $actionRetrait = $aMoi
                ? 'partager/' . Partages::mot($p['type']) . '/' . (int) $p['id'] . '/acces/' . (int) $ami['id'] . '/retirer'
                : 'partages/' . Partages::mot($p['type']) . '/' . (int) $p['id'] . '/oublier';
            $garde = $aMoi
                ? e($pseudo) . ' n’aura plus accès à « ' . e($p['titre']) . ' ». Continuer ?'
                : 'Retirer « ' . e($p['titre']) . ' » de vos partages ? Il faudra que ' . e($pseudo) . ' vous le partage de nouveau pour le revoir.';
            ?>
            <?php if ($aMoi): ?>
              <?php // Le droit de mon ami se change ici, comme dans la fenêtre « Partager ». ?>
              <form method="post" class="en-ligne profil-ami__droit" data-envoi-fenetre
                    action="<?= url('partager/' . Partages::mot($p['type']) . '/' . (int) $p['id'] . '/acces/' . (int) $ami['id'] . '/droit') ?>">
                <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                <input type="hidden" name="profil" value="<?= (int) $ami['id'] ?>">
                <label class="sr-only" for="droit-<?= e($p['type']) ?>-<?= (int) $p['id'] ?>">Ce que <?= e($pseudo) ?> peut faire de « <?= e($p['titre']) ?> »</label>
                <select id="droit-<?= e($p['type']) ?>-<?= (int) $p['id'] ?>" name="droit">
                  <?php foreach (Partages::DROITS as $unDroit): ?>
                    <?php if (in_array($p['type'], ['fichier', 'evenement'], true) && $unDroit === 'modification') { continue; } ?>
                    <option value="<?= e($unDroit) ?>"<?= $p['droit'] === $unDroit ? ' selected' : '' ?>><?= e(Partages::libelleDroit($unDroit)) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="bouton bouton--discret bouton--petit" type="submit">Changer</button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= url($actionRetrait) ?>" data-envoi-fenetre data-confirmation="<?= $garde ?>">
              <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
              <input type="hidden" name="profil" value="<?= (int) $ami['id'] ?>">
              <button class="bouton bouton--discret bouton--petit" type="submit">
                <?= $aMoi ? 'Retirer l’accès' : 'Retirer de ma liste' ?>
              </button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<section class="carte profil-ami__section profil-ami__danger">
  <button class="bouton bouton--danger" type="button" data-ouvrir-dialogue="confirmer-retrait-ami">Retirer <?= e($pseudo) ?> de mes amis</button>
  <button class="bouton bouton--danger" type="button" data-ouvrir-dialogue="confirmer-blocage-ami">🚫 Bloquer <?= e($pseudo) ?></button>
</section>

<dialog class="confirmation" id="confirmer-retrait-ami" data-confirmation-dialogue aria-labelledby="titre-retrait-ami">
  <button class="fenetre__fermer" type="button" data-fermer-dialogue aria-label="Fermer">✕</button>
  <h2 class="confirmation__titre" id="titre-retrait-ami">Retirer <?= e($pseudo) ?> de vos amis ?</h2>
  <p class="confirmation__texte">
    Vous ne pourrez plus vous écrire, ni voir les photos et fichiers échangés.
    Vos messages sont gardés : ils reviendront si vous redevenez amis.
  </p>
  <form method="post" action="<?= url('amis/' . (int) $ami['id'] . '/retirer') ?>" class="confirmation__choix">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <button class="bouton bouton--danger" type="submit">Oui, retirer <?= e($pseudo) ?></button>
    <button class="bouton bouton--discret" type="button" data-fermer-dialogue>Annuler</button>
  </form>
</dialog>

<dialog class="confirmation" id="confirmer-blocage-ami" data-confirmation-dialogue aria-labelledby="titre-blocage-ami">
  <button class="fenetre__fermer" type="button" data-fermer-dialogue aria-label="Fermer">✕</button>
  <h2 class="confirmation__titre" id="titre-blocage-ami">🚫 Bloquer <?= e($pseudo) ?> ?</h2>
  <p class="confirmation__texte">
    Vous ne serez plus amis. <?= e($pseudo) ?> ne pourra plus vous trouver par votre pseudo, ni vous écrire,
    ni vous redemander en ami — sans qu’on le lui dise. Vous pourrez le débloquer depuis la page « Amis ».
  </p>
  <form method="post" action="<?= url('amis/' . (int) $ami['id'] . '/bloquer') ?>" class="confirmation__choix">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <button class="bouton bouton--danger" type="submit">Oui, bloquer <?= e($pseudo) ?></button>
    <button class="bouton bouton--discret" type="button" data-fermer-dialogue>Annuler</button>
  </form>
</dialog>
