<?php
/**
 * Mot de passe oublié : l'adresse ou le pseudo, et un lien part par e-mail.
 *
 * @var bool $envoye
 */
?>
<h2 class="auth__titre"><?= e(t('auth.oubli_titre')) ?></h2>

<?php if ($envoye): ?>
  <div class="flash flash--succes" role="status">
    <?= e(t('auth.oubli_envoye', ['n' => Reinitialisation::DUREE_MINUTES])) ?>
  </div>
  <p class="auth__bas"><a href="<?= url('connexion') ?>"><?= e(t('auth.retour_connexion')) ?></a></p>
<?php else: ?>
  <p class="champ__aide" style="margin-top:0">
    <?= e(t('auth.oubli_aide')) ?>
  </p>
  <form method="post" action="<?= url('mot-de-passe/oublie') ?>">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <div class="champ">
      <label for="identifiant"><?= e(t('auth.identifiant')) ?></label>
      <input type="text" id="identifiant" name="identifiant" required autocomplete="username" autofocus
             autocapitalize="none" spellcheck="false" maxlength="190">
    </div>
    <button class="bouton bouton--bloc" type="submit"><?= e(t('auth.envoyer_lien')) ?></button>
  </form>
  <p class="auth__bas"><a href="<?= url('connexion') ?>"><?= e(t('auth.retour_connexion')) ?></a></p>
<?php endif; ?>