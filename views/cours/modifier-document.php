<?php
/**
 * @var array $fichier
 * @var array $paragraphes  le texte nu, un paragraphe par entrée
 * @var array $enrichis     le même texte, mise en forme comprise, en HTML
 * @var list<int> $tailles  les tailles proposées, en points
 * @var int $sommaire       jusqu'à quel niveau de titre il descend, zéro s'il n'y en a pas
 * @var int $titreMax       le niveau de titre le plus profond que l'on propose
 * @var string $format
 * @var ?string $erreur
 */

// Une image ne s'ajoute qu'à un document Word : voir « imagesAjoutables ».
$ajoutImages = EditionDocument::imagesAjoutables((string) $fichier['nom_origine']);

/*
 * Ouvert dans la fenêtre de l'aperçu, l'éditeur y reste : les liens qui
 * ramènent à l'aperçu l'y rouvrent, et le formulaire s'y enregistre.
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$versApercu = $dansUneFenetre ? ' data-fenetre' : '';
?>

<div class="entete-page"<?= $dansUneFenetre ? ' data-large data-document' : '' ?>>
  <div>
    <?php
    /*
     * Le retour à l'aperçu, en bouton, avant le titre — comme dans les
     * réglages des agendas : c'est une action, de la couleur des actions.
     */
    ?>
    <p style="margin:0 0 .6rem">
      <a class="bouton" href="<?= url('fichiers/' . $fichier['id'] . '/apercu') ?>"<?= $versApercu ?>
         title="<?= e(t('ed.retour_apercu_titre', ['nom' => (string) $fichier['nom_origine']])) ?>"><?= e(t('agenda.retour')) ?></a>
    </p>
    <h1><?= e(t('ed.titre')) ?></h1>
    <p><?= e(ucfirst($format)) ?> · <?= e(tn('ed.paragraphes', count($paragraphes))) ?></p>
  </div>
</div>

<?php if ($erreur !== null): ?>
  <div class="vide">
    <span class="vide__icone">⚠️</span>
    <p><?= e($erreur) ?></p>
    <a class="bouton bouton--secondaire" href="<?= url('fichiers/' . $fichier['id'] . '/apercu') ?>"<?= $versApercu ?>><?= e(t('ed.revenir_apercu')) ?></a>
  </div>
