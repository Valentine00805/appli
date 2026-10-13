<?php
/**
 * Les colonnes du tableau « Qui fait quoi » : les phases du projet (recherche, rédaction, relecture…), que l'administrateur ajoute — avec
 * l'icône de son choix, où il veut entre « À faire » et « Fait » —, renomme, déplace et supprime. Les trois colonnes de base y figurent, fixes,
 * pour qu'on voie où se placent les phases.
 *
 * @var array $projet
 * @var list<array{cle: string, nom: string, icone: string, phase: bool, id: ?int, nb_taches: ?int}> $suite  toutes les colonnes, de gauche à droite
 * @var list<string> $icones
 * @var string $onglet
 */
$dansUneFenetre = $dansUneFenetre ?? false;
// Dans la fenêtre, on y reste : les formulaires s'y enregistrent.
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$csrf = Session::jetonCsrf();
$idProjet = (int) $projet['id'];
$statuts = Travaux::statuts();
$nbPhases = count(array_filter($suite, static fn (array $c): bool => $c['phase']));
$dernier = count($suite) - 1;
// Après quelle colonne la nouvelle se range : toutes, sauf « Fait » (on y met la nouvelle à droite de la dernière).
$apresPossibles = array_values(array_filter($suite, static fn (array $c): bool => $c['cle'] !== 'fait'));
?>
<?php // Ouverte par-dessus le tableau, la page se suffit : ni onglets, ni retour. ?>
<?php if (!$dansUneFenetre): ?>
  <?= Vue::rendre('travaux/_onglets', ['projet' => $projet, 'onglet' => $onglet]) ?>
<?php endif; ?>

<div class="entete-page">
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p style="margin:0 0 .3rem"><a href="<?= url('travaux/' . $idProjet) ?>">← <?= e((string) $projet['nom']) ?></a></p>
    <?php endif; ?>
    <h1 style="margin:0"><?= e(t('tr.cl.titre')) ?></h1>
    <p class="discret" style="margin:.2rem 0 .4rem"><?= e((string) $projet['nom']) ?></p>
    <p><?= e(t('tr.cl.aide', ['a_faire' => $statuts['a_faire']['nom'], 'en_cours' => $statuts['en_cours']['nom'], 'fait' => $statuts['fait']['nom']])) ?></p>
  </div>
</div>

