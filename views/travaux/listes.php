<?php
/**
 * L'onglet « Listes » : des listes à points à cocher, communes au groupe (le matériel à apporter, le plan du rapport, les questions à poser…).
 *
 * @var array $projet
 * @var list<array> $listes  chacune avec ses « points »
 * @var string $onglet
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$csrf = Session::jetonCsrf();
$idProjet = (int) $projet['id'];
?>
<?= Vue::rendre('travaux/_onglets', ['projet' => $projet, 'onglet' => $onglet, 'dansUneFenetre' => $dansUneFenetre]) ?>

<div class="pile" style="max-width:48rem">
  <form method="post"<?= $envoi ?> action="<?= url('travaux/' . $idProjet . '/listes') ?>" class="carte liste-nouvelle">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label class="sr-only" for="titre-liste"><?= e(t('tr.li2.titre_label')) ?></label>
    <input type="text" id="titre-liste" name="titre" required maxlength="<?= Travaux::LISTE_TITRE_MAX ?>" placeholder="<?= e(t('tr.li2.titre_placeholder')) ?>" autocomplete="off">
    <button class="bouton bouton--petit" type="submit"><?= e(t('tr.li2.creer')) ?></button>
  </form>

  <?php if ($listes === []): ?>
    <div class="vide">
      <span class="vide__icone">☑️</span>
      <p><?= e(t('tr.li2.aucune')) ?></p>
      <p class="discret"><?= e(t('tr.li2.aucune_aide')) ?></p>
    </div>
  <?php endif; ?>

  <?php foreach ($listes as $l): ?>
    <?php
    $lid = (int) $l['id'];
    $av = Travaux::avancementPoints($l['points']);
    $part = $av === null ? null : (int) round($av[0] * 100 / $av[1]);
    ?>
    <section class="carte bloc-liste" id="liste-<?= $lid ?>">
      <?php
      /*
       * Comme « Ont accès » : un bouton « Modifier » ouvre un panneau — renommer la liste, en faire une tâche, la supprimer — et « Annuler »
       * le referme. Rien ne se fait d'un clic distrait. Sans script : une case cachée porte l'état, et son étiquette est le bouton.
       */
      ?>
      <div class="bloc-liste__entete">
        <h2><?= e((string) $l['titre']) ?></h2>
        <?php if ($av !== null): ?>
          <span class="pastille<?= $av[0] === $av[1] ? ' pastille--ok' : '' ?>"><?= e(t('tr.li2.faits', ['faits' => $av[0], 'total' => $av[1]])) ?></span>
        <?php endif; ?>
        <?php // À portée de clic, sans ouvrir « Modifier » : la liste devient une tâche du tableau (une confirmation avant, la liste disparaissant). ?>
        <form method="post"<?= $envoi ?> action="<?= url('travaux/listes/' . $lid . '/en-tache') ?>" class="bloc-liste__action"
              data-confirmation="<?= e(t('tr.li2.en_tache_confirmation', ['titre' => (string) $l['titre']])) ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--secondaire bouton--petit" type="submit" title="<?= e(t('tr.li2.en_tache_aide')) ?>">✅ <?= e(t('tr.li2.en_tache')) ?></button>
        </form>
        <input type="checkbox" id="liste-ouvrir-<?= $lid ?>" class="acces-bascule sr-only">
        <label for="liste-ouvrir-<?= $lid ?>" class="bouton bouton--secondaire bouton--petit acces-bouton" role="button"><?= e(t('commun.modifier')) ?></label>
        <div class="acces-panneau">
          <form method="post"<?= $envoi ?> action="<?= url('travaux/listes/' . $lid . '/modifier') ?>" class="acces-panneau__droit">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <label class="acces-panneau__etiquette" for="renommer-<?= $lid ?>"><?= e(t('tr.li2.titre_label')) ?></label>
            <div class="acces-panneau__ligne">
              <input type="text" id="renommer-<?= $lid ?>" name="titre" required maxlength="<?= Travaux::LISTE_TITRE_MAX ?>" value="<?= e((string) $l['titre']) ?>" autocomplete="off">
              <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
              <label for="liste-ouvrir-<?= $lid ?>" class="bouton bouton--discret" role="button"><?= e(t('commun.annuler')) ?></label>
            </div>
          </form>
          <form method="post"<?= $envoi ?> action="<?= url('travaux/listes/' . $lid . '/supprimer') ?>" class="acces-panneau__retrait"
                data-confirmation="<?= e(t('tr.li2.supprimer_confirmation', ['titre' => (string) $l['titre']])) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('tr.li2.supprimer')) ?></button>
          </form>
        </div>
      </div>
      <?php if ($part !== null): ?>
        <div class="jauge" title="<?= e(t('tr.li.part_faite', ['n' => $part])) ?>"><span style="width:<?= $part ?>%;background:var(--accent)"></span></div>
      <?php endif; ?>
      <?= Vue::rendre('travaux/_points', [
          'points' => $l['points'], 'ajout' => url('travaux/listes/' . $lid . '/points'), 'envoi' => $envoi, 'idBase' => 'point-liste-' . $lid,
      ]) ?>
    </section>
  <?php endforeach; ?>
</div>
