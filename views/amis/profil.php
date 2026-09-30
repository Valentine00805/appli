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
        <p class="discret" style="margin:0 0 .2rem"><a href="<?= url('amis/' . (int) $ami['id']) ?>"><?= e(t('prf.retour_discussion')) ?></a></p>
      <?php endif; ?>
      <h1 style="margin:0"><?= e($pseudo) ?></h1>
      <p class="discret" style="margin:.15rem 0 0">
        <?php if ($amisDepuis !== null): ?><?= e(t('prf.amis_depuis', ['date' => $amisDepuis])) ?> <?php endif; ?>
        <?= e(tn('prf.messages', $messages)) ?>
      </p>
    </div>
  </div>
</div>

<?php // Recevoir, ou non, les notifications de cette conversation. ?>
<?= Vue::rendre('amis/_notifications_conversation', [
    'action' => url('amis/' . (int) $ami['id'] . '/notifications'), 'muette' => $muette, 'coupure' => $coupure,
    'laquelle' => t('notif.cette_conversation'), 'dansUneFenetre' => $dansUneFenetre,
]) ?>

<?php
/*
 * Le fond d'écran de la conversation : une image prise dans ses fichiers,
 * que les deux amis voient derrière leurs messages.
 */
?>
<section class="carte profil-ami__section" id="fond-discussion">
  <h2 style="margin-top:0"><?= e(t('prf.fond')) ?></h2>
  <div class="fond-reglage">
    <div class="fond-reglage__apercu<?= $adresseFond === null ? ' fond-reglage__apercu--vide' : '' ?>" data-fond-apercu
         <?= $adresseFond !== null ? 'style="background-image: url(&quot;' . e($adresseFond) . '&quot;)"' : '' ?>>
      <span class="fond-reglage__bulle"><?= e(t('prf.bulle_1')) ?></span>
      <span class="fond-reglage__bulle fond-reglage__bulle--moi"><?= e(t('prf.bulle_2')) ?></span>
    </div>
    <div class="fond-reglage__infos">
      <p class="discret" style="margin:0 0 .75rem" data-fond-etat>
        <?php if ($fond === null): ?>
          <?= e(t('prf.aucun_fond')) ?>
        <?php else: ?>
          <?= e(t('prf.choisi_par', [
              'qui' => (int) ($fond['choisi_par'] ?? 0) === Auth::id() ? t('prf.vous') : $pseudo,
              'date' => date_fr(Amis::local((string) $fond['choisi_le'])->format('Y-m-d H:i:s'), false),
          ])) ?>
        <?php endif; ?>
        <?= e(t('prf.le_voit_aussi', ['qui' => $pseudo])) ?>
      </p>
      <form method="post" action="<?= url('amis/' . (int) $ami['id'] . '/fond') ?>" enctype="multipart/form-data" class="fond-reglage__choix" data-fond-formulaire>
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
        <input type="file" name="fond" id="fond-fichier" class="sr-only" required
               accept="image/jpeg,image/png,image/gif,image/webp" data-fond-fichier>
        <label class="bouton bouton--secondaire" for="fond-fichier">🖼️ <?= e(t($fond === null ? 'prf.choisir_image' : 'prf.changer_image')) ?></label>
        <span class="fond-reglage__nouveau" data-fond-nouveau hidden>
          <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
          <button class="bouton bouton--discret" type="button" data-fond-annuler><?= e(t('commun.annuler')) ?></button>
        </span>
      </form>
      <?php if ($fond !== null): ?>
        <form method="post" action="<?= url('amis/' . (int) $ami['id'] . '/fond/retirer') ?>" style="margin-top:.5rem" data-fond-retirer>
          <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
          <button class="bouton bouton--discret" type="submit"><?= e(t('prf.retirer_fond')) ?></button>
        </form>
      <?php endif; ?>
      <p class="champ__aide" style="margin-bottom:0"><?= e(t('prf.fond_formats', ['mo' => intdiv(Amis::IMAGE_MAX_OCTETS, 1024 * 1024)])) ?></p>
    </div>
  </div>
</section>

