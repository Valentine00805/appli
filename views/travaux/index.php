<?php
/**
 * Mes travaux de groupe, les invitations reçues, et de quoi en créer un.
 *
 * @var list<array> $projets
 * @var list<array> $invitations
 * @var list<array> $mesTaches  mes tâches à faire, tous projets confondus
 */
$csrf = Session::jetonCsrf();
?>
<div class="entete-page">
  <div>
    <h1>👥 Travaux de groupe</h1>
    <p>Qui fait quoi, les fichiers, un document écrit ensemble, et les échéances dans le calendrier de chacun.</p>
  </div>
  <a class="bouton" href="<?= url('travaux/nouveau') ?>" data-fenetre>+ Nouveau travail de groupe</a>
</div>

<?php if ($invitations !== []): ?>
  <section class="carte" style="margin-bottom:1.25rem">
    <h2>✉️ On vous invite</h2>
    <ul class="pile" style="list-style:none;padding:0;margin:0">
      <?php foreach ($invitations as $i): ?>
        <li class="travaux-invitation">
          <span>
            <strong><?= e((string) $i['nom']) ?></strong>
            <span class="discret">· invité par <?= e((string) ($i['invite_par_nom'] ?? 'un ancien membre')) ?>
              · <?= (int) $i['nb_membres'] ?> membre<?= (int) $i['nb_membres'] > 1 ? 's' : '' ?></span>
            <?php if ((string) ($i['description'] ?? '') !== ''): ?>
              <br><span class="discret"><?= e(extrait((string) $i['description'], 140)) ?></span>
            <?php endif; ?>
          </span>
          <span class="en-ligne">
            <form method="post" action="<?= url('travaux/' . (int) $i['id'] . '/rejoindre') ?>" class="en-ligne">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--petit" type="submit">Rejoindre</button>
            </form>
            <form method="post" action="<?= url('travaux/' . (int) $i['id'] . '/refuser') ?>" class="en-ligne">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--discret bouton--petit" type="submit">Refuser</button>
            </form>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<?php // Sans tâche à faire, la liste prend toute la largeur. ?>
<div class="<?= $mesTaches === [] ? 'pile' : 'colonnes' ?>">
  <div class="pile">
    <?php if ($projets === []): ?>
      <div class="vide">
        <span class="vide__icone">👥</span>
        <p>Aucun travail de groupe pour l’instant. Créez le premier, et invitez-y vos amis.</p>
        <p><a class="bouton bouton--secondaire" href="<?= url('travaux/nouveau') ?>" data-fenetre>Créer le premier</a></p>
      </div>
    <?php endif; ?>

    <div class="grille grille--2">
      <?php foreach ($projets as $p): ?>
        <?php $avancement = Travaux::avancement((int) $p['nb_taches'], (int) $p['nb_faites']); ?>
        <a class="carte travaux-carte" href="<?= url('travaux/' . (int) $p['id']) ?>">
          <h2 class="travaux-carte__titre"><?= e((string) $p['nom']) ?></h2>
          <p class="discret" style="margin:0">
            <?= (int) $p['nb_membres'] ?> membre<?= (int) $p['nb_membres'] > 1 ? 's' : '' ?>
            <?php if ($p['role'] === 'admin'): ?>· administrateur<?php endif; ?>
          </p>
          <?php if ($avancement !== null): ?>
            <div class="jauge" title="<?= $avancement ?> % des tâches faites">
              <span style="width:<?= $avancement ?>%;background:var(--accent)"></span>
            </div>
            <p class="discret" style="margin:0"><?= (int) $p['nb_faites'] ?> / <?= (int) $p['nb_taches'] ?> tâches faites
              <?php if ((int) $p['mes_taches'] > 0): ?>
                · <strong><?= (int) $p['mes_taches'] ?> pour moi</strong>
              <?php endif; ?>
            </p>
          <?php else: ?>
            <p class="discret" style="margin:.6rem 0 0">Pas encore de tâche répartie.</p>
          <?php endif; ?>
          <?php if ($p['prochaine'] !== null): ?>
            <p style="margin:.5rem 0 0">📅 <?= e((string) $p['prochaine_titre']) ?>
              <span class="discret">· <?= e(date_fr((string) $p['prochaine'], substr((string) $p['prochaine'], 11) !== '00:00:00')) ?></span></p>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="pile">
    <?php if ($mesTaches !== []): ?>
      <section class="carte">
        <h2>✅ Ce que j’ai à faire</h2>
        <ul class="travaux-mes-taches">
          <?php foreach ($mesTaches as $t): ?>
            <li>
              <a href="<?= url('travaux/' . (int) $t['projet_id']) ?>"><?= e((string) $t['titre']) ?></a>
              <span class="discret">· <?= e((string) $t['projet_nom']) ?></span>
              <?php $texte = echeance_libelle($t['echeance']); ?>
              <?php if ($texte !== ''): ?>
                <span class="echeance echeance--<?= e(echeance_etat($t['echeance'])) ?>"><?= e($texte) ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  </div>
</div>
