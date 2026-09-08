<?php
/**
 * @var bool $configuree  l'installation a-t-elle une application Microsoft ?
 * @var ?array $compte    la ligne « outlook_comptes », ou null
 * @var bool $relie       ce compte-ci est-il relié ?
 * @var ?string $derniere la dernière synchronisation, ou null
 * @var int $combien      combien d'évènements viennent d'Outlook
 * @var array $calendriers les calendriers du compte, tels qu'on les a vus
 * @var bool $partage     l'autorisation couvre-t-elle les calendriers partagés ?
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
            <?php if ($derniere === null): ?>
              Vos évènements ne sont pas encore venus : lancez une première lecture.
            <?php else: ?>
              Dernière lecture le <?= e(date('d/m/Y à H:i', strtotime($derniere))) ?>,
              <?= $combien === 0 ? 'aucun évènement suivi' : $combien . ' évènement' . ($combien > 1 ? 's' : '') . ' suivi' . ($combien > 1 ? 's' : '') ?>.
            <?php endif; ?>
          </p>
          <form method="post" action="<?= url('outlook/synchroniser') ?>">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <button class="bouton" type="submit">
              <?= $derniere === null ? 'Faire venir mes évènements' : 'Relire mon agenda' ?>
            </button>
          </form>
          <p class="champ__aide" style="margin-top:.6rem">
            La lecture porte sur le mois écoulé et l'année à venir, séries
            récurrentes comprises. Le sens est unique pour l'instant :
            <strong>Outlook fait foi</strong>. Un évènement importé que vous
            modifiez ici sera repris tel qu'il est là-bas à la lecture
            suivante, et si vous le supprimez ici, il reviendra.
          </p>
          <div class="outlook-defaire">
            <?php if ($combien > 0): ?>
              <form method="post" action="<?= url('outlook/retirer') ?>"
                    data-confirmation="Retirer du calendrier les évènements venus d'Outlook ? Ils restent dans votre agenda Microsoft.">
                <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                <button class="bouton bouton--secondaire bouton--petit" type="submit">
                  Retirer les évènements importés
                </button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= url('outlook/deconnexion') ?>"
                  data-confirmation="Délier votre compte Outlook ? L'application n'accèdera plus à votre agenda, et les évènements importés quitteront le calendrier.">
              <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
              <button class="bouton bouton--danger bouton--petit" type="submit">Délier mon compte</button>
            </form>
          </div>
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

      <?php
      /*
       * Le choix des calendriers.
       *
       * Un agenda est rarement d'un seul tenant : le calendrier personnel, les
       * jours fériés, celui d'un proche qu'on a accepté. Microsoft les tient
       * séparés et l'application n'en lit aucun sans qu'on l'ait dit — remplir
       * le calendrier de quelqu'un sans le lui demander serait pire que de ne
       * rien lire du tout.
       */
      ?>
      <section class="carte" style="margin-top:1rem">
        <h2 style="margin-top:0">Les calendriers à lire</h2>

        <?php if (!$partage): ?>
          <p class="champ__aide" style="margin-top:0">
            ⚠️ Votre autorisation date d'avant que l'application ne sache lire
            les calendriers <strong>partagés par quelqu'un d'autre</strong>.
            Pour y accéder, réautorisez-la : rien n'est perdu, ni votre liaison,
            ni vos évènements.
          </p>
          <form method="post" action="<?= url('outlook/connexion') ?>" style="margin-bottom:.9rem">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <button class="bouton bouton--secondaire bouton--petit" type="submit">
              Réautoriser l'application
            </button>
          </form>
        <?php endif; ?>

        <?php if ($calendriers === []): ?>
          <p class="champ__aide" style="margin-top:0">
            L'application n'a lu que votre calendrier principal. Demandez à
            Microsoft la liste complète pour choisir les autres.
          </p>
        <?php else: ?>
          <form method="post" action="<?= url('outlook/suivre') ?>">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <ul class="outlook-calendriers">
              <?php foreach ($calendriers as $cal): ?>
                <li>
                  <label>
                    <input type="checkbox" name="calendriers[]"
                           value="<?= e((string) $cal['empreinte']) ?>"
                           <?= (int) $cal['suivi'] === 1 ? 'checked' : '' ?>>
                    <span><?= e((string) ($cal['nom'] ?? 'Calendrier')) ?></span>
                    <?php if ((int) $cal['principal'] === 1): ?>
                      <em class="discret">principal</em>
                    <?php elseif ((int) $cal['partage'] === 1): ?>
                      <em class="discret">partagé par <?= e((string) ($cal['proprietaire'] ?? 'quelqu’un')) ?></em>
                    <?php endif; ?>
                  </label>
                </li>
              <?php endforeach; ?>
            </ul>
            <button class="bouton bouton--petit" type="submit">Enregistrer mon choix</button>
          </form>
        <?php endif; ?>

        <form method="post" action="<?= url('outlook/calendriers') ?>" style="margin-top:.7rem">
          <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
          <button class="bouton bouton--secondaire bouton--petit" type="submit">
            <?= $calendriers === [] ? 'Voir mes calendriers' : 'Actualiser la liste' ?>
          </button>
        </form>
      </section>

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
