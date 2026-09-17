<?php /** @var array $erreurs */ ?>

<?php if (isset($erreurs['global'])): ?>
  <div class="flash flash--erreur" role="alert"><?= e($erreurs['global']) ?></div>
<?php endif; ?>

<form method="post" action="<?= url('connexion') ?>" novalidate>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

  <div class="champ">
    <label for="identifiant">Adresse e-mail ou pseudo</label>
    <input type="text" id="identifiant" name="identifiant" required autocomplete="username" autofocus
           autocapitalize="none" spellcheck="false"
           value="<?= e(post('identifiant') !== '' ? post('identifiant') : post('email')) ?>">
  </div>

  <div class="champ">
    <label for="mot_de_passe">Mot de passe</label>
    <input type="password" id="mot_de_passe" name="mot_de_passe" required autocomplete="current-password">
  </div>

  <button class="bouton bouton--bloc" type="submit">Se connecter</button>
</form>

<p class="auth__bas" style="margin-top:.75rem">
  <a href="<?= url('mot-de-passe/oublie') ?>">Mot de passe oublié ?</a>
</p>

<?php if (Config::get('app', 'inscription_ouverte')): ?>
  <p class="auth__bas">
    Pas encore de compte ? <a href="<?= url('inscription') ?>">Créer un compte</a>
  </p>
<?php endif; ?>
