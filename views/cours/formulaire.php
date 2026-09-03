<?php
/**
 * @var ?array $cours
 * @var array $matieres, $fichiers
 * @var string $tagsCours
 * @var array $tousLesTags
 * @var ?int $matiereSelection
 */
$edition = $cours !== null;
$action = $edition ? url('cours/' . $cours['id'] . '/modifier') : url('cours/nouveau');
$matiereActive = $edition ? entier_ou_null($cours['matiere_id']) : $matiereSelection;
$dossierActif  = $edition ? entier_ou_null($cours['dossier_id']) : entier_ou_null($_GET['dossier'] ?? null);
?>

<div class="entete-page">
  <div>
    <p class="discret" style="margin-bottom:.35rem">
      <a href="<?= $edition ? url('cours/' . $cours['id']) : url('cours') ?>">← Retour</a>
    </p>
    <h1><?= $edition ? 'Modifier le cours' : 'Nouveau cours' ?></h1>
  </div>
</div>

<form method="post" action="<?= $action ?>" enctype="multipart/form-data">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

  <div class="colonnes">
    <div class="carte">
      <div class="champ">
        <label for="titre">Titre du cours</label>
        <input type="text" id="titre" name="titre" required maxlength="200" autofocus
               placeholder="Chapitre 3 — Les fonctions affines"
               value="<?= e($edition ? $cours['titre'] : post('titre')) ?>">
      </div>

      <div class="champ">
        <label for="contenu">Contenu</label>
        <textarea id="contenu" name="contenu"
                  placeholder="Notes, définitions, formules, plan du cours…"><?= e($edition ? (string) $cours['contenu'] : post('contenu')) ?></textarea>
        <span class="champ__aide">Le texte est affiché tel quel, sauts de ligne compris.</span>
      </div>
    </div>

    <div class="pile">
      <div class="carte">
        <div class="champ">
          <label for="matiere_id">Matière</label>
          <select id="matiere_id" name="matiere_id">
            <option value="">— Aucune —</option>
            <?php foreach ($matieres as $m): ?>
              <option value="<?= (int) $m['id'] ?>"<?= $matiereActive === (int) $m['id'] ? ' selected' : '' ?>>
                <?= e($m['nom']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="champ__aide"><a href="<?= url('organisation/matieres') ?>">Gérer mes matières</a></span>
        </div>

        <div class="champ">
          <label for="dossier_id">Dossier</label>
          <select id="dossier_id" name="dossier_id">
            <option value="">— Aucun —</option>
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
          <label for="tags">Tags</label>
          <input type="text" id="tags" name="tags" placeholder="révision, chapitre 3, important"
                 list="tags-existants" value="<?= e($edition ? $tagsCours : post('tags')) ?>">
          <datalist id="tags-existants">
            <?php foreach ($tousLesTags as $nomTag): ?>
              <option value="<?= e($nomTag) ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <span class="champ__aide">
            Séparés par des virgules. <a href="<?= url('organisation/tags') ?>">Gérer mes tags</a>
          </span>
        </div>
      </div>

      <div class="carte">
        <div class="champ">
          <label for="fichiers">Ajouter des fichiers</label>
          <input type="file" id="fichiers" name="fichiers[]" multiple>
          <span class="champ__aide">
            PDF, images, Word, PowerPoint, Excel, audio…
            <?= e(taille_lisible(Fichiers::tailleMax())) ?> maximum par fichier.
          </span>
        </div>

        <?php if ($fichiers !== []): ?>
          <p class="discret" style="margin:.5rem 0 .35rem">Fichiers déjà joints :</p>
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
          <p class="champ__aide">La suppression d'un fichier se fait depuis la page du cours.</p>
        <?php endif; ?>
      </div>

      <button class="bouton bouton--bloc" type="submit">
        <?= $edition ? 'Enregistrer les modifications' : 'Créer le cours' ?>
      </button>
      <a class="bouton bouton--secondaire bouton--bloc"
         href="<?= $edition ? url('cours/' . $cours['id']) : url('cours') ?>">Annuler</a>
    </div>
  </div>
</form>
