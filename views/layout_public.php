<?php
/**
 * Gabarit des pages ouvertes par un lien public : sans menu, pour quelqu'un
 * qui n'a peut-être pas de compte.
 * Variables attendues : $titrePage, $contenu
 */
?>
<!doctype html>
<?php // L'apparence choisie dans « Mon compte » ; « auto » suit l'appareil. ?>
<html lang="<?= e(Langue::courante()) ?>" data-theme="<?= e(Auth::theme()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#4f46e5">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titrePage) ?></title>
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<link rel="icon" type="image/png" href="<?= asset('assets/img/logo.png') ?>">
</head>
<body class="page-publique">

<header class="entete">
  <div class="entete__interieur">
    <a class="marque" href="<?= url('') ?>">
      <img class="marque__icone" src="<?= asset('assets/img/logo.png') ?>" alt="" width="44" height="44" decoding="async">
      <span><?= e((string) Config::get('app', 'nom')) ?></span>
    </a>
  </div>
</header>

<main id="contenu" class="conteneur">
  <?= $contenu ?>
</main>

<?php // Quelqu'un qui ouvre un lien public n'a pas forcément de compte : il choisit sa langue ici. ?>
<footer class="pied-public">
  <?php if (!Auth::connecte()): ?>
    <?= Vue::rendre('_choix_langue') ?>
  <?php endif; ?>
</footer>

</body>
</html>
