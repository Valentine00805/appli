<?php
/**
 * Le lien reçu par e-mail : choisir un nouveau mot de passe.
 *
 * @var string $jeton  vide si le lien n'est plus valable
 * @var ?array $compte
 * @var ?string $erreur
 */
?>
<h2 class="auth__titre"><?= e(t('auth.nouveau_mdp_titre')) ?></h2>

<?php if ($compte === null): ?>
  <div class="flash flash--erreur" role="alert">
    <?= e($erreur ?? t('auth.lien_perime')) ?>
  </div>
  <p class="auth__bas"><a href="<?= url('mot-de-passe/oublie') ?>"><?= e(t('auth.nouvelle_demande')) ?></a></p>
<?php else: ?>
  <?php if ($erreur !== null): ?>
    <div class="flash flash--erreur" role="alert"><?= e($erreur) ?></div>
  <?php endif; ?>
  <p class="champ__aide" style="margin-top:0">
    <?= t('auth.pour_le_compte', ['qui' => e((string) ($compte['pseudo'] ?: $compte['email']))]) ?>
  </p>
  <form method="post" action="<?= url('mot-de-passe/nouveau') ?>">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <input type="hidden" name="jeton" value="<?= e($jeton) ?>">
    <?php // Le compte, pour que le navigateur range le mot de passe au bon endroit. ?>
    <input type="text" name="identifiant" value="<?= e((string) $compte['email']) ?>" autocomplete="username" hidden>
    <div class="champ">
      <label for="mot_de_passe"><?= e(t('auth.nouveau_mdp_titre')) ?></label>
      <input type="password" id="mot_de_passe" name="mot_de_passe" required minlength="8" autocomplete="new-password" autofocus>
      <span class="champ__aide"><?= e(t('auth.huit_caracteres')) ?></span>
    </div>
    <div class="champ">
      <label for="mot_de_passe_confirmation"><?= e(t('auth.confirmer_mdp')) ?></label>
      <input type="password" id="mot_de_passe_confirmation" name="mot_de_passe_confirmation" required minlength="8" autocomplete="new-password">
    </div>
    <button class="bouton bouton--bloc" type="submit"><?= e(t('auth.enregistrer_mdp')) ?></button>
  </form>
<?php endif; ?>