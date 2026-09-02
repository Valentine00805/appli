<?php
/**
 * @var array $fichier, $paragraphes, $lignes
 * @var string $genre, $texte, $format
 * @var bool $tronque
 * @var bool $estTableur
 * @var int $total, $limite
 * @var ?string $erreur

 */
?>

<div class="entete-page">
  <div>
    <p class="discret" style="margin-bottom:.35rem">
      <a href="<?= url('cours/' . $fichier['cours_id']) ?>">← <?= e((string) $fichier['cours_titre']) ?></a>
    </p>
    <h1><?= e(Fichiers::icone($fichier['mime'], $fichier['nom_origine'])) ?> <?= e((string) $fichier['nom_origine']) ?></h1>
    <p><?= e(ucfirst($format)) ?> · <?= e(taille_lisible((int) $fichier['taille'])) ?></p>
  </div>
  <div class="actions">
    <a class="bouton" href="<?= url('fichiers/' . $fichier['id'], ['telecharger' => 1]) ?>">
      ⬇ Télécharger le fichier
    </a>
  </div>
</div>

<?php // Un PDF ou une image s'affichent tels quels : aucun avertissement à donner. ?>
<?php if (!in_array($genre, ['pdf', 'image'], true)): ?>
  <div class="flash flash--info" style="margin-bottom:1.25rem">
    <?php if ($genre === 'tableur'): ?>
      <strong>Aperçu du contenu.</strong> Les formules, les couleurs et les graphiques
      ne sont pas reproduits : seules les valeurs sont affichées.
      Téléchargez le fichier pour l'ouvrir tel quel dans Excel ou LibreOffice.
    <?php elseif ($genre === 'brut'): ?>
      <strong>Contenu du fichier</strong>, tel qu'il est enregistré.
    <?php else: ?>
      <strong>Aperçu du texte.</strong> La mise en forme, les images et la pagination
      ne sont pas reproduites — un navigateur ne sait pas afficher un <?= e($format) ?>.
      Téléchargez le fichier pour l'ouvrir tel quel dans Word ou LibreOffice.
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($erreur !== null): ?>
  <div class="vide">
    <span class="vide__icone">⚠️</span>
    <p><?= e($erreur) ?></p>
    <a class="bouton bouton--secondaire" href="<?= url('fichiers/' . $fichier['id'], ['telecharger' => 1]) ?>">
      Télécharger le fichier
    </a>
  </div>
<?php elseif ($genre === 'pdf'): ?>
  <?php // Le document lui-même, dans la page, avec la visionneuse du navigateur. ?>
  <div class="carte apercu-cadre">
    <iframe src="<?= url('fichiers/' . $fichier['id']) ?>"
            title="<?= e((string) $fichier['nom_origine']) ?>"></iframe>
  </div>
  <p class="champ__aide" style="margin-top:.6rem">
    Si le document ne s'affiche pas,
    <a href="<?= url('fichiers/' . $fichier['id']) ?>" target="_blank" rel="noopener">l'ouvrir dans un onglet</a>.
  </p>

<?php elseif ($genre === 'image'): ?>
  <div class="carte apercu-image">
    <a href="<?= url('fichiers/' . $fichier['id']) ?>" target="_blank" rel="noopener"
       title="Voir l'image en taille réelle">
      <img src="<?= url('fichiers/' . $fichier['id']) ?>" alt="<?= e((string) $fichier['nom_origine']) ?>">
    </a>
  </div>

<?php elseif ($genre === 'brut'): ?>
  <div class="carte">
    <pre class="apercu-brut"><?= e($texte) ?></pre>
  </div>
  <?php if ($tronque): ?>
    <p class="champ__aide" style="margin-top:.6rem">
      Fichier volumineux : seul le début est affiché. Téléchargez-le pour tout voir.
    </p>
  <?php endif; ?>

<?php elseif ($estTableur): ?>
  <?php if ($lignes === []): ?>
    <div class="vide">
      <span class="vide__icone">📊</span>
      <p>Ce classeur ne contient aucune donnée sur sa première feuille.</p>
      <a class="bouton bouton--secondaire" href="<?= url('fichiers/' . $fichier['id'], ['telecharger' => 1]) ?>">
        Télécharger le fichier
      </a>
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
      $n = count($lignes);
      $suite = match (true) {
          $total > $limite => ' sur ' . $total . ' — les suivantes ne sont pas montrées ici.'
                            . ' Téléchargez le fichier pour tout voir.',
          $format === 'classeur Excel' => ' · première feuille du classeur.',
          default => '.',
      };
      ?>
      <?= $n ?> ligne<?= $n > 1 ? 's' : '' ?> affichée<?= $n > 1 ? 's' : '' ?><?= $suite ?>
    </p>
  <?php endif; ?>
<?php elseif ($paragraphes === []): ?>
  <div class="vide">
    <span class="vide__icone">📄</span>
    <p>Ce document ne contient aucun texte — il est peut-être vide, ou composé
       uniquement d'images.</p>
    <a class="bouton bouton--secondaire" href="<?= url('fichiers/' . $fichier['id'], ['telecharger' => 1]) ?>">
      Télécharger le fichier
    </a>
  </div>
<?php else: ?>
  <article class="carte apercu-document">
    <?php foreach ($paragraphes as $paragraphe): ?>
      <p><?= e($paragraphe) ?></p>
    <?php endforeach; ?>
  </article>
  <p class="champ__aide" style="margin-top:.6rem">
    <?= count($paragraphes) ?> paragraphe<?= count($paragraphes) > 1 ? 's' : '' ?> lu<?= count($paragraphes) > 1 ? 's' : '' ?>.
  </p>
<?php endif; ?>
