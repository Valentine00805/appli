<?php
/**
 * Renommer un dossier de fichiers, ouvert en fenêtre par-dessus les fichiers.
 *
 * @var array $projet
 * @var array $dossier  le dossier (id, nom…)
 * @var list<array> $chemin  le chemin jusqu'à lui
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
    <h1><?= e(t('tr.fd.renommer_titre')) ?></h1>
    <p><?= e(implode(' / ', array_column($chemin, 'nom'))) ?></p>
  </div>
</div>

<form method="post" action="<?= url('travaux/dossiers/' . (int) $dossier['id'] . '/modifier') ?>" class="carte travaux-formulaire"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <input type="hidden" name="depuis" value="<?= (int) $depuis ?>">
  <div class="champ">
    <label for="nom-dossier"><?= e(t('tr.fd.nom_label')) ?></label>
    <input type="text" id="nom-dossier" name="nom" required autofocus maxlength="<?= Travaux::DOSSIER_NOM_MAX ?>" value="<?= e((string) $dossier['nom']) ?>" autocomplete="off">
  </div>
  <p class="actions">
    <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
    <a class="bouton bouton--secondaire" href="<?= $retour ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>><?= e(t('tr.ty.annuler')) ?></a>
  </p>
</form>
