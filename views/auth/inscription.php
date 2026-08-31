<?php /** @var array $erreurs */ ?>

<form method="post" action="<?= url('inscription') ?>" novalidate>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

  <div class="champ<?= isset($erreurs['nom']) ? ' champ--erreur' : '' ?>">
    <label for="nom">Votre nom</label>
    <input type="text" id="nom" name="nom" required autocomplete="name" autofocus value="<?= e(post('nom')) ?>">
    <?php if (isset($erreurs['nom'])): ?><span class="message-erreur"><?= e($erreurs['nom']) ?></span><?php endif; ?>
  </div>

  <div class="champ<?= isset($erreurs['email']) ? ' champ--erreur' : '' ?>">
    <label for="email">Adresse e-mail</label>
    <input type="email" id="email" name="email" required autocomplete="email" value="<?= e(post('email')) ?>">
    <?php if (isset($erreurs['email'])): ?><span class="message-erreur"><?= e($erreurs['email']) ?></span><?php endif; ?>
  </div>

  <div class="champ<?= isset($erreurs['mot_de_passe']) ? ' champ--erreur' : '' ?>">
    <label for="mot_de_passe">Mot de passe</label>
    <input type="password" id="mot_de_passe" name="mot_de_passe" required autocomplete="new-password" minlength="8">
    <span class="champ__aide">8 caractères minimum.</span>
    <?php if (isset($erreurs['mot_de_passe'])): ?><span class="message-erreur"><?= e($erreurs['mot_de_passe']) ?></span><?php endif; ?>
  </div>

  <div class="champ<?= isset($erreurs['mot_de_passe_confirmation']) ? ' champ--erreur' : '' ?>">
    <label for="mot_de_passe_confirmation">Confirmer le mot de passe</label>
    <input type="password" id="mot_de_passe_confirmation" name="mot_de_passe_confirmation" required
           autocomplete="new-password">
    <?php if (isset($erreurs['mot_de_passe_confirmation'])): ?>
      <span class="message-erreur"><?= e($erreurs['mot_de_passe_confirmation']) ?></span>
    <?php endif; ?>
  </div>

  <button class="bouton bouton--bloc" type="submit">Créer mon compte</button>
</form>

<p class="auth__bas">
  Déjà inscrit ? <a href="<?= url('connexion') ?>">Se connecter</a>
</p>
