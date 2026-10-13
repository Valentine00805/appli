<?php
/**
 * L'aperçu d'un fichier du groupe, en fenêtre par-dessus les fichiers (ou en page).
 *
 * @var array $projet
 * @var array $fichier
 * @var string $onglet  fichiers ou livrables : d'où l'on vient
 * @var string $genre  pdf, image, brut, tableur, document, audio ou video
 * @var list<string> $paragraphes
 * @var list<list<string>> $lignes
 * @var string $texte, $format
 * @var bool $tronque
 * @var int $total, $limite
 * @var ?string $erreur
 * @var ?array $diaporama
 * @var bool $peutDiapos
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$id = (int) $fichier['id'];
$nom = (string) $fichier['nom_origine'];
$adresse = url('travaux/fichiers/' . $id);
$telecharger = url('travaux/fichiers/' . $id, ['telecharger' => 1]);
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large data-document' : '' ?>>
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p class="discret" style="margin-bottom:.35rem"><a href="<?= url('travaux/' . (int) $projet['id'] . '/' . $onglet) ?>">← <?= e((string) $projet['nom']) ?></a></p>
    <?php endif; ?>
    <h1><?= e(Fichiers::icone((string) $fichier['mime'], $nom)) ?> <?= e($nom) ?></h1>
    <p><?= e(ucfirst($format)) ?> · <?= e(taille_lisible((int) $fichier['taille'])) ?></p>
  </div>
  <div class="actions">
    <a class="bouton bouton--secondaire" href="<?= $telecharger ?>">⬇ <?= e(t('tr.fi.telecharger')) ?></a>
  </div>
</div>

<?php if ($diaporama !== null): ?>
  <div class="flash flash--info" style="margin-bottom:1.25rem">
    <?= t('ap.diapo_aide') ?>
    <a href="<?= url('travaux/fichiers/' . $id . '/apercu', ['texte' => 1]) ?>"<?= $dansUneFenetre ? ' data-fenetre-dessus' : '' ?>><?= e(t('ap.diapo_texte')) ?></a>
  </div>
<?php elseif (!in_array($genre, ['pdf', 'image', 'audio', 'video'], true) && $erreur === null): ?>
  <div class="flash flash--info" style="margin-bottom:1.25rem">
    <?php if ($peutDiapos): ?>
      <?= t('ap.texte_aide', ['format' => e($format)]) ?>
      <a href="<?= url('travaux/fichiers/' . $id . '/apercu') ?>"<?= $dansUneFenetre ? ' data-fenetre-dessus' : '' ?>><?= e(t('ap.diapo_voir')) ?></a>
    <?php elseif ($genre === 'tableur'): ?>
      <?= t('ap.tableur_aide') ?>
    <?php elseif ($genre === 'brut'): ?>
      <?= t('ap.brut_aide') ?>
    <?php else: ?>
      <?= t('ap.texte_aide', ['format' => e($format)]) ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($erreur !== null): ?>
  <div class="vide">
    <span class="vide__icone">⚠️</span>
    <p><?= e($erreur) ?></p>
    <a class="bouton bouton--secondaire" href="<?= $telecharger ?>"><?= e(t('commun.telecharger_fichier')) ?></a>
  </div>
<?php elseif ($diaporama !== null): ?>
  <div class="diapos">
    <?php foreach ($diaporama['diapos'] as $d): ?>
      <figure class="diapo-carte">
        <?= $d['html'] ?>
        <figcaption><?= e(t('ap.diapo_legende', ['n' => $d['numero'], 'total' => $diaporama['total']])) ?><?= $d['titre'] !== '' ? ' — ' . e($d['titre']) : '' ?></figcaption>
      </figure>
    <?php endforeach; ?>
  </div>
  <?php if ($diaporama['tronque']): ?>
    <p class="champ__aide"><?= e(t('ap.diapo_tronque', ['n' => ApercuPresentation::DIAPOS_MAX, 'total' => $diaporama['total']])) ?></p>
  <?php endif; ?>
<?php elseif ($genre === 'pdf'): ?>
  <div class="carte apercu-cadre">
    <iframe src="<?= $adresse ?>" title="<?= e($nom) ?>"></iframe>
  </div>
  <p class="champ__aide" style="margin-top:.6rem">
    <?= e(t('ap.pdf_repli')) ?>
    <a href="<?= $adresse ?>" target="_blank" rel="noopener"><?= e(t('ap.pdf_onglet')) ?></a>.
  </p>
<?php elseif ($genre === 'image'): ?>
  <div class="carte apercu-image" data-zoom-image>
    <div class="zoom__barre" role="toolbar" aria-label="<?= e(t('ap.zoom_barre')) ?>" hidden data-zoom-barre>
      <button type="button" class="bouton bouton--secondaire bouton--petit" data-zoom-action="moins" title="<?= e(t('ap.zoom_moins')) ?> (−)">−</button>
      <span class="zoom__niveau" data-zoom-niveau aria-live="polite"></span>
      <button type="button" class="bouton bouton--secondaire bouton--petit" data-zoom-action="plus" title="<?= e(t('ap.zoom_plus')) ?> (+)">＋</button>
      <button type="button" class="bouton bouton--discret bouton--petit" data-zoom-action="ajuster" title="<?= e(t('ap.zoom_ajuster')) ?> (0)">⤢ <?= e(t('ap.zoom_ajuster')) ?></button>
      <button type="button" class="bouton bouton--discret bouton--petit" data-zoom-action="reel" title="<?= e(t('ap.zoom_reel')) ?> (1)">1:1</button>
      <a class="bouton bouton--discret bouton--petit" href="<?= $adresse ?>" target="_blank" rel="noopener">↗ <?= e(t('ap.zoom_onglet')) ?></a>
    </div>
    <div class="zoom__cadre" data-zoom-cadre>
      <img src="<?= $adresse ?>" alt="<?= e($nom) ?>">
    </div>
    <p class="champ__aide" hidden data-zoom-aide><?= e(t('ap.zoom_aide')) ?></p>
  </div>
<?php elseif ($genre === 'audio'): ?>
  <div class="carte"><audio controls preload="metadata" src="<?= $adresse ?>" style="width:100%"></audio></div>
<?php elseif ($genre === 'video'): ?>
  <div class="carte"><video controls preload="metadata" src="<?= $adresse ?>" style="width:100%;max-height:70vh"></video></div>
<?php elseif ($genre === 'brut'): ?>
  <?php if ($texte === ''): ?>
    <div class="vide">
      <span class="vide__icone">📄</span>
      <p><?= e(t('ap.rien_a_montrer')) ?></p>
    </div>
  <?php else: ?>
    <div class="carte"><pre class="apercu-brut"><?= e($texte) ?></pre></div>
    <?php if ($tronque): ?>
      <p class="champ__aide" style="margin-top:.6rem"><?= e(t('ap.tronque')) ?></p>
    <?php endif; ?>
  <?php endif; ?>
<?php elseif ($genre === 'tableur'): ?>
  <?php if ($lignes === []): ?>
    <div class="vide">
      <span class="vide__icone">📊</span>
      <p><?= e(t('ap.classeur_vide')) ?></p>
    </div>
  <?php else: ?>
    <div class="carte" style="overflow-x:auto">
      <table class="tableau apercu-classeur">
        <tbody>
          <?php foreach ($lignes as $i => $ligne): ?>
            <tr>
              <th scope="row" class="apercu-classeur__num"><?= $i + 1 ?></th>
              <?php foreach ($ligne as $cellule): ?>
                <td><?= e($cellule) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="champ__aide" style="margin-top:.6rem">
      <?php
      $suite = match (true) {
          $total > $limite => t('ap.sur_total', ['total' => $total]),
          $format === t('fmt.xlsx') => t('ap.premiere_feuille'),
          default => '.',
      };
      ?>
      <?= e(tn('ap.lignes', count($lignes))) ?><?= e($suite) ?>
    </p>
  <?php endif; ?>
<?php elseif ($paragraphes === []): ?>
  <div class="vide">
    <span class="vide__icone">📄</span>
    <p><?= e(t('ap.rien_a_montrer')) ?></p>
  </div>
<?php else: ?>
  <article class="carte apercu-document">
    <?php foreach ($paragraphes as $p): ?>
      <p><?= e($p) ?></p>
    <?php endforeach; ?>
  </article>
  <p class="champ__aide" style="margin-top:.6rem"><?= e(tn('ap.paragraphes', count($paragraphes))) ?>.</p>
<?php endif; ?>