<?php
/**
 * Une nouvelle tâche, ouverte en fenêtre par-dessus « Qui fait quoi ».
 *
 * @var array $projet
 * @var list<array> $membres
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
?>
<div class="entete-page">
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p style="margin:0 0 .3rem"><a href="<?= url('travaux/' . (int) $projet['id']) ?>">← <?= e((string) $projet['nom']) ?></a></p>
    <?php endif; ?>
    <h1><?= e(t('tr.tc.nouvelle_titre')) ?></h1>
    <p><?= e((string) $projet['nom']) ?></p>
  </div>
</div>

<form method="post" action="<?= url('travaux/' . (int) $projet['id'] . '/taches') ?>" class="carte travaux-formulaire"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <div class="champ">
    <label for="titre-nouvelle"><?= e(t('tr.tc.tache')) ?></label>
    <input type="text" id="titre-nouvelle" name="titre" required maxlength="200" autofocus placeholder="<?= e(t('tr.tc.titre_exemple')) ?>">
  </div>
  <div class="ligne-champs">
    <div class="champ">
      <label for="membre-nouvelle"><?= e(t('tr.tc.qui')) ?></label>
      <select id="membre-nouvelle" name="membre_id">
        <option value=""><?= e(t('tr.tc.personne')) ?></option>
        <?php foreach ($membres as $m): ?>
          <option value="<?= (int) $m['id'] ?>"><?= e((string) $m['nom_affiche']) ?><?= (int) $m['id'] === (int) $projet['mon_membre_id'] ? e(t('tr.tc.moi')) : '' ?><?= $m['user_id'] === null ? e(t('tr.tc.sans_compte')) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="champ">
      <label for="echeance-nouvelle"><?= e(t('tr.tc.pour_le')) ?> <span class="discret"><?= e(t('tr.ec.facultatif')) ?></span></label>
      <input type="date" id="echeance-nouvelle" name="echeance">
    </div>
  </div>
  <div class="champ">
    <label for="note-nouvelle"><?= e(t('tr.tc.precisions')) ?> <span class="discret"><?= e(t('tr.ec.facultatif')) ?></span></label>
    <textarea id="note-nouvelle" name="note" rows="3" maxlength="2000"></textarea>
  </div>
  <?php if (Travaux::listesDisponibles()): ?>
    <?php // La tâche n'existe pas encore : les points se tiennent ici (champs cachés « points[] »), et partent avec elle. Sans script : une zone de texte, un point par ligne. ?>
    <div class="bloc-liste bloc-liste--formulaire" data-points-nouveaux data-max="<?= Travaux::POINTS_MAX ?>"
         data-retirer="<?= e(t('tr.li2.retirer_point', ['texte' => '{texte}'])) ?>" data-retirer-court="<?= e(t('tr.li2.retirer')) ?>">
      <div class="bloc-liste__entete">
        <h2><?= e(t('tr.li2.dans_la_tache')) ?></h2>
        <span class="pastille" data-points-compte hidden>0/0</span>
      </div>
      <p class="discret" style="margin:0 0 .5rem"><?= e(t('tr.li2.dans_la_tache_aide')) ?></p>
      <ul class="cochees" data-points-liste></ul>
      <div class="coche-ajout">
        <label class="sr-only" for="point-nouveau"><?= e(t('tr.li2.ajouter_label')) ?></label>
        <input type="text" id="point-nouveau" maxlength="<?= Travaux::POINT_TEXTE_MAX ?>" placeholder="<?= e(t('tr.li2.ajouter_placeholder')) ?>" autocomplete="off" data-points-saisie>
        <button class="bouton bouton--secondaire bouton--petit" type="button" data-points-ajouter><?= e(t('tr.li2.ajouter')) ?></button>
      </div>
      <noscript>
        <textarea name="points" rows="4" placeholder="<?= e(t('tr.li2.nouvelle_exemple')) ?>" aria-label="<?= e(t('tr.li2.dans_la_tache')) ?>"></textarea>
        <p class="champ__aide"><?= e(t('tr.li2.nouvelle_aide', ['max' => Travaux::POINTS_MAX])) ?></p>
      </noscript>
    </div>
  <?php endif; ?>
  <p class="actions">
    <button class="bouton" type="submit"><?= e(t('tr.tc.ajouter')) ?></button>
    <a class="bouton bouton--secondaire" href="<?= url('travaux/' . (int) $projet['id']) ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>><?= e(t('tr.ty.annuler')) ?></a>
  </p>
</form>
