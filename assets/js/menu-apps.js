/*
 * Le menu en grille de la barre (« details.apps ») : il s'ouvre sans script ; ce fichier le ferme quand on clique à côté
 * ou sur Échap, et fait marcher le crayon — modifier ses favoris.
 *
 * En édition, un clic sur une tuile ne suit plus son lien : il la fait passer des « Toutes les sections » aux favoris, ou
 * l'inverse. Chaque changement est envoyé au serveur (la liste des favoris, dans l'ordre), qui ne garde que des clés
 * qu'il connaît. Les tuiles reviennent à leur place dans le catalogue grâce à leur rang (data-rang).
 */
(function () {
  'use strict';

  var MOTS = window.MOTS || {};
  var mot = function (cle) { return Object.prototype.hasOwnProperty.call(MOTS, cle) ? MOTS[cle] : cle; };

  var initialiser = function (racine) {
    if (racine.hasAttribute('data-apps-pret')) { return; }
    racine.setAttribute('data-apps-pret', '1');

    var crayon = racine.querySelector('[data-apps-edition]');
    var aide = racine.querySelector('[data-apps-aide]');
    var favoris = racine.querySelector('[data-apps-favoris]');
    var autres = racine.querySelector('[data-apps-autres]');
    var vide = racine.querySelector('[data-apps-vide]');
    var sommaire = racine.querySelector('summary');
    var URL_FAVORIS = racine.getAttribute('data-url-favoris');
    var JETON = racine.getAttribute('data-jeton');
    var enEdition = false;
    var texteAide = aide ? aide.textContent : '';

    // --- Fermer : un clic à côté, ou Échap ------------------------------------------------------------------------
    document.addEventListener('click', function (ev) {
      if (racine.open && !racine.contains(ev.target)) { racine.open = false; }
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && racine.open) {
        racine.open = false;
        if (sommaire) { sommaire.focus(); }
      }
    });

    // --- Les favoris ------------------------------------------------------------------------------------------------
    var majVide = function () { if (vide) { vide.hidden = favoris.children.length > 0; } };

    var sauver = function () {
      var corps = new FormData();
      corps.append('_csrf', JETON);
      Array.prototype.forEach.call(favoris.querySelectorAll('[data-cle]'), function (t) { corps.append('favoris[]', t.getAttribute('data-cle')); });
      fetch(URL_FAVORIS, { method: 'POST', body: corps, credentials: 'same-origin', keepalive: true })
        .then(function (r) {
          if (r.status !== 204) { throw new Error('http ' + r.status); }
          if (aide) { aide.textContent = texteAide; }
        })
        .catch(function () { if (aide) { aide.hidden = false; aide.textContent = mot('apps.echec'); } });
    };

    /** Une tuile passe des favoris aux autres (à sa place dans le catalogue), ou des autres aux favoris (à la fin). */
    var basculer = function (tuile) {
      if (tuile.parentNode === favoris) {
        var rang = parseInt(tuile.getAttribute('data-rang'), 10);
        var avant = null;
        Array.prototype.forEach.call(autres.children, function (t) {
          if (avant === null && parseInt(t.getAttribute('data-rang'), 10) > rang) { avant = t; }
        });
        autres.insertBefore(tuile, avant);
      } else {
        favoris.appendChild(tuile);
      }
      majVide();
      sauver();
    };

    if (!crayon) { return; }
    crayon.hidden = false;
    crayon.addEventListener('click', function (ev) {
      ev.stopPropagation();
      enEdition = !enEdition;
      racine.classList.toggle('apps--edition', enEdition);
      crayon.setAttribute('aria-pressed', enEdition ? 'true' : 'false');
      crayon.textContent = enEdition ? '✓' : '✏️';
      if (aide) { aide.hidden = !enEdition; aide.textContent = texteAide; }
    });
    racine.addEventListener('click', function (ev) {
      if (!enEdition) { return; }
      var tuile = ev.target.closest ? ev.target.closest('[data-cle]') : null;
      if (!tuile || !racine.contains(tuile)) { return; }
      ev.preventDefault();
      basculer(tuile);
    });
  };

  var tout = function () { Array.prototype.forEach.call(document.querySelectorAll('[data-apps]'), initialiser); };
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', tout); } else { tout(); }
})();
