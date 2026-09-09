<?php
/**
 * @var Fournisseur $f    l'agenda montré par cette page
 * @var bool $configuree  l'installation a-t-elle une application Microsoft ?
 * @var ?array $compte    la ligne « agenda_comptes », ou null
 * @var bool $relie       ce compte-ci est-il relié ?
 * @var ?string $derniere la dernière synchronisation, ou null
 * @var int $combien      combien d'évènements viennent de l'agenda
 * @var array $calendriers les calendriers du compte, tels qu'on les a vus
 * @var bool $partage     l'autorisation couvre-t-elle les calendriers partagés ?
 * @var int $envoyes      combien d'éléments d'ici vivent dans l'agenda
 * @var ?string $envoiLe  le dernier envoi, ou null
 * @var ?array $souci     le dernier échec, s'il n'a pas été suivi d'une réussite
 * @var string $retour    l'adresse à déclarer chez Microsoft
 */
?>

<div class="entete-page">
  <div>
    <p class="discret" style="margin-bottom:.35rem">
      <a href="<?= url('calendrier') ?>">← Calendrier</a>
    </p>
    <h1>Calendrier <?= e($f->nom()) ?></h1>
    <p>Relier votre agenda Microsoft à celui de l'application.</p>
  </div>
</div>

<?php
/*
 * Un calendrier partagé qu'on a coché mais qu'on n'a pas le droit de lire :
 * c'est la seule situation où la page doit insister. Cocher une case et ne
 * rien voir venir, sans que rien ne l'explique, est la pire des réponses.
 */
$partagesEnAttente = 0;
foreach (($calendriers ?? []) as $unCal) {
    if ((int) $unCal['suivi'] === 1 && (int) $unCal['partage'] === 1) {
        $partagesEnAttente++;
    }
}
$aReautoriser = !($partage ?? true) && $partagesEnAttente > 0 && ($souci ?? null) !== null;
?>

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
    <p>La liaison avec <?= e($f->nom()) ?> n'est pas encore activée sur cette installation.</p>
    <p class="champ__aide">
      Elle demande une inscription unique chez Microsoft, faite une fois pour
      toutes par la personne qui héberge l'application — pas par chacun.
    </p>
  </div>
