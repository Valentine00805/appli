<?php
/**
 * Une tâche de travail de groupe qui m'attend, en tuile : toute la carte se clique (le lien du titre s'étend dessus), le projet et l'échéance
 * s'y lisent en pastilles. Sur l'accueil et sur la page « Groupes ».
 *
 * @var array $t  la tâche (titre, membre_id, projet_id, projet_nom, echeance)
 * @var bool $sansProjet  vrai quand la tuile est rangée sous son projet : inutile alors de le répéter (faux par défaut)
 */
?>
<li class="tache-groupe">
  <a class="tache-groupe__titre" href="<?= url('travaux/' . (int) $t['projet_id']) ?>" data-fenetre><?= e($t['membre_id'] === null ? t('cal.tache_sans_personne', ['titre' => (string) $t['titre']]) : (string) $t['titre']) ?></a>
  <?php
  $texte = echeance_libelle($t['echeance']);
  $ditLeProjet = !($sansProjet ?? false);
  ?>
  <?php // Les pastilles sous le titre, s'il y en a : sans projet à dire ni échéance, pas de ligne vide. ?>
  <?php if ($ditLeProjet || $texte !== ''): ?>
    <span class="tache-groupe__meta">
      <?php if ($ditLeProjet): ?>
        <span class="travaux-projet" title="<?= e(t('tr.li.le_projet')) ?>">👥 <?= e((string) $t['projet_nom']) ?></span>
      <?php endif; ?>
      <?php if ($texte !== ''): ?>
        <span class="echeance echeance--<?= e(echeance_etat($t['echeance'])) ?>"><?= e($texte) ?></span>
      <?php endif; ?>
    </span>
  <?php endif; ?>
  <span class="tache-groupe__fleche" aria-hidden="true">›</span>
</li>
