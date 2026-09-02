<?php
/**
 * @var array $fichier
 * @var array $paragraphes
 * @var string $format
 * @var ?string $erreur
 */
?>

<div class="entete-page">
  <div>
    <p class="discret" style="margin-bottom:.35rem">
      <a href="<?= url('fichiers/' . $fichier['id'] . '/apercu') ?>">← <?= e((string) $fichier['nom_origine']) ?></a>
    </p>
    <h1>Modifier le texte</h1>
    <p><?= e(ucfirst($format)) ?> · <?= count($paragraphes) ?> paragraphe<?= count($paragraphes) > 1 ? 's' : '' ?></p>
  </div>
</div>

<?php if ($erreur !== null): ?>
  <div class="vide">
    <span class="vide__icone">⚠️</span>
    <p><?= e($erreur) ?></p>
    <a class="bouton bouton--secondaire" href="<?= url('fichiers/' . $fichier['id'] . '/apercu') ?>">Revenir à l'aperçu</a>
  </div>
<?php else: ?>

  <div class="flash flash--info" style="margin-bottom:1.25rem">
    <strong>Vous modifiez le texte, pas la mise en forme.</strong>
    Les styles, les images et les tableaux du document restent en place.
    Sur un paragraphe que vous changez, une mise en forme qui variait à
    l'intérieur — un mot en gras au milieu d'une phrase — reprend celle du
    début du paragraphe. Une copie du document d'origine est gardée avant la
    première modification.
  </div>

  <form method="post" action="<?= url('fichiers/' . $fichier['id'] . '/modifier') ?>">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

    <div class="carte">
      <div class="paragraphes" data-paragraphes>
        <?php foreach ($paragraphes as $rang => $paragraphe): ?>
          <div class="paragraphe" data-paragraphe>
            <span class="paragraphe__rang" aria-hidden="true"><?= $rang + 1 ?></span>
            <input type="hidden" name="origine[]" value="<?= (int) $rang ?>">
            <textarea name="texte[]" rows="1" class="paragraphe__texte"
                      aria-label="Paragraphe <?= $rang + 1 ?>"><?= e($paragraphe) ?></textarea>
            <button type="button" class="bouton bouton--discret bouton--petit"
                    data-supprimer-paragraphe title="Supprimer ce paragraphe">🗑</button>
          </div>
        <?php endforeach; ?>
      </div>

      <?php // Sans JavaScript, ces lignes vides tiennent lieu de bouton « ajouter ». ?>
      <noscript>
        <?php for ($i = 0; $i < 3; $i++): ?>
          <div class="paragraphe">
            <span class="paragraphe__rang" aria-hidden="true">+</span>
            <input type="hidden" name="origine[]" value="">
            <textarea name="texte[]" rows="1" class="paragraphe__texte"
                      aria-label="Nouveau paragraphe"></textarea>
          </div>
        <?php endfor; ?>
      </noscript>

      <p class="champ__aide" style="margin-top:.75rem">
        Un retour à la ligne dans une zone crée un nouveau paragraphe.
      </p>
    </div>

    <div class="actions" style="margin-top:1rem">
      <button type="button" class="bouton bouton--secondaire" data-ajouter-paragraphe hidden>
        + Ajouter un paragraphe
      </button>
      <button class="bouton" type="submit">Enregistrer le document</button>
      <a class="bouton bouton--discret" href="<?= url('fichiers/' . $fichier['id'] . '/apercu') ?>">Annuler</a>
    </div>
  </form>

  <?php // Modèle recopié par le bouton d'ajout. ?>
  <template data-modele-paragraphe>
    <div class="paragraphe" data-paragraphe>
      <span class="paragraphe__rang" aria-hidden="true">+</span>
      <input type="hidden" name="origine[]" value="">
      <textarea name="texte[]" rows="1" class="paragraphe__texte" aria-label="Nouveau paragraphe"></textarea>
      <button type="button" class="bouton bouton--discret bouton--petit"
              data-supprimer-paragraphe title="Supprimer ce paragraphe">🗑</button>
    </div>
  </template>
<?php endif; ?>
