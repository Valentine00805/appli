<?php
/**
 * Mot de passe oublié : l'adresse ou le pseudo, et un lien part par e-mail.
 *
 * @var bool $envoye
 */
?>
<h2 class="auth__titre">Mot de passe oublié</h2>

<?php if ($envoye): ?>
  <div class="flash flash--succes" role="status">
    Si un compte correspond, un e-mail vient de partir avec un lien pour choisir un nouveau mot de passe.
    Il vaut <?= Reinitialisation::DUREE_MINUTES ?> minutes. Pensez à regarder dans les indésirables.
  </div>
  <p class="auth__bas"><a href="<?= url('connexion') ?>">← Retour à la connexion</a></p>
<?php else: ?>
  <p class="champ__aide" style="margin-top:0">
    Indiquez l’adresse e-mail ou le pseudo de votre compte : nous vous enverrons un lien pour choisir un nouveau mot de passe.
  </p>
  <form method="post" action="<?= url('mot-de-passe/oublie') ?>">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <div class="champ">
      <label for="identifiant">Adresse e-mail ou pseudo</label>
      <input type="text" id="identifiant" name="identifiant" required autocomplete="username" autofocus
             autocapitalize="none" spellcheck="false" maxlength="190">
    </div>
    <button class="bouton bouton--bloc" type="submit">Envoyer le lien</button>
  </form>
  <p class="auth__bas"><a href="<?= url('connexion') ?>">← Retour à la connexion</a></p>
<?php endif; ?>