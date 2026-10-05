/*
 * Les sondages d'une discussion (entre amis ou de groupe).
 *
 *  - Dans le fil : chaque « .bulle__sondage » porte son sondage en JSON (« data-sondage »). Ce script le dessine — la
 *    question, les options avec leur nombre de votes et une barre — et prend les votes : un clic sur une option la coche ou
 *    la décoche. La page, en relevant les messages, remet à jour « data-sondage » quand quelqu'un vote : le sondage se
 *    redessine tout seul.
 *  - Dans la saisie : le bouton ouvre la fenêtre « Créer un sondage » (question, options, réponses multiples). Une option
 *    vide s'ajoute au bout dès qu'on écrit dans la dernière.
 *
 * Le texte vient des utilisateurs : tout est posé avec « textContent », jamais « innerHTML ».
 */
(function () {
  'use strict';

  var chat = document.querySelector('[data-chat]');
  if (!chat) { return; }
  var fil = chat.querySelector('[data-chat-messages]');
  var JETON = chat.getAttribute('data-jeton');

  var MOTS = window.MOTS || {};
  var mot = function (cle, valeurs) {
    var phrase = Object.prototype.hasOwnProperty.call(MOTS, cle) ? MOTS[cle] : cle;
    Object.keys(valeurs || {}).forEach(function (nom) { phrase = phrase.split('{' + nom + '}').join(String(valeurs[nom])); });
    return phrase;
  };
  // Une phrase qui s'accorde, comme « tn() » au serveur.
  var motN = function (cle, n) {
    var seul = (MOTS['_langue'] === 'fr') ? Math.abs(n) < 2 : Math.abs(n) === 1;
    return mot(cle + (seul ? '.un' : '.plusieurs'), { n: n });
  };
  var element = function (balise, classe, texte) {
    var e = document.createElement(balise);
    if (classe) { e.className = classe; }
    if (texte !== undefined) { e.textContent = texte; }
    return e;
  };

  // --- Dessiner un sondage et y voter ---------------------------------------------------------------------------------
  var voter = function (bloc, sondage, choix) {
    var corps = new FormData();
    corps.append('_csrf', JETON);
    choix.forEach(function (id) { corps.append('options[]', String(id)); });
    bloc.classList.add('bulle__sondage--attente');
    var erreur = bloc.querySelector('.sondage__erreur');
    if (erreur) { erreur.hidden = true; }
    fetch(sondage.url, { method: 'POST', body: corps, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { fait: false }; }); })
      .then(function (reponse) {
        if (!reponse.fait || !reponse.sondage) { throw new Error(reponse.message || mot('son.echec')); }
        bloc.setAttribute('data-sondage', JSON.stringify(reponse.sondage));
        rendre(bloc);
      })
      .catch(function (e) {
        bloc.classList.remove('bulle__sondage--attente');
        var zone = bloc.querySelector('.sondage__erreur');
        if (zone) { zone.textContent = e && e.message && e.message !== 'Failed to fetch' ? e.message : mot('son.echec'); zone.hidden = false; }
      });
  };

  var rendre = function (bloc) {
    var brut = bloc.getAttribute('data-sondage');
    if (!brut || bloc.getAttribute('data-sondage-rendu') === brut) { return; }
    var sondage;
    try { sondage = JSON.parse(brut); } catch (e) { return; }
    bloc.setAttribute('data-sondage-rendu', brut);
    bloc.classList.remove('bulle__sondage--attente');
    bloc.textContent = '';

    bloc.appendChild(element('p', 'sondage__question', sondage.question));
    bloc.appendChild(element('p', 'sondage__consigne', mot(sondage.multiple ? 'son.consigne_plusieurs' : 'son.consigne_une')));

    var liste = element('ul', 'sondage__options');
    var votants = Math.max(sondage.votants || 0, 1);
    sondage.options.forEach(function (option) {
      var ligne = element('li', 'sondage__ligne');
      var bouton = element('button', 'sondage__option' + (option.moi ? ' sondage__option--moi' : ''));
      bouton.type = 'button';
      bouton.setAttribute('aria-pressed', option.moi ? 'true' : 'false');
      bouton.setAttribute('data-option', String(option.id));
      bouton.appendChild(element('span', 'sondage__case' + (sondage.multiple ? ' sondage__case--carree' : '')));
      bouton.appendChild(element('span', 'sondage__texte', option.texte));
      var nombre = element('span', 'sondage__nombre', option.nombre > 0 ? String(option.nombre) : '');
      if (option.qui) { bouton.title = option.qui; }
      bouton.appendChild(nombre);
      var barre = element('span', 'sondage__barre');
      var rempli = element('span', 'sondage__rempli');
      rempli.style.width = Math.round((option.nombre / votants) * 100) + '%';
      barre.appendChild(rempli);
      bouton.appendChild(barre);
      bouton.addEventListener('click', function () {
        if (bloc.classList.contains('bulle__sondage--attente')) { return; }
        var choisies = sondage.options.filter(function (o) { return o.moi; }).map(function (o) { return o.id; });
        var avait = choisies.indexOf(option.id) >= 0;
        var choix;
        if (sondage.multiple) {
          choix = avait ? choisies.filter(function (id) { return id !== option.id; }) : choisies.concat([option.id]);
        } else {
          choix = avait ? [] : [option.id];
        }
        voter(bloc, sondage, choix);
      });
      ligne.appendChild(bouton);
      liste.appendChild(ligne);
    });
    bloc.appendChild(liste);
    bloc.appendChild(element('p', 'sondage__total', sondage.votants > 0 ? motN('son.votes', sondage.votants) : mot('son.aucun_vote')));
    var erreur = element('p', 'sondage__erreur');
    erreur.hidden = true;
    erreur.setAttribute('role', 'alert');
    bloc.appendChild(erreur);
  };

  var rendreTous = function () {
    Array.prototype.forEach.call(fil.querySelectorAll('.bulle__sondage[data-sondage]'), rendre);
  };
  rendreTous();
  // Un nouveau message, ou un vote relevé par la page : « data-sondage » change, on redessine.
  new MutationObserver(rendreTous).observe(fil, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-sondage'] });

  // --- Créer un sondage -----------------------------------------------------------------------------------------------
  var dialogue = document.querySelector('[data-sondage-dialogue]');
  var ouvrir = chat.querySelector('[data-sondage-ouvrir]');
  if (!dialogue || !ouvrir || typeof dialogue.showModal !== 'function') { return; }
  var formulaire = dialogue.querySelector('[data-sondage-formulaire]');
  var zoneOptions = dialogue.querySelector('[data-sondage-options]');
  var erreurForm = dialogue.querySelector('[data-sondage-erreur]');
  var envoyer = dialogue.querySelector('.sondage-form__envoyer');
  var OPTIONS_MAX = 12;
  var OPTIONS_DE_DEPART = 2;
  var OPTION_MAX = parseInt((zoneOptions.querySelector('input') || {}).maxLength, 10) || 100;

  /** Une ligne d'option : le champ, et la croix qui la retire (cachée tant qu'il n'y a que les deux premières). */
  var ligneOption = function () {
    var ligne = element('div', 'sondage-form__ligne');
    var champ = element('input', 'sondage-form__champ');
    champ.type = 'text';
    champ.name = 'options[]';
    champ.maxLength = OPTION_MAX;
    champ.placeholder = mot('son.option_aide');
    var croix = element('button', 'sondage-form__retirer', '✕');
    croix.type = 'button';
    croix.title = mot('son.option_retirer');
    croix.setAttribute('aria-label', mot('son.option_retirer'));
    croix.addEventListener('click', function () {
      ligne.remove();
      majOptions();
    });
    ligne.appendChild(champ);
    ligne.appendChild(croix);
    return ligne;
  };
  /** Toujours une ligne vide au bout (jusqu'à douze) ; la croix n'apparaît que sur les lignes en trop. */
  var majOptions = function () {
    var lignes = Array.prototype.slice.call(zoneOptions.querySelectorAll('.sondage-form__ligne'));
    var derniere = lignes[lignes.length - 1];
    if (lignes.length < OPTIONS_MAX && derniere && derniere.querySelector('input').value.trim() !== '') {
      zoneOptions.appendChild(ligneOption());
      lignes = Array.prototype.slice.call(zoneOptions.querySelectorAll('.sondage-form__ligne'));
    }
    lignes.forEach(function (ligne, i) {
      var vide = i === lignes.length - 1 && ligne.querySelector('input').value.trim() === '';
      ligne.querySelector('.sondage-form__retirer').hidden = lignes.length <= OPTIONS_DE_DEPART || vide;
    });
  };
  var remettreAZero = function () {
    formulaire.elements.question.value = '';
    zoneOptions.textContent = '';
    for (var i = 0; i < OPTIONS_DE_DEPART; i++) { zoneOptions.appendChild(ligneOption()); }
    formulaire.elements.multiple.checked = true;
    erreurForm.hidden = true;
    envoyer.disabled = false;
    majOptions();
  };
  zoneOptions.addEventListener('input', majOptions);

  ouvrir.hidden = false;
  ouvrir.addEventListener('click', function () {
    remettreAZero();
    dialogue.showModal();
    formulaire.elements.question.focus();
  });
  dialogue.querySelector('[data-sondage-fermer]').addEventListener('click', function () { dialogue.close(); });
  // Un clic sur le fond grisé (la fenêtre elle-même, hors de son contenu) la ferme ; Échap aussi, de lui-même.
  dialogue.addEventListener('click', function (ev) { if (ev.target === dialogue) { dialogue.close(); } });

  formulaire.addEventListener('submit', function (ev) {
    ev.preventDefault();
    envoyer.disabled = true;
    erreurForm.hidden = true;
    fetch(formulaire.action, { method: 'POST', body: new FormData(formulaire), credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { fait: false }; }); })
      .then(function (reponse) {
        if (!reponse.fait) { throw new Error(reponse.message || mot('son.envoi_echec')); }
        dialogue.close();
        // Le sondage est un message : la page le relève tout de suite, sans attendre le prochain tour.
        chat.dispatchEvent(new CustomEvent('chat:relever'));
      })
      .catch(function (e) {
        envoyer.disabled = false;
        erreurForm.textContent = e && e.message && e.message !== 'Failed to fetch' ? e.message : mot('son.envoi_echec');
        erreurForm.hidden = false;
      });
  });
})();
