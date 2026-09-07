<?php
/**
 * Les documents d'un cours, à cocher, dans le formulaire de fabrication.
 *
 * Toutes les listes sont dans la page, éteintes ; le script n'allume que celle
 * du cours choisi. Un « fieldset » désactivé n'envoie rien : la liste d'un
 * autre cours ne peut donc pas se glisser dans le formulaire. Sans script,
 * aucune ne s'affiche, et la case « les documents joints » garde son sens
 * d'origine — tous les documents du cours.
 *
 * @var int $coursId     le cours dont on liste les documents
 * @var array $documents ses fichiers, ceux du cours d'abord
 */
$parPlace = [0 => [], 1 => []];
foreach ($documents as $document) {
    $parPlace[(int) $document['pour_fiche'] === 1 ? 1 : 0][] = $document;
}
$places = [0 => 'Joints au cours', 1 => 'Joints à la fiche de révision'];
?>
<fieldset class="sources documents" data-documents="<?= $coursId ?>" hidden disabled>
  <legend>Documents de ce cours</legend>

  <?php
  /*
   * De quel cours vient la liste : sans ce repère, le serveur prendrait pour un
   * choix une liste restée affichée sur un autre cours, et lirait les mauvais
   * documents — ou plus aucun.
   */
  ?>
  <input type="hidden" name="documents_de" value="<?= $coursId ?>">

  <?php if ($documents === []): ?>
    <p class="champ__aide">Rien n'est joint à ce cours, ni à sa fiche de révision.</p>
  <?php endif; ?>

  <?php foreach ($places as $place => $titre): ?>
    <?php if ($parPlace[$place] === []) { continue; } ?>
    <p class="documents__place"><?= e($titre) ?></p>

    <?php foreach ($parPlace[$place] as $document): ?>
      <?php
      $nom = (string) $document['nom_origine'];
      /*
       * Une image, un son, une vidéo : il n'y a pas de texte à lire dedans. On
       * le montre quand même, grisé, plutôt que de faire disparaître le
       * document de la liste — sans quoi on le chercherait.
       */
      $lisible = in_array(ApercuDocument::genre($nom), ['pdf', 'document', 'tableur', 'brut'], true);
      ?>
      <label class="sources__choix<?= $lisible ? '' : ' sources__choix--vide' ?>">
        <input type="checkbox" name="documents[]" value="<?= (int) $document['id'] ?>"
               <?= $lisible ? 'checked' : 'disabled' ?>>
        <span>
          <span aria-hidden="true"><?= Fichiers::icone((string) $document['mime'], $nom) ?></span>
          <?= e($nom) ?>
          <span class="discret">
            <?= $lisible ? e(taille_lisible((int) $document['taille'])) : 'rien à lire dedans' ?>
          </span>
        </span>
      </label>
    <?php endforeach; ?>
  <?php endforeach; ?>
</fieldset>
