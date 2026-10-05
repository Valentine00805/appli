<?php
/**
 * Gabarit principal (barre de navigation + contenu).
 * Variables attendues : $titrePage, $contenu, $utilisateur, $flashs
 */
$route = ROUTE;
$actif = static function (string $prefixe) use ($route): string {
    if ($prefixe === '') {
        return $route === '' ? ' aria-current="page"' : '';
    }
    return str_starts_with($route, $prefixe) ? ' aria-current="page"' : '';
};
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
<?php // Installable, et gardée pour le hors-ligne. ?>
<link rel="manifest" href="<?= url('manifeste.webmanifest') ?>">
<link rel="apple-touch-icon" href="<?= url('icone-192.png') ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📚</text></svg>">
</head>
<?php // Le réglage « transcription de la voix » vaut aussi pour la dictée dans l’éditeur. ?>
<body data-transcription="<?= Auth::connecte() ? (int) (Auth::utilisateur()['transcription_vocale'] ?? 1) : 1 ?>"
      data-service-worker="<?= url('service-worker.js') ?>" data-portee="<?= url('') ?>">

<a class="lien-evitement" href="#contenu"><?= e(t('nav.aller_contenu')) ?></a>

<header class="entete" id="haut">
  <div class="entete__interieur">
    <a class="marque" href="<?= url('') ?>">
      <span class="marque__icone" aria-hidden="true">📚</span>
      <span><?= e((string) Config::get('app', 'nom')) ?></span>
    </a>

    <button class="burger" type="button" aria-expanded="false" aria-controls="navigation" aria-label="<?= e(t('nav.ouvrir_menu')) ?>">
      <span></span><span></span><span></span>
    </button>

    <nav class="nav" id="navigation" aria-label="<?= e(t('nav.principale')) ?>">
      <a href="<?= url('') ?>"<?= $actif('') ?>><?= e(t('nav.accueil')) ?></a>
      <a href="<?= url('calendrier') ?>"<?= $actif('calendrier') ?>><?= e(t('nav.calendrier')) ?></a>
      <a href="<?= url('cours') ?>"<?= $actif('cours') ?>><?= e(t('nav.cours')) ?></a>
      <?php // Ce qu'on m'a partagé et que je n'ai pas encore vu. ?>
      <?php $nouveauxPartages = $utilisateur !== null ? Partages::nbNonVus((int) $utilisateur['id']) : 0; ?>
      <a href="<?= url('partages') ?>"<?= $actif('partages') ?>>
        <?= e(t('nav.partages')) ?><?php if ($nouveauxPartages > 0): ?> <span class="compteur" title="<?= e(tn('nav.nouveaux', $nouveauxPartages)) ?>"><?= $nouveauxPartages > 99 ? '99+' : $nouveauxPartages ?></span><?php endif; ?>
      </a>
      <a href="<?= url('revision') ?>"<?= $actif('revision') ?>><?= e(t('nav.revision')) ?></a>
      <a href="<?= url('cartes') ?>"<?= $actif('cartes') ?>><?= e(t('nav.cartes')) ?></a>
      <a href="<?= url('resumes') ?>"<?= $actif('resumes') ?>><?= e(t('nav.resumes')) ?></a>
      <a href="<?= url('taches') ?>"<?= $actif('taches') ?>><?= e(t('nav.taches')) ?></a>
      <a href="<?= url('tableau') ?>"<?= $actif('tableau') ?>><?= e(t('nav.tableau')) ?></a>
      <a href="<?= url('alternance') ?>"<?= $actif('alternance') ?>><?= e(t('nav.alternance')) ?></a>
      <?php // Les travaux de groupe : la pastille compte les invitations reçues. ?>
      <?php $invitationsTravaux = $utilisateur !== null ? Travaux::nbInvitations((int) $utilisateur['id']) : 0; ?>
      <a href="<?= url('travaux') ?>"<?= $actif('travaux') ?>>
        <?= e(t('nav.groupes')) ?><?php if ($invitationsTravaux > 0): ?> <span class="compteur" title="<?= e(tn('nav.invitations', $invitationsTravaux)) ?>"><?= $invitationsTravaux ?></span><?php endif; ?>
      </a>
      <a href="<?= url('budget') ?>"<?= $actif('budget') ?>><?= e(t('nav.budget')) ?></a>
      <a href="<?= url('organisation/matieres') ?>"<?= $actif('organisation') ?>><?= e(t('nav.organisation')) ?></a>
      <?php // Les amis : la pastille compte les messages non lus et les demandes reçues. ?>
      <?php $attenteAmis = $utilisateur !== null ? Amis::enAttente((int) $utilisateur['id']) : 0; ?>
      <a href="<?= url('amis') ?>"<?= $actif('amis') ?> class="nav__amis">
        <?= e(t('nav.amis')) ?><?php if ($attenteAmis > 0): ?> <span class="compteur" title="<?= e(t('nav.en_attente', ['n' => $attenteAmis])) ?>"><?= $attenteAmis > 99 ? '99+' : $attenteAmis ?></span><?php endif; ?>
      </a>

      <form class="recherche-rapide" action="<?= url('recherche') ?>" method="get" role="search">
        <input type="search" name="q" placeholder="<?= e(t('nav.rechercher')) ?>" aria-label="<?= e(t('nav.rechercher_aide')) ?>"
               value="<?= e((string) ($_GET['q'] ?? '')) ?>">
      </form>

      <div class="nav__compte">
        <?php if ($utilisateur !== null): ?>
          <?php
          /*
           * Le menu en grille, comme celui des applications de Google : toutes les sections en tuiles, les favoris en
           * tête (au choix de chacun : le crayon les modifie). Un « details » : il s'ouvre sans script ; le script ajoute
           * la fermeture au clic à côté, et l'édition des favoris.
           */
          $favorisMenu = Menu::favoris($utilisateur['menu_favoris'] ?? null);
          $compteursMenu = ['partages' => $nouveauxPartages, 'groupes' => $invitationsTravaux, 'amis' => $attenteAmis];
          $liensApps = LienApp::duUser((int) $utilisateur['id']);
          $tuile = static function (string $cle) use ($actif, $compteursMenu): string {
              $s = Menu::SECTIONS[$cle];
              $n = (int) ($compteursMenu[$cle] ?? 0);

              return '<a class="apps__tuile" href="' . e(url($s['route'])) . '" data-cle="' . e($cle) . '" data-rang="'
                  . (int) array_search($cle, array_keys(Menu::SECTIONS), true) . '"' . $actif($s['prefixe']) . '>'
                  . '<span class="apps__icone" aria-hidden="true">' . $s['icone'] . '</span>'
                  . '<span class="apps__nom">' . e(t($s['nom'])) . '</span>'
                  . ($n > 0 ? '<span class="compteur apps__compteur">' . ($n > 99 ? '99+' : $n) . '</span>' : '')
                  . '<span class="apps__etoile" aria-hidden="true"></span></a>';
          };
          ?>
          <details class="apps" data-apps data-url-favoris="<?= e(url('compte/menu-favoris')) ?>" data-url-liens="<?= e(url('compte/liens-apps')) ?>"
                   data-liens-max="<?= LienApp::MAX ?>" data-lien-dialogue="apps-lien-dialogue" data-jeton="<?= e(Session::jetonCsrf()) ?>">
            <summary class="apps__bouton" title="<?= e(t('nav.apps')) ?>" aria-label="<?= e(t('nav.apps')) ?>">
              <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">
                <?php foreach ([4, 12, 20] as $cy): foreach ([4, 12, 20] as $cx): ?>
                  <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="2.1" fill="currentColor"/>
                <?php endforeach; endforeach; ?>
              </svg>
            </summary>
            <div class="apps__panneau" role="region" aria-label="<?= e(t('nav.apps')) ?>">
              <section class="apps__bloc apps__bloc--favoris">
                <div class="apps__entete">
                  <h2 class="apps__titre"><?= e(t('apps.favoris')) ?></h2>
                  <button type="button" class="apps__crayon" data-apps-edition aria-pressed="false" hidden
                          title="<?= e(t('apps.modifier')) ?>" aria-label="<?= e(t('apps.modifier')) ?>">✏️</button>
                </div>
                <p class="apps__aide" data-apps-aide hidden><?= e(t('apps.aide_edition')) ?></p>
                <div class="apps__grille" data-apps-favoris>
                  <?php foreach ($favorisMenu as $cle): ?><?= $tuile($cle) ?><?php endforeach; ?>
                </div>
                <p class="apps__vide" data-apps-vide<?= $favorisMenu === [] ? '' : ' hidden' ?>><?= e(t('apps.aucun_favori')) ?></p>
              </section>
              <section class="apps__bloc">
                <h2 class="apps__titre"><?= e(t('apps.toutes')) ?></h2>
                <div class="apps__grille" data-apps-autres>
                  <?php foreach (array_keys(Menu::SECTIONS) as $cle): ?>
                    <?php if (!in_array($cle, $favorisMenu, true)): ?><?= $tuile($cle) ?><?php endif; ?>
                  <?php endforeach; ?>
                </div>
              </section>
              <?php
              /*
               * Mes applications : des liens vers d'autres sites (YouTube, NotebookLM…), ajoutés par chacun. Ils s'ouvrent
               * dans un nouvel onglet. Les tuiles sont de vrais liens (sans script, ils marchent) ; ajouter, modifier et
               * supprimer demandent le script. L'icône est l'image du logo dont on a donné l'adresse, ou celle du site (le
               * navigateur la demande à un service de favicons), avec l'emoji en repli ; un emoji choisi remplace celle du site.
               */
              ?>
              <section class="apps__bloc" data-apps-liens>
                <div class="apps__entete">
                  <h2 class="apps__titre"><?= e(t('apps.liens')) ?></h2>
                  <button type="button" class="apps__crayon" data-liens-edition aria-pressed="false" hidden
                          title="<?= e(t('apps.liens_modifier')) ?>" aria-label="<?= e(t('apps.liens_modifier')) ?>">✏️</button>
                </div>
                <p class="apps__aide" data-liens-aide hidden><?= e(t('apps.liens_aide')) ?></p>
                <div class="apps__grille" data-liens-grille>
                  <?php foreach ($liensApps as $lien): ?>
                    <a class="apps__tuile apps__tuile--lien" href="<?= e($lien['url']) ?>" target="_blank" rel="noopener noreferrer"
                       data-lien-id="<?= (int) $lien['id'] ?>" data-nom="<?= e($lien['nom']) ?>" data-icone-choisie="<?= e($lien['icone_choisie']) ?>" data-logo="<?= e($lien['logo']) ?>">
                      <span class="apps__icone" aria-hidden="true"><?= e($lien['icone']) ?><?php if ($lien['image'] !== ''): ?><img class="apps__favicon" src="<?= e($lien['image']) ?>" alt="" width="32" height="32" loading="lazy" decoding="async" referrerpolicy="no-referrer"><?php endif; ?></span>
                      <span class="apps__nom"><?= e($lien['nom']) ?></span>
                      <span class="apps__etoile apps__etoile--lien" aria-hidden="true">✎</span>
                    </a>
                  <?php endforeach; ?>
                  <button type="button" class="apps__tuile apps__tuile--ajout" data-lien-ajout hidden>
                    <span class="apps__icone" aria-hidden="true">＋</span>
                    <span class="apps__nom"><?= e(t('apps.lien_ajouter')) ?></span>
                  </button>
                </div>
                <p class="apps__vide" data-liens-vide<?= $liensApps === [] ? '' : ' hidden' ?>><?= e(t('apps.liens_vide')) ?></p>
              </section>
            </div>
          </details>
          <a class="nav__utilisateur" href="<?= url('compte') ?>" title="<?= e(t('nav.compte')) ?>">
            <?= Amis::avatar((int) $utilisateur['id'], Auth::nomAffiche($utilisateur)) ?>
            <span class="nav__utilisateur-nom"><?= e(Auth::nomAffiche($utilisateur)) ?></span>
          </a>
          <form action="<?= url('deconnexion') ?>" method="post">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <button class="bouton bouton--discret" type="submit"><?= e(t('nav.deconnexion')) ?></button>
          </form>
        <?php else: ?>
          <a class="bouton bouton--secondaire" href="<?= url('connexion') ?>"><?= e(t('nav.connexion')) ?></a>
        <?php endif; ?>
      </div>
    </nav>
  </div>
