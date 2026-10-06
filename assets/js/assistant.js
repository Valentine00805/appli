/*
 * L'assistant IA : le formulaire s'envoie sans recharger la page. La question s'affiche aussitôt, « l'IA réfléchit » tient la
 * place de la réponse (le champ est déjà vide), puis la réponse (déjà mise en forme par le serveur, qui échappe le HTML) la remplace. La discussion
 * nouvelle prend son adresse dans la barre, et rejoint la liste de gauche. Entrée envoie ; Maj+Entrée va à la ligne.
 *
 * Sans ce script, le formulaire s'envoie normalement et la page se relit sur la discussion : rien n'est perdu.
 * Les phrases viennent de « window.MOTS » (clés « js.ia.* »).
 */
(function () {
  'use strict';

  var racine = document.querySelector('[data-assistant]');
  if (!racine) { return; }
  var formulaire = racine.querySelector('[data-ia-formulaire]');
  var zone = racine.querySelector('[data-ia-messages]');
  var champ = racine.querySelector('[data-ia-message]');
  var bouton = racine.querySelector('[data-ia-envoyer]');
  var erreur = racine.querySelector('[data-ia-erreur]');
  var liste = racine.querySelector('[data-ia-liste]');
  var MOTS = window.MOTS || {};
  var mot = function (cle) { return Object.prototype.hasOwnProperty.call(MOTS, cle) ? MOTS[cle] : cle; };
  var enCours = false;

  var enBas = function () { zone.scrollTop = zone.scrollHeight; };
  var element = function (balise, classe, texte) {
    var e = document.createElement(balise);
    if (classe) { e.className = classe; }
    if (texte !== undefined) { e.textContent = texte; }
    return e;
  };

  /** Un tour de parole : l'auteur, et une bulle dont le contenu (HTML du serveur) est posé tel quel. */
  var tour = function (qui, html) {
    var article = element('article', 'ia__tour ia__tour--' + (qui === 'ia' ? 'ia' : 'moi'));
    article.appendChild(element('span', 'ia__auteur', mot(qui === 'ia' ? 'ia.assistant' : 'ia.toi')));
    var bulle = element('div', 'ia__bulle texte-riche-affiche');
    if (html !== null) { bulle.innerHTML = html; }
    article.appendChild(bulle);
    zone.appendChild(article);
    return { article: article, bulle: bulle };
  };

  var dire = function (message) {
    erreur.textContent = message || mot('ia.echec');
    erreur.hidden = false;
  };

  var ajusterChamp = function () {
    champ.style.height = 'auto';
    champ.style.height = Math.min(champ.scrollHeight, 220) + 'px';
  };
  champ.addEventListener('input', ajusterChamp);

  /** La discussion nouvelle : son adresse dans la barre, son champ caché, son titre en tête de la liste. */
  var adopter = function (reponse) {
    racine.setAttribute('data-discussion', String(reponse.discussion));
    var cache = formulaire.querySelector('input[name="discussion"]');
    if (!cache) {
      cache = document.createElement('input');
      cache.type = 'hidden';
      cache.name = 'discussion';
      formulaire.insertBefore(cache, formulaire.firstChild.nextSibling);
    }
    cache.value = String(reponse.discussion);
    try { window.history.replaceState(null, '', reponse.adresse); } catch (e) { /* l'adresse reste, la page marche */ }
    var aucune = racine.querySelector('[data-ia-aucune]');
    if (aucune) { aucune.remove(); }
    var li = document.createElement('li');
    var a = document.createElement('a');
    a.href = reponse.adresse;
    a.textContent = reponse.titre;
    a.setAttribute('aria-current', 'page');
    li.appendChild(a);
    Array.prototype.forEach.call(liste.querySelectorAll('[aria-current]'), function (x) { x.removeAttribute('aria-current'); });
    liste.insertBefore(li, liste.firstChild);
  };

  var envoyer = function () {
    if (enCours) { return; }
    var texte = champ.value.trim();
    if (texte === '') { return; }
    enCours = true;
    bouton.disabled = true;
    erreur.hidden = true;
    var accueil = racine.querySelector('[data-ia-accueil]');
    if (accueil) { accueil.remove(); }

    var question = tour('moi', null);
    question.bulle.textContent = texte;
    var attente = tour('ia', null);
    attente.bulle.classList.add('ia__bulle--attente');
    attente.bulle.textContent = mot('ia.reflechit');
    enBas();

    // Le message part : le champ se vide tout de suite (il revient si l'envoi échoue).
    var donnees = new FormData(formulaire);
    champ.value = '';
    ajusterChamp();
    fetch(formulaire.action, { method: 'POST', body: donnees, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { fait: false }; }); })
      .then(function (reponse) {
        if (!reponse.fait) { throw new Error(reponse.message || ''); }
        question.bulle.innerHTML = reponse.questionHtml;
        attente.bulle.classList.remove('ia__bulle--attente');
        attente.bulle.innerHTML = reponse.reponseHtml;
        if (reponse.nouvelle) { adopter(reponse); }
      })
      .catch(function (e) {
        // La question n'est pas perdue : elle revient dans le champ, et la réponse en attente disparaît.
        question.article.remove();
        attente.article.remove();
        if (champ.value.trim() === '') { champ.value = texte; ajusterChamp(); }
        dire(e && e.message && e.message !== 'Failed to fetch' ? e.message : '');
      })
      .then(function () {
        enCours = false;
        bouton.disabled = false;
        champ.focus();
        enBas();
      });
  };

  formulaire.addEventListener('submit', function (ev) {
    ev.preventDefault();
    envoyer();
  });
  champ.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter' && !ev.shiftKey && !ev.isComposing) {
      ev.preventDefault();
      envoyer();
    }
  });
  Array.prototype.forEach.call(racine.querySelectorAll('[data-ia-suggestion]'), function (b) {
    b.addEventListener('click', function () {
      champ.value = b.getAttribute('data-ia-suggestion');
      ajusterChamp();
      champ.focus();
    });
  });

  enBas();
})();
