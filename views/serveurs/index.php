<?php
/**
 * Mes serveurs : ceux où je suis, les invitations reçues, et de quoi en créer un.
 *
 * @var list<array{id: int, nom: string, icone: string, role: string, membres: int, non_lus: int, premier_salon: ?int}> $serveurs
 * @var list<array{id: int, nom: string, icone: string, par: string, membres: int}> $invitations
 */
$csrf = Session::jetonCsrf();
?>
<div class="avec-barre">
<?= Vue::rendre('serveurs/_barre', ['barreActive' => 'serveurs']) ?>
<div class="avec-barre__page">
<div class="entete-page">
  <div>
    <h1>🏰 <?= e(t('srv.titre')) ?></h1>
    <p><?= e(t('srv.sous_titre')) ?></p>
  </div>
  <div class="serveurs__actions">
    <a class="bouton" href="<?= url('serveurs/nouveau') ?>" data-fenetre><?= e(t('srv.creer')) ?></a>
    <a class="bouton bouton--secondaire" href="<?= url('amis') ?>"><?= e(t('srv.retour_amis')) ?></a>
  </div>
</div>

<?php if ($invitations !== []): ?>
  <section class="carte serveurs__invitations" aria-labelledby="srv-invitations">
    <h2 id="srv-invitations" style="margin-top:0"><?= e(t('srv.invitations')) ?> <span class="compteur"><?= count($invitations) ?></span></h2>
    <ul class="serveurs__liste">
      <?php foreach ($invitations as $inv): ?>
        <li class="serveur-carte serveur-carte--invitation">
          <span class="serveur-icone serveur-icone--grand" aria-hidden="true"><?= e($inv['icone']) ?></span>
          <span class="serveur-carte__texte">
            <strong><?= e($inv['nom']) ?></strong>
            <span class="discret"><?= e(t('srv.invite_par', ['qui' => $inv['par'] !== '' ? $inv['par'] : t('grp.quelquun'), 'membres' => tn('srv.membres_n', $inv['membres'])])) ?></span>
          </span>
          <span class="serveur-carte__actions">
            <form method="post" action="<?= url('serveurs/' . $inv['id'] . '/accepter') ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--petit" type="submit"><?= e(t('srv.accepter')) ?></button>
            </form>
            <form method="post" action="<?= url('serveurs/' . $inv['id'] . '/refuser') ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('srv.refuser')) ?></button>
            </form>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<section class="carte" aria-labelledby="srv-mes-serveurs">
  <h2 id="srv-mes-serveurs" style="margin-top:0"><?= e(t('srv.mes_serveurs')) ?></h2>
  <?php if ($serveurs === []): ?>
    <div class="vide">
      <p><?= e(t('srv.aucun')) ?></p>
      <a class="bouton" href="<?= url('serveurs/nouveau') ?>" data-fenetre><?= e(t('srv.creer')) ?></a>
    </div>
  <?php else: ?>
    <ul class="serveurs__liste">
      <?php foreach ($serveurs as $s): ?>
        <li>
          <a class="serveur-carte" href="<?= url('serveurs/' . $s['id']) ?>">
            <span class="serveur-icone serveur-icone--grand" aria-hidden="true"><?= e($s['icone']) ?></span>
            <span class="serveur-carte__texte">
              <strong><?= e($s['nom']) ?></strong>
              <span class="discret"><?= e(tn('srv.membres_n', $s['membres'])) ?><?php if ($s['role'] === 'proprietaire'): ?> · 👑 <?= e(t('srv.role_proprietaire')) ?><?php elseif ($s['role'] === 'admin'): ?> · 🛡️ <?= e(t('srv.role_admin')) ?><?php endif; ?></span>
            </span>
            <?php if ($s['non_lus'] > 0): ?>
              <span class="compteur" title="<?= e(tn('ami.non_lus', $s['non_lus'])) ?>"><?= $s['non_lus'] > 99 ? '99+' : $s['non_lus'] ?></span>
            <?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
</div>
</div>
