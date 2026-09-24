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
<html lang="fr" data-theme="<?= e(Auth::theme()) ?>">
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

<a class="lien-evitement" href="#contenu">Aller au contenu</a>

<header class="entete" id="haut">
  <div class="entete__interieur">
    <a class="marque" href="<?= url('') ?>">
      <span class="marque__icone" aria-hidden="true">📚</span>
      <span><?= e((string) Config::get('app', 'nom')) ?></span>
    </a>

    <button class="burger" type="button" aria-expanded="false" aria-controls="navigation" aria-label="Ouvrir le menu">
      <span></span><span></span><span></span>
    </button>

    <nav class="nav" id="navigation" aria-label="Navigation principale">
      <a href="<?= url('') ?>"<?= $actif('') ?>>Accueil</a>
      <a href="<?= url('calendrier') ?>"<?= $actif('calendrier') ?>>Calendrier</a>
      <a href="<?= url('cours') ?>"<?= $actif('cours') ?>>Mes cours</a>
      <?php // Ce qu'on m'a partagé et que je n'ai pas encore vu. ?>
      <?php $nouveauxPartages = $utilisateur !== null ? Partages::nbNonVus((int) $utilisateur['id']) : 0; ?>
      <a href="<?= url('partages') ?>"<?= $actif('partages') ?>>
        Partagés<?php if ($nouveauxPartages > 0): ?> <span class="compteur" title="<?= $nouveauxPartages ?> nouveau<?= $nouveauxPartages > 1 ? 'x' : '' ?>"><?= $nouveauxPartages > 99 ? '99+' : $nouveauxPartages ?></span><?php endif; ?>
      </a>
      <a href="<?= url('revision') ?>"<?= $actif('revision') ?>>Révision</a>
      <a href="<?= url('cartes') ?>"<?= $actif('cartes') ?>>Cartes</a>
      <a href="<?= url('taches') ?>"<?= $actif('taches') ?>>Tâches</a>
      <a href="<?= url('tableau') ?>"<?= $actif('tableau') ?>>Tableau</a>
      <a href="<?= url('alternance') ?>"<?= $actif('alternance') ?>>Alternance</a>
      <?php // Les travaux de groupe : la pastille compte les invitations reçues. ?>
      <?php $invitationsTravaux = $utilisateur !== null ? Travaux::nbInvitations((int) $utilisateur['id']) : 0; ?>
      <a href="<?= url('travaux') ?>"<?= $actif('travaux') ?>>
        Groupes<?php if ($invitationsTravaux > 0): ?> <span class="compteur" title="<?= $invitationsTravaux ?> invitation<?= $invitationsTravaux > 1 ? 's' : '' ?>"><?= $invitationsTravaux ?></span><?php endif; ?>
      </a>
      <a href="<?= url('budget') ?>"<?= $actif('budget') ?>>Budget</a>
      <a href="<?= url('organisation/matieres') ?>"<?= $actif('organisation') ?>>Organisation</a>
      <?php // Les amis : la pastille compte les messages non lus et les demandes reçues. ?>
      <?php $attenteAmis = $utilisateur !== null ? Amis::enAttente((int) $utilisateur['id']) : 0; ?>
      <a href="<?= url('amis') ?>"<?= $actif('amis') ?> class="nav__amis">
        Amis<?php if ($attenteAmis > 0): ?> <span class="compteur" title="<?= $attenteAmis ?> en attente"><?= $attenteAmis > 99 ? '99+' : $attenteAmis ?></span><?php endif; ?>
      </a>

      <form class="recherche-rapide" action="<?= url('recherche') ?>" method="get" role="search">
        <input type="search" name="q" placeholder="Rechercher…" aria-label="Rechercher partout : cours, calendrier, notes, journal, tâches"
               value="<?= e((string) ($_GET['q'] ?? '')) ?>">
      </form>

      <div class="nav__compte">
        <?php if ($utilisateur !== null): ?>
          <a class="nav__utilisateur" href="<?= url('compte') ?>" title="Mon compte">
            <?= Amis::avatar((int) $utilisateur['id'], Auth::nomAffiche($utilisateur)) ?>
            <span class="nav__utilisateur-nom"><?= e(Auth::nomAffiche($utilisateur)) ?></span>
          </a>
          <form action="<?= url('deconnexion') ?>" method="post">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <button class="bouton bouton--discret" type="submit">Déconnexion</button>
          </form>
        <?php else: ?>
          <a class="bouton bouton--secondaire" href="<?= url('connexion') ?>">Connexion</a>
        <?php endif; ?>
      </div>
    </nav>
  </div>
</header>

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
<a class="haut-de-page" href="#haut" title="Remonter en haut de la page">
  <svg class="haut-de-page__anneau" viewBox="0 0 44 44" aria-hidden="true" focusable="false">
    <circle class="haut-de-page__piste" cx="22" cy="22" r="20"></circle>
    <circle class="haut-de-page__part" cx="22" cy="22" r="20"></circle>
  </svg>
  <span class="haut-de-page__fleche" aria-hidden="true">↑</span>
  <span class="sr-only">Remonter en haut de la page</span>
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

<script src="<?= asset('assets/js/app.js') ?>" defer></script>
<script src="<?= asset('assets/js/mot-de-passe.js') ?>" defer></script>
</body>
</html>
