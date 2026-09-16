/* Interactions légères — l'application fonctionne aussi sans JavaScript. */
(function () {
  'use strict';

  /*
   * Remonter en haut, et voir d'un coup d'œil où l'on en est.
   *
   * L'anneau autour de la flèche se remplit à mesure qu'on descend. C'est la
   * même information que la barre de défilement, mais à l'endroit où l'on
   * regarde déjà quand on cherche à remonter.
   *
   * Le lien fonctionne sans nous — il saute à l'ancre du haut de page. On ne
   * s'en mêle que pour glisser plutôt que sauter, et pour ne pas laisser
   * « #haut » derrière nous dans la barre d'adresse.
   */
  var haut = document.querySelector('.haut-de-page');
  if (haut) {
    var part = haut.querySelector('.haut-de-page__part');
    var tour = 2 * Math.PI * 20;          // le rayon du cercle, dans le SVG
    var enAttente = false;

    var suivreLeDefilement = function () {
      enAttente = false;
      var doc = document.documentElement;
      var course = doc.scrollHeight - doc.clientHeight;
      var y = window.pageYOffset || doc.scrollTop || 0;

      // Une page qui tient dans l'écran n'a pas de haut à retrouver, et les
      // premiers pixels ne valent pas qu'on encombre le coin de l'écran.
      haut.classList.toggle('haut-de-page--efface', course < 240 || y < 120);

      var avance = course > 0 ? Math.min(1, Math.max(0, y / course)) : 0;
      part.style.strokeDashoffset = (tour * (1 - avance)).toFixed(2);
    };

    // Le défilement se déclenche bien plus souvent que l'écran ne se redessine.
    var demanderLeSuivi = function () {
      if (enAttente) { return; }
      enAttente = true;
      if (window.requestAnimationFrame) {
        window.requestAnimationFrame(suivreLeDefilement);
      } else {
        window.setTimeout(suivreLeDefilement, 60);
      }
    };

    part.style.strokeDasharray = tour.toFixed(2);
    window.addEventListener('scroll', demanderLeSuivi, { passive: true });
    window.addEventListener('resize', demanderLeSuivi);
    suivreLeDefilement();

    haut.addEventListener('click', function (evenement) {
      if (!window.scrollTo) { return; }   // le saut d'ancre fera l'affaire
      evenement.preventDefault();

      var brusque = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      try {
        window.scrollTo({ top: 0, behavior: brusque ? 'auto' : 'smooth' });
      } catch (e) {
        window.scrollTo(0, 0);            // les navigateurs d'avant l'objet
      }
    });
  }

  // Menu mobile
  var burger = document.querySelector('.burger');
  var nav = document.getElementById('navigation');
  if (burger && nav) {
    burger.addEventListener('click', function () {
      var ouvert = nav.classList.toggle('est-ouvert');
      burger.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    });
  }

  /*
   * Réserver la hauteur de l'en-tête pour les liens qui mènent à une ancre.
   *
   * L'en-tête reste collé en haut de la page, et un saut d'ancre pose sa
   * cible à zéro pixel du haut : elle se retrouve dessous, invisible. La
   * feuille de style en réserve une hauteur par défaut, mais l'en-tête n'a
   * pas toujours la même : passé une certaine largeur, la barre de
   * navigation passe à la ligne et il triple de hauteur. On le mesure donc,
   * et on tient le réglage à jour quand la fenêtre change de taille.
   */
  var sousLentete = function () {
    var entete = document.querySelector('.entete');
    if (!entete || getComputedStyle(entete).position !== 'sticky') { return 20; }

    return Math.round(entete.getBoundingClientRect().height) + 20;
  };
  var reserverLentete = function () {
    document.documentElement.style.scrollPaddingTop = sousLentete() + 'px';
  };
  reserverLentete();
  window.addEventListener('resize', reserverLentete);

  /*
   * Le sommaire de l'aperçu : amener le titre visé juste sous l'en-tête.
   *
   * Le saut du navigateur suffirait, maintenant qu'il sait quelle hauteur
   * réserver ; on le refait tout de même à la main, pour que le titre se
   * pose au même endroit quel que soit le navigateur.
   */
  var sommaireApercu = document.querySelector('[data-apercu-sommaire]');
  if (sommaireApercu) {
    sommaireApercu.addEventListener('click', function (evenement) {
      var lien = evenement.target.closest && evenement.target.closest('a[href^="#"]');
      if (!lien) { return; }

      var nom = decodeURIComponent(lien.getAttribute('href').slice(1));
      var titre = document.getElementById(nom);
      if (!titre) { return; }

      evenement.preventDefault();

      // Où s'arrêter, mesuré avant de bouger : une position dans la page,
      // que le saut du navigateur ne changera pas.
      var haut = Math.max(0, Math.round(
        titre.getBoundingClientRect().top + window.pageYOffset - sousLentete()
      ));

      /*
       * L'ancre est posée dans l'adresse, et non par « pushState » : c'est
       * elle qui marque le titre visé, et tous les navigateurs ne relisent
       * pas la marque quand l'adresse change sans saut.
       */
      window.location.hash = nom;
      window.scrollTo(0, haut);
    });
  }

  /*
   * Les fenêtres de confirmation : un bouton « data-ouvrir-dialogue » ouvre
   * la fenêtre qu'il nomme, par-dessus tout le reste — même une autre
   * fenêtre. Écoutées sur le document, elles marchent aussi dans un contenu
   * chargé après coup. Seuls leur croix et « Annuler » les ferment ; le
   * curseur part sur « Annuler », pour qu'un Entrée distrait ne confirme rien.
   */
  document.addEventListener('click', function (evenement) {
    var cible = evenement.target;
    if (!cible || !cible.closest) { return; }
    var ouvrir = cible.closest('[data-ouvrir-dialogue]');
    if (ouvrir) {
      var dialogue = document.getElementById(ouvrir.getAttribute('data-ouvrir-dialogue'));
      if (dialogue && typeof dialogue.showModal === 'function') {
        evenement.preventDefault();
        dialogue.showModal();
        var annuler = dialogue.querySelector('.confirmation__choix [data-fermer-dialogue]');
        if (annuler) { annuler.focus(); }
      }
      return;
    }
    var fermer = cible.closest('[data-fermer-dialogue]');
    if (fermer && fermer.closest('dialog')) {
      fermer.closest('dialog').close();
    }
  });
  document.addEventListener('cancel', function (evenement) {
    if (evenement.target.matches && evenement.target.matches('[data-confirmation-dialogue]')) { evenement.preventDefault(); }
  }, true);

  // Confirmation avant les suppressions
  document.addEventListener('submit', function (evenement) {
    var formulaire = evenement.target;
    var message = formulaire.getAttribute('data-confirmation');
    if (message && !window.confirm(message)) {
      evenement.preventDefault();
    }
  });

  // Formulaire d'évènement : masquer les heures si « journée entière »
  var caseJournee = document.getElementById('journee_entiere');
  var blocHeures = document.getElementById('bloc-heures');
  if (caseJournee && blocHeures) {
    var majHeures = function () {
      blocHeures.hidden = caseJournee.checked;
    };
    caseJournee.addEventListener('change', majHeures);
    majHeures();
  }

  // La date de fin suit la date de début tant qu'elles sont identiques
  var dateDebut = document.getElementById('date_debut');
  var dateFin = document.getElementById('date_fin');
  if (dateDebut && dateFin) {
    var ancienneValeur = dateDebut.value;
    dateDebut.addEventListener('change', function () {
      if (dateFin.value === ancienneValeur || dateFin.value === '') {
        dateFin.value = dateDebut.value;
      }
      ancienneValeur = dateDebut.value;
    });
  }

  // Les filtres s'appliquent dès qu'on change une valeur — listes déroulantes
  // comme cases à cocher, et sur chaque formulaire qui le demande, non plus
  // seulement le premier : le calendrier en a deux depuis le volet des agendas.
  /*
   * Ce qui s'ouvre dans une fenêtre, sans quitter la page.
   *
   * La fiche d'un évènement, le formulaire qui le crée, celui qui le modifie.
   * Le lien reste un lien : sans script il mène à la page correspondante, et
   * tout y fonctionne. Le script se contente de l'intercepter, d'aller
   * chercher la même chose en fragment, et de la poser dans une « dialog ».
   *
   * « dialog » plutôt qu'un bloc à nous : le navigateur s'occupe du fond
   * grisé et du clavier qui ne doit pas s'échapper derrière la fenêtre.
   *
   * L'écoute est posée sur le document et non sur chaque lien : le bouton
   * « Modifier » de la fiche n'existe pas au chargement de la page, il arrive
   * dans la fenêtre. Un lien qui naît là doit s'ouvrir comme les autres.
   */
  // Le message d'un enregistrement fait dans une fenêtre, qui a rechargé la page.
  try {
    var messagesGardes = sessionStorage.getItem('mesCoursMessages');
    var contenuPage = document.getElementById('contenu');
    if (messagesGardes && contenuPage) {
      sessionStorage.removeItem('mesCoursMessages');
      var accueil = document.createElement('div');
      accueil.innerHTML = messagesGardes;
      var bloc = accueil.querySelector('.flashs');
      if (bloc && !contenuPage.querySelector(':scope > .flashs')) {
        contenuPage.insertBefore(bloc, contenuPage.firstChild);
      }
    }
  } catch (e) { /* rien de gardé, ou stockage refusé */ }

  if (typeof HTMLDialogElement === 'function') {
    var fenetre = document.createElement('dialog');
    fenetre.className = 'fenetre';
    fenetre.innerHTML =
      '<button class="fenetre__retour" type="button" aria-label="Revenir" title="Revenir" hidden>←</button>' +
      '<button class="fenetre__fermer" type="button" aria-label="Fermer">✕</button>' +
      '<div class="fenetre__corps"></div>';
    document.body.appendChild(fenetre);

    var corps = fenetre.querySelector('.fenetre__corps');

    /*
     * Un document modifié et pas encore enregistré ne se referme pas sans
     * qu'on le confirme : ni la croix, ni Échap, ni un clic à côté, ni un lien
     * qui remplacerait l'éditeur.
     */
    var peutQuitter = function () {
      if (corps.querySelector('form[data-modifie]') === null) { return true; }
      return window.confirm('Fermer sans enregistrer ? Les modifications du document seront perdues.');
    };
    /*
     * Le chemin parcouru dans la fenêtre : un cours, puis l'aperçu d'un de ses
     * fichiers, puis l'éditeur. Le bouton de retour remonte d'un cran ; revenir
     * à une page déjà ouverte y ramène le chemin, pour qu'on ne tourne pas en
     * rond entre l'aperçu et l'éditeur.
     */
    var historique = [];
    var boutonRetour = fenetre.querySelector('.fenetre__retour');
    var sansFenetre = function (adresse) {
      var lien = new URL(adresse, window.location.href);
      lien.searchParams.delete('fenetre');
      return lien.pathname + lien.search;
    };
    var majRetour = function () { boutonRetour.hidden = historique.length < 2; };

    /*
     * Ce qui a été enregistré depuis la fenêtre change la page derrière elle :
     * un cours renommé, un favori, un fichier de plus. On la recharge quand la
     * fenêtre se ferme, pour qu'elle dise la même chose.
     */
    var aChange = false;


    var fermer = function () {
      if (!peutQuitter()) { return; }
      corps.innerHTML = '';
      historique = [];
      majRetour();
      fenetre.close();
      if (aChange) {
        aChange = false;
        window.location.reload();
      }
    };

    // Quelque chose a changé dans la fenêtre sans passer par un formulaire.
    document.addEventListener('fenetre:changee', function () { aChange = true; });

    // Une séance de cartes terminée dans la fenêtre : on y relit la fiche, à jour.
    document.addEventListener('fenetre:relire', function (evenement) {
      // Ce qui se relit ailleurs — dans la fenêtre du dessus — ne la regarde pas.
      if (evenement.target !== document && !fenetre.contains(evenement.target)) { return; }
      if (!fenetre.open || historique.length === 0) { return; }
      aChange = true;
      ouvrir(historique[historique.length - 1]);
    });

    boutonRetour.addEventListener('click', function () {
      if (historique.length > 1) { ouvrir(historique[historique.length - 2]); }
    });
    /*
     * Une fenêtre ne se quitte que par sa croix (ou par un bouton qui le dit,
     * « Annuler », « Retour ») : ni un clic à côté, ni la touche Échap, qui
     * perdraient d'un geste ce qu'on était en train de lire ou d'écrire.
     *
     * « dialog » se ferme d'elle-même sur Échap : on la retient.
     */
    fenetre.addEventListener('cancel', function (evenement) {
      evenement.preventDefault();
    });

    fenetre.querySelector('.fenetre__fermer').addEventListener('click', fermer);

    // Et certains navigateurs la ferment sur Échap sans passer par « cancel ».
    fenetre.addEventListener('keydown', function (evenement) {
      if (evenement.key === 'Escape') { evenement.preventDefault(); }
    });

    fenetre.addEventListener('click', function (evenement) {
      /*
       * Le sommaire d'un aperçu ouvert en fenêtre : c'est la fenêtre qui
       * défile, pas la page derrière. On amène le titre en haut de la
       * fenêtre, sans toucher à l'adresse de la page — elle est celle du
       * cours, qu'une ancre d'aperçu n'a rien à y faire.
       */
      var lien = evenement.target.closest && evenement.target.closest('[data-apercu-sommaire] a[href^="#"]');
      if (!lien) { return; }
      var titre = document.getElementById(decodeURIComponent(lien.getAttribute('href').slice(1)));
      if (!titre || !corps.contains(titre)) { return; }
      evenement.preventDefault();
      corps.scrollTo({
        top: titre.getBoundingClientRect().top - corps.getBoundingClientRect().top + corps.scrollTop - 12,
        behavior: 'smooth'
      });
    });

    var poser = function (html) {
        corps.innerHTML = html;

        // Le contenu annonce la place qu'il lui faut : un formulaire tient sur
        // deux colonnes, une fiche de six lignes se lit mieux étroite.
        fenetre.classList.toggle('fenetre--large', corps.querySelector('[data-large]') !== null);
        // Un document à lire prend toute la place que l'écran lui laisse.
        fenetre.classList.toggle('fenetre--document', corps.querySelector('[data-document]') !== null);

        /*
         * Remplacer le contenu emporte l'élément qui avait le focus, et le
         * clavier retombe sur la page derrière : la touche d'échappement ne
         * ferme plus rien. On le ramène dans la fenêtre — sur le premier champ
         * s'il y en a un, puisque c'est là qu'on allait.
         *
         * Sans faire défiler : donner le focus amène l'élément sous les yeux,
         * et sur une page de réglages le premier champ est au milieu. On
         * arrivait donc à mi-hauteur, sans avoir rien demandé. Le clavier va
         * où il doit, la fenêtre reste en haut.
         */
        corps.scrollTop = 0;

        /*
         * Et seulement s'il est sous les yeux. Sur une page de réglages, le
         * premier champ est au milieu : y poser le curseur laisserait le
         * clavier quelque part qu'on ne voit pas, où une frappe changerait un
         * réglage sans qu'on l'ait cherché. La croix, elle, est toujours là.
         */
        var premier = corps.querySelector('input:not([type="hidden"]), textarea, select');
        var aPortee = premier !== null && premier.offsetTop < 200;
        (aPortee ? premier : fenetre.querySelector('.fenetre__fermer'))
          .focus({ preventScroll: true });

        // Ce qui s'anime dans ce qui vient d'arriver : l'éditeur de document,
        // le dépôt de fichiers, la fiche de révision et sa séance de cartes.
        initialiserEditeur(corps);
        initialiserDepots(corps);
        initialiserTexteRiche(corps);
        initialiserNotifications(corps);
        initialiserFiche(corps);
        initialiserSeance(corps);
    };

    var ouvrir = function (adresse) {
      if (fenetre.open && !peutQuitter()) { return; }
      var cle = sansFenetre(adresse);
      if (!fenetre.open) { historique = []; }
      var deja = historique.indexOf(cle);
      if (deja >= 0) { historique = historique.slice(0, deja + 1); } else { historique.push(cle); }
      majRetour();
      corps.innerHTML = '<p class="discret" style="padding:1rem">Un instant…</p>';
      if (!fenetre.open) { fenetre.showModal(); }

      fetch(adresse + (adresse.indexOf('?') === -1 ? '?' : '&') + 'fenetre=1', {
        credentials: 'same-origin'
      }).then(function (reponse) {
        if (!reponse.ok) { throw new Error('refus'); }
        return reponse.text();
      }).then(poser).catch(function () {
        // Plutôt que d'expliquer un échec qu'on ne sait pas nommer, on fait
        // ce que le lien aurait fait sans nous.
        window.location.href = adresse;
      });
    };

    /*
     * Un formulaire qui s'enregistre sans quitter la fenêtre.
     *
     * L'envoi est celui du formulaire, fichiers compris, marqué « fenetre » :
     * le serveur répond par un fragment — l'aperçu mis à jour, ou l'éditeur
     * avec son message d'erreur — qui prend la place du formulaire. Les
     * écoutes du formulaire lui-même sont passées avant celle-ci : le texte
     * mis en forme est déjà recopié dans les champs qui partent.
     */
    corps.addEventListener('submit', function (evenement) {
      var formulaire = evenement.target;
      if (evenement.defaultPrevented || !formulaire.hasAttribute('data-envoi-fenetre')) { return; }
      evenement.preventDefault();
      // La confirmation d'une suppression se demande ici, et une seule fois :
      // l'écoute générale, plus haut dans la page, n'a pas à la reposer.
      evenement.stopPropagation();
      var confirmation = formulaire.getAttribute('data-confirmation');
      if (confirmation && !window.confirm(confirmation)) { return; }

      var donnees = new FormData(formulaire);
      donnees.append('fenetre', '1');
      var bouton = formulaire.querySelector('button[type="submit"]');
      if (bouton) { bouton.disabled = true; bouton.textContent = 'Enregistrement…'; }

      fetch(formulaire.action, { method: 'POST', body: donnees, credentials: 'same-origin' })
        .then(function (reponse) {
          if (!reponse.ok) { throw new Error('refus'); }
          return reponse.text().then(function (html) { return { html: html, adresse: reponse.url }; });
        })
        .then(function (reponse) {
          aChange = true;
          var cle = sansFenetre(reponse.adresse);

          // Le message du serveur est déjà arrivé dans la réponse, et ne sera
          // plus redonné : il est gardé le temps du chargement, pour s'afficher
          // sur la page où l'on va. Lu sans l'insérer, rien ne se télécharge.
          var garderLesMessages = function () {
            try {
              var recu = new DOMParser().parseFromString(reponse.html, 'text/html');
              var messages = recu.querySelector('.flashs');
              if (messages) { sessionStorage.setItem('mesCoursMessages', messages.outerHTML); }
            } catch (e) { /* stockage refusé : le message sera perdu, pas l'enregistrement */ }
          };

          // Une page entière n'a rien à faire dans la fenêtre : on y va —
          // la page même qu'on regardait, quand on y revient (« + Tâche »).
          if (/<header class="entete"/.test(reponse.html)) {
            garderLesMessages();
            if (cle === sansFenetre(window.location.href)) {
              window.location.reload();
            } else {
              window.location.href = cle;
            }
            return;
          }
          // La page qu'on regarde derrière la fenêtre : c'est elle qui a changé.
          if (cle === sansFenetre(window.location.href)) {
            garderLesMessages();
            window.location.reload();
            return;
          }

          var deja = historique.indexOf(cle);
          if (deja >= 0) {
            historique = historique.slice(0, deja + 1);
          } else {
            historique[Math.max(0, historique.length - 1)] = cle;
          }
          majRetour();
          poser(reponse.html);
        })
        .catch(function () {
          // Faute de mieux, l'envoi ordinaire : la page entière s'en chargera.
          formulaire.removeAttribute('data-envoi-fenetre');
          formulaire.submit();
        });
    });

    document.addEventListener('click', function (evenement) {
      // Un clic du milieu, ou avec une touche tenue, ouvre un onglet : ce
      // n'est pas à nous de le contrarier.
      if (evenement.metaKey || evenement.ctrlKey || evenement.shiftKey
          || evenement.altKey || evenement.button !== 0) {
        return;
      }

      var fermeture = evenement.target.closest('[data-fermer]');
      if (fermeture !== null && fenetre.open) {
        evenement.preventDefault();
        fermer();

        return;
      }

      var lien = evenement.target.closest('[data-fenetre]');
      if (lien === null) { return; }

      evenement.preventDefault();
      ouvrir(lien.getAttribute('href'));
    });

    /*
     * La fenêtre du dessus.
     *
     * Un lien « data-fenetre-dessus » s'ouvre par-dessus ce qu'on est en train
     * de faire, sans le remplacer : « Régler les notifications » depuis le
     * formulaire d'un évènement laisse l'évènement tel qu'on l'a écrit, et la
     * croix y ramène. Une seule page à la fois, pas de chemin : c'est un
     * détour, pas une navigation.
     */
    var dessus = document.createElement('dialog');
    dessus.className = 'fenetre fenetre--dessus';
    dessus.innerHTML =
      '<button class="fenetre__fermer" type="button" aria-label="Fermer">✕</button>' +
      '<div class="fenetre__corps"></div>';
    document.body.appendChild(dessus);
    var corpsDessus = dessus.querySelector('.fenetre__corps');
    var adresseDessus = null;

    var poserDessus = function (html) {
      corpsDessus.innerHTML = html;
      dessus.classList.toggle('fenetre--large', corpsDessus.querySelector('[data-large]') !== null);
      corpsDessus.scrollTop = 0;
      dessus.querySelector('.fenetre__fermer').focus({ preventScroll: true });
      initialiserTexteRiche(corpsDessus);
      initialiserNotifications(corpsDessus);
    };
    var ouvrirDessus = function (adresse) {
      adresseDessus = adresse;
      corpsDessus.innerHTML = '<p class="discret" style="padding:1rem">Un instant…</p>';
      if (!dessus.open) { dessus.showModal(); }
      fetch(adresse + (adresse.indexOf('?') === -1 ? '?' : '&') + 'fenetre=1', { credentials: 'same-origin' })
        .then(function (reponse) {
          if (!reponse.ok) { throw new Error('refus'); }
          return reponse.text();
        })
        .then(poserDessus)
        .catch(function () {
          // Faute de mieux, un onglet : ce qu'on écrivait dessous reste intact.
          dessus.close();
          window.open(adresse, '_blank', 'noopener');
        });
    };
    var fermerDessus = function () {
      corpsDessus.innerHTML = '';
      adresseDessus = null;
      dessus.close();
    };

    // Comme l'autre : la croix seule la ferme.
    dessus.addEventListener('cancel', function (evenement) { evenement.preventDefault(); });
    dessus.addEventListener('keydown', function (evenement) {
      if (evenement.key === 'Escape') { evenement.preventDefault(); }
    });
    dessus.querySelector('.fenetre__fermer').addEventListener('click', fermerDessus);

    // Activer ou désactiver les notifications : c'est elle qui se relit.
    dessus.addEventListener('fenetre:relire', function (evenement) {
      evenement.stopPropagation();
      if (dessus.open && adresseDessus !== null) { ouvrirDessus(adresseDessus); }
    });

    // Un formulaire marqué pour la fenêtre s'y enregistre, et la page y revient.
    corpsDessus.addEventListener('submit', function (evenement) {
      var formulaire = evenement.target;
      if (evenement.defaultPrevented || !formulaire.hasAttribute('data-envoi-fenetre')) { return; }
      evenement.preventDefault();
      evenement.stopPropagation();
      var confirmation = formulaire.getAttribute('data-confirmation');
      if (confirmation && !window.confirm(confirmation)) { return; }

      var donnees = new FormData(formulaire);
      donnees.append('fenetre', '1');
      fetch(formulaire.action, { method: 'POST', body: donnees, credentials: 'same-origin' })
        .then(function (reponse) {
          if (!reponse.ok) { throw new Error('refus'); }
          return reponse.text();
        })
        .then(function (html) {
          if (/<header class="entete"/.test(html)) { ouvrirDessus(adresseDessus); } else { poserDessus(html); }
        })
        .catch(function () { if (adresseDessus !== null) { ouvrirDessus(adresseDessus); } });
    });

    document.addEventListener('click', function (evenement) {
      if (evenement.metaKey || evenement.ctrlKey || evenement.shiftKey
          || evenement.altKey || evenement.button !== 0) {
        return;
      }
      var lien = evenement.target.closest('[data-fenetre-dessus]');
      if (lien === null) { return; }
      evenement.preventDefault();
      ouvrirDessus(lien.getAttribute('href'));
    });
  }
  /*
   * Le volet d'une journée chargée se ferme comme on s'y attend.
   *
   * « details » l'ouvre et le referme tout seul, et cela suffit à s'en
   * servir. Mais un panneau posé sur le calendrier qui reste ouvert pendant
   * qu'on regarde ailleurs finit par gêner : on le ferme donc en cliquant à
   * côté, ou d'un coup d'échappement, et l'on n'en garde qu'un ouvert à la
   * fois.
   */
  var restes = document.querySelectorAll('.cal-reste');
  if (restes.length) {
    restes.forEach(function (reste) {
      reste.addEventListener('toggle', function () {
        if (!reste.open) { return; }
        restes.forEach(function (autre) {
          if (autre !== reste) { autre.open = false; }
        });
      });
    });

    document.addEventListener('click', function (evenement) {
      restes.forEach(function (reste) {
        if (reste.open && !reste.contains(evenement.target)) { reste.open = false; }
      });
    });

    document.addEventListener('keydown', function (evenement) {
      if (evenement.key !== 'Escape') { return; }
      restes.forEach(function (reste) {
        if (reste.open) {
          reste.open = false;
          // Le clavier ne doit pas rester en l'air : il revient sur le bouton
          // qui vient de se refermer.
          var bouton = reste.querySelector('summary');
          if (bouton) { bouton.focus(); }
        }
      });
    });
  }

  /*
   * Ouvrir et fermer le volet des agendas, sans recharger.
   *
   * Le formulaire fonctionne seul : sans script, le bouton renvoie la page et
   * le volet a changé d'état. Mais recharger un calendrier de trois cents
   * évènements pour cacher une colonne, c'est cher payé — on bascule donc sur
   * place, et l'on prévient le serveur en arrière-plan pour que le volet soit
   * dans le même état au prochain changement de mois.
   */
  /*
   * Le même bouton ferme la colonne des dossiers de « Mes cours » : le
   * formulaire dit quelle classe porte l'état fermé, et de quoi il parle.
   */
  document.querySelectorAll('[data-volet-bascule]').forEach(function (bascule) {
    var classeFerme = bascule.dataset.classeFerme || 'cal-avec-volet--ferme';
    var quoi = bascule.dataset.quoi || 'les agendas';
    var zone = bascule.closest('.' + classeFerme.replace(/--ferme$/, ''));
    if (!zone) { return; }
    var jetonBascule = bascule.querySelector('input[name="_csrf"]');
    var etat = bascule.querySelector('input[name="ferme"]');
    var boutonBascule = bascule.querySelector('button');
    var fleche = boutonBascule.querySelector('[aria-hidden]');
    var motBascule = boutonBascule.querySelector('.sr-only');

    bascule.addEventListener('submit', function (evenement) {
      evenement.preventDefault();

      var ferme = etat.value === '1';
      zone.classList.toggle(classeFerme, ferme);

      // Le bouton dit maintenant l'inverse : c'est lui qui porte le prochain
      // geste, pas l'état où l'on se trouve.
      etat.value = ferme ? '0' : '1';
      boutonBascule.setAttribute('aria-expanded', ferme ? 'false' : 'true');
      var mot = (ferme ? 'Montrer ' : 'Masquer ') + quoi;
      boutonBascule.title = mot;
      fleche.textContent = ferme ? '›' : '‹';
      motBascule.textContent = mot;

      var corpsBascule = new FormData();
      corpsBascule.append('_csrf', jetonBascule.value);
      corpsBascule.append('ferme', ferme ? '1' : '0');

      fetch(bascule.dataset.voletBascule || '', {
        method: 'POST',
        body: corpsBascule,
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      }).catch(function () {
        // Tant pis : le volet reste comme on vient de le mettre, pour cette
        // page-ci.
      });
    });
  });

  /*
   * Le volet retient ses sections repliées.
   *
   * Sans cela, replier « Outlook » puis cocher un agenda le rouvrirait : le
   * formulaire du volet se renvoie tout seul à chaque changement, et la page
   * reviendrait dépliée. On prévient donc le serveur en arrière-plan, sans
   * recharger — plier est un geste d'affichage, il ne doit rien coûter.
   *
   * Sans JavaScript, plier fonctionne quand même : « details » s'en charge.
   * Seule la mémoire manque.
   */
  var volet = document.querySelector('.cal-volet');
  if (volet) {
    var sections = volet.querySelectorAll('.cal-volet__section');
    var jeton = volet.querySelector('input[name="_csrf"]');

    sections.forEach(function (section) {
      section.addEventListener('toggle', function () {
        if (!jeton) { return; }

        var corps = new FormData();
        corps.append('_csrf', jeton.value);
        sections.forEach(function (autre) {
          if (!autre.open) { corps.append('replie[]', autre.dataset.section || ''); }
        });

        fetch(volet.dataset.volet || '', {
          method: 'POST',
          body: corps,
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin'
        }).catch(function () {
          // Tant pis : la section reste pliée pour cette page-ci.
        });
      });
    });
  }

  /*
   * Le plafond d'une sous-tâche suit la tâche principale qu'on lui choisit.
   *
   * Le champ arrive déjà borné par le serveur, sur la tâche principale du
   * moment. Mais le même formulaire permet d'en changer : sans cela, le
   * plafond resterait celui d'avant et la date serait refusée à l'envoi,
   * alors qu'on pouvait le dire tout de suite.
   */
  /*
   * La croix d'un panneau déroulant (« + Nouvelle liste ») : elle le referme,
   * et rend le clavier au bouton qui l'avait ouvert.
   */
  document.addEventListener('click', function (evenement) {
    var croix = evenement.target.closest && evenement.target.closest('[data-fermer-panneau]');
    if (!croix) { return; }
    var panneau = croix.closest('details');
    if (!panneau) { return; }
    panneau.open = false;
    var bouton = panneau.querySelector('summary');
    if (bouton) { bouton.focus(); }
  });

  /*
   * Les notifications de rappel, sur la page « Notifications » : la permission
   * du navigateur, le service worker, et l'abonnement confié au serveur.
   */
  var initialiserNotifications = function (racine) {
    var zoneNotifications = racine.querySelector('[data-notifications]');
    if (!zoneNotifications) { return; }
    // Une fois l'appareil activé ou désactivé, la page se relit : dans la
    // fenêtre, c'est elle qu'on relit, pas la page qu'elle recouvre.
    var relire = function () {
      if (zoneNotifications.closest('.fenetre__corps')) {
        zoneNotifications.dispatchEvent(new CustomEvent('fenetre:relire', { bubbles: true }));
      } else {
        window.location.reload();
      }
    };
    (function () {
      var etat = zoneNotifications.querySelector('[data-notifications-etat]');
      var aide = zoneNotifications.querySelector('[data-notifications-aide]');
      var activer = zoneNotifications.querySelector('[data-notifications-activer]');
      var essai = zoneNotifications.querySelector('[data-notifications-essai]');
      var desactiver = zoneNotifications.querySelector('[data-notifications-desactiver]');
      var d = zoneNotifications.dataset;

      var dire = function (texte) { etat.textContent = texte; };
      var montrer = function (abonne) {
        activer.hidden = abonne;
        essai.hidden = !abonne;
        desactiver.hidden = !abonne;
      };
      var octets = function (base64url) {
        var texte = atob((base64url + '==='.slice((base64url.length + 3) % 4)).replace(/-/g, '+').replace(/_/g, '/'));
        var tableau = new Uint8Array(texte.length);
        for (var i = 0; i < texte.length; i++) { tableau[i] = texte.charCodeAt(i); }
        return tableau;
      };
      var champs = function (abonnement) {
        var j = abonnement.toJSON();
        return { point_final: j.endpoint, cle_p256dh: j.keys.p256dh, cle_auth: j.keys.auth };
      };
      var poster = function (adresse, valeurs) {
        var corps = new FormData();
        corps.append('_csrf', d.jeton);
        Object.keys(valeurs).forEach(function (k) { corps.append(k, valeurs[k]); });
        return fetch(adresse, { method: 'POST', body: corps, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); });
      };

      if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        dire('Ce navigateur ne sait pas recevoir de notifications. Sur iPhone, ajoutez d’abord l’application à l’écran d’accueil (Partager → Sur l’écran d’accueil), puis ouvrez-la depuis là.');
        return;
      }
      if (!window.isSecureContext) {
        dire('Les notifications demandent une adresse sécurisée : https, ou localhost sur cet ordinateur.');
        return;
      }

      var enregistrement = navigator.serviceWorker.register(d.serviceWorker, { scope: d.portee });

      var verifier = function () {
        return enregistrement.then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (abonnement) {
          if (Notification.permission === 'denied') {
            dire('Les notifications sont bloquées pour ce site. Autorisez-les dans les réglages du site (le cadenas à gauche de l’adresse), puis rechargez la page.');
            montrer(false);
            activer.hidden = true;
            return;
          }
          if (abonnement && Notification.permission === 'granted') {
            // Le serveur a pu l'oublier (appareil retiré ailleurs) : on le lui redit.
            poster(d.abonner, champs(abonnement));
            dire('✅ Activées sur cet appareil : vous recevrez les rappels ici.');
            montrer(true);
          } else {
            dire('Désactivées sur cet appareil.');
            montrer(false);
          }
        }).catch(function (e) {
          dire('Le service des notifications n’a pas pu démarrer sur ce navigateur : ' + e.message);
          montrer(false);
          activer.hidden = true;
        });
      };

      activer.addEventListener('click', function () {
        activer.disabled = true;
        Notification.requestPermission().then(function (permission) {
          if (permission !== 'granted') { return verifier(); }
          return enregistrement
            .then(function () { return navigator.serviceWorker.ready; })
            .then(function (reg) {
              // Un ancien abonnement, fait avec une autre clé, empêcherait le nouveau.
              return reg.pushManager.getSubscription().then(function (ancien) {
                return ancien ? ancien.unsubscribe().then(function () { return reg; }) : reg;
              });
            })
            .then(function (reg) {
              return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: octets(d.clePublique) });
            })
            .then(function (abonnement) { return poster(d.abonner, champs(abonnement)); })
            .then(function (reponse) {
              if (!reponse.fait) { throw new Error(reponse.message || 'refus du serveur'); }
              relire();
            });
        }).catch(function (e) {
          dire('L’activation a échoué : ' + e.message);
        }).then(function () { activer.disabled = false; });
      });

      essai.addEventListener('click', function () {
        essai.disabled = true;
        poster(d.essai, {}).then(function (reponse) {
          aide.hidden = false;
          aide.textContent = reponse.message + (reponse.fait ? ' Elle devrait apparaître dans quelques secondes.' : '');
        }).catch(function () {
          aide.hidden = false;
          aide.textContent = 'L’essai n’a pas pu partir.';
        }).then(function () { essai.disabled = false; });
      });

      desactiver.addEventListener('click', function () {
        enregistrement.then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (abonnement) {
          if (!abonnement) { return null; }
          var valeurs = champs(abonnement);
          return abonnement.unsubscribe().then(function () { return poster(d.desabonner, valeurs); });
        }).then(relire);
      });

      verifier();
    })();
  };
  initialiserNotifications(document);

  /*
   * Le battement : tant qu'un onglet est ouvert, la page déclenche l'envoi des
   * rappels chaque minute — un seul onglet à la fois, grâce à l'heure du
   * dernier battement gardée dans le navigateur.
   */
  var battement = document.querySelector('[data-battement-rappels]');
  if (battement && window.fetch && window.FormData) {
    var battre = function () {
      try {
        if (Date.now() - Number(localStorage.getItem('mesCoursBattement') || 0) < 50000) { return; }
        localStorage.setItem('mesCoursBattement', String(Date.now()));
      } catch (e) { /* stockage refusé : on bat quand même */ }
      var corps = new FormData();
      corps.append('_csrf', battement.getAttribute('data-jeton'));
      fetch(battement.getAttribute('data-battement-rappels'), {
        method: 'POST', body: corps, credentials: 'same-origin', headers: { 'Accept': 'application/json' }
      }).catch(function () { /* hors ligne : la minute suivante réessaiera */ });
    };
    window.setTimeout(battre, 3000);
    window.setInterval(battre, 60000);
  }

  /*
   * Le menu « Télécharger » se referme une fois le format choisi, ou quand on
   * clique ailleurs — le téléchargement, lui, ne quitte pas la page.
   */
  document.addEventListener('click', function (evenement) {
    [].slice.call(document.querySelectorAll('details.menu-telecharger[open]')).forEach(function (menu) {
      var dedans = menu.contains(evenement.target);
      if (!dedans || evenement.target.closest('a')) {
        window.setTimeout(function () { menu.open = false; }, 0);
      }
    });
  });

  /*
   * « Annuler » dans un formulaire qui se replie (une remarque du tableau) :
   * le navigateur remet le texte d'avant, on referme le volet par-dessus.
   */
  document.addEventListener('reset', function (evenement) {
    var formulaire = evenement.target;
    if (!formulaire.matches || !formulaire.matches('[data-annuler-replie]')) { return; }
    var volet = formulaire.closest('details');
    if (!volet) { return; }
    volet.open = false;
    var bouton = volet.querySelector('summary');
    if (bouton) { bouton.focus(); }
  }, true);

  /*
   * Une carte du tableau s'ouvre d'un clic n'importe où sur elle, comme son
   * titre. Ses boutons, sa remarque et sa poignée gardent leur propre rôle.
   */
  document.addEventListener('click', function (evenement) {
    if (evenement.defaultPrevented || !evenement.target.closest) { return; }
    var carte = evenement.target.closest('[data-detail-carte]');
    if (!carte) { return; }
    if (evenement.target.closest('a, button, input, textarea, select, label, summary, form, details')) { return; }
    // Un texte qu'on sélectionne n'est pas un clic pour ouvrir.
    if (String(window.getSelection ? window.getSelection() : '') !== '') { return; }
    var titre = carte.querySelector('.kanban-carte__titre[href]');
    if (titre) { titre.click(); }
  });

  /*
   * Écouté sur le document, et non champ par champ : le formulaire de « + Tâche »
   * arrive dans une fenêtre après le chargement de la page.
   */
  document.addEventListener('change', function (evenement) {
    var choix = evenement.target;
    if (!choix || choix.tagName !== 'SELECT' || !choix.id) { return; }
    (choix.form || document).querySelectorAll('[data-plafond-de]').forEach(function (champDate) {
      if (champDate.getAttribute('data-plafond-de') !== choix.id) { return; }
      var option = choix.options[choix.selectedIndex];
      var plafond = option ? (option.getAttribute('data-echeance') || '') : '';
      if (plafond === '') {
        champDate.removeAttribute('max');
        champDate.removeAttribute('title');
        return;
      }
      champDate.setAttribute('max', plafond);
      champDate.title = 'Au plus tard le ' + plafond.split('-').reverse().join('/');
    });
  });

  // Les champs hors d'un formulaire commun avec leur liste gardent l'écoute d'avant.
  document.querySelectorAll('[data-plafond-de]').forEach(function (champDate) {
    var choix = document.getElementById(champDate.getAttribute('data-plafond-de'));
    if (!choix || choix.form === champDate.form) { return; }

    var suivreLePlafond = function () {
      var option = choix.options[choix.selectedIndex];
      var plafond = option ? (option.getAttribute('data-echeance') || '') : '';

      if (plafond === '') {
        champDate.removeAttribute('max');
        champDate.removeAttribute('title');
        return;
      }
      champDate.setAttribute('max', plafond);
      // Une date déjà saisie au-delà : on la signale plutôt que de la couper.
      champDate.title = 'Au plus tard le ' + plafond.split('-').reverse().join('/');
    };

    choix.addEventListener('change', suivreLePlafond);
  });

  document.querySelectorAll('[data-auto-envoi]').forEach(function (formulaire) {
    // « elements », et non les seuls descendants : un champ rangé ailleurs dans
    // la page mais rattaché au formulaire (« form="…" ») s'envoie aussi.
    [].slice.call(formulaire.elements).forEach(function (champ) {
      if (!champ.matches('select, input[type="checkbox"], input[type="radio"], input[type="color"]')) { return; }
      champ.addEventListener('change', function () { formulaire.submit(); });
    });
  });

  // Les champs de remboursement n'apparaissent qu'une fois la case cochée
  var caseRemb = document.getElementById('a_rembourser');
  var blocRemb = document.getElementById('bloc-remboursement');
  if (caseRemb && blocRemb) {
    var majRemb = function () {
      blocRemb.hidden = !caseRemb.checked;
    };
    caseRemb.addEventListener('change', majRemb);
    majRemb();
  }

  /*
   * Qui rembourse : le champ libre ne sert qu'à nommer quelqu'un de nouveau.
   *
   * Il est écrit visible, pour que la page marche sans script ; on le replie
   * ici, et on ne le rouvre que sur « Quelqu'un d'autre ». On le vide en le
   * repliant : le serveur fait passer le nom écrit avant la liste, et un reste
   * oublié là écraserait le choix.
   */
  document.querySelectorAll('[data-qui-rembourse]').forEach(function (choix) {
    var cle = choix.getAttribute('data-qui-rembourse');
    var autre = document.querySelector('[data-qui-autre="' + cle + '"]');
    if (!autre) { return; }

    var majQui = function (ouvertPar) {
      var nouveau = choix.value === '+';
      autre.hidden = !nouveau;
      if (!nouveau) { autre.value = ''; }
      // Nommer quelqu'un enchaîne sur la frappe : le champ prend la main.
      if (nouveau && ouvertPar) { autre.focus(); }
    };

    choix.addEventListener('change', function () { majQui(true); });
    majQui(false);
  });

  /*
   * Une conversation entre amis.
   *
   * L'envoi part sans recharger la page ; les nouveaux messages arrivent en
   * allant les chercher toutes les quatre secondes — une demi-minute quand
   * l'onglet est caché, pour ne pas solliciter le serveur pour rien. Entrée
   * envoie, Maj+Entrée va à la ligne. Tout le texte est posé en textContent :
   * un message ne peut pas glisser de balise dans la page.
   */
  var chat = document.querySelector('[data-chat]');
  if (chat) {
    (function () {
      var fil = chat.querySelector('[data-chat-messages]');
      var formulaire = chat.querySelector('[data-chat-formulaire]');
      var champ = formulaire.querySelector('textarea');
      var bouton = formulaire.querySelector('button[type="submit"]');
      var erreur = chat.querySelector('[data-chat-erreur]');
      var vu = chat.querySelector('[data-chat-vu]');
      var dernier = Number(chat.getAttribute('data-dernier')) || 0;
      var vuJusqua = Number(chat.getAttribute('data-vu')) || 0;
      var dernierMien = 0;
      var enCours = false;

      fil.querySelectorAll('.bulle--moi[data-message]').forEach(function (b) {
        dernierMien = Math.max(dernierMien, Number(b.getAttribute('data-message')));
      });

      var enBas = function () { fil.scrollTop = fil.scrollHeight; };
      // Amène une bulle au milieu de la conversation. Calculé plutôt que « scrollIntoView » : le défilement
      // animé ne s'exécute pas toujours, et ne doit pas faire bouger la page, figée, autour.
      var auCentre = function (bulle) {
        var cadre = fil.getBoundingClientRect();
        var place = bulle.getBoundingClientRect();
        fil.scrollTop += (place.top - cadre.top) - (fil.clientHeight - place.height) / 2;
      };
      var presqueEnBas = function () { return fil.scrollHeight - fil.scrollTop - fil.clientHeight < 80; };

      /*
       * La discussion tient dans l'écran, sous le menu quelle que soit sa
       * hauteur, et la page elle-même ne défile pas : seules la conversation
       * et la liste des amis ont leur barre. La zone de saisie reste ainsi
       * toujours visible.
       */
      var liste = document.querySelector('.chat__amis');
      document.documentElement.classList.add('page-discussion');
      var caler = function () {
        window.scrollTo(0, 0);
        var haut = chat.getBoundingClientRect().top;
        var hauteur = Math.max(240, window.innerHeight - haut - 16);
        var etaitEnBas = presqueEnBas();
        chat.style.height = hauteur + 'px';
        if (liste) { liste.style.maxHeight = hauteur + 'px'; }
        if (etaitEnBas) { enBas(); }
      };
      caler();
      window.addEventListener('resize', caler);
      enBas();

      /*
       * Collé en bas tant qu'on n'est pas remonté lire plus haut : une image
       * qui finit de charger allonge la conversation, et la vue la suit au
       * lieu de rester accrochée au milieu.
       */
      var colle = true;
      fil.addEventListener('scroll', function () { colle = presqueEnBas(); });
      var suivreImage = function (img) {
        if (img.complete) { return; }
        img.addEventListener('load', function () { if (colle) { enBas(); } });
      };
      fil.querySelectorAll('img').forEach(suivreImage);

      // « Vu » se place sous mon dernier message, et seulement s'il a été lu.
      var majVu = function () {
        dernierMien = 0;
        fil.querySelectorAll('.bulle--moi[data-message]').forEach(function (b) {
          dernierMien = Math.max(dernierMien, Number(b.getAttribute('data-message')));
        });
        var mienne = dernierMien > 0 ? fil.querySelector('[data-message="' + dernierMien + '"]') : null;
        vu.hidden = !(mienne && vuJusqua >= dernierMien);
        if (mienne && mienne.nextElementSibling !== vu) { mienne.after(vu); }
      };

      /*
       * Supprimer un message : « pour moi » — il disparaît de ma conversation
       * seulement — ou, pour un message que j'ai écrit, « pour tout le monde »
       * — il est effacé et les deux côtés voient « Message supprimé ». La
       * question s'ouvre depuis le menu de la bulle ; comme les autres
       * fenêtres de l'application, elle ne se ferme que par ses boutons.
       */
      var marquerSupprime = function (id) {
        fil.querySelectorAll('[data-extrait-de="' + id + '"]').forEach(function (e) { e.textContent = '🚫 Message supprimé'; });
        var bulle = fil.querySelector('[data-message="' + id + '"]');
        if (!bulle || bulle.classList.contains('bulle--supprime')) { return; }
        bulle.classList.add('bulle--supprime');
        bulle.classList.remove('bulle--image');
        bulle.removeAttribute('data-piece');
        bulle.querySelectorAll('.bulle__vocal audio').forEach(function (a) { a.pause(); });
        bulle.querySelectorAll('.bulle__image, .bulle__fichier, .bulle__vocal, .bulle__texte, .bulle__citation, .bulle__modifie, .bulle__reactions').forEach(function (e) { e.remove(); });
        var efface = document.createElement('p');
        efface.className = 'bulle__texte';
        efface.textContent = '🚫 Message supprimé';
        bulle.insertBefore(efface, bulle.querySelector('.bulle__heure'));
        if (mode && String(mode.id) === String(id)) { sortirMode(); }
      };
      var retirerBulle = function (id) {
        var bulle = fil.querySelector('[data-message="' + id + '"]');
        if (!bulle) { return; }
        bulle.remove();
        // Un séparateur de jour resté sans message n'a plus rien à séparer.
        fil.querySelectorAll('[data-jour]').forEach(function (jour) {
          var suivant = jour.nextElementSibling;
          while (suivant && suivant.matches('[data-chat-vu]')) { suivant = suivant.nextElementSibling; }
          if (!suivant || suivant.matches('[data-jour]')) { jour.remove(); }
        });
        majVu();
      };

      var question = document.createElement('dialog');
      question.className = 'question-suppression';
      question.innerHTML = '<h2 class="question-suppression__titre">Supprimer ce message ?</h2>'
        + '<p class="question-suppression__aide" data-aide></p>'
        + '<div class="question-suppression__choix">'
        + '<button type="button" class="bouton bouton--danger" data-portee="tous">Supprimer pour tout le monde</button>'
        + '<button type="button" class="bouton bouton--secondaire" data-portee="moi">Supprimer pour moi</button>'
        + '<button type="button" class="bouton bouton--discret" data-portee="">Annuler</button>'
        + '</div>'
        + '<p class="question-suppression__erreur" data-erreur hidden></p>';
      document.body.appendChild(question);
      question.addEventListener('cancel', function (evenement) { evenement.preventDefault(); });
      var aSupprimer = null;

      var demanderSuppression = function (bulle) {
        aSupprimer = bulle;
        var mien = bulle.classList.contains('bulle--moi');
        var dejaEfface = bulle.classList.contains('bulle--supprime');
        question.querySelector('[data-portee="tous"]').hidden = !mien || dejaEfface;
        question.querySelector('[data-aide]').textContent = !mien
          ? 'Il disparaîtra de votre conversation. Votre ami, lui, le verra toujours : seul qui l’a écrit peut le supprimer pour tout le monde.'
          : (dejaEfface ? 'Il disparaîtra de votre conversation.'
            : '« Pour moi » le retire de votre conversation seulement. « Pour tout le monde » l’efface des deux côtés, pièce jointe comprise : il restera « Message supprimé ».');
        question.querySelector('[data-erreur]').hidden = true;
        question.showModal();
        question.querySelector(mien && !dejaEfface ? '[data-portee="tous"]' : '[data-portee="moi"]').focus();
      };

      /*
       * Le menu d'une bulle : un clic gauche, ou un appui long sur un écran
       * tactile, l'ouvre juste à côté du message. Il propose de répondre, de
       * modifier (un message qu'on a écrit) et de supprimer. Il se referme en
       * cliquant ailleurs, par Échap, ou dès qu'on a choisi.
       *
       * Un clic sur ce qui a déjà son rôle dans la bulle — la photo, le nom
       * d'un fichier, la citation — garde ce rôle, et une sélection de texte à
       * la souris n'ouvre rien : on voulait copier.
       */
      var menu = document.createElement('div');
      menu.className = 'menu-message';
      menu.setAttribute('role', 'menu');
      menu.hidden = true;
      var rapides = (chat.getAttribute('data-reactions-rapides') || '👍 ❤️ 😂 😮 😢 🙏').split(' ');
      menu.innerHTML = '<div class="menu-message__reactions" data-menu-reactions>'
        + rapides.map(function (e) {
          return '<button type="button" class="menu-message__reaction" data-reagir-emoji="' + e + '" aria-label="Réagir ' + e + '">' + e + '</button>';
        }).join('')
        + '<button type="button" class="menu-message__reaction menu-message__plus" data-action="plus-reactions" aria-label="Choisir un autre emoji" title="Autre emoji">➕</button>'
        + '</div>'
        + '<button type="button" role="menuitem" data-action="repondre">↩ Répondre</button>'
        + '<button type="button" role="menuitem" data-action="modifier">✏️ Modifier</button>'
        + '<button type="button" role="menuitem" data-action="epingler">📌 Épingler</button>'
        + '<button type="button" role="menuitem" data-action="supprimer" class="menu-message__danger">🗑 Supprimer</button>';
      document.body.appendChild(menu);
      var bulleDuMenu = null;

      var fermerMenu = function () {
        if (menu.hidden) { return; }
        menu.hidden = true;
        if (bulleDuMenu) { bulleDuMenu.classList.remove('bulle--menu'); }
        bulleDuMenu = null;
      };
      var ouvrirMenu = function (bulle) {
        fermerMenu();
        var mien = bulle.classList.contains('bulle--moi');
        var efface = bulle.classList.contains('bulle--supprime');
        menu.querySelector('[data-action="repondre"]').hidden = efface;
        menu.querySelector('[data-action="epingler"]').textContent = bulle.classList.contains('bulle--epingle') ? '📌 Désépingler' : '📌 Épingler';
        menu.querySelector('[data-menu-reactions]').hidden = efface;
        // La réaction déjà posée est allumée : la reprendre l'enlève.
        var miennes = bulle.querySelector('.reaction--moi');
        menu.querySelectorAll('[data-reagir-emoji]').forEach(function (b) {
          b.classList.toggle('menu-message__reaction--moi', !!miennes && miennes.getAttribute('data-reaction') === b.getAttribute('data-reagir-emoji'));
        });
        menu.querySelector('[data-action="modifier"]').hidden = !mien || efface;
        bulleDuMenu = bulle;
        bulle.classList.add('bulle--menu');
        menu.hidden = false;

        // À côté de la bulle, du côté où elle est rangée ; au-dessus s'il n'y a pas la place dessous.
        var cadre = bulle.getBoundingClientRect();
        var largeur = menu.offsetWidth;
        var hauteur = menu.offsetHeight;
        var gauche = mien ? cadre.right - largeur : cadre.left;
        gauche = Math.max(8, Math.min(gauche, window.innerWidth - largeur - 8));
        var haut = cadre.bottom + 6;
        if (haut + hauteur > window.innerHeight - 8) { haut = Math.max(8, cadre.top - hauteur - 6); }
        menu.style.left = gauche + 'px';
        menu.style.top = haut + 'px';
        var premier = menu.querySelector('button:not([hidden])');
        if (premier) { premier.focus({ preventScroll: true }); }
      };

      var appuiLong = null;
      var appuiLongFait = false;
      var finAppuiLong = 0;
      fil.addEventListener('touchstart', function (evenement) {
        var bulle = evenement.target.closest('[data-message]');
        if (!bulle || evenement.touches.length > 1) { return; }
        var depart = evenement.touches[0];
        appuiLongFait = false;
        clearTimeout(appuiLong);
        appuiLong = setTimeout(function () {
          appuiLongFait = true;
          if (navigator.vibrate) { navigator.vibrate(15); }
          ouvrirMenu(bulle);
        }, 450);
        bulle.dataset.departX = String(depart.clientX);
        bulle.dataset.departY = String(depart.clientY);
      }, { passive: true });
      fil.addEventListener('touchmove', function (evenement) {
        var bulle = evenement.target.closest('[data-message]');
        if (!bulle || !evenement.touches.length) { return; }
        var dx = evenement.touches[0].clientX - Number(bulle.dataset.departX || 0);
        var dy = evenement.touches[0].clientY - Number(bulle.dataset.departY || 0);
        // On fait défiler la conversation : ce n'est pas un appui long.
        if (Math.abs(dx) > 10 || Math.abs(dy) > 10) { clearTimeout(appuiLong); }
      }, { passive: true });
      fil.addEventListener('touchend', function (evenement) {
        clearTimeout(appuiLong);
        // Après un appui long, le « clic » qui suit aussitôt ne doit ni refermer le menu ni suivre un lien.
        if (appuiLongFait) {
          evenement.preventDefault();
          appuiLongFait = false;
          finAppuiLong = Date.now();
        }
      });
      fil.addEventListener('contextmenu', function (evenement) {
        if (evenement.target.closest('[data-message]') && (appuiLongFait || appuiLong)) { evenement.preventDefault(); }
      });

      fil.addEventListener('click', function (evenement) {
        var citation = evenement.target.closest('[data-citation]');
        if (citation) {
          evenement.preventDefault();
          var cible = fil.querySelector('[data-message="' + citation.getAttribute('data-citation') + '"]');
          if (cible) {
            auCentre(cible);
            cible.classList.remove('bulle--repere');
            void cible.offsetWidth;
            cible.classList.add('bulle--repere');
          }
          return;
        }
        var bulle = evenement.target.closest('[data-message]');
        if (!bulle || evenement.target.closest('a, button, .bulle__vocal')) { return; }
        if (Date.now() - finAppuiLong < 700) { return; }
        var selection = window.getSelection ? String(window.getSelection()) : '';
        if (selection.trim() !== '' && bulle.contains(window.getSelection().anchorNode)) { return; }
        if (bulleDuMenu === bulle) { fermerMenu(); return; }
        ouvrirMenu(bulle);
      });
      fil.addEventListener('keydown', function (evenement) {
        var bulle = evenement.target.matches && evenement.target.matches('[data-message]') ? evenement.target : null;
        if (bulle && (evenement.key === 'Enter' || evenement.key === ' ' || evenement.key === 'ContextMenu')) {
          evenement.preventDefault();
          ouvrirMenu(bulle);
        }
      });
      document.addEventListener('click', function (evenement) {
        if (!menu.hidden && !menu.contains(evenement.target) && !(bulleDuMenu && bulleDuMenu.contains(evenement.target))) { fermerMenu(); }
      });
      document.addEventListener('keydown', function (evenement) {
        if (evenement.key === 'Escape' && !menu.hidden) {
          var bulle = bulleDuMenu;
          fermerMenu();
          if (bulle) { bulle.focus(); }
        }
      });
      fil.addEventListener('scroll', fermerMenu, { passive: true });
      window.addEventListener('resize', fermerMenu);

      menu.addEventListener('click', function (evenement) {
        var rapide = evenement.target.closest('[data-reagir-emoji]');
        if (rapide && bulleDuMenu) {
          var cible = bulleDuMenu;
          fermerMenu();
          reagir(cible, rapide.getAttribute('data-reagir-emoji'));
          return;
        }
        var choix = evenement.target.closest('[data-action]');
        if (!choix || !bulleDuMenu) { return; }
        var bulle = bulleDuMenu;
        fermerMenu();
        var action = choix.getAttribute('data-action');
        if (action === 'plus-reactions') {
          if (ouvrirEmojisPourReaction) { ouvrirEmojisPourReaction(bulle); }
          return;
        }
        if (action === 'repondre') { entrerMode('reponse', bulle); }
        if (action === 'modifier') { entrerMode('modifier', bulle); }
        if (action === 'supprimer') { demanderSuppression(bulle); }
        if (action === 'epingler') { epingler(bulle, !bulle.classList.contains('bulle--epingle')); }
      });

      /*
       * Répondre et modifier.
       *
       * Un bandeau au-dessus de la saisie dit ce qu'on fait — « Réponse à … »
       * avec le début du message, ou « Modifier le message » — et sa croix (ou
       * Échap) l'annule. Pour modifier, le texte du message revient dans la
       * saisie ; Entrée l'enregistre.
       */
      var contexte = chat.querySelector('[data-chat-contexte]');
      var mode = null;
      var texteDe = function (bulle) {
        var p = bulle.classList.contains('bulle--supprime') ? null : bulle.querySelector(':scope > .bulle__texte');
        return p ? p.textContent.replace(/\r/g, '') : '';
      };
      var extraitDe = function (bulle) {
        if (bulle.classList.contains('bulle--supprime')) { return '🚫 Message supprimé'; }
        var texte = texteDe(bulle).replace(/\s+/g, ' ').trim();
        var nomFichier = bulle.querySelector('.bulle__fichier-nom');
        var piece = bulle.querySelector('.bulle__image') ? '📷 Photo' : (nomFichier ? '📎 ' + nomFichier.textContent
          : (bulle.querySelector('.bulle__vocal') ? '🎤 Message vocal' : ''));
        var extrait = texte === '' ? piece : (piece === '' ? texte : piece + ' · ' + texte);
        return extrait.length > 120 ? extrait.slice(0, 119) + '…' : extrait;
      };
      /* Un extrait « 🎤 Message vocal » s'affiche avec le micro dessiné. */
      var poserExtrait = function (element, extrait) {
        element.textContent = '';
        if (extrait.indexOf('🎤 ') !== 0) { element.textContent = extrait; return; }
        var ns = 'http://www.w3.org/2000/svg';
        var svg = document.createElementNS(ns, 'svg');
        [['class', 'micro'], ['viewBox', '0 0 24 24'], ['width', '14'], ['height', '14'], ['fill', 'none'], ['stroke', 'currentColor'],
          ['stroke-width', '2'], ['stroke-linecap', 'round'], ['aria-hidden', 'true'], ['focusable', 'false']].forEach(function (a) { svg.setAttribute(a[0], a[1]); });
        var capsule = document.createElementNS(ns, 'rect');
        [['x', '9'], ['y', '3'], ['width', '6'], ['height', '12'], ['rx', '3'], ['fill', 'currentColor']].forEach(function (a) { capsule.setAttribute(a[0], a[1]); });
        svg.appendChild(capsule);
        ['M5.5 11a6.5 6.5 0 0 0 13 0', 'M12 17.5V21'].forEach(function (d) {
          var trait = document.createElementNS(ns, 'path');
          trait.setAttribute('d', d);
          svg.appendChild(trait);
        });
        element.appendChild(svg);
        element.appendChild(document.createTextNode(' ' + extrait.slice('🎤 '.length)));
      };
      var sortirMode = function () {
        if (!mode) { return; }
        if (mode.type === 'modifier') { champ.value = ''; ajuster(); }
        mode = null;
        contexte.hidden = true;
        contexte.classList.remove('chat__contexte--modifier');
        champ.required = enAttente.length === 0;
      };
      var entrerMode = function (type, bulle) {
        sortirMode();
        mode = { type: type, id: bulle.getAttribute('data-message'), bulle: bulle };
        var mien = bulle.classList.contains('bulle--moi');
        contexte.querySelector('[data-contexte-titre]').textContent = type === 'reponse'
          ? '↩ Réponse à ' + (mien ? 'vous-même' : chat.getAttribute('data-ami'))
          : '✏️ Modifier le message';
        poserExtrait(contexte.querySelector('[data-contexte-extrait]'), extraitDe(bulle));
        contexte.classList.toggle('chat__contexte--modifier', type === 'modifier');
        contexte.hidden = false;
        if (type === 'modifier') {
          champ.value = texteDe(bulle);
          // Une photo ou un fichier peut perdre sa légende ; un message de texte seul, non.
          champ.required = !bulle.hasAttribute('data-piece');
          ajuster();
        }
        champ.focus();
        champ.setSelectionRange(champ.value.length, champ.value.length);
      };
      contexte.querySelector('[data-contexte-annuler]').addEventListener('click', function () { sortirMode(); champ.focus(); });
      champ.addEventListener('keydown', function (evenement) {
        if (evenement.key === 'Escape' && mode) { evenement.preventDefault(); sortirMode(); }
      });

      /*
       * Les réactions.
       *
       * Sous la bulle, une pastille par emoji, avec le nombre et, au survol,
       * qui a réagi. Cliquer sur une pastille pose sa réaction avec cet emoji,
       * ou la retire si c'était déjà la sienne. On en choisit une depuis le
       * menu de la bulle : les raccourcis, ou ➕ pour tous les emojis.
       */
      var ouvrirEmojisPourReaction = null;
      var dessinerReactions = function (bulle, reactions) {
        if (!bulle) { return; }
        var zone = bulle.querySelector('.bulle__reactions');
        if (!reactions || !reactions.length || bulle.classList.contains('bulle--supprime')) {
          if (zone) { zone.remove(); }
          return;
        }
        if (!zone) {
          zone = document.createElement('div');
          zone.className = 'bulle__reactions';
          bulle.appendChild(zone);
        }
        zone.textContent = '';
        reactions.forEach(function (r) {
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'reaction' + (r.moi ? ' reaction--moi' : '');
          b.setAttribute('data-reaction', r.emoji);
          b.setAttribute('aria-pressed', r.moi ? 'true' : 'false');
          b.title = r.qui;
          b.textContent = r.emoji;
          if (r.nombre > 1) {
            var nombre = document.createElement('span');
            nombre.className = 'reaction__nombre';
            nombre.textContent = ' ' + r.nombre;
            b.appendChild(nombre);
          }
          zone.appendChild(b);
        });
      };
      var reagir = function (bulle, emoji) {
        var donnees = new FormData();
        donnees.append('_csrf', chat.getAttribute('data-jeton'));
        donnees.append('emoji', emoji);
        fetch(chat.getAttribute('data-reagir').replace('/0/', '/' + bulle.getAttribute('data-message') + '/'), {
          method: 'POST', body: donnees, credentials: 'same-origin', headers: { Accept: 'application/json' }
        }).then(function (r) { return r.json(); })
          .then(function (reponse) {
            if (!reponse.fait) { throw new Error(reponse.message || 'La réaction n’a pas pu être enregistrée.'); }
            var enBasAvant = presqueEnBas();
            dessinerReactions(bulle, reponse.reactions);
            if (enBasAvant) { enBas(); }
          })
          .catch(function (e) { montrerErreur(e.message || 'La réaction n’a pas pu être enregistrée.'); });
      };
      fil.addEventListener('click', function (evenement) {
        var pastille = evenement.target.closest('[data-reaction]');
        if (!pastille) { return; }
        var bulle = pastille.closest('[data-message]');
        // La sienne se retire ; celle d'un autre se reprend à son compte.
        reagir(bulle, pastille.getAttribute('aria-pressed') === 'true' ? '' : pastille.getAttribute('data-reaction'));
      });

      /*
       * Les épingles.
       *
       * Épingler un message (depuis son menu) le marque d'un 📌 et l'ajoute à
       * la liste qu'ouvre le bouton 📌 de l'en-tête. Un clic dans la liste
       * ramène au message et le fait clignoter ; s'il est trop ancien pour
       * être affiché, la conversation se rouvre à partir de lui.
       */
      var boutonEpingles = chat.querySelector('[data-epingles-bouton]');
      var panneauEpingles = chat.querySelector('[data-epingles-panneau]');
      var fermerEpingles = function () {
        if (panneauEpingles.hidden) { return; }
        panneauEpingles.hidden = true;
        boutonEpingles.setAttribute('aria-expanded', 'false');
      };
      var allerAuMessage = function (id) {
        var bulle = fil.querySelector('[data-message="' + id + '"]');
        if (!bulle) {
          window.location.href = chat.getAttribute('data-conversation') + '?message=' + encodeURIComponent(id);
          return;
        }
        colle = false;
        auCentre(bulle);
        bulle.classList.remove('bulle--repere');
        void bulle.offsetWidth;
        bulle.classList.add('bulle--repere');
      };
      var dessinerEpingles = function (liste) {
        var ul = panneauEpingles.querySelector('[data-epingles-liste]');
        ul.textContent = '';
        liste.forEach(function (ep) {
          var li = document.createElement('li');
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'epingles__element';
          b.setAttribute('data-aller-message', String(ep.id));
          var entete = document.createElement('span');
          entete.className = 'epingles__entete';
          var auteur = document.createElement('strong');
          auteur.textContent = ep.auteur;
          var quand = document.createElement('span');
          quand.textContent = ep.quand;
          entete.appendChild(auteur);
          entete.appendChild(quand);
          var extrait = document.createElement('span');
          extrait.className = 'epingles__extrait';
          poserExtrait(extrait, ep.extrait);
          b.appendChild(entete);
          b.appendChild(extrait);
          li.appendChild(b);
          ul.appendChild(li);
        });
        panneauEpingles.querySelector('[data-epingles-vide]').hidden = liste.length > 0;
        boutonEpingles.querySelector('[data-epingles-nombre]').textContent = String(liste.length);
      };
      var epingler = function (bulle, voulu) {
        var donnees = new FormData();
        donnees.append('_csrf', chat.getAttribute('data-jeton'));
        donnees.append('epingle', voulu ? '1' : '0');
        fetch(chat.getAttribute('data-epingler').replace('/0/', '/' + bulle.getAttribute('data-message') + '/'), {
          method: 'POST', body: donnees, credentials: 'same-origin', headers: { Accept: 'application/json' }
        }).then(function (r) { return r.json(); })
          .then(function (reponse) {
            if (!reponse.fait) { throw new Error(reponse.message || 'L’épingle n’a pas pu être posée.'); }
            bulle.classList.toggle('bulle--epingle', reponse.epingle);
            dessinerEpingles(reponse.epingles || []);
          })
          .catch(function (e) { montrerErreur(e.message || 'L’épingle n’a pas pu être posée.'); });
      };
      boutonEpingles.addEventListener('click', function () {
        if (panneauEpingles.hidden) {
          fermerMenu();
          fermerRecherche();
          panneauEpingles.hidden = false;
          boutonEpingles.setAttribute('aria-expanded', 'true');
          var premier = panneauEpingles.querySelector('[data-aller-message]');
          if (premier) { premier.focus(); }
        } else {
          fermerEpingles();
        }
      });
      panneauEpingles.addEventListener('click', function (evenement) {
        var element = evenement.target.closest('[data-aller-message]');
        if (!element) { return; }
        fermerEpingles();
        allerAuMessage(element.getAttribute('data-aller-message'));
      });
      document.addEventListener('click', function (evenement) {
        if (!panneauEpingles.hidden && !panneauEpingles.contains(evenement.target) && !boutonEpingles.contains(evenement.target)) { fermerEpingles(); }
      });
      document.addEventListener('keydown', function (evenement) {
        if (evenement.key === 'Escape' && !panneauEpingles.hidden) { fermerEpingles(); boutonEpingles.focus(); }
      });
      /*
       * Chercher dans la conversation.
       *
       * Le bouton 🔎 ouvre un champ sous l'en-tête. Dès deux caractères, les
       * messages qui contiennent le mot (ou dont le fichier le porte) arrivent,
       * du plus récent au plus ancien, le mot surligné. Un clic ramène au
       * message, comme une épingle — même s'il faut rouvrir la conversation
       * plus haut pour l'afficher.
       */
      var boutonRecherche = chat.querySelector('[data-recherche-bouton]');
      var panneauRecherche = chat.querySelector('[data-recherche-panneau]');
      var champRecherche = panneauRecherche.querySelector('[data-recherche-champ]');
      var etatRecherche = panneauRecherche.querySelector('[data-recherche-etat]');
      var listeRecherche = panneauRecherche.querySelector('[data-recherche-liste]');
      var minuterieRecherche = null;
      var numeroRecherche = 0;
      function fermerRecherche() {
        if (panneauRecherche.hidden) { return; }
        panneauRecherche.hidden = true;
        boutonRecherche.setAttribute('aria-expanded', 'false');
      }
      var dessinerResultats = function (reponse, recherche) {
        listeRecherche.textContent = '';
        var resultats = reponse.resultats || [];
        etatRecherche.textContent = resultats.length === 0
          ? 'Aucun message ne contient « ' + recherche + ' ».'
          : reponse.total + ' message' + (reponse.total > 1 ? 's' : '') + ' trouvé' + (reponse.total > 1 ? 's' : '')
            + (reponse.total > resultats.length ? ' — les ' + resultats.length + ' plus récents :' : '');
        resultats.forEach(function (r) {
          var li = document.createElement('li');
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'epingles__element';
          b.setAttribute('data-aller-message', String(r.id));
          var entete = document.createElement('span');
          entete.className = 'epingles__entete';
          var auteur = document.createElement('strong');
          auteur.textContent = r.auteur;
          var quand = document.createElement('span');
          quand.textContent = r.quand;
          entete.appendChild(auteur);
          entete.appendChild(quand);
          var extrait = document.createElement('span');
          extrait.className = 'epingles__extrait recherche-chat__extrait';
          extrait.appendChild(document.createTextNode(r.piece + r.avant));
          if (r.trouve) {
            var surligne = document.createElement('mark');
            surligne.textContent = r.trouve;
            extrait.appendChild(surligne);
          }
          extrait.appendChild(document.createTextNode(r.apres));
          b.appendChild(entete);
          b.appendChild(extrait);
          li.appendChild(b);
          listeRecherche.appendChild(li);
        });
      };
      var lancerRecherche = function () {
        var recherche = champRecherche.value.trim();
        clearTimeout(minuterieRecherche);
        if (recherche.length < 2) {
          listeRecherche.textContent = '';
          etatRecherche.textContent = 'Tapez au moins deux caractères.';
          return;
        }
        etatRecherche.textContent = 'Recherche…';
        // Une frappe rapide ne lance qu'une recherche, et seule la dernière réponse compte.
        minuterieRecherche = setTimeout(function () {
          var numero = ++numeroRecherche;
          fetch(chat.getAttribute('data-rechercher') + '?q=' + encodeURIComponent(recherche), {
            credentials: 'same-origin', headers: { Accept: 'application/json' }
          }).then(function (r) { return r.json(); })
            .then(function (reponse) {
              if (numero !== numeroRecherche) { return; }
              if (!reponse.fait) { throw new Error(reponse.message || ''); }
              dessinerResultats(reponse, recherche);
            })
            .catch(function (e) { if (numero === numeroRecherche) { etatRecherche.textContent = e.message || 'La recherche n’a pas abouti.'; } });
        }, 250);
      };
      champRecherche.addEventListener('input', lancerRecherche);
      boutonRecherche.addEventListener('click', function () {
        if (panneauRecherche.hidden) {
          fermerMenu();
          fermerEpingles();
          panneauRecherche.hidden = false;
          boutonRecherche.setAttribute('aria-expanded', 'true');
          champRecherche.focus();
          champRecherche.select();
        } else {
          fermerRecherche();
        }
      });
      panneauRecherche.addEventListener('click', function (evenement) {
        var element = evenement.target.closest('[data-aller-message]');
        if (!element) { return; }
        fermerRecherche();
        allerAuMessage(element.getAttribute('data-aller-message'));
      });
      document.addEventListener('click', function (evenement) {
        if (!panneauRecherche.hidden && !panneauRecherche.contains(evenement.target) && !boutonRecherche.contains(evenement.target)) { fermerRecherche(); }
      });
      document.addEventListener('keydown', function (evenement) {
        if (evenement.key === 'Escape' && !panneauRecherche.hidden) { fermerRecherche(); boutonRecherche.focus(); }
      });
      // Ctrl+F (ou Cmd+F) dans une discussion ouvre la recherche de la conversation.
      document.addEventListener('keydown', function (evenement) {
        if ((evenement.ctrlKey || evenement.metaKey) && !evenement.altKey && evenement.key.toLowerCase() === 'f') {
          evenement.preventDefault();
          if (panneauRecherche.hidden) { boutonRecherche.click(); } else { champRecherche.focus(); champRecherche.select(); }
        }
      });

      // Ouverte sur un message précis (depuis une épingle) : on s'y rend, plutôt qu'en bas.
      if (chat.getAttribute('data-cible')) {
        setTimeout(function () { allerAuMessage(chat.getAttribute('data-cible')); }, 60);
        // L'adresse redevient celle de la conversation : la recharger ramène en bas, comme d'habitude.
        if (window.history && history.replaceState) { history.replaceState(null, '', chat.getAttribute('data-conversation')); }
      }

      var appliquerTexte = function (bulle, texte, modifie) {
        if (!bulle || bulle.classList.contains('bulle--supprime')) { return; }
        var p = bulle.querySelector(':scope > .bulle__texte');
        if (texte === '') {
          if (p) { p.remove(); }
        } else {
          if (!p) {
            p = document.createElement('p');
            p.className = 'bulle__texte';
            bulle.insertBefore(p, bulle.querySelector('.bulle__heure'));
          }
          p.textContent = texte;
        }
        var heure = bulle.querySelector('.bulle__heure');
        if (modifie && heure && !heure.querySelector('.bulle__modifie')) {
          var marque = document.createElement('span');
          marque.className = 'bulle__modifie';
          marque.textContent = 'modifié · ';
          heure.insertBefore(marque, heure.firstChild);
        }
        var id = bulle.getAttribute('data-message');
        fil.querySelectorAll('[data-extrait-de="' + id + '"]').forEach(function (e) { poserExtrait(e, extraitDe(bulle)); });
      };

      question.addEventListener('click', function (evenement) {
        var choix = evenement.target.closest('[data-portee]');
        if (!choix) { return; }
        var portee = choix.getAttribute('data-portee');
        if (!portee || !aSupprimer) { question.close(); aSupprimer = null; return; }

        var bulle = aSupprimer;
        var id = bulle.getAttribute('data-message');
        var donnees = new FormData();
        donnees.append('_csrf', chat.getAttribute('data-jeton'));
        donnees.append('portee', portee);
        question.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
        fetch(chat.getAttribute('data-supprimer').replace('/0/', '/' + id + '/'), {
          method: 'POST', body: donnees, credentials: 'same-origin', headers: { Accept: 'application/json' }
        }).then(function (r) { return r.json(); })
          .then(function (reponse) {
            if (!reponse.fait) { throw new Error(reponse.message || 'Le message n’a pas pu être supprimé.'); }
            if (portee === 'tous') { marquerSupprime(id); } else { retirerBulle(id); }
            question.close();
            aSupprimer = null;
          })
          .catch(function (e) {
            var zone = question.querySelector('[data-erreur]');
            zone.textContent = e.message || 'Le message n’a pas pu être supprimé.';
            zone.hidden = false;
          })
          .then(function () { question.querySelectorAll('button').forEach(function (b) { b.disabled = false; }); });
      });

      var ajouter = function (message) {
        if (fil.querySelector('[data-message="' + message.id + '"]')) { return; }
        var vide = fil.querySelector('[data-chat-vide]');
        if (vide) { vide.remove(); }

        var jours = fil.querySelectorAll('[data-jour]');
        var jourPrecedent = jours.length ? jours[jours.length - 1].getAttribute('data-jour') : null;
        if (jourPrecedent !== message.jour) {
          var separateur = document.createElement('p');
          separateur.className = 'chat__jour';
          separateur.setAttribute('data-jour', message.jour);
          var libelle = document.createElement('span');
          libelle.textContent = message.jour_libelle;
          separateur.appendChild(libelle);
          fil.appendChild(separateur);
        }

        var bulle = document.createElement('div');
        bulle.className = 'bulle' + (message.moi ? ' bulle--moi' : '') + (message.image ? ' bulle--image' : '')
          + (message.supprime ? ' bulle--supprime' : '');
        bulle.setAttribute('data-message', String(message.id));
        bulle.id = 'message-' + message.id;
        bulle.tabIndex = 0;
        bulle.setAttribute('aria-haspopup', 'menu');
        if (message.image || message.fichier || message.vocal) { bulle.setAttribute('data-piece', ''); }
        if (message.reponse) {
          var citation = document.createElement('a');
          citation.className = 'bulle__citation';
          citation.href = '#message-' + message.reponse.id;
          citation.setAttribute('data-citation', String(message.reponse.id));
          var auteur = document.createElement('span');
          auteur.className = 'bulle__citation-auteur';
          auteur.textContent = message.reponse.auteur;
          var extraitCite = document.createElement('span');
          extraitCite.className = 'bulle__citation-extrait';
          extraitCite.setAttribute('data-extrait-de', String(message.reponse.id));
          poserExtrait(extraitCite, message.reponse.extrait);
          citation.appendChild(auteur);
          citation.appendChild(extraitCite);
          bulle.appendChild(citation);
        }
        if (message.supprime) {
          var efface = document.createElement('p');
          efface.className = 'bulle__texte';
          efface.textContent = '🚫 Message supprimé';
          bulle.appendChild(efface);
        }
        if (message.image) {
          var lienImage = document.createElement('a');
          lienImage.className = 'bulle__image';
          lienImage.href = message.image;
          lienImage.target = '_blank';
          lienImage.rel = 'noopener';
          lienImage.setAttribute('data-visionneuse', '');
          var img = document.createElement('img');
          img.alt = 'Photo';
          if (message.largeur > 0) { img.width = message.largeur; img.height = message.hauteur; }
          img.src = message.image;
          suivreImage(img);
          lienImage.appendChild(img);
          bulle.appendChild(lienImage);
        }
        if (message.vocal) {
          var lecteur = document.createElement('div');
          lecteur.className = 'bulle__vocal';
          lecteur.setAttribute('data-vocal', '');
          lecteur.setAttribute('data-duree', String(message.vocal.duree));
          var lecture = document.createElement('button');
          lecture.type = 'button';
          lecture.className = 'bulle__vocal-lecture';
          lecture.setAttribute('data-vocal-lecture', '');
          lecture.setAttribute('aria-label', 'Écouter le message vocal');
          lecture.textContent = '▶';
          var piste = document.createElement('span');
          piste.className = 'bulle__vocal-piste';
          piste.setAttribute('data-vocal-piste', '');
          var avance = document.createElement('span');
          avance.className = 'bulle__vocal-avance';
          avance.setAttribute('data-vocal-avance', '');
          piste.appendChild(avance);
          var temps = document.createElement('span');
          temps.className = 'bulle__vocal-temps';
          temps.setAttribute('data-vocal-temps', '');
          temps.textContent = message.vocal.duree_texte;
          var audio = document.createElement('audio');
          audio.preload = 'none';
          audio.src = message.vocal.url;
          lecteur.appendChild(lecture);
          lecteur.appendChild(piste);
          lecteur.appendChild(temps);
          lecteur.appendChild(audio);
          bulle.appendChild(lecteur);
        }
        if (message.fichier) {
          var carte = document.createElement('div');
          carte.className = 'bulle__fichier';
          var icone = document.createElement('span');
          icone.className = 'bulle__fichier-icone';
          icone.setAttribute('aria-hidden', 'true');
          icone.textContent = message.fichier.icone;
          var infos = document.createElement('span');
          infos.className = 'bulle__fichier-infos';
          var nom = document.createElement('a');
          nom.className = 'bulle__fichier-nom';
          nom.href = message.fichier.url;
          nom.target = '_blank';
          nom.rel = 'noopener';
          nom.textContent = message.fichier.nom;
          var poids = document.createElement('span');
          poids.className = 'bulle__fichier-taille';
          poids.textContent = message.fichier.taille;
          infos.appendChild(nom);
          infos.appendChild(poids);
          var telecharger = document.createElement('a');
          telecharger.className = 'bulle__fichier-telecharger';
          telecharger.href = message.fichier.telecharger;
          telecharger.title = 'Télécharger';
          telecharger.setAttribute('aria-label', 'Télécharger ' + message.fichier.nom);
          telecharger.textContent = '⬇';
          carte.appendChild(icone);
          carte.appendChild(infos);
          carte.appendChild(telecharger);
          bulle.appendChild(carte);
        }
        if (message.texte) {
          var texte = document.createElement('p');
          texte.className = 'bulle__texte';
          texte.textContent = message.texte;
          bulle.appendChild(texte);
        }
        var heure = document.createElement('span');
        heure.className = 'bulle__heure';
        heure.textContent = message.heure;
        var marqueEpingle = document.createElement('span');
        marqueEpingle.className = 'bulle__epingle';
        marqueEpingle.title = 'Épinglé';
        marqueEpingle.setAttribute('aria-label', 'Épinglé');
        marqueEpingle.textContent = '📌 ';
        heure.insertBefore(marqueEpingle, heure.firstChild);
        if (message.epingle) { bulle.classList.add('bulle--epingle'); }
        if (message.modifie) {
          var marque = document.createElement('span');
          marque.className = 'bulle__modifie';
          marque.textContent = 'modifié · ';
          heure.insertBefore(marque, heure.firstChild);
        }
        bulle.appendChild(heure);
        fil.appendChild(bulle);
        dessinerReactions(bulle, message.reactions || []);

        dernier = Math.max(dernier, message.id);
        if (message.moi) { dernierMien = Math.max(dernierMien, message.id); }
      };

      var relever = function () {
        if (enCours) { return Promise.resolve(); }
        enCours = true;
        var enBasAvant = presqueEnBas();
        // « visible » : la discussion est sous les yeux, inutile d'en notifier les messages.
        // Sous les yeux : l'onglet affiché ET la fenêtre active — pas une discussion laissée ouverte à côté.
        var regardee = !document.hidden && document.hasFocus();
        return fetch(chat.getAttribute('data-nouveaux') + '?apres=' + dernier + '&visible=' + (regardee ? 1 : 0)
          + '&modifies_depuis=' + encodeURIComponent(chat.getAttribute('data-maintenant') || ''), {
          credentials: 'same-origin', headers: { Accept: 'application/json' }
        }).then(function (r) {
          if (r.status === 403) { window.location.reload(); throw new Error('plus amis'); }
          return r.json();
        }).then(function (reponse) {
          if (!reponse.fait) { return; }
          (reponse.messages || []).forEach(ajouter);
          // Ce qui a été supprimé depuis : par l'autre pour tout le monde, ou par moi dans un autre onglet.
          (reponse.supprimes || []).forEach(marquerSupprime);
          (reponse.masques || []).forEach(retirerBulle);
          // Les messages modifiés depuis le relevé précédent, de part et d'autre.
          (reponse.modifies || []).forEach(function (m) {
            if (mode && mode.type === 'modifier' && String(mode.id) === String(m.id)) { return; }
            appliquerTexte(fil.querySelector('[data-message="' + m.id + '"]'), m.texte, m.modifie);
          });
          (reponse.reactions || []).forEach(function (m) {
            dessinerReactions(fil.querySelector('[data-message="' + m.id + '"]'), m.reactions);
          });
          if (reponse.maintenant) { chat.setAttribute('data-maintenant', reponse.maintenant); }
          vuJusqua = reponse.vu_jusqua || vuJusqua;
          majVu();
          if (enBasAvant && reponse.messages && reponse.messages.length) { enBas(); }
        }).catch(function () { /* réseau coupé : on réessaiera au prochain tour */ })
          .then(function () { enCours = false; });
      };

      var montrerErreur = function (texte) {
        erreur.textContent = texte;
        erreur.hidden = !texte;
      };

      /*
       * Les photos et fichiers à envoyer : choisis par le bouton 📎, collés
       * dans la zone de saisie, ou déposés sur la conversation. Ils attendent
       * au-dessus de la saisie — une vignette pour une photo, une étiquette
       * pour un fichier —, chacun avec sa croix, et partent à l'envoi : un par
       * message, la légende avec le premier.
       *
       * Une photo (JPEG, PNG, GIF, WebP de 10 Mo au plus) part comme image, et
       * s'affiche dans la conversation ; tout le reste part comme fichier.
       */
      var TYPES_IMAGES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
      var IMAGE_MAX = 10 * 1024 * 1024;
      var IMAGES_MAX = 10;
      var EXTENSIONS = (formulaire.getAttribute('data-extensions') || '').split(',');
      var FICHIER_MAX = Number(formulaire.getAttribute('data-fichier-max')) || 50 * 1024 * 1024;
      var enMo = function (octets) { return Math.round(octets / 1048576) + ' Mo'; };
      var poidsLisible = function (octets) {
        if (octets < 1024) { return octets + ' o'; }
        if (octets < 1048576) { return Math.round(octets / 1024) + ' Ko'; }
        return (octets / 1048576).toFixed(1).replace('.', ',') + ' Mo';
      };
      var apercus = chat.querySelector('[data-chat-apercus]');
      var choixImage = formulaire.querySelector('[data-chat-image]');
      var enAttente = [];

      var dessinerApercus = function () {
        apercus.textContent = '';
        enAttente.forEach(function (element, rang) {
          var vignette = document.createElement('div');
          vignette.className = 'chat__apercu' + (element.image ? '' : ' chat__apercu--fichier');
          var img;
          if (element.image) {
            img = document.createElement('img');
            img.src = element.adresse;
            img.alt = element.fichier.name;
          } else {
            img = document.createElement('span');
            img.className = 'chat__apercu-fichier';
            var nomFichier = document.createElement('span');
            nomFichier.className = 'chat__apercu-nom';
            nomFichier.textContent = '📎 ' + element.fichier.name;
            var poidsFichier = document.createElement('span');
            poidsFichier.className = 'chat__apercu-poids';
            poidsFichier.textContent = poidsLisible(element.fichier.size);
            img.appendChild(nomFichier);
            img.appendChild(poidsFichier);
          }
          var retirer = document.createElement('button');
          retirer.type = 'button';
          retirer.className = 'chat__apercu-retirer';
          retirer.setAttribute('aria-label', 'Retirer ' + element.fichier.name);
          retirer.textContent = '✕';
          retirer.addEventListener('click', function () {
            if (element.adresse) { URL.revokeObjectURL(element.adresse); }
            enAttente.splice(rang, 1);
            dessinerApercus();
            champ.focus();
          });
          vignette.appendChild(img);
          vignette.appendChild(retirer);
          apercus.appendChild(vignette);
        });
        apercus.hidden = enAttente.length === 0;
        champ.required = enAttente.length === 0;
      };

      var ajouterImages = function (liste) {
        var refus = '';
        Array.prototype.forEach.call(liste, function (fichier) {
          if (enAttente.length >= IMAGES_MAX) { refus = 'Dix pièces jointes au plus à la fois.'; return; }
          var estImage = TYPES_IMAGES.indexOf(fichier.type) !== -1 && fichier.size <= IMAGE_MAX;
          if (!estImage) {
            var extension = (fichier.name.split('.').pop() || '').toLowerCase();
            if (fichier.name.indexOf('.') === -1 || EXTENSIONS.indexOf(extension) === -1) {
              refus = '« ' + fichier.name + ' » : ce type de fichier n’est pas accepté.'; return;
            }
            if (fichier.size > FICHIER_MAX) { refus = '« ' + fichier.name + ' » est trop lourd : ' + enMo(FICHIER_MAX) + ' au plus.'; return; }
            if (fichier.size === 0) { refus = '« ' + fichier.name + ' » est vide.'; return; }
          }
          enAttente.push({ fichier: fichier, image: estImage, adresse: estImage ? URL.createObjectURL(fichier) : null });
        });
        montrerErreur(refus);
        dessinerApercus();
      };

      choixImage.addEventListener('change', function () {
        ajouterImages(choixImage.files);
        choixImage.value = '';
        champ.focus();
      });
      champ.addEventListener('paste', function (evenement) {
        var colles = (evenement.clipboardData && evenement.clipboardData.files) || [];
        if (colles.length) { evenement.preventDefault(); ajouterImages(colles); }
      });
      chat.addEventListener('dragover', function (evenement) {
        if (evenement.dataTransfer && Array.prototype.indexOf.call(evenement.dataTransfer.types, 'Files') !== -1) {
          evenement.preventDefault();
          chat.classList.add('chat__fil--depot');
        }
      });
      chat.addEventListener('dragleave', function (evenement) {
        if (!chat.contains(evenement.relatedTarget)) { chat.classList.remove('chat__fil--depot'); }
      });
      chat.addEventListener('drop', function (evenement) {
        chat.classList.remove('chat__fil--depot');
        if (evenement.dataTransfer && evenement.dataTransfer.files.length) {
          evenement.preventDefault();
          ajouterImages(evenement.dataTransfer.files);
        }
      });

      /*
       * Écouter un message vocal.
       *
       * Un seul joue à la fois. La piste avance avec la lecture, et un clic
       * dessus y déplace l'écoute ; le temps affiché décompte ce qui reste.
       * La durée enregistrée avec le message sert quand le fichier ne la dit
       * pas lui-même — c'est le cas des enregistrements WebM des navigateurs.
       */
      var dureeDe = function (lecteur, audio) {
        return isFinite(audio.duration) && audio.duration > 0 ? audio.duration : Number(lecteur.getAttribute('data-duree')) || 0;
      };
      var formatDuree = function (secondes) {
        secondes = Math.max(0, Math.round(secondes));
        return Math.floor(secondes / 60) + ':' + String(secondes % 60).padStart(2, '0');
      };
      var brancherLecteur = function (lecteur) {
        if (lecteur.dataset.branche) { return; }
        lecteur.dataset.branche = '1';
        var audio = lecteur.querySelector('audio');
        var bouton = lecteur.querySelector('[data-vocal-lecture]');
        var avance = lecteur.querySelector('[data-vocal-avance]');
        var temps = lecteur.querySelector('[data-vocal-temps]');
        audio.addEventListener('timeupdate', function () {
          var duree = dureeDe(lecteur, audio);
          avance.style.width = duree ? Math.min(100, audio.currentTime / duree * 100) + '%' : '0';
          temps.textContent = formatDuree(duree - audio.currentTime);
        });
        audio.addEventListener('play', function () { bouton.textContent = '⏸'; bouton.setAttribute('aria-label', 'Mettre en pause'); lecteur.classList.add('bulle__vocal--joue'); });
        audio.addEventListener('pause', function () { bouton.textContent = '▶'; bouton.setAttribute('aria-label', 'Écouter le message vocal'); lecteur.classList.remove('bulle__vocal--joue'); });
        audio.addEventListener('ended', function () {
          avance.style.width = '0';
          temps.textContent = formatDuree(dureeDe(lecteur, audio));
        });
        audio.addEventListener('error', function () { temps.textContent = 'Illisible'; });
      };
      fil.addEventListener('click', function (evenement) {
        var lecteur = evenement.target.closest('[data-vocal]');
        if (!lecteur) { return; }
        brancherLecteur(lecteur);
        var audio = lecteur.querySelector('audio');
        if (evenement.target.closest('[data-vocal-lecture]')) {
          if (audio.paused) {
            fil.querySelectorAll('[data-vocal] audio').forEach(function (autre) { if (autre !== audio) { autre.pause(); } });
            audio.play().catch(function () { lecteur.querySelector('[data-vocal-temps]').textContent = 'Illisible'; });
          } else {
            audio.pause();
          }
          return;
        }
        var piste = evenement.target.closest('[data-vocal-piste]');
        if (piste) {
          var cadre = piste.getBoundingClientRect();
          var part = Math.min(1, Math.max(0, (evenement.clientX - cadre.left) / cadre.width));
          var duree = dureeDe(lecteur, audio);
          if (duree) { try { audio.currentTime = part * duree; } catch (e) { /* pas encore chargé */ } }
          if (audio.paused) { audio.play().catch(function () {}); }
        }
      });

      /*
       * Enregistrer un message vocal.
       *
       * Le bouton 🎤 n'apparaît que si le navigateur sait enregistrer. Un clic
       * demande le micro, puis une barre remplace la saisie : le temps qui
       * passe, « Annuler » et « Envoyer ». Cinq minutes au plus : au-delà, le
       * message part de lui-même. Il répond au message choisi, s'il y en a un.
       */
      var boutonVocal = formulaire.querySelector('[data-vocal-bouton]');
      var barreVocal = chat.querySelector('[data-vocal-barre]');
      var chronoVocal = barreVocal.querySelector('[data-vocal-chrono]');
      var VOCAL_MAX = 300;
      var enregistreur = null;
      var morceaux = [];
      var debutVocal = 0;
      var minuterieVocal = null;
      var fluxVocal = null;
      var sortieVocal = null;

      var terminerEnregistrement = function () {
        clearInterval(minuterieVocal);
        if (fluxVocal) { fluxVocal.getTracks().forEach(function (piste) { piste.stop(); }); }
        fluxVocal = null;
        enregistreur = null;
        barreVocal.hidden = true;
        formulaire.hidden = false;
        champ.focus();
      };
      var arreter = function (envoyer) {
        if (!enregistreur) { return; }
        sortieVocal = envoyer;
        if (enregistreur.state !== 'inactive') { enregistreur.stop(); } else { terminerEnregistrement(); }
      };

      if (boutonVocal && navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder) {
        boutonVocal.hidden = false;
        boutonVocal.addEventListener('click', function () {
          montrerErreur('');
          navigator.mediaDevices.getUserMedia({ audio: true }).then(function (flux) {
            fluxVocal = flux;
            var formats = ['audio/webm;codecs=opus', 'audio/ogg;codecs=opus', 'audio/mp4', 'audio/webm'];
            var format = formats.filter(function (f) { return MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(f); })[0];
            enregistreur = format ? new MediaRecorder(flux, { mimeType: format }) : new MediaRecorder(flux);
            morceaux = [];
            enregistreur.addEventListener('dataavailable', function (e) { if (e.data && e.data.size) { morceaux.push(e.data); } });
            enregistreur.addEventListener('stop', function () {
              var duree = Math.round((Date.now() - debutVocal) / 1000);
              var type = (enregistreur && enregistreur.mimeType) || format || 'audio/webm';
              var envoyer = sortieVocal;
              terminerEnregistrement();
              if (!envoyer) { return; }
              if (duree < 1 || !morceaux.length) { montrerErreur('Message vocal trop court : maintenez l’enregistrement au moins une seconde.'); return; }
              var extension = type.indexOf('ogg') !== -1 ? 'ogg' : (type.indexOf('mp4') !== -1 ? 'm4a' : 'webm');
              var donnees = new FormData();
              donnees.append('_csrf', chat.getAttribute('data-jeton'));
              donnees.append('texte', '');
              donnees.append('duree', String(Math.min(duree, VOCAL_MAX)));
              donnees.append('vocal', new Blob(morceaux, { type: type }), 'message-vocal.' + extension);
              if (mode && mode.type === 'reponse') { donnees.append('reponse_a', mode.id); }
              bouton.disabled = true;
              fetch(chat.getAttribute('data-envoyer'), {
                method: 'POST', body: donnees, credentials: 'same-origin', headers: { Accept: 'application/json' }
              }).then(function (r) { return r.json(); })
                .then(function (reponse) {
                  if (!reponse.fait) { throw new Error(reponse.message || 'Le message vocal n’est pas parti.'); }
                  if (mode && mode.type === 'reponse') { sortirMode(); }
                  return relever().then(enBas);
                })
                .catch(function (e) { montrerErreur(e.message || 'Le message vocal n’est pas parti.'); })
                .then(function () { bouton.disabled = false; });
            });
            enregistreur.start(250);
            debutVocal = Date.now();
            chronoVocal.textContent = '0:00';
            formulaire.hidden = true;
            barreVocal.hidden = false;
            barreVocal.querySelector('[data-vocal-envoyer]').focus();
            minuterieVocal = setInterval(function () {
              var ecoule = (Date.now() - debutVocal) / 1000;
              chronoVocal.textContent = formatDuree(ecoule);
              if (ecoule >= VOCAL_MAX) { arreter(true); }
            }, 250);
          }).catch(function () {
            montrerErreur('Le micro n’est pas accessible : autorisez-le pour ce site (le cadenas à gauche de l’adresse), puis réessayez.');
          });
        });
        barreVocal.querySelector('[data-vocal-annuler]').addEventListener('click', function () { arreter(false); });
        barreVocal.querySelector('[data-vocal-envoyer]').addEventListener('click', function () { arreter(true); });
        document.addEventListener('keydown', function (evenement) {
          if (evenement.key === 'Escape' && enregistreur) { arreter(false); }
        });
      }

      var envoyerUn = function (texte, element, reponseA) {
        var donnees = new FormData();
        donnees.append('_csrf', chat.getAttribute('data-jeton'));
        donnees.append('texte', texte);
        if (reponseA) { donnees.append('reponse_a', reponseA); }
        if (element) { donnees.append(element.image ? 'image' : 'fichier', element.fichier, element.fichier.name || 'fichier'); }
        return fetch(chat.getAttribute('data-envoyer'), {
          method: 'POST', body: donnees, credentials: 'same-origin', headers: { Accept: 'application/json' }
        }).then(function (r) {
          return r.json().catch(function () { throw new Error('Le message n’est pas parti. Réessayez.'); });
        }).then(function (reponse) {
          if (!reponse.fait) { throw new Error(reponse.message || 'Le message n’est pas parti.'); }
        });
      };

      formulaire.addEventListener('submit', function (evenement) {
        evenement.preventDefault();
        var texte = champ.value.trim();

        if (mode && mode.type === 'modifier') {
          if (enAttente.length) { montrerErreur('Terminez la modification avant d’envoyer des pièces jointes.'); return; }
          var enModification = mode;
          if (!texte && !enModification.bulle.hasAttribute('data-piece')) {
            montrerErreur('Le message ne peut pas être vide : pour l’enlever, supprimez-le.');
            return;
          }
          montrerErreur('');
          bouton.disabled = true;
          var envoi = new FormData();
          envoi.append('_csrf', chat.getAttribute('data-jeton'));
          envoi.append('texte', texte);
          fetch(chat.getAttribute('data-modifier').replace('/0/', '/' + enModification.id + '/'), {
            method: 'POST', body: envoi, credentials: 'same-origin', headers: { Accept: 'application/json' }
          }).then(function (r) { return r.json(); })
            .then(function (reponse) {
              if (!reponse.fait) { throw new Error(reponse.message || 'Le message n’a pas pu être modifié.'); }
              appliquerTexte(enModification.bulle, texte, texte !== texteDe(enModification.bulle).trim() || enModification.bulle.querySelector('.bulle__modifie') !== null);
              sortirMode();
            })
            .catch(function (e) { montrerErreur(e.message || 'Le message n’a pas pu être modifié.'); })
            .then(function () { bouton.disabled = false; champ.focus(); });
          return;
        }

        if (!texte && !enAttente.length) { return; }
        montrerErreur('');
        bouton.disabled = true;
        var libelle = bouton.textContent;
        if (enAttente.length) { bouton.textContent = 'Envoi…'; }
        // La réponse part avec le premier message envoyé : le texte, ou la première pièce jointe.
        var reponseA = mode && mode.type === 'reponse' ? mode.id : null;
        var prendreReponse = function () { var r = reponseA; reponseA = null; return r; };

        // Les images partent l'une après l'autre ; la légende voyage avec la première.
        var suite = Promise.resolve();
        if (!enAttente.length) {
          suite = envoyerUn(texte, null, prendreReponse()).then(function () { champ.value = ''; sortirMode(); });
        }
        enAttente.slice().forEach(function (element) {
          suite = suite.then(function () {
            return envoyerUn(champ.value.trim(), element, prendreReponse()).then(function () {
              champ.value = '';
              sortirMode();
              if (element.adresse) { URL.revokeObjectURL(element.adresse); }
              enAttente.splice(enAttente.indexOf(element), 1);
              dessinerApercus();
            });
          });
        });

        suite.then(function () { ajuster(); return relever().then(enBas); })
          .catch(function (e) { montrerErreur(e.message || 'Le message n’est pas parti. Réessayez.'); relever(); })
          .then(function () { bouton.disabled = false; bouton.textContent = libelle; champ.focus(); });
      });

      /*
       * Une image s'agrandit dans une visionneuse, par-dessus la discussion.
       * Comme les autres fenêtres de l'application, seule sa croix la ferme.
       */
      var visionneuse = document.createElement('dialog');
      visionneuse.className = 'visionneuse';
      visionneuse.innerHTML = '<button class="fenetre__fermer" type="button" aria-label="Fermer">✕</button>'
        + '<img alt="Photo"><a class="bouton bouton--secondaire bouton--petit visionneuse__ouvrir" target="_blank" rel="noopener">Ouvrir en grand</a>';
      document.body.appendChild(visionneuse);
      visionneuse.querySelector('.fenetre__fermer').addEventListener('click', function () { visionneuse.close(); });
      visionneuse.addEventListener('cancel', function (evenement) { evenement.preventDefault(); });
      document.addEventListener('click', function (evenement) {
        var lienImage = evenement.target.closest && evenement.target.closest('[data-visionneuse]');
        if (!lienImage || typeof visionneuse.showModal !== 'function' || evenement.ctrlKey || evenement.metaKey) { return; }
        evenement.preventDefault();
        visionneuse.querySelector('img').src = lienImage.href;
        visionneuse.querySelector('.visionneuse__ouvrir').href = lienImage.href;
        visionneuse.showModal();
      });

      // Entrée envoie ; Maj+Entrée, ou une saisie en cours de composition, va à la ligne.
      champ.addEventListener('keydown', function (evenement) {
        if (evenement.key === 'Enter' && !evenement.shiftKey && !evenement.isComposing) {
          evenement.preventDefault();
          if (typeof formulaire.requestSubmit === 'function') { formulaire.requestSubmit(); }
          else { formulaire.dispatchEvent(new Event('submit', { cancelable: true })); }
        }
      });

      // La zone de saisie grandit avec le texte, jusqu'à six lignes environ.
      var ajuster = function () {
        champ.style.height = 'auto';
        champ.style.height = Math.min(champ.scrollHeight, 160) + 'px';
        champ.style.overflowY = champ.scrollHeight > 160 ? 'auto' : 'hidden';
      };
      champ.addEventListener('input', ajuster);

      /*
       * Les emojis : un bouton ouvre un panneau par catégories, avec les
       * derniers utilisés en tête. Un clic insère l'emoji là où est le
       * curseur ; le panneau reste ouvert pour en mettre plusieurs, et se
       * ferme d'un clic à côté, par Échap, ou à l'envoi.
       */
      var boutonEmoji = formulaire.querySelector('[data-emoji-bouton]');
      var panneauEmoji = formulaire.querySelector('[data-emoji-panneau]');
      if (boutonEmoji && panneauEmoji) {
        var EMOJIS = [
          ['😀', 'Visages', '😀 😃 😄 😁 😆 😅 🤣 😂 🙂 😉 😊 😇 🥰 😍 🤩 😘 😗 😚 😋 😛 😜 🤪 😝 🤗 🤭 🤫 🤔 🤐 🤨 😐 😑 😶 😏 😒 🙄 😬 😌 😔 😪 🤤 😴 😷 🤒 🤕 🤢 🤮 🥵 🥶 🥴 😵 🤯 🤠 🥳 😎 🤓 🧐 😕 😟 🙁 😮 😯 😲 😳 🥺 😦 😧 😨 😰 😥 😢 😭 😱 😖 😣 😞 😓 😩 😫 🥱 😤 😡 😠 🤬 😈 💀 💩 🤡 👻 👽 🤖'],
          ['👍', 'Gestes', '👍 👎 👌 🤌 ✌️ 🤞 🤟 🤘 🤙 👈 👉 👆 👇 ☝️ ✋ 🤚 🖐️ 🖖 👋 🤏 💪 🙏 🤝 👏 🙌 👐 🤲 ✍️ 💅 🤳 👀 👁️ 🧠 🫶 🙋 🙆 🙅 🤷 🤦 🙇 💁 🧑‍🎓 👩‍🎓 👨‍🎓 🧑‍🏫 🏃 💃 🕺'],
          ['❤️', 'Cœurs', '❤️ 🧡 💛 💚 💙 💜 🖤 🤍 🤎 💔 ❣️ 💕 💞 💓 💗 💖 💘 💝 💟 ♥️ 😻 💌 💋 🌹 💐'],
          ['🐶', 'Nature', '🐶 🐱 🐭 🐹 🐰 🦊 🐻 🐼 🐨 🐯 🦁 🐮 🐷 🐸 🐵 🐔 🐧 🐦 🐤 🦆 🦉 🐴 🦄 🐝 🦋 🐌 🐞 🐢 🐍 🐙 🐬 🐳 🦈 🌸 🌼 🌻 🌺 🌷 🌱 🌲 🌳 🍀 🍁 🍂 🌈 ☀️ 🌤️ ⛅ 🌧️ ⛈️ ❄️ ☃️ 🔥 💧 🌊 ⭐ 🌟 🌙'],
          ['🍕', 'Nourriture', '🍎 🍐 🍊 🍋 🍌 🍉 🍇 🍓 🍒 🍑 🥭 🍍 🥥 🥝 🍅 🥑 🥕 🌽 🥐 🥖 🧀 🥚 🍳 🥞 🧇 🥓 🍔 🍟 🍕 🌭 🥪 🌮 🌯 🥗 🍝 🍜 🍣 🍱 🍩 🍪 🎂 🍰 🧁 🍫 🍬 🍭 🍿 ☕ 🍵 🧃 🥤 🧋 🍺 🍷 🥂'],
          ['⚽', 'Activités', '⚽ 🏀 🏈 ⚾ 🎾 🏐 🏉 🎱 🏓 🏸 🥊 ⛸️ 🎿 🏂 🏋️ 🚴 🏊 🧘 🎮 🕹️ 🎲 🧩 ♟️ 🎯 🎳 🎨 🎭 🎤 🎧 🎸 🎹 🥁 🎬 📷 🎉 🎊 🎈 🎁 🏆 🥇 🥈 🥉 🏅 ✈️ 🚗 🚌 🚆 🚲 🏠 🏫 🏖️ ⛰️ 🗺️'],
          ['📚', 'École', '📚 📖 📝 ✏️ 🖊️ 🖍️ 📒 📓 📔 📕 📗 📘 📙 📄 📃 📑 🗂️ 📁 📂 📅 📆 🗓️ 📌 📍 📎 🖇️ 📏 📐 ✂️ 🧮 🔬 🔭 🧪 🧬 💻 🖥️ ⌨️ 🖱️ 📱 ☎️ 🔋 💡 🔦 ⏰ ⏳ ⌛ 🎒 🎓 🏫 💯 ✅ ❌ ❓ ❗ ⚠️'],
          ['✨', 'Symboles', '✨ 💫 💥 💢 💦 💨 🕳️ 💬 💭 🗯️ 💤 ✔️ ☑️ ➕ ➖ ✖️ ➗ 🟰 ♾️ ‼️ ⁉️ 🔝 🆗 🆕 🆒 🔴 🟠 🟡 🟢 🔵 🟣 ⚫ ⚪ 🟥 🟧 🟨 🟩 🟦 🟪 ⬛ ⬜ 🔶 🔷 ➡️ ⬅️ ⬆️ ⬇️ 🔁 🔄 ⏩ ⏪ 🎵 🎶 💲 💰 🔒 🔓 🔑 🚀']
        ];
        var CLE_RECENTS = 'mesCoursEmojisRecents';
        var lireRecents = function () {
          try { var r = JSON.parse(localStorage.getItem(CLE_RECENTS) || '[]'); return Array.isArray(r) ? r : []; }
          catch (e) { return []; }
        };
        var retenir = function (emoji) {
          var recents = lireRecents().filter(function (x) { return x !== emoji; });
          recents.unshift(emoji);
          try { localStorage.setItem(CLE_RECENTS, JSON.stringify(recents.slice(0, 24))); } catch (e) { /* stockage refusé */ }
        };

        var onglets = document.createElement('div');
        onglets.className = 'emojis__onglets';
        onglets.setAttribute('role', 'tablist');
        var grille = document.createElement('div');
        grille.className = 'emojis__grille';
        grille.setAttribute('role', 'tabpanel');
        var titre = document.createElement('p');
        titre.className = 'emojis__titre';
        panneauEmoji.appendChild(onglets);
        panneauEmoji.appendChild(titre);
        panneauEmoji.appendChild(grille);

        var montrer = function (rang) {
          var liste = rang < 0 ? lireRecents() : EMOJIS[rang][2].split(' ');
          titre.textContent = rang < 0 ? 'Récents' : EMOJIS[rang][1];
          grille.textContent = '';
          if (!liste.length) {
            var vide = document.createElement('p');
            vide.className = 'emojis__vide';
            vide.textContent = 'Les emojis que vous utiliserez apparaîtront ici.';
            grille.appendChild(vide);
          }
          liste.forEach(function (emoji) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'emojis__emoji';
            b.textContent = emoji;
            b.setAttribute('data-emoji', emoji);
            b.setAttribute('aria-label', emoji);
            grille.appendChild(b);
          });
          onglets.querySelectorAll('button').forEach(function (o) {
            o.setAttribute('aria-selected', Number(o.getAttribute('data-rang')) === rang ? 'true' : 'false');
          });
          grille.scrollTop = 0;
        };

        [['🕘', 'Récents', -1]].concat(EMOJIS.map(function (c, i) { return [c[0], c[1], i]; })).forEach(function (o) {
          var onglet = document.createElement('button');
          onglet.type = 'button';
          onglet.className = 'emojis__onglet';
          onglet.setAttribute('role', 'tab');
          onglet.setAttribute('data-rang', String(o[2]));
          onglet.title = o[1];
          onglet.setAttribute('aria-label', o[1]);
          onglet.textContent = o[0];
          onglets.appendChild(onglet);
        });

        // Où était le curseur avant d'ouvrir le panneau : c'est là qu'on insère.
        var debutSelection = null;
        var finSelection = null;
        var retenirCurseur = function () {
          debutSelection = champ.selectionStart;
          finSelection = champ.selectionEnd;
        };
        champ.addEventListener('keyup', retenirCurseur);
        champ.addEventListener('click', retenirCurseur);
        champ.addEventListener('input', retenirCurseur);

        var ouvrirEmojis = function () {
          retenirCurseur();
          montrer(lireRecents().length ? -1 : 0);
          panneauEmoji.hidden = false;
          boutonEmoji.setAttribute('aria-expanded', 'true');
        };
        var bullePourReaction = null;
        ouvrirEmojisPourReaction = function (bulle) {
          // Au tour suivant : le clic qui l'ouvre, en remontant jusqu'au document, le refermerait aussitôt.
          setTimeout(function () {
            bullePourReaction = bulle;
            montrer(lireRecents().length ? -1 : 0);
            panneauEmoji.hidden = false;
            panneauEmoji.classList.add('emojis--reaction');
            boutonEmoji.setAttribute('aria-expanded', 'true');
          }, 0);
        };
        var fermerEmojis = function (rendreLaMain) {
          bullePourReaction = null;
          panneauEmoji.classList.remove('emojis--reaction');
          if (panneauEmoji.hidden) { return; }
          panneauEmoji.hidden = true;
          boutonEmoji.setAttribute('aria-expanded', 'false');
          if (rendreLaMain) { champ.focus(); }
        };

        boutonEmoji.hidden = false;
        boutonEmoji.addEventListener('click', function () {
          if (panneauEmoji.hidden) { ouvrirEmojis(); } else { fermerEmojis(true); }
        });

        onglets.addEventListener('click', function (evenement) {
          var onglet = evenement.target.closest('[data-rang]');
          if (onglet) { montrer(Number(onglet.getAttribute('data-rang'))); }
        });

        grille.addEventListener('click', function (evenement) {
          var b = evenement.target.closest('[data-emoji]');
          if (!b) { return; }
          var emoji = b.getAttribute('data-emoji');
          // Ouvert depuis le menu d'une bulle : l'emoji choisi est une réaction, pas du texte.
          if (bullePourReaction) {
            var bulleVisee = bullePourReaction;
            retenir(emoji);
            fermerEmojis(false);
            reagir(bulleVisee, emoji);
            return;
          }
          var debut = debutSelection === null ? champ.value.length : debutSelection;
          var fin = finSelection === null ? champ.value.length : finSelection;
          // Pas au-delà de la longueur permise : l'emoji ne tiendrait qu'à moitié.
          var place = Number(champ.getAttribute('maxlength')) || Infinity;
          if (champ.value.length - (fin - debut) + emoji.length > place) { return; }
          champ.setRangeText(emoji, debut, fin, 'end');
          debutSelection = finSelection = debut + emoji.length;
          retenir(emoji);
          champ.dispatchEvent(new Event('input', { bubbles: true }));
          champ.focus({ preventScroll: true });
          champ.setSelectionRange(debutSelection, finSelection);
        });

        document.addEventListener('click', function (evenement) {
          if (!panneauEmoji.hidden && !panneauEmoji.contains(evenement.target) && evenement.target !== boutonEmoji) {
            fermerEmojis(false);
          }
        });
        document.addEventListener('keydown', function (evenement) {
          if (evenement.key === 'Escape' && !panneauEmoji.hidden) { fermerEmojis(true); }
        });
        formulaire.addEventListener('submit', function () { fermerEmojis(false); });
      }

      var minuterie = null;
      var planifier = function () {
        clearTimeout(minuterie);
        minuterie = setTimeout(function () { relever().then(planifier); }, document.hidden ? 30000 : 4000);
      };
      document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { relever().then(planifier); }
      });
      planifier();
    })();
  }

  /*
   * Les réglages de « Mon compte » — le pseudo, le fuseau horaire : ils se
   * lisent, et ne s'ouvrent à la modification que par leur bouton.
   * « Annuler » referme et remet la valeur enregistrée.
   */
  document.querySelectorAll('[data-reglage]').forEach(function (carte) {
    var lecture = carte.querySelector('[data-reglage-lecture]');
    var edition = carte.querySelector('[data-reglage-edition]');
    var modifier = carte.querySelector('[data-reglage-modifier]');
    var annuler = carte.querySelector('[data-reglage-annuler]');
    if (!lecture || !edition || !modifier || !annuler) { return; }
    var champ = edition.querySelector('input:not([type="hidden"]), select');

    modifier.addEventListener('click', function () {
      lecture.hidden = true;
      edition.hidden = false;
      if (champ) {
        champ.focus();
        if (champ.select && champ.tagName === 'INPUT') { champ.select(); }
      }
    });
    annuler.addEventListener('click', function () {
      edition.reset();
      // Après un refus, le champ montrait la saisie : on revient à ce qui est enregistré.
      edition.querySelectorAll('[data-valeur-actuelle]').forEach(function (c) {
        c.value = c.getAttribute('data-valeur-actuelle');
      });
      edition.hidden = true;
      lecture.hidden = false;
      modifier.focus();
    });
  });

  // Édition rapide d'une matière
  document.querySelectorAll('[data-bascule]').forEach(function (bouton) {
    bouton.addEventListener('click', function () {
      var cible = document.getElementById(bouton.getAttribute('data-bascule'));
      if (cible) {
        cible.hidden = !cible.hidden;
        if (!cible.hidden) {
          var premier = cible.querySelector('input, select, textarea');
          if (premier) { premier.focus(); }
        }
      }
    });
  });
  // Les cases à cocher des tâches enregistrent d'elles-mêmes.
  // Sans JavaScript, le bouton « OK » du <noscript> prend le relais.
  document.addEventListener("change", function (evenement) {
    var champ = evenement.target;
    if (champ.matches && champ.matches("[data-envoi-immediat]") && champ.form) {
      champ.form.submit();
    }
  });

  // Une liste dont seules certaines tâches sont faites : la case
  // affiche un trait, ni vide ni cochée. Seul JavaScript peut le poser.
  document.querySelectorAll("[data-partiel]").forEach(function (case_) {
    case_.indeterminate = true;
  });

  /*
   * Glisser-déposer des tâches, en deux gestes :
   *   — une carte de la colonne se déplace dans la colonne (on la réordonne) ;
   *   — une sous-tâche du volet se dépose sur une carte (elle change de liste).
   * Sans JavaScript, les flèches et le champ « Déplacer vers » prennent le relais.
   */
  var colonne = document.querySelector("[data-listes-triables]");
  var formeOrdre = document.getElementById("forme-ordre");
  var formeRanger = document.getElementById("forme-ranger");
  var glissementPossible = "draggable" in document.createElement("div");

  if (colonne && glissementPossible) {
    var cartes = [].slice.call(colonne.querySelectorAll(".liste-carte"));
    var listeGlissee = null;   // carte en cours de déplacement
    var tacheGlissee = null;   // sous-tâche en cours de déplacement

    var ordreDe = function () {
      return [].slice.call(colonne.querySelectorAll(".liste-carte"))
        .map(function (c) { return c.dataset.listeId; });
    };
    var ordreDepart = ordreDe().join(",");

    var envoyer = function (forme) {
      colonne.classList.add("est-en-cours");
      forme.submit();
    };

    /* --- Réordonner les tâches principales --- */

    if (formeOrdre && cartes.length > 1) {
      colonne.classList.add("est-triable");

      cartes.forEach(function (carte) {
        carte.draggable = true;
        carte.classList.add("est-saisissable");

        carte.addEventListener("dragstart", function (evenement) {
          listeGlissee = carte;
          carte.classList.add("liste-carte--glisse");
          evenement.dataTransfer.effectAllowed = "move";
          // Firefox exige une donnée pour démarrer le glissement.
          try { evenement.dataTransfer.setData("text/plain", carte.dataset.listeId); } catch (e) {}
        });

        carte.addEventListener("dragend", function () {
          carte.classList.remove("liste-carte--glisse");
          listeGlissee = null;
          var ordre = ordreDe();
          if (ordre.join(",") === ordreDepart || !formeOrdre) { return; }
          formeOrdre.elements.ordre.value = ordre.join(",");
          envoyer(formeOrdre);
        });
      });
    }

    /* --- Glisser une sous-tâche : vers une carte, ou dans sa propre liste --- */

    var listeTaches = document.querySelector("[data-taches-triables]");
    var formeOrdreTaches = document.getElementById("forme-ordre-taches");

    var ordreTachesDe = function () {
      if (!listeTaches) { return []; }
      return [].slice.call(listeTaches.querySelectorAll(".tache[data-tache-id]"))
        .map(function (l) { return l.dataset.tacheId; });
    };
    var ordreTachesDepart = ordreTachesDe().join(",");

    if (formeRanger || formeOrdreTaches) {
      if (listeTaches) { listeTaches.classList.add("est-triable"); }

      [].slice.call(document.querySelectorAll(".tache[data-tache-id]")).forEach(function (ligne) {
        ligne.draggable = true;
        ligne.classList.add("est-saisissable");

        ligne.addEventListener("dragstart", function (evenement) {
          tacheGlissee = ligne;
          ligne.classList.add("tache--glisse");
          colonne.classList.add("attend-une-tache");
          evenement.dataTransfer.effectAllowed = "move";
          try { evenement.dataTransfer.setData("text/plain", ligne.dataset.tacheId); } catch (e) {}
        });

        // Survoler une sœur la réordonne, à condition de rester dans la même liste.
        ligne.addEventListener("dragover", function (evenement) {
          if (!tacheGlissee || tacheGlissee === ligne) { return; }
          if (!listeTaches || ligne.parentNode !== listeTaches) { return; }
          if (tacheGlissee.parentNode !== listeTaches) { return; }
          evenement.preventDefault();
          evenement.dataTransfer.dropEffect = "move";

          var zone = ligne.getBoundingClientRect();
          var avant = (evenement.clientY - zone.top) < zone.height / 2;
          var repere = avant ? ligne : ligne.nextSibling;
          // Le formulaire d'édition suit sa ligne, sans quoi il resterait en arrière.
          var edition = document.getElementById("tache-" + tacheGlissee.dataset.tacheId);
          listeTaches.insertBefore(tacheGlissee, repere);
          if (edition) { listeTaches.insertBefore(edition, tacheGlissee.nextSibling); }
        });

        ligne.addEventListener("drop", function (evenement) { evenement.preventDefault(); });

        ligne.addEventListener("dragend", function () {
          ligne.classList.remove("tache--glisse");
          colonne.classList.remove("attend-une-tache");
          tacheGlissee = null;

          var ordre = ordreTachesDe();
          if (!formeOrdreTaches || ordre.join(",") === ordreTachesDepart) { return; }
          formeOrdreTaches.elements.ordre.value = ordre.join(",");
          envoyer(formeOrdreTaches);
        });
      });
    }

    /* --- Survol et dépôt sur une carte --- */

    cartes.forEach(function (carte) {
      carte.addEventListener("dragover", function (evenement) {
        // Une carte glissée : on réordonne la colonne en direct.
        if (listeGlissee && listeGlissee !== carte) {
          evenement.preventDefault();
          evenement.dataTransfer.dropEffect = "move";
          var zone = carte.getBoundingClientRect();
          var avant = (evenement.clientY - zone.top) < zone.height / 2;
          colonne.insertBefore(listeGlissee, avant ? carte : carte.nextSibling);
          return;
        }
        // Une sous-tâche glissée : la carte devient une cible de dépôt.
        if (tacheGlissee) {
          evenement.preventDefault();
          evenement.dataTransfer.dropEffect = "move";
          carte.classList.add("liste-carte--cible");
        }
      });

      carte.addEventListener("dragleave", function () {
        carte.classList.remove("liste-carte--cible");
      });

      carte.addEventListener("drop", function (evenement) {
        evenement.preventDefault();
        carte.classList.remove("liste-carte--cible");
        if (!tacheGlissee || !formeRanger) { return; }
        formeRanger.elements.tache.value = tacheGlissee.dataset.tacheId;
        formeRanger.elements.cible.value = carte.dataset.listeId;
        envoyer(formeRanger);
      });
    });
  }

  // Tableau kanban : on saisit une carte, on la depose dans une colonne.
  // Sans JavaScript, les petits boutons de chaque carte font le meme travail.
  var tableau = document.querySelector("[data-kanban]");
  var formeKanban = document.getElementById("forme-kanban");
  if (tableau && formeKanban && "draggable" in document.createElement("div")) {
    var carteGlissee = null;

    [].slice.call(tableau.querySelectorAll(".kanban-carte")).forEach(function (carte) {
      carte.draggable = true;
      carte.classList.add("est-saisissable");

      carte.addEventListener("dragstart", function (evenement) {
        carteGlissee = carte;
        carte.classList.add("kanban-carte--glisse");
        evenement.dataTransfer.effectAllowed = "move";
        try { evenement.dataTransfer.setData("text/plain", carte.dataset.carte); } catch (e) {}
      });

      carte.addEventListener("dragend", function () {
        carte.classList.remove("kanban-carte--glisse");
        carteGlissee = null;
      });
    });

    [].slice.call(tableau.querySelectorAll(".kanban__colonne")).forEach(function (colonne) {
      colonne.addEventListener("dragover", function (evenement) {
        if (!carteGlissee) { return; }
        evenement.preventDefault();
        evenement.dataTransfer.dropEffect = "move";
        colonne.classList.add("kanban__colonne--cible");
      });

      colonne.addEventListener("dragleave", function () {
        colonne.classList.remove("kanban__colonne--cible");
      });

      colonne.addEventListener("drop", function (evenement) {
        evenement.preventDefault();
        colonne.classList.remove("kanban__colonne--cible");
        if (!carteGlissee) { return; }
        // Reposee dans sa propre colonne : rien a enregistrer.
        if (carteGlissee.closest(".kanban__colonne") === colonne) { return; }
        formeKanban.elements.carte.value = carteGlissee.dataset.carte;
        formeKanban.elements.nature.value = carteGlissee.dataset.nature;
        formeKanban.elements.colonne.value = colonne.dataset.colonne;
        tableau.classList.add("est-en-cours");
        formeKanban.submit();
      });
    });
  }

  // Les dossiers se plient : seuls ceux de premier niveau restent
  // visibles, un clic sur un dossier montre ou masque les siens.
  // Sans JavaScript, rien ne se replie et l arborescence reste entiere.
  document.querySelectorAll("[data-plier]").forEach(function (bouton) {
    var cible = document.getElementById(bouton.getAttribute("data-plier"));
    if (!cible) { return; }

    var appliquer = function (ouvert) {
      cible.hidden = !ouvert;
      bouton.setAttribute("aria-expanded", ouvert ? "true" : "false");
    };
    appliquer(false);   // replié au chargement

    bouton.addEventListener("click", function () {
      appliquer(cible.hidden);
    });
  });

  // Glisser un cours sur un dossier pour l y ranger.
  // Sans JavaScript, le champ « Dossier » du formulaire fait le meme travail.
  var colonneDossiers = document.querySelector("[data-dossiers-cibles]");
  var formeRangerCours = document.getElementById("forme-ranger-cours");
  if (colonneDossiers && formeRangerCours && "draggable" in document.createElement("div")) {
    var coursGlisse = null;

    [].slice.call(document.querySelectorAll(".cours-carte[data-cours]")).forEach(function (carte) {
      carte.draggable = true;
      carte.classList.add("est-saisissable");

      carte.addEventListener("dragstart", function (evenement) {
        coursGlisse = carte;
        carte.classList.add("cours-carte--glisse");
        colonneDossiers.classList.add("attend-un-cours");
        evenement.dataTransfer.effectAllowed = "move";
        try { evenement.dataTransfer.setData("text/plain", carte.dataset.cours); } catch (e) {}
      });

      carte.addEventListener("dragend", function () {
        carte.classList.remove("cours-carte--glisse");
        colonneDossiers.classList.remove("attend-un-cours");
        coursGlisse = null;
      });
    });

    // Un dossier accepte deux choses : une carte de cours, ou des fichiers
    // venus du bureau — qui deviennent alors des cours.
    var formeDepot = document.getElementById("forme-depot-dossier");
    var apporteDesFichiers = function (evenement) {
      var t = evenement.dataTransfer && evenement.dataTransfer.types;
      if (!t) { return false; }
      return [].indexOf.call(t, "Files") !== -1;
    };

    [].slice.call(colonneDossiers.querySelectorAll("[data-dossier]")).forEach(function (cible) {
      cible.addEventListener("dragover", function (evenement) {
        if (!coursGlisse && !apporteDesFichiers(evenement)) { return; }
        evenement.preventDefault();
        evenement.dataTransfer.dropEffect = coursGlisse ? "move" : "copy";
        cible.classList.add("dossier-cible--survol");
      });

      cible.addEventListener("dragleave", function () {
        cible.classList.remove("dossier-cible--survol");
      });

      cible.addEventListener("drop", function (evenement) {
        evenement.preventDefault();
        cible.classList.remove("dossier-cible--survol");

        // Des fichiers deposes : on cree un cours par fichier dans ce dossier.
        var fichiers = evenement.dataTransfer && evenement.dataTransfer.files;
        if (!coursGlisse && fichiers && fichiers.length && formeDepot) {
          formeDepot.elements.dossier.value = cible.dataset.dossier;
          formeDepot.elements["fichiers[]"].files = fichiers;
          formeDepot.submit();
          return;
        }

        if (!coursGlisse) { return; }
        formeRangerCours.elements.cours.value = coursGlisse.dataset.cours;
        formeRangerCours.elements.dossier.value = cible.dataset.dossier;
        formeRangerCours.submit();
      });
    });
  }

  /*
   * Importer un dossier entier : un cours par fichier, l'arborescence reprise.
   *
   * Deux raisons de passer par le script plutôt que par un envoi ordinaire.
   * Le chemin de chaque fichier, d'abord : un formulaire ne transmet que le
   * nom, et les sous-dossiers seraient perdus. Le nombre, ensuite : PHP
   * n'accepte qu'une vingtaine de fichiers par requête, et un dossier en
   * compte souvent davantage — on les envoie donc par paquets, l'un après
   * l'autre, en disant où l'on en est.
   */
  var importDossier = document.querySelector("[data-import-dossier]");
  var champImport = importDossier ? importDossier.querySelector("[data-import-champ]") : null;

  if (importDossier && champImport && "webkitdirectory" in champImport && window.FormData) {
    importDossier.hidden = false;
    var etatImport = importDossier.querySelector("[data-import-etat]");

    // En deçà de ce que PHP accepte, et sans charger la requête à l'excès.
    var PAQUET_MAX = 15;
    var POIDS_MAX = 40 * 1024 * 1024;

    var dire = function (texte) {
      if (etatImport) { etatImport.textContent = texte; }
    };

    var envoyerLePaquet = function (paquet) {
      var corps = new FormData();
      corps.append("_csrf", importDossier.getAttribute("data-jeton"));
      corps.append("dossier", importDossier.getAttribute("data-dossier") || "");
      paquet.forEach(function (fichier) {
        corps.append("fichiers[]", fichier);
        // Le chemin voyage à côté du fichier : l'envoi ne le porte pas.
        corps.append("chemins[]", fichier.webkitRelativePath || fichier.name);
      });

      return fetch(importDossier.getAttribute("data-url"),
        { method: "POST", body: corps, credentials: "same-origin" })
        .then(function (reponse) {
          return reponse.ok ? reponse.json() : null;
        })
        .catch(function () { return null; });
    };

    /* Quinze fichiers au plus par paquet, et pas trop de poids d'un coup. */
    var enPaquets = function (fichiers) {
      var paquets = [];
      var courant = [];
      var poids = 0;
      fichiers.forEach(function (fichier) {
        if (courant.length >= PAQUET_MAX || (courant.length && poids + fichier.size > POIDS_MAX)) {
          paquets.push(courant);
          courant = [];
          poids = 0;
        }
        courant.push(fichier);
        poids += fichier.size;
      });
      if (courant.length) { paquets.push(courant); }
      return paquets;
    };

    var resumer = function (total, ecartes) {
      var mots = total.cours + (total.cours > 1 ? " cours créés" : " cours créé");
      if (total.dossiers > 0) {
        mots += " dans " + total.dossiers
          + (total.dossiers > 1 ? " nouveaux dossiers" : " nouveau dossier");
      }
      if (ecartes > 0) {
        mots += " · " + ecartes + (ecartes > 1 ? " fichiers écartés" : " fichier écarté");
      }
      dire(mots + ".");

      if (total.cours > 0 && etatImport) {
        // Le texte est posé en clair : ce qui vient du serveur ne devient
        // jamais du balisage.
        var lien = document.createElement("button");
        lien.type = "button";
        lien.className = "bouton bouton--discret bouton--petit";
        lien.style.marginTop = ".4rem";
        lien.textContent = "Voir les cours";
        lien.addEventListener("click", function () { window.location.reload(); });
        etatImport.parentNode.appendChild(lien);
      }
    };

    champImport.addEventListener("change", function () {
      var fichiers = [].slice.call(champImport.files || []);
      if (!fichiers.length) { return; }

      var paquets = enPaquets(fichiers);
      var total = { cours: 0, dossiers: 0 };
      var ecartes = 0;
      var faits = 0;
      champImport.disabled = true;
      dire("Import en cours…");

      var suite = Promise.resolve();
      paquets.forEach(function (paquet) {
        suite = suite.then(function () {
          return envoyerLePaquet(paquet).then(function (reponse) {
            if (reponse === null) {
              ecartes += paquet.length;
            } else {
              total.cours += reponse.cours || 0;
              total.dossiers += reponse.dossiers || 0;
              ecartes += (reponse.erreurs || []).length;
            }
            faits += paquet.length;
            dire("Import en cours… " + faits + " fichiers sur " + fichiers.length);
          });
        });
      });

      suite.then(function () {
        champImport.disabled = false;
        champImport.value = "";
        resumer(total, ecartes);
      });
    });
  }

  /*
   * Replier un dossier de la colonne, et le rouvrir.
   *
   * La colonne est une suite de lignes à plat, et non des listes emboîtées :
   * replier revient donc à cacher toutes les lignes dont un aïeul est replié.
   * Ce qu'on a replié est gardé dans le navigateur — retrouver sa colonne
   * comme on l'avait laissée fait toute l'utilité de la chose.
   */
  var colonnePlis = document.querySelector("[data-dossiers-cibles]");
  var rangsDossiers = colonnePlis ? [].slice.call(colonnePlis.querySelectorAll("[data-rang]")) : [];

  if (colonnePlis && rangsDossiers.length) {
    var CLE_PLIS = "mescours.dossiers-replies";

    // Les boutons ne servent qu'ici : sans script, la place reste vide.
    [].slice.call(colonnePlis.querySelectorAll(".dossier-rang__plier")).forEach(function (marque) {
      marque.hidden = false;
    });

    var replies = {};
    try {
      (JSON.parse(window.localStorage.getItem(CLE_PLIS)) || []).forEach(function (id) {
        replies[String(id)] = true;
      });
    } catch (e) { replies = {}; }

    var parentDe = {};
    rangsDossiers.forEach(function (rang) {
      parentDe[rang.getAttribute("data-rang")] = rang.getAttribute("data-parent");
    });

    // Le dossier ouvert ne doit pas rester caché : on déplie ce qui le couvre.
    var actif = colonnePlis.querySelector(".dossier-cible--active[data-dossier]");
    if (actif) {
      var aieul = parentDe[actif.getAttribute("data-dossier")];
      while (aieul && aieul !== "0") {
        delete replies[aieul];
        aieul = parentDe[aieul];
      }
    }

    var garderLesPlis = function () {
      try {
        window.localStorage.setItem(CLE_PLIS, JSON.stringify(Object.keys(replies)));
      } catch (e) { /* navigation privée, ou stockage plein : tant pis */ }
    };

    var peindreLesPlis = function () {
      rangsDossiers.forEach(function (rang) {
        var couvert = false;
        var dessus = rang.getAttribute("data-parent");
        while (dessus && dessus !== "0") {
          if (replies[dessus]) { couvert = true; break; }
          dessus = parentDe[dessus];
        }
        rang.hidden = couvert;

        var bouton = rang.querySelector("[data-plier-rang]");
        if (!bouton) { return; }
        var plie = !!replies[bouton.getAttribute("data-plier-rang")];
        bouton.setAttribute("aria-expanded", plie ? "false" : "true");
        bouton.textContent = plie ? "▸" : "▾";
        bouton.setAttribute("aria-label",
          (plie ? "Déplier « " : "Replier « ") + (rang.getAttribute("data-nom") || "") + " »");
      });
    };

    colonnePlis.addEventListener("click", function (evenement) {
      var bouton = evenement.target.closest && evenement.target.closest("[data-plier-rang]");
      if (!bouton) { return; }
      evenement.preventDefault();

      var id = bouton.getAttribute("data-plier-rang");
      if (replies[id]) { delete replies[id]; } else { replies[id] = true; }
      garderLesPlis();
      peindreLesPlis();
    });

    peindreLesPlis();
  }

  // Glisser un dossier dans un autre.
  // Sans JavaScript, le champ « Range dans » du formulaire fait le meme travail.
  var arbreDossiers = document.querySelector("[data-dossiers-arbre]");
  var formeRangerDossier = document.getElementById("forme-ranger-dossier");
  if (arbreDossiers && formeRangerDossier && "draggable" in document.createElement("div")) {
    var noeudGlisse = null;
    var zoneRacine = document.querySelector(".dossier-racine");

    var noeuds = [].slice.call(arbreDossiers.querySelectorAll(".dossier-noeud"));

    noeuds.forEach(function (noeud) {
      var carte = noeud.querySelector(".dossier-carte");
      if (!carte) { return; }
      noeud.draggable = true;
      noeud.classList.add("est-saisissable");

      noeud.addEventListener("dragstart", function (evenement) {
        // Le nœud le plus profond gagne : on ne saisit pas le parent
        // quand on attrape un enfant.
        evenement.stopPropagation();
        noeudGlisse = noeud;
        noeud.classList.add("dossier-noeud--glisse");
        arbreDossiers.classList.add("attend-un-dossier");
        if (zoneRacine) { zoneRacine.parentNode.classList.add("attend-un-dossier"); }
        document.body.classList.add("attend-un-dossier");
        evenement.dataTransfer.effectAllowed = "move";
        try { evenement.dataTransfer.setData("text/plain", noeud.dataset.dossier); } catch (e) {}
      });

      noeud.addEventListener("dragend", function () {
        noeud.classList.remove("dossier-noeud--glisse");
        arbreDossiers.classList.remove("attend-un-dossier");
        document.body.classList.remove("attend-un-dossier");
        noeudGlisse = null;
      });

      // Un dossier ne se depose ni sur lui-meme ni chez sa descendance :
      // celle-ci est justement contenue dans son propre noeud.
      var accepte = function () {
        return noeudGlisse && !noeudGlisse.contains(noeud);
      };

      carte.addEventListener("dragover", function (evenement) {
        if (!accepte()) { return; }
        evenement.preventDefault();
        evenement.stopPropagation();
        evenement.dataTransfer.dropEffect = "move";
        carte.classList.add("dossier-carte--survol");
      });

      carte.addEventListener("dragleave", function () {
        carte.classList.remove("dossier-carte--survol");
      });

      carte.addEventListener("drop", function (evenement) {
        evenement.preventDefault();
        evenement.stopPropagation();
        carte.classList.remove("dossier-carte--survol");
        if (!accepte()) { return; }
        formeRangerDossier.elements.dossier.value = noeudGlisse.dataset.dossier;
        formeRangerDossier.elements.parent.value = noeud.dataset.dossier;
        formeRangerDossier.submit();
      });
    });

    if (zoneRacine) {
      zoneRacine.addEventListener("dragover", function (evenement) {
        if (!noeudGlisse) { return; }
        evenement.preventDefault();
        zoneRacine.classList.add("dossier-racine--survol");
      });
      zoneRacine.addEventListener("dragleave", function () {
        zoneRacine.classList.remove("dossier-racine--survol");
      });
      zoneRacine.addEventListener("drop", function (evenement) {
        evenement.preventDefault();
        zoneRacine.classList.remove("dossier-racine--survol");
        if (!noeudGlisse) { return; }
        formeRangerDossier.elements.dossier.value = noeudGlisse.dataset.dossier;
        formeRangerDossier.elements.parent.value = "";
        formeRangerDossier.submit();
      });
    }
  }

  // Deposer des fichiers sur la page d un cours.
  // Sans JavaScript, la zone reste un champ de fichiers avec son bouton.
  // « requestSubmit » plutôt que « submit » : l'envoi passe alors par ceux qui
  // l'écoutent — la fenêtre, qui l'enregistre sans quitter la liste des cours.
  var envoyerForme = function (forme) {
    if (forme.requestSubmit) { forme.requestSubmit(); } else { forme.submit(); }
  };

  var initialiserDepots = function (racine) {
  [].slice.call(racine.querySelectorAll("[data-depot]")).forEach(function (forme) {
    var champ = forme.querySelector("[data-depot-champ]");
    var envoi = forme.querySelector("[data-depot-envoi]");
    if (!champ || forme.hasAttribute("data-depot-lance")) { return; }
    forme.setAttribute("data-depot-lance", "");

    // Avec JavaScript, le depot suffit : le bouton ne sert plus qu au clavier.
    var transfertPossible = "DataTransfer" in window && "files" in champ;

    champ.addEventListener("change", function () {
      if (champ.files && champ.files.length) { envoyerForme(forme); }
    });

    ["dragenter", "dragover"].forEach(function (nom) {
      forme.addEventListener(nom, function (evenement) {
        evenement.preventDefault();
        forme.classList.add("depot--survol");
      });
    });

    ["dragleave", "dragend"].forEach(function (nom) {
      forme.addEventListener(nom, function () { forme.classList.remove("depot--survol"); });
    });

    forme.addEventListener("drop", function (evenement) {
      evenement.preventDefault();
      forme.classList.remove("depot--survol");
      var fichiers = evenement.dataTransfer && evenement.dataTransfer.files;
      if (!fichiers || !fichiers.length || !transfertPossible) { return; }
      champ.files = fichiers;
      envoyerForme(forme);
    });

    if (envoi) { envoi.textContent = "Joindre les fichiers choisis"; }
  });
  };
  initialiserDepots(document);

  /*
   * Modifier le texte d un document : ajouter, supprimer, et laisser chaque
   * zone grandir avec son contenu.
   */
  /*
   * L'éditeur se lance sur une racine : la page, ou la fenêtre où il vient
   * d'être posé. Une zone déjà lancée ne l'est pas deux fois.
   */
  var initialiserEditeur = function (racine) {
  var zoneParagraphes = racine.querySelector("[data-paragraphes]");
  if (zoneParagraphes && !zoneParagraphes.hasAttribute("data-editeur-lance")) {
    zoneParagraphes.setAttribute("data-editeur-lance", "");
    var modeleParagraphe = racine.querySelector("[data-modele-paragraphe]");
    var ajoutParagraphe = racine.querySelector("[data-ajouter-paragraphe]");

    var ajusterHauteur = function (zone) {
      zone.style.height = "auto";
      zone.style.height = zone.scrollHeight + "px";
    };

    var renumeroter = function () {
      var rang = 1;
      [].slice.call(zoneParagraphes.querySelectorAll(".paragraphe__rang")).forEach(function (etiquette) {
        etiquette.textContent = String(rang++);
      });
    };

    [].slice.call(zoneParagraphes.querySelectorAll("textarea")).forEach(ajusterHauteur);

    zoneParagraphes.addEventListener("input", function (evenement) {
      if (evenement.target && evenement.target.tagName === "TEXTAREA") {
        ajusterHauteur(evenement.target);
      }
    });

    zoneParagraphes.addEventListener("click", function (evenement) {
      var bouton = evenement.target.closest && evenement.target.closest("[data-supprimer-paragraphe]");
      if (!bouton) { return; }
      var ligne = bouton.closest("[data-paragraphe]");
      if (!ligne) { return; }
      ligne.parentNode.removeChild(ligne);
      renumeroter();
      if (typeof renumeroterListes === "function") { renumeroterListes(); }
    });

    if (ajoutParagraphe && modeleParagraphe && modeleParagraphe.content) {
      ajoutParagraphe.hidden = false;
      ajoutParagraphe.addEventListener("click", function () {
        var ligne = modeleParagraphe.content.firstElementChild.cloneNode(true);
        zoneParagraphes.appendChild(ligne);
        renumeroter();
        var zone = ligne.querySelector("textarea");
        if (zone) { ajusterHauteur(zone); zone.focus(); }
        if (typeof enrichir === "function") {
          enrichir(ligne);
          var riche = ligne.querySelector("[data-zone-riche]");
          if (riche) { riche.focus(); }
        }
      });
    }

    /*
     * Le gras, l'italique, le souligné et la taille.
     *
     * Chaque paragraphe reçoit une zone modifiable par-dessus son champ de
     * texte, qui reste là, caché : c'est lui que le formulaire envoie, et on y
     * recopie le balisage juste avant de partir. Sans ce script, le champ se
     * montre tel quel et la page fait ce qu'elle a toujours fait — on modifie
     * le texte, pas sa forme.
     */
    var formulaireDocument = racine.querySelector("[data-edition-document]");
    var barreOutils = racine.querySelector("[data-barre-outils]");
    var drapeauRiche = racine.querySelector("[data-riche]");

    /*
     * Sur quelle zone la barre agit : celle où se trouve la sélection.
     *
     * On la relit à chaque changement de sélection plutôt qu'au moment où une
     * zone prend le focus : cet événement-là ne se déclenche pas toujours, et
     * la barre restait alors sans effet.
     */
    var zoneChoisie = null;
    var plageChoisie = null;

    var zoneDeLaSelection = function () {
      var selection = document.getSelection();
      if (!selection || selection.rangeCount === 0) { return null; }
      var noeud = selection.getRangeAt(0).commonAncestorContainer;
      if (noeud.nodeType === 3) { noeud = noeud.parentNode; }

      return noeud && noeud.closest ? noeud.closest("[data-zone-riche]") : null;
    };

    document.addEventListener("selectionchange", function () {
      var zone = zoneDeLaSelection();
      // Seulement les zones de cet éditeur : une fenêtre refermée en laisse un
      // autre derrière elle, qui n'a pas à suivre la sélection d'ailleurs.
      if (!zone || !zoneParagraphes.contains(zone)) { return; }
      zoneChoisie = zone;
      // La plage est retenue, et pas seulement la zone : cliquer dans un
      // nuancier fait perdre la sélection, et il faut pouvoir la remettre.
      var selection = document.getSelection();
      plageChoisie = selection.rangeCount > 0 ? selection.getRangeAt(0).cloneRange() : null;
    });

    /** La zone visée, sa sélection remise en place si un clic l'a défaite. */
    var reprendreLaSelection = function () {
      var zone = zoneDeLaSelection();
      if (zone !== null) { return zone; }
      if (zoneChoisie === null || plageChoisie === null) { return null; }

      zoneChoisie.focus();
      var selection = document.getSelection();
      selection.removeAllRanges();
      selection.addRange(plageChoisie);

      return zoneChoisie;
    };

    /**
     * Ouvre le paragraphe suivant, à partir du curseur.
     *
     * Sur un élément de liste resté vide, Entrée sort de la liste plutôt que
     * d'en ajouter un de plus : c'est ainsi qu'on la termine sans avoir à
     * chercher un bouton.
     */
    /**
     * Va à la ligne sans changer de paragraphe.
     *
     * Le navigateur sait le faire lui-même ; à défaut, on pose le saut à la
     * main. Le serveur l'écrira comme Word le fait avec Maj+Entrée.
     */
    var allerALaLigne = function (zone) {
      var fait = false;
      try { fait = document.execCommand("insertLineBreak"); } catch (e) { fait = false; }
      if (!fait) { document.execCommand("insertHTML", false, "<br>"); }
      var champ = zone.closest("[data-paragraphe]") && zone.closest("[data-paragraphe]").querySelector("textarea");
      if (champ) { champ.value = zone.innerHTML; }
    };

    var alaLigne = function (ligne, zone) {
      var sorte = ligne.getAttribute("data-liste") || "";
      var niveau = sorte === "" ? 0 : Number(ligne.getAttribute("data-niveau") || 0);
      if (sorte !== "" && zone.textContent.trim() === "") {
        // Sur un élément resté vide, Entrée remonte d'abord d'un cran, puis
        // sort de la liste : la même touche défait ce qu'elle a fait.
        if (!changerNiveau(ligne, -1)) { marquerLaLigne(ligne, ""); }
        return;
      }
      if (!modeleParagraphe || !modeleParagraphe.content) { return; }

      // Ce qui suit le curseur s'en va dans le paragraphe qui s'ouvre.
      var reste = null;
      var selection = document.getSelection();
      if (selection && selection.rangeCount > 0) {
        var coupe = selection.getRangeAt(0);
        var apres = coupe.cloneRange();
        apres.selectNodeContents(zone);
        apres.setStart(coupe.endContainer, coupe.endOffset);
        reste = apres.extractContents();
      }

      /*
       * Le paragraphe qui s'ouvre ne reprend pas le titre du précédent : après
       * un titre vient le texte de la section, comme le fait un traitement de
       * texte. Le modèle est déjà sans titre, il n'y a rien à recopier.
       */
      var suivante = modeleParagraphe.content.firstElementChild.cloneNode(true);
      suivante.setAttribute("data-liste", sorte);
      suivante.setAttribute("data-niveau", String(niveau));
      suivante.setAttribute("data-aligne", ligne.getAttribute("data-aligne") || "");
      ligne.parentNode.insertBefore(suivante, ligne.nextSibling);

      var champListe = suivante.querySelector("input[name='liste[]']");
      var champNiveau = suivante.querySelector("input[name='niveau[]']");
      var champAligne = suivante.querySelector("input[name='alignement[]']");
      if (champListe) { champListe.value = sorte; }
      if (champNiveau) { champNiveau.value = String(niveau); }
      if (champAligne) { champAligne.value = ligne.getAttribute("data-aligne") || ""; }

      enrichir(suivante);
      renumeroter();
      renumeroterListes();

      var neuve = suivante.querySelector("[data-zone-riche]");
      var champTexte = suivante.querySelector("textarea");
      if (!neuve) { return; }
      if (reste !== null) { neuve.appendChild(reste); }
      if (champTexte) { champTexte.value = neuve.innerHTML; }
      var ancien = ligne.querySelector("textarea");
      if (ancien) { ancien.value = zone.innerHTML; }

      neuve.focus();
      var debut = document.createRange();
      debut.selectNodeContents(neuve);
      debut.collapse(true);
      var choix = document.getSelection();
      choix.removeAllRanges();
      choix.addRange(debut);
    };

    // Jusqu'où l'éditeur descend : trois étages, comme un plan de cours.
    // Le serveur en dit autant, dans EditionDocument::NIVEAU_MAX.
    var NIVEAU_MAX = 2;

    /** Le rang d'une sous-liste, en lettres : a, b, … z, aa, ab. */
    var enLettres = function (rang) {
      var mot = "";
      while (rang > 0) {
        rang--;
        mot = "abcdefghijklmnopqrstuvwxyz".charAt(rang % 26) + mot;
        rang = Math.floor(rang / 26);
      }
      return mot;
    };

    /** Le rang d'un troisième étage, en chiffres romains : i, ii, iii. */
    var ROMAINS = [[1000, "m"], [900, "cm"], [500, "d"], [400, "cd"], [100, "c"], [90, "xc"],
                   [50, "l"], [40, "xl"], [10, "x"], [9, "ix"], [5, "v"], [4, "iv"], [1, "i"]];
    var enRomain = function (rang) {
      var mot = "";
      ROMAINS.forEach(function (paire) {
        while (rang >= paire[0]) { mot += paire[1]; rang -= paire[0]; }
      });
      return mot;
    };

    // La marque d'un rang, selon l'étage où il se trouve.
    var MARQUES = [String, enLettres, enRomain];
    var PUCES = ["•", "◦", "▪"];

    /**
     * Renumérote les éléments de liste.
     *
     * Un paragraphe ordinaire n'interrompt pas la numérotation : le document
     * la poursuit par-dessus, et l'écran doit dire la même chose. Une
     * sous-liste, elle, repart à « a » sous chacun des éléments qui la
     * portent. La marque est posée sur la zone, seule à pouvoir l'afficher
     * devant son texte.
     */
    var renumeroterListes = function () {
      var compteurs = [0, 0, 0];
      [].slice.call(zoneParagraphes.querySelectorAll("[data-paragraphe]")).forEach(function (ligne) {
        var zone = ligne.querySelector("[data-zone-riche]");
        var sorte = ligne.getAttribute("data-liste") || "";
        var niveau = sorte === "" ? 0 : Number(ligne.getAttribute("data-niveau") || 0);
        var marque = "";

        if (sorte === "numero") {
          compteurs[niveau]++;
          // Ce qui est plus profond repart de zéro sous ce nouveau point.
          for (var etage = niveau + 1; etage < compteurs.length; etage++) { compteurs[etage] = 0; }
          marque = MARQUES[niveau](compteurs[niveau]) + ".";
          ligne.setAttribute("data-numero", String(compteurs[niveau]));
        } else {
          if (sorte === "puce") { marque = PUCES[niveau]; }
          ligne.setAttribute("data-numero", "0");
        }

        ligne.setAttribute("data-etiquette", marque);
        if (zone) { zone.setAttribute("data-etiquette", marque); }
      });
    };

    /** Met, change ou retire la sorte de liste d'un paragraphe. */
    var marquerLaLigne = function (ligne, sorte) {
      var champ = ligne.querySelector("input[name='liste[]']");
      if (!champ) { return; }
      ligne.setAttribute("data-liste", sorte);
      champ.value = sorte;

      // Hors d'une liste, la profondeur ne veut plus rien dire.
      if (sorte === "") {
        var etage = ligne.querySelector("input[name='niveau[]']");
        ligne.setAttribute("data-niveau", "0");
        if (etage) { etage.value = "0"; }
      }
      renumeroterListes();
    };

    /**
     * Met, change ou retire le niveau de titre d'un paragraphe.
     *
     * Un titre annonce une section : il ne se numérote pas avec le reste, et
     * sortir de la liste fait donc partie du geste.
     */
    var marquerLeTitre = function (ligne, niveau) {
      var champ = ligne.querySelector("input[name='titre[]']");
      if (!champ) { return; }

      ligne.setAttribute("data-titre", String(niveau));
      champ.value = String(niveau);
      if (niveau > 0 && ligne.getAttribute("data-liste")) { marquerLaLigne(ligne, ""); }
      renumeroterListes();
    };

    /**
     * Décale un élément de liste d'un cran, sans sortir des bornes.
     *
     * Rend « faux » quand il n'y avait plus de cran à prendre : l'appelant
     * sait alors qu'il peut faire autre chose de la touche.
     */
    var changerNiveau = function (ligne, pas) {
      if (!ligne.getAttribute("data-liste")) { return false; }

      var avant = Number(ligne.getAttribute("data-niveau") || 0);
      var apres = Math.min(NIVEAU_MAX, Math.max(0, avant + pas));
      if (apres === avant) { return false; }

      var champ = ligne.querySelector("input[name='niveau[]']");
      ligne.setAttribute("data-niveau", String(apres));
      if (champ) { champ.value = String(apres); }
      renumeroterListes();
      return true;
    };

    /** Le curseur est-il au tout début de la zone, sans rien de sélectionné ? */
    var auDebutDe = function (zone) {
      var selection = document.getSelection();
      if (!selection || selection.rangeCount === 0 || !selection.isCollapsed) { return false; }

      var avant = selection.getRangeAt(0).cloneRange();
      avant.selectNodeContents(zone);
      avant.setEnd(selection.getRangeAt(0).startContainer, selection.getRangeAt(0).startOffset);

      return avant.toString() === "";
    };

    /**
     * Ce que la frappe transforme d'elle-même en liste, comme un traitement de
     * texte : « 1. » ou « 1) » ouvrent une numérotation, « a. » une sous-liste,
     * « i. » une sous-sous-liste, « - » et « * » une suite de puces. La marque
     * tapée disparaît, c'est la liste qui la porte.
     */
    var DEBUTS = [
      { motif: /^1[.)]\s$/, sorte: "numero", niveau: 0 },
      { motif: /^a[.)]\s$/, sorte: "numero", niveau: 1 },
      { motif: /^i[.)]\s$/, sorte: "numero", niveau: 2 },
      { motif: /^[-*]\s$/,  sorte: "puce",   niveau: 0 },
    ];

    var enrichir = function (ligne) {
      var champ = ligne.querySelector("textarea");
      if (!champ || ligne.querySelector("[data-zone-riche]")) { return; }

      var zone = document.createElement("div");
      zone.className = "paragraphe__riche";
      zone.setAttribute("data-zone-riche", "");
      zone.setAttribute("contenteditable", "true");
      zone.setAttribute("role", "textbox");
      zone.setAttribute("aria-multiline", "false");
      zone.setAttribute("aria-label", champ.getAttribute("aria-label") || "Paragraphe");

      var balise = ligne.getAttribute("data-riche-html");
      // Rien de balisé : on part du texte, que le navigateur échappe pour nous.
      if (balise) { zone.innerHTML = balise; } else { zone.textContent = champ.value; }

      champ.hidden = true;
      champ.parentNode.insertBefore(zone, champ.nextSibling);

      /*
       * Le champ que le formulaire envoie est recopié à chaque frappe, et dès
       * maintenant. Attendre l'envoi ne suffisait pas : un formulaire soumis
       * autrement que par son bouton ne déclenche pas cet événement-là, et le
       * document repartait alors sans sa mise en forme.
       */
      var synchroniser = function () { champ.value = zone.innerHTML; };
      synchroniser();
      zone.addEventListener("input", synchroniser);
      zone.addEventListener("focus", function () { zoneChoisie = zone; });

      zone.addEventListener("input", function () {
        if (ligne.getAttribute("data-liste")) { return; }
        // Une ligne qui porte une image ne devient pas une liste : la vider
        // pour y poser la marque emporterait l'image.
        if (zone.querySelector("[data-dessin], [data-ajout]")) { return; }
        for (var i = 0; i < DEBUTS.length; i++) {
          if (DEBUTS[i].motif.test(zone.textContent)) {
            zone.textContent = "";
            marquerLaLigne(ligne, DEBUTS[i].sorte);
            // On descend cran par cran jusqu'à l'étage que la marque annonce.
            while (Number(ligne.getAttribute("data-niveau") || 0) < DEBUTS[i].niveau
              && changerNiveau(ligne, 1)) { /* jusqu'au bon étage */ }
            synchroniser();
            zone.focus();
            return;
          }
        }
      });

      zone.addEventListener("keydown", function (evenement) {
        /*
         * Entrée ouvre le paragraphe suivant : ici, une zone vaut un
         * paragraphe, et un saut de ligne au milieu n'aurait pas de sens. Le
         * nouveau reprend la liste et l'alignement du précédent — c'est ce qui
         * fait qu'une numérotation se poursuit toute seule.
         */
        if (evenement.key === "Enter") {
          evenement.preventDefault();
          // Maj+Entrée : à la ligne, dans le même paragraphe — comme dans Word.
          if (evenement.shiftKey) {
            allerALaLigne(zone);
            return;
          }
          alaLigne(ligne, zone);
          return;
        }

        /*
         * La tabulation décale d'un cran dans une liste, comme dans un
         * traitement de texte. Quand il n'y a plus de cran à prendre, elle
         * reprend son rôle habituel et passe au champ suivant.
         */
        if (evenement.key === "Tab" && ligne.getAttribute("data-liste")) {
          if (changerNiveau(ligne, evenement.shiftKey ? -1 : 1)) {
            evenement.preventDefault();
          }
          return;
        }

        // Revenir en arrière au tout début remonte d'un cran, puis sort de la
        // liste : c'est le geste qui défait ce que « 1. » vient de faire.
        if (evenement.key === "Backspace"
          && ligne.getAttribute("data-liste")
          && auDebutDe(zone)
        ) {
          evenement.preventDefault();
          if (!changerNiveau(ligne, -1)) { marquerLaLigne(ligne, ""); }
        }
      });
      // Un collage apporterait la mise en forme du site d'origine, polices et
      // couleurs comprises : on ne garde que le texte.
      zone.addEventListener("paste", function (evenement) {
        evenement.preventDefault();
        var presse = evenement.clipboardData || window.clipboardData;
        var texte = presse.getData("text");
        // Une image coupée ici pour être collée ailleurs revient avec : c'est
        // ainsi qu'on la déplace au clavier. Rien d'autre ne passe.
        var html = presse.getData ? presse.getData("text/html") : "";
        var avecImages = html && typeof imagesCollables === "function" ? imagesCollables(html) : null;
        if (avecImages) {
          document.execCommand("insertHTML", false, avecImages);
          return;
        }
        document.execCommand("insertText", false, texte.replace(/\s*\n\s*/g, " "));
      });
    };

    if (formulaireDocument && barreOutils && drapeauRiche) {
      try { document.execCommand("styleWithCSS", false, false); } catch (e) { /* vieux navigateur */ }

      [].slice.call(zoneParagraphes.querySelectorAll("[data-paragraphe]")).forEach(enrichir);
      renumeroterListes();
      barreOutils.hidden = false;
      drapeauRiche.value = "1";

      /*
       * Remet l'apparence d'après les attributs.
       *
       * En découpant un passage, le navigateur recopie nos attributs mais jette
       * parfois le style qui les accompagnait : la marque restait dans ce qu'on
       * enregistre — c'est l'attribut qui fait foi — mais ne se voyait plus à
       * l'écran, ce qui est le meilleur moyen de la poser deux fois.
       */
      var repeindre = function (zone) {
        [].slice.call(zone.querySelectorAll("[data-taille]")).forEach(function (span) {
          span.style.fontSize = span.getAttribute("data-taille") + "pt";
        });
        [].slice.call(zone.querySelectorAll("[data-couleur]")).forEach(function (span) {
          var teinte = span.getAttribute("data-couleur");
          if (teinte === "auto") {
            span.style.color = "";
            span.classList.add("riche-couleur-auto");
          } else {
            span.style.color = "#" + teinte;
          }
        });
        [].slice.call(zone.querySelectorAll("[data-fond]")).forEach(function (span) {
          var teinte = span.getAttribute("data-fond");
          if (teinte === "auto") {
            span.style.backgroundColor = "";
            span.classList.add("riche-fond-auto");
          } else {
            span.style.backgroundColor = "#" + teinte;
          }
        });
      };

      /** Recopie chaque zone dans le champ que le formulaire enverra. */
      var recopier = function () {
        [].slice.call(zoneParagraphes.querySelectorAll("[data-paragraphe]")).forEach(function (ligne) {
          var champ = ligne.querySelector("textarea");
          var zone = ligne.querySelector("[data-zone-riche]");
          if (champ && zone) { champ.value = zone.innerHTML; }
        });
      };

      var agir = function (commande, valeur) {
        var zone = reprendreLaSelection();
        if (zone === null) { return; }
        if (document.activeElement !== zone) { zone.focus(); }
        document.execCommand(commande, false, valeur);
        repeindre(zone);
        recopier();
      };

      [].slice.call(barreOutils.querySelectorAll("[data-commande]")).forEach(function (bouton) {
        // « mousedown » plutôt que « click » : la sélection survit au clic.
        bouton.addEventListener("mousedown", function (evenement) {
          evenement.preventDefault();
          agir(bouton.getAttribute("data-commande"));
        });
      });

      /*
       * La taille et la couleur passent par le même détour.
       *
       * « fontSize » ne connaît que sept crans et écrit une balise <font>. On
       * s'en sert comme d'un marqueur — le cran 7 ne servant à rien d'autre
       * ici — puis on remplace ces balises par ce qu'on voulait vraiment.
       * « Celle du document » laisse une balise nue, qui efface la marque d'un
       * cadre englobant sans en poser de nouvelle.
       */
      var marquerLaSelection = function (habiller) {
        var zone = reprendreLaSelection();
        if (zone === null) { return; }
        if (document.activeElement !== zone) { zone.focus(); }

        document.execCommand("fontSize", false, "7");
        [].slice.call(zone.querySelectorAll("font[size='7']")).forEach(function (marque) {
          var remplacant = document.createElement("span");
          habiller(remplacant);
          while (marque.firstChild) { remplacant.appendChild(marque.firstChild); }
          marque.parentNode.replaceChild(remplacant, marque);
        });
        repeindre(zone);
        recopier();
      };

      var choixTaille = barreOutils.querySelector("[data-taille-texte]");
      if (choixTaille) {
        choixTaille.addEventListener("change", function () {
          var taille = choixTaille.value;
          marquerLaSelection(function (span) {
            if (!taille) { return; }
            span.setAttribute("data-taille", taille);
            span.style.fontSize = taille + "pt";
          });
          choixTaille.selectedIndex = 0;
        });
      }

      /*
       * La couleur du moment. Le nuancier sert à la choisir, le bouton « A » à
       * la poser : rouvrir le nuancier pour reprendre la même couleur ne
       * déclenche aucun événement, et sans ce bouton il n'y aurait alors plus
       * moyen de l'appliquer ailleurs.
       */
      var choixCouleur = barreOutils.querySelector("[data-couleur-texte]");
      var appliquerCouleur = barreOutils.querySelector("[data-couleur-appliquer]");

      var poserLaCouleur = function () {
        if (!choixCouleur) { return; }
        var couleur = choixCouleur.value.replace("#", "").toUpperCase();
        if (!/^[0-9A-F]{6}$/.test(couleur)) { return; }
        marquerLaSelection(function (span) {
          span.setAttribute("data-couleur", couleur);
          span.style.color = "#" + couleur;
        });
      };

      if (choixCouleur) {
        var montrerLaCouleur = function () {
          if (appliquerCouleur) { appliquerCouleur.style.color = choixCouleur.value; }
        };
        montrerLaCouleur();
        choixCouleur.addEventListener("input", montrerLaCouleur);
        // Choisir une couleur l'applique aussitôt, quand du texte est retenu.
        choixCouleur.addEventListener("change", function () {
          montrerLaCouleur();
          poserLaCouleur();
        });
      }

      if (appliquerCouleur) {
        appliquerCouleur.addEventListener("mousedown", function (evenement) {
          evenement.preventDefault();
          poserLaCouleur();
        });
      }

      /*
       * L'alignement porte sur le paragraphe entier : il suffit d'avoir le
       * curseur dedans, sans rien sélectionner. Il ne passe pas par
       * « execCommand », qui alignerait aussi les zones voisines si la
       * sélection les touchait.
       */
      [].slice.call(barreOutils.querySelectorAll("[data-aligner]")).forEach(function (bouton) {
        bouton.addEventListener("mousedown", function (evenement) {
          evenement.preventDefault();
          var zone = reprendreLaSelection();
          if (zone === null) { return; }

          var ligne = zone.closest("[data-paragraphe]");
          var champ = ligne ? ligne.querySelector("input[name='alignement[]']") : null;
          if (!ligne || !champ) { return; }

          ligne.setAttribute("data-aligne", bouton.getAttribute("data-aligner"));
          champ.value = bouton.getAttribute("data-aligner");
        });
      });

      /*
       * La liste se met et se retire sur le paragraphe où l'on a le curseur.
       * Chaque bouton bascule sa propre sorte : cliquer sur la numérotation
       * d'un paragraphe à puce le fait passer d'une sorte à l'autre, et
       * recliquer sur la sienne l'en sort.
       */
      [].slice.call(barreOutils.querySelectorAll("[data-liste]")).forEach(function (bouton) {
        bouton.addEventListener("mousedown", function (evenement) {
          evenement.preventDefault();
          var zone = reprendreLaSelection();
          if (zone === null) { return; }

          var ligne = zone.closest("[data-paragraphe]");
          if (!ligne) { return; }

          var sorte = bouton.getAttribute("data-liste");
          marquerLaLigne(ligne, ligne.getAttribute("data-liste") !== sorte ? sorte : "");
        });
      });

      /*
       * Titre 1 et Titre 2 se posent sur le paragraphe où l'on a le curseur,
       * et se retirent en recliquant sur le même bouton.
       */
      [].slice.call(barreOutils.querySelectorAll("[data-titre]")).forEach(function (bouton) {
        bouton.addEventListener("mousedown", function (evenement) {
          evenement.preventDefault();
          var zone = reprendreLaSelection();
          if (zone === null) { return; }

          var ligne = zone.closest("[data-paragraphe]");
          if (!ligne) { return; }

          var niveau = bouton.getAttribute("data-titre");
          marquerLeTitre(ligne, ligne.getAttribute("data-titre") !== niveau ? Number(niveau) : 0);
        });
      });

      // Abaisser ou remonter d'un cran, pour qui préfère la barre à la touche.
      [].slice.call(barreOutils.querySelectorAll("[data-niveau-liste]")).forEach(function (bouton) {
        bouton.addEventListener("mousedown", function (evenement) {
          evenement.preventDefault();
          var zone = reprendreLaSelection();
          if (zone === null) { return; }

          var ligne = zone.closest("[data-paragraphe]");
          if (ligne) { changerNiveau(ligne, Number(bouton.getAttribute("data-niveau-liste"))); }
        });
      });

      /*
       * Le surlignage suit la même mécanique que la couleur du texte : un
       * nuancier pour choisir, un bouton pour poser, un autre pour retirer.
       */
      var choixFond = barreOutils.querySelector("[data-fond-texte]");
      var appliquerFond = barreOutils.querySelector("[data-fond-appliquer]");

      var poserLeFond = function () {
        if (!choixFond) { return; }
        var fond = choixFond.value.replace("#", "").toUpperCase();
        if (!/^[0-9A-F]{6}$/.test(fond)) { return; }
        marquerLaSelection(function (span) {
          span.setAttribute("data-fond", fond);
          span.style.backgroundColor = "#" + fond;
        });
      };

      if (choixFond) {
        var montrerLeFond = function () {
          if (appliquerFond) { appliquerFond.style.backgroundColor = choixFond.value; }
        };
        montrerLeFond();
        choixFond.addEventListener("input", montrerLeFond);
        choixFond.addEventListener("change", function () {
          montrerLeFond();
          poserLeFond();
        });
      }

      if (appliquerFond) {
        appliquerFond.addEventListener("mousedown", function (evenement) {
          evenement.preventDefault();
          poserLeFond();
        });
      }

      var retourFond = barreOutils.querySelector("[data-fond-defaut]");
      if (retourFond) {
        retourFond.addEventListener("mousedown", function (evenement) {
          evenement.preventDefault();
          marquerLaSelection(function (span) {
            span.setAttribute("data-fond", "auto");
            span.className = "riche-fond-auto";
          });
        });
      }

      /*
       * Revenir à la couleur du document. C'est un choix, et non une absence :
       * le passage sort du morceau coloré qui l'englobait. Une classe plutôt
       * qu'un style, pour que la zone le montre sans qu'une couleur en dur se
       * retrouve dans ce qu'on enverra.
       */
      var retourCouleur = barreOutils.querySelector("[data-couleur-defaut]");
      if (retourCouleur) {
        retourCouleur.addEventListener("mousedown", function (evenement) {
          evenement.preventDefault();
          marquerLaSelection(function (span) {
            span.setAttribute("data-couleur", "auto");
            span.className = "riche-couleur-auto";
          });
        });
      }

      /*
       * Les images, dans le texte.
       *
       * Une image fait partie de sa phrase : on la glisse où l'on veut, on la
       * coupe pour la coller ailleurs, on l'efface comme un mot. Un clic la
       * choisit, et la barre propose alors sa largeur. « 🖼 Image » en ajoute
       * une à l'endroit du curseur.
       *
       * Le drapeau dit au serveur que les images reviennent à leur place dans
       * le texte : sans lui, une image absente serait gardée en fin de
       * paragraphe, faute de savoir si on l'a effacée ou si la page venait
       * d'une version d'avant.
       */
      var drapeauImages = formulaireDocument.querySelector("[data-images-en-ligne]");
      var largeurPage = Number(formulaireDocument.getAttribute("data-largeur-page") || 0);
      var reserveFichiers = formulaireDocument.querySelector("[data-fichiers-images]");
      var groupeTaille = barreOutils.querySelector("[data-taille-image]");
      var curseurTaille = barreOutils.querySelector("[data-taille-image-curseur]");
      var valeurTaille = barreOutils.querySelector("[data-taille-image-valeur]");
      var origineTaille = barreOutils.querySelector("[data-taille-image-origine]");
      var imageChoisie = null;
      var imagesConnues = {};

      if (drapeauImages && largeurPage > 0) { drapeauImages.value = "1"; }

      var cleImage = function (image) {
        var ajout = image.getAttribute("data-ajout");
        return ajout ? "a:" + ajout : "d:" + image.getAttribute("data-dessin");
      };

      // Chaque image connue est retenue telle quelle : c'est d'après ce
      // modèle, et non d'après le presse-papiers, qu'on la recolle.
      var retenirImage = function (image) {
        var modele = image.cloneNode(true);
        modele.classList.remove("est-choisie");
        imagesConnues[cleImage(image)] = modele;
      };

      [].slice.call(zoneParagraphes.querySelectorAll("[data-zone-riche] [data-dessin]")).forEach(function (image) {
        image.setAttribute("data-largeur-origine", image.getAttribute("data-largeur") || "");
        retenirImage(image);
      });

      var recopierLaLigne = function (element) {
        var ligne = element && element.closest("[data-paragraphe]");
        var zone = ligne && ligne.querySelector("[data-zone-riche]");
        var champ = ligne && ligne.querySelector("textarea");
        if (zone && champ) { champ.value = zone.innerHTML; }
      };

      var enCm = function (px) {
        return String(Math.round(px * 25.4 / 96) / 10).replace(".", ",") + " cm";
      };

      var groupeHabillage = barreOutils.querySelector("[data-habillage-image]");
      var HABILLAGES = ["ligne", "gauche", "centre", "droite"];

      var montrerHabillage = function () {
        if (!groupeHabillage) { return; }
        var actuel = imageChoisie ? imageChoisie.getAttribute("data-habillage") || "" : "";
        // « fixe » : une image que Word place autrement — on la garde telle quelle.
        groupeHabillage.hidden = !(imageChoisie && imageChoisie.tagName === "IMG"
          && HABILLAGES.indexOf(actuel) >= 0);
        [].slice.call(groupeHabillage.querySelectorAll("[data-habillage-choix]")).forEach(function (bouton) {
          bouton.setAttribute("aria-pressed", bouton.getAttribute("data-habillage-choix") === actuel ? "true" : "false");
        });
      };

      if (groupeHabillage) {
        groupeHabillage.addEventListener("mousedown", function (evenement) { evenement.preventDefault(); });
        groupeHabillage.addEventListener("click", function (evenement) {
          var bouton = evenement.target.closest("[data-habillage-choix]");
          if (!bouton || !imageChoisie) { return; }
          imageChoisie.setAttribute("data-habillage", bouton.getAttribute("data-habillage-choix"));
          retenirImage(imageChoisie);
          recopierLaLigne(imageChoisie);
          montrerHabillage();
        });
      }

      var montrerTaille = function () {
        montrerHabillage();
        if (!groupeTaille || !curseurTaille || !valeurTaille) { return; }
        var possible = imageChoisie !== null && imageChoisie.tagName === "IMG"
          && imageChoisie.getAttribute("data-redim") !== "0" && largeurPage > 0;
        groupeTaille.hidden = !possible;
        if (!possible) { return; }
        var largeur = Number(imageChoisie.getAttribute("data-largeur")) || imageChoisie.naturalWidth || largeurPage;
        curseurTaille.value = String(Math.max(3, Math.min(100, Math.round(largeur * 100 / largeurPage))));
        valeurTaille.textContent = enCm(Math.min(largeur, largeurPage));
      };

      var choisirImage = function (image) {
        if (imageChoisie) { imageChoisie.classList.remove("est-choisie"); }
        imageChoisie = image;
        if (image) { image.classList.add("est-choisie"); }
        montrerTaille();
      };

      zoneParagraphes.addEventListener("click", function (evenement) {
        var image = evenement.target.closest
          && evenement.target.closest("[data-zone-riche] img[data-dessin], [data-zone-riche] img[data-ajout]");
        choisirImage(image || null);
        if (!image) { return; }
        // La sélection entoure l'image : Suppr l'efface, Ctrl+X l'emporte.
        var autour = document.createRange();
        autour.selectNode(image);
        var selection = document.getSelection();
        selection.removeAllRanges();
        selection.addRange(autour);
      });
      zoneParagraphes.addEventListener("input", function () {
        if (imageChoisie && !imageChoisie.isConnected) { choisirImage(null); }
      });

      var donnerLargeur = function (image, largeur) {
        largeur = Math.max(8, Math.round(largeurPage > 0 ? Math.min(largeur, largeurPage) : largeur));
        image.setAttribute("data-largeur", String(largeur));
        image.removeAttribute("width");
        image.removeAttribute("height");
        image.style.width = largeur + "px";
        image.style.height = "auto";
        retenirImage(image);
        recopierLaLigne(image);
        if (image === imageChoisie && valeurTaille) { valeurTaille.textContent = enCm(largeur); }
      };

      if (curseurTaille) {
        curseurTaille.addEventListener("input", function () {
          if (imageChoisie) { donnerLargeur(imageChoisie, largeurPage * Number(curseurTaille.value) / 100); }
        });
      }
      if (origineTaille) {
        origineTaille.addEventListener("click", function () {
          var origine = imageChoisie ? Number(imageChoisie.getAttribute("data-largeur-origine")) : 0;
          if (origine > 0) {
            donnerLargeur(imageChoisie, origine);
            montrerTaille();
          }
        });
      }

      /*
       * Coller : le texte seul, comme toujours — sauf les images connues de
       * cette page, qu'on reprend d'après leur modèle, avec leur largeur.
       * Une image venue d'ailleurs ne passe pas : on ne sait pas ce qu'elle
       * est, ni si le document pourrait la garder.
       */
      var imagesCollables = function (html) {
        var recu = new DOMParser().parseFromString(html, "text/html");
        var morceaux = [];
        var trouvees = 0;
        var echapper = function (texte) {
          var boite = document.createElement("div");
          boite.textContent = texte;
          return boite.innerHTML;
        };
        var parcourir = function (noeud) {
          [].slice.call(noeud.childNodes).forEach(function (enfant) {
            if (enfant.nodeType === 3) {
              morceaux.push(echapper(enfant.data.replace(/\s*\n\s*/g, " ")));
              return;
            }
            if (enfant.nodeType !== 1 || /^(script|style|head|title)$/i.test(enfant.tagName)) { return; }
            if (enfant.hasAttribute("data-dessin") || enfant.hasAttribute("data-ajout")) {
              var modele = imagesConnues[cleImage(enfant)];
              if (!modele) { return; }
              var copie = modele.cloneNode(true);
              var habillageColle = enfant.getAttribute("data-habillage") || "";
              if (copie.tagName === "IMG" && HABILLAGES.indexOf(habillageColle) >= 0
                && HABILLAGES.indexOf(copie.getAttribute("data-habillage") || "") >= 0) {
                copie.setAttribute("data-habillage", habillageColle);
              }
              var largeur = enfant.getAttribute("data-largeur") || "";
              if (copie.tagName === "IMG" && /^\d{1,5}$/.test(largeur)) {
                copie.setAttribute("data-largeur", largeur);
                copie.style.width = largeur + "px";
                copie.style.height = "auto";
              }
              morceaux.push(copie.outerHTML);
              trouvees++;
              return;
            }
            parcourir(enfant);
          });
        };
        parcourir(recu.body);
        return trouvees > 0 ? morceaux.join("") : null;
      };

      var boutonSaut = barreOutils.querySelector("[data-saut-ligne]");
      if (boutonSaut) {
        // Sur l'appui, et non au clic : le clic ferait perdre le curseur.
        boutonSaut.addEventListener("mousedown", function (evenement) {
          evenement.preventDefault();
          var zone = reprendreLaSelection();
          if (zone) { allerALaLigne(zone); }
        });
      }

      var boutonImage = barreOutils.querySelector("[data-inserer-image]");
      var choixImage = barreOutils.querySelector("[data-choisir-image]");
      var souciImage = barreOutils.querySelector("[data-souci-image]");
      if (boutonImage && choixImage && reserveFichiers && modeleParagraphe && modeleParagraphe.content) {
        var formatsImage = ["image/png", "image/jpeg", "image/gif"];
        var poidsMax = Number(formulaireDocument.getAttribute("data-taille-max") || 0);
        var zoneDeLImage = null;
        var plageDeLImage = null;
        var imagesPosees = 0;

        var direSouci = function (texte) {
          if (!souciImage) { return; }
          souciImage.textContent = texte;
          souciImage.hidden = texte === "";
        };

        var enMo = function (octets) {
          return String(Math.round(octets / 104857.6) / 10).replace(".", ",") + " Mo";
        };

        // Ce que pèsent les images en attente dont l'image est encore dans le
        // texte : elles partiront ensemble.
        var poidsEnAttente = function () {
          var total = 0;
          [].slice.call(reserveFichiers.querySelectorAll("input[type='file']")).forEach(function (champ) {
            var cle = (champ.name.match(/^images\[([a-z0-9]+)\]$/) || [])[1];
            if (cle && champ.files && champ.files[0]
              && zoneParagraphes.querySelector("[data-ajout='" + cle + "']") !== null) {
              total += champ.files[0].size;
            }
          });
          return total;
        };

        // Garder la sélection : c'est elle qui dit où poser l'image.
        boutonImage.addEventListener("mousedown", function (evenement) {
          evenement.preventDefault();
        });
        boutonImage.addEventListener("click", function () {
          var zone = zoneDeLaSelection();
          var selection = document.getSelection();
          if (zone && selection.rangeCount > 0) {
            zoneDeLImage = zone;
            plageDeLImage = selection.getRangeAt(0).cloneRange();
          } else {
            zoneDeLImage = zoneChoisie;
            plageDeLImage = plageChoisie ? plageChoisie.cloneRange() : null;
          }
          direSouci("");
          choixImage.click();
        });

        var poserLImage = function () {
          var champ = choixImage;
          var fichier = champ.files && champ.files[0];
          if (!fichier) { return; }

          if (formatsImage.indexOf(fichier.type) < 0) {
            champ.value = "";
            direSouci("« " + fichier.name + " » n'est pas une image PNG, JPEG ou GIF — les formats que Word ouvre partout.");
            return;
          }
          if (poidsMax > 0 && poidsEnAttente() + fichier.size > poidsMax) {
            champ.value = "";
            direSouci("« " + fichier.name + " » est trop lourde : le serveur accepte " + enMo(poidsMax)
              + " par enregistrement. Enregistrez d'abord, puis ajoutez la suite.");
            return;
          }

          imagesPosees++;
          var cle = "n" + Date.now().toString(36) + imagesPosees;
          var image = document.createElement("img");
          image.className = "riche-image";
          image.setAttribute("data-ajout", cle);
          image.setAttribute("data-redim", "1");
          image.setAttribute("data-habillage", "ligne");
          image.alt = fichier.name.replace(/\.[^.]+$/, "");

          // À l'endroit du curseur ; sans curseur, dans une ligne neuve en fin
          // de document.
          if (zoneDeLImage && zoneDeLImage.isConnected && plageDeLImage
            && zoneDeLImage.contains(plageDeLImage.startContainer)) {
            plageDeLImage.deleteContents();
            plageDeLImage.insertNode(image);
          } else {
            var ligne = modeleParagraphe.content.firstElementChild.cloneNode(true);
            zoneParagraphes.appendChild(ligne);
            enrichir(ligne);
            renumeroter();
            renumeroterListes();
            var zoneNeuve = ligne.querySelector("[data-zone-riche]");
            (zoneNeuve || ligne).appendChild(image);
          }

          // Le fichier attend l'enregistrement, sous la clé que porte l'image.
          champ.removeAttribute("data-choisir-image");
          champ.name = "images[" + cle + "]";
          reserveFichiers.appendChild(champ);

          // Sa largeur d'origine, ramenée à celle de la page, dès qu'on la connaît.
          image.addEventListener("load", function () {
            var largeur = largeurPage > 0 ? Math.min(image.naturalWidth, largeurPage) : image.naturalWidth;
            image.setAttribute("data-largeur-origine", String(largeur));
            donnerLargeur(image, largeur);
            if (image === imageChoisie) { montrerTaille(); }
          }, { once: true });
          image.src = URL.createObjectURL(fichier);
          retenirImage(image);
          recopierLaLigne(image);

          // Un champ neuf pour l'image suivante.
          var neuf = document.createElement("input");
          neuf.type = "file";
          neuf.accept = formatsImage.join(",");
          neuf.hidden = true;
          neuf.setAttribute("data-choisir-image", "");
          neuf.setAttribute("aria-label", "Choisir une image à ajouter");
          neuf.addEventListener("change", poserLImage);
          boutonImage.parentNode.insertBefore(neuf, boutonImage.nextSibling);
          choixImage = neuf;

          choisirImage(image);
          image.scrollIntoView({ block: "nearest" });
        };

        choixImage.addEventListener("change", poserLImage);
      }

      // À l'envoi : la marque de sélection n'a rien à faire dans le document,
      // et le fichier d'une image retirée du texte ne part pas.
      formulaireDocument.addEventListener("submit", function () {
        choisirImage(null);
        if (!reserveFichiers) { return; }
        [].slice.call(reserveFichiers.querySelectorAll("input[type='file']")).forEach(function (champ) {
          var cle = (champ.name.match(/^images\[([a-z0-9]+)\]$/) || [])[1];
          if (!cle || zoneParagraphes.querySelector("[data-ajout='" + cle + "']") === null) {
            champ.parentNode.removeChild(champ);
          }
        });
      });
      formulaireDocument.addEventListener("submit", recopier);
    }

    /*
     * Retenir qu'on a modifié le document : la fenêtre demande alors avant
     * de se fermer, pour qu'un clic à côté ne jette pas une heure de travail.
     * Choisir une image, déplacer le curseur ne compte pas ; changer un mot,
     * une largeur, une liste, si.
     */
    if (formulaireDocument && window.MutationObserver) {
      var marquerModifie = function () { formulaireDocument.setAttribute("data-modifie", ""); };
      new MutationObserver(marquerModifie).observe(zoneParagraphes, {
        childList: true, subtree: true, characterData: true, attributes: true,
        attributeFilter: ["data-largeur", "data-habillage", "data-liste", "data-niveau",
          "data-titre", "data-aligne", "data-couleur", "data-fond", "data-taille"]
      });
      formulaireDocument.addEventListener("input", marquerModifie);
      formulaireDocument.addEventListener("change", marquerModifie);
    }
  }
  };
  initialiserEditeur(document);

  /*
   * Le petit traitement de texte des zones marquées « data-texte-riche » :
   * fiche de révision, notes d'un évènement, et — « complet », avec tous les
   * réglages de l'éditeur de documents — contenu d'un cours.
   *
   * La zone de texte reste dans le formulaire, cachée : c'est elle qui part.
   * On écrit dans un bloc éditable posé à sa place, et ce qu'il contient y est
   * recopié à chaque frappe, derrière la marque qui dit « mis en forme ». Le
   * serveur nettoie ce HTML à l'arrivée ; sans script, la zone de texte reste
   * une zone de texte.
   */
  var MARQUE_RICHE = '<!--riche-->';

  /*
   * La barre. Réduite pour une fiche ou des notes ; « complète » pour le
   * contenu d'un cours, avec tout ce que propose l'éditeur de documents :
   * alignement, retraits, titres, sommaire, taille, saut de ligne, images.
   */
  var barreRiche = function (complet, tailles) {
    var bouton = function (attributs, titre, contenu) {
      var classe = /class="/.test(attributs) ? '' : 'class="barre-outils__bouton" ';
      return '<button type="button" ' + classe + attributs + ' title="' + titre + '">' + contenu + '</button>';
    };
    var groupe = function (contenu, attributs) {
      return '<span class="barre-outils__couleurs"' + (attributs || '') + '>' + contenu + '</span>';
    };
    var h = bouton('data-riche="bold" aria-pressed="false"', 'Gras (Ctrl+B)', '<b>G</b>')
      + bouton('data-riche="italic" aria-pressed="false"', 'Italique (Ctrl+I)', '<i>I</i>')
      + bouton('data-riche="underline" aria-pressed="false"', 'Souligné (Ctrl+U)', '<u>S</u>');

    var listes = bouton('data-riche="insertUnorderedList" aria-pressed="false"', 'Liste à puces', '•—')
      + bouton('data-riche="insertOrderedList" aria-pressed="false"', 'Liste numérotée', '1—');
    if (complet) {
      h += groupe(
        bouton('data-riche="justifyLeft" aria-pressed="false"', 'Aligner à gauche', '<span aria-hidden="true">◧</span><span class="sr-only">Aligner à gauche</span>')
        + bouton('data-riche="justifyCenter" aria-pressed="false"', 'Centrer', '<span aria-hidden="true">▣</span><span class="sr-only">Centrer</span>')
        + bouton('data-riche="justifyRight" aria-pressed="false"', 'Aligner à droite', '<span aria-hidden="true">◨</span><span class="sr-only">Aligner à droite</span>')
        + listes
        + bouton('data-riche-retrait="1"', 'Sous-liste, ou retrait (Tab)', '<span aria-hidden="true">⇥</span><span class="sr-only">Abaisser d’un niveau</span>')
        + bouton('data-riche-retrait="-1"', 'Remonter d’un niveau (Maj+Tab)', '<span aria-hidden="true">⇤</span><span class="sr-only">Remonter d’un niveau</span>'));
      h += groupe(
        bouton('data-riche-titre="h2" aria-pressed="false"', 'Mettre ou retirer le Titre 1', 'T1')
        + bouton('data-riche-titre="h3" aria-pressed="false"', 'Mettre ou retirer le Titre 2', 'T2')
        + bouton('data-riche-titre="h4" aria-pressed="false"', 'Mettre ou retirer le Titre 3', 'T3'));
      h += '<label class="barre-outils__taille"><span class="discret">Sommaire</span>'
        + '<select data-riche-sommaire title="Jusqu’à quel niveau de titre le sommaire descend">'
        + '<option value="0">Aucun</option><option value="1">Titres 1</option>'
        + '<option value="2">Jusqu’aux Titres 2</option><option value="3">Jusqu’aux Titres 3</option>'
        + '</select></label>';
      h += '<label class="barre-outils__taille"><span class="discret">Taille</span><select data-riche-taille>'
        + '<option value="">Celle du texte</option>'
        + tailles.map(function (t) { return '<option value="' + t + '">' + t + ' pt</option>'; }).join('')
        + '</select></label>';
    } else {
      h += listes;
    }

    h += groupe('<span class="discret">Couleur</span>'
      + bouton('class="barre-outils__bouton barre-outils__appliquer" data-riche-couleur', 'Appliquer cette couleur au texte choisi',
        '<span aria-hidden="true">A</span><span class="barre-outils__trait"></span><span class="sr-only">Appliquer la couleur</span>')
      + '<input type="color" class="barre-outils__couleur" data-riche-teinte value="#dc2626" aria-label="Choisir la couleur du texte">'
      + bouton('data-riche-couleur-defaut', 'Remettre la couleur normale', '⌫'));
    h += groupe('<span class="discret">Surlignage</span>'
      + bouton('class="barre-outils__bouton barre-outils__surligner" data-riche-fond', 'Surligner le texte choisi',
        '<span aria-hidden="true">🖍</span><span class="sr-only">Surligner</span>')
      + '<input type="color" class="barre-outils__couleur" data-riche-fond-teinte value="#ffff00" aria-label="Choisir la couleur du surlignage">'
      + bouton('data-riche-fond-defaut', 'Retirer le surlignage', '⌫'));

    if (complet) {
      h += bouton('data-riche-saut', 'Aller à la ligne sans changer de paragraphe (Maj+Entrée)', '↵');
      h += bouton('class="barre-outils__bouton barre-outils__image" data-riche-image', 'Ajouter une image à l’endroit du curseur',
        '<span aria-hidden="true">🖼</span> Image')
        + '<input type="file" accept="image/png,image/jpeg,image/gif,image/webp" hidden data-riche-fichier aria-label="Choisir une image à ajouter">';
      h += groupe('<span class="discret">Largeur</span>'
        + '<input type="range" min="10" max="100" step="1" value="100" data-riche-largeur aria-label="Largeur de l’image, en part de la largeur du texte">'
        + '<output data-riche-largeur-valeur>—</output>'
        + bouton('data-riche-largeur-origine', 'Toute la largeur', '↺'), ' data-riche-groupe-image hidden');
      h += groupe('<span class="discret">Texte</span>'
        + bouton('data-riche-habillage="ligne" aria-pressed="false"', 'L’image dans la ligne, comme un mot', '▭')
        + bouton('data-riche-habillage="gauche" aria-pressed="false"', 'L’image à gauche, le texte à sa droite', '◧≡')
        + bouton('data-riche-habillage="centre" aria-pressed="false"', 'L’image centrée, le texte au-dessus et en dessous', '▣')
        + bouton('data-riche-habillage="droite" aria-pressed="false"', 'L’image à droite, le texte à sa gauche', '≡◨'), ' data-riche-groupe-image hidden');
      h += '<p class="message-erreur barre-outils__souci" data-riche-souci role="alert" hidden></p>';
    }

    return h;
  };

  /*
   * Une image choisie sur l'ordinateur, prête à ranger dans le texte : ramenée à
   * 1600 pixels au plus, et réencodée si elle est trop lourde. Elle voyage dans
   * la page elle-même ; le serveur n'en garde que les formats d'image connus.
   */
  var IMAGE_RICHE_MAX = 6 * 1024 * 1024;
  var preparerImage = function (fichier, fini, echec) {
    if (!/^image\/(png|jpeg|gif|webp)$/.test(fichier.type)) {
      echec('Seules les images PNG, JPEG, GIF ou WebP peuvent être ajoutées.');
      return;
    }
    var lecteur = new FileReader();
    lecteur.onerror = function () { echec('Cette image n’a pas pu être lue.'); };
    lecteur.onload = function () {
      var origine = String(lecteur.result);
      var image = new Image();
      image.onerror = function () { echec('Cette image n’a pas pu être lue.'); };
      image.onload = function () {
        var cote = 1600;
        var echelle = Math.min(1, cote / Math.max(image.naturalWidth, image.naturalHeight));
        var resultat = origine;
        // Une image déjà petite et légère reste telle quelle (un GIF garde son animation).
        if (echelle < 1 || origine.length > 1.5 * 1024 * 1024) {
          var toile = document.createElement('canvas');
          toile.width = Math.max(1, Math.round(image.naturalWidth * echelle));
          toile.height = Math.max(1, Math.round(image.naturalHeight * echelle));
          toile.getContext('2d').drawImage(image, 0, 0, toile.width, toile.height);
          resultat = fichier.type === 'image/png' || fichier.type === 'image/gif'
            ? toile.toDataURL('image/png')
            : toile.toDataURL('image/jpeg', 0.85);
          // Un PNG réencodé peut rester lourd : le JPEG tranche.
          if (resultat.length > IMAGE_RICHE_MAX) { resultat = toile.toDataURL('image/jpeg', 0.8); }
        }
        if (resultat.length > IMAGE_RICHE_MAX) {
          echec('Cette image est trop lourde, même réduite.');
          return;
        }
        fini(resultat, Math.round(image.naturalWidth * echelle));
      };
      image.src = origine;
    };
    lecteur.readAsDataURL(fichier);
  };

  var initialiserTexteRiche = function (racine) {
    [].slice.call(racine.querySelectorAll('textarea[data-texte-riche]')).forEach(function (zone) {
      if (zone.hasAttribute('data-riche-lance') || typeof document.execCommand !== 'function') { return; }
      zone.setAttribute('data-riche-lance', '');
      var complet = zone.getAttribute('data-texte-riche') === 'complet';
      var tailles = (zone.getAttribute('data-tailles') || '').split(',').filter(Boolean);

      var bloc = document.createElement('div');
      bloc.className = 'texte-riche';
      var barre = document.createElement('div');
      barre.className = 'barre-outils';
      barre.setAttribute('role', 'toolbar');
      barre.setAttribute('aria-label', 'Mise en forme');
      barre.innerHTML = barreRiche(complet, tailles);

      var edition = document.createElement('div');
      edition.className = 'texte-riche__zone ' + zone.className;
      edition.contentEditable = 'true';
      edition.setAttribute('role', 'textbox');
      edition.setAttribute('aria-multiline', 'true');
      edition.setAttribute('data-placeholder', zone.getAttribute('placeholder') || '');
      if (zone.style.minHeight) { edition.style.minHeight = zone.style.minHeight; }
      var etiquette = zone.id ? document.querySelector('label[for="' + zone.id + '"]') : null;
      if (etiquette) {
        edition.setAttribute('aria-label', etiquette.textContent.trim());
        etiquette.addEventListener('click', function (e) { e.preventDefault(); edition.focus(); });
      }

      var choixSommaire = barre.querySelector('[data-riche-sommaire]');
      var choixTaille = barre.querySelector('[data-riche-taille]');
      var souci = barre.querySelector('[data-riche-souci]');

      // Le contenu de départ : déjà nettoyé par le serveur s'il est mis en forme,
      // du texte à échapper sinon.
      var depart = zone.value;
      if (depart.indexOf(MARQUE_RICHE) === 0) {
        depart = depart.slice(MARQUE_RICHE.length);
        var niveau = depart.match(/^<!--sommaire:([1-3])-->/);
        if (niveau) {
          depart = depart.slice(niveau[0].length);
          if (choixSommaire) { choixSommaire.value = niveau[1]; }
        }
        edition.innerHTML = depart;
      } else {
        edition.textContent = depart;
        edition.innerHTML = edition.innerHTML.replace(/\n/g, '<br>');
      }

      zone.hidden = true;
      zone.parentNode.insertBefore(bloc, zone);
      bloc.appendChild(barre);
      bloc.appendChild(edition);

      var imageChoisie = null;

      var recopier = function () {
        var html = edition.innerHTML.replace(/\sclass="texte-riche__image-choisie"/g, '');
        var vide = edition.textContent.trim() === '' && !edition.querySelector('li, img');
        var sommaire = choixSommaire && choixSommaire.value !== '0' ? '<!--sommaire:' + choixSommaire.value + '-->' : '';
        zone.value = vide ? '' : MARQUE_RICHE + sommaire + html;
        zone.dispatchEvent(new Event('input', { bubbles: true }));
      };

      var dire = function (message) {
        if (!souci) { return; }
        souci.textContent = message;
        souci.hidden = message === '';
      };

      // La sélection se perd quand on ouvre un nuancier ou un menu : on garde la dernière.
      var plage = null;
      var majEtats = function () {
        [].slice.call(barre.querySelectorAll('[data-riche]')).forEach(function (bouton) {
          var actif = false;
          try { actif = document.queryCommandState(bouton.getAttribute('data-riche')); } catch (e) {}
          bouton.setAttribute('aria-pressed', actif ? 'true' : 'false');
        });
        var bloc = '';
        try { bloc = String(document.queryCommandValue('formatBlock') || '').toLowerCase(); } catch (e) {}
        [].slice.call(barre.querySelectorAll('[data-riche-titre]')).forEach(function (bouton) {
          bouton.setAttribute('aria-pressed', bloc === bouton.getAttribute('data-riche-titre') ? 'true' : 'false');
        });
        if (choixTaille && plage) {
          var noeud = plage.startContainer.nodeType === 1 ? plage.startContainer : plage.startContainer.parentNode;
          var porteur = noeud && noeud.closest ? noeud.closest('span[style*="font-size"]') : null;
          var pt = porteur && edition.contains(porteur) ? parseInt(porteur.style.fontSize, 10) : NaN;
          choixTaille.value = isNaN(pt) ? '' : String(pt);
        }
      };
      document.addEventListener('selectionchange', function () {
        var sel = window.getSelection();
        if (sel.rangeCount && edition.contains(sel.getRangeAt(0).commonAncestorContainer)) {
          plage = sel.getRangeAt(0).cloneRange();
          majEtats();
        }
      });

      var remettreSelection = function () {
        // La sélection du moment d'abord, si elle est dans le texte : « selectionchange »
        // arrive un peu après, et la plage gardée pourrait être la précédente.
        var actuelle = window.getSelection();
        if (actuelle.rangeCount && edition.contains(actuelle.getRangeAt(0).commonAncestorContainer)) {
          plage = actuelle.getRangeAt(0).cloneRange();
        }
        edition.focus();
        if (plage) {
          var sel = window.getSelection();
          sel.removeAllRanges();
          sel.addRange(plage);
        }
      };

      var executer = function (commande, valeur) {
        remettreSelection();
        // Couleurs et alignements en style, pour que le serveur les reconnaisse ; le reste en balises.
        document.execCommand('styleWithCSS', false, /^(foreColor|hiliteColor|justify)/.test(commande));
        document.execCommand(commande, false, valeur);
        recopier();
        majEtats();
      };

      var teinte = barre.querySelector('[data-riche-teinte]');
      var fond = barre.querySelector('[data-riche-fond-teinte]');
      var boutonCouleur = barre.querySelector('[data-riche-couleur]');
      var boutonFond = barre.querySelector('[data-riche-fond]');
      var peindre = function () {
        boutonCouleur.style.color = teinte.value;
        boutonFond.style.background = fond.value;
      };
      peindre();

      /* --- Les images ------------------------------------------------------ */
      var groupesImage = [].slice.call(barre.querySelectorAll('[data-riche-groupe-image]'));
      var curseurLargeur = barre.querySelector('[data-riche-largeur]');
      var valeurLargeur = barre.querySelector('[data-riche-largeur-valeur]');

      var habillageDe = function (img) {
        if (img.style.float === 'left') { return 'gauche'; }
        if (img.style.float === 'right') { return 'droite'; }
        return img.style.display === 'block' ? 'centre' : 'ligne';
      };
      var choisirImage = function (img) {
        if (imageChoisie && imageChoisie !== img) { imageChoisie.classList.remove('texte-riche__image-choisie'); }
        imageChoisie = img;
        groupesImage.forEach(function (g) { g.hidden = img === null; });
        if (!img) { return; }
        img.classList.add('texte-riche__image-choisie');
        var largeur = parseInt(img.style.width, 10) || 100;
        curseurLargeur.value = String(largeur);
        valeurLargeur.textContent = largeur + ' %';
        var habillage = habillageDe(img);
        [].slice.call(barre.querySelectorAll('[data-riche-habillage]')).forEach(function (b) {
          b.setAttribute('aria-pressed', b.getAttribute('data-riche-habillage') === habillage ? 'true' : 'false');
        });
      };
      var habiller = function (img, habillage) {
        img.style.float = habillage === 'gauche' ? 'left' : (habillage === 'droite' ? 'right' : '');
        img.style.display = habillage === 'centre' ? 'block' : '';
        img.style.margin = '';
      };

      var insererImage = function (fichier, ou) {
        dire('');
        preparerImage(fichier, function (source, largeurPixels) {
          if (ou) { plage = ou; }
          var utile = Math.max(1, edition.clientWidth - 24);
          var part = Math.max(10, Math.min(100, Math.round(largeurPixels / utile * 100)));
          var img = document.createElement('img');
          img.src = source;
          img.alt = fichier.name.replace(/\.[^.]+$/, '');
          img.style.width = part + '%';
          remettreSelection();
          var sel = window.getSelection();
          if (sel.rangeCount && edition.contains(sel.getRangeAt(0).commonAncestorContainer)) {
            var r = sel.getRangeAt(0);
            r.deleteContents();
            r.insertNode(img);
            r.setStartAfter(img);
            r.collapse(true);
            sel.removeAllRanges();
            sel.addRange(r);
          } else {
            edition.appendChild(img);
          }
          recopier();
          choisirImage(img);
        }, dire);
      };

      if (complet) {
        var champImage = barre.querySelector('[data-riche-fichier]');
        champImage.addEventListener('change', function () {
          if (champImage.files && champImage.files[0]) { insererImage(champImage.files[0]); }
          champImage.value = '';
        });
        curseurLargeur.addEventListener('input', function () {
          if (!imageChoisie) { return; }
          imageChoisie.style.width = curseurLargeur.value + '%';
          valeurLargeur.textContent = curseurLargeur.value + ' %';
          recopier();
        });
        edition.addEventListener('click', function (e) {
          choisirImage(e.target.tagName === 'IMG' ? e.target : null);
        });
        // Glisser une image depuis l'ordinateur la pose là où on la lâche.
        edition.addEventListener('dragover', function (e) {
          if (e.dataTransfer && [].indexOf.call(e.dataTransfer.types || [], 'Files') >= 0) { e.preventDefault(); }
        });
        edition.addEventListener('drop', function (e) {
          var fichiers = e.dataTransfer ? e.dataTransfer.files : null;
          if (!fichiers || !fichiers.length) { return; }
          e.preventDefault();
          var ou = document.caretRangeFromPoint ? document.caretRangeFromPoint(e.clientX, e.clientY) : null;
          insererImage(fichiers[0], ou);
        });
      }

      // Cliquer un bouton ne doit pas voler la sélection au texte.
      barre.addEventListener('mousedown', function (e) {
        if (e.target.closest('button')) { e.preventDefault(); }
      });
      barre.addEventListener('click', function (e) {
        var bouton = e.target.closest('button');
        if (!bouton) { return; }
        if (bouton.hasAttribute('data-riche')) { executer(bouton.getAttribute('data-riche')); }
        if (bouton.hasAttribute('data-riche-couleur')) { executer('foreColor', teinte.value); }
        if (bouton.hasAttribute('data-riche-couleur-defaut')) { executer('foreColor', getComputedStyle(edition).color); }
        if (bouton.hasAttribute('data-riche-fond')) { executer('hiliteColor', fond.value); }
        if (bouton.hasAttribute('data-riche-fond-defaut')) { executer('hiliteColor', 'transparent'); }
        if (bouton.hasAttribute('data-riche-retrait')) {
          executer(bouton.getAttribute('data-riche-retrait') === '1' ? 'indent' : 'outdent');
        }
        if (bouton.hasAttribute('data-riche-titre')) {
          var voulu = bouton.getAttribute('data-riche-titre');
          var courant = '';
          remettreSelection();
          try { courant = String(document.queryCommandValue('formatBlock') || '').toLowerCase(); } catch (err) {}
          // Recliquer sur le même titre le ramène à du texte ordinaire.
          executer('formatBlock', courant === voulu ? 'div' : voulu);
        }
        if (bouton.hasAttribute('data-riche-saut')) {
          remettreSelection();
          if (!document.execCommand('insertLineBreak')) { document.execCommand('insertHTML', false, '<br>'); }
          recopier();
        }
        if (bouton.hasAttribute('data-riche-image')) { barre.querySelector('[data-riche-fichier]').click(); }
        if (bouton.hasAttribute('data-riche-largeur-origine') && imageChoisie) {
          imageChoisie.style.width = '100%';
          choisirImage(imageChoisie);
          recopier();
        }
        if (bouton.hasAttribute('data-riche-habillage') && imageChoisie) {
          habiller(imageChoisie, bouton.getAttribute('data-riche-habillage'));
          choisirImage(imageChoisie);
          recopier();
        }
      });

      // La taille : le navigateur ne sait poser qu'une taille « 1 à 7 » ; on la
      // remplace aussitôt par des points, comme dans l'éditeur de documents.
      if (choixTaille) {
        choixTaille.addEventListener('change', function () {
          remettreSelection();
          document.execCommand('styleWithCSS', false, false);
          document.execCommand('fontSize', false, '7');
          [].slice.call(edition.querySelectorAll('font[size]')).forEach(function (police) {
            [].slice.call(police.querySelectorAll('span')).forEach(function (s) { s.style.fontSize = ''; });
            if (choixTaille.value === '') {
              while (police.firstChild) { police.parentNode.insertBefore(police.firstChild, police); }
              police.remove();
              return;
            }
            var porteur = document.createElement('span');
            porteur.style.fontSize = choixTaille.value + 'pt';
            while (police.firstChild) { porteur.appendChild(police.firstChild); }
            police.replaceWith(porteur);
          });
          recopier();
        });
      }
      if (choixSommaire) { choixSommaire.addEventListener('change', recopier); }

      // Choisir une couleur l'applique aussitôt au texte choisi.
      teinte.addEventListener('change', function () { peindre(); executer('foreColor', teinte.value); });
      fond.addEventListener('change', function () { peindre(); executer('hiliteColor', fond.value); });

      edition.addEventListener('input', recopier);
      edition.addEventListener('keyup', majEtats);
      edition.addEventListener('keydown', function (e) {
        // Une image choisie s'efface au clavier, comme un mot.
        if (imageChoisie && (e.key === 'Delete' || e.key === 'Backspace')) {
          e.preventDefault();
          imageChoisie.remove();
          choisirImage(null);
          recopier();
          return;
        }
        // Dans une liste, la tabulation fait une sous-liste, comme dans l'éditeur de documents.
        if (complet && e.key === 'Tab') {
          var dansListe = false;
          try { dansListe = document.queryCommandState('insertUnorderedList') || document.queryCommandState('insertOrderedList'); } catch (err) {}
          if (dansListe) {
            e.preventDefault();
            executer(e.shiftKey ? 'outdent' : 'indent');
          }
        }
      });
      // Coller n'apporte que le texte — ou, dans un cours, l'image copiée.
      edition.addEventListener('paste', function (e) {
        var presse = e.clipboardData || window.clipboardData;
        if (!presse) { return; }
        e.preventDefault();
        var fichier = complet && presse.files && presse.files.length ? presse.files[0] : null;
        if (fichier && /^image\//.test(fichier.type)) {
          insererImage(fichier);
          return;
        }
        document.execCommand('insertText', false, presse.getData('text/plain'));
      });
      if (zone.form) { zone.form.addEventListener('submit', recopier); }
    });
  };
  initialiserTexteRiche(document);

  /*
   * La fiche de révision — sa copie imprimable, ses lecteurs, ses anneaux —
   * se lance sur une racine : la page, ou la fenêtre où elle vient d'arriver.
   */
  var initialiserFiche = function (racine) {
  /*
   * La copie imprimable de la fiche suit ce qu on tape : sans cela, imprimer
   * avant d avoir enregistre sortirait l ancien texte.
   */
  var zoneFiche = racine.querySelector("#fiche_revision");
  var copieFiche = racine.querySelector("[data-impression-fiche]");
  if (zoneFiche && copieFiche) {
    zoneFiche.addEventListener("input", function () {
      // Mis en forme, le texte vient de l'éditeur d'à côté, tapé ici même.
      if (zoneFiche.value.indexOf(MARQUE_RICHE) === 0) {
        copieFiche.innerHTML = zoneFiche.value.slice(MARQUE_RICHE.length);
      } else {
        copieFiche.textContent = zoneFiche.value;
      }
    });
  }

  /*
   * Le bouton qui termine une lecture défait aussi ce qu'il a fait : une fois
   * tout lu, il propose de remettre à zéro. Se tromper de document, ou vouloir
   * relire, arrive plus souvent qu'on ne croit.
   */
  var direLeBouton = function (bouton, fini, aFaire, aDefaire) {
    if (!bouton) { return; }
    bouton.textContent = fini ? "Annuler" : "Terminer";
    bouton.title = fini ? aDefaire : aFaire;
  };

  /*
   * L'avancement dans un enregistrement : le lecteur reprend là où on s'était
   * arrêté, et prévient le serveur quand on le quitte. L'anneau suit en direct.
   */
  var jetonLecture = racine.querySelector("[data-jeton-lecture]");
  var lecteurs = [].slice.call(racine.querySelectorAll("[data-lecteur]"));

  var jeton = jetonLecture ? jetonLecture.getAttribute("data-jeton-lecture") : "";

  /*
   * L'avancement de toute la fiche : la moyenne des anneaux qu'elle contient.
   * On la relit sur les anneaux eux-mêmes plutôt que de tenir un compte à
   * part — c'est ce qui est affiché qui fait foi, et le serveur calcule
   * exactement pareil au chargement suivant.
   */
  var totalFiche = racine.querySelector("[data-total-fiche]");

  var majTotalFiche = function () {
    if (!totalFiche) { return; }

    var parts = [].slice.call(racine.querySelectorAll("[data-avancement] .anneau"));
    if (!parts.length) { return; }

    var somme = 0;
    parts.forEach(function (anneau) {
      // Un anneau qu'on ne sait pas encore mesurer vaut zéro, comme au serveur.
      if (anneau.classList.contains("anneau--inconnu")) { return; }
      var trait = anneau.querySelector(".anneau__part");
      somme += parseFloat((trait.getAttribute("stroke-dasharray") || "0").split(" ")[0]) || 0;
    });

    var moyenne = Math.round(somme / parts.length);
    var trait = totalFiche.querySelector(".anneau__part");
    var texte = totalFiche.querySelector(".anneau__texte");
    var anneau = totalFiche.querySelector(".anneau");
    if (trait) { trait.setAttribute("stroke-dasharray", moyenne + " 100"); }
    if (texte) { texte.innerHTML = moyenne + "<span class='anneau__pourcent'>%</span>"; }
    if (anneau) {
      anneau.classList.remove("anneau--inconnu");
      anneau.classList.toggle("anneau--fini", moyenne >= 100);
    }
  };

  if (jetonLecture && lecteurs.length) {

    var minutage = function (secondes) {
      secondes = Math.max(0, Math.round(secondes));
      var h = Math.floor(secondes / 3600);
      var m = Math.floor((secondes % 3600) / 60);
      var s = secondes % 60;
      var deux = function (n) { return n < 10 ? "0" + n : String(n); };
      return h > 0 ? h + ":" + deux(m) + ":" + deux(s) : m + ":" + deux(s);
    };

    lecteurs.forEach(function (lecteur) {
      var id = lecteur.getAttribute("data-lecteur");
      var bloc = racine.querySelector("[data-avancement='" + id + "']");
      var anneau = bloc ? bloc.querySelector(".anneau") : null;
      var trait = anneau ? anneau.querySelector(".anneau__part") : null;
      var texte = anneau ? anneau.querySelector(".anneau__texte") : null;
      var horloge = bloc ? bloc.querySelector(".fichier__minutage") : null;
      var dernierEnvoi = 0;
      var repris = false;

      var peindre = function () {
        if (!lecteur.duration || !isFinite(lecteur.duration)) { return; }
        var part = Math.max(0, Math.min(100, Math.round(lecteur.currentTime / lecteur.duration * 100)));
        // Les toutes dernières secondes valent la fin : même règle que le serveur.
        if (lecteur.duration - lecteur.currentTime <= 5) { part = 100; }
        if (trait) { trait.setAttribute("stroke-dasharray", part + " 100"); }
        if (texte) { texte.innerHTML = part + "<span class='anneau__pourcent'>%</span>"; }
        if (anneau) {
          anneau.classList.remove("anneau--inconnu");
          anneau.classList.toggle("anneau--fini", part >= 100);
        }
        if (horloge) {
          horloge.textContent = minutage(lecteur.currentTime) + " / " + minutage(lecteur.duration);
        }
        majTotalFiche();
      };

      var envoyer = function () {
        if (!lecteur.duration || !isFinite(lecteur.duration)) { return; }
        var corps = new URLSearchParams();
        corps.set("_csrf", jeton);
        corps.set("position", String(Math.round(lecteur.currentTime)));
        corps.set("duree", String(Math.round(lecteur.duration)));
        var url = lecteur.getAttribute("data-position-url");
        // sendBeacon survit à la fermeture de l'onglet ; fetch prend le relais.
        if (navigator.sendBeacon) {
          navigator.sendBeacon(url, corps);
        } else {
          fetch(url, { method: "POST", body: corps, credentials: "same-origin", keepalive: true });
        }
        dernierEnvoi = Date.now();
      };

      lecteur.addEventListener("loadedmetadata", function () {
        var depart = parseInt(lecteur.getAttribute("data-position") || "0", 10);
        // On ne reprend pas à la toute fin : ce serait rejouer le générique.
        if (!repris && depart > 0 && lecteur.duration - depart > 5) {
          lecteur.currentTime = depart;
        }
        repris = true;
        peindre();
      });

      lecteur.addEventListener("timeupdate", function () {
        peindre();
        // Une écriture toutes les cinq secondes suffit : c'est un repère, pas un chronomètre.
        if (Date.now() - dernierEnvoi > 5000) { envoyer(); }
      });

      ["pause", "ended", "seeked"].forEach(function (nom) {
        lecteur.addEventListener(nom, envoyer);
      });
    });

    // Quitter la page sans avoir mis en pause ne doit pas perdre la position.
    window.addEventListener("pagehide", function () {
      lecteurs.forEach(function (l) {
        if (l.currentTime > 0 && !l.paused) { l.dispatchEvent(new Event("pause")); }
      });
    });
  }

  /*
   * L'avancement dans un PDF. La visionneuse du navigateur ne dit rien de ce
   * qu'on lit : ce sont nos propres flèches qui tournent les pages, et c'est
   * donc par elles qu'on sait où l'on en est. Le cadre est remplacé à chaque
   * fois, car le greffon ne relit pas un fragment changé sur place.
   */
  var documents = [].slice.call(racine.querySelectorAll("[data-pdf]"));

  if (jetonLecture && documents.length) {
    documents.forEach(function (bloc) {
      var pages = parseInt(bloc.getAttribute("data-pages") || "0", 10);
      var page = parseInt(bloc.getAttribute("data-page") || "1", 10);
      // Ouvrir un document ne l'a pas fait lire : l'anneau attend le premier saut.
      var atteinte = parseInt(bloc.getAttribute("data-atteinte") || "0", 10);
      if (!pages || pages < 2) { return; }

      var id = bloc.getAttribute("data-pdf");
      var cadre = bloc.querySelector("iframe");
      var libelle = bloc.querySelector("[data-pdf-libelle]");
      var recule = bloc.querySelector("[data-pdf-recule]");
      var avance = bloc.querySelector("[data-pdf-avance]");
      var mesure = racine.querySelector("[data-avancement='" + id + "']");
      var anneau = mesure ? mesure.querySelector(".anneau") : null;
      var trait = anneau ? anneau.querySelector(".anneau__part") : null;
      var texte = anneau ? anneau.querySelector(".anneau__texte") : null;
      var minutage = mesure ? mesure.querySelector(".fichier__minutage") : null;
      var fini = bloc.querySelector("[data-pdf-fini]");

      var peindre = function () {
        var part = Math.max(0, Math.min(100, Math.round(atteinte / pages * 100)));
        if (trait) { trait.setAttribute("stroke-dasharray", part + " 100"); }
        if (texte) { texte.innerHTML = part + "<span class='anneau__pourcent'>%</span>"; }
        if (anneau) {
          anneau.classList.remove("anneau--inconnu");
          anneau.classList.toggle("anneau--fini", part >= 100);
        }
        if (libelle) { libelle.textContent = "Page " + page + " sur " + pages; }
        if (minutage) {
          minutage.textContent = atteinte > 0 ? "Page " + atteinte + " sur " + pages : "pas encore lu";
        }
        if (recule) { recule.disabled = page <= 1; }
        if (avance) { avance.disabled = page >= pages; }
        direLeBouton(fini, atteinte >= pages,
          "Marquer ce document comme lu", "Remettre ce document comme non lu");
        majTotalFiche();
      };

      var envoyer = function () {
        var corps = new URLSearchParams();
        corps.set("_csrf", jeton);
        // C'est la page atteinte qu'on garde, et non celle qu'on regarde :
        // les deux ne diffèrent qu'après avoir tout remis à zéro.
        corps.set("position", String(atteinte));
        corps.set("duree", String(pages));
        var url = bloc.getAttribute("data-position-url");
        if (navigator.sendBeacon) {
          navigator.sendBeacon(url, corps);
        } else {
          fetch(url, { method: "POST", body: corps, credentials: "same-origin", keepalive: true });
        }
      };

      /* Le greffon ne relit pas un fragment changé sur place : on remplace le cadre. */
      var afficherPage = function (numero) {
        var source = cadre.getAttribute("src").split("#")[0];
        var remplacant = cadre.cloneNode(false);
        remplacant.setAttribute("src", source + "#page=" + numero + "&navpanes=0&view=FitH");
        cadre.replaceWith(remplacant);
        cadre = remplacant;
      };

      var aller = function (voulue) {
        var neuve = Math.max(1, Math.min(pages, voulue));
        if (neuve === page) { return; }
        page = neuve;
        atteinte = neuve;

        afficherPage(page);
        peindre();
        envoyer();
      };

      /*
       * Déclarer le document lu. On ne passe pas par aller() : depuis la dernière
       * page, il n'aurait rien à faire, alors qu'un document ouvert sans être
       * parcouru doit tout de même pouvoir être marqué fini.
       */
      var finir = function () {
        if (page !== pages) {
          page = pages;
          afficherPage(page);
        }
        atteinte = pages;
        peindre();
        envoyer();
      };

      // Et le défaire : le document redevient à lire, ouvert à sa première page.
      var defaire = function () {
        if (page !== 1) {
          page = 1;
          afficherPage(page);
        }
        atteinte = 0;
        peindre();
        envoyer();
      };

      if (recule) { recule.addEventListener("click", function () { aller(page - 1); }); }
      if (avance) { avance.addEventListener("click", function () { aller(page + 1); }); }
      if (fini) {
        fini.addEventListener("click", function () {
          if (atteinte >= pages) { defaire(); } else { finir(); }
        });
      }
      peindre();
    });
  }

  /*
   * Les images de la fiche : une suite qu'on feuillette, comme les pages d'un
   * PDF. Sans script elles s'affichent toutes à la file ; ici on n'en montre
   * qu'une, et l'anneau compte celles qu'on a vues.
   *
   * Ouvrir la fiche ne compte pour rien : c'est en passant d'une image à la
   * suivante qu'on les déclare vues, comme on tourne les pages d'un document.
   */
  var galeries = [].slice.call(racine.querySelectorAll("[data-images]"));

  if (jetonLecture && galeries.length) {
    galeries.forEach(function (bloc) {
      var vues = [].slice.call(bloc.querySelectorAll("[data-image]"));
      var total = vues.length;
      if (!total) { return; }

      var rang = Math.max(0, Math.min(total - 1,
        parseInt(bloc.getAttribute("data-depart") || "0", 10)));
      var libelle = bloc.querySelector("[data-images-libelle]");
      var recule = bloc.querySelector("[data-images-recule]");
      var avance = bloc.querySelector("[data-images-avance]");
      var compte = bloc.querySelector("[data-images-compte]");
      var anneau = bloc.querySelector(".anneau");
      var trait = anneau ? anneau.querySelector(".anneau__part") : null;
      var texte = anneau ? anneau.querySelector(".anneau__texte") : null;
      var fini = bloc.querySelector("[data-images-fini]");

      var comptees = function () {
        return vues.filter(function (v) { return v.getAttribute("data-vue") === "1"; }).length;
      };

      var peindre = function () {
        vues.forEach(function (v, i) { v.hidden = i !== rang; });

        var lues = comptees();
        var part = Math.max(0, Math.min(100, Math.round(lues / total * 100)));
        if (trait) { trait.setAttribute("stroke-dasharray", part + " 100"); }
        if (texte) { texte.innerHTML = part + "<span class='anneau__pourcent'>%</span>"; }
        if (anneau) {
          anneau.classList.remove("anneau--inconnu");
          anneau.classList.toggle("anneau--fini", part >= 100);
        }
        if (libelle) { libelle.textContent = "Image " + (rang + 1) + " sur " + total; }
        if (compte) {
          compte.textContent = lues === 0
            ? "pas encore vue"
            : lues + (lues > 1 ? " images vues sur " : " image vue sur ") + total;
        }
        if (recule) { recule.disabled = rang <= 0; }
        if (avance) { avance.disabled = rang >= total - 1; }
        direLeBouton(fini, lues >= total,
          total > 1 ? "Marquer toutes les images comme vues" : "Marquer cette image comme vue",
          total > 1 ? "Remettre toutes les images comme non vues" : "Remettre cette image comme non vue");
        majTotalFiche();
      };

      var envoyer = function (image, vue) {
        var corps = new URLSearchParams();
        corps.set("_csrf", jeton);
        // Une image tient en une « page », vue ou pas vue.
        corps.set("position", vue ? "1" : "0");
        corps.set("duree", "1");
        var url = image.getAttribute("data-position-url");
        if (navigator.sendBeacon) {
          navigator.sendBeacon(url, corps);
        } else {
          fetch(url, { method: "POST", body: corps, credentials: "same-origin", keepalive: true });
        }
      };

      // Arriver sur une image, c'est avoir vu celles qu'on a traversées.
      var marquer = function (jusqua) {
        for (var i = 0; i <= jusqua; i++) {
          if (vues[i].getAttribute("data-vue") !== "1") {
            vues[i].setAttribute("data-vue", "1");
            envoyer(vues[i], true);
          }
        }
      };

      // Et l'inverse : tout redevient à voir, depuis la première.
      var oublier = function () {
        vues.forEach(function (image) {
          if (image.getAttribute("data-vue") === "1") {
            image.setAttribute("data-vue", "");
            envoyer(image, false);
          }
        });
      };

      var aller = function (voulu) {
        var neuf = Math.max(0, Math.min(total - 1, voulu));
        if (neuf === rang) { return; }
        rang = neuf;
        marquer(rang);
        peindre();
      };

      if (recule) { recule.addEventListener("click", function () { aller(rang - 1); }); }
      if (avance) { avance.addEventListener("click", function () { aller(rang + 1); }); }

      /*
       * Tout déclarer vu. On ne passe pas par aller() : depuis la dernière
       * image, il n'aurait rien à faire, alors qu'une fiche parcourue d'un
       * coup d'oeil doit tout de même pouvoir être marquée finie.
       */
      if (fini) {
        fini.addEventListener("click", function () {
          if (comptees() >= total) {
            rang = 0;
            oublier();
          } else {
            rang = total - 1;
            marquer(total - 1);
          }
          peindre();
        });
      }

      peindre();
    });
  }

  };
  initialiserFiche(document);

  // La fiche seule, ouverte pour être imprimée : l'impression part d'elle-même.
  if (/(^|[?&])imprimer=1(&|$)/.test(window.location.search.slice(1))
    && document.querySelector("[data-impression-fiche]")) {
    window.addEventListener("load", function () { window.print(); });
  }

  /*
   * Fabriquer des cartes : les cours cochés montrent leurs documents.
   *
   * Les listes des différents cours attendent toutes dans la page, cachées et
   * désactivées ; on n'allume que celles des cours retenus. Un « fieldset »
   * désactivé n'envoie rien, ce qui suffit à ce que le formulaire ne parle que
   * des bons cours. Elles s'éteignent aussi quand on décoche « les documents
   * joints » : elles n'auraient alors plus rien à dire, et les cases gardent
   * malgré tout ce qu'on y avait mis.
   */
  var choixCours = [].slice.call(document.querySelectorAll("[data-choix-cours]"));
  var listesDocuments = [].slice.call(document.querySelectorAll("[data-documents]"));
  var caseDocuments = document.querySelector("input[name='sources[]'][value='documents']");

  if (choixCours.length > 0 && listesDocuments.length > 0) {
    var montrerLesDocuments = function () {
      var utile = !caseDocuments || caseDocuments.checked;
      var retenus = choixCours.filter(function (c) { return c.checked; })
        .map(function (c) { return c.value; });

      listesDocuments.forEach(function (liste) {
        var sien = retenus.indexOf(liste.getAttribute("data-documents")) >= 0;
        liste.hidden = !sien;
        liste.disabled = !sien || !utile;
      });
    };

    choixCours.forEach(function (c) { c.addEventListener("change", montrerLesDocuments); });
    if (caseDocuments) { caseDocuments.addEventListener("change", montrerLesDocuments); }
    montrerLesDocuments();
  }

  /*
   * La séance de cartes. Toutes les cartes sont déjà dans la page : le script
   * n'en montre qu'une à la fois, dévoile la réponse à la demande, envoie le
   * verdict et passe à la suivante. Sans lui, la page reste lisible — questions
   * et réponses à la suite, ce qui vaut mieux que rien.
   */
  /*
   * Dans une fonction, et non à même la page : ses variables « ouvrir » et
   * « fermer » écrasaient celles de la fenêtre, et sur une page où une séance
   * était présente, plus aucun lien ne s'ouvrait en fenêtre.
   */
  var initialiserSeance = function (racine) {
  var seance = racine.querySelector("[data-seance]");

  if (seance && !seance.hasAttribute("data-seance-lancee")) {
    seance.setAttribute("data-seance-lancee", "");
    var cartesSeance = [].slice.call(seance.querySelectorAll("[data-carte]"));
    var compteur = seance.querySelector("[data-seance-compteur]");
    var fin = seance.querySelector("[data-seance-fin]");
    var bilan = seance.querySelector("[data-seance-bilan]");
    var jetonSeance = seance.getAttribute("data-jeton");
    var melanger = seance.querySelector("[data-melanger]");
    var rang = 0;
    var sues = 0;
    var rates = 0;
    // Ce qu'on a répondu à chaque carte, pour défaire le compte en revenant.
    var verdicts = [];
    var affichageSu = seance.querySelector("[data-score-su]");
    var affichageRate = seance.querySelector("[data-score-rate]");

    /*
     * L'anneau du paquet entier. Une carte sue monte d'une boîte, une carte
     * ratée retombe en boîte 1 : la moyenne des boîtes bouge à chaque verdict,
     * et l'anneau avec elle, sans rien redemander au serveur. Chaque carte
     * porte la sienne, ce qui survit au mélange des cartes.
     */
    var mesurePaquet = seance.querySelector("[data-anneau-paquet]");
    var totalPaquet = mesurePaquet ? parseInt(mesurePaquet.getAttribute("data-total"), 10) : 0;
    var sommePaquet = mesurePaquet ? parseInt(mesurePaquet.getAttribute("data-somme"), 10) : 0;

    var boiteDe = function (carte) {
      return parseInt(carte.getAttribute("data-boite"), 10) || 1;
    };

    var peindrePaquet = function () {
      if (!mesurePaquet || !totalPaquet) { return; }
      var moyenne = Math.max(1, Math.min(5, sommePaquet / totalPaquet));
      var part = Math.round((moyenne - 1) / 4 * 100);
      var anneau = mesurePaquet.querySelector(".anneau");
      var trait = anneau ? anneau.querySelector(".anneau__part") : null;
      var texte = anneau ? anneau.querySelector(".anneau__texte") : null;

      if (trait) { trait.setAttribute("stroke-dasharray", part + " 100"); }
      if (texte) { texte.innerHTML = part + "<span class='anneau__pourcent'>%</span>"; }
      if (anneau) {
        anneau.classList.remove("anneau--inconnu");
        anneau.classList.toggle("anneau--fini", part >= 100);
        anneau.setAttribute("aria-label", "Avancement du paquet : " + part + " %");
      }
    };

    var montrerCarte = function () {
      cartesSeance.forEach(function (carte, i) {
        carte.hidden = i !== rang;
        if (i === rang) {
          // Chaque carte repart cachée : on ne triche pas d'une carte à l'autre.
          var montrer = carte.querySelector("[data-montrer]");
          carte.querySelector("[data-reponse]").hidden = true;
          montrer.hidden = false;
          montrer.textContent = "Voir la réponse";
          montrer.setAttribute("aria-expanded", "false");
          carte.querySelectorAll("[data-verdict]").forEach(function (b) { b.hidden = true; });
          var arriere = carte.querySelector("[data-precedente]");
          if (arriere) { arriere.hidden = rang === 0; }
        }
      });

      var reste = cartesSeance.length - rang;
      if (compteur) {
        compteur.textContent = reste + " carte" + (reste > 1 ? "s" : "") + " à revoir";
      }
      // Brasser une seule carte n'a pas de sens.
      if (melanger) { melanger.disabled = reste < 2; }
    };

    var peindreScore = function () {
      if (affichageSu) { affichageSu.textContent = String(sues); }
      if (affichageRate) { affichageRate.textContent = String(rates); }
    };

    /*
     * Revenir en arrière retire du score le verdict de la carte qu'on rouvre :
     * le compte doit dire ce qu'on a répondu, pas ce qu'on a répondu puis repris.
     * Le serveur, lui, a déjà noté ce premier verdict ; répondre de nouveau le
     * remplace.
     */
    var revenir = function () {
      if (rang === 0) { return; }
      rang--;

      if (verdicts[rang] !== undefined) {
        if (verdicts[rang]) { sues--; } else { rates--; }
        verdicts[rang] = undefined;
        peindreScore();

        // Et la carte retrouve la boîte qu'elle avait : répondre de nouveau
        // repartira de là, comme au serveur.
        var rouverte = cartesSeance[rang];
        var revenue = parseInt(rouverte.getAttribute("data-boite-avant"), 10);
        if (!isNaN(revenue)) {
          sommePaquet += revenue - boiteDe(rouverte);
          rouverte.setAttribute("data-boite", String(revenue));
          rouverte.removeAttribute("data-boite-avant");
          peindrePaquet();
        }
      }
      montrerCarte();
    };
    var terminer = function () {
      cartesSeance.forEach(function (carte) { carte.hidden = true; });
      if (compteur) { compteur.hidden = true; }
      if (bilan) {
        bilan.textContent = sues + " sue" + (sues > 1 ? "s" : "") + ", "
          + rates + " à revoir, sur " + cartesSeance.length + " carte"
          + (cartesSeance.length > 1 ? "s" : "") + ".";
      }
      if (fin) { fin.hidden = false; }
    };

    /*
     * Les verdicts partent à la file, et non tous à la fois. Revenir sur une
     * carte pour changer d'avis en envoie deux coup sur coup, et c'est le
     * dernier qui doit l'emporter : deux requêtes lâchées ensemble n'arrivent
     * pas forcément dans l'ordre où on les a lancées, et la carte finirait dans
     * la mauvaise boîte — ce que l'anneau du paquet montrerait au rechargement.
     */
    var file = Promise.resolve();

    var envoyer = function (carte, sue) {
      // Dans une fenêtre, la page derrière compte encore cette carte à revoir :
      // elle se rechargera quand la fenêtre se fermera.
      if (seance.closest(".fenetre__corps")) {
        document.dispatchEvent(new CustomEvent("fenetre:changee"));
      }
      var corps = new URLSearchParams();
      corps.set("_csrf", jetonSeance);
      corps.set("sue", sue ? "1" : "0");
      file = file.then(function () {
        return fetch(carte.getAttribute("data-url"), {
          method: "POST", body: corps, credentials: "same-origin", keepalive: true,
        });
      }).catch(function () {
        // Le réseau a lâché : la séance continue, les suivantes passeront.
      });
    };

    var repondre = function (carte, sue) {
      envoyer(carte, sue);

      // La carte change de boîte à l'instant même, comme au serveur : l'anneau
      // du paquet le montre sans attendre la réponse de celui-ci.
      var avant = boiteDe(carte);
      var apres = sue ? Math.min(5, avant + 1) : 1;
      carte.setAttribute("data-boite-avant", String(avant));
      carte.setAttribute("data-boite", String(apres));
      sommePaquet += apres - avant;
      peindrePaquet();

      verdicts[rang] = sue;
      if (sue) { sues++; } else { rates++; }
      peindreScore();
      rang++;
      if (rang >= cartesSeance.length) { terminer(); } else { montrerCarte(); }
    };

    /*
     * Mélanger ne touche qu'à ce qui reste : les cartes déjà tranchées gardent
     * leur place, et le compte des verdicts ne bouge pas. On brasse le tableau,
     * puis on remet les nœuds dans le même ordre pour que la page suive.
     */
    if (melanger) {
      melanger.addEventListener("click", function () {
        var restantes = cartesSeance.slice(rang);
        for (var i = restantes.length - 1; i > 0; i--) {
          var j = Math.floor(Math.random() * (i + 1));
          var garde = restantes[i];
          restantes[i] = restantes[j];
          restantes[j] = garde;
        }
        cartesSeance = cartesSeance.slice(0, rang).concat(restantes);
        restantes.forEach(function (carte) {
          if (fin) { fin.parentNode.insertBefore(carte, fin); }
        });
        montrerCarte();
      });
    }

    /*
     * Sur une fiche de révision, la séance est repliée : le lien « Réviser »
     * mène à la page de révision, et le script l'intercepte pour la déplier ici
     * même. Sans lui, le lien fait ce qu'il annonce, et rien n'est perdu.
     */
    var repli = racine.querySelector("[data-seance-sur-place]");
    var resume = racine.querySelector("[data-cartes-resume]");
    var ouvrir = racine.querySelector("[data-ouvrir-seance]");

    if (repli && ouvrir) {
      ouvrir.addEventListener("click", function (e) {
        e.preventDefault();
        repli.hidden = false;
        if (resume) { resume.hidden = true; }
        repli.scrollIntoView({ block: "nearest" });
      });
    }

    /*
     * Refaire le tour : on remet le rang et le score à zéro, et la première
     * carte revient. Les cartes elles-mêmes n'ont pas bougé de la page, il n'y
     * a donc rien à recharger.
     */
    var recommencer = seance.querySelector("[data-recommencer]");
    if (recommencer) {
      recommencer.addEventListener("click", function () {
        rang = 0;
        sues = 0;
        rates = 0;
        verdicts = [];
        // Les boîtes, elles, restent où la séance les a mises : refaire le tour
        // fait remonter les cartes une seconde fois, et c'est bien le but.
        cartesSeance.forEach(function (c) { c.removeAttribute("data-boite-avant"); });
        if (affichageSu) { affichageSu.textContent = "0"; }
        if (affichageRate) { affichageRate.textContent = "0"; }
        if (fin) { fin.hidden = true; }
        if (compteur) { compteur.hidden = false; }
        montrerCarte();
        seance.scrollIntoView({ block: "nearest" });
      });
    }

    var fermer = seance.querySelector("[data-fermer-seance]");
    if (fermer) {
      // Les compteurs de la page ont vieilli pendant la séance : on repart du serveur.
      fermer.addEventListener("click", function () {
        // Dans une fenêtre, c'est la fiche qu'on relit, pas la page derrière.
        if (seance.closest(".fenetre__corps")) {
          document.dispatchEvent(new CustomEvent("fenetre:relire"));
          return;
        }
        window.location.reload();
      });
    }
    cartesSeance.forEach(function (carte) {
      carte.querySelector("[data-montrer]").addEventListener("click", function (e) {
        var reponse = carte.querySelector("[data-reponse]");
        var bouton = e.currentTarget;
        var visible = reponse.hidden;

        reponse.hidden = !visible;
        bouton.textContent = visible ? "Cacher la réponse" : "Voir la réponse";
        bouton.setAttribute("aria-expanded", visible ? "true" : "false");

        // Une fois la réponse vue, on peut trancher, même en la recachant
        // pour se réciter la carte une dernière fois.
        carte.querySelectorAll("[data-verdict]").forEach(function (b) { b.hidden = false; });
      });

      var arriere = carte.querySelector("[data-precedente]");
      if (arriere) { arriere.addEventListener("click", revenir); }

      carte.querySelectorAll("[data-verdict]").forEach(function (bouton) {
        bouton.addEventListener("click", function () {
          repondre(carte, bouton.getAttribute("data-verdict") === "1");
        });
      });
    });

    montrerCarte();
  }
  };
  initialiserSeance(document);
})();

