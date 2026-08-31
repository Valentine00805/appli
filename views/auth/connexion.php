<?php /** @var array $erreurs */ ?>

<?php if (isset($erreurs['global'])): ?>
  <div class="flash flash--erreur" role="alert"><?= e($erreurs['global']) ?></div>
<?php endif; ?>

<form method="post" action="<?= url('connexion') ?>" novalidate>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

  <div class="champ">
    <label for="email">Adresse e-mail</label>
    <input type="email" id="email" name="email" required autocomplete="email" autofocus
           value="<?= e(post('email')) ?>">
  </div>

  <div class="champ">
    <label for="mot_de_passe">Mot de passe</label>
    <input type="password" id="mot_de_passe" name="mot_de_passe" required autocomplete="current-password">
  </div>

  <button class="bouton bouton--bloc" type="submit">Se connecter</button>
</form>

<?php if (Config::get('app', 'inscription_ouverte')): ?>
  <p class="auth__bas">
    Pas encore de compte ? <a href="<?= url('inscription') ?>">Créer un compte</a>
  </p>
<?php endif; ?>
