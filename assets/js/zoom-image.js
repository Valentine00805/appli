/*
 * Le zoom d'une image montrée dans l'aperçu (souvent dans une fenêtre).
 *
 * L'image s'affiche d'abord ajustée à la largeur du cadre ; les boutons + et −, la touche + ou −, ou un double-clic la
 * grossissent ou la ramènent (« ajuster » ↔ « taille réelle »), et on s'y déplace en la faisant glisser ou avec les
 * barres de défilement du cadre. Sans ce script, l'image est affichée à la largeur de la page et rien d'autre ne change.
 *
 * Comme pour la carte mentale, le contenu d'une fenêtre est posé par app.js, qui n'exécute pas les scripts d'un
 * fragment : ce fichier est chargé avec toutes les pages, et app.js appelle window.initialiserZoomImage(zone) sur ce
 * qu'il vient de poser.
 */
(function () {
  'use strict';

  var MIN = 0.1, MAX = 4, PAS = 1.25;

  var initialiser = function (racine) {
    if (racine.hasAttribute('data-zoom-pret')) { return; }
    var cadre = racine.querySelector('[data-zoom-cadre]');
    var image = cadre ? cadre.querySelector('img') : null;
    var barre = racine.querySelector('[data-zoom-barre]');
    var niveau = racine.querySelector('[data-zoom-niveau]');
    if (!cadre || !image || !barre) { return; }
    racine.setAttribute('data-zoom-pret', '1');

    var echelle = 1;
    var ajuste = true;

    var largeurNaturelle = function () { return image.naturalWidth || 0; };

    /** L'échelle qui fait tenir l'image dans la largeur du cadre — sans jamais l'agrandir au-delà de sa taille réelle. */
    var echelleAjustee = function () {
      var nat = largeurNaturelle();
      return nat > 0 ? Math.min(1, (cadre.clientWidth - 2) / nat) : 1;
    };

    var appliquer = function (centre) {
      var nat = largeurNaturelle();
      if (!nat) { return; }
      if (ajuste) { echelle = echelleAjustee(); }
      echelle = Math.max(MIN, Math.min(MAX, echelle));
      // Le point regardé au centre du cadre reste au centre : zoomer ne fait pas sauter ailleurs.
      var ancienne = image.clientWidth || 1;
      var cx = (cadre.scrollLeft + cadre.clientWidth / 2) / ancienne;
      var cy = (cadre.scrollTop + cadre.clientHeight / 2) / (image.clientHeight || 1);
      image.style.maxWidth = 'none';
      image.style.width = Math.round(nat * echelle) + 'px';
      image.style.height = 'auto';
      if (centre) {
        cadre.scrollLeft = cx * image.clientWidth - cadre.clientWidth / 2;
        cadre.scrollTop = cy * image.clientHeight - cadre.clientHeight / 2;
      }
      if (niveau) { niveau.textContent = Math.round(echelle * 100) + ' %'; }
      cadre.classList.toggle('zoom__cadre--deplacable', image.clientWidth > cadre.clientWidth || image.clientHeight > cadre.clientHeight);
    };

    var regler = function (nouvelle) {
      ajuste = false;
      echelle = nouvelle;
      appliquer(true);
    };

    var agir = function (action) {
      if (action === 'plus') { regler(echelle * PAS); }
      else if (action === 'moins') { regler(echelle / PAS); }
      else if (action === 'reel') { regler(1); }
      else if (action === 'ajuster') { ajuste = true; appliquer(false); cadre.scrollLeft = 0; cadre.scrollTop = 0; }
    };

    barre.addEventListener('click', function (ev) {
      var bouton = ev.target.closest ? ev.target.closest('[data-zoom-action]') : null;
      if (bouton) { agir(bouton.getAttribute('data-zoom-action')); }
    });

    // Un double-clic passe de « ajusté » à « taille réelle », et inversement.
    cadre.addEventListener('dblclick', function () {
      agir(ajuste && echelleAjustee() < 1 ? 'reel' : 'ajuster');
    });

    cadre.addEventListener('keydown', function (ev) {
      if (ev.ctrlKey || ev.metaKey || ev.altKey) { return; }
      if (ev.key === '+' || ev.key === '=') { ev.preventDefault(); agir('plus'); }
      else if (ev.key === '-') { ev.preventDefault(); agir('moins'); }
      else if (ev.key === '0') { ev.preventDefault(); agir('ajuster'); }
      else if (ev.key === '1') { ev.preventDefault(); agir('reel'); }
    });

    // Se déplacer en faisant glisser l'image (à la souris ; au doigt, le cadre défile déjà).
    var glisse = null;
    cadre.addEventListener('pointerdown', function (ev) {
      if (ev.pointerType !== 'mouse' || ev.button !== 0) { return; }
      glisse = { x: ev.clientX, y: ev.clientY, gauche: cadre.scrollLeft, haut: cadre.scrollTop };
      cadre.setPointerCapture(ev.pointerId);
      cadre.classList.add('zoom__cadre--glisse');
    });
    cadre.addEventListener('pointermove', function (ev) {
      if (!glisse) { return; }
      cadre.scrollLeft = glisse.gauche - (ev.clientX - glisse.x);
      cadre.scrollTop = glisse.haut - (ev.clientY - glisse.y);
    });
    var lacher = function () { glisse = null; cadre.classList.remove('zoom__cadre--glisse'); };
    cadre.addEventListener('pointerup', lacher);
    cadre.addEventListener('pointercancel', lacher);

    // Le cadre change de taille (la fenêtre s'agrandit, l'écran tourne) : une image ajustée suit.
    if (window.ResizeObserver) {
      new ResizeObserver(function () { if (ajuste) { appliquer(false); } }).observe(cadre);
    } else {
      window.addEventListener('resize', function () { if (ajuste) { appliquer(false); } });
    }

    barre.hidden = false;
    var aide = racine.querySelector('[data-zoom-aide]');
    if (aide) { aide.hidden = false; }
    cadre.setAttribute('tabindex', '0');
    if (image.complete && image.naturalWidth) { appliquer(false); }
    else { image.addEventListener('load', function () { appliquer(false); }); }
  };

  window.initialiserZoomImage = function (zone) {
    Array.prototype.forEach.call((zone || document).querySelectorAll('[data-zoom-image]'), initialiser);
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { window.initialiserZoomImage(document); });
  } else {
    window.initialiserZoomImage(document);
  }
})();
