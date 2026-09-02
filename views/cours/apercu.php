<?php
/** @var array $fichier @var array $paragraphes @var ?string $erreur @var string $format */
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

<div class="flash flash--info" style="margin-bottom:1.25rem">
  <strong>Aperçu du texte.</strong> La mise en forme, les images et la pagination
  ne sont pas reproduites — un navigateur ne sait pas afficher un <?= e($format) ?>.
  Téléchargez le fichier pour l'ouvrir tel quel dans Word ou LibreOffice.
</div>

<?php if ($erreur !== null): ?>
  <div class="vide">
    <span class="vide__icone">⚠️</span>
    <p><?= e($erreur) ?></p>
    <a class="bouton bouton--secondaire" href="<?= url('fichiers/' . $fichier['id'], ['telecharger' => 1]) ?>">
      Télécharger le fichier
    </a>
  </div>
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