</header>

<?php
/*
 * La fenêtre pour ajouter ou modifier une application du menu en grille. Hors du panneau (et de la barre, repliée sur
 * mobile) : elle reste là même quand le panneau se referme. Le script l'ouvre ; sans lui, elle reste cachée.
 */
if ($utilisateur !== null): ?>
<dialog class="fenetre apps-fenetre" id="apps-lien-dialogue" data-lien-dialogue aria-labelledby="apps-lien-titre">
  <button class="fenetre__fermer" type="button" data-lien-annuler aria-label="<?= e(t('apps.lien_annuler')) ?>">✕</button>
  <div class="fenetre__corps">
    <form class="apps__form" data-lien-form autocomplete="off">
      <h2 class="apps__sous-titre" id="apps-lien-titre" data-lien-form-titre><?= e(t('apps.lien_nouveau')) ?></h2>
      <label>
        <span><?= e(t('apps.lien_nom')) ?></span>
        <input type="text" name="nom" maxlength="<?= LienApp::NOM_MAX ?>" required placeholder="YouTube">
      </label>
      <label>
        <span><?= e(t('apps.lien_adresse')) ?></span>
        <input type="text" name="url" inputmode="url" maxlength="<?= LienApp::URL_MAX ?>" required placeholder="youtube.com">
      </label>
      <label>
        <span><?= e(t('apps.lien_logo')) ?></span>
        <input type="text" name="logo" inputmode="url" maxlength="<?= LienApp::URL_MAX ?>" placeholder="https://…/logo.png">
        <small class="apps__indice"><?= e(t('apps.lien_logo_aide')) ?></small>
      </label>
      <label>
        <span><?= e(t('apps.lien_icone')) ?></span>
        <input type="text" name="icone" maxlength="<?= LienApp::ICONE_MAX ?>" placeholder="▶️">
      </label>
      <p class="apps__erreur" role="alert" data-lien-erreur hidden></p>
      <div class="apps__form-actions">
        <button type="submit" class="bouton bouton--petit"><?= e(t('apps.lien_enregistrer')) ?></button>
        <button type="button" class="bouton bouton--discret bouton--petit" data-lien-annuler><?= e(t('apps.lien_annuler')) ?></button>
        <button type="button" class="bouton bouton--discret bouton--petit apps__supprimer" data-lien-supprimer hidden><?= e(t('apps.lien_supprimer')) ?></button>
      </div>
    </form>
  </div>
