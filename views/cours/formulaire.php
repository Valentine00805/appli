<?php
/**
 * @var ?array $cours
 * @var array $matieres, $fichiers
 * @var string $tagsCours
 * @var array $tousLesTags
 * @var ?int $matiereSelection
 * @var bool $dansUneFenetre  rendu seul, pour être posé dans une fenêtre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$edition = $cours !== null;
$action = $edition ? url('cours/' . $cours['id'] . '/modifier') : url('cours/nouveau');
$matiereActive = $edition ? entier_ou_null($cours['matiere_id']) : $matiereSelection;
$dossierActif  = $edition ? entier_ou_null($cours['dossier_id']) : entier_ou_null($_GET['dossier'] ?? null);
?>

<?php
/*
 * « data-large » : le formulaire tient sur deux colonnes, il lui faut de la place.
 */
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <?php // Dans une fenêtre, le cours est juste derrière : la croix y ramène. ?>
    <?php if (!$dansUneFenetre): ?>
      <p class="discret" style="margin-bottom:.35rem">
        <a href="<?= $edition ? url('cours/' . $cours['id']) : url('cours') ?>"><?= e(t('commun.retour')) ?></a>
      </p>
    <?php endif; ?>
    <h1><?= e($edition ? t('cours.modifier_titre') : t('cours.nouveau')) ?></h1>
  </div>
</div>

<?php // Dans une fenêtre, le formulaire s'y enregistre, et le cours revient à sa place. ?>
<form method="post" action="<?= $action ?>" enctype="multipart/form-data"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

  <div class="colonnes">
    <div class="carte">
      <div class="champ">
        <label for="titre"><?= e(t('cours.titre_champ')) ?></label>
        <input type="text" id="titre" name="titre" required maxlength="200" autofocus
               placeholder="<?= e(t('cours.titre_exemple')) ?>"
               value="<?= e($edition ? $cours['titre'] : post('titre')) ?>">
      </div>

      <div class="champ">
        <label for="contenu"><?= e(t('cours.contenu')) ?></label>
        <textarea id="contenu" name="contenu" data-texte-riche="complet" data-tailles="<?= e(implode(',', TexteRiche::TAILLES)) ?>"
                  placeholder="<?= e(t('cours.contenu_placeholder')) ?>"><?= e(TexteRiche::pourEditeur($edition ? (string) $cours['contenu'] : post('contenu'))) ?></textarea>
        <span class="champ__aide"><?= e(t('cours.contenu_aide')) ?></span>
      </div>
    </div>

    <div class="pile">
      <div class="carte">
        <div class="champ">
          <label for="matiere_id"><?= e(t('cours.matiere')) ?></label>
          <select id="matiere_id" name="matiere_id">
            <option value=""><?= e(t('commun.aucune')) ?></option>
            <?php foreach ($matieres as $m): ?>
              <option value="<?= (int) $m['id'] ?>"<?= $matiereActive === (int) $m['id'] ? ' selected' : '' ?>>
                <?= e($m['nom']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="champ__aide"><a href="<?= url('organisation/matieres') ?>">Gérer mes matières</a></span>
        </div>

        <div class="champ">
          <label for="dossier_id"><?= e(t('cours.dossier')) ?></label>
          <select id="dossier_id" name="dossier_id">
            <option value=""><?= e(t('commun.aucun')) ?></option>
            <?php foreach ($dossiers as $d): ?>
              <option value="<?= (int) $d['id'] ?>"<?= $dossierActif === (int) $d['id'] ? ' selected' : '' ?>>
                <?= e(retrait_dossier($d) . $d['icone'] . ' ' . $d['nom']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="champ__aide">
            Où ranger ce cours, indépendamment de sa matière —
            <a href="<?= url('organisation/dossiers') ?>">gérer mes dossiers</a>.
          </span>
        </div>

        <div class="champ">
          <label for="tags"><?= e(t('cours.tags')) ?></label>
          <input type="text" id="tags" name="tags" placeholder="<?= e(t('cours.tags_exemple')) ?>"
                 list="tags-existants" value="<?= e($edition ? $tagsCours : post('tags')) ?>">
          <datalist id="tags-existants">
            <?php foreach ($tousLesTags as $nomTag): ?>
              <option value="<?= e($nomTag) ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <span class="champ__aide">
            <?= e(t('cours.tags_aide')) ?> <a href="<?= url('organisation/tags') ?>"><?= e(t('cours.gerer_tags')) ?></a>
          </span>
        </div>
      </div>

      <div class="carte">
        <div class="champ">
          <label for="fichiers"><?= e(t('cours.ajouter_fichiers')) ?></label>
          <input type="file" id="fichiers" name="fichiers[]" multiple>
          <span class="champ__aide">
            <?= e(t('cours.fichiers_aide', ['taille' => taille_lisible(Fichiers::tailleMax())])) ?>
          </span>
        </div>

        <?php if ($fichiers !== []): ?>
          <p class="discret" style="margin:.5rem 0 .35rem"><?= e(t('cours.deja_joints')) ?></p>
          <ul class="liste-fichiers">
            <?php foreach ($fichiers as $f): ?>
              <li class="fichier">
                <span class="fichier__icone" aria-hidden="true"><?= Fichiers::icone($f['mime'], $f['nom_origine']) ?></span>
                <span style="min-width:0">
                  <a class="fichier__nom" href="<?= url('fichiers/' . $f['id']) ?>" target="_blank" rel="noopener">
                    <?= e($f['nom_origine']) ?>
                  </a><br>
                  <span class="fichier__meta"><?= e(taille_lisible((int) $f['taille'])) ?></span>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
          <p class="champ__aide"><?= e(t('cours.suppression_ailleurs')) ?></p>
        <?php endif; ?>
      </div>

      <button class="bouton bouton--bloc" type="submit">
        <?= e($edition ? t('cours.enregistrer_modifications') : t('cours.creer')) ?>
      </button>
      <?php // Dans une fenêtre, « Annuler » la ferme ; sur la page, il y ramène. ?>
      <a class="bouton bouton--secondaire bouton--bloc" data-fermer
         href="<?= $edition ? url('cours/' . $cours['id']) : url('cours') ?>"><?= e(t('commun.annuler')) ?></a>
    </div>
  </div>
</form>
