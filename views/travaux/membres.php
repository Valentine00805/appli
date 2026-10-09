<?php
/**
 * Les membres du projet, la discussion du groupe, le calendrier commun, le lien public, et les
 * réglages du projet.
 *
 * @var ?array $calendrier  le calendrier commun du projet, s'il en a un
 * @var array $projet
 * @var list<array> $membres
 * @var list<array> $amisAInviter
 * @var list<array> $discussions  les discussions de groupe dont je fais partie
 * @var string $onglet
 */
$dansUneFenetre = $dansUneFenetre ?? false;
// Dans la fenêtre, on y reste : les formulaires s'y enregistrent.
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$csrf = Session::jetonCsrf();
$admin = $projet['role'] === 'admin';
$jeton = $projet['jeton'] === null ? null : (string) $projet['jeton'];
?>
<?= Vue::rendre('travaux/_onglets', ['projet' => $projet, 'onglet' => $onglet, 'dansUneFenetre' => $dansUneFenetre]) ?>

<div class="colonnes">
  <div class="pile">
    <section class="carte">
      <h2><?= e(t('tr.me.membres')) ?> <span class="discret">(<?= count(array_filter($membres, static fn (array $m): bool => $m['statut'] === 'membre')) ?>)</span></h2>
      <ul class="travaux-membres">
        <?php foreach ($membres as $m): ?>
          <?php $moi = (int) ($m['user_id'] ?? 0) === Auth::id(); ?>
          <li class="travaux-membre">
            <?php if ($m['user_id'] !== null): ?>
              <?= Amis::avatar((int) $m['user_id'], (string) $m['nom_affiche'], 'avatar--mini') ?>
            <?php else: ?>
              <span class="avatar avatar--mini" aria-hidden="true">👤</span>
            <?php endif; ?>
            <span style="flex:1;min-width:0">
              <strong><?= e((string) $m['nom_affiche']) ?></strong><?= $moi ? ' <span class="discret">' . e(t('tr.me.moi')) . '</span>' : '' ?>
              <?php if ($m['role'] === 'admin'): ?><span class="pastille"><?= e(t('tr.me.administrateur')) ?></span><?php endif; ?>
              <?php if ($m['statut'] === 'invite'): ?><span class="pastille pastille--muette"><?= e(t('tr.me.invitation_envoyee')) ?></span><?php endif; ?>
              <?php if ($m['user_id'] === null): ?><span class="pastille pastille--muette"><?= e(t('tr.me.sans_compte')) ?></span><?php endif; ?>
              <?php if ($m['statut'] === 'membre'): ?>
                <br><span class="discret"><?= e(tn('tr.me.a_faire', (int) $m['a_faire'])) ?>
                  · <?= e(tn('tr.me.faites', (int) $m['faites'])) ?></span>
              <?php endif; ?>
            </span>
            <?php if ($admin && !$moi): ?>
              <span class="en-ligne">
                <?php if ($m['user_id'] !== null && $m['statut'] === 'membre'): ?>
                  <form method="post"<?= $envoi ?> action="<?= url('travaux/membres/' . (int) $m['id'] . ($m['role'] === 'admin' ? '/membre' : '/admin')) ?>" class="en-ligne">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button class="bouton bouton--discret bouton--petit" type="submit">
                      <?= e(t($m['role'] === 'admin' ? 'tr.me.retirer_admin' : 'tr.me.nommer_admin')) ?></button>
                  </form>
                <?php endif; ?>
                <form method="post"<?= $envoi ?> action="<?= url('travaux/membres/' . (int) $m['id'] . '/retirer') ?>" class="en-ligne"
                      data-confirmation="<?= e(t('tr.me.retirer_confirmation', ['qui' => (string) $m['nom_affiche']])) ?>">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t($m['statut'] === 'invite' ? 'tr.me.annuler_invitation' : 'tr.me.retirer')) ?></button>
                </form>
              </span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <?php if ($admin): ?>
      <section class="carte">
        <h2><?= e(t('tr.me.inviter_amis')) ?></h2>
        <?php if ($amisAInviter === []): ?>
          <p class="discret"><?= e(t('tr.me.tous_deja')) ?> <a href="<?= url('amis') ?>"><?= e(t('tr.me.en_ajouter')) ?></a>.</p>
        <?php else: ?>
          <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/inviter') ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <label class="discussions-recherche">
              <span class="sr-only"><?= e(t('tr.me.chercher_ami')) ?></span>
              <input type="search" placeholder="<?= e(t('tr.me.chercher_ami')) ?>" autocomplete="off" data-filtre-liste="[data-liste-amis-inviter]">
            </label>
            <ul class="groupe-choix__liste partage-liste" data-liste-amis-inviter>
              <?php foreach ($amisAInviter as $a): ?>
                <li data-nom="<?= e(mb_strtolower((string) $a['pseudo'])) ?>">
                  <label class="groupe-choix__ami">
                    <input type="checkbox" name="amis[]" value="<?= (int) $a['id'] ?>">
                    <?= Amis::avatar((int) $a['id'], (string) $a['pseudo'], 'avatar--mini') ?>
                    <span class="partage-liste__nom"><?= e((string) $a['pseudo']) ?></span>
                  </label>
                </li>
              <?php endforeach; ?>
            </ul>
            <p class="discret" data-filtre-vide hidden style="margin:.4rem 0 0"><?= e(t('tr.me.aucun_ami_nom')) ?></p>
            <button class="bouton bouton--petit" type="submit" style="margin-top:.6rem"><?= e(t('tr.me.inviter')) ?></button>
          </form>
        <?php endif; ?>
      </section>

      <section class="carte">
        <h2><?= e(t('tr.me.ajouter_sans_compte')) ?></h2>
        <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/sans-compte') ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <div class="champ">
            <label for="nom-sans-compte"><?= e(t('tr.me.son_nom')) ?></label>
            <input type="text" id="nom-sans-compte" name="nom" required maxlength="60" placeholder="<?= e(t('tr.me.nom_exemple')) ?>">
            <span class="champ__aide"><?= e(t('tr.me.sans_compte_aide')) ?></span>
          </div>
          <button class="bouton bouton--petit" type="submit"><?= e(t('tr.me.ajouter')) ?></button>
        </form>
      </section>
    <?php endif; ?>
  </div>

  <div class="pile">
    <section class="carte">
      <h2><?= e(t('tr.me.discussion')) ?></h2>
      <?php if ($projet['conversation_id'] !== null): ?>
        <p><a class="bouton bouton--bloc" href="<?= url('groupes/' . (int) $projet['conversation_id']) ?>"><?= e(t('tr.me.ouvrir_discussion')) ?></a></p>
        <p class="champ__aide"><?= e(t('tr.me.qui_rejoint')) ?></p>
        <?php if ($admin): ?>
          <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/discussion/delier') ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('tr.me.delier')) ?></button>
          </form>
        <?php endif; ?>
      <?php else: ?>
        <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/discussion') ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--bloc" type="submit"><?= e(t('tr.me.creer_discussion')) ?></button>
          <span class="champ__aide"><?= e(t('tr.me.avec_membres')) ?></span>
        </form>
        <?php if ($discussions !== []): ?>
          <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/discussion') ?>" style="margin-top:.8rem">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="champ">
              <label for="discussion-existante"><?= e(t('tr.me.relier_existante')) ?></label>
              <select id="discussion-existante" name="conversation_id">
                <?php foreach ($discussions as $d): ?>
                  <option value="<?= (int) $d['id'] ?>"><?= e((string) $d['nom']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="bouton bouton--secondaire bouton--petit" type="submit"><?= e(t('tr.me.relier')) ?></button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <section class="carte">
      <h2>📅 <?= e(t('cam.projet_titre')) ?></h2>
      <?php if ($calendrier === null): ?>
        <p class="discret"><?= e(t('cam.projet_aide')) ?></p>
        <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/calendrier') ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--secondaire bouton--bloc" type="submit"><?= e(t('cam.projet_creer')) ?></button>
        </form>
      <?php else: ?>
        <p style="display:flex;align-items:center;gap:.5rem;margin:.2rem 0">
          <span class="cam-pastille" style="background:<?= e($calendrier['couleur']) ?>" aria-hidden="true"></span>
          <strong><?= e($calendrier['nom']) ?></strong>
          <span class="discret"><?= e(tn('cam.membres_n', (int) $calendrier['membres'])) ?></span>
        </p>
        <p class="champ__aide"><?= e(t('cam.projet_existe')) ?></p>
        <div class="actions">
          <a class="bouton bouton--petit" href="<?= url('evenements/nouveau', ['agenda' => CalendriersAmis::cle((int) $calendrier['id'])]) ?>" data-fenetre>＋ <?= e(t('cam.evt_ajouter')) ?></a>
          <a class="bouton bouton--discret bouton--petit" href="<?= url('calendriers-amis/' . (int) $calendrier['id']) ?>" data-fenetre><?= e(t('cam.ouvrir')) ?></a>
        </div>
      <?php endif; ?>
    </section>

    <section class="carte">
      <h2><?= e(t('tr.me.lien_public')) ?></h2>
      <?php if ($jeton === null): ?>
        <p class="discret"><?= e(t('tr.me.lien_aide')) ?></p>
        <?php if ($admin): ?>
          <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/lien') ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button class="bouton bouton--secondaire bouton--bloc" type="submit"><?= e(t('tr.me.creer_lien')) ?></button>
          </form>
        <?php endif; ?>
      <?php else: ?>
        <div class="partage-lien" data-partage-lien>
          <label class="sr-only" for="lien-travaux"><?= e(t('tr.me.lien_du_projet')) ?></label>
          <input type="text" id="lien-travaux" readonly value="<?= e(Travaux::adresseLien($jeton)) ?>" data-partage-adresse>
          <button class="bouton" type="button" data-partage-copier><?= e(t('tr.me.copier')) ?></button>
        </div>
        <p class="champ__aide" data-partage-etat aria-live="polite"><?= e(t('tr.me.lecture_seule')) ?></p>
        <?php if ($admin): ?>
          <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/lien/fermer') ?>"
                data-confirmation="<?= e(t('tr.me.desactiver_confirmation')) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('tr.me.desactiver')) ?></button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <?php if ($admin): ?>
      <details class="carte">
        <summary><strong><?= e(t('tr.me.reglages')) ?></strong></summary>
        <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/modifier') ?>" style="margin-top:.8rem">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <div class="champ">
            <label for="nom-projet"><?= e(t('tr.me.nom')) ?></label>
            <input type="text" id="nom-projet" name="nom" required maxlength="<?= Travaux::NOM_MAX ?>" value="<?= e((string) $projet['nom']) ?>">
          </div>
          <div class="champ">
            <label for="description-projet"><?= e(t('tr.me.sujet')) ?></label>
            <textarea id="description-projet" name="description" rows="3" maxlength="2000"><?= e((string) ($projet['description'] ?? '')) ?></textarea>
          </div>
          <button class="bouton bouton--petit" type="submit"><?= e(t('tr.me.enregistrer')) ?></button>
        </form>
        <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/supprimer') ?>" style="margin-top:1rem"
              data-confirmation="<?= e(t('tr.me.supprimer_confirmation', ['nom' => (string) $projet['nom']])) ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('tr.me.supprimer')) ?></button>
        </form>
      </details>
    <?php endif; ?>

    <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/quitter') ?>"
          data-confirmation="<?= e(t('tr.me.quitter_confirmation', ['nom' => (string) $projet['nom']])) ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('tr.me.quitter')) ?></button>
    </form>
  </div>
</div>
