<?php
/**
 * Créer une tâche sans quitter la page d'où l'on vient — le tableau.
 *
 * Deux formulaires côte à côte : une nouvelle tâche principale, ou une
 * sous-tâche rangée dans la tâche principale de son choix (c'est elle qui
 * paraît au tableau). Ils envoient aux mêmes adresses que la page « Tâches » ;
 * « depuis » leur dit de revenir ici, et « retour » où aller une fois fait.
 *
 * @var array  $listes  les tâches principales, pour y ranger la sous-tâche
 * @var array  $palette, $icones
 * @var string $retour  la page d'où l'on vient, déjà vérifiée ; '' sinon
 * @var bool   $dansUneFenetre  rendue seule, pour être posée dans une fenêtre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$surPlace = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$csrf = Session::jetonCsrf();
$champsCommuns = '<input type="hidden" name="_csrf" value="' . e($csrf) . '">'
    . '<input type="hidden" name="depuis" value="nouvelle">'
    . '<input type="hidden" name="retour" value="' . e($retour) . '">';
?>

<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p class="discret" style="margin-bottom:.35rem">
        <a href="<?= e($retour !== '' ? $retour : url('tableau')) ?>"><?= e(t('commun.retour')) ?></a>
      </p>
    <?php endif; ?>
    <h1><?= e(t('taches.nouvelle_titre')) ?></h1>
    <p class="discret" style="margin:0">
      <?= e(t('taches.nouvelle_aide')) ?>
    </p>
  </div>
</div>

<div class="nouvelle-tache-choix">
  <section class="carte">
    <h2 style="margin-top:0"><?= e(t('taches.principale')) ?></h2>
    <form method="post" action="<?= url('taches/listes') ?>"<?= $surPlace ?>>
      <?= $champsCommuns ?>

      <div class="champ">
        <label for="nt-nom"><?= e(t('taches.nom')) ?></label>
        <input type="text" id="nt-nom" name="nom" required maxlength="120" placeholder="<?= e(t('taches.nom_exemple')) ?>">
      </div>

      <div class="champ">
        <label for="nt-ech-liste"><?= e(t('taches.echeance')) ?> <span class="discret"><?= e(t('taches.echeance_facultative')) ?></span></label>
        <input type="date" id="nt-ech-liste" name="echeance">
      </div>

      <div class="champ">
        <span class="legende"><?= e(t('taches.icone')) ?></span>
        <div class="choix-icones">
          <?php foreach ($icones as $i => $icone): ?>
            <input type="radio" id="nt-i-<?= $i ?>" name="icone" value="<?= e($icone) ?>"<?= $i === 0 ? ' checked' : '' ?>>
            <label for="nt-i-<?= $i ?>"><?= e($icone) ?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="champ">
        <span class="legende"><?= e(t('taches.couleur')) ?></span>
        <div class="choix-couleurs">
          <?php foreach ($palette as $i => $couleur): ?>
            <input type="radio" id="nt-c-<?= $i ?>" name="couleur" value="<?= e($couleur) ?>"<?= $i === 0 ? ' checked' : '' ?>>
            <label for="nt-c-<?= $i ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
          <?php endforeach; ?>
        </div>
      </div>

      <button class="bouton bouton--bloc" type="submit"><?= e(t('taches.creer_principale')) ?></button>
    </form>
  </section>

  <section class="carte">
    <h2 style="margin-top:0"><?= e(t('taches.sous_tache')) ?></h2>
    <?php if ($listes === []): ?>
      <p class="discret" style="margin:0">
        <?= e(t('taches.principale_dabord')) ?>
      </p>
    <?php else: ?>
      <form method="post" action="<?= url('taches') ?>"<?= $surPlace ?>>
        <?= $champsCommuns ?>

        <div class="champ">
          <label for="nt-liste"><?= e(t('taches.principale')) ?></label>
          <select id="nt-liste" name="liste_id" required>
            <?php foreach ($listes as $l): ?>
              <option value="<?= (int) $l['id'] ?>" data-echeance="<?= e((string) ($l['echeance'] ?? '')) ?>">
                <?= e($l['icone'] . ' ' . $l['nom']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="champ">
          <label for="nt-titre"><?= e(t('taches.tache')) ?></label>
          <input type="text" id="nt-titre" name="titre" required maxlength="200" placeholder="<?= e(t('taches.tache_exemple')) ?>">
        </div>

        <?php $premier = (string) ($listes[0]['echeance'] ?? ''); ?>
        <div class="champ">
          <label for="nt-echeance"><?= e(t('taches.echeance')) ?> <span class="discret"><?= e(t('taches.echeance_facultative')) ?></span></label>
          <input type="date" id="nt-echeance" name="echeance" data-plafond-de="nt-liste"
                 <?= $premier === '' ? '' : 'max="' . e($premier) . '"' ?>>
          <span class="champ__aide"><?= e(t('taches.sous_tache_echeance_aide')) ?></span>
        </div>

        <button class="bouton bouton--bloc" type="submit"><?= e(t('taches.ajouter_sous_tache')) ?></button>
      </form>
    <?php endif; ?>
  </section>
</div>
