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

    var zoneDeLaSelection = function () {
      var selection = document.getSelection();
      if (!selection || selection.rangeCount === 0) { return null; }
      var noeud = selection.getRangeAt(0).commonAncestorContainer;
      if (noeud.nodeType === 3) { noeud = noeud.parentNode; }

      return noeud && noeud.closest ? noeud.closest("[data-zone-riche]") : null;
    };

    document.addEventListener("selectionchange", function () {
      var zone = zoneDeLaSelection();
      if (zone) { zoneChoisie = zone; }
    });

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
      // Entrée ne doit pas glisser un saut de ligne au milieu d'un paragraphe :
      // ici, une zone vaut un paragraphe.
      zone.addEventListener("keydown", function (evenement) {
        if (evenement.key === "Enter") { evenement.preventDefault(); }
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
      barreOutils.hidden = false;
      drapeauRiche.value = "1";

      /** Recopie chaque zone dans le champ que le formulaire enverra. */
      var recopier = function () {
        [].slice.call(zoneParagraphes.querySelectorAll("[data-paragraphe]")).forEach(function (ligne) {
          var champ = ligne.querySelector("textarea");
          var zone = ligne.querySelector("[data-zone-riche]");
          if (champ && zone) { champ.value = zone.innerHTML; }
        });
      };

      var agir = function (commande, valeur) {
        var zone = zoneDeLaSelection() || zoneChoisie;
        if (zone === null) { return; }
        if (document.activeElement !== zone) { zone.focus(); }
        document.execCommand(commande, false, valeur);
        recopier();
      };

      [].slice.call(barreOutils.querySelectorAll("[data-commande]")).forEach(function (bouton) {
        // « mousedown » plutôt que « click » : la sélection survit au clic.
        bouton.addEventListener("mousedown", function (evenement) {
          evenement.preventDefault();
          agir(bouton.getAttribute("data-commande"));
        });
      });

      var choixTaille = barreOutils.querySelector("[data-taille-texte]");
      if (choixTaille) {
        choixTaille.addEventListener("change", function () {
          var zone = zoneDeLaSelection() || zoneChoisie;
          if (zone === null) { return; }
          if (document.activeElement !== zone) { zone.focus(); }
          /*
           * « fontSize » ne connaît que sept crans et écrit une balise <font>.
           * On s'en sert comme d'un marqueur — le cran 7 ne servant à rien
           * d'autre ici — puis on remplace ces balises par la taille voulue.
           */
          document.execCommand("fontSize", false, "7");
          [].slice.call(zone.querySelectorAll("font[size='7']")).forEach(function (marque) {
            var remplacant = document.createElement("span");
            if (choixTaille.value) {
              remplacant.setAttribute("data-taille", choixTaille.value);
              remplacant.style.fontSize = choixTaille.value + "pt";
            }
            while (marque.firstChild) { remplacant.appendChild(marque.firstChild); }
            marque.parentNode.replaceChild(remplacant, marque);
          });
          choixTaille.selectedIndex = 0;
          recopier();
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
        majTotalFiche();
      };

      var envoyer = function () {
        var corps = new URLSearchParams();
        corps.set("_csrf", jeton);
        corps.set("position", String(page));
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

      if (recule) { recule.addEventListener("click", function () { aller(page - 1); }); }
      if (avance) { avance.addEventListener("click", function () { aller(page + 1); }); }
      var fini = bloc.querySelector("[data-pdf-fini]");
      if (fini) { fini.addEventListener("click", finir); }
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
