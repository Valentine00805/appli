<?php
/**
 * Déplacer un fichier du groupe dans un dossier (ou à la racine), ouvert en fenêtre par-dessus les fichiers.
 *
 * @var array $projet
 * @var array $fichier  le fichier (id, nom_origine, dossier_id…)
 * @var list<array{id: int, libelle: string}> $tous  les dossiers, avec leur chemin
 * @var int $depuis  le dossier où l'on était (0 : la racine) : c'est là qu'on revient
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$retour = url('travaux/' . (int) $projet['id'] . '/fichiers', $depuis > 0 ? ['dossier' => $depuis] : []);
?>
<div class="entete-page">
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p style="margin:0 0 .3rem"><a href="<?= $retour ?>">← <?= e((string) $projet['nom']) ?></a></p>
    <?php endif; ?>
    <h1><?= e(t('tr.fd.deplacer_page')) ?></h1>
    <p><?= e((string) $fichier['nom_origine']) ?></p>
  </div>
</div>

<form method="post" action="<?= url('travaux/fichiers/' . (int) $fichier['id'] . '/deplacer') ?>" class="carte travaux-formulaire"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <input type="hidden" name="depuis" value="<?= (int) $depuis ?>">
  <div class="champ">
    <label for="dossier-du-fichier"><?= e(t('tr.fd.deplacer_dans')) ?></label>
    <select id="dossier-du-fichier" name="dossier" autofocus>
      <option value="0"<?= ($fichier['dossier_id'] ?? null) === null ? ' selected' : '' ?>>📎 <?= e(t('tr.fd.racine')) ?></option>
      <?php foreach ($tous as $d): ?>
        <option value="<?= (int) $d['id'] ?>"<?= (int) ($fichier['dossier_id'] ?? 0) === (int) $d['id'] ? ' selected' : '' ?>>📁 <?= e($d['libelle']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <p class="actions">
    <button class="bouton" type="submit"><?= e(t('tr.fd.deplacer')) ?></button>
    <a class="bouton bouton--secondaire" href="<?= $retour ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>><?= e(t('tr.ty.annuler')) ?></a>
  </p>
</form>
