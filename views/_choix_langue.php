<?php
/**
 * Le choix de la langue, pour qui n'a pas (encore) de compte : connexion,
 * inscription, mot de passe oublié, liens publics.
 *
 * La langue vient d'abord du navigateur (en-tête Accept-Language) ; ce choix la
 * fixe dans un cookie. Un compte garde la sienne, dans « Mon compte » — ce
 * sélecteur ne s'affiche que là où il n'y en a pas.
 */
$retour = (string) ($_SERVER['REQUEST_URI'] ?? '');
?>
<form method="post" action="<?= url('langue') ?>" class="choix-langue" aria-label="<?= e(t('langue.choisir')) ?>">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <input type="hidden" name="retour" value="<?= e($retour) ?>">
  <?php foreach (Langue::LANGUES as $code => $langue): ?>
    <button class="choix-langue__bouton" type="submit" name="langue" value="<?= e($code) ?>" lang="<?= e($code) ?>"
            <?= $code === Langue::courante() ? 'aria-current="true"' : '' ?>>
      <?= $langue['drapeau'] ?> <?= e($langue['nom']) ?>
    </button>
  <?php endforeach; ?>
</form>
