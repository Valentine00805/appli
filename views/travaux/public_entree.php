<?php
/**
 * Le lien public d'un travail de groupe, avant qu'on ait dit son prénom : rien du projet ne se voit, sinon son nom.
 *
 * @var array $projet
 * @var string $jeton
 * @var ?string $erreur
 * @var string $prenom  ce qui vient d'être tapé (ou le prénom déjà pris, quand on pose la question)
 * @var string $etat  « saisie » (le champ), « question » (« est-ce vous ? » : ce prénom est déjà entré), « distinguer » (une autre personne du même prénom)
 */
$etat = $etat ?? 'saisie';
$action = url('g/' . $jeton . '/entrer');
$csrf = Session::jetonCsrf();
?>
<section class="carte entree-lien">
  <p class="entree-lien__icone" aria-hidden="true">👋</p>
  <h1 class="entree-lien__titre"><?= e((string) $projet['nom']) ?></h1>

  <?php if ($etat === 'question'): ?>
    <?php // Ce prénom est déjà entré par le lien : est-ce la même personne qui revient (un navigateur fermé, un autre appareil), ou une autre ? ?>
    <p class="entree-lien__question"><?= e(t('tr.pu.deja_entre', ['prenom' => $prenom])) ?></p>
    <p class="discret entree-lien__aide"><?= e(t('tr.pu.deja_entre_aide')) ?></p>
    <form method="post" action="<?= $action ?>" class="entree-lien__choix">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="prenom" value="<?= e($prenom) ?>">
      <button class="bouton bouton--bloc" type="submit" name="reponse" value="moi"><?= e(t('tr.pu.oui_moi')) ?></button>
      <button class="bouton bouton--secondaire bouton--bloc" type="submit" name="reponse" value="autre"><?= e(t('tr.pu.non_autre')) ?></button>
    </form>
  <?php else: ?>
    <p class="discret entree-lien__aide"><?= e($etat === 'distinguer' ? t('tr.pu.distinguer', ['prenom' => $prenom]) : t('tr.pu.entree_aide')) ?></p>
    <?php if ($erreur !== null): ?>
      <p class="alerte" role="alert" style="margin:.6rem 0"><?= e($erreur) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= $action ?>" class="entree-lien__formulaire">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <div class="champ">
        <label for="prenom-visiteur"><?= e(t('tr.pu.votre_prenom')) ?></label>
        <?php // À se distinguer : le prénom est déjà là, suivi d'une espace — il n'y a plus qu'à ajouter l'initiale. ?>
        <input type="text" id="prenom-visiteur" name="prenom" required autofocus autocomplete="given-name" maxlength="<?= Travaux::PRENOM_MAX ?>"
               value="<?= e($etat === 'distinguer' ? $prenom . ' ' : $prenom) ?>" placeholder="<?= e(t('tr.pu.prenom_exemple')) ?>">
      </div>
      <button class="bouton bouton--bloc" type="submit"><?= e(t('tr.pu.acceder')) ?></button>
    </form>
    <p class="champ__aide" style="margin:.8rem 0 0"><?= e(t('tr.pu.entree_confidence')) ?></p>
    <?php // Quelqu'un qui a un compte peut s'y connecter (et revenir ici) ; sinon il peut en créer un, si les inscriptions sont ouvertes. ?>
    <div class="entree-lien__compte">
      <p class="entree-lien__compte-ligne">
        <span><?= e(t('tr.pu.deja_compte')) ?></span>
        <a class="bouton bouton--secondaire bouton--petit" href="<?= url('g/' . $jeton . '/se-connecter') ?>"><?= e(t('auth.se_connecter')) ?></a>
      </p>
      <?php if (Config::get('app', 'inscription_ouverte')): ?>
        <p class="entree-lien__compte-ligne">
          <span><?= e(t('auth.pas_de_compte')) ?></span>
          <?php // Le bouton qui compte : plein, avec l'icône d'une personne et son « + ». ?>
          <a class="bouton bouton--petit entree-lien__creer" href="<?= url('inscription') ?>">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
              <circle cx="9" cy="8" r="4"/><path d="M2 21v-1a6 6 0 0 1 6-6h2a6 6 0 0 1 6 6v1"/><path d="M19 8v6M16 11h6"/>
            </svg>
            <?= e(t('auth.creer_compte')) ?>
          </a>
        </p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>
