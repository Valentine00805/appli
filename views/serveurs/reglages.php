<?php
/**
 * Les réglages d'un serveur, ouverts depuis ses salons : nom et icône, salons, membres, invitations, départ.
 *
 * Les administrateurs créent les salons, invitent et retirent des membres ; le propriétaire seul nomme les administrateurs et
 * supprime le serveur. Les formulaires restent dans la fenêtre (data-envoi-fenetre) ; quitter et supprimer, eux, en sortent.
 *
 * @var array $serveur
 * @var bool $gere  administrateur ou propriétaire
 * @var bool $proprietaire
 * @var list<array{id: int, nom: string, non_lus: int}> $salons
 * @var list<array{id: int, pseudo: string, role: string}> $membres
 * @var list<array{id: int, pseudo: string}> $invites
 * @var list<array> $aInviter  mes amis qui ne sont ni dedans ni déjà invités
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$id = (int) $serveur['id'];
$nom = (string) $serveur['nom'];
$csrf = Session::jetonCsrf();
$moi = Auth::id();
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$base = 'serveurs/' . $id;
$photo = Serveurs::adressePhoto($id, $serveur['photo_nom']);
?>
<div class="entete-page profil-ami"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div class="profil-ami__identite">
    <span class="serveur-icone serveur-icone--enorme" aria-hidden="true"><?= Serveurs::visuel((string) $serveur['icone'], $photo) ?></span>
    <div>
      <?php if (!$dansUneFenetre): ?>
        <p class="discret" style="margin:0 0 .2rem"><a href="<?= url($base) ?>"><?= e(t('srv.retour_serveur')) ?></a></p>
      <?php endif; ?>
      <h1 style="margin:0"><?= e($nom) ?></h1>
      <p class="discret" style="margin:.15rem 0 0"><?= e(tn('srv.membres_n', count($membres))) ?> · <?= e(tn('srv.salons_n', count($salons))) ?></p>
    </div>
  </div>
</div>

<?php if ($gere): ?>
<?php // Le logo : essayé dans l'aperçu, envoyé seulement avec « Enregistrer » (le script des photos de groupe s'en charge). ?>
<section class="carte profil-ami__section" id="serveur-logo" data-photo-carte>
  <h2 style="margin-top:0"><?= e(t('srv.logo')) ?></h2>
  <div class="photo-groupe">
    <span class="photo-groupe__apercu" data-photo-apercu>
      <span class="avatar avatar--apercu serveur-apercu<?= $photo !== null ? ' avatar--photo' : '' ?>" aria-hidden="true"><?= Serveurs::visuel((string) $serveur['icone'], $photo) ?></span>
    </span>
    <div class="fond-reglage__infos">
      <form method="post" action="<?= url($base . '/photo') ?>" enctype="multipart/form-data" class="fond-reglage__choix" data-photo-formulaire<?= $envoi ?>>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="file" name="photo" id="serveur-logo-fichier" class="sr-only" required
               accept="image/jpeg,image/png,image/gif,image/webp" data-photo-fichier>
        <label class="bouton bouton--secondaire" for="serveur-logo-fichier">📷 <?= e(t($photo === null ? 'srv.logo_choisir' : 'srv.logo_changer')) ?></label>
        <span class="fond-reglage__nouveau" data-photo-nouveau hidden>
          <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
          <button class="bouton bouton--discret" type="button" data-photo-annuler><?= e(t('commun.annuler')) ?></button>
        </span>
      </form>
      <?php if ($photo !== null): ?>
        <form method="post" action="<?= url($base . '/photo/retirer') ?>" style="margin-top:.5rem"<?= $envoi ?>>
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--discret" type="submit"><?= e(t('srv.logo_retirer')) ?></button>
        </form>
      <?php endif; ?>
      <p class="champ__aide" style="margin-bottom:0"><?= e(t('srv.logo_aide', ['mo' => intdiv(Amis::IMAGE_MAX_OCTETS, 1024 * 1024)])) ?></p>
    </div>
  </div>
</section>

<section class="carte profil-ami__section" id="serveur-nom-icone">
  <h2 style="margin-top:0"><?= e(t('srv.nom_icone')) ?></h2>
  <form method="post" action="<?= url($base . '/modifier') ?>"<?= $envoi ?>>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="champ">
      <label for="serveur-renommer"><?= e(t('srv.nom')) ?></label>
      <input type="text" id="serveur-renommer" name="nom" required maxlength="<?= Serveurs::NOM_MAX ?>" value="<?= e($nom) ?>" autocomplete="off">
    </div>
    <?= Vue::rendre('serveurs/_icones', ['choisie' => (string) $serveur['icone'], 'idPrefixe' => 'reglages']) ?>
    <div class="actions"><button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button></div>
  </form>
</section>
<?php endif; ?>

<section class="carte profil-ami__section" id="salons">
  <h2 style="margin-top:0"><?= e(t('srv.salons')) ?> <span class="discret profil-ami__nombre"><?= count($salons) ?></span></h2>
  <ul class="groupe-membres">
    <?php foreach ($salons as $s): ?>
      <li class="groupe-membres__ligne">
        <span class="groupe-membres__nom"><a href="<?= url('groupes/' . (int) $s['id']) ?>"># <?= e($s['nom']) ?></a></span>
        <?php if ($gere): ?>
          <span class="groupe-membres__actions">
            <form method="post" action="<?= url($base . '/salons/' . (int) $s['id'] . '/renommer') ?>" class="serveur-salon-renommer"<?= $envoi ?>>
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <label class="sr-only" for="salon-nom-<?= (int) $s['id'] ?>"><?= e(t('srv.renommer_salon')) ?></label>
              <input type="text" id="salon-nom-<?= (int) $s['id'] ?>" name="nom" required maxlength="<?= Serveurs::SALON_MAX ?>" value="<?= e($s['nom']) ?>" autocomplete="off">
              <button class="bouton bouton--secondaire bouton--petit" type="submit"><?= e(t('srv.renommer')) ?></button>
            </form>
            <?php if (count($salons) > 1): ?>
              <form method="post" action="<?= url($base . '/salons/' . (int) $s['id'] . '/supprimer') ?>"<?= $envoi ?>
                    data-confirmation="<?= e(t('srv.supprimer_salon_confirmation', ['nom' => $s['nom']])) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('srv.supprimer')) ?></button>
              </form>
            <?php endif; ?>
          </span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if ($gere && count($salons) < Serveurs::SALONS_MAX): ?>
    <form method="post" action="<?= url($base . '/salons') ?>" class="fuseau-choix" style="margin-top:.8rem"<?= $envoi ?>>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <label class="sr-only" for="salon-nouveau"><?= e(t('srv.ajouter_salon')) ?></label>
      <input type="text" id="salon-nouveau" name="nom" required maxlength="<?= Serveurs::SALON_MAX ?>" placeholder="<?= e(t('srv.salon_exemple')) ?>" autocomplete="off">
      <button class="bouton" type="submit"><?= e(t('srv.ajouter_salon')) ?></button>
    </form>
    <p class="champ__aide"><?= e(t('srv.salon_aide_nom')) ?></p>
  <?php endif; ?>
</section>

<section class="carte profil-ami__section" id="serveur-membres">
  <h2 style="margin-top:0"><?= e(t('srv.membres')) ?> <span class="discret profil-ami__nombre"><?= count($membres) ?></span></h2>
  <ul class="groupe-membres">
    <?php foreach ($membres as $m): ?>
      <li class="groupe-membres__ligne">
        <?= Amis::avatar($m['id'], $m['pseudo'], '', true) ?>
        <span class="groupe-membres__nom">
          <?= e($m['pseudo']) ?><?= $m['id'] === $moi ? ' <span class="discret">' . e(t('grp.vous')) . '</span>' : '' ?>
          <?php if ($m['role'] === 'proprietaire'): ?><span class="pastille">👑 <?= e(t('srv.role_proprietaire')) ?></span>
          <?php elseif ($m['role'] === 'admin'): ?><span class="pastille">🛡️ <?= e(t('srv.role_admin')) ?></span><?php endif; ?>
        </span>
        <?php if ($m['id'] !== $moi && $m['role'] !== 'proprietaire'): ?>
          <span class="groupe-membres__actions">
            <?php if ($proprietaire): ?>
              <form method="post" action="<?= url($base . '/membres/' . $m['id'] . ($m['role'] === 'admin' ? '/membre' : '/admin')) ?>"<?= $envoi ?>>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button class="bouton bouton--secondaire bouton--petit" type="submit"><?= e(t($m['role'] === 'admin' ? 'srv.retirer_admin' : 'srv.nommer_admin')) ?></button>
              </form>
            <?php endif; ?>
            <?php if ($gere && ($m['role'] === 'membre' || $proprietaire)): ?>
              <form method="post" action="<?= url($base . '/membres/' . $m['id'] . '/retirer') ?>"<?= $envoi ?>
                    data-confirmation="<?= e(t('srv.retirer_membre_confirmation', ['qui' => $m['pseudo']])) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('srv.retirer')) ?></button>
              </form>
            <?php endif; ?>
          </span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($invites !== []): ?>
    <h3 class="groupe-sous-titre"><?= e(t('srv.invites_attente')) ?></h3>
    <ul class="groupe-membres">
      <?php foreach ($invites as $inv): ?>
        <li class="groupe-membres__ligne">
          <?= Amis::avatar($inv['id'], $inv['pseudo']) ?>
          <span class="groupe-membres__nom"><?= e($inv['pseudo']) ?></span>
          <?php if ($gere): ?>
            <span class="groupe-membres__actions">
              <form method="post" action="<?= url($base . '/invitations/' . $inv['id'] . '/annuler') ?>"<?= $envoi ?>>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('srv.annuler_invitation')) ?></button>
              </form>
            </span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($gere): ?>
    <h3 class="groupe-sous-titre"><?= e(t('srv.inviter')) ?></h3>
    <?php if ($aInviter === []): ?>
      <p class="discret"><?= e(t('srv.plus_d_amis')) ?></p>
    <?php else: ?>
      <form method="post" action="<?= url($base . '/inviter') ?>" class="groupe-formulaire"<?= $envoi ?>>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <ul class="groupe-choix__liste">
          <?php foreach ($aInviter as $a): ?>
            <li>
              <label class="groupe-choix__ami">
                <input type="checkbox" name="amis[]" value="<?= (int) $a['id'] ?>">
                <?= Amis::avatar((int) $a['id'], (string) $a['pseudo']) ?>
                <span><?= e((string) $a['pseudo']) ?></span>
              </label>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="champ__aide"><?= e(t('srv.inviter_aide')) ?></p>
        <div class="actions"><button class="bouton" type="submit"><?= e(t('srv.inviter_bouton')) ?></button></div>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</section>

<section class="carte profil-ami__section profil-ami__danger">
  <?php if ($proprietaire): ?>
    <p class="discret" style="margin-top:0"><?= e(t('srv.proprietaire_aide')) ?></p>
    <form method="post" action="<?= url($base . '/supprimer') ?>" data-confirmation="<?= e(t('srv.supprimer_confirmation', ['nom' => $nom])) ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <button class="bouton bouton--danger" type="submit"><?= e(t('srv.supprimer_serveur')) ?></button>
    </form>
  <?php else: ?>
    <form method="post" action="<?= url($base . '/quitter') ?>" data-confirmation="<?= e(t('srv.quitter_confirmation', ['nom' => $nom])) ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <button class="bouton bouton--danger" type="submit"><?= e(t('srv.quitter')) ?></button>
    </form>
  <?php endif; ?>
</section>
