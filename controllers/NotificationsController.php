<?php
declare(strict_types=1);

/**
 * Les notifications de rappel : abonner un appareil, l'essayer, et envoyer.
 *
 * Un appareil s'abonne depuis la page « Notifications » : son navigateur
 * demande la permission, s'inscrit auprès de son service de notifications et
 * nous confie l'adresse et les clés reçues. L'envoi, lui, se déclenche par
 * l'adresse « notifications/envoyer », munie de sa clé, qu'une tâche planifiée
 * appelle chaque minute — et, tant qu'un onglet de l'application est ouvert,
 * par la page elle-même.
 */
final class NotificationsController
{
    public function index(): void
    {
        Auth::exiger();
        $userId = Auth::id();

        $donnees = [
            'appareils' => Database::all(
                'SELECT id, appareil, created_at, dernier_envoi FROM abonnements_push WHERE user_id = ? ORDER BY created_at',
                [$userId]
            ),
            'clePublique' => WebPush::clesVapid()['publique'],
            'adresseEnvoi' => self::adresseAbsolue(url('notifications/envoyer', ['cle' => self::cleEnvoi()])),
        ];

        // Depuis « Mon compte », la page s'ouvre dans une fenêtre.
        if (Vue::enFenetre()) {
            Vue::fragment('notifications/index', $donnees);

            return;
        }

        Vue::afficher('notifications/index', $donnees, 'Notifications');
    }

    /**
     * Le service worker : il reçoit les messages même quand aucun onglet n'est
     * ouvert, affiche la notification, et ouvre la bonne page au clic. Servi
     * depuis la racine de l'application, pour la couvrir toute entière.
     *
     * Il garde aussi l'application pour le hors-ligne : la feuille de style et
     * le script une fois pour toutes, chaque page au fur et à mesure qu'on
     * l'ouvre. Sans réseau, on relit donc ce qu'on a déjà vu, et une page
     * jamais ouverte répond par « Pas de réseau » plutôt que par l'écran du
     * navigateur.
     *
     * La version du cache suit la date des fichiers : une mise à jour de
     * l'application jette d'elle-même l'ancien cache, sans quoi on servirait
     * un script d'hier avec une page d'aujourd'hui.
     */
    public function serviceWorker(): void
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Service-Worker-Allowed: ' . BASE_URL . '/');

        $version = self::versionDesFichiers();
        $coquille = json_encode([
            asset('assets/css/app.css'),
            asset('assets/js/app.js'),
            url('hors-ligne'),
            url('manifeste.webmanifest'),
        ], JSON_UNESCAPED_SLASHES);
        $base = json_encode(url(''), JSON_UNESCAPED_SLASHES);
        $horsLigne = json_encode(url('hors-ligne'), JSON_UNESCAPED_SLASHES);