<section class="carte profil-ami__section">
  <h2 style="margin-top:0"><?= e(t('prf.photos')) ?> <span class="discret profil-ami__nombre"><?= count($photos) ?></span></h2>
  <?php if ($photos === []): ?>
    <p class="discret" style="margin:0"><?= e(t('prf.aucune_photo')) ?></p>
  <?php else: ?>
    <div class="profil-ami__photos">
      <?php foreach ($photos as $p): ?>
        <a class="profil-ami__photo" href="<?= e($p['url']) ?>" target="_blank" rel="noopener" data-visionneuse
           title="<?= e(($p['moi'] ? t('prf.envoyee_par_vous') : t('prf.envoyee_par', ['qui' => $pseudo])) . ' · ' . $p['date']) ?>">
          <img src="<?= e($p['url']) ?>" alt="<?= e(t('prf.photo_du', ['date' => $p['date']])) ?>" loading="lazy">
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="carte profil-ami__section">
  <h2 style="margin-top:0"><?= e(t('prf.fichiers')) ?> <span class="discret profil-ami__nombre"><?= count($fichiersPartages) ?></span></h2>
  <?php if ($fichiersPartages === []): ?>
    <p class="discret" style="margin:0"><?= e(t('prf.aucun_fichier')) ?></p>
  <?php else: ?>
    <ul class="profil-ami__fichiers">
      <?php foreach ($fichiersPartages as $f): ?>
        <li class="profil-ami__fichier">
          <span class="bulle__fichier-icone" aria-hidden="true"><?= e($f['icone']) ?></span>
          <span class="profil-ami__fichier-infos">
            <a class="bulle__fichier-nom" href="<?= e($f['url']) ?>" target="_blank" rel="noopener"><?= e($f['nom']) ?></a>
            <span class="discret" style="font-size:.78rem">
              <?= e($f['taille']) ?> · <?= e($f['moi'] ? t('prf.envoye_par_vous') : t('prf.envoye_par', ['qui' => $pseudo])) ?> · <?= e($f['date']) ?>
            </span>
          </span>
          <a class="bouton bouton--secondaire bouton--petit" href="<?= e($f['telecharger']) ?>"
             aria-label="<?= e(t('prf.telecharger_nom', ['nom' => $f['nom']])) ?>" title="<?= e(t('commun.telecharger')) ?>">⬇</a>
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
  <h2 style="margin-top:0"><?= Partages::icone(20) ?> <?= e(t('prf.partages')) ?> <span class="discret profil-ami__nombre"><?= $nbPartages ?></span></h2>
  <?php if ($nbPartages === 0): ?>
    <p class="discret" style="margin:0"><?= e(t('prf.rien_partage')) ?></p>
  <?php else: ?>
    <?php foreach (['recus' => t('prf.partage_par', ['qui' => $pseudo]), 'envoyes' => t('prf.partage_par_vous')] as $sensPartage => $titreSens): ?>
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
                ? e(t('prf.retirer_acces_sur', ['qui' => $pseudo, 'titre' => $p['titre']]))
                : e(t('prf.oublier_sur', ['titre' => $p['titre'], 'qui' => $pseudo]));
            ?>
            <?php if ($aMoi): ?>
              <?php // Le droit de mon ami se change ici, comme dans la fenêtre « Partager ». ?>
              <form method="post" class="en-ligne profil-ami__droit" data-envoi-fenetre
                    action="<?= url('partager/' . Partages::mot($p['type']) . '/' . (int) $p['id'] . '/acces/' . (int) $ami['id'] . '/droit') ?>">
                <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                <input type="hidden" name="profil" value="<?= (int) $ami['id'] ?>">
                <label class="sr-only" for="droit-<?= e($p['type']) ?>-<?= (int) $p['id'] ?>"><?= e(t('prf.ce_que_peut_faire', ['qui' => $pseudo, 'titre' => $p['titre']])) ?></label>
                <select id="droit-<?= e($p['type']) ?>-<?= (int) $p['id'] ?>" name="droit">
                  <?php foreach (Partages::DROITS as $unDroit): ?>
                    <?php if (in_array($p['type'], ['fichier', 'evenement'], true) && $unDroit === 'modification') { continue; } ?>
                    <option value="<?= e($unDroit) ?>"<?= $p['droit'] === $unDroit ? ' selected' : '' ?>><?= e(Partages::libelleDroit($unDroit)) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('prf.changer')) ?></button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= url($actionRetrait) ?>" data-envoi-fenetre data-confirmation="<?= $garde ?>">
              <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
              <input type="hidden" name="profil" value="<?= (int) $ami['id'] ?>">
              <button class="bouton bouton--discret bouton--petit" type="submit">
                <?= e(t($aMoi ? 'prf.retirer_acces' : 'prf.retirer_liste')) ?>
              </button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<section class="carte profil-ami__section profil-ami__danger">
  <button class="bouton bouton--danger" type="button" data-ouvrir-dialogue="confirmer-retrait-ami"><?= e(t('prf.retirer_ami', ['qui' => $pseudo])) ?></button>
  <button class="bouton bouton--danger" type="button" data-ouvrir-dialogue="confirmer-blocage-ami"><?= e(t('prf.bloquer', ['qui' => $pseudo])) ?></button>
</section>

<dialog class="confirmation" id="confirmer-retrait-ami" data-confirmation-dialogue aria-labelledby="titre-retrait-ami">
  <button class="fenetre__fermer" type="button" data-fermer-dialogue aria-label="<?= e(t('prf.fermer')) ?>">✕</button>
  <h2 class="confirmation__titre" id="titre-retrait-ami"><?= e(t('prf.retrait_titre', ['qui' => $pseudo])) ?></h2>
  <p class="confirmation__texte">
    <?= e(t('prf.retrait_texte')) ?>
  </p>
  <form method="post" action="<?= url('amis/' . (int) $ami['id'] . '/retirer') ?>" class="confirmation__choix">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <button class="bouton bouton--danger" type="submit"><?= e(t('prf.oui_retirer', ['qui' => $pseudo])) ?></button>
    <button class="bouton bouton--discret" type="button" data-fermer-dialogue><?= e(t('commun.annuler')) ?></button>
  </form>
</dialog>

<dialog class="confirmation" id="confirmer-blocage-ami" data-confirmation-dialogue aria-labelledby="titre-blocage-ami">
  <button class="fenetre__fermer" type="button" data-fermer-dialogue aria-label="<?= e(t('prf.fermer')) ?>">✕</button>
  <h2 class="confirmation__titre" id="titre-blocage-ami"><?= e(t('prf.blocage_titre', ['qui' => $pseudo])) ?></h2>
  <p class="confirmation__texte">
    <?= e(t('prf.blocage_texte', ['qui' => $pseudo])) ?>
  </p>
  <form method="post" action="<?= url('amis/' . (int) $ami['id'] . '/bloquer') ?>" class="confirmation__choix">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <button class="bouton bouton--danger" type="submit"><?= e(t('prf.oui_bloquer', ['qui' => $pseudo])) ?></button>
    <button class="bouton bouton--discret" type="button" data-fermer-dialogue><?= e(t('commun.annuler')) ?></button>
  </form>
</dialog>
