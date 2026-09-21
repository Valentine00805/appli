<?php
/**
 * Mes travaux de groupe, les invitations reçues, et de quoi en créer un.
 *
 * @var list<array> $projets
 * @var list<array> $invitations
 * @var list<array> $amis
 * @var list<array> $mesTaches  mes tâches à faire, tous projets confondus
 */
$csrf = Session::jetonCsrf();
?>
<div class="entete-page">
  <div>
    <h1>👥 Travaux de groupe</h1>
    <p>Qui fait quoi, les fichiers, un document écrit ensemble, et les échéances dans le calendrier de chacun.</p>
  </div>
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

<div class="colonnes">
  <div class="pile">
    <?php if ($projets === []): ?>
      <div class="vide">
        <span class="vide__icone">👥</span>
        <p>Aucun travail de groupe pour l’instant. Créez le premier, et invitez-y vos amis.</p>
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

    <section class="carte">
      <h2>Nouveau travail de groupe</h2>
      <form method="post" action="<?= url('travaux') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="champ">
          <label for="nom-projet">Nom</label>
          <input type="text" id="nom-projet" name="nom" required maxlength="<?= Travaux::NOM_MAX ?>"
                 placeholder="Exposé d’histoire, projet de fin d’année…">
        </div>
        <div class="champ">
          <label for="description-projet">Le sujet, les consignes <span class="discret">(facultatif)</span></label>
          <textarea id="description-projet" name="description" rows="3" maxlength="2000"></textarea>
        </div>
        <div class="champ">
          <span class="legende">Inviter des amis <span class="discret">(ils acceptent ou refusent)</span></span>
          <?php if ($amis === []): ?>
            <p class="discret" style="margin:.3rem 0 0">Pas encore d’amis dans l’appli :
              <a href="<?= url('amis') ?>">en ajouter</a>. Vous pourrez aussi ajouter des personnes sans compte.</p>
          <?php else: ?>
            <label class="discussions-recherche">
              <span class="sr-only">Rechercher un ami</span>
              <input type="search" placeholder="Rechercher un ami" autocomplete="off" data-filtre-liste="[data-liste-amis-projet]">
            </label>
            <ul class="groupe-choix__liste partage-liste" data-liste-amis-projet>
              <?php foreach ($amis as $a): ?>
                <li data-nom="<?= e(mb_strtolower((string) $a['pseudo'])) ?>">
                  <label class="groupe-choix__ami">
                    <input type="checkbox" name="amis[]" value="<?= (int) $a['id'] ?>">
                    <?= Amis::avatar((int) $a['id'], (string) $a['pseudo'], 'avatar--mini') ?>
                    <span class="partage-liste__nom"><?= e((string) $a['pseudo']) ?></span>
                  </label>
                </li>
              <?php endforeach; ?>
            </ul>
            <p class="discret" data-filtre-vide hidden style="margin:.4rem 0 0">Aucun ami ne porte ce nom.</p>
          <?php endif; ?>
        </div>
        <button class="bouton bouton--bloc" type="submit">Créer</button>
      </form>
    </section>
  </div>
</div>
