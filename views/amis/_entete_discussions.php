<?php
/**
 * L'en-tête de la liste des discussions : son pseudo et le crayon « nouveau
 * message », un champ qui filtre les discussions, puis « Messages » et le lien
 * vers les demandes (d'amis et de groupes), avec leur nombre.
 *
 * @var string $lienDemandes  où mène « Demandes »
 */
$moiEntete = Auth::utilisateur();
$demandesEnAttente = count(Amis::demandesRecues(Auth::id())) + Conversations::nombreInvitations(Auth::id());
?>
<div class="discussions-entete">
  <div class="discussions-entete__haut">
    <a class="discussions-entete__compte" href="<?= url('compte') ?>" title="Mon compte"><?= e(Auth::nomAffiche($moiEntete)) ?></a>
    <a class="discussions-entete__nouveau" href="<?= url('discussions/nouvelle') ?>" data-fenetre
       title="Nouveau message" aria-label="Nouveau message">
      <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/>
        <path d="M17.6 3.6a2 2 0 0 1 2.8 2.8L12 14.8 8.5 15.5l.7-3.5z"/>
      </svg>
    </a>
  </div>

  <label class="discussions-recherche">
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
      <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
    </svg>
    <span class="sr-only">Rechercher une discussion</span>
    <input type="search" placeholder="Rechercher" autocomplete="off" data-filtre-liste="[data-liste-discussions]">
  </label>

  <div class="discussions-entete__titres">
    <h2>Messages</h2>
    <a href="<?= e($lienDemandes ?? url('amis')) ?>">Demandes<?php if ($demandesEnAttente > 0): ?> <span class="compteur"><?= $demandesEnAttente > 99 ? '99+' : $demandesEnAttente ?></span><?php endif; ?></a>
  </div>
</div>
