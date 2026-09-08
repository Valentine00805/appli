<?php
/**
 * @var ?array $compte  la ligne « outlook_comptes », ou null
 * @var bool $relie     un compte Outlook est-il relié ?
 * @var string $retour  l'adresse à déclarer chez Microsoft
 */
?>

<div class="entete-page">
  <div>
    <p class="discret" style="margin-bottom:.35rem">
      <a href="<?= url('calendrier') ?>">← Calendrier</a>
    </p>
    <h1>Calendrier Outlook</h1>
    <p>Relier votre agenda Microsoft à celui de l'application.</p>
  </div>
</div>

<div class="colonnes">
  <div>
    <?php if ($relie): ?>
      <section class="carte">
        <h2 style="margin-top:0">✅ Compte relié</h2>
        <p>
          L'application accède à l'agenda de
          <strong><?= e((string) ($compte['compte'] ?? 'votre compte')) ?></strong><?php
          ?><?= ($compte['calendrier_nom'] ?? '') === ''
              ? '' : ', calendrier « ' . e((string) $compte['calendrier_nom']) . ' »' ?>.
        </p>
        <p class="champ__aide">
          La synchronisation des évènements n'est pas encore en place : pour
          l'instant, la liaison est faite et l'autorisation obtenue.
        </p>
        <form method="post" action="<?= url('outlook/deconnexion') ?>"
              data-confirmation="Délier le compte Outlook ? L'application n'accèdera plus à votre agenda.">
          <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
          <button class="bouton bouton--danger bouton--petit" type="submit">Délier le compte</button>
        </form>
      </section>
    <?php elseif ($compte !== null): ?>
      <section class="carte">
        <h2 style="margin-top:0">Application enregistrée</h2>
        <p>
          Identifiant : <code><?= e((string) $compte['client_id']) ?></code><br>
          Comptes acceptés : <code><?= e((string) $compte['locataire']) ?></code>
        </p>
        <form method="post" action="<?= url('outlook/connexion') ?>">
          <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
          <button class="bouton" type="submit">Relier mon compte Outlook</button>
        </form>
        <p class="champ__aide" style="margin-top:.6rem">
          Vous serez envoyé chez Microsoft pour choisir le compte et accepter
          l'accès à votre agenda, puis ramené ici.
        </p>
      </section>
    <?php endif; ?>

    <?php // --- Ce qu'il faut faire chez Microsoft, une fois pour toutes --- ?>
    <section class="carte" style="margin-top:1rem">
      <h2 style="margin-top:0"><?= $compte === null ? 'À faire d’abord' : 'Rappel de l’inscription' ?></h2>
      <p class="champ__aide" style="margin-top:0">
        L'application ne peut pas s'inscrire toute seule chez Microsoft : cette
        démarche vous appartient, et elle est gratuite.
      </p>
      <ol class="outlook-marche">
        <li>Ouvrez <a href="https://entra.microsoft.com" target="_blank" rel="noopener">entra.microsoft.com</a>,
            puis <em>Applications</em> › <em>Inscriptions d'applications</em> › <em>Nouvelle inscription</em>.</li>
        <li>Nommez-la comme vous voulez — « Mes Cours », par exemple.</li>
        <li>Pour les comptes pris en charge, choisissez
            <em>Comptes dans un annuaire organisationnel et comptes Microsoft personnels</em>.</li>
        <li>Dans <em>URI de redirection</em>, choisissez la plateforme
            <strong>Applications mobiles et de bureau</strong>, et inscrivez exactement :
            <br><code class="outlook-retour"><?= e($retour) ?></code></li>
        <li>Inscrivez. Copiez ensuite l'<strong>ID d'application (client)</strong> affiché sur la page.</li>
      </ol>
      <p class="champ__aide">
        La plateforme « mobile et bureau » est celle qui convient : l'application
        n'a alors aucun secret à garder, et prouve son identité autrement.
        Aucun secret client n'est nécessaire.
      </p>
    </section>
  </div>

  <div>
    <section class="carte">
      <h2 style="margin-top:0"><?= $compte === null ? 'Enregistrer l’application' : 'Changer d’application' ?></h2>
      <form method="post" action="<?= url('outlook/enregistrer') ?>">
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

        <div class="champ">
          <label for="client_id">ID d'application (client)</label>
          <input type="text" id="client_id" name="client_id" required
                 placeholder="11111111-2222-3333-4444-555555555555"
                 value="<?= e((string) ($compte['client_id'] ?? '')) ?>">
        </div>

        <div class="champ">
          <label for="locataire">Comptes acceptés</label>
          <input type="text" id="locataire" name="locataire"
                 value="<?= e((string) ($compte['locataire'] ?? 'common')) ?>">
          <span class="champ__aide">
            <code>common</code> convient dans presque tous les cas : compte
            personnel comme compte d'établissement. N'y mettez l'identifiant de
            votre établissement que s'il l'exige.
          </span>
        </div>

        <button class="bouton bouton--bloc" type="submit">Enregistrer</button>
      </form>

      <?php if ($compte !== null): ?>
        <p class="champ__aide" style="margin-top:.7rem">
          Changer d'identifiant délie le compte : les autorisations obtenues par
          l'ancienne inscription ne valent rien pour la nouvelle.
        </p>
      <?php endif; ?>
    </section>

    <section class="carte" style="margin-top:1rem">
      <h2 style="margin-top:0">Ce qui est gardé ici</h2>
      <p class="champ__aide" style="margin-top:0">
        Les autorisations obtenues sont enregistrées dans votre base, en clair,
        comme le reste de l'application. Elles ouvrent l'agenda du compte relié :
        elles sont donc <strong>exclues des sauvegardes exportables</strong>, pour
        qu'une archive partagée ne les emporte pas. Délier le compte les efface.
      </p>
    </section>
  </div>
</div>
