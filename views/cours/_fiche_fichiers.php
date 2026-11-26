<?php
/**
 * Le rayon « Fichiers et images » d'une fiche de révision : ses pièces jointes (lecteur, PDF feuilletable, images), et le dépôt de nouvelles.
 *
 * Le même fragment sert la fiche d'un cours et celle d'un dossier.
 *
 * @var array  $fichiersFiche
 * @var string $actionDepot     l'adresse qui reçoit les fichiers déposés
 * @var string $idDepot         de quoi donner un identifiant unique au champ de dépôt
 * @var string $surPlace        ' data-envoi-fenetre' dans une fenêtre
 * @var string $champPage       le champ caché qui dit où revenir après un retrait
 * @var bool   $dansUneFenetre
 */
?>
<?php
// Les pages web (.html) sont des quizz : elles ont leur rayon (cours/_fiche_quizz), on ne les liste pas ici.
$fichiersFiche = array_values(array_filter($fichiersFiche, static fn (array $f): bool => !Fichiers::estPageWeb((string) $f['nom_origine'])));
?>
  <?php // --- Fichiers et images propres à la fiche ------------------- ?>
  <div class="fiche__rayon<?= $fichiersFiche === [] ? ' fiche__rayon--vide' : '' ?>">
    <h4 class="fiche__titre"><?= e(t('fiche.fichiers')) ?></h4>

    <?php if ($fichiersFiche === []): ?>
      <p class="discret fiche__vide"><?= e(t('commun.rien')) ?></p>
    <?php else: ?>
      <ul class="liste-fichiers">
        <?php foreach ($fichiersFiche as $f): ?>
          <?php
          $estImage = Fichiers::estImage($f['mime']);
          $estAudio = Fichiers::estAudio((string) $f['mime'], (string) $f['nom_origine']);
          $estVideo = Fichiers::estVideo((string) $f['mime'], (string) $f['nom_origine']);
          // Un PDF se lit sur place, comme une vidéo : le navigateur sait le faire seul.
          $estPdf = Fichiers::estPdf((string) $f['mime'], (string) $f['nom_origine']);
          // Pour un PDF, « durée » veut dire nombre de pages, et « position » page atteinte.
          $pages = $estPdf ? (int) $f['duree_lecture'] : 0;
          // Page atteinte : zéro tant qu'on n'a pas tourné de page, comme un
          // enregistrement jamais lancé. La page ouverte, elle, vaut au moins 1.
          $pageAtteinte = $pages > 0 ? min(max(0, (int) $f['position_lecture']), $pages) : 0;
          $pageLue = max(1, $pageAtteinte);
          ?>
          <li class="fichier<?= $estAudio || $estVideo || $estPdf ? ' fichier--media' : '' ?>">
            <?php // L'image se voit en entier plus bas : la vignette ferait double emploi. ?>
            <span class="fichier__icone" aria-hidden="true"><?= Fichiers::icone($f['mime'], $f['nom_origine']) ?></span>
            <span style="min-width:0">
              <?php $apercu = ApercuDocument::possible((string) $f['nom_origine']); ?>
              <a class="fichier__nom"
                 href="<?= url('fichiers/' . $f['id'] . ($apercu ? '/apercu' : '')) ?>"
                 <?php // L'aperçu s'ouvre dans une fenêtre, par-dessus le cours. ?>
                 <?= $apercu ? 'data-fenetre' : ' target="_blank" rel="noopener"' ?>>
                <?= e($f['nom_origine']) ?>
              </a><br>
              <span class="fichier__meta"><?= e(taille_lisible((int) $f['taille'])) ?></span>
            </span>
            <span class="fichier__actions">
              <?= Vue::rendre('cours/_telecharger', ['fichier' => $f, 'compact' => true]) ?>
              <form<?= $surPlace ?> method="post" action="<?= url('fichiers/' . $f['id'] . '/supprimer') ?>" class="en-ligne"
                    data-confirmation="<?= e(t('fiche.retirer_fichier_sur')) ?>">
                <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>"><?= $champPage ?>
                <button class="bouton bouton--discret bouton--petit" type="submit" title="<?= e(t('commun.retirer')) ?>">✕</button>
              </form>
            </span>

            <?php if ($estAudio || $estVideo || $pages > 0): ?>
              <?php
              /*
               * L'anneau dit où l'on en est : dans l'enregistrement pour un
               * média, dans les pages pour un PDF. Le lecteur et les flèches
               * le mettent à jour en cours de route ; sans JavaScript, il
               * montre la dernière position connue.
               */
              $avance = avancement_lecture($f);
              ?>
              <span class="fichier__avancement" data-avancement="<?= (int) $f['id'] ?>">
                <?= Vue::rendre('cours/_anneau', [
                    'pourcentage' => $avance,
                    'titre'       => t('fiche.avancement_de', ['nom' => $f['nom_origine']]),
                ]) ?>
                <span class="fichier__minutage">
                  <?php if ($pages > 0 && $pageAtteinte > 0): ?>
                    <?= e(t('fiche.page_sur', ['page' => $pageAtteinte, 'total' => $pages])) ?>
                  <?php elseif ($pages > 0): ?>
                    <?= e(t('fiche.pas_encore_lu')) ?>
                  <?php elseif ((int) $f['duree_lecture'] > 0): ?>
                    <?= e(duree_lisible((int) $f['position_lecture'])) ?>
                    / <?= e(duree_lisible((int) $f['duree_lecture'])) ?>
                  <?php else: ?>
                    <?= e(t('fiche.pas_encore_lu')) ?>
                  <?php endif; ?>
                </span>
              </span>
            <?php endif; ?>

            <?php // Le lecteur du navigateur suffit : rien à charger de plus. ?>
            <?php if ($estAudio): ?>
              <audio class="fichier__lecteur" controls preload="metadata"
                     data-lecteur="<?= (int) $f['id'] ?>"
                     data-position="<?= (int) $f['position_lecture'] ?>"
                     data-position-url="<?= url('fichiers/' . $f['id'] . '/position') ?>"
                     src="<?= url('fichiers/' . $f['id']) ?>">
                <a href="<?= url('fichiers/' . $f['id'], ['telecharger' => 1]) ?>">
                  <?= e(t('fiche.telecharger_audio')) ?>
                </a>
              </audio>
            <?php elseif ($estVideo): ?>
              <video class="fichier__lecteur fichier__lecteur--video" controls preload="metadata"
                     data-lecteur="<?= (int) $f['id'] ?>"
                     data-position="<?= (int) $f['position_lecture'] ?>"
                     data-position-url="<?= url('fichiers/' . $f['id'] . '/position') ?>"
                     src="<?= url('fichiers/' . $f['id']) ?>">
                <a href="<?= url('fichiers/' . $f['id'], ['telecharger' => 1]) ?>">
                  <?= e(t('fiche.telecharger_video')) ?>
                </a>
              </video>
            <?php endif; ?>

            <?php if ($estPdf): ?>
              <?php
              /*
               * La visionneuse du navigateur, dans la fiche même : pas de page
               * intermédiaire pour relire deux annales. « lazy » évite de
               * charger tous les documents d'un coup quand il y en a plusieurs.
               */
              ?>
              <span class="fichier__pdf" data-pdf="<?= (int) $f['id'] ?>"
                    data-pages="<?= $pages ?>" data-page="<?= $pageLue ?>"
                    data-atteinte="<?= $pageAtteinte ?>"
                    data-position-url="<?= url('fichiers/' . $f['id'] . '/position') ?>">
                <?php // Le volet est étroit : la page est ajustée à sa largeur, sans le panneau des vignettes. ?>
                <iframe src="<?= url('fichiers/' . $f['id']) ?>#page=<?= $pageLue ?>&amp;navpanes=0&amp;view=FitH"
                        loading="lazy" title="<?= e($f['nom_origine']) ?>"></iframe>

                <?php if ($pages > 0): ?>
                  <?php
                  /*
                   * Un document d'une seule page (un résumé, une affiche) n'a pas de page à tourner : seul « Terminer »
                   * lui reste, pour dire qu'on l'a lu.
                   *
                   * La visionneuse du navigateur ne dit pas où l'on en est : ces
                   * flèches sont notre seul moyen de le savoir, et elles font
                   * avancer l'anneau à mesure qu'on tourne les pages.
                   */
                  ?>
                  <span class="fichier__pages">
                    <?php if ($pages > 1): ?>
                      <button class="bouton bouton--discret bouton--petit" type="button"
                              data-pdf-recule title="<?= e(t('fiche.page_precedente')) ?>">◀</button>
                      <span class="fichier__page" data-pdf-libelle aria-live="polite">
                        <?= e(t('fiche.page_sur', ['page' => $pageLue, 'total' => $pages])) ?>
                      </span>
                      <button class="bouton bouton--discret bouton--petit" type="button"
                              data-pdf-avance title="<?= e(t('fiche.page_suivante')) ?>">▶</button>
                    <?php endif; ?>
                    <?php
                    /*
                     * Lu en diagonale, ou déjà connu : on le déclare fini sans
                     * tourner les pages. Une fois fini, le même bouton défait
                     * ce qu'il a fait — se tromper de document arrive.
                     */
                    $luEnEntier = $pageAtteinte >= $pages;
                    ?>
                    <button class="bouton bouton--discret bouton--petit" type="button"
                            data-pdf-fini
                            title="<?= e(t($luEnEntier ? 'fiche.doc_non_lu' : 'fiche.doc_lu')) ?>"><?=
                        e(t($luEnEntier ? 'commun.annuler' : 'commun.terminer')) ?></button>
                  </span>
                <?php endif; ?>

                <span class="fichier__repli">
                  <?= e(t('fiche.pdf_repli')) ?>
                  <a href="<?= url('fichiers/' . $f['id']) ?>" target="_blank" rel="noopener">
                    <?= e(t('fiche.pdf_onglet')) ?>
                  </a>
                </span>
              </span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>

      <?php
      /*
       * Les images d'une fiche forment une suite, comme les pages d'un PDF :
       * on les feuillette sur place plutôt que de les empiler, l'anneau dit
       * combien on en a vues, et « Terminer » les déclare toutes revues.
       *
       * Sans JavaScript, elles s'affichent toutes à la file : c'est le
       * script qui n'en montre qu'une à la fois.
       */
      $images = array_values(array_filter(
          $fichiersFiche,
          static fn (array $image): bool => Fichiers::estImage($image['mime'])
      ));
      ?>
      <?php if ($images !== []): ?>
        <?php
        $total = count($images);
        $vues = count(array_filter(
            $images,
            static fn (array $image): bool => (int) $image['position_lecture'] >= 1
        ));
        // On rouvre sur la première image qu'on n'a pas encore vue.
        $depart = 0;
        foreach ($images as $rang => $image) {
            if ((int) $image['position_lecture'] < 1) {
                $depart = $rang;
                break;
            }
        }
        $compte = $vues === 0
            ? t('fiche.image_pas_vue')
            : tn('fiche.images_vues', $vues, ['total' => $total]);
        ?>
        <div class="fichier__images" data-images data-total="<?= $total ?>"
             data-depart="<?= $depart ?>">
          <?php foreach ($images as $rang => $image): ?>
            <figure class="fichier__vue" data-image="<?= $rang ?>"
                    data-vue="<?= (int) $image['position_lecture'] >= 1 ? '1' : '' ?>"
                    data-position-url="<?= url('fichiers/' . $image['id'] . '/position') ?>">
              <?php // Dans une fenêtre, avec de quoi zoomer ; sans script, le lien ouvre la page d'aperçu. ?>
              <a href="<?= url('fichiers/' . $image['id'] . '/apercu') ?>" <?= $dansUneFenetre ? 'data-fenetre-dessus' : 'data-fenetre' ?>
                 title="<?= e(t('fiche.image_grand')) ?>">
                <img src="<?= url('fichiers/' . $image['id']) ?>" loading="lazy"
                     alt="<?= e($image['nom_origine']) ?>">
              </a>
              <figcaption><?= e($image['nom_origine']) ?></figcaption>
            </figure>
          <?php endforeach; ?>

          <span class="fichier__avancement" data-avancement="images">
            <?= Vue::rendre('cours/_anneau', [
                'pourcentage' => $total > 0 ? (int) round($vues / $total * 100) : null,
                'titre'       => t('fiche.images_vues_titre'),
            ]) ?>
            <span class="fichier__minutage" data-images-compte><?= e($compte) ?></span>
          </span>

          <span class="fichier__pages">
            <?php if ($total > 1): ?>
              <button class="bouton bouton--discret bouton--petit" type="button"
                      data-images-recule title="<?= e(t('fiche.image_precedente')) ?>">◀</button>
              <span class="fichier__page" data-images-libelle aria-live="polite">
                <?= e(t('fiche.image_sur', ['rang' => $depart + 1, 'total' => $total])) ?>
              </span>
              <button class="bouton bouton--discret bouton--petit" type="button"
                      data-images-avance title="<?= e(t('fiche.image_suivante')) ?>">▶</button>
            <?php endif; ?>
            <?php
            /*
             * Déjà connues, ou parcourues d'un coup d'œil : on les déclare
             * vues. Une fois toutes vues, le même bouton les remet à zéro.
             */
            ?>
            <button class="bouton bouton--discret bouton--petit" type="button"
                    data-images-fini
                    title="<?= e(t($vues >= $total
                        ? ($total > 1 ? 'fiche.images_non_vues' : 'fiche.image_non_vue')
                        : ($total > 1 ? 'fiche.images_vues_toutes' : 'fiche.image_vue'))) ?>"><?=
                e(t($vues >= $total ? 'commun.annuler' : 'commun.terminer')) ?></button>
          </span>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <form<?= $surPlace ?> method="post" action="<?= $actionDepot ?>"
          enctype="multipart/form-data" class="depot depot--mince" data-depot>
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>"><?= $champPage ?>
      <label class="depot__zone" for="depot-fiche-<?= $idDepot ?>">
        <span class="depot__icone" aria-hidden="true">📎</span>
        <span><strong><?= e(t('fiche.deposez')) ?></strong>
          <span class="discret"><?= e(t('fiche.deposez_aide')) ?></span></span>
      </label>
      <input type="file" id="depot-fiche-<?= $idDepot ?>" name="fichiers[]" multiple
             class="depot__champ" data-depot-champ>
      <button class="bouton bouton--petit bouton--bloc" type="submit" data-depot-envoi><?= e(t('fiche.joindre')) ?></button>
    </form>
  </div>
