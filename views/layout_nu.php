<?php
/** Gabarit sans navigation, pour les pages de connexion / inscription. */
?>
<!doctype html>
<?php // L'apparence choisie dans « Mon compte » ; « auto » suit l'appareil. ?>
<html lang="<?= e(Langue::courante()) ?>" data-theme="<?= e(Auth::theme()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#4f46e5">
<title><?= e($titrePage) ?></title>
<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
<link rel="icon" type="image/png" href="<?= asset('assets/img/logo.png') ?>">
</head>
<body class="page-auth">

<main class="auth">
  <div class="auth__carte">
    <div class="auth__marque">
      <img class="auth__logo" src="<?= asset('assets/img/logo.png') ?>" alt="" width="120" height="120" decoding="async">
      <h1><?= e((string) Config::get('app', 'nom')) ?></h1>
      <p><?= e(t('accueil.pitch')) ?></p>
    </div>

    <?php if ($flashs !== []): ?>
      <div class="flashs">
        <?php foreach ($flashs as $flash): ?>
          <div class="flash flash--<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?= $contenu ?>
  </div>
  <?= Vue::rendre('_choix_langue') ?>
</main>

<script>window.MOTS = <?= json_encode(Langue::pourLeScript(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= asset('assets/js/mot-de-passe.js') ?>" defer></script>
</body>
</html>
