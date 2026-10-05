/*
 * Le menu en grille de la barre (« details.apps ») : il s'ouvre sans script ; ce fichier le ferme quand on clique à côté
 * ou sur Échap, fait marcher le crayon des favoris, et la section « Mes applications » (ajouter, modifier, supprimer ses
 * liens vers d'autres sites).
 *
 * En édition, un clic sur une tuile ne suit plus son lien : il la fait passer des « Toutes les sections » aux favoris, ou
 * l'inverse. Chaque changement est envoyé au serveur (la liste des favoris, dans l'ordre), qui ne garde que des clés
 * qu'il connaît. Les tuiles reviennent à leur place dans le catalogue grâce à leur rang (data-rang).
 */
(function () {
  'use strict';

  var MOTS = window.MOTS || {};
  var mot = function (cle, valeurs) {
    var phrase = Object.prototype.hasOwnProperty.call(MOTS, cle) ? MOTS[cle] : cle;
    Object.keys(valeurs || {}).forEach(function (nom) { phrase = phrase.split('{' + nom + '}').join(String(valeurs[nom])); });
    return phrase;
  };

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
    var fenetreDesLiens = function () { return document.getElementById(racine.getAttribute('data-lien-dialogue') || ''); };
    document.addEventListener('click', function (ev) {
      var fenetre = fenetreDesLiens();
      if (fenetre && (fenetre.open || fenetre.contains(ev.target))) { return; }
      if (racine.open && !racine.contains(ev.target)) { racine.open = false; }
    });
    document.addEventListener('keydown', function (ev) {
      var fenetre = fenetreDesLiens();
      if (fenetre && fenetre.open) { return; }
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

    // --- Mes applications : des liens vers d'autres sites, ajoutés par chacun --------------------------------------
    var blocLiens = racine.querySelector('[data-apps-liens]');
    var dialogue = document.getElementById(racine.getAttribute('data-lien-dialogue') || '');
    if (blocLiens && dialogue && typeof dialogue.showModal === 'function') {
      var grilleLiens = blocLiens.querySelector('[data-liens-grille]');
      var videLiens = blocLiens.querySelector('[data-liens-vide]');
      var boutonAjout = blocLiens.querySelector('[data-lien-ajout]');
      var crayonLiens = blocLiens.querySelector('[data-liens-edition]');
      var aideLiens = blocLiens.querySelector('[data-liens-aide]');
      // Le formulaire est dans une fenêtre (« dialog »), hors du panneau : voir le gabarit. Sans « dialog » (très vieux
      // navigateur), les liens restent des liens : seul l'ajout manque.
      var formulaire = dialogue.querySelector('[data-lien-form]');
      var titreFormulaire = dialogue.querySelector('[data-lien-form-titre]');
      var erreurLien = dialogue.querySelector('[data-lien-erreur]');
      var boutonSupprimer = dialogue.querySelector('[data-lien-supprimer]');
      var URL_LIENS = racine.getAttribute('data-url-liens');
      var MAX_LIENS = parseInt(racine.getAttribute('data-liens-max'), 10) || 24;
      var editionLiens = false;
      var enCours = null;   // la tuile qu'on modifie ; null : un nouveau lien

      var tuilesLiens = function () { return grilleLiens.querySelectorAll('[data-lien-id]'); };
      var majLiens = function () {
        var n = tuilesLiens().length;
        if (videLiens) { videLiens.hidden = n > 0; }
        boutonAjout.hidden = n >= MAX_LIENS;
      };
      /** La tuile d'un lien, construite sans « innerHTML » : le nom et l'adresse viennent de l'utilisateur. */
      var remplir = function (tuile, lien) {
        tuile.setAttribute('href', lien.url);
        tuile.setAttribute('data-lien-id', String(lien.id));
        tuile.setAttribute('data-nom', lien.nom);
        tuile.setAttribute('data-icone-choisie', lien.icone_choisie || '');
        tuile.querySelector('.apps__icone').textContent = lien.icone;
        tuile.querySelector('.apps__nom').textContent = lien.nom;
      };
      var creer = function (lien) {
        var tuile = document.createElement('a');
        tuile.className = 'apps__tuile apps__tuile--lien';
        tuile.setAttribute('target', '_blank');
        tuile.setAttribute('rel', 'noopener noreferrer');
        ['apps__icone', 'apps__nom'].forEach(function (classe) {
          var s = document.createElement('span');
          s.className = classe;
          if (classe === 'apps__icone') { s.setAttribute('aria-hidden', 'true'); }
          tuile.appendChild(s);
        });
        var crayonTuile = document.createElement('span');
        crayonTuile.className = 'apps__etoile apps__etoile--lien';
        crayonTuile.setAttribute('aria-hidden', 'true');
        crayonTuile.textContent = '✎';
        tuile.appendChild(crayonTuile);
        remplir(tuile, lien);
        return tuile;
      };

      var ouvrirFormulaire = function (tuile) {
        enCours = tuile || null;
        titreFormulaire.textContent = tuile ? mot('apps.lien_modifier_titre') : mot('apps.lien_nouveau');
        formulaire.elements.nom.value = tuile ? tuile.getAttribute('data-nom') : '';
        formulaire.elements.url.value = tuile ? tuile.getAttribute('href') : '';
        formulaire.elements.icone.value = tuile ? tuile.getAttribute('data-icone-choisie') : '';
        erreurLien.hidden = true;
        boutonSupprimer.hidden = !tuile;
        if (!dialogue.open) { dialogue.showModal(); }
        formulaire.elements.nom.focus();
        formulaire.elements.nom.select();
      };
      var fermerFormulaire = function () { if (dialogue.open) { dialogue.close(); } enCours = null; };
      var dire = function (message) { erreurLien.textContent = message; erreurLien.hidden = false; };

      /** Envoie au serveur et rend sa réponse JSON ({ok, lien | message}) ; une panne réseau devient un message. */
      var envoyer = function (adresse, corps) {
        corps.append('_csrf', JETON);
        return fetch(adresse, { method: 'POST', body: corps, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .catch(function () { return { ok: false, message: mot('apps.lien_echec') }; });
      };

      boutonAjout.hidden = false;
      crayonLiens.hidden = false;
      majLiens();

      boutonAjout.addEventListener('click', function () { ouvrirFormulaire(null); });
      Array.prototype.forEach.call(dialogue.querySelectorAll('[data-lien-annuler]'), function (b) { b.addEventListener('click', fermerFormulaire); });
      // Un clic sur le fond grisé (la fenêtre elle-même, hors de son contenu) la ferme ; Échap aussi, de lui-même.
      dialogue.addEventListener('click', function (ev) { if (ev.target === dialogue) { fermerFormulaire(); } });
      dialogue.addEventListener('close', function () { enCours = null; });
      crayonLiens.addEventListener('click', function (ev) {
        ev.stopPropagation();
        editionLiens = !editionLiens;
        racine.classList.toggle('apps--edition-liens', editionLiens);
        crayonLiens.setAttribute('aria-pressed', editionLiens ? 'true' : 'false');
        crayonLiens.textContent = editionLiens ? '✓' : '✏️';
        if (aideLiens) { aideLiens.hidden = !editionLiens; }
      });
      // En édition, un clic sur une application ne l'ouvre plus : il l'ouvre dans le formulaire.
      grilleLiens.addEventListener('click', function (ev) {
        var tuile = ev.target.closest ? ev.target.closest('[data-lien-id]') : null;
        if (!editionLiens || !tuile) { return; }
        ev.preventDefault();
        ouvrirFormulaire(tuile);
      });
      formulaire.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var visee = enCours;
        envoyer(visee ? URL_LIENS + '/' + visee.getAttribute('data-lien-id') : URL_LIENS, new FormData(formulaire)).then(function (rep) {
          if (!rep || !rep.ok) { dire((rep && rep.message) || mot('apps.lien_echec')); return; }
          if (visee) { remplir(visee, rep.lien); } else { grilleLiens.insertBefore(creer(rep.lien), boutonAjout); }
          fermerFormulaire();
          majLiens();
        });
      });
      boutonSupprimer.addEventListener('click', function () {
        var visee = enCours;
        if (!visee || !window.confirm(mot('apps.lien_supprimer_sur', { nom: visee.getAttribute('data-nom') }))) { return; }
        envoyer(URL_LIENS + '/' + visee.getAttribute('data-lien-id') + '/supprimer', new FormData()).then(function (rep) {
          if (!rep || !rep.ok) { dire((rep && rep.message) || mot('apps.lien_echec')); return; }
          visee.remove();
          fermerFormulaire();
          majLiens();
        });
      });
    }

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
