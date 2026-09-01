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

  // Réordonner les tâches principales en les faisant glisser.
  // Sans JavaScript, les deux flèches de chaque carte prennent le relais.
  var colonne = document.querySelector("[data-listes-triables]");
  var formeOrdre = document.getElementById("forme-ordre");
  if (colonne && formeOrdre && "draggable" in document.createElement("div")) {
    var cartes = [].slice.call(colonne.querySelectorAll(".liste-carte"));

    if (cartes.length > 1) {
      colonne.classList.add("est-triable");

      var ordreDe = function () {
        return [].slice.call(colonne.querySelectorAll(".liste-carte"))
          .map(function (c) { return c.dataset.listeId; });
      };
      var ordreDepart = ordreDe().join(",");
      var glissee = null;

      cartes.forEach(function (carte) {
        carte.draggable = true;

        carte.addEventListener("dragstart", function (evenement) {
          glissee = carte;
          carte.classList.add("liste-carte--glisse");
          evenement.dataTransfer.effectAllowed = "move";
          // Firefox exige une donnée pour démarrer le glissement.
          try { evenement.dataTransfer.setData("text/plain", carte.dataset.listeId); } catch (e) {}
        });

        carte.addEventListener("dragover", function (evenement) {
          if (!glissee || glissee === carte) { return; }
          evenement.preventDefault();
          evenement.dataTransfer.dropEffect = "move";
          // On insère avant ou après selon la moitié survolée.
          var zone = carte.getBoundingClientRect();
          var avant = (evenement.clientY - zone.top) < zone.height / 2;
          colonne.insertBefore(glissee, avant ? carte : carte.nextSibling);
        });

        carte.addEventListener("drop", function (evenement) { evenement.preventDefault(); });

        carte.addEventListener("dragend", function () {
          carte.classList.remove("liste-carte--glisse");
          glissee = null;
          var ordre = ordreDe();
          if (ordre.join(",") === ordreDepart) { return; }
          colonne.classList.add("est-en-cours");
          formeOrdre.elements.ordre.value = ordre.join(",");
          formeOrdre.submit();
        });
      });
    }
  }

})();
