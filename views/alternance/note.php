<?php
/**
 * Une note d'alternance, à écrire ou à modifier.
 *
 * @var array|null $note
 * @var list<string> $aFaire  les cases à cocher écrites dans la note
 * @var string $onglet
 * @var array|null $situation
 * @var bool $dansUneFenetre  ouverte par « + Nouvelle note », par-dessus la liste
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$aFaire = $aFaire ?? [];
$edition = $note !== null;
$action = $edition ? url('alternance/notes/' . (int) $note['id']) : url('alternance/notes/nouvelle');
?>
<?php if (!$dansUneFenetre): ?>
  <?= Vue::rendre('alternance/_onglets', ['onglet' => $onglet, 'situation' => $situation]) ?>
<?php endif; ?>

<?php // Large : l'éditeur a besoin de place pour sa barre d'outils. ?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p style="margin:0 0 .3rem"><a href="<?= url('alternance') ?>"><?= e(t('alt.nt.retour')) ?></a></p>
    <?php endif; ?>
    <h1><?= $edition ? '🗒️ ' . e($note['titre']) : e(t('alt.nt.nouvelle')) ?></h1>
    <?php if ($edition): ?>
      <p class="discret"><?= e(t('alt.nt.ecrite_le', ['date' => date_fr((string) $note['created_at'])])) ?>
        <?php if (substr((string) $note['updated_at'], 0, 16) !== substr((string) $note['created_at'], 0, 16)): ?>
          <?= e(t('alt.nt.modifiee_le', ['date' => date_fr((string) $note['updated_at'])])) ?>
        <?php endif; ?>
      </p>
    <?php endif; ?>
  </div>
</div>

<form method="post" action="<?= $action ?>" class="carte"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>
      data-brouillon="note-<?= $edition ? (int) $note['id'] : 'nouvelle' ?>">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <div class="champ">
    <label for="titre"><?= e(t('alt.nt.titre_champ')) ?></label>
    <input type="text" id="titre" name="titre" required maxlength="200"<?= $edition ? '' : ' autofocus' ?>
           placeholder="<?= e(t('alt.nt.titre_exemple')) ?>" value="<?= e($edition ? (string) $note['titre'] : post('titre')) ?>">
  </div>
  <?php if (!$edition): ?>
    <?php // Un modèle pose les titres qu'on oublie, et les cases à cocher. ?>
    <div class="champ">
      <label for="modele"><?= e(t('alt.nt.modele')) ?> <span class="discret"><?= e(t('alt.ry.facultatif')) ?></span></label>
      <select id="modele" data-modele-note data-titre="titre" data-texte="contenu">
        <option value=""><?= e(t('alt.nt.page_blanche')) ?></option>
        <?php foreach (Alternance::modeles() as $cle => $m): ?>
          <option value="<?= e($cle) ?>"
                  data-modele-titre="<?= e(str_replace('{date}', date_fr(date('Y-m-d'), false), $m['titre'])) ?>"
                  data-modele-html="<?= e($m['html']) ?>"><?= e($m['nom']) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="champ__aide"><?= e(t('alt.nt.modele_aide')) ?></span>
    </div>
  <?php endif; ?>

  <div class="champ">
    <label for="contenu"><?= e(t('alt.nt.note')) ?></label>
    <textarea id="contenu" name="contenu" style="min-height:320px" data-texte-riche="complet"
              data-tailles="<?= e(implode(',', TexteRiche::TAILLES)) ?>"
              placeholder="<?= e(t('alt.nt.note_exemple')) ?>"><?= e(TexteRiche::pourEditeur($edition ? $note['contenu'] : post('contenu'))) ?></textarea>
  </div>
  <div class="champ">
    <label for="etiquettes"><?= e(t('alt.nt.etiquettes')) ?> <span class="discret"><?= e(t('alt.ry.facultatif')) ?></span></label>
    <input type="text" id="etiquettes" name="etiquettes" maxlength="200"
           placeholder="<?= e(t('alt.nt.etiquettes_exemple')) ?>"
           value="<?= e($edition ? (string) ($note['etiquettes'] ?? '') : post('etiquettes')) ?>">
    <span class="champ__aide"><?= e(t('alt.nt.etiquettes_aide')) ?></span>
  </div>

  <p class="actions">
    <button class="bouton" type="submit"><?= e(t($edition ? 'alt.nt.enregistrer' : 'alt.nt.creer')) ?></button>
    <a class="bouton bouton--secondaire" href="<?= url('alternance') ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>><?= e(t('alt.nt.annuler')) ?></a>
  </p>
</form>

<?php if ($edition && $aFaire !== []): ?>
  <?php
  /*
   * Ce que la note laisse à faire : les lignes cochables qu'on y a écrites.
   * Elles deviennent de vraies tâches, avec leur échéance, dans la liste de
   * l'alternance — sans quoi une décision prise en réunion reste au fond
   * d'une note que personne ne rouvre.
   */
  ?>
  <section class="carte" style="margin-top:1rem">
    <h2 style="margin-top:0"><?= e(t('alt.nt.a_faire')) ?> <span class="discret">(<?= count($aFaire) ?>)</span></h2>
    <form method="post" action="<?= url('alternance/notes/' . (int) $note['id'] . '/taches') ?>">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <ul class="alternance-afaire">
        <?php foreach ($aFaire as $rang => $ligne): ?>
          <li>
            <label class="case">
              <input type="checkbox" name="aFaire[]" value="<?= e($ligne) ?>" checked>
              <?= e($ligne) ?>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="ligne-champs" style="align-items:flex-end">
        <div class="champ" style="max-width:220px">
          <label for="echeance-taches"><?= e(t('alt.nt.pour_quand')) ?> <span class="discret"><?= e(t('alt.ry.facultatif')) ?></span></label>
          <input type="date" id="echeance-taches" name="echeance">
        </div>
        <p class="actions" style="margin:0">
          <button class="bouton" type="submit"><?= e(t('alt.nt.en_taches')) ?></button>
        </p>
      </div>
      <p class="champ__aide" style="margin-top:.6rem"><?= e(t('alt.nt.taches_aide', ['liste' => Alternance::LISTE])) ?></p>
    </form>
  </section>
<?php endif; ?>

<?php if ($edition): ?>
  <form method="post" action="<?= url('alternance/notes/' . (int) $note['id'] . '/supprimer') ?>"
        data-confirmation="<?= e(t('alt.nt.supprimer_confirmation', ['titre' => (string) $note['titre']])) ?>" style="margin-top:1rem">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('alt.nt.supprimer')) ?></button>
  </form>
<?php endif; ?>
