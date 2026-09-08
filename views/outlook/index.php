<?php
/**
 * @var bool $configuree  l'installation a-t-elle une application Microsoft ?
 * @var ?array $compte    la ligne « outlook_comptes », ou null
 * @var bool $relie       ce compte-ci est-il relié ?
 * @var string $retour    l'adresse à déclarer chez Microsoft
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

<?php if (!$configuree): ?>
  <?php
  /*
   * Rien n'est configuré : ce n'est pas à la personne qui lit de s'en occuper,
   * mais à celle qui tient l'installation. On le dit sans l'accabler de
   * démarches, et le détail attend, replié, celle que ça regarde.
   */
  ?>
  <div class="vide">
    <span class="vide__icone">📆</span>
    <p>La liaison avec Outlook n'est pas encore activée sur cette installation.</p>
    <p class="champ__aide">
      Elle demande une inscription unique chez Microsoft, faite une fois pour
      toutes par la personne qui héberge l'application — pas par chacun.
    </p>
  </div>
<?php else: ?>
  <div class="colonnes">
    <div>
      <?php if ($relie): ?>
        <section class="carte">
          <h2 style="margin-top:0">✅ Votre compte est relié</h2>
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
                data-confirmation="Délier votre compte Outlook ? L'application n'accèdera plus à votre agenda.">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <button class="bouton bouton--danger bouton--petit" type="submit">Délier mon compte</button>
          </form>
        </section>
      <?php else: ?>
        <section class="carte">
          <h2 style="margin-top:0">Relier votre compte</h2>
          <p>
            Vos rendez-vous et vos cours Outlook rejoindront le calendrier de
            l'application, et vos évènements d'ici rejoindront le vôtre.
          </p>
          <form method="post" action="<?= url('outlook/connexion') ?>">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <button class="bouton" type="submit">Connecter mon compte Outlook</button>
          </form>
          <p class="champ__aide" style="margin-top:.6rem">
            Microsoft vous demandera de choisir votre compte et d'accepter
            l'accès à votre agenda, puis vous ramènera ici. Vous pouvez délier
            le compte quand vous voulez.
          </p>
        </section>
      <?php endif; ?>

      <section class="carte" style="margin-top:1rem">
        <h2 style="margin-top:0">Ce que l'application voit</h2>
        <p class="champ__aide" style="margin-top:0">
          Votre agenda, et rien d'autre : elle demande la permission de lire et
          d'écrire vos évènements, votre nom et votre adresse. Ni vos messages,
          ni vos fichiers, ni vos contacts.
        </p>
        <p class="champ__aide">
          L'autorisation obtenue est gardée dans la base de l'application. Elle
          est <strong>exclue des sauvegardes exportables</strong>, et délier
          votre compte l'efface.
        </p>
      </section>
    </div>

    <div>
      <?php
      /*
       * Le détail de l'inscription ne concerne que qui tient l'installation :
       * il reste replié, plutôt que de faire croire à chacun qu'il a des
       * démarches à faire.
       */
      ?>
      <details class="carte">
        <summary style="cursor:pointer;font-weight:650">
          Pour qui héberge l'application
        </summary>
        <p class="champ__aide">
          Une seule inscription chez Microsoft sert à tout le monde. Elle se
          règle dans <code>config/parametres.php</code>, section
          <code>outlook</code>, qui ne va pas au dépôt.
        </p>
        <ol class="outlook-marche">
          <li>Sur <a href="https://entra.microsoft.com" target="_blank" rel="noopener">entra.microsoft.com</a> :
              <em>Applications</em> › <em>Inscriptions d'applications</em> › <em>Nouvelle inscription</em>.</li>
          <li>Comptes pris en charge :
              <em>Comptes dans un annuaire organisationnel et comptes Microsoft personnels</em>.</li>
          <li>URI de redirection — inscrivez celle-ci, au mot près :
              <br><code class="outlook-retour"><?= e($retour) ?></code></li>
          <li>Plateforme : <strong>Applications mobiles et de bureau</strong> en local, sans secret ;
              <strong>Web</strong> une fois en ligne, avec un secret client à recopier dans
              <code>outlook.secret</code>.</li>
          <li>Reportez l'<strong>ID d'application (client)</strong> dans <code>outlook.client_id</code>.</li>
        </ol>
        <p class="champ__aide">
          En ligne, inscrivez aussi <code>outlook.adresse_retour</code> en clair :
          ce qu'un navigateur annonce comme hôte ne se croit pas sur parole, et
          cette adresse doit correspondre à celle déclarée chez Microsoft.
        </p>
        <p class="champ__aide">
          Certains établissements exigent qu'un administrateur autorise
          l'application avant que leurs comptes puissent s'y relier. Les comptes
          personnels, eux, n'ont besoin de personne.
        </p>
      </details>
    </div>
  </div>
<?php endif; ?>
