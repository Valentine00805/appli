<?php /** @var array $erreurs */ ?>

<?php if (isset($erreurs['global'])): ?>
  <div class="flash flash--erreur" role="alert"><?= e($erreurs['global']) ?></div>
<?php endif; ?>

<form method="post" action="<?= url('connexion') ?>" novalidate>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

  <div class="champ">
    <label for="identifiant"><?= e(t('auth.identifiant')) ?></label>
    <input type="text" id="identifiant" name="identifiant" required autocomplete="username" autofocus
           autocapitalize="none" spellcheck="false"
           value="<?= e(post('identifiant') !== '' ? post('identifiant') : post('email')) ?>">
  </div>

  <div class="champ">
    <label for="mot_de_passe"><?= e(t('auth.mot_de_passe')) ?></label>
    <input type="password" id="mot_de_passe" name="mot_de_passe" required autocomplete="current-password">
  </div>

  <button class="bouton bouton--bloc" type="submit"><?= e(t('auth.se_connecter')) ?></button>
</form>

<p class="auth__bas" style="margin-top:.75rem">
  <a href="<?= url('mot-de-passe/oublie') ?>"><?= e(t('auth.mdp_oublie')) ?></a>
</p>

<?php if (Config::get('app', 'inscription_ouverte')): ?>
  <p class="auth__bas">
    <?= e(t('auth.pas_de_compte')) ?> <a href="<?= url('inscription') ?>"><?= e(t('auth.creer_compte')) ?></a>
  </p>
<?php endif; ?>