<?php else: ?>

  <div class="flash flash--info" style="margin-bottom:1.25rem">
    <?= t('ed.aide_debut') ?>
    <?= e(t($ajoutImages ? 'ed.aide_images_oui' : 'ed.aide_images_non')) ?>
    <?= e(t('ed.aide_fin')) ?>
  </div>

  <?php
  /*
   * « multipart » : le formulaire peut emporter des images. Le poids admis
   * est dit au script, qui refuse une image trop lourde dès qu'on la choisit
   * plutôt qu'à l'envoi — un refus du serveur ferait perdre tout ce qu'on a
   * tapé depuis l'ouverture.
   */
  ?>
  <form method="post" action="<?= url('fichiers/' . $fichier['id'] . '/modifier') ?>"
        enctype="multipart/form-data" data-edition-document<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>
        data-taille-max="<?= (int) Fichiers::tailleMax() ?>"
        data-largeur-page="<?= (int) ($largeurPage ?? 0) ?>">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <?php // Posé par le script : il dit au serveur que le texte arrive balisé. ?>
    <input type="hidden" name="riche" value="" data-riche>
    <?php // Posé par le script : chaque image revient à sa place dans le texte. ?>
    <input type="hidden" name="images_en_ligne" value="" data-images-en-ligne>
    <?php // Les fichiers des images ajoutées attendent ici l'enregistrement. ?>
    <div hidden data-fichiers-images></div>


    <?php
    /*
     * La barre d'outils ne sert qu'avec JavaScript : sans lui, les zones
     * restent de simples champs de texte, et la page garde le comportement
     * qu'elle avait — on modifie le texte, pas sa forme.
     */
    ?>
    <div class="barre-outils" data-barre-outils hidden>
      <button type="button" class="barre-outils__bouton" data-commande="bold"
              title="<?= e(t('js.riche.gras')) ?>"><strong>G</strong></button>
      <button type="button" class="barre-outils__bouton" data-commande="italic"
              title="<?= e(t('js.riche.italique')) ?>"><em>I</em></button>
      <button type="button" class="barre-outils__bouton" data-commande="underline"
              title="<?= e(t('js.riche.souligne')) ?>"><u>S</u></button>
      <?php
      /*
       * L'alignement porte sur le paragraphe entier, et non sur ce qui est
       * sélectionné : il suffit d'avoir le curseur dedans.
       */
      ?>
      <span class="barre-outils__couleurs">
        <button type="button" class="barre-outils__bouton" data-aligner="gauche"
                title="<?= e(t('js.riche.gauche')) ?>"><span aria-hidden="true">◧</span>
          <span class="sr-only"><?= e(t('js.riche.gauche')) ?></span></button>
        <button type="button" class="barre-outils__bouton" data-aligner="centre"
                title="<?= e(t('js.riche.centrer')) ?>"><span aria-hidden="true">▣</span>
          <span class="sr-only"><?= e(t('js.riche.centrer')) ?></span></button>
        <button type="button" class="barre-outils__bouton" data-aligner="droite"
                title="<?= e(t('js.riche.droite')) ?>"><span aria-hidden="true">◨</span>
          <span class="sr-only"><?= e(t('js.riche.droite')) ?></span></button>
        <button type="button" class="barre-outils__bouton" data-liste="puce"
                title="<?= e(t('ed.puce')) ?>"><span aria-hidden="true">•—</span>
          <span class="sr-only"><?= e(t('ed.puce_court')) ?></span></button>
        <button type="button" class="barre-outils__bouton" data-liste="numero"
                title="<?= e(t('ed.numerotation')) ?>"><span aria-hidden="true">1—</span>
          <span class="sr-only"><?= e(t('js.riche.numerotee')) ?></span></button>
        <?php
        /*
         * La sous-liste : « a. b. c. » sous un « 1. ». La touche de
         * tabulation fait la même chose depuis la zone de texte, comme dans
         * un traitement de texte.
         */
        ?>
        <button type="button" class="barre-outils__bouton" data-niveau-liste="1"
                title="<?= e(t('ed.sous_liste')) ?>"><span aria-hidden="true">⇥</span>
          <span class="sr-only"><?= e(t('js.riche.abaisser_court')) ?></span></button>
        <button type="button" class="barre-outils__bouton" data-niveau-liste="-1"
                title="<?= e(t('ed.remonter')) ?>"><span aria-hidden="true">⇤</span>
          <span class="sr-only"><?= e(t('js.riche.remonter_court')) ?></span></button>
      </span>
      <?php
      /*
       * Les titres portent sur le paragraphe entier, comme l'alignement :
       * il suffit d'avoir le curseur dedans. Recliquer sur le même le
       * ramène à du texte ordinaire.
       */
      ?>
      <span class="barre-outils__couleurs">
        <button type="button" class="barre-outils__bouton" data-titre="1"
                title="<?= e(t('js.riche.titre1')) ?>">T1</button>
        <button type="button" class="barre-outils__bouton" data-titre="2"
                title="<?= e(t('js.riche.titre2')) ?>">T2</button>
        <button type="button" class="barre-outils__bouton" data-titre="3"
                title="<?= e(t('js.riche.titre3')) ?>">T3</button>
      </span>
      <?php
      /*
       * Le sommaire ne se modifie pas ligne à ligne : c'est une propriété du
       * document. Il est refait à chaque enregistrement, à partir des titres
       * du moment — il n'y a rien à tenir à jour à la main. La liste part de
       * ce que le document porte déjà, et repart telle quelle même sans
       * JavaScript : un enregistrement ne le fait donc ni apparaître ni
       * disparaître par surprise.
       */
      ?>
      <label class="barre-outils__taille">
        <span class="discret"><?= e(t('js.riche.sommaire')) ?></span>
        <select name="sommaire" title="<?= e(t('js.riche.sommaire_aide')) ?>">
          <option value="0"<?= $sommaire === 0 ? ' selected' : '' ?>><?= e(t('ed.sommaire_aucun')) ?></option>
          <?php for ($niveau = 1; $niveau <= $titreMax; $niveau++): ?>
            <option value="<?= $niveau ?>"<?= $sommaire === $niveau ? ' selected' : '' ?>>
              <?= e($niveau === 1 ? t('ed.sommaire_1') : t('ed.sommaire_n', ['n' => $niveau])) ?>
            </option>
          <?php endfor; ?>
        </select>
      </label>
      <label class="barre-outils__taille">
        <span class="discret"><?= e(t('js.riche.taille')) ?></span>
        <select data-taille-texte>
          <option value=""><?= e(t('ed.taille_document')) ?></option>
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
        <span class="discret"><?= e(t('js.riche.couleur')) ?></span>
        <button type="button" class="barre-outils__bouton barre-outils__appliquer"
                data-couleur-appliquer title="<?= e(t('js.riche.couleur_appliquer')) ?>">
          <span aria-hidden="true">A</span>
          <span class="barre-outils__trait"></span>
          <span class="sr-only"><?= e(t('js.riche.couleur_appliquer_court')) ?></span>
        </button>
        <input type="color" class="barre-outils__couleur" data-couleur-texte
               value="#000000" title="<?= e(t('ed.couleur_autre')) ?>"
               aria-label="<?= e(t('ed.couleur_autre')) ?>">
        <button type="button" class="barre-outils__bouton" data-couleur-defaut
                title="<?= e(t('ed.couleur_document')) ?>">⌫</button>
      </span>
      <span class="barre-outils__couleurs">
        <span class="discret"><?= e(t('js.riche.surlignage')) ?></span>
        <button type="button" class="barre-outils__bouton barre-outils__surligner"
                data-fond-appliquer title="<?= e(t('js.riche.surligner')) ?>">
          <span aria-hidden="true">🖍</span>
          <span class="sr-only"><?= e(t('js.riche.surligner_court')) ?></span>
        </button>
        <input type="color" class="barre-outils__couleur" data-fond-texte
               value="#FFFF00" title="<?= e(t('ed.surlignage_autre')) ?>"
               aria-label="<?= e(t('ed.surlignage_autre')) ?>">
        <button type="button" class="barre-outils__bouton" data-fond-defaut
                title="<?= e(t('js.riche.surlignage_retirer')) ?>">⌫</button>
      </span>
      <?php
      /*
       * Aller à la ligne sans changer de paragraphe : ce que fait Maj+Entrée,
       * pour qui ne connaît pas le raccourci.
       */
      ?>
      <button type="button" class="barre-outils__bouton" data-saut-ligne
              title="<?= e(t('js.riche.saut')) ?>">↵</button>
      <?php if ($ajoutImages): ?>
        <?php
        /*
         * Ajouter une image. Le champ de fichier reste caché : le bouton
         * l'ouvre, et le script le range ensuite dans la ligne créée pour
         * l'image — c'est de là qu'il partira, à l'enregistrement.
         */
        ?>
        <button type="button" class="barre-outils__bouton barre-outils__image"
                data-inserer-image title="<?= e(t('js.riche.image')) ?>">
          <span aria-hidden="true">🖼</span> <?= e(t('ed.image_bouton')) ?>
        </button>
        <input type="file" accept="image/png,image/jpeg,image/gif" hidden data-choisir-image
               aria-label="<?= e(t('js.riche.image_choisir')) ?>">
        <?php
        /*
         * La largeur de l'image choisie. Le groupe n'apparaît que lorsqu'on a
         * cliqué une image : il n'a rien à dire le reste du temps. La hauteur
         * suit toujours, dans la même proportion.
         */
        ?>
        <span class="barre-outils__couleurs barre-outils__taille-image" data-taille-image hidden>
          <span class="discret"><?= e(t('js.riche.largeur')) ?></span>
          <input type="range" min="3" max="100" step="1" value="100" data-taille-image-curseur
                 aria-label="<?= e(t('ed.image_largeur')) ?>">
          <output data-taille-image-valeur>—</output>
          <button type="button" class="barre-outils__bouton" data-taille-image-origine
                  title="<?= e(t('ed.image_origine')) ?>">↺</button>
        </span>
        <?php
        /*
         * Où se tient l'image : dans la ligne, comme un mot, ou sur un bord,
         * le texte de son paragraphe à côté d'elle — ce que Word appelle
         * l'habillage « carré ».
         */
        ?>
        <span class="barre-outils__couleurs" data-habillage-image hidden>
          <span class="discret"><?= e(t('js.riche.texte')) ?></span>
          <button type="button" class="barre-outils__bouton" data-habillage-choix="ligne"
                  aria-pressed="false" title="<?= e(t('js.riche.image_ligne')) ?>">▭</button>
          <button type="button" class="barre-outils__bouton" data-habillage-choix="gauche"
                  aria-pressed="false" title="<?= e(t('js.riche.image_gauche')) ?>">◧≡</button>
          <button type="button" class="barre-outils__bouton" data-habillage-choix="centre"
                  aria-pressed="false" title="<?= e(t('js.riche.image_centre')) ?>">▣</button>
          <button type="button" class="barre-outils__bouton" data-habillage-choix="droite"
                  aria-pressed="false" title="<?= e(t('js.riche.image_droite')) ?>">≡◨</button>
        </span>
      <?php endif; ?>
      <span class="champ__aide barre-outils__aide">
        <?= e(t('ed.selection_aide')) ?>
      </span>
      <p class="message-erreur barre-outils__souci" data-souci-image role="alert" hidden></p>
    </div>

    <div class="carte">
      <div class="paragraphes" data-paragraphes>
        <?php foreach ($paragraphes as $rang => $paragraphe): ?>
          <?php
          $aligne = (string) ($enrichis[$rang]['alignement'] ?? '');
          $liste = (string) ($enrichis[$rang]['liste'] ?? '');
          $niveau = $liste === '' ? 0 : (int) ($enrichis[$rang]['niveau'] ?? 0);
          $titre = (int) ($enrichis[$rang]['titre'] ?? 0);
          ?>
          <div class="paragraphe" data-paragraphe
               data-riche-html="<?= e($enrichis[$rang]['html'] ?? '') ?>"
               data-aligne="<?= e($aligne) ?>" data-liste="<?= e($liste) ?>"
               data-numero="<?= (int) ($enrichis[$rang]['numero'] ?? 0) ?>"
               <?php // Le premier niveau, ou celui d'une sous-liste. ?>
               data-niveau="<?= $niveau ?>"
               <?php // Titre 1, Titre 2, ou rien du tout. ?>
               data-titre="<?= $titre ?>">
            <span class="paragraphe__rang" aria-hidden="true"><?= $rang + 1 ?></span>
            <input type="hidden" name="origine[]" value="<?= (int) $rang ?>">
            <?php // Sans script, ils repartent tels quels : rien n'est perdu. ?>
            <input type="hidden" name="alignement[]" value="<?= e($aligne) ?>">
            <input type="hidden" name="liste[]" value="<?= e($liste) ?>">
            <input type="hidden" name="niveau[]" value="<?= $niveau ?>">
            <input type="hidden" name="titre[]" value="<?= $titre ?>">
            <textarea name="texte[]" rows="1" class="paragraphe__texte"
                      aria-label="<?= e(t('ed.paragraphe_n', ['n' => $rang + 1])) ?>"><?= e($paragraphe) ?></textarea>
            <?php
            $images = $enrichis[$rang]['images'] ?? [];
            // Dans un document Word, les images sont dans le texte même.
            $nbImages = count($images) + (int) ($enrichis[$rang]['images_texte'] ?? 0);
            ?>
            <button type="button" class="bouton bouton--discret bouton--petit"
                    data-supprimer-paragraphe
                    title="<?= e(match (true) {
                        $nbImages === 0 => t('ed.supprimer_paragraphe'),
                        $nbImages === 1 => t('ed.supprimer_paragraphe_image'),
                        default         => t('ed.supprimer_paragraphe_images'),
                    }) ?>">🗑</button>
            <?php
            /*
             * Ses images, sous le texte et hors de la zone qu'on modifie : on
             * les voit, on ne les réécrit pas. Elles ne partent pas avec le
             * formulaire — le serveur les garde de lui-même dans leur
             * paragraphe, et couper celui-ci en deux les laisse au premier
             * morceau, comme ici. Seule la corbeille les emporte, avec la
             * ligne entière : son titre le dit.
             */
            ?>
            <?php if ($images !== []): ?>
              <div class="paragraphe__images">
                <?= Vue::rendre('cours/_images', ['images' => $images, 'fichier' => $fichier]) ?>
              </div>
            <?php endif; ?>
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
            <input type="hidden" name="niveau[]" value="0">
            <input type="hidden" name="titre[]" value="0">
            <textarea name="texte[]" rows="1" class="paragraphe__texte"
                      aria-label="<?= e(t('ed.nouveau_paragraphe')) ?>"></textarea>
          </div>
        <?php endfor; ?>
        <?php if ($ajoutImages): ?>
          <?php // Et une image, ajoutée à la fin du document, avec sa légende si on en écrit une. ?>
          <div class="paragraphe">
            <span class="paragraphe__rang" aria-hidden="true">🖼</span>
            <input type="hidden" name="origine[]" value="image:sansjs1">
            <input type="hidden" name="alignement[]" value="">
            <input type="hidden" name="liste[]" value="">
            <input type="hidden" name="niveau[]" value="0">
            <input type="hidden" name="titre[]" value="0">
            <textarea name="texte[]" rows="1" class="paragraphe__texte"
                      aria-label="<?= e(t('ed.legende_aria')) ?>"
                      placeholder="<?= e(t('ed.legende_placeholder')) ?>"></textarea>
            <input type="file" name="images[sansjs1]" accept="image/png,image/jpeg,image/gif"
                   aria-label="<?= e(t('ed.image_a_ajouter')) ?>">
          </div>
        <?php endif; ?>
      </noscript>

      <p class="champ__aide" style="margin-top:.75rem">
        <?= e(t('ed.entree_aide')) ?>
      </p>
    </div>

    <div class="actions" style="margin-top:1rem">
      <button type="button" class="bouton bouton--secondaire" data-ajouter-paragraphe hidden>
        <?= e(t('ed.ajouter_paragraphe')) ?>
      </button>
      <button class="bouton" type="submit"><?= e(t('ed.enregistrer')) ?></button>
      <a class="bouton bouton--discret" href="<?= url('fichiers/' . $fichier['id'] . '/apercu') ?>"<?= $versApercu ?>><?= e(t('commun.annuler')) ?></a>
    </div>
  </form>

  <?php // Modèle recopié par le bouton d'ajout. ?>
  <template data-modele-paragraphe>
    <div class="paragraphe" data-paragraphe data-riche-html="" data-aligne=""
         data-liste="" data-numero="0" data-niveau="0" data-titre="0">
      <span class="paragraphe__rang" aria-hidden="true">+</span>
      <input type="hidden" name="origine[]" value="">
      <input type="hidden" name="alignement[]" value="">
      <input type="hidden" name="liste[]" value="">
      <input type="hidden" name="niveau[]" value="0">
      <input type="hidden" name="titre[]" value="0">
      <textarea name="texte[]" rows="1" class="paragraphe__texte" aria-label="<?= e(t('ed.nouveau_paragraphe')) ?>"></textarea>
      <button type="button" class="bouton bouton--discret bouton--petit"
              data-supprimer-paragraphe title="<?= e(t('ed.supprimer_paragraphe')) ?>">🗑</button>
    </div>
  </template>
<?php endif; ?>