</dialog>
<?php endif; ?>

<?php
/*
 * Tant qu'un onglet est ouvert, la page déclenche elle-même l'envoi des
 * rappels chaque minute : ils partent alors même sans tâche planifiée. Seulement
 * pour qui a abonné un appareil — les autres n'ont rien à recevoir.
 */
if ($utilisateur !== null
    && (int) Database::valeur('SELECT COUNT(*) FROM abonnements_push WHERE user_id = ?', [(int) $utilisateur['id']]) > 0): ?>
  <span hidden data-battement-rappels="<?= e(url('notifications/battement')) ?>" data-jeton="<?= e(Session::jetonCsrf()) ?>"></span>
<?php endif; ?>
<main id="contenu" class="conteneur">
  <?php if ($flashs !== []): ?>
    <div class="flashs">
      <?php foreach ($flashs as $flash): ?>
        <div class="flash flash--<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?= $contenu ?>
</main>

<?php
/*
 * Remonter en haut de la page.
 *
 * Un lien d'ancre, et non un bouton : sans JavaScript il remonte quand même,
 * d'un saut plutôt qu'en glissant. Le cercle reste vide dans ce cas — il ne
 * promet rien qu'il ne tienne.
 *
 * Le script prend le relais : il remplit le cercle à mesure qu'on descend et
 * efface la flèche tant qu'on est en haut, où elle n'aurait rien à faire.
 */
