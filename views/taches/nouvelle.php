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

<div class="entete-page"<?= $dansUneFenetre ? ' data-large data-croix-seule' : '' ?>>
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p class="discret" style="margin-bottom:.35rem">
        <a href="<?= e($retour !== '' ? $retour : url('tableau')) ?>">← Retour</a>
      </p>
    <?php endif; ?>
    <h1>Nouvelle tâche</h1>
    <p class="discret" style="margin:0">
      Une sous-tâche se range dans une tâche principale, et paraît au tableau.
    </p>
  </div>
</div>

<div class="nouvelle-tache-choix">
  <section class="carte">
    <h2 style="margin-top:0">Tâche principale</h2>
    <form method="post" action="<?= url('taches/listes') ?>"<?= $surPlace ?>>
      <?= $champsCommuns ?>

      <div class="champ">
        <label for="nt-nom">Nom</label>
        <input type="text" id="nt-nom" name="nom" required maxlength="120" placeholder="Cette semaine">
      </div>

      <div class="champ">
        <label for="nt-ech-liste">Échéance <span class="discret">(facultative)</span></label>
        <input type="date" id="nt-ech-liste" name="echeance">
      </div>

      <div class="champ">
        <span class="legende">Icône</span>
        <div class="choix-icones">
          <?php foreach ($icones as $i => $icone): ?>
            <input type="radio" id="nt-i-<?= $i ?>" name="icone" value="<?= e($icone) ?>"<?= $i === 0 ? ' checked' : '' ?>>
            <label for="nt-i-<?= $i ?>"><?= e($icone) ?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="champ">
        <span class="legende">Couleur</span>
        <div class="choix-couleurs">
          <?php foreach ($palette as $i => $couleur): ?>
            <input type="radio" id="nt-c-<?= $i ?>" name="couleur" value="<?= e($couleur) ?>"<?= $i === 0 ? ' checked' : '' ?>>
            <label for="nt-c-<?= $i ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
          <?php endforeach; ?>
        </div>
      </div>

      <button class="bouton bouton--bloc" type="submit">Créer la tâche principale</button>
    </form>
  </section>

  <section class="carte">
    <h2 style="margin-top:0">Sous-tâche</h2>
    <?php if ($listes === []): ?>
      <p class="discret" style="margin:0">
        Il faut d'abord une tâche principale pour la ranger : créez-la à côté.
      </p>
    <?php else: ?>
      <form method="post" action="<?= url('taches') ?>"<?= $surPlace ?>>
        <?= $champsCommuns ?>

        <div class="champ">
          <label for="nt-liste">Tâche principale</label>
          <select id="nt-liste" name="liste_id" required>
            <?php foreach ($listes as $l): ?>
              <option value="<?= (int) $l['id'] ?>" data-echeance="<?= e((string) ($l['echeance'] ?? '')) ?>">
                <?= e($l['icone'] . ' ' . $l['nom']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="champ">
          <label for="nt-titre">Tâche</label>
          <input type="text" id="nt-titre" name="titre" required maxlength="200" placeholder="Relire le chapitre 3">
        </div>

        <?php $premier = (string) ($listes[0]['echeance'] ?? ''); ?>
        <div class="champ">
          <label for="nt-echeance">Échéance <span class="discret">(facultative)</span></label>
          <input type="date" id="nt-echeance" name="echeance" data-plafond-de="nt-liste"
                 <?= $premier === '' ? '' : 'max="' . e($premier) . '"' ?>>
          <span class="champ__aide">Au plus tard à l’échéance de la tâche principale.</span>
        </div>

        <button class="bouton bouton--bloc" type="submit">Ajouter la sous-tâche</button>
      </form>
    <?php endif; ?>
  </section>
</div>
