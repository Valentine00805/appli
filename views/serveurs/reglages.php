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
    <?= Serveurs::pastille($nom, $photo, 'serveur-icone serveur-icone--enorme', $serveur['couleur']) ?>
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
      <?= Serveurs::pastille($nom, $photo, 'avatar avatar--apercu serveur-apercu' . ($photo !== null ? ' avatar--photo' : ''), $serveur['couleur']) ?>
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

<?php if ($photo === null): ?>
<?php // La couleur du fond des initiales (sans photo) : l'aperçu du logo, plus haut, change aussitôt. ?>
<?php // La couleur se lit d'abord ; « Modifier » ouvre le choix, qui s'enregistre ou s'annule. ?>
<section class="carte profil-ami__section" id="serveur-couleur" data-reglage>
  <h2 style="margin-top:0"><?= e(t('srv.couleur')) ?></h2>
  <div class="reglage-lecture" data-reglage-lecture>
    <p class="reglage-lecture__valeur serveur-couleur-lecture">
      <span class="serveur-couleur-lecture__pastille" style="background: <?= e(Serveurs::couleurValide($serveur['couleur']) ?? Serveurs::couleur($nom)) ?>" aria-hidden="true"></span>
      <?= e($serveur['couleur'] === null ? t('srv.couleur_auto') : (string) $serveur['couleur']) ?>
    </p>
    <button class="bouton bouton--secondaire" type="button" data-reglage-modifier><?= e(t('commun.modifier')) ?></button>
  </div>
  <form method="post" action="<?= url($base . '/couleur') ?>" data-reglage-edition hidden<?= $envoi ?>>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <?= Vue::rendre('serveurs/_couleurs', ['choisie' => $serveur['couleur'], 'automatique' => Serveurs::couleur($nom)]) ?>
    <div class="actions">
      <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
      <button class="bouton bouton--discret" type="button" data-reglage-annuler><?= e(t('commun.annuler')) ?></button>
    </div>
  </form>
</section>
<?php endif; ?>

<?php // Le nom se lit d'abord ; « Modifier » ouvre le champ, qui s'enregistre ou s'annule (revient au nom enregistré). ?>
<section class="carte profil-ami__section" id="serveur-nom" data-reglage>
  <h2 style="margin-top:0"><?= e(t('srv.nom_titre')) ?></h2>
  <div class="reglage-lecture" data-reglage-lecture>
    <p class="reglage-lecture__valeur"><?= e($nom) ?></p>
    <button class="bouton bouton--secondaire" type="button" data-reglage-modifier><?= e(t('commun.modifier')) ?></button>
  </div>
  <form method="post" action="<?= url($base . '/modifier') ?>" data-reglage-edition hidden<?= $envoi ?>>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label class="legende" for="serveur-renommer"><?= e(t('srv.nom')) ?></label>
    <div class="fuseau-choix">
      <input type="text" id="serveur-renommer" name="nom" required maxlength="<?= Serveurs::NOM_MAX ?>"
             value="<?= e($nom) ?>" data-valeur-actuelle="<?= e($nom) ?>" autocomplete="off">
      <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
      <button class="bouton bouton--discret" type="button" data-reglage-annuler><?= e(t('commun.annuler')) ?></button>
    </div>
  </form>
</section>
<?php endif; ?>

<section class="carte profil-ami__section" id="salons">
  <h2 style="margin-top:0"><?= e(t('srv.salons')) ?> <span class="discret profil-ami__nombre"><?= count($salons) ?></span></h2>
  <ul class="serveur-salons-lecture">
    <?php foreach ($salons as $s): ?>
      <li><a href="<?= url('groupes/' . (int) $s['id']) ?>"># <?= e($s['nom']) ?></a></li>
    <?php endforeach; ?>
  </ul>
  <?php if ($gere): ?>
    <p style="margin:.8rem 0 0"><a class="bouton bouton--secondaire" href="<?= url($base . '/salons') ?>" data-fenetre><?= e(t('srv.gerer_salons')) ?></a></p>
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
