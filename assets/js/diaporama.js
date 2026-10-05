/*
 * Le lecteur de diaporama commenté.
 *
 * Les diapositives viennent de la page (JSON dans data-diapos) : titre, points, commentaire, et si une voix Gemini
 * existe déjà pour elle. La lecture commentée dit le commentaire de chaque diapositive puis passe à la suivante :
 *   - avec la voix du navigateur (gratuite, instantanée, sans clé) — le commentaire est dit phrase par phrase, car
 *     certains navigateurs coupent une longue lecture ;
 *   - ou avec la voix Gemini, fabriquée diapositive par diapositive à la demande (le bouton appelle le serveur une
 *     fois par diapositive, ce qui montre l'avancement et permet de reprendre après une limite atteinte).
 * Sans voix du tout, le diaporama avance seul, au rythme d'une lecture.
 *
 * Comme pour la carte mentale, le contenu d'une fenêtre est posé par app.js, qui n'exécute pas les scripts d'un
 * fragment : ce fichier est chargé avec toutes les pages, et app.js appelle window.initialiserDiaporama(zone).
 */
(function () {
  'use strict';

  var MOTS = window.MOTS || {};
  var mot = function (cle, valeurs) {
    var phrase = Object.prototype.hasOwnProperty.call(MOTS, cle) ? MOTS[cle] : cle;
    Object.keys(valeurs || {}).forEach(function (nom) { phrase = phrase.split('{' + nom + '}').join(String(valeurs[nom])); });
    return phrase;
  };
  var LANGUES = { fr: 'fr-FR', en: 'en-GB', es: 'es-ES', de: 'de-DE' };

  // Les lecteurs ouverts : quand la fenêtre qui en contient un se ferme (son contenu disparaît), on le fait taire.
  var lecteurs = [];
  var surveillance = null;
  var surveiller = function (lecteur) {
    lecteurs.push(lecteur);
    if (surveillance || !window.MutationObserver) { return; }
    surveillance = new MutationObserver(function () {
      lecteurs = lecteurs.filter(function (l) {
        if (document.body.contains(l.racine)) { return true; }
        l.arreter();
        return false;
      });
      if (!lecteurs.length) { surveillance.disconnect(); surveillance = null; }
    });
    surveillance.observe(document.body, { childList: true, subtree: true });
  };

  var initialiser = function (racine) {
    if (racine.hasAttribute('data-dia-pret')) { return; }
    var diapos;
    try { diapos = JSON.parse(racine.getAttribute('data-diapos')); } catch (e) { return; }
    if (!Array.isArray(diapos) || !diapos.length) { return; }
    racine.setAttribute('data-dia-pret', '1');

    var scene = racine.querySelector('[data-dia-scene]');
    var barre = racine.querySelector('[data-dia-barre]');
    var compteur = racine.querySelector('[data-dia-compteur]');
    var titre = racine.querySelector('[data-dia-titre]');
    var points = racine.querySelector('[data-dia-points]');
    var transcription = racine.querySelector('[data-dia-trans]');
    var transTexte = racine.querySelector('[data-dia-trans-texte]');
    var transEtat = racine.querySelector('[data-dia-trans-etat]');
    var etat = racine.querySelector('[data-dia-etat]');
    var plan = racine.querySelector('[data-dia-plan]');
    var blocGemini = racine.querySelector('[data-dia-gemini]');
    var boutonLire = racine.querySelector('[data-dia-lire]');
    var boutonGenerer = racine.querySelector('[data-dia-generer]');
    var choixSource = racine.querySelector('[data-dia-source]');
    var choixVitesse = racine.querySelector('[data-dia-vitesse]');
    var choixVoixGemini = racine.querySelector('[data-dia-voix-gemini]');
    var boutonTranscription = racine.querySelector('[data-dia-action="transcription"]');

    var URL_AUDIO = racine.getAttribute('data-url-audio');
    var URL_VOIX = racine.getAttribute('data-url-voix');
    var JETON = racine.getAttribute('data-jeton');
    var LANGUE = LANGUES[racine.getAttribute('data-langue')] || 'fr-FR';
    var voixNavigateur = 'speechSynthesis' in window && typeof window.SpeechSynthesisUtterance === 'function';

    var i = 0;
    var lecture = false;
    var generation = 0;            // change à chaque (re)lancement : les fins de lectures périmées s'ignorent
    var minuteur = null;
    var veille = null;             // la voix du navigateur n'a pas démarré : on s'en passe
    var garde = null;              // et si elle ne se termine jamais : on passe à la suite
    var audio = new Audio();
    var transVisible = false;
    var phrasesDe = [];            // le commentaire de chaque diapositive, coupé en phrases (comme la voix le dit)
    var spans = [];                // et l'élément de la transcription qui porte chaque phrase
    var phraseCourante = null;
    var enFabrication = false;
    var arretDemande = false;

    var dire = function (texte) { etat.textContent = texte; };
    var nbVoix = function () { return diapos.filter(function (d) { return d.a; }).length; };

    // --- Afficher -----------------------------------------------------------------------------------------------
    var afficher = function () {
      var d = diapos[i];
      compteur.textContent = mot('dia.diapo', { i: i + 1, n: diapos.length });
      titre.textContent = d.t;
      points.innerHTML = '';
      d.p.forEach(function (p) {
        var li = document.createElement('li');
        li.textContent = p;
        points.appendChild(li);
      });
      majTranscription(true);
      racine.querySelector('[data-dia-action="precedent"]').disabled = i === 0;
      racine.querySelector('[data-dia-action="suivant"]').disabled = i === diapos.length - 1;
    };

    // --- La transcription : tout ce qui est dit, la phrase dite surlignée --------------------------------------
    var coupe = function (texte) { return texte.match(/[^.!?…]+[.!?…]*\s*/g) || (texte ? [texte] : []); };

    var construire = function () {
      transTexte.innerHTML = '';
      diapos.forEach(function (d, rang) {
        phrasesDe[rang] = coupe(d.c);
        spans[rang] = [];
        var section = document.createElement('section');
        section.className = 'diapo__trans-diapo';
        section.setAttribute('data-trans-diapo', String(rang));
        var entete = document.createElement('h3');
        entete.textContent = (rang + 1) + '. ' + d.t;
        section.appendChild(entete);
        var paragraphe = document.createElement('p');
        phrasesDe[rang].forEach(function (phrase, k) {
          var span = document.createElement('span');
          span.className = 'diapo__phrase';
          span.setAttribute('data-phrase', String(k));
          span.textContent = phrase;
          paragraphe.appendChild(span);
          spans[rang].push(span);
        });
        section.appendChild(paragraphe);
        transTexte.appendChild(section);
      });
    };

    /** Fait voir l'élément dans le cadre de la transcription, sans faire défiler la page ni la fenêtre. */
    var montrer = function (element, centrer) {
      if (!transVisible || !element) { return; }
      var haut = element.offsetTop, bas = haut + element.offsetHeight;
      if (centrer || haut < transTexte.scrollTop || bas > transTexte.scrollTop + transTexte.clientHeight) {
        transTexte.scrollTop = Math.max(0, haut - transTexte.clientHeight / 3);
      }
    };

    /** La diapositive en cours est marquée ; la phrase dite aussi (le temps de la dire). */
    var majTranscription = function (changementDeDiapo) {
      var sections = transTexte.children;
      for (var k = 0; k < sections.length; k++) {
        sections[k].classList.toggle('diapo__trans-diapo--courante', k === i);
      }
      if (changementDeDiapo) {
        if (phraseCourante) { phraseCourante.classList.remove('diapo__phrase--dite'); phraseCourante = null; }
        montrer(sections[i], true);
      }
    };

    var surligner = function (rangDiapo, k) {
      if (phraseCourante) { phraseCourante.classList.remove('diapo__phrase--dite'); }
      phraseCourante = spans[rangDiapo] && spans[rangDiapo][k] ? spans[rangDiapo][k] : null;
      if (phraseCourante) {
        phraseCourante.classList.add('diapo__phrase--dite');
        montrer(phraseCourante, false);
      }
    };

    /** Avec la voix Gemini, pas de repère par phrase : on le déduit de l'avancée dans le son (au prorata des lettres). */
    var phrasePourFraction = function (rangDiapo, fraction) {
      var total = diapos[rangDiapo].c.length || 1;
      var cible = fraction * total, cumul = 0;
      for (var k = 0; k < phrasesDe[rangDiapo].length; k++) {
        cumul += phrasesDe[rangDiapo][k].length;
        if (cible < cumul) { return k; }
      }
      return Math.max(0, phrasesDe[rangDiapo].length - 1);
    };

    var texteComplet = function () {
      return (racine.getAttribute('data-titre') || '') + '\n\n'
        + diapos.map(function (d, k) { return (k + 1) + '. ' + d.t + '\n' + d.c; }).join('\n\n') + '\n';
    };

    var copier = function () {
      var texte = texteComplet();
      var reussi = function () { transEtat.textContent = mot('dia.copie'); };
      var repli = function () {
        // Sans l'API du presse-papiers (page non sécurisée, autorisation refusée) : la copie « à l'ancienne ».
        var zone = document.createElement('textarea');
        zone.value = texte;
        zone.setAttribute('readonly', '');
        zone.style.position = 'fixed';
        zone.style.opacity = '0';
        document.body.appendChild(zone);
        zone.select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        zone.remove();
        transEtat.textContent = ok ? mot('dia.copie') : mot('dia.copie_echec');
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(texte).then(reussi, repli);
      } else {
        repli();
      }
    };

    var telecharger = function () {
      // Un BOM en tête : le Bloc-notes de Windows lit ainsi les accents sans se tromper d'encodage.
      var fichier = new Blob(['\ufeff' + texteComplet()], { type: 'text/plain;charset=utf-8' });
      var lien = document.createElement('a');
      lien.href = URL.createObjectURL(fichier);
      lien.download = (racine.getAttribute('data-nom-fichier') || 'diaporama') + '-' + mot('dia.nom_transcription') + '.txt';
      document.body.appendChild(lien);
      lien.click();
      lien.remove();
      setTimeout(function () { URL.revokeObjectURL(lien.href); }, 1000);
      transEtat.textContent = mot('dia.telecharge');
    };

    // --- Lire ---------------------------------------------------------------------------------------------------
    var majBouton = function () {
      boutonLire.textContent = lecture ? boutonLire.getAttribute('data-texte-pause') : boutonLire.getAttribute('data-texte-lire');
      boutonLire.setAttribute('aria-pressed', lecture ? 'true' : 'false');
    };

    var taire = function () {
      generation++;
      clearTimeout(minuteur);
      clearTimeout(veille);
      clearTimeout(garde);
      audio.onended = null;
      audio.onerror = null;
      audio.ontimeupdate = null;
      audio.onloadedmetadata = null;
      audio.pause();
      if (voixNavigateur) { window.speechSynthesis.cancel(); }
    };

    var arreter = function () {
      lecture = false;
      taire();
      majBouton();
    };

    var suite = function () {
      if (!lecture) { return; }
      if (i < diapos.length - 1) {
        i++;
        afficher();
        jouer();
      } else {
        arreter();
        dire(mot('dia.fin'));
      }
    };

    /** Sans voix : on laisse le temps de lire (environ 150 mots à la minute). */
    var attendre = function (jeton) {
      var mots = diapos[i].c.split(/\s+/).length;
      var duree = Math.max(3, mots / (2.5 * (parseFloat(choixVitesse.value) || 1)));
      minuteur = setTimeout(function () { if (jeton === generation) { suite(); } }, duree * 1000);
    };

    var direAuNavigateur = function (jeton, debut) {
      var vitesse = parseFloat(choixVitesse.value) || 1;
      var phrases = phrasesDe[i].length ? phrasesDe[i] : [diapos[i].c];
      var depart = Math.max(0, Math.min(phrases.length - 1, debut || 0));
      var mots = diapos[i].c.split(/\s+/).length;
      var demarre = false;
      window.speechSynthesis.cancel();
      // Un navigateur sans voix installée accepte la demande et ne dit rien, sans jamais finir : si rien ne démarre en
      // trois secondes, le diaporama avance seul ; et une lecture qui dépasse largement sa durée probable est coupée.
      veille = setTimeout(function () {
        if (jeton !== generation || demarre) { return; }
        window.speechSynthesis.cancel();
        dire(mot('dia.pas_de_voix'));
        attendre(jeton);
      }, 3000);
      garde = setTimeout(function () {
        if (jeton === generation) { window.speechSynthesis.cancel(); suite(); }
      }, (2 * mots / (2.5 * vitesse) + 15) * 1000);
      phrases.forEach(function (phrase, rang) {
        if (rang < depart) { return; }
        var u = new window.SpeechSynthesisUtterance(phrase);
        u.lang = LANGUE;
        u.rate = vitesse;
        u.onstart = function () { demarre = true; if (jeton === generation) { surligner(i, rang); } };
        if (rang === phrases.length - 1) {
          u.onend = function () { if (jeton === generation) { suite(); } };
        }
        u.onerror = function (ev) {
          // « canceled » / « interrupted » : c'est nous qui avons coupé (changement de diapositive, pause).
          if (jeton !== generation || ev.error === 'canceled' || ev.error === 'interrupted') { return; }
          attendre(jeton);
        };
        window.speechSynthesis.speak(u);
      });
    };

    var jouer = function (debut) {
      taire();
      var jeton = generation;
      var d = diapos[i];
      var vitesse = parseFloat(choixVitesse.value) || 1;
      if (choixSource.value === 'gemini' && d.a) {
        audio.src = URL_AUDIO + '/' + i;
        audio.playbackRate = vitesse;
        audio.onended = function () { if (jeton === generation) { suite(); } };
        audio.ontimeupdate = function () {
          if (jeton === generation && audio.duration) { surligner(i, phrasePourFraction(i, audio.currentTime / audio.duration)); }
        };
        // Partir d'une phrase : on se place au prorata des lettres qui la précèdent, dès que la durée est connue.
        if (debut) {
          audio.onloadedmetadata = function () {
            var avant = 0;
            for (var k = 0; k < debut; k++) { avant += phrasesDe[i][k].length; }
            audio.currentTime = audio.duration * avant / (diapos[i].c.length || 1);
          };
        }
        var repli = function () {
          if (jeton !== generation) { return; }
          if (voixNavigateur) { direAuNavigateur(jeton, debut); } else { attendre(jeton); }
        };
        audio.onerror = repli;
        var essai = audio.play();
        if (essai && essai.catch) { essai.catch(repli); }
        return;
      }
      if (voixNavigateur && d.c) { direAuNavigateur(jeton, debut); return; }
      dire(mot('dia.pas_de_voix'));
      attendre(jeton);
    };

    var aller = function (rang) {
      var neuf = Math.max(0, Math.min(diapos.length - 1, rang));
      if (neuf === i) { return; }
      i = neuf;
      afficher();
      if (lecture) { jouer(); }
    };

    // --- La voix Gemini, une diapositive à la fois ----------------------------------------------------------------
    var majSource = function () {
      var option = choixSource.querySelector('option[value="gemini"]');
      if (option) { option.disabled = nbVoix() === 0; }
    };

    var fabriquer = function () {
      if (enFabrication) { arretDemande = true; return; }
      var voix = choixVoixGemini ? choixVoixGemini.value : '';
      // Celles qui manquent ; si elles sont toutes faites, on les refait toutes (avec une autre voix, par exemple).
      var liste = [];
      diapos.forEach(function (d, rang) { if (!d.a) { liste.push(rang); } });
      if (!liste.length) { diapos.forEach(function (d, rang) { liste.push(rang); }); }

      enFabrication = true;
      arretDemande = false;
      boutonGenerer.textContent = mot('dia.voix_arreter');

      var fini = function (message) {
        enFabrication = false;
        boutonGenerer.textContent = nbVoix() === diapos.length && !message ? mot('dia.voix_refaire') : mot('dia.voix_reprendre');
        majSource();
        if (message) { dire(message); }
      };
      var etape = function (k) {
        if (arretDemande) { fini(mot('dia.voix_interrompue', { i: k, n: liste.length })); return; }
        if (k >= liste.length) {
          choixSource.value = 'gemini';
          fini('');
          dire(mot('dia.voix_prete'));
          return;
        }
        dire(mot('dia.voix_en_cours', { i: k + 1, n: liste.length }));
        var corps = new FormData();
        corps.append('_csrf', JETON);
        corps.append('n', String(liste[k]));
        corps.append('voix', voix);
        fetch(URL_VOIX, { method: 'POST', body: corps, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            if (!j || !j.ok) {
              fini(mot('dia.voix_arret', { i: liste[k] + 1, detail: (j && j.message) || '' }));
              return;
            }
            diapos[liste[k]].a = true;
            majSource();
            etape(k + 1);
          })
          .catch(function () { fini(mot('dia.voix_arret', { i: liste[k] + 1, detail: mot('dia.erreur_reseau') })); });
      };
      etape(0);
    };

    // --- Les gestes ---------------------------------------------------------------------------------------------
    barre.addEventListener('click', function (ev) {
      var bouton = ev.target.closest ? ev.target.closest('[data-dia-action]') : null;
      if (!bouton) { return; }
      var action = bouton.getAttribute('data-dia-action');
      if (action === 'precedent') { aller(i - 1); }
      else if (action === 'suivant') { aller(i + 1); }
      else if (action === 'lire') {
        if (lecture) { arreter(); }
        else { lecture = true; majBouton(); dire(''); jouer(); }
      } else if (action === 'transcription') {
        transVisible = !transVisible;
        boutonTranscription.setAttribute('aria-pressed', transVisible ? 'true' : 'false');
        transcription.hidden = !transVisible;
        majTranscription(true);
        if (transVisible && phraseCourante) { montrer(phraseCourante, true); }
      }
    });
    transcription.addEventListener('click', function (ev) {
      var bouton = ev.target.closest ? ev.target.closest('[data-dia-action]') : null;
      if (bouton) {
        var geste = bouton.getAttribute('data-dia-action');
        if (geste === 'copier') { copier(); } else if (geste === 'telecharger') { telecharger(); }
        return;
      }
      // Un clic sur une phrase : on va à sa diapositive, et si la lecture est en cours, elle repart de cette phrase.
      var phrase = ev.target.closest ? ev.target.closest('[data-phrase]') : null;
      var section = ev.target.closest ? ev.target.closest('[data-trans-diapo]') : null;
      if (!section) { return; }
      var rangDiapo = parseInt(section.getAttribute('data-trans-diapo'), 10);
      var k = phrase ? parseInt(phrase.getAttribute('data-phrase'), 10) : 0;
      var change = rangDiapo !== i;
      i = rangDiapo;
      afficher();
      if (lecture) { jouer(k); } else if (!change) { surligner(i, k); }
      if (!lecture && change) { surligner(i, k); }
    });
    if (boutonGenerer) { boutonGenerer.addEventListener('click', fabriquer); }
    choixSource.addEventListener('change', function () { if (lecture) { jouer(); } });
    choixVitesse.addEventListener('change', function () { if (lecture) { jouer(); } });

    racine.addEventListener('keydown', function (ev) {
      if (ev.ctrlKey || ev.metaKey || ev.altKey) { return; }
      var cible = ev.target;
      if (cible && (cible.tagName === 'SELECT' || cible.tagName === 'INPUT' || cible.tagName === 'TEXTAREA')) { return; }
      if (ev.key === 'ArrowRight') { ev.preventDefault(); aller(i + 1); }
      else if (ev.key === 'ArrowLeft') { ev.preventDefault(); aller(i - 1); }
      else if (ev.key === ' ' && cible === racine) { ev.preventDefault(); boutonLire.click(); }
    });
    window.addEventListener('pagehide', arreter);

    // --- C'est parti --------------------------------------------------------------------------------------------
    if (boutonGenerer && nbVoix() > 0) {
      boutonGenerer.textContent = nbVoix() === diapos.length ? mot('dia.voix_refaire') : mot('dia.voix_reprendre');
    }
    majSource();
    // La voix Gemini d'abord, quand elle existe pour toutes les diapositives ; sinon celle du navigateur.
    choixSource.value = nbVoix() === diapos.length ? 'gemini' : 'navigateur';
    construire();
    racine.setAttribute('tabindex', '0');
    scene.hidden = false;
    barre.hidden = false;
    if (blocGemini) { blocGemini.hidden = false; }
    plan.hidden = true;
    afficher();
    majBouton();
    surveiller({ racine: racine, arreter: function () { arreter(); arretDemande = true; } });
  };

  window.initialiserDiaporama = function (zone) {
    Array.prototype.forEach.call((zone || document).querySelectorAll('[data-diaporama]'), initialiser);
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { window.initialiserDiaporama(document); });
  } else {
    window.initialiserDiaporama(document);
  }
})();
