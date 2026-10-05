/*
 * Les photos de profil (d'un compte ou d'un groupe) s'agrandissent d'un clic, là où elles sont marquées « data-zoom-avatar » :
 * les réglages du compte, le profil d'un ami, les réglages d'un groupe. Une visionneuse s'ouvre par-dessus la page — ou par-dessus
 * la fenêtre qui montre le profil —, avec un lien pour ouvrir l'image en grand dans un onglet.
 *
 * Un seul écouteur, sur le document : les fenêtres de l'application posent leur contenu après coup, et les avatars qui y arrivent
 * marchent sans rien initialiser. Comme les autres fenêtres, la visionneuse se ferme par sa croix (Échap ne la ferme pas).
 * Sans ce script, la photo reste une simple image.
 */
(function () {
  'use strict';

  var MOTS = window.MOTS || {};
  var mot = function (cle) { return Object.prototype.hasOwnProperty.call(MOTS, cle) ? MOTS[cle] : cle; };

  var visionneuse = null;
  var creer = function () {
    var dialogue = document.createElement('dialog');
    dialogue.className = 'visionneuse visionneuse--avatar';

    var fermer = document.createElement('button');
    fermer.className = 'fenetre__fermer';
    fermer.type = 'button';
    fermer.setAttribute('aria-label', mot('chat.fermer'));
    fermer.textContent = '✕';
    fermer.addEventListener('click', function () { dialogue.close(); });

    var image = document.createElement('img');
    image.alt = mot('chat.photo');

    var ouvrir = document.createElement('a');
    ouvrir.className = 'bouton bouton--secondaire bouton--petit visionneuse__ouvrir';
    ouvrir.target = '_blank';
    ouvrir.rel = 'noopener';
    ouvrir.textContent = mot('chat.ouvrir_en_grand');

    dialogue.appendChild(fermer);
    dialogue.appendChild(image);
    dialogue.appendChild(ouvrir);
    dialogue.addEventListener('cancel', function (ev) { ev.preventDefault(); });
    document.body.appendChild(dialogue);

    return dialogue;
  };

  var agrandir = function (avatar) {
    var photo = avatar.querySelector('img');
    if (!photo || !photo.getAttribute('src') || typeof HTMLDialogElement === 'undefined') { return; }
    if (visionneuse === null) { visionneuse = creer(); }
    if (visionneuse.open) { return; }
    var adresse = photo.getAttribute('src');
    visionneuse.querySelector('img').src = adresse;
    visionneuse.querySelector('.visionneuse__ouvrir').href = adresse;
    if (typeof visionneuse.showModal === 'function') { visionneuse.showModal(); }
  };

  var cible = function (ev) {
    return ev.target && ev.target.closest ? ev.target.closest('[data-zoom-avatar]') : null;
  };

  document.addEventListener('click', function (ev) {
    var avatar = cible(ev);
    if (!avatar || ev.ctrlKey || ev.metaKey || ev.shiftKey) { return; }
    ev.preventDefault();
    agrandir(avatar);
  });
  // Au clavier : l'avatar est un bouton (Entrée ou Espace).
  document.addEventListener('keydown', function (ev) {
    var avatar = cible(ev);
    if (!avatar || (ev.key !== 'Enter' && ev.key !== ' ')) { return; }
    ev.preventDefault();
    agrandir(avatar);
  });
})();