/* ==========================================================================
   Relire l'agenda Outlook sans qu'on ait à le demander.

   Le serveur ne pose le repère que lorsqu'il estime qu'il est temps, et il
   le rejuge à la réception : la page ne décide de rien, elle réveille. Si
   quelque chose a changé, on recharge — c'est le seul moyen honnête de
   montrer un calendrier à jour sans le redessiner à moitié.

   Un échec reste silencieux : le bouton de la page Outlook est là pour
   provoquer l'erreur et la lire. Une relecture qu'on n'a pas demandée n'a
   pas à interrompre ce qu'on est en train de faire.
   ========================================================================== */
(function () {
  var repere = document.querySelector("[data-outlook-relire]");
  if (!repere || !window.fetch) { return; }

  var corps = new FormData();
  corps.append("_csrf", repere.getAttribute("data-csrf") || "");
  corps.append("seul", "1");

  fetch(repere.getAttribute("data-outlook-relire"), {
    method: "POST",
    body: corps,
    headers: { Accept: "application/json" },
    credentials: "same-origin"
  })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (bilan) {
      if (!bilan || !bilan.fait || !bilan.change) { return; }
      // Les formulaires renvoyés se rejoueraient au rechargement : on remet
      // l'adresse telle quelle plutôt que de repasser par l'envoi.
      window.location.replace(window.location.href);
    })
    .catch(function () { /* silence : rien n'était promis à l'écran */ });
})();
