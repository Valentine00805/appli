/*
 * La barre des serveurs : on réorganise ses serveurs par glisser-déposer (à la souris ou au stylet), ou au clavier avec
 * Alt + flèches. Le serveur attrapé prend la place de celui qu'il survole ; l'ordre est gardé à chaque changement, sans recharger
 * la page. Un simple clic ouvre toujours le serveur : il faut bouger de quelques pixels pour que cela devienne un glissement.
 *
 * Au doigt, la barre se fait défiler (sur un petit écran, c'est une bande horizontale) : le glissement n'y est pas pris.
 * Sans ce script, la barre marche comme avant, dans l'ordre enregistré.
 */
(function () {
  'use strict';

  var barre = document.querySelector('[data-ordonner]');
  if (!barre || !window.fetch) { return; }
  var url = barre.getAttribute('data-ordonner');
  var jeton = barre.getAttribute('data-jeton');
  var SELECTEUR = '[data-serveur]';
  var glisse = null;   // {bouton, x, y, pointeur, actif, avant}

  var boutons = function () { return Array.prototype.slice.call(barre.querySelectorAll(SELECTEUR)); };
  var ordre = function () { return boutons().map(function (b) { return b.getAttribute('data-serveur'); }); };
  var bouton = function (cible) { return cible && cible.closest ? cible.closest(SELECTEUR) : null; };
  // Vertical sur un grand écran ; horizontal quand la barre devient une bande.
  var vertical = function () { return window.getComputedStyle(barre).flexDirection.indexOf('column') === 0; };

  var enregistrer = function () {
    var corps = new FormData();
    corps.append('_csrf', jeton);
    ordre().forEach(function (id) { corps.append('serveurs[]', id); });
    fetch(url, { method: 'POST', body: corps, credentials: 'same-origin', keepalive: true }).catch(function () { /* l'ordre se refera au prochain essai */ });
  };

  barre.addEventListener('pointerdown', function (ev) {
    var b = bouton(ev.target);
    if (!b || ev.pointerType === 'touch' || (ev.pointerType === 'mouse' && ev.button !== 0)) { return; }
    // Un lien et son image : le navigateur voudrait les faire glisser lui-même, ce qui coupe les événements « pointer ».
    b.setAttribute('draggable', 'false');
    Array.prototype.forEach.call(b.querySelectorAll('img, a'), function (e) { e.setAttribute('draggable', 'false'); });
    glisse = { bouton: b, x: ev.clientX, y: ev.clientY, pointeur: ev.pointerId, actif: false, avant: ordre().join(',') };
  });

  document.addEventListener('pointermove', function (ev) {
    if (!glisse || ev.pointerId !== glisse.pointeur) { return; }
    if (!glisse.actif) {
      if (Math.abs(ev.clientX - glisse.x) + Math.abs(ev.clientY - glisse.y) < 8) { return; }
      glisse.actif = true;
      glisse.bouton.classList.add('barre-serveurs__bouton--glisse');
      barre.classList.add('barre-serveurs--tri');
      try { glisse.bouton.setPointerCapture(ev.pointerId); } catch (e) { /* le glissement marche sans capture */ }
    }
    ev.preventDefault();
    var autre = bouton(document.elementFromPoint(ev.clientX, ev.clientY));
    if (!autre || autre === glisse.bouton || !barre.contains(autre)) { return; }
    // Il passe devant l'autre quand on est dans sa première moitié, derrière quand on est dans la seconde : pas de va-et-vient.
    var cadre = autre.getBoundingClientRect();
    var apres = vertical() ? ev.clientY > cadre.top + cadre.height / 2 : ev.clientX > cadre.left + cadre.width / 2;
    var suit = (glisse.bouton.compareDocumentPosition(autre) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0;
    // On ne le déplace que dans le sens où il va : sinon, survoler la moitié proche d'un voisin ferait trembler la barre.
    if (suit && !apres) { return; }
    if (!suit && apres) { return; }
    barre.insertBefore(glisse.bouton, suit ? autre.nextSibling : autre);
  });

  var fin = function (ev) {
    if (!glisse || ev.pointerId !== glisse.pointeur) { return; }
    var fini = glisse;
    glisse = null;
    if (!fini.actif) { return; }
    fini.bouton.classList.remove('barre-serveurs__bouton--glisse');
    barre.classList.remove('barre-serveurs--tri');
    // Le clic qui suit un glissement ne doit pas ouvrir le serveur.
    var etouffer = function (e) { e.preventDefault(); e.stopPropagation(); };
    barre.addEventListener('click', etouffer, { capture: true, once: true });
    setTimeout(function () { barre.removeEventListener('click', etouffer, { capture: true }); }, 0);
    if (ordre().join(',') !== fini.avant) { enregistrer(); }
  };
  document.addEventListener('pointerup', fin);
  document.addEventListener('pointercancel', fin);
  barre.addEventListener('dragstart', function (ev) { if (bouton(ev.target)) { ev.preventDefault(); } });

  // Au clavier : Alt + flèches déplace le serveur qui a le focus.
  barre.addEventListener('keydown', function (ev) {
    var b = bouton(ev.target);
    if (!b || !ev.altKey) { return; }
    var avance = ev.key === 'ArrowDown' || ev.key === 'ArrowRight';
    if (!avance && ev.key !== 'ArrowUp' && ev.key !== 'ArrowLeft') { return; }
    ev.preventDefault();
    var voisin = avance ? b.nextElementSibling : b.previousElementSibling;
    if (!voisin || !voisin.matches(SELECTEUR)) { return; }
    barre.insertBefore(b, avance ? voisin.nextSibling : voisin);
    b.focus();
    enregistrer();
  });
})();