<?php else: ?>
  <div class="colonnes">
    <div>
      <?php
      /*
       * Le dernier échec, quel que soit l'état où il a laissé le compte : une
       * synchronisation de fond n'a personne devant elle, et c'est ici qu'on
       * vient chercher pourquoi rien n'arrive plus.
       */
      ?>
      <?php if ($souci !== null): ?>
        <p class="outlook-attention">
          <strong>La dernière synchronisation a échoué</strong>
          (le <?= e(date('d/m/Y à H:i', strtotime($souci['quand']))) ?>) :
          <?= e($souci['quoi']) ?>
        </p>
      <?php endif; ?>

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
            <?php if ($envoiLe !== null): ?>
              <br>Dernier envoi le <?= e(date('d/m/Y à H:i', strtotime($envoiLe))) ?>,
              <?= $envoyes === 0 ? 'rien dans ' . $f->nom() : $envoyes . ' élément' . ($envoyes > 1 ? 's' : '') . ' dans « Mes Cours »' ?>.
            <?php endif; ?>
          </p>
          <?php if ($aReautoriser): ?>
            <p class="outlook-attention">
              <strong>Si Microsoft refuse les calendriers partagés, c'est l'autorisation.</strong>
              Vous suivez <?= $partagesEnAttente ?> calendrier<?= $partagesEnAttente > 1 ? 's' : '' ?>
              partagé<?= $partagesEnAttente > 1 ? 's' : '' ?> par quelqu'un d'autre, et votre
              autorisation date d'avant que l'application ne sache les lire.
              Essayez : selon le compte, cela passe. Si la lecture est refusée,
              réautorisez l'application — le bouton est plus bas.
            </p>
          <?php endif; ?>
          <form method="post" action="<?= url('agenda/' . $f->cle() . '/synchroniser') ?>">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
            <button class="bouton" type="submit">
              <?= $derniere === null ? 'Synchroniser mon agenda' : 'Synchroniser maintenant' ?>
            </button>
          </form>
          <p class="champ__aide" style="margin-top:.6rem">
            La synchronisation se fait <strong>toute seule</strong> quand vous
            ouvrez l'application, si la dernière remonte à plus de cinq
            minutes. Le bouton reste là pour ne pas attendre, et pour voir le
            message en cas de refus.
          </p>
          <p class="champ__aide">
            <strong>De <?= e($f->nom()) ?> vers ici :</strong> les calendriers cochés
            plus bas, du mois écoulé à l'année à venir, séries récurrentes
            comprises — et « Mes Cours » lui-même, pour que ce que vous y
            créez depuis <?= e($f->nom()) ?> arrive jusqu'ici. <?= e($f->nom()) ?> fait foi : un
            évènement importé que vous modifiez ici sera repris tel qu'il est
            là-bas. Le supprimer ici l'efface aussi dans <?= e($f->nom()) ?>, mais
            <strong>uniquement dans « Mes Cours » et dans votre calendrier
            principal</strong> : venu d'un agenda que quelqu'un vous a partagé,
            il est seulement retiré d'ici, et reviendra à la lecture suivante.
            L'application n'efface rien chez les autres.
          </p>
          <p class="champ__aide">
            <strong>D'ici vers <?= e($f->nom()) ?> :</strong> vos évènements et les
            échéances de vos tâches non faites, dans un calendrier
            <strong>« Mes Cours »</strong> que l'application crée chez
            Microsoft. Elle n'écrit que là : votre agenda existant n'est
            jamais touché, et vous pouvez masquer ou supprimer ce calendrier
            depuis <?= e($f->nom()) ?>. Une tâche cochée quitte l'agenda, un évènement
            supprimé ici disparaît là-bas.
          </p>
          <div class="outlook-defaire">
            <?php if ($combien > 0): ?>
              <form method="post" action="<?= url('agenda/' . $f->cle() . '/retirer') ?>"
                    data-confirmation="Retirer du calendrier les évènements venus <?= e(de_agenda($f->nom())) ?> ? Ils restent dans votre agenda Microsoft.">
                <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                <button class="bouton bouton--secondaire bouton--petit" type="submit">
                  Retirer les évènements importés
                </button>
              </form>
            <?php endif; ?>
            <?php if ($envoyes > 0): ?>
              <form method="post" action="<?= url('agenda/' . $f->cle() . '/retirer-envoi') ?>"
                    data-confirmation="Retirer <?= e(de_agenda($f->nom())) ?> ce que l'application y a mis ? Vos évènements et vos tâches restent ici, intacts.">
                <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                <button class="bouton bouton--secondaire bouton--petit" type="submit">
                  Retirer mes évènements <?= e(de_agenda($f->nom())) ?>
                </button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= url('agenda/' . $f->cle() . '/deconnexion') ?>"
                  data-confirmation="Délier votre compte <?= e($f->nom()) ?> ? L'application n'accèdera plus à votre agenda, et les évènements importés quitteront le calendrier.">
              <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
              <button class="bouton bouton--danger bouton--petit" type="submit">Délier mon compte</button>
            </form>
          </div>
        </section>
      <?php else: ?>
        <section class="carte">
          <h2 style="margin-top:0">Relier votre compte</h2>
          <p>
            Vos rendez-vous et vos cours <?= e($f->nom()) ?> rejoindront le calendrier de
            l'application, et vos évènements d'ici rejoindront le vôtre.
          </p>
          <form method="post" action="<?= url('agenda/' . $f->cle() . '/connexion') ?>">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <button class="bouton" type="submit">Connecter mon compte <?= e($f->nom()) ?></button>
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
            Votre autorisation date d'avant que l'application ne demande une
            permission pour les calendriers <strong>partagés par quelqu'un
            d'autre</strong>. Elle n'est pas toujours nécessaire — selon le
            compte, Microsoft les donne sans rien de plus. Si l'un d'eux vous
            est refusé, réautorisez : rien n'est perdu, ni votre liaison, ni
            vos évènements.
          </p>
          <form method="post" action="<?= url('agenda/' . $f->cle() . '/connexion') ?>" style="margin-bottom:.9rem">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <button class="bouton" type="submit">Réautoriser l'application</button>
          </form>
        <?php endif; ?>

        <?php if ($calendriers === []): ?>
          <p class="champ__aide" style="margin-top:0">
            L'application n'a lu que votre calendrier principal. Demandez à
            Microsoft la liste complète pour choisir les autres.
          </p>
        <?php else: ?>
          <form method="post" action="<?= url('agenda/' . $f->cle() . '/suivre') ?>">
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

        <form method="post" action="<?= url('agenda/' . $f->cle() . '/calendriers') ?>" style="margin-top:.7rem">
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
          <code><?= e($f->cle() === 'microsoft' ? 'outlook' : $f->cle()) ?></code>, qui ne va pas au dépôt.
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
              <code><?= e($f->cle() === 'microsoft' ? 'outlook' : $f->cle()) ?>.secret</code>.</li>
          <li>Reportez l'<strong>ID d'application (client)</strong> dans <code><?= e($f->cle() === 'microsoft' ? 'outlook' : $f->cle()) ?>.client_id</code>.</li>
        </ol>
        <p class="champ__aide">
          En ligne, inscrivez aussi <code><?= e($f->cle() === 'microsoft' ? 'outlook' : $f->cle()) ?>.adresse_retour</code> en clair :
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