?>
<a class="haut-de-page" href="#haut" title="<?= e(t('nav.haut_de_page')) ?>">
  <svg class="haut-de-page__anneau" viewBox="0 0 44 44" aria-hidden="true" focusable="false">
    <circle class="haut-de-page__piste" cx="22" cy="22" r="20"></circle>
    <circle class="haut-de-page__part" cx="22" cy="22" r="20"></circle>
  </svg>
  <span class="haut-de-page__fleche" aria-hidden="true">↑</span>
  <span class="sr-only"><?= e(t('nav.haut_de_page')) ?></span>
</a>

<?php
/*
 * La relecture de l'agenda Outlook, sans qu'on ait à la demander.
 *
 * Le repère n'apparaît que lorsqu'il y a lieu de relire : compte relié,
 * calendriers choisis, dernière lecture assez ancienne. C'est le serveur qui
 * en juge, et il le rejuge à la réception — la page ne fait que réveiller.
 *
 * Sans JavaScript, il ne se passe rien de plus qu'avant : le bouton de la
 * page Outlook reste la voie sûre.
 */
?>
<?php if (Auth::connecte() && Agenda::aQuelqueChoseAFaire(Auth::id())): ?>
  <div hidden data-outlook-relire="<?= e(url('agenda/synchroniser')) ?>"
       data-csrf="<?= e(Session::jetonCsrf()) ?>"></div>
<?php endif; ?>

<?php // Le script affiche lui aussi des phrases : voici les siennes, traduites. ?>
<script>window.MOTS = <?= json_encode(Langue::pourLeScript(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= asset('assets/js/app.js') ?>" defer></script>
<script src="<?= asset('assets/js/mot-de-passe.js') ?>" defer></script>
<script src="<?= asset('assets/js/carte-mentale.js') ?>" defer></script>
<script src="<?= asset('assets/js/zoom-image.js') ?>" defer></script>
<script src="<?= asset('assets/js/diaporama.js') ?>" defer></script>
<script src="<?= asset('assets/js/menu-apps.js') ?>" defer></script>
</body>
</html>
