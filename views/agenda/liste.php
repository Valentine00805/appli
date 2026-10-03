<?php
/**
 * Les agendas qu'on peut relier, et où chacun en est.
 *
 * @var array $etats  un par fournisseur : [f, configure, relie, compte,
 *                    evenements, envoyes, souci]
 * @var string $vue  la vue du calendrier qu'on retrouve en arrivant
 * @var bool $dansUneFenetre  rendu seul, pour être posé dans une fenêtre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$relies = array_filter($etats, static fn (array $e): bool => $e['relie']);
?>

<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p class="discret" style="margin-bottom:.35rem">
        <a href="<?= url('calendrier') ?>"><?= e(t('agenda.retour_calendrier')) ?></a>
      </p>
    <?php endif; ?>
    <h1><?= e(t('agenda.titre')) ?></h1>
    <p><?= e(t('agenda.sous_titre')) ?></p>
  </div>

  <?php if ($relies !== []): ?>
    <div class="actions">
      <form method="post" action="<?= url('agenda/synchroniser') ?>">
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
        <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
        <button class="bouton" type="submit"><?= e(t('agenda.tout_synchroniser')) ?></button>
      </form>
    </div>
  <?php endif; ?>
</div>

<div class="pile">
  <?php
  /*
   * La vue qu'on retrouve en arrivant.
   *
   * Le mois s'imposait à tous. Il convient à qui prend du recul sur son
   * trimestre, moins à qui vit sa semaine heure par heure : celui-là
   * commençait chaque visite par un clic pour arriver là où il voulait être.
   *
   * Le réglage ne ferme rien : changer de vue depuis le calendrier reste un
   * clic, et ne touche pas à ce choix-ci.
   */
  ?>
  <section class="carte">
    <form method="post" action="<?= url('agenda/vue') ?>" data-auto-envoi>
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
      <div class="champ" style="max-width:22rem;margin:0">
        <label for="vue-calendrier"><?= e(t('agenda.vue_ouverture')) ?></label>
        <select id="vue-calendrier" name="vue">
          <?php foreach (['jour', 'semaine', 'mois', 'annee', 'liste'] as $cle): ?>
            <option value="<?= e($cle) ?>"<?= $vue === $cle ? ' selected' : '' ?>>
              <?= e(t('cal.' . $cle)) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="champ__aide">
          <?= e(t('agenda.vue_aide')) ?>
        </span>
      </div>
      <noscript>
        <button class="bouton bouton--secondaire bouton--petit" type="submit"
                style="margin-top:.5rem"><?= e(t('commun.enregistrer')) ?></button>
      </noscript>
    </form>
  </section>

  <?php foreach ($etats as $etat): ?>
    <?php $f = $etat['f']; ?>
    <section class="carte agenda-carte">
      <div class="agenda-carte__titre">
        <h2><?= e($f->nom()) ?></h2>
        <?php if (!$etat['configure']): ?>
          <span class="pastille pastille--muette"><?= e(t('agenda.non_active')) ?></span>
        <?php elseif ($etat['relie']): ?>
          <span class="pastille pastille--ok"><?= e(t('agenda.relie')) ?></span>
        <?php else: ?>
          <span class="pastille pastille--muette"><?= e(t('agenda.non_relie')) ?></span>
        <?php endif; ?>
      </div>

      <?php if (!$etat['configure']): ?>
        <p class="champ__aide" style="margin-top:0">
          <?= e(t('agenda.non_active_aide')) ?>
        </p>
      <?php elseif ($etat['relie']): ?>
        <p class="champ__aide" style="margin-top:0">
          <?= $etat['compte'] === '' ? e(t('agenda.compte_relie')) : e($etat['compte']) ?>
          <?php if ($etat['evenements'] > 0 || $etat['envoyes'] > 0): ?>
            · <?= e(tn('agenda.venus', (int) $etat['evenements'])) ?>
            · <?= e(tn('agenda.partis', (int) $etat['envoyes'])) ?>
          <?php endif; ?>
        </p>
        <?php if ($etat['souci'] !== null): ?>
          <p class="outlook-attention">
            <strong><?= e(t('agenda.echec')) ?></strong>
            (<?= e(t('date.le_a', ['date' => date_numerique($etat['souci']['quand']),
                                  'heure' => heure_courte((int) strtotime($etat['souci']['quand']))])) ?>) :
            <?= e($etat['souci']['quoi']) ?>
          </p>
        <?php endif; ?>
      <?php else: ?>
        <p class="champ__aide" style="margin-top:0">
          <?= e(t('agenda.non_relie_aide')) ?>
        </p>
      <?php endif; ?>

      <a class="bouton <?= $etat['relie'] ? 'bouton--secondaire' : '' ?>"
         href="<?= url('agenda/' . $f->cle()) ?>"
         <?= $dansUneFenetre ? 'data-fenetre' : '' ?>>
        <?= e(t($etat['relie'] ? 'agenda.reglages' : ($etat['configure'] ? 'agenda.relier_mon_compte' : 'agenda.en_savoir_plus'))) ?>
      </a>
    </section>
  <?php endforeach; ?>
</div>
