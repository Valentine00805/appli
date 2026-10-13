<?php
/**
 * Un nouveau dossier de fichiers, ouvert en fenêtre par-dessus les fichiers.
 *
 * @var array $projet
 * @var ?int $parent  le dossier où on le crée (null : à la racine)
 * @var list<array> $chemin  le chemin jusqu'à ce dossier (vide : la racine)
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$retour = url('travaux/' . (int) $projet['id'] . '/fichiers', $parent !== null ? ['dossier' => $parent] : []);
$ou = $chemin === [] ? t('tr.fd.racine') : implode(' / ', array_column($chemin, 'nom'));
?>
<div class="entete-page">
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p style="margin:0 0 .3rem"><a href="<?= $retour ?>">← <?= e((string) $projet['nom']) ?></a></p>
    <?php endif; ?>
    <h1><?= e(t('tr.fd.nouveau_titre')) ?></h1>
    <p><?= e((string) $projet['nom']) ?></p>
  </div>
</div>

<form method="post" action="<?= url('travaux/' . (int) $projet['id'] . '/dossiers') ?>" class="carte travaux-formulaire"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <?php if ($parent !== null): ?><input type="hidden" name="parent" value="<?= (int) $parent ?>"><?php endif; ?>
  <div class="champ">
    <label for="nom-dossier"><?= e(t('tr.fd.nom_label')) ?></label>
    <input type="text" id="nom-dossier" name="nom" required autofocus maxlength="<?= Travaux::DOSSIER_NOM_MAX ?>" placeholder="<?= e(t('tr.fd.nom_exemple')) ?>" autocomplete="off">
    <span class="champ__aide"><?= e(t('tr.fd.sera_cree_dans', ['chemin' => $ou])) ?></span>
  </div>
  <p class="actions">
    <button class="bouton" type="submit"><?= e(t('tr.fd.creer')) ?></button>
    <a class="bouton bouton--secondaire" href="<?= $retour ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>><?= e(t('tr.ty.annuler')) ?></a>
  </p>
</form>
