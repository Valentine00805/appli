<?php
/**
 * Le lien public d'un travail de groupe, avant qu'on ait dit son prénom : rien du projet ne se voit, sinon son nom.
 *
 * @var array $projet
 * @var string $jeton
 * @var ?string $erreur
 * @var string $prenom  ce qui vient d'être tapé, à remettre dans le champ
 */
?>
<section class="carte entree-lien">
  <p class="entree-lien__icone" aria-hidden="true">👋</p>
  <h1 class="entree-lien__titre"><?= e((string) $projet['nom']) ?></h1>
  <p class="discret entree-lien__aide"><?= e(t('tr.pu.entree_aide')) ?></p>
  <?php if ($erreur !== null): ?>
    <p class="alerte" role="alert" style="margin:.6rem 0"><?= e($erreur) ?></p>
  <?php endif; ?>
  <form method="post" action="<?= url('g/' . $jeton . '/entrer') ?>" class="entree-lien__formulaire">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <div class="champ">
      <label for="prenom-visiteur"><?= e(t('tr.pu.votre_prenom')) ?></label>
      <input type="text" id="prenom-visiteur" name="prenom" required autofocus autocomplete="given-name" maxlength="<?= Travaux::PRENOM_MAX ?>"
             value="<?= e($prenom) ?>" placeholder="<?= e(t('tr.pu.prenom_exemple')) ?>">
    </div>
    <button class="bouton bouton--bloc" type="submit"><?= e(t('tr.pu.acceder')) ?></button>
  </form>
  <p class="champ__aide" style="margin:.8rem 0 0"><?= e(t('tr.pu.entree_confidence')) ?></p>
</section>
