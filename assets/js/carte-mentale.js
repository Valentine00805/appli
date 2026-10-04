/*
 * L'éditeur de carte mentale.
 *
 * La carte est un arbre {t, c, p} (texte, sous-idées, branche repliée) que la page donne en JSON. On la dessine
 * en SVG, de gauche à droite, et chaque geste — ajouter, renommer, déplacer, replier, supprimer — modifie l'arbre
 * puis le redessine. L'arbre est envoyé au serveur peu après chaque modification (rien à valider), et le serveur
 * le re-nettoie : ce script n'est qu'une commodité, pas une garantie.
 *
 * Les couleurs sont des attributs, pas du CSS : le même dessin sert à l'écran (clair ou sombre) et à l'image
 * téléchargée, sans dépendre de la feuille de style.
 *
 * La carte s'ouvre le plus souvent dans une fenêtre : le contenu y est posé par app.js, qui n'exécute pas les scripts
 * d'un fragment. Ce fichier est donc chargé avec toutes les pages, et app.js appelle window.initialiserCarteMentale(zone)
 * sur ce qu'il vient de poser. Une page entière (sans fenêtre) est initialisée au chargement.
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
  if (racine.hasAttribute('data-cm-pret')) { return; }
  racine.setAttribute('data-cm-pret', '1');

  var NOEUDS_MAX = parseInt(racine.getAttribute('data-max-noeuds'), 10) || 250;
  var NIVEAUX_MAX = parseInt(racine.getAttribute('data-max-niveaux'), 10) || 6;
  var TEXTE_MAX = parseInt(racine.getAttribute('data-max-texte'), 10) || 120;
  var URL_ENREGISTRER = racine.getAttribute('data-url');
  var URL_IMAGE = racine.getAttribute('data-url-image');
  var JETON = racine.getAttribute('data-jeton');

  var toile = racine.querySelector('[data-cm-toile]');
  var barre = racine.querySelector('[data-cm-barre]');
  var aide = racine.querySelector('[data-cm-aide]');
  var etat = racine.querySelector('[data-cm-etat]');
  var plan = racine.querySelector('[data-cm-plan]');
  var champTitre = racine.querySelector('[data-cm-titre]');
  // La page et la fenêtre peuvent montrer deux cartes à la fois : on ne cherche que dans la zone de celle-ci.
  var zone = racine.parentNode || document;
  var compte = zone.querySelector('[data-cm-compte]');
  var titrePage = zone.querySelector('[data-cm-titre-page]');

  var arbre;
  try { arbre = JSON.parse(racine.getAttribute('data-arbre')); } catch (e) { return; }
  if (!arbre || typeof arbre.t !== 'string') { return; }

  // --- Le dessin : couleurs et mesures --------------------------------------------------------------------------
  var FORTES = ['#2563eb', '#15803d', '#b45309', '#b91c1c', '#6d28d9', '#0e7490', '#be185d', '#4d7c0f'];
  var PALES = ['#dbeafe', '#dcfce7', '#fef3c7', '#fee2e2', '#ede9fe', '#cffafe', '#fce7f3', '#ecfccb'];
  var CENTRE = '#1e3a8a';
  var POLICE = 'system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
  var CAR_MAX = 22, LIGNES_MAX = 3, HAUT_LIGNE = 18, MARGE_X = 12, MARGE_Y = 8, ECART_X = 56, ECART_Y = 10, BORD = 24;

  var echelle = 1;
  var selection = arbre;
  var parents = new Map();
  var histoire = [];
  var modifie = false;
  var enEnregistrement = false;
  var minuteur = null;
  var enEdition = null;

  var esc = function (s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  };

  /** Le texte d'une idée, coupé en lignes de CAR_MAX lettres au plus, trois lignes au plus. */
  var lignes = function (texte) {
    var res = [], cour = '';
    texte.split(' ').forEach(function (m) {
      while (m.length > CAR_MAX) {
        if (cour) { res.push(cour); cour = ''; }
        res.push(m.slice(0, CAR_MAX));
        m = m.slice(CAR_MAX);
      }
      if (!cour) { cour = m; }
      else if ((cour + ' ' + m).length <= CAR_MAX) { cour += ' ' + m; }
      else { res.push(cour); cour = m; }
    });
    if (cour) { res.push(cour); }
    if (res.length > LIGNES_MAX) {
      res = res.slice(0, LIGNES_MAX);
      res[LIGNES_MAX - 1] = res[LIGNES_MAX - 1].slice(0, CAR_MAX - 1) + '…';
    }
    return res.length ? res : [''];
  };

  var compter = function (n) {
    return 1 + n.c.reduce(function (somme, e) { return somme + compter(e); }, 0);
  };

  /** Les liens enfant → parent, refaits à chaque dessin : l'arbre reste un JSON simple, sans lien circulaire. */
  var relier = function (n, parent) {
    parents.set(n, parent);
    n.c.forEach(function (e) { relier(e, n); });
  };

  var chemin = function (n) {
    var c = [];
    while (parents.get(n)) {
      var p = parents.get(n);
      c.unshift(p.c.indexOf(n));
      n = p;
    }
    return c;
  };
  var auChemin = function (c) {
    var n = arbre;
    for (var i = 0; i < c.length; i++) {
      if (!n.c[c[i]]) { break; }
      n = n.c[c[i]];
    }
    return n;
  };
  var niveauDe = function (n) { return chemin(n).length; };

  /** Ce qui part au serveur : le texte, les sous-idées, et « p » quand la branche est repliée. */
  var epure = function (n) {
    var o = { t: n.t, c: n.c.map(epure) };
    if (n.p && n.c.length) { o.p = 1; }
    return o;
  };

  // --- La mise en page : de gauche à droite, chaque idée centrée sur ses sous-idées ------------------------------
  var dessin = { noeuds: [], largeur: 0, hauteur: 0 };

  var mesurer = function (n, niveau) {
    n._l = lignes(n.t);
    var plusLong = Math.max.apply(null, n._l.map(function (l) { return l.length; }));
    var facteur = niveau === 0 ? 8.6 : 7.3;
    n._w = Math.max(64, Math.round(plusLong * facteur) + 2 * MARGE_X + (n.p ? 20 : 0));
    n._h = n._l.length * HAUT_LIGNE + 2 * MARGE_Y;
  };

  var disposer = function (n, niveau, couleur, colonnes) {
    mesurer(n, niveau);
    n._niveau = niveau;
    n._couleur = couleur;
    colonnes[niveau] = Math.max(colonnes[niveau] || 0, n._w);
    var enfants = n.p ? [] : n.c;
    var total = 0;
    enfants.forEach(function (e, i) {
      total += disposer(e, niveau + 1, niveau === 0 ? i % FORTES.length : couleur, colonnes) + (i ? ECART_Y : 0);
    });
    n._sh = Math.max(n._h, total);
    n._enfantsH = total;
    return n._sh;
  };

  var placer = function (n, x, haut, xs, liste) {
    n._x = xs[n._niveau];
    n._y = haut + n._sh / 2 - n._h / 2;
    liste.push(n);
    if (n.p) { return; }
    var y = haut + (n._sh - n._enfantsH) / 2;
    n.c.forEach(function (e) {
      placer(e, x, y, xs, liste);
      y += e._sh + ECART_Y;
    });
  };

  var calculer = function () {
    parents = new Map();
    relier(arbre, null);
    var colonnes = [];
    disposer(arbre, 0, 0, colonnes);
    var xs = [], x = BORD;
    colonnes.forEach(function (l, i) { xs[i] = x; x += l + ECART_X; });
    var liste = [];
    placer(arbre, 0, BORD, xs, liste);
    dessin.noeuds = liste;
    dessin.largeur = x - ECART_X + BORD;
    dessin.hauteur = arbre._sh + 2 * BORD;
  };

  /** Le SVG de la carte. Pour l'image téléchargée : sans sélection, sans gestes, sur fond blanc. */
  var svg = function (exportation) {
    var h = [];
    var l = dessin.largeur, ht = dessin.hauteur;
    var f = exportation ? 1 : echelle;
    h.push('<svg xmlns="http://www.w3.org/2000/svg" width="' + Math.round(l * f) + '" height="' + Math.round(ht * f)
      + '" viewBox="0 0 ' + l + ' ' + ht + '" font-family="' + POLICE + '"'
      + (exportation ? '' : ' role="tree" aria-label="' + esc(mot('cm.arbre_label')) + '"') + '>');
    if (exportation) { h.push('<rect width="100%" height="100%" fill="#ffffff"/>'); }

    dessin.noeuds.forEach(function (n) {
      if (n.p) { return; }
      n.c.forEach(function (e) {
        var x1 = n._x + n._w, y1 = n._y + n._h / 2, x2 = e._x, y2 = e._y + e._h / 2, xm = (x1 + x2) / 2;
        h.push('<path d="M' + x1 + ' ' + y1 + ' C' + xm + ' ' + y1 + ' ' + xm + ' ' + y2 + ' ' + x2 + ' ' + y2
          + '" fill="none" stroke="' + FORTES[e._couleur] + '" stroke-width="' + (e._niveau === 1 ? 3 : 2) + '" stroke-linecap="round"/>');
      });
    });

    dessin.noeuds.forEach(function (n, i) {
      var racineN = n._niveau === 0, branche = n._niveau === 1;
      var fond = racineN ? CENTRE : (branche ? FORTES[n._couleur] : PALES[n._couleur]);
      var texte = racineN || branche ? '#ffffff' : '#1f2937';
      var choisi = !exportation && n === selection;
      h.push('<g' + (exportation ? '' : ' data-id="' + i + '" role="treeitem" aria-level="' + (n._niveau + 1)
        + '" aria-selected="' + (choisi ? 'true' : 'false') + '" tabindex="' + (choisi ? '0' : '-1') + '" style="cursor:pointer;outline:none"') + '>');
      h.push('<title>' + esc(n.t) + '</title>');
      if (choisi) {
        h.push('<rect x="' + (n._x - 4) + '" y="' + (n._y - 4) + '" width="' + (n._w + 8) + '" height="' + (n._h + 8)
          + '" rx="14" fill="none" stroke="#f59e0b" stroke-width="3"/>');
      }
      h.push('<rect x="' + n._x + '" y="' + n._y + '" width="' + n._w + '" height="' + n._h + '" rx="10" fill="' + fond
        + '" stroke="' + (racineN ? CENTRE : FORTES[n._couleur]) + '" stroke-width="2"/>');
      n._l.forEach(function (ligne, k) {
        h.push('<text x="' + (n._x + (n.p ? (n._w - 20) / 2 + 0 : n._w / 2)) + '" y="' + (n._y + MARGE_Y + HAUT_LIGNE * k + 13)
          + '" text-anchor="middle" font-size="' + (racineN ? 16 : 14) + '"' + (racineN || branche ? ' font-weight="600"' : '')
          + ' fill="' + texte + '">' + esc(ligne) + '</text>');
      });
      if (n.p) {
        // La branche est repliée : une pastille dit combien d'idées elle cache, et la déplie d'un clic.
        var cachees = compter(n) - 1;
        h.push('<g' + (exportation ? '' : ' data-pli="' + i + '" style="cursor:pointer"') + '>'
          + (exportation ? '' : '<title>' + esc(mot('cm.deplier', { n: cachees })) + '</title>')
          + '<circle cx="' + (n._x + n._w - 14) + '" cy="' + (n._y + n._h / 2) + '" r="10" fill="#ffffff" stroke="' + FORTES[n._couleur] + '" stroke-width="2"/>'
          + '<text x="' + (n._x + n._w - 14) + '" y="' + (n._y + n._h / 2 + 4) + '" text-anchor="middle" font-size="11" font-weight="700" fill="#1f2937">+' + cachees + '</text></g>');
      }
      h.push('</g>');
    });
    h.push('</svg>');

    return h.join('');
  };

  // --- Dessiner, choisir, enregistrer ------------------------------------------------------------------------------
  var surface = null;

  var dire = function (texte) { etat.textContent = texte; };

  var dessiner = function (garderFocus) {
    var avait = garderFocus && toile.contains(document.activeElement);
    calculer();
    var defilement = [toile.scrollLeft, toile.scrollTop];
    toile.innerHTML = '<div class="cm__dessin">' + svg(false) + '</div>';
    surface = toile.firstChild;
    toile.scrollLeft = defilement[0];
    toile.scrollTop = defilement[1];
    if (avait) {
      var g = toile.querySelector('[data-id="' + dessin.noeuds.indexOf(selection) + '"]');
      if (g) { g.focus({ preventScroll: true }); }
    }
    majBarre();
    if (compte) {
      var n = compter(arbre);
      var seul = MOTS['_langue'] === 'fr' ? n < 2 : n === 1;
      compte.textContent = mot(seul ? 'cm.idees_une' : 'cm.idees_plusieurs', { n: n });
    }
  };

  var montrerSelection = function () {
    var g = toile.querySelector('[data-id="' + dessin.noeuds.indexOf(selection) + '"]');
    if (!g) { return; }
    g.focus({ preventScroll: true });
    var r = g.getBoundingClientRect(), t = toile.getBoundingClientRect();
    if (r.left < t.left || r.right > t.right) { toile.scrollLeft += r.left - t.left - 40; }
    if (r.top < t.top || r.bottom > t.bottom) { toile.scrollTop += r.top - t.top - 40; }
  };

  /** Les boutons qui n'ont pas de sens (supprimer le centre, replier une idée sans suite) sont grisés. */
  var majBarre = function () {
    var estRacine = selection === arbre;
    var freres = parents.get(selection) ? parents.get(selection).c : [];
    var rang = freres.indexOf(selection);
    var etatBouton = {
      supprimer: estRacine, replier: selection.c.length === 0,
      monter: estRacine || rang <= 0, descendre: estRacine || rang === freres.length - 1,
      annuler: histoire.length === 0, frere: estRacine
    };
    Array.prototype.forEach.call(barre.querySelectorAll('[data-cm-action]'), function (b) {
      var a = b.getAttribute('data-cm-action');
      if (Object.prototype.hasOwnProperty.call(etatBouton, a)) { b.disabled = etatBouton[a]; }
    });
  };

  var enregistrer = function () {
    clearTimeout(minuteur);
    if (!modifie) { return; }
    if (enEnregistrement) { minuteur = setTimeout(enregistrer, 400); return; }
    enEnregistrement = true;
    var corps = new FormData();
    corps.append('_csrf', JETON);
    corps.append('arbre', JSON.stringify(epure(arbre)));
    if (champTitre) { corps.append('titre', champTitre.value); }
    modifie = false;
    dire(mot('cm.en_cours'));
    // « keepalive » : l'envoi va à son terme même si la fenêtre se ferme, ou la page se recharge, juste après.
    fetch(URL_ENREGISTRER, { method: 'POST', body: corps, credentials: 'same-origin', keepalive: true })
      .then(function (r) {
        if (r.status !== 204) { throw new Error('http ' + r.status); }
        dire(mot('cm.enregistre'));
        if (titrePage && champTitre && champTitre.value.trim() !== '') { titrePage.textContent = champTitre.value.trim(); }
      })
      .catch(function () { modifie = true; dire(mot('cm.echec')); })
      .then(function () { enEnregistrement = false; });
  };

  var programmer = function () {
    modifie = true;
    // Dans une fenêtre : la page derrière se recharge à la fermeture, pour dire la même chose (voir app.js).
    document.dispatchEvent(new Event('fenetre:changee'));
    dire(mot('cm.modifie'));
    clearTimeout(minuteur);
    minuteur = setTimeout(enregistrer, 700);
  };

  /** Fait une modification : l'état d'avant est gardé pour « Annuler ». */
  var changer = function (geste) {
    histoire.push({ arbre: JSON.stringify(epure(arbre)), chemin: chemin(selection) });
    if (histoire.length > 60) { histoire.shift(); }
    geste();
    dessiner(true);
    montrerSelection();
    programmer();
  };

  var annuler = function () {
    var avant = histoire.pop();
    if (!avant) { return; }
    arbre = JSON.parse(avant.arbre);
    parents = new Map();
    relier(arbre, null);
    selection = auChemin(avant.chemin);
    dessiner(true);
    montrerSelection();
    programmer();
  };

  // --- Les gestes ------------------------------------------------------------------------------------------------
  var peutAjouter = function (parent) {
    if (compter(arbre) >= NOEUDS_MAX) { dire(mot('cm.limite', { n: NOEUDS_MAX })); return false; }
    if (niveauDe(parent) + 1 > NIVEAUX_MAX) { dire(mot('cm.niveau_max')); return false; }
    return true;
  };

  var ajouter = function (parent, apres) {
    if (!peutAjouter(parent)) { return; }
    var neuve = { t: mot('cm.nouvelle_idee'), c: [] };
    changer(function () {
      parent.p = 0;
      var rang = apres ? parent.c.indexOf(apres) + 1 : parent.c.length;
      parent.c.splice(rang, 0, neuve);
      selection = neuve;
    });
    renommer(neuve, true);
  };

  var supprimer = function () {
    var parent = parents.get(selection);
    if (!parent) { return; }
    var rang = parent.c.indexOf(selection);
    changer(function () {
      parent.c.splice(rang, 1);
      selection = parent.c[Math.min(rang, parent.c.length - 1)] || parent;
      if (!parent.c.length) { parent.p = 0; }
    });
  };

  var deplacer = function (sens) {
    var parent = parents.get(selection);
    if (!parent) { return; }
    var rang = parent.c.indexOf(selection), voulu = rang + sens;
    if (voulu < 0 || voulu >= parent.c.length) { return; }
    changer(function () {
      parent.c.splice(rang, 1);
      parent.c.splice(voulu, 0, selection);
    });
  };

  var replier = function (n) {
    n = n || selection;
    if (!n.c.length) { return; }
    changer(function () { n.p = n.p ? 0 : 1; if (n.p && contient(n, selection)) { selection = n; } });
  };
  var contient = function (n, cible) {
    return n.c.some(function (e) { return e === cible || contient(e, cible); });
  };

  /** L'idée devient un champ de saisie, posé exactement dessus. */
  var renommer = function (n, neuve) {
    if (enEdition) { return; }
    var g = toile.querySelector('[data-id="' + dessin.noeuds.indexOf(n) + '"]');
    if (!g) { return; }
    var saisie = document.createElement('input');
    saisie.type = 'text';
    saisie.className = 'cm__saisie';
    saisie.maxLength = TEXTE_MAX;
    saisie.value = n.t;
    saisie.setAttribute('aria-label', mot('cm.renommer'));
    var largeur = Math.max(n._w, 160) * echelle;
    saisie.style.left = (n._x * echelle - (largeur - n._w * echelle) / 2) + 'px';
    saisie.style.top = (n._y * echelle) + 'px';
    saisie.style.width = largeur + 'px';
    saisie.style.height = Math.max(n._h * echelle, 32) + 'px';
    surface.appendChild(saisie);
    enEdition = saisie;
    saisie.focus();
    saisie.select();

    var fini = false;
    var clore = function (garder) {
      if (fini) { return; }
      fini = true;
      var valeur = saisie.value.replace(/\s+/g, ' ').trim();
      enEdition = null;
      saisie.remove();
      if (garder && valeur !== '' && valeur !== n.t) {
        if (neuve) {
          // La carte est déjà modifiée par l'ajout : le nom s'y ajoute sans nouvelle étape d'annulation.
          n.t = valeur;
          dessiner(true);
          programmer();
        } else {
          changer(function () { n.t = valeur; });
        }
      } else if (!garder && neuve) {
        annuler();
      }
      toile.querySelector('[data-id="' + dessin.noeuds.indexOf(selection) + '"]') && montrerSelection();
    };
    saisie.addEventListener('keydown', function (ev) {
      ev.stopPropagation();
      if (ev.key === 'Enter') { ev.preventDefault(); clore(true); }
      else if (ev.key === 'Escape') { ev.preventDefault(); clore(false); }
      else if (ev.key === 'Tab') { ev.preventDefault(); clore(true); }
    });
    saisie.addEventListener('blur', function () { clore(true); });
  };

  /** D'une idée à sa voisine de même niveau, dans l'ordre où elles sont dessinées. */
  var voisine = function (sens) {
    var memeNiveau = dessin.noeuds.filter(function (n) { return n._niveau === selection._niveau; });
    var i = memeNiveau.indexOf(selection) + sens;
    if (i >= 0 && i < memeNiveau.length) { selection = memeNiveau[i]; dessiner(true); montrerSelection(); }
  };

  var zoomer = function (facteur) {
    echelle = Math.max(0.3, Math.min(2.5, echelle * facteur));
    dessiner(true);
  };

  var ajuster = function () {
    var dispo = toile.clientWidth - 8;
    echelle = Math.max(0.3, Math.min(1, dispo / dessin.largeur));
    dessiner(true);
  };

  var telecharger = function () {
    var contenu = '<?xml version="1.0" encoding="UTF-8"?>\n' + svg(true);
    var lien = document.createElement('a');
    lien.href = URL.createObjectURL(new Blob([contenu], { type: 'image/svg+xml;charset=utf-8' }));
    lien.download = (racine.getAttribute('data-nom-fichier') || 'carte-mentale') + '.svg';
    document.body.appendChild(lien);
    lien.click();
    lien.remove();
    setTimeout(function () { URL.revokeObjectURL(lien.href); }, 1000);
  };

  /**
   * La carte en image dans la fiche de révision : le SVG est dessiné sur une toile (à deux fois sa taille, au plus 4 000
   * points de côté, pour rester nette), puis envoyé en PNG au serveur, qui le range avec les fichiers de la fiche.
   */
  var versLaFiche = function () {
    var bouton = barre.querySelector('[data-cm-action="fiche"]');
    var fini = function (texte) { if (bouton) { bouton.disabled = false; } dire(texte); };
    if (bouton) { bouton.disabled = true; }
    dire(mot('cm.image_envoi'));

    var adresse = URL.createObjectURL(new Blob([svg(true)], { type: 'image/svg+xml;charset=utf-8' }));
    var image = new Image();
    image.onerror = function () { URL.revokeObjectURL(adresse); fini(mot('cm.image_echec')); };
    image.onload = function () {
      var facteur = Math.min(2, 4000 / Math.max(dessin.largeur, dessin.hauteur));
      var toileImage = document.createElement('canvas');
      toileImage.width = Math.max(1, Math.round(dessin.largeur * facteur));
      toileImage.height = Math.max(1, Math.round(dessin.hauteur * facteur));
      toileImage.getContext('2d').drawImage(image, 0, 0, toileImage.width, toileImage.height);
      URL.revokeObjectURL(adresse);
      toileImage.toBlob(function (png) {
        if (!png) { fini(mot('cm.image_echec')); return; }
        var corps = new FormData();
        corps.append('_csrf', JETON);
        corps.append('image', png, 'carte-mentale.png');
        fetch(URL_IMAGE, { method: 'POST', body: corps, credentials: 'same-origin' })
          .then(function (r) {
            if (r.status !== 204) { throw new Error('http ' + r.status); }
            fini(mot('cm.image_ajoutee'));
            // Dans une fenêtre : la page derrière se recharge à la fermeture, et montre l'image dans la fiche.
            document.dispatchEvent(new Event('fenetre:changee'));
          })
          .catch(function () { fini(mot('cm.image_echec')); });
      }, 'image/png');
    };
    image.src = adresse;
  };

  var agir = function (action) {
    switch (action) {
      case 'enfant': ajouter(selection, null); break;
      case 'frere': if (parents.get(selection)) { ajouter(parents.get(selection), selection); } break;
      case 'renommer': renommer(selection, false); break;
      case 'replier': replier(); break;
      case 'monter': deplacer(-1); break;
      case 'descendre': deplacer(1); break;
      case 'supprimer': supprimer(); break;
      case 'annuler': annuler(); break;
      case 'zoom-moins': zoomer(0.8); break;
      case 'zoom-plus': zoomer(1.25); break;
      case 'ajuster': ajuster(); break;
      case 'image': telecharger(); break;
      case 'fiche': versLaFiche(); break;
    }
  };

  // --- Les événements --------------------------------------------------------------------------------------------
  barre.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-cm-action]');
    if (b && !b.disabled) { agir(b.getAttribute('data-cm-action')); }
  });

  var noeudDe = function (cible) {
    var g = cible.closest ? cible.closest('[data-id]') : null;
    return g ? dessin.noeuds[parseInt(g.getAttribute('data-id'), 10)] : null;
  };

  toile.addEventListener('click', function (ev) {
    var pli = ev.target.closest('[data-pli]');
    if (pli) { var cache = dessin.noeuds[parseInt(pli.getAttribute('data-pli'), 10)]; selection = cache; replier(cache); return; }
    var n = noeudDe(ev.target);
    if (n && n !== selection) { selection = n; dessiner(true); montrerSelection(); }
    else if (n) { montrerSelection(); }
  });

  toile.addEventListener('dblclick', function (ev) {
    var n = noeudDe(ev.target);
    if (n && !ev.target.closest('[data-pli]')) { selection = n; dessiner(true); renommer(n, false); }
  });

  toile.addEventListener('keydown', function (ev) {
    if (enEdition) { return; }
    var k = ev.key;
    if ((ev.ctrlKey || ev.metaKey) && k.toLowerCase() === 'z') { ev.preventDefault(); annuler(); return; }
    if (ev.ctrlKey || ev.metaKey || ev.altKey) { return; }
    if (k === 'Tab' && !ev.shiftKey) { ev.preventDefault(); agir('enfant'); }
    else if (k === 'Enter') { ev.preventDefault(); agir(selection === arbre ? 'enfant' : 'frere'); }
    else if (k === 'F2') { ev.preventDefault(); agir('renommer'); }
    else if (k === 'Delete' || k === 'Backspace') { ev.preventDefault(); agir('supprimer'); }
    else if (k === ' ') { ev.preventDefault(); agir('replier'); }
    else if (k === 'ArrowUp') { ev.preventDefault(); voisine(-1); }
    else if (k === 'ArrowDown') { ev.preventDefault(); voisine(1); }
    else if (k === 'ArrowLeft') { ev.preventDefault(); var p = parents.get(selection); if (p) { selection = p; dessiner(true); montrerSelection(); } }
    else if (k === 'ArrowRight') {
      ev.preventDefault();
      if (selection.c.length) {
        if (selection.p) { replier(); } else { selection = selection.c[0]; dessiner(true); montrerSelection(); }
      }
    }
  });

  if (champTitre) { champTitre.addEventListener('input', programmer); }

  // Fermer la fenêtre, ou quitter la page, avec une modification en route : on l'envoie tout de suite.
  document.addEventListener('click', function (ev) {
    if (modifie && document.body.contains(racine) && ev.target.closest && ev.target.closest('.fenetre__fermer')) { enregistrer(); }
  }, true);
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden' && modifie && document.body.contains(racine) && navigator.sendBeacon) {
      var corps = new FormData();
      corps.append('_csrf', JETON);
      corps.append('arbre', JSON.stringify(epure(arbre)));
      if (champTitre) { corps.append('titre', champTitre.value); }
      if (navigator.sendBeacon(URL_ENREGISTRER, corps)) { modifie = false; }
    }
  });

  // --- C'est parti -----------------------------------------------------------------------------------------------
  var numeroter = function (n) {
    n.c = Array.isArray(n.c) ? n.c : [];
    n.c.forEach(numeroter);
  };
  numeroter(arbre);

  barre.hidden = false;
  aide.hidden = false;
  toile.hidden = false;
  plan.hidden = true;
  dessiner(false);
  ajuster();
  };

  window.initialiserCarteMentale = function (zone) {
    Array.prototype.forEach.call((zone || document).querySelectorAll('[data-carte-mentale]'), initialiser);
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { window.initialiserCarteMentale(document); });
  } else {
    window.initialiserCarteMentale(document);
  }
})();
