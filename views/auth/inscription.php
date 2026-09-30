<?php /** @var array $erreurs @var bool $codeExige */ ?>

<form method="post" action="<?= url('inscription') ?>" novalidate>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

  <div class="champ<?= isset($erreurs['nom']) ? ' champ--erreur' : '' ?>">
    <label for="nom"><?= e(t('auth.votre_nom')) ?></label>
    <input type="text" id="nom" name="nom" required autocomplete="name" autofocus value="<?= e(post('nom')) ?>">
    <?php if (isset($erreurs['nom'])): ?><span class="message-erreur"><?= e($erreurs['nom']) ?></span><?php endif; ?>
  </div>

  <div class="champ<?= isset($erreurs['pseudo']) ? ' champ--erreur' : '' ?>">
    <label for="pseudo"><?= e(t('auth.pseudo')) ?></label>
    <input type="text" id="pseudo" name="pseudo" required autocomplete="nickname"
           minlength="<?= Auth::PSEUDO_MIN ?>" maxlength="<?= Auth::PSEUDO_MAX ?>"
           value="<?= e(post('pseudo')) ?>">
    <span class="champ__aide"><?= e(t('auth.pseudo_aide')) ?></span>
    <?php if (isset($erreurs['pseudo'])): ?><span class="message-erreur"><?= e($erreurs['pseudo']) ?></span><?php endif; ?>
  </div>

  <div class="champ<?= isset($erreurs['email']) ? ' champ--erreur' : '' ?>">
    <label for="email"><?= e(t('auth.email')) ?></label>
    <input type="email" id="email" name="email" required autocomplete="email" value="<?= e(post('email')) ?>">
    <?php if (isset($erreurs['email'])): ?><span class="message-erreur"><?= e($erreurs['email']) ?></span><?php endif; ?>
  </div>

  <div class="champ<?= isset($erreurs['mot_de_passe']) ? ' champ--erreur' : '' ?>">
    <label for="mot_de_passe"><?= e(t('auth.mot_de_passe')) ?></label>
    <input type="password" id="mot_de_passe" name="mot_de_passe" required autocomplete="new-password" minlength="8">
    <span class="champ__aide"><?= e(t('auth.huit_caracteres')) ?></span>
    <?php if (isset($erreurs['mot_de_passe'])): ?><span class="message-erreur"><?= e($erreurs['mot_de_passe']) ?></span><?php endif; ?>
  </div>

  <div class="champ<?= isset($erreurs['mot_de_passe_confirmation']) ? ' champ--erreur' : '' ?>">
    <label for="mot_de_passe_confirmation"><?= e(t('auth.confirmer_mdp')) ?></label>
    <input type="password" id="mot_de_passe_confirmation" name="mot_de_passe_confirmation" required
           autocomplete="new-password">
    <?php if (isset($erreurs['mot_de_passe_confirmation'])): ?>
      <span class="message-erreur"><?= e($erreurs['mot_de_passe_confirmation']) ?></span>
    <?php endif; ?>
  </div>

  <?php if ($codeExige): ?>
    <div class="champ<?= isset($erreurs['code_inscription']) ? ' champ--erreur' : '' ?>">
      <label for="code_inscription"><?= e(t('auth.code_inscription')) ?></label>
      <input type="password" id="code_inscription" name="code_inscription" required
             autocomplete="off">
      <span class="champ__aide"><?= e(t('auth.code_aide')) ?></span>
      <?php if (isset($erreurs['code_inscription'])): ?>
        <span class="message-erreur"><?= e($erreurs['code_inscription']) ?></span>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <button class="bouton bouton--bloc" type="submit"><?= e(t('auth.creer_mon_compte')) ?></button>
</form>

<p class="auth__bas">
  <?= e(t('auth.deja_inscrit')) ?> <a href="<?= url('connexion') ?>"><?= e(t('auth.se_connecter')) ?></a>
</p>
