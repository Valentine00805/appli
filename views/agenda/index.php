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
 * @var array $ouEcrire  les agendas à soi qui peuvent recevoir
 * @var array $destination  celui qui reçoit : [id, nom, choisi]
 * @var ?array $souci     le dernier échec, s'il n'a pas été suivi d'une réussite
 * @var string $retour    l'adresse à déclarer chez Microsoft
 */

// « le 29/09/2026 à 13:54 » : la tournure et l'heure viennent de la langue.
$quand = static fn (string $datetime): string => t('date.le_a', [
    'date'  => date_numerique($datetime),
    'heure' => heure_courte((int) strtotime($datetime)),
]);
?>

<?php
/*
 * Le retour, avant le titre : on arrive ici depuis « Mes agendas » et l'on y
 * retourne, c'est le seul chemin. Posé en tête plutôt qu'en marge, là où le
 * regard commence — et de la couleur des actions, puisqu'il en est une.
 */
?>
<div class="entete-page"<?= ($dansUneFenetre ?? false) ? ' data-large' : '' ?>>
  <div>
    <p style="margin:0 0 .6rem">
      <a class="bouton" href="<?= url('agenda') ?>"
         <?= ($dansUneFenetre ?? false) ? 'data-fenetre' : '' ?>><?= e(t('agenda.retour')) ?></a>
    </p>
    <h1><?= e(t('agenda.page_titre', ['nom' => $f->nom()])) ?></h1>
    <p><?= e(t('agenda.page_sous_titre', ['nom' => $f->nom()])) ?></p>
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
    <p><?= e(t('agenda.pas_activee', ['nom' => $f->nom()])) ?></p>
    <p class="champ__aide">
      <?= e(t('agenda.pas_activee_aide')) ?>
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
          <strong><?= e(t('agenda.echec')) ?></strong>
          (<?= e(t('date.le_a', ['date' => date_numerique($souci['quand']),
                                'heure' => heure_courte((int) strtotime($souci['quand']))])) ?>) :
          <?= e($souci['quoi']) ?>
        </p>
      <?php endif; ?>

      <?php if ($relie): ?>
        <section class="carte">
          <h2 style="margin-top:0"><?= e(t('agenda.compte_relie_titre')) ?></h2>
          <p>
            <?= e(t('agenda.acces')) ?>
            <strong><?= e((string) ($compte['compte'] ?? t('agenda.votre_compte'))) ?></strong><?php
            ?><?= ($compte['calendrier_nom'] ?? '') === ''
                ? '' : e(t('agenda.calendrier_nomme', ['nom' => (string) $compte['calendrier_nom']])) ?>.
          </p>
          <p class="champ__aide">
            <?php if ($derniere === null): ?>
              <?= e(t('agenda.jamais_lu')) ?>
            <?php else: ?>
              <?= e(t('agenda.derniere_lecture', ['quand' => $quand($derniere)])) ?>
              <?= e($combien === 0 ? t('agenda.aucun_suivi') : tn('agenda.suivis', $combien)) ?>.
            <?php endif; ?>
            <?php if ($envoiLe !== null): ?>
              <br><?= e(t('agenda.dernier_envoi', ['quand' => $quand($envoiLe)])) ?>
              <?= e($envoyes === 0
                  ? t('agenda.rien_dans', ['nom' => $f->nom()])
                  : tn('agenda.elements_dans', $envoyes, ['nom' => $destination['nom']])) ?>.
            <?php endif; ?>
          </p>
          <?php
          /*
           * L'autorisation obtenue ne couvre pas tout ce qu'on demande. Chez
           * Google c'est le cas ordinaire : les permissions sensibles sont
           * proposées décochées, et l'on passe outre sans le voir. Le message
           * des calendriers partagés, plus précis, passe devant quand il vaut.
           */
          ?>
          <?php if (!$partage && !$aReautoriser): ?>
            <p class="outlook-attention">
              <strong><?= e(t('agenda.portee_refusee')) ?></strong>
              <?= e(t('agenda.portee_refusee_aide')) ?>
            </p>
            <form method="post" action="<?= url('agenda/' . $f->cle() . '/connexion') ?>"
                  style="margin-bottom:.9rem">
              <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
              <button class="bouton" type="submit"><?= e(t('agenda.reconnecter')) ?></button>
            </form>
          <?php endif; ?>
          <?php if ($aReautoriser): ?>
            <p class="outlook-attention">
              <strong><?= e(t('agenda.partages_refuses', ['nom' => $f->nom()])) ?></strong>
              <?= e(tn('agenda.partages_refuses_aide', $partagesEnAttente)) ?>
            </p>
          <?php endif; ?>
          <form method="post" action="<?= url('agenda/' . $f->cle() . '/synchroniser') ?>">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
            <button class="bouton" type="submit">
              <?= e(t($derniere === null ? 'agenda.synchroniser_premiere' : 'agenda.synchroniser')) ?>
            </button>
          </form>
          <p class="champ__aide" style="margin-top:.6rem">
            <?= t('agenda.auto_aide') ?>
          </p>
          <p class="champ__aide">
            <?= t('agenda.vers_ici', ['nom' => e($f->nom())]) ?>
          </p>
          <p class="champ__aide">
            <?= t('agenda.modifs_ici', ['nom' => e($f->nom())]) ?>
          </p>
          <p class="champ__aide">
            <?= t('agenda.exception') ?>
          </p>
          <p class="champ__aide">
            <?= t('agenda.ici_vers', [
                'nom' => e($f->nom()),
                'dest' => e($destination['nom']),
                'cree' => $destination['choisi'] ? '' : e(t('agenda.cree_chez', ['nom' => $f->nom()])),
            ]) ?>
          </p>

          <?php
          /*
           * Où vont les évènements.
           *
           * « Mes Cours » reste le choix par défaut, et le plus sûr : un
           * calendrier à part, qu'une erreur de notre part ne peut pas
           * répandre ailleurs. Mais un agenda que la famille regarde ne sert à
           * rien s'il ne reçoit rien — alors on peut le désigner, en sachant
           * ce que cela veut dire.
           */
          ?>
          <?php if ($ouEcrire !== []): ?>
            <form method="post" action="<?= url('agenda/' . $f->cle() . '/destination') ?>"
                  class="champ" style="max-width:420px"
                  data-confirmation="<?= e(t('agenda.destination_sur')) ?>">
              <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
              <label for="destination-<?= e($f->cle()) ?>"><?= e(t('agenda.destination')) ?></label>
              <select id="destination-<?= e($f->cle()) ?>" name="destination">
                <option value=""<?= $destination['choisi'] ? '' : ' selected' ?>>
                  <?= e(t('agenda.destination_appli')) ?>
                </option>
                <?php foreach ($ouEcrire as $cal): ?>
                  <option value="<?= e($cal['cle']) ?>"<?= $destination['choisi']
                      && $destination['id'] !== null
                      && md5($destination['id']) === $cal['cle'] ? ' selected' : '' ?>>
                    <?= e($cal['nom']) ?><?= $cal['principal'] ? e(t('agenda.principal_suffixe')) : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <span class="champ__aide">
                <?= e(t('agenda.destination_aide')) ?>
              </span>
              <button class="bouton bouton--secondaire bouton--petit" type="submit"
                      style="margin-top:.5rem"><?= e(t('agenda.changer_destination')) ?></button>
            </form>
          <?php endif; ?>
          <div class="outlook-defaire">
            <?php if ($combien > 0): ?>
              <form method="post" action="<?= url('agenda/' . $f->cle() . '/retirer') ?>"
                    data-confirmation="<?= e(t('agenda.retirer_importes_sur', ['de' => de_agenda($f->nom())])) ?>">
                <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                <button class="bouton bouton--secondaire bouton--petit" type="submit">
                  <?= e(t('agenda.retirer_importes')) ?>
                </button>
              </form>
            <?php endif; ?>
            <?php if ($envoyes > 0): ?>
              <form method="post" action="<?= url('agenda/' . $f->cle() . '/retirer-envoi') ?>"
                    data-confirmation="<?= e(t('agenda.retirer_envoi_sur', ['de' => de_agenda($f->nom())])) ?>">
                <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                <button class="bouton bouton--secondaire bouton--petit" type="submit">
                  <?= e(t('agenda.retirer_envoi', ['de' => de_agenda($f->nom())])) ?>
                </button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= url('agenda/' . $f->cle() . '/deconnexion') ?>"
                  data-confirmation="<?= e(t('agenda.delier_sur', ['nom' => $f->nom()])) ?>">
              <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
              <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('agenda.delier')) ?></button>
            </form>
          </div>
        </section>
      <?php else: ?>
        <section class="carte">
          <h2 style="margin-top:0"><?= e(t('agenda.relier_titre')) ?></h2>
          <p>
            <?= e(t('agenda.relier_aide', ['nom' => $f->nom()])) ?>
          </p>
          <form method="post" action="<?= url('agenda/' . $f->cle() . '/connexion') ?>">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <button class="bouton" type="submit"><?= e(t('agenda.connecter', ['nom' => $f->nom()])) ?></button>
          </form>
          <p class="champ__aide" style="margin-top:.6rem">
            <?= e(t('agenda.connecter_aide', ['nom' => $f->nom()])) ?>
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
        <h2 style="margin-top:0"><?= e(t('agenda.calendriers_titre')) ?></h2>

        <?php if (!$partage): ?>
          <p class="champ__aide" style="margin-top:0">
            <?= t('agenda.partage_ancien', ['nom' => e($f->nom())]) ?>
          </p>
          <form method="post" action="<?= url('agenda/' . $f->cle() . '/connexion') ?>" style="margin-bottom:.9rem">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <button class="bouton" type="submit"><?= e(t('agenda.reautoriser')) ?></button>
          </form>
        <?php endif; ?>

        <?php if ($calendriers === []): ?>
          <p class="champ__aide" style="margin-top:0">
            <?= e(t('agenda.aucun_calendrier', ['nom' => $f->nom()])) ?>
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
                    <span><?= e((string) ($cal['nom'] ?? t('agenda.calendrier'))) ?></span>
                    <?php if ((int) $cal['principal'] === 1): ?>
                      <em class="discret"><?= e(t('agenda.principal')) ?></em>
                    <?php elseif ((int) $cal['partage'] === 1): ?>
                      <em class="discret"><?= e(t('agenda.partage_par', ['qui' => (string) ($cal['proprietaire'] ?? t('agenda.quelquun'))])) ?></em>
                    <?php endif; ?>
                  </label>
                </li>
              <?php endforeach; ?>
            </ul>
            <button class="bouton bouton--petit" type="submit"><?= e(t('agenda.enregistrer_choix')) ?></button>
          </form>
        <?php endif; ?>

        <form method="post" action="<?= url('agenda/' . $f->cle() . '/calendriers') ?>" style="margin-top:.7rem">
          <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
          <button class="bouton bouton--secondaire bouton--petit" type="submit">
            <?= e(t($calendriers === [] ? 'agenda.voir_calendriers' : 'agenda.actualiser')) ?>
          </button>
        </form>
      </section>

      <section class="carte" style="margin-top:1rem">
        <h2 style="margin-top:0"><?= e(t('agenda.ce_que_voit')) ?></h2>
        <p class="champ__aide" style="margin-top:0">
          <?= e(t('agenda.ce_que_voit_1')) ?>
        </p>
        <p class="champ__aide">
          <?= t('agenda.ce_que_voit_2') ?>
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
          <?= e(t('agenda.pour_hebergeur')) ?>
        </summary>
        <?php $section = $f->cle() === 'microsoft' ? 'outlook' : $f->cle(); ?>
        <p class="champ__aide">
          <?= t('agenda.inscription_unique', ['section' => e($section)]) ?>
        </p>

        <?php if ($f->cle() === 'microsoft'): ?>
          <ol class="outlook-marche">
            <li><?= t('agenda.ms_1') ?></li>
            <li><?= t('agenda.ms_2') ?></li>
            <li><?= t('agenda.ms_3') ?>
                <br><code class="outlook-retour"><?= e($retour) ?></code></li>
            <li><?= t('agenda.ms_4') ?></li>
            <li><?= t('agenda.ms_5') ?></li>
          </ol>
        <?php else: ?>
          <ol class="outlook-marche">
            <li><?= t('agenda.g_1') ?></li>
            <li><?= t('agenda.g_2') ?></li>
            <li><?= t('agenda.g_3') ?></li>
            <li><?= t('agenda.g_4') ?>
                <br><code class="outlook-retour"><?= e($retour) ?></code></li>
            <li><?= t('agenda.g_5') ?></li>
          </ol>
          <p class="champ__aide">
            <?= e(t('agenda.g_test')) ?>
          </p>
        <?php endif; ?>

        <p class="champ__aide">
          <?= t('agenda.en_ligne', ['section' => e($section)]) ?>
        </p>
      </details>
    </div>
  </div>
<?php endif; ?>
