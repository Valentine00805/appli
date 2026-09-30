<?php
/**
 * Les réglages d'un groupe, ouverts depuis la discussion : son nom, son fond
 * d'écran, ses membres, ce qu'on y a échangé et, tout en bas, le départ.
 *
 * Chacun peut renommer le groupe et changer son fond ; les administrateurs
 * ajoutent et retirent des membres, et en nomment d'autres. Retirer un
 * membre et quitter le groupe demandent une confirmation, dans une fenêtre
 * par-dessus.
 *
 * @var array $groupe
 * @var list<array{id: int, pseudo: string, role: string}> $membres
 * @var bool $admin
 * @var int $nombreAdmins
 * @var list<array> $invitations  les invitations en attente
 * @var list<array> $aAjouter  mes amis qui ne sont pas encore dans le groupe
 * @var list<array> $photos
 * @var list<array> $fichiersPartages
 * @var ?string $adresseFond
 * @var ?string $fondPar
 * @var bool $muette  ai-je coupé les notifications du groupe ?
 * @var string|null|false $coupure  la fin de la coupure (UTC), null sans fin, false sans coupure
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$id = (int) $groupe['id'];
$nom = (string) $groupe['nom'];
$csrf = Session::jetonCsrf();
$moi = Auth::id();
?>

<div class="entete-page profil-ami"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div class="profil-ami__identite">
    <?= Conversations::avatar($id, $groupe['photo_nom'], 'avatar--grand') ?>
    <div>
      <?php if (!$dansUneFenetre): ?>
        <p class="discret" style="margin:0 0 .2rem"><a href="<?= url('groupes/' . $id) ?>"><?= e(t('prf.retour_discussion')) ?></a></p>
      <?php endif; ?>
      <h1 style="margin:0"><?= e($nom) ?></h1>
      <p class="discret" style="margin:.15rem 0 0"><?= e(t('grp.membres_cree', [
          'n' => count($membres),
          'date' => date_fr(Amis::local((string) $groupe['created_at'])->format('Y-m-d H:i:s'), false),
      ])) ?></p>
    </div>
  </div>
</div>

<?php // La photo du groupe : chacun peut la changer ; tout le groupe la voit. ?>
<section class="carte profil-ami__section" id="photo-groupe" data-photo-carte>
  <h2 style="margin-top:0"><?= e(t('grp.photo')) ?></h2>
  <div class="photo-groupe">
    <span class="photo-groupe__apercu" data-photo-apercu>
      <?= Conversations::avatar($id, $groupe['photo_nom'], 'avatar--apercu') ?>
    </span>
    <div class="fond-reglage__infos">
      <form method="post" action="<?= url('groupes/' . $id . '/photo') ?>" enctype="multipart/form-data" class="fond-reglage__choix" data-photo-formulaire>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="file" name="photo" id="photo-groupe-fichier" class="sr-only" required
               accept="image/jpeg,image/png,image/gif,image/webp" data-photo-fichier>
        <label class="bouton bouton--secondaire" for="photo-groupe-fichier">📷 <?= e(t($groupe['photo_nom'] === null ? 'grp.choisir_photo' : 'grp.changer_photo')) ?></label>
        <span class="fond-reglage__nouveau" data-photo-nouveau hidden>
          <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
          <button class="bouton bouton--discret" type="button" data-photo-annuler><?= e(t('commun.annuler')) ?></button>
        </span>
      </form>
      <?php if ($groupe['photo_nom'] !== null): ?>
        <form method="post" action="<?= url('groupes/' . $id . '/photo/retirer') ?>" style="margin-top:.5rem">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--discret" type="submit"><?= e(t('grp.retirer_photo')) ?></button>
        </form>
      <?php endif; ?>
      <p class="champ__aide" style="margin-bottom:0"><?= e(t('grp.photo_aide', ['mo' => intdiv(Amis::IMAGE_MAX_OCTETS, 1024 * 1024)])) ?></p>
    </div>
  </div>
</section>

<?php // Le nom : il se lit, et ne se change qu'en passant par « Modifier ». ?>
<section class="carte profil-ami__section" data-reglage>
  <h2 style="margin-top:0"><?= e(t('grp.nom_titre')) ?></h2>
  <div class="reglage-lecture" data-reglage-lecture>
    <p class="reglage-lecture__valeur"><?= e($nom) ?></p>
    <button class="bouton bouton--secondaire" type="button" data-reglage-modifier><?= e(t('commun.modifier')) ?></button>
  </div>
  <form method="post" action="<?= url('groupes/' . $id . '/nom') ?>" data-reglage-edition hidden>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label class="legende" for="groupe-renommer"><?= e(t('grp.nouveau_nom')) ?></label>
    <div class="fuseau-choix">
      <input type="text" id="groupe-renommer" name="nom" required maxlength="<?= Conversations::NOM_MAX ?>"
             value="<?= e($nom) ?>" data-valeur-actuelle="<?= e($nom) ?>" autocomplete="off">
      <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
      <button class="bouton bouton--discret" type="button" data-reglage-annuler><?= e(t('commun.annuler')) ?></button>
    </div>
  </form>
</section>

<?php // Recevoir, ou non, les notifications de ce groupe : un choix de chacun. ?>
<?= Vue::rendre('amis/_notifications_conversation', [
    'action' => url('groupes/' . $id . '/notifications'), 'muette' => $muette, 'coupure' => $coupure,
    'laquelle' => t('notif.ce_groupe'), 'dansUneFenetre' => $dansUneFenetre,
]) ?>

<?php // Le fond d'écran, commun à tout le groupe : même carte qu'entre deux amis. ?>
<section class="carte profil-ami__section" id="fond-discussion">
  <h2 style="margin-top:0"><?= e(t('grp.fond')) ?></h2>
  <div class="fond-reglage">
    <div class="fond-reglage__apercu<?= $adresseFond === null ? ' fond-reglage__apercu--vide' : '' ?>" data-fond-apercu
         <?= $adresseFond !== null ? 'style="background-image: url(&quot;' . e($adresseFond) . '&quot;)"' : '' ?>>
      <span class="fond-reglage__bulle"><?= e(t('prf.bulle_1')) ?></span>
      <span class="fond-reglage__bulle fond-reglage__bulle--moi"><?= e(t('prf.bulle_2')) ?></span>
    </div>
    <div class="fond-reglage__infos">
      <p class="discret" style="margin:0 0 .75rem" data-fond-etat>
        <?php if ($adresseFond === null): ?>
          <?= e(t('prf.aucun_fond')) ?>
        <?php else: ?>
          <?= e(t('prf.choisi_par', [
              'qui' => (string) ($fondPar ?: t('grp.ancien_membre')),
              'date' => date_fr(Amis::local((string) $groupe['fond_le'])->format('Y-m-d H:i:s'), false),
          ])) ?>
        <?php endif; ?>
        <?= e(t('grp.tout_le_groupe')) ?>
      </p>
      <form method="post" action="<?= url('groupes/' . $id . '/fond') ?>" enctype="multipart/form-data" class="fond-reglage__choix" data-fond-formulaire>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="file" name="fond" id="fond-fichier" class="sr-only" required
               accept="image/jpeg,image/png,image/gif,image/webp" data-fond-fichier>
        <label class="bouton bouton--secondaire" for="fond-fichier">🖼️ <?= e(t($adresseFond === null ? 'prf.choisir_image' : 'prf.changer_image')) ?></label>
        <span class="fond-reglage__nouveau" data-fond-nouveau hidden>
          <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
          <button class="bouton bouton--discret" type="button" data-fond-annuler><?= e(t('commun.annuler')) ?></button>
        </span>
      </form>
      <?php if ($adresseFond !== null): ?>
        <form method="post" action="<?= url('groupes/' . $id . '/fond/retirer') ?>" style="margin-top:.5rem">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--discret" type="submit"><?= e(t('prf.retirer_fond')) ?></button>
        </form>
      <?php endif; ?>
      <p class="champ__aide" style="margin-bottom:0"><?= e(t('prf.fond_formats', ['mo' => intdiv(Amis::IMAGE_MAX_OCTETS, 1024 * 1024)])) ?></p>
    </div>
  </div>
</section>

<section class="carte profil-ami__section" id="groupe-membres">
  <h2 style="margin-top:0"><?= e(t('grp.membres_titre')) ?> <span class="discret profil-ami__nombre"><?= count($membres) ?></span></h2>
  <ul class="groupe-membres">
    <?php foreach ($membres as $m): ?>
      <li class="groupe-membres__ligne">
        <?= Amis::avatar($m['id'], $m['pseudo']) ?>
        <span class="groupe-membres__nom">
          <?= e($m['pseudo']) ?><?= $m['id'] === $moi ? ' <span class="discret">' . e(t('grp.vous')) . '</span>' : '' ?>
          <?php if ($m['role'] === 'admin'): ?><span class="pastille"><?= e(t('grp.administrateur')) ?></span><?php endif; ?>
        </span>
        <?php if ($admin && $m['id'] === $moi && $nombreAdmins > 1): ?>
          <?php // Un administrateur peut rendre son rôle, tant qu'il en reste un autre. ?>
          <span class="groupe-membres__actions">
            <form method="post" action="<?= url('groupes/' . $id . '/membres/' . $m['id'] . '/membre') ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('grp.ne_plus_admin')) ?></button>
            </form>
          </span>
        <?php endif; ?>
        <?php if ($admin && $m['id'] !== $moi): ?>
          <span class="groupe-membres__actions">
            <?php if ($m['role'] !== 'admin'): ?>
              <form method="post" action="<?= url('groupes/' . $id . '/membres/' . $m['id'] . '/admin') ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('grp.nommer_admin')) ?></button>
              </form>
            <?php else: ?>
              <form method="post" action="<?= url('groupes/' . $id . '/membres/' . $m['id'] . '/membre') ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('grp.retirer_admin')) ?></button>
              </form>
            <?php endif; ?>
            <button class="bouton bouton--danger bouton--petit" type="button" data-ouvrir-dialogue="confirmer-retrait-membre-<?= $m['id'] ?>"><?= e(t('grp.retirer')) ?></button>
          </span>
          <dialog class="confirmation" id="confirmer-retrait-membre-<?= $m['id'] ?>" data-confirmation-dialogue aria-labelledby="titre-retrait-membre-<?= $m['id'] ?>">
            <button class="fenetre__fermer" type="button" data-fermer-dialogue aria-label="<?= e(t('prf.fermer')) ?>">✕</button>
            <h2 class="confirmation__titre" id="titre-retrait-membre-<?= $m['id'] ?>"><?= e(t('grp.retirer_membre_titre', ['qui' => $m['pseudo']])) ?></h2>
            <p class="confirmation__texte"><?= e(t('grp.retirer_membre_texte')) ?></p>
            <form method="post" action="<?= url('groupes/' . $id . '/membres/' . $m['id'] . '/retirer') ?>" class="confirmation__choix">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--danger" type="submit"><?= e(t('grp.oui_retirer', ['qui' => $m['pseudo']])) ?></button>
              <button class="bouton bouton--discret" type="button" data-fermer-dialogue><?= e(t('commun.annuler')) ?></button>
            </form>
          </dialog>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($invitations !== []): ?>
    <h3 class="groupe-sous-titre"><?= e(t('grp.invitations')) ?></h3>
    <ul class="groupe-membres">
      <?php foreach ($invitations as $inv): ?>
        <li class="groupe-membres__ligne">
          <?= Amis::avatar((int) $inv['id'], (string) $inv['pseudo']) ?>
          <span class="groupe-membres__nom">
            <?= e((string) $inv['pseudo']) ?>
            <span class="discret" style="font-size:.8rem"><?= e(t('grp.invite')) ?><?= $inv['par'] !== '' ? e(t('grp.invite_par', ['qui' => (string) $inv['par']])) : '' ?></span>
          </span>
          <?php if ($admin): ?>
            <form method="post" action="<?= url('groupes/' . $id . '/invitations/' . (int) $inv['id'] . '/annuler') ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('commun.annuler')) ?></button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($admin): ?>
    <?php // Ajouter n'importe quel compte par son pseudo : un ami entre tout de suite, les autres sont invités. ?>
    <div class="groupe-recherche" data-groupe-recherche
         data-url="<?= e(url('groupes/' . $id . '/chercher')) ?>"
         data-inviter="<?= e(url('groupes/' . $id . '/inviter')) ?>"
         data-jeton="<?= e($csrf) ?>">
      <label class="legende" for="groupe-recherche-champ"><?= e(t('grp.ajouter_pseudo')) ?></label>
      <input type="search" id="groupe-recherche-champ" data-groupe-recherche-champ
             placeholder="<?= e(t('grp.pseudo_exemple')) ?>" minlength="2" maxlength="<?= Auth::PSEUDO_MAX ?>"
             autocomplete="off" autocapitalize="none" spellcheck="false">
      <p class="champ__aide" data-groupe-recherche-etat aria-live="polite">
        <?= e(t('grp.ajouter_aide')) ?>
      </p>
      <ul class="amis-resultats" data-groupe-recherche-liste></ul>
    </div>
    <?php if ($aAjouter === []): ?>
      <p class="discret" style="margin:.75rem 0 0"><?= e(t('grp.tous_deja')) ?></p>
    <?php else: ?>
      <form method="post" action="<?= url('groupes/' . $id . '/membres') ?>" class="groupe-ajout">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <fieldset class="groupe-choix">
          <legend class="legende"><?= e(t('grp.ajouter_amis')) ?></legend>
          <ul class="groupe-choix__liste">
            <?php foreach ($aAjouter as $a): ?>
              <li>
                <label class="groupe-choix__ami">
                  <input type="checkbox" name="membres[]" value="<?= (int) $a['id'] ?>">
                  <?= Amis::avatar((int) $a['id'], (string) $a['pseudo']) ?>
                  <span><?= e((string) $a['pseudo']) ?></span>
                </label>
              </li>
            <?php endforeach; ?>
          </ul>
          <p class="champ__aide"><?= e(t('grp.ajouter_amis_aide')) ?></p>
        </fieldset>
        <button class="bouton" type="submit"><?= e(t('grp.ajouter')) ?></button>
      </form>
    <?php endif; ?>
  <?php else: ?>
    <p class="champ__aide" style="margin-bottom:0"><?= e(t('grp.seuls_admins')) ?></p>
  <?php endif; ?>
</section>

<section class="carte profil-ami__section">
  <h2 style="margin-top:0"><?= e(t('prf.photos')) ?> <span class="discret profil-ami__nombre"><?= count($photos) ?></span></h2>
  <?php if ($photos === []): ?>
    <p class="discret" style="margin:0"><?= e(t('prf.aucune_photo')) ?></p>
  <?php else: ?>
    <div class="profil-ami__photos">
      <?php foreach ($photos as $p): ?>
        <a class="profil-ami__photo" href="<?= e($p['url']) ?>" target="_blank" rel="noopener" data-visionneuse
           title="<?= e(t('prf.envoyee_par', ['qui' => $p['qui']]) . ' · ' . $p['date']) ?>">
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
            <span class="discret" style="font-size:.78rem"><?= e($f['taille']) ?> · <?= e(t('grp.envoye_par', ['qui' => $f['qui']])) ?> · <?= e($f['date']) ?></span>
          </span>
          <a class="bouton bouton--secondaire bouton--petit" href="<?= e($f['telecharger']) ?>"
             aria-label="<?= e(t('prf.telecharger_nom', ['nom' => $f['nom']])) ?>" title="<?= e(t('commun.telecharger')) ?>">⬇</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="carte profil-ami__section profil-ami__danger">
  <button class="bouton bouton--danger" type="button" data-ouvrir-dialogue="confirmer-depart-groupe"><?= e(t('grp.quitter')) ?></button>
</section>

<dialog class="confirmation" id="confirmer-depart-groupe" data-confirmation-dialogue aria-labelledby="titre-depart-groupe">
  <button class="fenetre__fermer" type="button" data-fermer-dialogue aria-label="<?= e(t('prf.fermer')) ?>">✕</button>
  <h2 class="confirmation__titre" id="titre-depart-groupe"><?= e(t('grp.quitter_titre', ['nom' => $nom])) ?></h2>
  <p class="confirmation__texte">
    <?= e(t('grp.quitter_texte')) ?>
    <?php if (count($membres) === 1): ?><?= e(t('grp.quitter_dernier')) ?><?php endif; ?>
  </p>
  <form method="post" action="<?= url('groupes/' . $id . '/quitter') ?>" class="confirmation__choix">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <button class="bouton bouton--danger" type="submit"><?= e(t('grp.oui_quitter')) ?></button>
    <button class="bouton bouton--discret" type="button" data-fermer-dialogue><?= e(t('commun.annuler')) ?></button>
  </form>
</dialog>
