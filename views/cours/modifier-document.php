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
    <strong>Le gras, l'italique, le souligné, la taille, la couleur, le
    surlignage, l'alignement et les listes se modifient ici.</strong> Le reste de
    la mise en forme — styles, polices, retraits, images, tableaux — reste dans
    le document sans passer par cette page, et n'est donc pas perdu. Une copie
    du document d'origine est gardée avant la première modification.
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
      <?php
      /*
       * L'alignement porte sur le paragraphe entier, et non sur ce qui est
       * sélectionné : il suffit d'avoir le curseur dedans.
       */
      ?>
      <span class="barre-outils__couleurs">
        <button type="button" class="barre-outils__bouton" data-aligner="gauche"
                title="Aligner à gauche"><span aria-hidden="true">◧</span>
          <span class="sr-only">Aligner à gauche</span></button>
        <button type="button" class="barre-outils__bouton" data-aligner="centre"
                title="Centrer"><span aria-hidden="true">▣</span>
          <span class="sr-only">Centrer</span></button>
        <button type="button" class="barre-outils__bouton" data-aligner="droite"
                title="Aligner à droite"><span aria-hidden="true">◨</span>
          <span class="sr-only">Aligner à droite</span></button>
        <button type="button" class="barre-outils__bouton" data-liste="puce"
                title="Mettre ou retirer la puce"><span aria-hidden="true">•—</span>
          <span class="sr-only">Puce</span></button>
        <button type="button" class="barre-outils__bouton" data-liste="numero"
                title="Mettre ou retirer la numérotation"><span aria-hidden="true">1—</span>
          <span class="sr-only">Liste numérotée</span></button>
      </span>
      <label class="barre-outils__taille">
        <span class="discret">Taille</span>
        <select data-taille-texte>
          <option value="">Celle du document</option>
          <?php foreach ($tailles as $taille): ?>
            <option value="<?= (int) $taille ?>"><?= (int) $taille ?> pt</option>
          <?php endforeach; ?>
        </select>
      </label>
      <?php
      /*
       * Choisir la couleur et l'appliquer sont deux gestes séparés. Le
       * nuancier du système ne prévient que lorsqu'on change de couleur :
       * rouvrir pour reprendre la même ne dit rien, et le texte suivant serait
       * resté noir sans qu'on comprenne pourquoi.
       */
      ?>
      <span class="barre-outils__couleurs">
        <span class="discret">Couleur</span>
        <button type="button" class="barre-outils__bouton barre-outils__appliquer"
                data-couleur-appliquer title="Appliquer cette couleur au texte choisi">
          <span aria-hidden="true">A</span>
          <span class="barre-outils__trait"></span>
          <span class="sr-only">Appliquer la couleur</span>
        </button>
        <input type="color" class="barre-outils__couleur" data-couleur-texte
               value="#000000" title="Choisir une autre couleur"
               aria-label="Choisir une autre couleur">
        <button type="button" class="barre-outils__bouton" data-couleur-defaut
                title="Remettre la couleur du document">⌫</button>
      </span>
      <span class="barre-outils__couleurs">
        <span class="discret">Surlignage</span>
        <button type="button" class="barre-outils__bouton barre-outils__surligner"
                data-fond-appliquer title="Surligner le texte choisi">
          <span aria-hidden="true">🖍</span>
          <span class="sr-only">Surligner</span>
        </button>
        <input type="color" class="barre-outils__couleur" data-fond-texte
               value="#FFFF00" title="Choisir une autre couleur de surlignage"
               aria-label="Choisir une autre couleur de surlignage">
        <button type="button" class="barre-outils__bouton" data-fond-defaut
                title="Retirer le surlignage">⌫</button>
      </span>
      <span class="champ__aide barre-outils__aide">
        Sélectionnez du texte, puis cliquez sur une commande.
      </span>
    </div>

    <div class="carte">
      <div class="paragraphes" data-paragraphes>
        <?php foreach ($paragraphes as $rang => $paragraphe): ?>
          <?php
          $aligne = (string) ($enrichis[$rang]['alignement'] ?? '');
          $liste = (string) ($enrichis[$rang]['liste'] ?? '');
          ?>
          <div class="paragraphe" data-paragraphe
               data-riche-html="<?= e($enrichis[$rang]['html'] ?? '') ?>"
               data-aligne="<?= e($aligne) ?>" data-liste="<?= e($liste) ?>"
               data-numero="<?= (int) ($enrichis[$rang]['numero'] ?? 0) ?>"
               <?php // Une sous-liste se compte à part : le script la laisse. ?>
               data-profond="<?= ($enrichis[$rang]['profond'] ?? false) ? '1' : '' ?>">
            <span class="paragraphe__rang" aria-hidden="true"><?= $rang + 1 ?></span>
            <input type="hidden" name="origine[]" value="<?= (int) $rang ?>">
            <?php // Sans script, ils repartent tels quels : rien n'est perdu. ?>
            <input type="hidden" name="alignement[]" value="<?= e($aligne) ?>">
            <input type="hidden" name="liste[]" value="<?= e($liste) ?>">
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
            <input type="hidden" name="alignement[]" value="">
            <input type="hidden" name="liste[]" value="">
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
    <div class="paragraphe" data-paragraphe data-riche-html="" data-aligne=""
         data-liste="" data-numero="0">
      <span class="paragraphe__rang" aria-hidden="true">+</span>
      <input type="hidden" name="origine[]" value="">
      <input type="hidden" name="alignement[]" value="">
      <input type="hidden" name="liste[]" value="">
      <textarea name="texte[]" rows="1" class="paragraphe__texte" aria-label="Nouveau paragraphe"></textarea>
      <button type="button" class="bouton bouton--discret bouton--petit"
              data-supprimer-paragraphe title="Supprimer ce paragraphe">🗑</button>
    </div>
  </template>
<?php endif; ?>
