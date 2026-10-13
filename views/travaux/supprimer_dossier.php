<?php
/**
 * Supprimer un dossier — avec ses sous-dossiers et tous ses fichiers —, ouvert en fenêtre par-dessus les fichiers. Un dossier qui n'est pas vide demande d'en
 * retaper le nom ; le formulaire redemande une dernière fois avant de partir.
 *
 * @var array $projet
 * @var array{dossier: array, sous_dossiers: int, fichiers: list<array>, taille: int, autres: int, droit: bool, permis: bool} $resume
 * @var int $depuis  le dossier où l'on était (0 : la racine) : c'est là qu'on revient
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$nom = (string) $resume['dossier']['nom'];
$vide = $resume['fichiers'] === [] && $resume['sous_dossiers'] === 0;
$retour = url('travaux/' . (int) $projet['id'] . '/fichiers', $depuis > 0 ? ['dossier' => $depuis] : []);
$visibles = array_slice($resume['fichiers'], 0, 8);
?>
<div class="entete-page">
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p style="margin:0 0 .3rem"><a href="<?= $retour ?>">← <?= e((string) $projet['nom']) ?></a></p>
    <?php endif; ?>
    <h1>🗑️ <?= e(t('tr.fd.supprimer_titre', ['nom' => $nom])) ?></h1>
  </div>
</div>

<?php if (!$resume['permis']): ?>
  <div class="flash flash--erreur"><?= e(t($resume['droit'] ? 'tr.fd.supprimer_autres' : 'tr.fd.supprimer_interdit')) ?></div>
  <p class="actions"><a class="bouton bouton--secondaire" href="<?= $retour ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>><?= e(t('tr.ty.annuler')) ?></a></p>
<?php else: ?>
  <form method="post" action="<?= url('travaux/dossiers/' . (int) $resume['dossier']['id'] . '/supprimer') ?>" class="carte travaux-formulaire"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>
        data-confirmation="<?= e(t('tr.fd.supprimer_confirmation', ['nom' => $nom])) ?>">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <input type="hidden" name="depuis" value="<?= (int) $depuis ?>">
    <?php if ($vide): ?>
      <p><?= e(t('tr.fd.supprimer_vide')) ?></p>
    <?php else: ?>
      <div class="flash flash--erreur" role="alert"><?= e(t('tr.fd.supprimer_avertissement')) ?></div>
      <p>
        <strong>
          <?php
          $emporte = [];
          if ($resume['fichiers'] !== []) { $emporte[] = tn('tr.fd.emporte_fichiers', count($resume['fichiers']), ['taille' => taille_lisible($resume['taille'])]); }
          if ($resume['sous_dossiers'] > 0) { $emporte[] = tn('tr.fd.emporte_dossiers', $resume['sous_dossiers']); }
          ?>
          <?= e(implode(' · ', $emporte)) ?>
        </strong>
      </p>
      <?php if ($visibles !== []): ?>
        <ul class="discret" style="margin:.2rem 0 .8rem;padding-left:1.2rem">
          <?php foreach ($visibles as $f): ?><li><?= e((string) $f['nom_origine']) ?></li><?php endforeach; ?>
          <?php if (count($resume['fichiers']) > count($visibles)): ?>
            <li><?= e(t('tr.fd.emporte_suite', ['n' => count($resume['fichiers']) - count($visibles)])) ?></li>
          <?php endif; ?>
        </ul>
      <?php endif; ?>
      <div class="champ">
        <label for="dossier-confirmation"><?= e(t('tr.fd.confirmer_label', ['nom' => $nom])) ?></label>
        <input type="text" id="dossier-confirmation" name="confirmation" required autocomplete="off" autofocus placeholder="<?= e($nom) ?>">
      </div>
    <?php endif; ?>
    <p class="actions">
      <button class="bouton bouton--danger" type="submit">🗑️ <?= e(t($vide ? 'tr.fd.supprimer' : 'tr.fd.supprimer_tout')) ?></button>
      <a class="bouton bouton--secondaire" href="<?= $retour ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>><?= e(t('tr.ty.annuler')) ?></a>
    </p>
  </form>
<?php endif; ?>