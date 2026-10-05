<?php
/**
 * Mes travaux de groupe, les invitations reçues, et de quoi en créer un.
 *
 * @var list<array> $projets
 * @var list<array> $invitations
 * @var list<array> $mesTaches  mes tâches à faire, tous projets confondus
 * @var ?array $aOuvrir  le groupe à ouvrir aussitôt dans une fenêtre (une invitation vient d'être acceptée)
 */
$aOuvrir = $aOuvrir ?? null;
$csrf = Session::jetonCsrf();
?>
<div class="entete-page">
  <div>
    <h1><?= e(t('tr.li.titre')) ?></h1>
    <p><?= e(t('tr.li.aide')) ?></p>
  </div>
  <a class="bouton" href="<?= url('travaux/nouveau') ?>" data-fenetre><?= e(t('tr.li.nouveau')) ?></a>
</div>

<?php if ($aOuvrir !== null): ?>
  <?php // Le script clique ici tout seul : le groupe s'ouvre en fenêtre. Sans script, le lien reste, à cliquer. ?>
  <p class="carte"><a href="<?= url('travaux/' . (int) $aOuvrir['id']) ?>" data-fenetre data-ouvrir-auto>
    <?= e(t('tr.li.ouvrir', ['nom' => (string) $aOuvrir['nom']])) ?></a></p>
<?php endif; ?>

<?php if ($invitations !== []): ?>
  <section class="carte" style="margin-bottom:1.25rem">
    <h2><?= e(t('tr.li.on_vous_invite')) ?></h2>
    <ul class="pile" style="list-style:none;padding:0;margin:0">
      <?php foreach ($invitations as $i): ?>
        <li class="travaux-invitation">
          <span>
            <strong><?= e((string) $i['nom']) ?></strong>
            <span class="discret"><?= e(t('tr.li.invite_par', ['qui' => (string) ($i['invite_par_nom'] ?? t('tr.li.ancien_membre'))])) ?>
              · <?= e(tn('tr.li.membres', (int) $i['nb_membres'])) ?></span>
            <?php if ((string) ($i['description'] ?? '') !== ''): ?>
              <br><span class="discret"><?= e(extrait((string) $i['description'], 140)) ?></span>
            <?php endif; ?>
          </span>
          <span class="en-ligne">
            <form method="post" action="<?= url('travaux/' . (int) $i['id'] . '/rejoindre') ?>" class="en-ligne">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--petit" type="submit"><?= e(t('tr.li.rejoindre')) ?></button>
            </form>
            <form method="post" action="<?= url('travaux/' . (int) $i['id'] . '/refuser') ?>" class="en-ligne">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('tr.li.refuser')) ?></button>
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
        <p><?= e(t('tr.li.aucun')) ?></p>
        <p><a class="bouton bouton--secondaire" href="<?= url('travaux/nouveau') ?>" data-fenetre><?= e(t('tr.li.creer_premier')) ?></a></p>
      </div>
    <?php endif; ?>

    <div class="grille grille--2">
      <?php foreach ($projets as $p): ?>
        <?php $avancement = Travaux::avancement((int) $p['nb_taches'], (int) $p['nb_faites']); ?>
        <a class="carte travaux-carte" href="<?= url('travaux/' . (int) $p['id']) ?>" data-fenetre>
          <h2 class="travaux-carte__titre"><?= e((string) $p['nom']) ?></h2>
          <p class="discret" style="margin:0">
            <?= e(tn('tr.li.membres', (int) $p['nb_membres'])) ?>
            <?php if ($p['role'] === 'admin'): ?><?= e(t('tr.li.administrateur')) ?><?php endif; ?>
          </p>
          <?php if ($avancement !== null): ?>
            <div class="jauge" title="<?= e(t('tr.li.part_faite', ['n' => $avancement])) ?>">
              <span style="width:<?= $avancement ?>%;background:var(--accent)"></span>
            </div>
            <p class="discret" style="margin:0"><?= e(t('tr.li.taches_faites', [
                'faites' => (int) $p['nb_faites'],
                'total' => (int) $p['nb_taches'],
            ])) ?>
              <?php if ((int) $p['mes_taches'] > 0): ?>
                · <strong><?= e(t('tr.li.pour_moi', ['n' => (int) $p['mes_taches']])) ?></strong>
              <?php endif; ?>
            </p>
          <?php else: ?>
            <p class="discret" style="margin:.6rem 0 0"><?= e(t('tr.li.pas_de_tache')) ?></p>
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
        <h2><?= e(t('tr.li.a_faire')) ?></h2>
        <ul class="travaux-mes-taches">
          <?php foreach ($mesTaches as $t): ?>
            <li>
              <a href="<?= url('travaux/' . (int) $t['projet_id']) ?>" data-fenetre><?= e($t['membre_id'] === null ? t('cal.tache_sans_personne', ['titre' => (string) $t['titre']]) : (string) $t['titre']) ?></a>
              <span class="travaux-projet" title="<?= e(t('tr.li.le_projet')) ?>">👥 <?= e((string) $t['projet_nom']) ?></span>
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
