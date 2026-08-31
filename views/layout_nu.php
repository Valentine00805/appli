<?php
/** Gabarit sans navigation, pour les pages de connexion / inscription. */
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#4f46e5">
<title><?= e($titrePage) ?></title>
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>?v=1">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📚</text></svg>">
</head>
<body class="page-auth">

<main class="auth">
  <div class="auth__carte">
    <div class="auth__marque">
      <span aria-hidden="true">📚</span>
      <h1><?= e((string) Config::get('app', 'nom')) ?></h1>
      <p>Vos cours, vos fichiers et votre planning au même endroit.</p>
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
</main>

</body>
</html>
