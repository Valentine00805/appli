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
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📚</text></svg>">
</head>
<body class="page-publique">

<header class="entete">
  <div class="entete__interieur">
    <a class="marque" href="<?= url('') ?>">
      <span class="marque__icone" aria-hidden="true">📚</span>
      <span><?= e((string) Config::get('app', 'nom')) ?></span>
    </a>
  </div>
</header>

<main id="contenu" class="conteneur">
  <?= $contenu ?>
</main>

</body>
</html>
