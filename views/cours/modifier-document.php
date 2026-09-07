<?php
/**
 * @var array $fichier
 * @var array $paragraphes  le texte nu, un paragraphe par entrée
 * @var array $enrichis     le même texte, mise en forme comprise, en HTML
 * @var list<int> $tailles  les tailles proposées, en points
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
    <strong>Le gras, l'italique, le souligné et la taille se modifient ici.</strong>
    Le reste de la mise en forme — styles, couleurs, polices, images, tableaux —
    reste dans le document sans passer par cette page, et n'est donc pas perdu.
    Une copie du document d'origine est gardée avant la première modification.
  </div>

  <form method="post" action="<?= url('fichiers/' . $fichier['id'] . '/modifier') ?>"
        data-edition-document>
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <?php // Posé par le script : il dit au serveur que le texte arrive balisé. ?>
    <input type="hidden" name="riche" value="" data-riche>

    <?php
    /*
     * La barre d'outils ne sert qu'avec JavaScript : sans lui, les zones
     * restent de simples champs de texte, et la page garde le comportement
     * qu'elle avait — on modifie le texte, pas sa forme.
     */
    ?>
    <div class="barre-outils" data-barre-outils hidden>
      <button type="button" class="barre-outils__bouton" data-commande="bold"
              title="Gras (Ctrl+B)"><strong>G</strong></button>
      <button type="button" class="barre-outils__bouton" data-commande="italic"
              title="Italique (Ctrl+I)"><em>I</em></button>
      <button type="button" class="barre-outils__bouton" data-commande="underline"
              title="Souligné (Ctrl+U)"><u>S</u></button>
      <label class="barre-outils__taille">
        <span class="discret">Taille</span>
        <select data-taille-texte>
          <option value="">Celle du document</option>
          <?php foreach ($tailles as $taille): ?>
            <option value="<?= (int) $taille ?>"><?= (int) $taille ?> pt</option>
          <?php endforeach; ?>
        </select>
      </label>
      <span class="champ__aide barre-outils__aide">
        Sélectionnez du texte, puis choisissez.
      </span>
    </div>

    <div class="carte">
      <div class="paragraphes" data-paragraphes>
        <?php foreach ($paragraphes as $rang => $paragraphe): ?>
          <div class="paragraphe" data-paragraphe
               data-riche-html="<?= e($enrichis[$rang] ?? '') ?>">
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
    <div class="paragraphe" data-paragraphe data-riche-html="">
      <span class="paragraphe__rang" aria-hidden="true">+</span>
      <input type="hidden" name="origine[]" value="">
      <textarea name="texte[]" rows="1" class="paragraphe__texte" aria-label="Nouveau paragraphe"></textarea>
      <button type="button" class="bouton bouton--discret bouton--petit"
              data-supprimer-paragraphe title="Supprimer ce paragraphe">🗑</button>
    </div>
  </template>
<?php endif; ?>
