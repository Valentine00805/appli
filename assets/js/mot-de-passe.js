/*
 * Voir le mot de passe qu'on tape.
 *
 * Chaque champ de mot de passe reçoit, à sa droite, un œil : un clic montre
 * le texte, un second le cache de nouveau. Le mot de passe est toujours
 * recaché à l'envoi du formulaire, pour que le navigateur ne le retienne pas
 * comme un texte ordinaire.
 */
(function () {
  'use strict';

  var ns = 'http://www.w3.org/2000/svg';
  var dessin = function (barre) {
    var svg = document.createElementNS(ns, 'svg');
    [['viewBox', '0 0 24 24'], ['width', '20'], ['height', '20'], ['fill', 'none'], ['stroke', 'currentColor'],
      ['stroke-width', '2'], ['stroke-linecap', 'round'], ['stroke-linejoin', 'round'], ['aria-hidden', 'true'], ['focusable', 'false']]
      .forEach(function (a) { svg.setAttribute(a[0], a[1]); });
    var traits = ['M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z'];
    if (barre) { traits.push('M3 3l18 18'); }
    traits.forEach(function (d) {
      var p = document.createElementNS(ns, 'path');
      p.setAttribute('d', d);
      svg.appendChild(p);
    });
    var c = document.createElementNS(ns, 'circle');
    [['cx', '12'], ['cy', '12'], ['r', '3']].forEach(function (a) { c.setAttribute(a[0], a[1]); });
    svg.appendChild(c);
    return svg;
  };

  document.querySelectorAll('input[type="password"]').forEach(function (champ) {
    if (champ.closest('.mot-de-passe')) { return; }
    var enveloppe = document.createElement('span');
    enveloppe.className = 'mot-de-passe';
    champ.parentNode.insertBefore(enveloppe, champ);
    enveloppe.appendChild(champ);

    var bouton = document.createElement('button');
    bouton.type = 'button';
    bouton.className = 'mot-de-passe__oeil';
    var montrer = function (visible) {
      champ.type = visible ? 'text' : 'password';
      bouton.textContent = '';
      bouton.appendChild(dessin(visible));
      bouton.setAttribute('aria-pressed', visible ? 'true' : 'false');
      bouton.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
      bouton.title = visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe';
    };
    montrer(false);
    bouton.addEventListener('click', function () {
      var position = champ.selectionStart;
      montrer(champ.type === 'password');
      champ.focus();
      try { champ.setSelectionRange(position, position); } catch (e) { /* champ sans sélection */ }
    });
    enveloppe.appendChild(bouton);

    if (champ.form) {
      champ.form.addEventListener('submit', function () { montrer(false); });
    }
  });
})();
