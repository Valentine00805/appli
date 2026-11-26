<?php
/**
 * La matière d'un fichier gardé comme tel — un « simple » cours importé —, en fenêtre : on le range sous une matière, il reste un fichier.
 *
 * @var array $cours  le cours-fichier (id, titre, matiere_id…)
 * @var array $fichier  son fichier (id, nom_origine, mime…)
 * @var list<array> $matieres  mes matières
 * @var string $retour  la page qu'on regardait derrière (vide : l'aperçu du fichier)
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$actuelle = entier_ou_null($cours['matiere_id']);
?>
<div class="entete-page">
  <div>
    <h1 style="margin:0">🏷️ <?= e(t('cours.fm.titre')) ?></h1>
    <p class="discret" style="margin:.15rem 0 0"><?= e(Fichiers::icone((string) $fichier['mime'], (string) $fichier['nom_origine'])) ?> <?= e((string) $cours['titre']) ?></p>
  </div>
</div>

<form method="post" action="<?= url('cours/' . (int) $cours['id'] . '/matiere') ?>" class="carte"<?= $envoi ?>>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <input type="hidden" name="retour" value="<?= e($retour) ?>">
  <div class="champ">
    <label for="fm-matiere"><?= e(t('cours.matiere')) ?></label>
    <select id="fm-matiere" name="matiere_id" autofocus>
      <option value=""><?= e(t('commun.aucune')) ?></option>
      <?php foreach ($matieres as $m): ?>
        <option value="<?= (int) $m['id'] ?>"<?= $actuelle === (int) $m['id'] ? ' selected' : '' ?>><?= e((string) $m['nom']) ?></option>
      <?php endforeach; ?>
    </select>
    <span class="champ__aide"><?= e(t('cours.fm.aide')) ?> <a href="<?= url('organisation/matieres') ?>"><?= e(t('cours.gerer_matieres')) ?></a></span>
  </div>
  <div class="actions">
    <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
    <a class="bouton bouton--secondaire" href="<?= e($retour !== '' ? $retour : url('fichiers/' . (int) $fichier['id'] . '/apercu')) ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>><?= e(t('commun.annuler')) ?></a>
  </div>
</form>