<div class="pile"<?= $dansUneFenetre ? '' : ' style="max-width:48rem"' ?>>
  <?php if ($nbPhases < Travaux::COLONNES_MAX): ?>
    <form method="post"<?= $envoi ?> action="<?= url('travaux/' . $idProjet . '/colonnes') ?>" class="carte travaux-formulaire">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <div class="champ">
        <label for="nom-colonne"><?= e(t('tr.cl.nom_label')) ?></label>
        <input type="text" id="nom-colonne" name="nom" required maxlength="<?= Travaux::COLONNE_NOM_MAX ?>" placeholder="<?= e(t('tr.cl.nom_exemple')) ?>" autocomplete="off">
      </div>
      <div class="champ">
        <span class="legende"><?= e(t('tr.cl.icone')) ?></span>
        <div class="choix-icones">
          <?php foreach ($icones as $j => $i): ?>
            <input type="radio" id="icone-nouvelle-<?= $j ?>" name="icone" value="<?= e($i) ?>"<?= $j === 0 ? ' checked' : '' ?>>
            <label for="icone-nouvelle-<?= $j ?>"><?= e($i) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="champ">
        <label for="apres-colonne"><?= e(t('tr.cl.placer_apres')) ?></label>
        <select id="apres-colonne" name="apres">
          <?php foreach ($apresPossibles as $k => $c): ?>
            <option value="<?= e($c['cle']) ?>"<?= $k === count($apresPossibles) - 1 ? ' selected' : '' ?>><?= e($c['icone'] . ' ' . $c['nom']) ?><?= $k === count($apresPossibles) - 1 ? ' · ' . e(t('tr.cl.a_la_fin', ['fait' => $statuts['fait']['nom']])) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <p class="actions"><button class="bouton" type="submit"><?= e(t('tr.cl.ajouter')) ?></button></p>
    </form>
  <?php else: ?>
    <p class="discret"><?= e(t('tr.cl.trop', ['max' => Travaux::COLONNES_MAX])) ?></p>
  <?php endif; ?>

  <?php if ($nbPhases === 0): ?>
    <p class="discret"><?= e(t('tr.cl.aucune')) ?></p>
  <?php endif; ?>

  <?php foreach ($suite as $i => $c): ?>
    <?php if (!$c['phase']): ?>
      <?php // Les colonnes de base : là pour situer les phases, on n'y touche pas. ?>
      <section class="carte colonne-fixe">
        <div class="bloc-liste__entete">
          <h3 style="margin:0"><?= e($c['icone']) ?> <?= e($c['nom']) ?></h3>
          <span class="pastille pastille--muette"><?= e(t('tr.cl.fixe')) ?></span>
        </div>
      </section>
      <?php continue; ?>
    <?php endif; ?>
    <?php $cid = (int) $c['id']; ?>
    <section class="carte">
      <?php // Comme « Ont accès » : « Modifier » ouvre un panneau (renommer, changer l'icône, supprimer), « Annuler » le referme. ?>
      <div class="bloc-liste__entete">
        <h3 style="margin:0"><?= e($c['icone']) ?> <?= e($c['nom']) ?></h3>
        <span class="pastille"><?= e(tn('tr.cl.taches', (int) $c['nb_taches'])) ?></span>
        <form method="post"<?= $envoi ?> action="<?= url('travaux/colonnes/' . $cid . '/deplacer') ?>" class="en-ligne">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="sens" value="gauche">
          <button class="bouton bouton--discret bouton--petit" type="submit" title="<?= e(t('tr.cl.gauche')) ?>" aria-label="<?= e(t('tr.cl.gauche')) ?>"<?= $i === 1 ? ' disabled' : '' ?>>←</button>
        </form>
        <form method="post"<?= $envoi ?> action="<?= url('travaux/colonnes/' . $cid . '/deplacer') ?>" class="en-ligne">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="sens" value="droite">
          <button class="bouton bouton--discret bouton--petit" type="submit" title="<?= e(t('tr.cl.droite')) ?>" aria-label="<?= e(t('tr.cl.droite')) ?>"<?= $i === $dernier - 1 ? ' disabled' : '' ?>>→</button>
        </form>
        <input type="checkbox" id="colonne-ouvrir-<?= $cid ?>" class="acces-bascule sr-only">
        <label for="colonne-ouvrir-<?= $cid ?>" class="bouton bouton--secondaire bouton--petit acces-bouton" role="button"><?= e(t('commun.modifier')) ?></label>
        <div class="acces-panneau">
          <form method="post"<?= $envoi ?> action="<?= url('travaux/colonnes/' . $cid . '/modifier') ?>" class="acces-panneau__droit">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <label class="acces-panneau__etiquette" for="renommer-colonne-<?= $cid ?>"><?= e(t('tr.cl.nom_label')) ?></label>
            <div class="acces-panneau__ligne">
              <input type="text" id="renommer-colonne-<?= $cid ?>" name="nom" required maxlength="<?= Travaux::COLONNE_NOM_MAX ?>" value="<?= e($c['nom']) ?>" autocomplete="off">
            </div>
            <span class="acces-panneau__etiquette" style="margin-top:.7rem"><?= e(t('tr.cl.icone')) ?></span>
            <div class="choix-icones">
              <?php foreach ($icones as $j => $i2): ?>
                <input type="radio" id="icone-<?= $cid ?>-<?= $j ?>" name="icone" value="<?= e($i2) ?>"<?= $i2 === $c['icone'] ? ' checked' : '' ?>>
                <label for="icone-<?= $cid ?>-<?= $j ?>"><?= e($i2) ?></label>
              <?php endforeach; ?>
            </div>
            <div class="acces-panneau__ligne" style="margin-top:.8rem">
              <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
              <label for="colonne-ouvrir-<?= $cid ?>" class="bouton bouton--discret" role="button"><?= e(t('commun.annuler')) ?></label>
            </div>
          </form>
          <form method="post"<?= $envoi ?> action="<?= url('travaux/colonnes/' . $cid . '/supprimer') ?>" class="acces-panneau__retrait"
                data-confirmation="<?= e(t('tr.cl.supprimer_confirmation', ['nom' => $c['nom'], 'en_cours' => $statuts['en_cours']['nom']])) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('tr.cl.supprimer')) ?></button>
          </form>
        </div>
      </div>
    </section>
  <?php endforeach; ?>
</div>
