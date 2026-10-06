<?php
/**
 * Inviter des amis dans un serveur, dans leur propre fenêtre : la liste de ceux qu'on peut inviter, et les invitations en attente.
 * Ouverte par le « ＋ » de la colonne des membres ; réservée aux administrateurs.
 *
 * @var array $serveur
 * @var list<array> $aInviter  mes amis qui ne sont ni dedans ni déjà invités
 * @var list<array{id: int, pseudo: string}> $invites  les invitations en attente
 * @var int $membres  le nombre de membres
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$id = (int) $serveur['id'];
$csrf = Session::jetonCsrf();
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$base = 'serveurs/' . $id;
?>
<div class="entete-page profil-ami"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div class="profil-ami__identite">
    <?= Serveurs::pastille((string) $serveur['nom'], Serveurs::adressePhoto($id, $serveur['photo_nom']), 'serveur-icone serveur-icone--grand', $serveur['couleur']) ?>
    <div>
      <?php if (!$dansUneFenetre): ?>
        <p class="discret" style="margin:0 0 .2rem"><a href="<?= url($base) ?>"><?= e(t('srv.retour_serveur')) ?></a></p>
      <?php endif; ?>
      <h1 style="margin:0"><?= e(t('srv.inviter')) ?></h1>
      <p class="discret" style="margin:.15rem 0 0"><?= e((string) $serveur['nom']) ?> · <?= e(tn('srv.membres_n', $membres)) ?></p>
    </div>
  </div>
</div>

<section class="carte profil-ami__section">
  <?php if ($aInviter === []): ?>
    <p class="discret" style="margin:0"><?= e(t('srv.plus_d_amis')) ?></p>
  <?php else: ?>
    <form method="post" action="<?= url($base . '/inviter') ?>" class="groupe-formulaire"<?= $envoi ?>>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="retour" value="inviter">
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

  <?php if ($invites !== []): ?>
    <h3 class="groupe-sous-titre"><?= e(t('srv.invites_attente')) ?></h3>
    <ul class="groupe-membres">
      <?php foreach ($invites as $inv): ?>
        <li class="groupe-membres__ligne">
          <?= Amis::avatar($inv['id'], $inv['pseudo']) ?>
          <span class="groupe-membres__nom"><?= e($inv['pseudo']) ?></span>
          <span class="groupe-membres__actions">
            <form method="post" action="<?= url($base . '/invitations/' . $inv['id'] . '/annuler') ?>"<?= $envoi ?>>
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="retour" value="inviter">
              <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('srv.annuler_invitation')) ?></button>
            </form>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>