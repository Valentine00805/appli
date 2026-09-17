<?php
/**
 * Le lien reçu par e-mail : choisir un nouveau mot de passe.
 *
 * @var string $jeton  vide si le lien n'est plus valable
 * @var ?array $compte
 * @var ?string $erreur
 */
?>
<h2 class="auth__titre">Nouveau mot de passe</h2>

<?php if ($compte === null): ?>
  <div class="flash flash--erreur" role="alert">
    <?= e($erreur ?? 'Ce lien n’est plus valable : il a déjà servi, ou a expiré.') ?>
  </div>
  <p class="auth__bas"><a href="<?= url('mot-de-passe/oublie') ?>">Faire une nouvelle demande</a></p>
<?php else: ?>
  <?php if ($erreur !== null): ?>
    <div class="flash flash--erreur" role="alert"><?= e($erreur) ?></div>
  <?php endif; ?>
  <p class="champ__aide" style="margin-top:0">
    Pour le compte <strong><?= e((string) ($compte['pseudo'] ?: $compte['email'])) ?></strong>.
    Les appareils encore connectés devront se reconnecter.
  </p>
  <form method="post" action="<?= url('mot-de-passe/nouveau') ?>">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <input type="hidden" name="jeton" value="<?= e($jeton) ?>">
    <?php // Le compte, pour que le navigateur range le mot de passe au bon endroit. ?>
    <input type="text" name="identifiant" value="<?= e((string) $compte['email']) ?>" autocomplete="username" hidden>
    <div class="champ">
      <label for="mot_de_passe">Nouveau mot de passe</label>
      <input type="password" id="mot_de_passe" name="mot_de_passe" required minlength="8" autocomplete="new-password" autofocus>
      <span class="champ__aide">8 caractères minimum.</span>
    </div>
    <div class="champ">
      <label for="mot_de_passe_confirmation">Confirmer le mot de passe</label>
      <input type="password" id="mot_de_passe_confirmation" name="mot_de_passe_confirmation" required minlength="8" autocomplete="new-password">
    </div>
    <button class="bouton bouton--bloc" type="submit">Enregistrer le mot de passe</button>
  </form>
<?php endif; ?>