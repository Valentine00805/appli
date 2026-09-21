<?php
/**
 * Un travail de groupe vu par son lien public : tout, en lecture seule.
 *
 * @var array $projet
 * @var string $jeton
 * @var list<array> $membres
 * @var list<array> $taches
 * @var list<array> $echeances
 * @var list<array> $fichiers
 */
$maintenant = date('Y-m-d H:i:s');
$avenir = array_filter($echeances, static fn (array $e): bool => (string) $e['fin'] >= $maintenant);
$faites = count(array_filter($taches, static fn (array $t): bool => $t['statut'] === 'fait'));
$avancement = Travaux::avancement(count($taches), $faites);
?>
<div class="entete-page">
  <div>
    <h1>👥 <?= e((string) $projet['nom']) ?></h1>
    <?php if ((string) ($projet['description'] ?? '') !== ''): ?>
      <p><?= nl2br(e((string) $projet['description'])) ?></p>
    <?php endif; ?>
    <p class="discret">Travail de groupe · <?= e(implode(', ', array_map(static fn (array $m): string => (string) $m['nom_affiche'], $membres))) ?></p>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <section class="carte">
      <h2>✅ Qui fait quoi</h2>
      <?php if ($avancement !== null): ?>
        <div class="jauge" title="<?= $avancement ?> % des tâches faites">
          <span style="width:<?= $avancement ?>%;background:var(--accent)"></span>
        </div>
        <p class="discret" style="margin:0 0 .6rem"><?= $faites ?> / <?= count($taches) ?> tâches faites</p>
      <?php endif; ?>
      <?php if ($taches === []): ?>
        <p class="discret">Aucune tâche pour l’instant.</p>
      <?php else: ?>
        <ul class="travaux-public-taches">
          <?php foreach ($taches as $t): ?>
            <?php $fait = $t['statut'] === 'fait'; ?>
            <li<?= $fait ? ' class="travaux-public-taches--faite"' : '' ?>>
              <span aria-hidden="true"><?= Travaux::STATUTS[$t['statut']]['icone'] ?></span>
              <span style="flex:1;min-width:0"><?= e((string) $t['titre']) ?>
                <span class="discret">· <?= e((string) ($t['membre_nom'] ?? 'personne')) ?></span></span>
              <?php $texte = echeance_libelle($t['echeance'], $fait); ?>
              <?php if ($texte !== ''): ?>
                <span class="echeance echeance--<?= e(echeance_etat($t['echeance'], $fait)) ?>"><?= e($texte) ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <?php if ((string) ($projet['document'] ?? '') !== ''): ?>
      <section class="carte">
        <h2>📝 Document commun</h2>
        <div class="texte-riche-affiche"><?= TexteRiche::versHtml((string) $projet['document']) ?></div>
      </section>
    <?php endif; ?>
  </div>

  <div class="pile">
    <section class="carte">
      <h2>📅 Échéances</h2>
      <?php if ($avenir === []): ?>
        <p class="discret">Aucune échéance à venir.</p>
      <?php else: ?>
        <ul class="travaux-echeances">
          <?php foreach ($avenir as $e): ?>
            <?php $journee = (int) $e['journee_entiere'] === 1; ?>
            <li class="travaux-echeance">
              <span class="travaux-echeance__icone" aria-hidden="true"><?= e((string) ($e['type_icone'] ?? Travaux::ICONE_SANS_TYPE)) ?></span>
              <span><strong><?= e((string) $e['titre']) ?></strong><br>
                <?= e(ucfirst(date_fr((string) $e['debut'], !$journee))) ?>
                <?php if ((string) ($e['lieu'] ?? '') !== ''): ?><span class="discret">· <?= e((string) $e['lieu']) ?></span><?php endif; ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <?php if ($fichiers !== []): ?>
      <section class="carte">
        <h2>📎 Fichiers</h2>
        <ul class="liste-fichiers">
          <?php foreach ($fichiers as $f): ?>
            <li class="fichier">
              <span class="fichier__icone" aria-hidden="true"><?= Fichiers::icone((string) $f['mime'], (string) $f['nom_origine']) ?></span>
              <span style="min-width:0">
                <a class="fichier__nom" href="<?= url('g/' . $jeton . '/fichiers/' . (int) $f['id']) ?>" target="_blank" rel="noopener">
                  <?= e((string) $f['nom_origine']) ?></a><br>
                <span class="fichier__meta"><?= e(taille_lisible((int) $f['taille'])) ?></span>
              </span>
              <span class="fichier__actions">
                <a class="bouton bouton--discret bouton--petit" href="<?= url('g/' . $jeton . '/fichiers/' . (int) $f['id'], ['telecharger' => 1]) ?>"
                   title="Télécharger" aria-label="Télécharger <?= e((string) $f['nom_origine']) ?>">⬇</a>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  </div>
</div>
