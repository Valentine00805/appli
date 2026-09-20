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
      <p style="margin:0 0 .3rem"><a href="<?= url('alternance') ?>">← Toutes les notes</a></p>
    <?php endif; ?>
    <h1><?= $edition ? '🗒️ ' . e($note['titre']) : '🗒️ Nouvelle note' ?></h1>
    <?php if ($edition): ?>
      <p class="discret">Écrite le <?= e(date_fr((string) $note['created_at'])) ?>
        <?php if (substr((string) $note['updated_at'], 0, 16) !== substr((string) $note['created_at'], 0, 16)): ?>
          · modifiée le <?= e(date_fr((string) $note['updated_at'])) ?>
        <?php endif; ?>
      </p>
    <?php endif; ?>
  </div>
</div>

<form method="post" action="<?= $action ?>" class="carte"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <div class="champ">
    <label for="titre">Titre</label>
    <input type="text" id="titre" name="titre" required maxlength="200"<?= $edition ? '' : ' autofocus' ?>
           placeholder="Réunion d’équipe du lundi" value="<?= e($edition ? (string) $note['titre'] : post('titre')) ?>">
  </div>
  <div class="champ">
    <label for="contenu">Note</label>
    <textarea id="contenu" name="contenu" style="min-height:320px" data-texte-riche="complet"
              data-tailles="<?= e(implode(',', TexteRiche::TAILLES)) ?>"
              placeholder="Ce qui a été dit, ce qu’il faut faire, ce que vous avez appris…"><?= e(TexteRiche::pourEditeur($edition ? $note['contenu'] : post('contenu'))) ?></textarea>
  </div>
  <p class="actions">
    <button class="bouton" type="submit"><?= $edition ? 'Enregistrer' : 'Créer la note' ?></button>
    <a class="bouton bouton--secondaire" href="<?= url('alternance') ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>>Annuler</a>
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
    <h2 style="margin-top:0">✅ À faire dans cette note <span class="discret">(<?= count($aFaire) ?>)</span></h2>
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
          <label for="echeance-taches">Pour quand <span class="discret">(facultatif)</span></label>
          <input type="date" id="echeance-taches" name="echeance">
        </div>
        <p class="actions" style="margin:0">
          <button class="bouton" type="submit">En faire des tâches</button>
        </p>
      </div>
      <p class="champ__aide" style="margin-top:.6rem">Elles iront dans votre liste
        « <?= e(Alternance::LISTE) ?> ». Une tâche déjà là n’y sera pas écrite deux fois.</p>
    </form>
  </section>
<?php endif; ?>

<?php if ($edition): ?>
  <form method="post" action="<?= url('alternance/notes/' . (int) $note['id'] . '/supprimer') ?>"
        data-confirmation="Supprimer définitivement la note « <?= e($note['titre']) ?> » ?" style="margin-top:1rem">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <button class="bouton bouton--danger bouton--petit" type="submit">Supprimer la note</button>
  </form>
<?php endif; ?>
