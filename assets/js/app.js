/* Interactions légères — l'application fonctionne aussi sans JavaScript. */
(function () {
  'use strict';

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

  // Les filtres s'appliquent dès qu'on change une valeur
  var filtres = document.querySelector('[data-auto-envoi]');
  if (filtres) {
    filtres.querySelectorAll('select').forEach(function (champ) {
      champ.addEventListener('change', function () { filtres.submit(); });
    });
  }

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
    [].slice.call(colonnePlis.querySelectorAll(".dossier-plier")).forEach(function (marque) {
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

        var bouton = rang.querySelector("[data-plier]");
        if (!bouton) { return; }
        var plie = !!replies[bouton.getAttribute("data-plier")];
        bouton.setAttribute("aria-expanded", plie ? "false" : "true");
        bouton.textContent = plie ? "▸" : "▾";
        bouton.setAttribute("aria-label",
          (plie ? "Déplier « " : "Replier « ") + (rang.getAttribute("data-nom") || "") + " »");
      });
    };

    colonnePlis.addEventListener("click", function (evenement) {
      var bouton = evenement.target.closest && evenement.target.closest("[data-plier]");
      if (!bouton) { return; }
      evenement.preventDefault();

      var id = bouton.getAttribute("data-plier");
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
  [].slice.call(document.querySelectorAll("[data-depot]")).forEach(function (forme) {
    var champ = forme.querySelector("[data-depot-champ]");
    var envoi = forme.querySelector("[data-depot-envoi]");
    if (!champ) { return; }

    // Avec JavaScript, le depot suffit : le bouton ne sert plus qu au clavier.
    var transfertPossible = "DataTransfer" in window && "files" in champ;

    champ.addEventListener("change", function () {
      if (champ.files && champ.files.length) { forme.submit(); }
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
      forme.submit();
    });

    if (envoi) { envoi.textContent = "Joindre les fichiers choisis"; }
  });

  /*
   * Modifier le texte d un document : ajouter, supprimer, et laisser chaque
   * zone grandir avec son contenu.
   */
  var zoneParagraphes = document.querySelector("[data-paragraphes]");
  if (zoneParagraphes) {
    var modeleParagraphe = document.querySelector("[data-modele-paragraphe]");
    var ajoutParagraphe = document.querySelector("[data-ajouter-paragraphe]");

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
    var formulaireDocument = document.querySelector("[data-edition-document]");
    var barreOutils = document.querySelector("[data-barre-outils]");
    var drapeauRiche = document.querySelector("[data-riche]");

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
      if (!zone) { return; }
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
        var texte = (evenement.clipboardData || window.clipboardData).getData("text");
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

      formulaireDocument.addEventListener("submit", recopier);
    }
  }

  /*
   * La copie imprimable de la fiche suit ce qu on tape : sans cela, imprimer
   * avant d avoir enregistre sortirait l ancien texte.
   */
  var zoneFiche = document.getElementById("fiche_revision");
  var copieFiche = document.querySelector("[data-impression-fiche]");
  if (zoneFiche && copieFiche) {
    zoneFiche.addEventListener("input", function () {
      copieFiche.textContent = zoneFiche.value;
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
  var jetonLecture = document.querySelector("[data-jeton-lecture]");
  var lecteurs = [].slice.call(document.querySelectorAll("[data-lecteur]"));

  var jeton = jetonLecture ? jetonLecture.getAttribute("data-jeton-lecture") : "";

  /*
   * L'avancement de toute la fiche : la moyenne des anneaux qu'elle contient.
   * On la relit sur les anneaux eux-mêmes plutôt que de tenir un compte à
   * part — c'est ce qui est affiché qui fait foi, et le serveur calcule
   * exactement pareil au chargement suivant.
   */
  var totalFiche = document.querySelector("[data-total-fiche]");

  var majTotalFiche = function () {
    if (!totalFiche) { return; }

    var parts = [].slice.call(document.querySelectorAll("[data-avancement] .anneau"));
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
      var bloc = document.querySelector("[data-avancement='" + id + "']");
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
  var documents = [].slice.call(document.querySelectorAll("[data-pdf]"));

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
      var mesure = document.querySelector("[data-avancement='" + id + "']");
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
  var galeries = [].slice.call(document.querySelectorAll("[data-images]"));

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
  var seance = document.querySelector("[data-seance]");

  if (seance) {
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
    var repli = document.querySelector("[data-seance-sur-place]");
    var resume = document.querySelector("[data-cartes-resume]");
    var ouvrir = document.querySelector("[data-ouvrir-seance]");

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
      fermer.addEventListener("click", function () { window.location.reload(); });
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
})();
