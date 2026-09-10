<?php
/**
 * Gabarit principal (barre de navigation + contenu).
 * Variables attendues : $titrePage, $contenu, $utilisateur, $flashs
 */
$route = ROUTE;
$actif = static function (string $prefixe) use ($route): string {
    if ($prefixe === '') {
        return $route === '' ? ' aria-current="page"' : '';
    }
    return str_starts_with($route, $prefixe) ? ' aria-current="page"' : '';
};
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#4f46e5">
<title><?= e($titrePage) ?></title>
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📚</text></svg>">
</head>
<body>

<a class="lien-evitement" href="#contenu">Aller au contenu</a>

<header class="entete" id="haut">
  <div class="entete__interieur">
    <a class="marque" href="<?= url('') ?>">
      <span class="marque__icone" aria-hidden="true">📚</span>
      <span><?= e((string) Config::get('app', 'nom')) ?></span>
    </a>

    <button class="burger" type="button" aria-expanded="false" aria-controls="navigation" aria-label="Ouvrir le menu">
      <span></span><span></span><span></span>
    </button>

    <nav class="nav" id="navigation" aria-label="Navigation principale">
      <a href="<?= url('') ?>"<?= $actif('') ?>>Accueil</a>
      <a href="<?= url('calendrier') ?>"<?= $actif('calendrier') ?>>Calendrier</a>
      <a href="<?= url('cours') ?>"<?= $actif('cours') ?>>Mes cours</a>
      <a href="<?= url('revision') ?>"<?= $actif('revision') ?>>Révision</a>
      <a href="<?= url('cartes') ?>"<?= $actif('cartes') ?>>Cartes</a>
      <a href="<?= url('taches') ?>"<?= $actif('taches') ?>>Tâches</a>
      <a href="<?= url('tableau') ?>"<?= $actif('tableau') ?>>Tableau</a>
      <a href="<?= url('budget') ?>"<?= $actif('budget') ?>>Budget</a>
      <a href="<?= url('organisation/matieres') ?>"<?= $actif('organisation') ?>>Organisation</a>

      <form class="recherche-rapide" action="<?= url('recherche') ?>" method="get" role="search">
        <input type="search" name="q" placeholder="Rechercher…" aria-label="Rechercher dans mes cours"
               value="<?= e((string) ($_GET['q'] ?? '')) ?>">
      </form>

      <div class="nav__compte">
        <?php if ($utilisateur !== null): ?>
          <a class="nav__utilisateur" href="<?= url('compte') ?>" title="Mon compte">
            <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $utilisateur['nom'], 0, 1))) ?></span>
            <span class="nav__utilisateur-nom"><?= e((string) $utilisateur['nom']) ?></span>
          </a>
          <form action="<?= url('deconnexion') ?>" method="post">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <button class="bouton bouton--discret" type="submit">Déconnexion</button>
          </form>
        <?php else: ?>
          <a class="bouton bouton--secondaire" href="<?= url('connexion') ?>">Connexion</a>
        <?php endif; ?>
      </div>
    </nav>
  </div>
</header>

<main id="contenu" class="conteneur">
  <?php if ($flashs !== []): ?>
    <div class="flashs">
      <?php foreach ($flashs as $flash): ?>
        <div class="flash flash--<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?= $contenu ?>
</main>

<?php
/*
 * Remonter en haut de la page.
 *
 * Un lien d'ancre, et non un bouton : sans JavaScript il remonte quand même,
 * d'un saut plutôt qu'en glissant. Le cercle reste vide dans ce cas — il ne
 * promet rien qu'il ne tienne.
 *
 * Le script prend le relais : il remplit le cercle à mesure qu'on descend et
 * efface la flèche tant qu'on est en haut, où elle n'aurait rien à faire.
 */
?>
<a class="haut-de-page" href="#haut" title="Remonter en haut de la page">
  <svg class="haut-de-page__anneau" viewBox="0 0 44 44" aria-hidden="true" focusable="false">
    <circle class="haut-de-page__piste" cx="22" cy="22" r="20"></circle>
    <circle class="haut-de-page__part" cx="22" cy="22" r="20"></circle>
  </svg>
  <span class="haut-de-page__fleche" aria-hidden="true">↑</span>
  <span class="sr-only">Remonter en haut de la page</span>
</a>

<footer class="pied">
  <p><?= e((string) Config::get('app', 'nom')) ?> — vos cours et votre planning, en local.</p>
</footer>

<?php
/*
 * La relecture de l'agenda Outlook, sans qu'on ait à la demander.
 *
 * Le repère n'apparaît que lorsqu'il y a lieu de relire : compte relié,
 * calendriers choisis, dernière lecture assez ancienne. C'est le serveur qui
 * en juge, et il le rejuge à la réception — la page ne fait que réveiller.
 *
 * Sans JavaScript, il ne se passe rien de plus qu'avant : le bouton de la
 * page Outlook reste la voie sûre.
 */
?>
<?php if (Auth::connecte() && Agenda::aQuelqueChoseAFaire(Auth::id())): ?>
  <div hidden data-outlook-relire="<?= e(url('agenda/synchroniser')) ?>"
       data-csrf="<?= e(Session::jetonCsrf()) ?>"></div>
<?php endif; ?>

<script src="<?= asset('assets/js/app.js') ?>" defer></script>
</body>
</html>
