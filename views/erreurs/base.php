<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Base de données inaccessible</title>
<style>
  body { font: 16px/1.6 system-ui, sans-serif; background: #f5f6fa; color: #1c2033;
         display: grid; place-items: center; min-height: 100vh; margin: 0; padding: 1.5rem; }
  .boite { background: #fff; border-radius: 14px; padding: 1.75rem; max-width: 560px;
           box-shadow: 0 10px 40px rgba(20,25,50,.12); }
  h1 { font-size: 1.3rem; margin-top: 0; }
  code { background: #eef0f7; padding: .1rem .35rem; border-radius: 5px; }
  ol { padding-left: 1.2rem; }
  li { margin-bottom: .4rem; }
</style>
</head>
<body>
<div class="boite">
  <h1>⚠️ Impossible de se connecter à la base de données</h1>
  <p>L'application n'arrive pas à joindre MySQL. Vérifiez dans l'ordre :</p>
  <ol>
    <li>WAMP est bien démarré et son icône est <strong>verte</strong> (service MySQL actif).</li>
    <li>La base a été créée : importez le fichier <code>sql/schema.sql</code>
        (via phpMyAdmin, onglet « Importer »).</li>
    <li>Les identifiants MySQL de <code>config/config.php</code> sont corrects
        (par défaut : utilisateur <code>root</code>, mot de passe vide).</li>
  </ol>
  <p>Le détail technique de l'erreur n'est pas affiché ici pour des raisons de sécurité.</p>
</div>
</body>
</html>