        echo <<<JS
/* Mes Cours — les notifications de rappel, et l'application hors connexion. */
var VERSION = '$version';
var CACHE = 'mescours-' + VERSION;
var BASE = $base;
var HORS_LIGNE = $horsLigne;
var COQUILLE = $coquille;
JS;
        echo <<<'JS'

/*
 * Ce qui ne se garde jamais : ce qui pèse (les fichiers déposés, les PDF, les
 * exports), ce qui ne vaut que sur l'instant (l'envoi des rappels, les
 * sondages du chat) et ce qui appartient à quelqu'un d'autre (les liens
 * publics). Une réponse partielle ou un téléchargement n'ont rien à faire
 * dans un cache de pages.
 */
var SANS_CACHE = /(\/(fichiers|storage|p|g)\/|\/documents\/\d+|\/(pdf|ics|export|apercu|contenu|telecharger|battement|envoyer|fil|messages)(\/|$|\?))/;

self.addEventListener('install', function (evenement) {
  evenement.waitUntil(
    caches.open(CACHE)
      .then(function (cache) { return cache.addAll(COQUILLE); })
      .catch(function () { /* hors ligne dès l'installation : on fera sans */ })
      .then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (evenement) {
  evenement.waitUntil(
    caches.keys().then(function (noms) {
      return Promise.all(noms.map(function (nom) {
        // Les caches d'une version précédente n'ont plus lieu d'être.
        return nom.indexOf('mescours-') === 0 && nom !== CACHE ? caches.delete(nom) : null;
      }));
    }).then(function () { return self.clients.claim(); })
  );
});

/** Une page de plus dans le cache, et les plus vieilles s'effacent. */
function garder(requete, reponse) {
  if (!reponse || !reponse.ok || reponse.type !== 'basic') { return reponse; }
  if ((reponse.headers.get('Content-Disposition') || '').indexOf('attachment') === 0) { return reponse; }
  var copie = reponse.clone();
  caches.open(CACHE).then(function (cache) {
    return cache.put(requete, copie).then(function () { return cache.keys(); }).then(function (gardees) {
      var trop = gardees.length - 120;
      // Le cache ne grossit pas sans fin : au-delà, les plus anciennes partent.
      for (var i = 0; i < trop; i++) {
        if (COQUILLE.indexOf(new URL(gardees[i].url).pathname) === -1) { cache.delete(gardees[i]); }
      }
    });
  }).catch(function () { /* cache plein ou refusé : on sert quand même */ });
  return reponse;
}

/** Le réseau d'abord ; à défaut, ce qu'on avait gardé. */
function reseauDAbord(requete, secours) {
  return fetch(requete)
    .then(function (reponse) { return garder(requete, reponse); })
    .catch(function () {
      return caches.match(requete).then(function (gardee) {
        if (gardee) { return gardee; }
        return secours ? caches.match(HORS_LIGNE) : Response.error();
      });
    });
}

/** Le cache d'abord, pour ce qui ne change qu'avec la version. */
function cacheDAbord(requete) {
  return caches.match(requete).then(function (gardee) {
    return gardee || fetch(requete).then(function (reponse) { return garder(requete, reponse); });
  });
}

self.addEventListener('fetch', function (evenement) {
  var requete = evenement.request;
  if (requete.method !== 'GET') { return; }

  var url = new URL(requete.url);
  if (url.origin !== self.location.origin || url.pathname.indexOf(BASE) !== 0) { return; }
  // Une lecture partielle (un son, une vidéo) ne se garde pas par morceaux.
  if (requete.headers.get('Range') || SANS_CACHE.test(url.pathname + url.search)) { return; }

  if (url.pathname.indexOf(BASE + 'assets/') === 0 || url.pathname.indexOf(BASE + 'icone-') === 0) {
    evenement.respondWith(cacheDAbord(requete));
    return;
  }

  var page = requete.mode === 'navigate'
    || (requete.headers.get('Accept') || '').indexOf('text/html') !== -1;
  evenement.respondWith(reseauDAbord(requete, page));
});

/*
 * Ce que la page demande au service worker : garder d'avance les pages qu'on
 * voudra relire sans réseau, ou dire ce qu'il a déjà.
 */
self.addEventListener('message', function (evenement) {
  var message = evenement.data || {};
  if (message.quoi === 'garder' && Array.isArray(message.pages)) {
    evenement.waitUntil(caches.open(CACHE).then(function (cache) {
      return Promise.all(message.pages.map(function (page) {
        return fetch(page, { credentials: 'same-origin' })
          .then(function (reponse) { return reponse.ok ? cache.put(page, reponse) : null; })
          .catch(function () { return null; });
      }));
    }).then(function () {
      if (evenement.source) { evenement.source.postMessage({ quoi: 'gardees' }); }
    }));
  }
  if (message.quoi === 'combien') {
    evenement.waitUntil(caches.open(CACHE).then(function (cache) { return cache.keys(); })
      .then(function (gardees) {
        if (evenement.source) { evenement.source.postMessage({ quoi: 'combien', pages: gardees.length }); }
      }));
  }
  if (message.quoi === 'oublier') {
    evenement.waitUntil(caches.delete(CACHE).then(function () {
      if (evenement.source) { evenement.source.postMessage({ quoi: 'oubliees' }); }
    }));
  }
});

self.addEventListener('push', function (evenement) {
  var message = {};
  try { message = evenement.data ? evenement.data.json() : {}; }
  catch (e) { message = { title: 'Mes Cours', body: evenement.data ? evenement.data.text() : '' }; }

  evenement.waitUntil(self.registration.showNotification(message.title || 'Mes Cours', {
    body: message.body || '',
    tag: message.tag || undefined,
    // Une notification du même fil remplace la précédente, et prévient de nouveau.
    renotify: !!message.tag,
    lang: 'fr',
    data: { url: message.url || './' }
  }));
});

self.addEventListener('notificationclick', function (evenement) {
  evenement.notification.close();
  var cible = new URL((evenement.notification.data && evenement.notification.data.url) || './', self.registration.scope).href;
  evenement.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (fenetres) {
    for (var i = 0; i < fenetres.length; i++) {
      if (fenetres[i].url.indexOf(self.registration.scope) === 0 && 'focus' in fenetres[i]) {
        return fenetres[i].navigate(cible).then(function (f) { return (f || fenetres[i]).focus(); });
      }
    }
    return self.clients.openWindow(cible);
  }));
});
JS;
        exit;
    }

    /** La version du cache : la date du script et de la feuille de style. */
    private static function versionDesFichiers(): string
    {
        $dates = [];
        foreach (['assets/js/app.js', 'assets/css/app.css'] as $fichier) {
            $chemin = dirname(__DIR__) . '/' . $fichier;
            $dates[] = is_file($chemin) ? (string) filemtime($chemin) : '0';
        }

        return substr(sha1(implode('-', $dates)), 0, 12);
    }

    /** Un appareil s'abonne, ou renouvelle son abonnement. */
    public function abonner(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $pointFinal = trim((string) ($_POST['point_final'] ?? ''));
        $p256dh = trim((string) ($_POST['cle_p256dh'] ?? ''));
        $auth = trim((string) ($_POST['cle_auth'] ?? ''));

        $publique = WebPush::base64urlDecoder($p256dh);
        $secret = WebPush::base64urlDecoder($auth);
        if (!WebPush::serviceConnu($pointFinal)
            || strlen($publique) !== 65 || $publique[0] !== "\x04" || strlen($secret) !== 16) {
            http_response_code(422);
            repondre_json(['fait' => false, 'message' => 'Cet abonnement est illisible.']);
        }

        // Une même adresse ne vaut qu'un abonnement : l'appareil change de compte, il suit.
        Database::run(
            'INSERT INTO abonnements_push (user_id, point_final, empreinte, cle_p256dh, cle_auth, appareil)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), cle_p256dh = VALUES(cle_p256dh),
                                     cle_auth = VALUES(cle_auth), appareil = VALUES(appareil)',
            [$userId, $pointFinal, hash('sha256', $pointFinal), $p256dh, $auth,
             self::nomAppareil((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))]
        );

        repondre_json(['fait' => true]);
    }

    /** Retirer un appareil : celui-ci (par son adresse) ou un autre de la liste (par son numéro). */
    public function desabonner(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        $userId = Auth::id();

        $pointFinal = trim((string) ($_POST['point_final'] ?? ''));
        $id = entier_ou_null($_POST['id'] ?? null);
        if ($pointFinal !== '') {
            Database::run('DELETE FROM abonnements_push WHERE empreinte = ? AND user_id = ?', [hash('sha256', $pointFinal), $userId]);
        } elseif ($id !== null) {
            Database::run('DELETE FROM abonnements_push WHERE id = ? AND user_id = ?', [$id, $userId]);
        }

        if (veut_du_json()) {
            repondre_json(['fait' => true]);
        }
        Session::flash('succes', 'Appareil retiré : il ne recevra plus de rappels.');
        redirect('notifications');
    }

    /** Une notification d'essai, à tous les appareils du compte. */
    public function essai(): void
    {
        Auth::exiger();
        Session::verifierCsrf();

        $bilan = Rappels::envoyerAuCompte(Auth::id(), [
            'title' => '🔔 Les rappels fonctionnent',
            'body' => 'Mes Cours pourra vous prévenir avant vos évènements et le matin de vos échéances.',
            'url' => url('notifications'),
            'tag' => 'essai',
        ]);

        $message = match (true) {
            $bilan['envoyes'] > 0 => 'Notification d’essai envoyée à ' . $bilan['envoyes'] . ' appareil'
                . ($bilan['envoyes'] > 1 ? 's' : '') . '.',
            $bilan['retires'] > 0 => 'Cet appareil n’était plus abonné : réactivez les notifications.',
            default => 'La notification n’a pas pu partir. Vérifiez la connexion à Internet, puis réessayez.',
        };

        if (veut_du_json()) {
            repondre_json(['fait' => $bilan['envoyes'] > 0, 'message' => $message] + $bilan);
        }
        Session::flash($bilan['envoyes'] > 0 ? 'succes' : 'erreur', $message);
        redirect('notifications');
    }

    /**
     * L'envoi des rappels dus, pour tous les comptes : l'adresse qu'appelle la
     * tâche planifiée. Pas de session ici — la clé en tient lieu.
     */
    public function envoyer(): void
    {
        $donnee = (string) ($_GET['cle'] ?? $_POST['cle'] ?? '');
        if ($donnee === '' || !hash_equals(self::cleEnvoi(), $donnee)) {
            http_response_code(403);
            repondre_json(['fait' => false]);
        }

        repondre_json(['fait' => true] + Rappels::envoyerCeQuiEstDu());
    }

    /** Le même envoi, pour la seule personne connectée : la page ouverte y pourvoit chaque minute. */
    public function battement(): void
    {
        Auth::exiger();
        Session::verifierCsrf();
        // La session n'a rien à attendre de l'envoi : on la libère pour les autres onglets.
        session_write_close();

        repondre_json(['fait' => true] + Rappels::envoyerCeQuiEstDu(Auth::id()));
    }

    /* --- Interne ------------------------------------------------------------ */

    /** La clé de l'adresse d'envoi, tirée une fois pour toutes. */
    private static function cleEnvoi(): string
    {
        $cle = (string) Database::valeur("SELECT valeur FROM reglages_application WHERE cle = 'cle_envoi_rappels'");
        if ($cle === '') {
            Database::run("INSERT IGNORE INTO reglages_application (cle, valeur) VALUES ('cle_envoi_rappels', ?)",
                [bin2hex(random_bytes(24))]);
            $cle = (string) Database::valeur("SELECT valeur FROM reglages_application WHERE cle = 'cle_envoi_rappels'");
        }

        return $cle;
    }

    private static function adresseAbsolue(string $chemin): string
    {
        $https = (string) ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off';

        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $chemin;
    }

    /** « Chrome · Windows », pour reconnaître un appareil dans la liste. */
    private static function nomAppareil(string $agent): string
    {
        $navigateur = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Navigateur',
        };
        $systeme = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => '',
        };

        return $navigateur . ($systeme === '' ? '' : ' · ' . $systeme);
    }
}
