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
    });

    if (ajoutParagraphe && modeleParagraphe && modeleParagraphe.content) {
      ajoutParagraphe.hidden = false;
      ajoutParagraphe.addEventListener("click", function () {
        var ligne = modeleParagraphe.content.firstElementChild.cloneNode(true);
        zoneParagraphes.appendChild(ligne);
        renumeroter();
        var zone = ligne.querySelector("textarea");
        if (zone) { ajusterHauteur(zone); zone.focus(); }
      });
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
   * L'avancement dans un enregistrement : le lecteur reprend là où on s'était
   * arrêté, et prévient le serveur quand on le quitte. L'anneau suit en direct.
   */
  var jetonLecture = document.querySelector("[data-jeton-lecture]");
  var lecteurs = [].slice.call(document.querySelectorAll("[data-lecteur]"));

  if (jetonLecture && lecteurs.length) {
    var jeton = jetonLecture.getAttribute("data-jeton-lecture");

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

})();
