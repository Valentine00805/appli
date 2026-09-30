<?php
/** @var array $resume, $libelles */
$csrf = Session::jetonCsrf();
$aDesDonnees = $resume['lignes'] > 0;
?>

<div class="entete-page">
  <div>
    <p class="discret" style="margin-bottom:.35rem">
      <a href="<?= url('compte') ?>"><?= e(t('notif.retour_compte')) ?></a>
    </p>
    <h1><?= e(t('svg.titre')) ?></h1>
    <p><?= e(t('svg.sous_titre')) ?></p>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <section class="carte" style="border-color:var(--accent)">
      <h2><?= e(t('svg.telecharger_titre')) ?></h2>
      <p class="discret" style="margin:.3rem 0 1rem">
        <?= t('svg.telecharger_aide') ?>
      </p>

      <?php if (!$aDesDonnees): ?>
        <p class="discret"><?= e(t('svg.vide')) ?></p>
      <?php else: ?>
        <a class="bouton bouton--bloc" href="<?= url('compte/sauvegarde/export') ?>">
          <?= e(t('svg.telecharger')) ?>
        </a>
        <p class="champ__aide" style="margin-top:.6rem">
          <?= e(t('svg.lignes', ['n' => (int) $resume['lignes']])) ?>
          <?= e(tn('svg.pieces', (int) $resume['fichiers'])) ?>
          <?php if ((int) $resume['octets'] > 0): ?>
            (<?= e(taille_lisible((int) $resume['octets'])) ?>)
          <?php endif; ?>.
          <?= e(t('svg.preparation')) ?>
        </p>
      <?php endif; ?>
    </section>

    <section class="carte">
      <h2><?= e(t('svg.restaurer_titre')) ?></h2>
      <p class="discret" style="margin:.3rem 0 .9rem">
        <?= e(t('svg.restaurer_aide')) ?>
      </p>

      <div class="flash flash--erreur" style="margin-bottom:1rem">
        <?= t('svg.avertissement') ?>
      </div>

      <form method="post" action="<?= url('compte/sauvegarde/restaurer') ?>" enctype="multipart/form-data"
            data-confirmation="<?= e(t('svg.restaurer_sur')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

        <div class="champ">
          <label for="archive"><?= e(t('svg.fichier')) ?></label>
          <input type="file" id="archive" name="archive" accept=".zip,application/zip" required>
          <span class="champ__aide"><?= t('svg.fichier_aide') ?></span>
        </div>

        <label class="case" style="margin-bottom:1rem">
          <input type="checkbox" name="confirmation" value="1" required>
          <?= e(t('svg.je_comprends')) ?>
        </label>

        <button class="bouton bouton--danger" type="submit"><?= e(t('svg.restaurer')) ?></button>
      </form>
    </section>
  </div>

  <div class="pile">
    <?php if ($aDesDonnees): ?>
      <div class="carte">
        <h2><?= e(t('svg.ce_qui_est')) ?></h2>
        <table class="tableau">
          <tbody>
            <?php foreach ($resume['detail'] as $table => $n): ?>
              <?php if ($n === 0) { continue; } ?>
              <tr>
                <th scope="row" style="font-weight:500"><?= e($libelles[$table] ?? $table) ?></th>
                <td class="nombre"><?= (int) $n ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="champ__aide" style="margin-top:.6rem">
          <?= e(t('svg.identifiants')) ?>
        </p>
      </div>
    <?php endif; ?>

    <div class="carte">
      <h2><?= e(t('svg.pourquoi')) ?></h2>
      <p class="discret" style="margin-bottom:.6rem">
        <?= t('svg.pourquoi_1') ?>
      </p>
      <p class="discret" style="margin:0">
        <?= e(t('svg.pourquoi_2')) ?>
      </p>
    </div>

    <div class="carte">
      <h2><?= e(t('svg.rythme')) ?></h2>
      <p class="discret" style="margin:0">
        <?= e(t('svg.rythme_aide')) ?>
      </p>
    </div>
  </div>
</div>
