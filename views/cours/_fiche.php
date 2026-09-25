<?php
/**
 * La fiche de révision d'un cours : son texte et ce qui lui est rattaché.
 *
 * Le même fragment sert au volet ouvert depuis un cours et à la page qui
 * ne montre que la fiche. $surPage dit laquelle, pour que les formulaires
 * ramènent là d'où l'on vient.
 *
 * @var array $cours, $fichiersFiche, $parType, $autresCours, $evenementsChoix
 * @var array $cartes  combien de cartes a ce cours, et combien sont dues
 * @var string $fiche
 * @var bool $surPage
 */
$surPage = $surPage ?? false;
$nbElements = count($fichiersFiche) + array_sum(array_map('count', $parType));

// Dans une fenêtre, chaque formulaire de la fiche s'y enregistre.
$dansUneFenetre = $dansUneFenetre ?? false;
$surPlace = $dansUneFenetre ? ' data-envoi-fenetre' : '';

// Sur sa propre page, chaque formulaire doit y ramener plutôt que d'ouvrir le cours.
$champPage = $surPage ? '<input type="hidden" name="page" value="fiche">' : '';

// Le lecteur enregistre sa position sans recharger la page : il lui faut le
// jeton, que les formulaires portent déjà mais qu'aucun ne lui prête.
$jetonLecture = Session::jetonCsrf();

$cartes = $cartes ?? ['total' => 0, 'a_revoir' => 0, 'dues' => [], 'avancement' => null];

// L'avancement de la fiche entière : la moyenne des anneaux qu'elle contient,
// le paquet de cartes compris — il se mesure comme un enregistrement.
$avancementFiche = avancement_anneaux(
    fichiers_suivis($fichiersFiche),
    $cartes['avancement'] === null ? [] : [$cartes['avancement']]
);
?>
<span hidden data-jeton-lecture="<?= e($jetonLecture) ?>"></span>

<section class="carte fiche<?= $surPage ? '' : ' volet' ?>" id="revision">
  <div class="volet__entete">
    <span class="volet__icone" aria-hidden="true">📝</span>
    <div style="min-width:0">
      <h2 style="margin:0"><?= e(t('fiche.titre')) ?></h2>
      <?php if (!$surPage): ?>
        <p class="discret" style="margin:.15rem 0 0"><?= e($cours['titre']) ?></p>
      <?php endif; ?>
    </div>

    <?php if ($avancementFiche['total'] > 0): ?>
      <?php
      /*
       * Où l'on en est de toute la fiche : la moyenne des anneaux ci-dessous.
       * Le script la refait à chaque fois que l'un d'eux bouge, pour qu'elle
       * ne mente pas pendant qu'on écoute ou qu'on tourne une page.
       */
      ?>
      <span class="fiche__total" data-total-fiche>
        <?= Vue::rendre('cours/_anneau', [
            'pourcentage' => $avancementFiche['pourcentage'],
            'titre'       => t('fiche.avancement'),
        ]) ?>
        <span class="fiche__total-mot">
          <?= tn('fiche.suivis', $avancementFiche['total']) ?>
        </span>
      </span>
    <?php endif; ?>
  </div>

  <?php // Sur sa propre page, la note et ce qui lui est rattaché se font face. ?>
  <div class="fiche-grille">
    <div class="fiche-grille__note">
  <form<?= $surPlace ?> method="post" action="<?= url('cours/' . $cours['id'] . '/revision') ?>" style="margin-top:1rem">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>"><?= $champPage ?>

    <div class="champ">
      <label for="fiche_revision"><?= e(t('fiche.a_retenir')) ?></label>
      <textarea id="fiche_revision" name="fiche_revision" class="fiche__texte" data-texte-riche
                placeholder="<?= e(t('fiche.a_retenir_exemple')) ?>"><?= e(TexteRiche::pourEditeur($fiche)) ?></textarea>
      <?php
      /*
       * Une zone de saisie s'imprime mal : seule la partie visible sort, avec
       * sa barre de défilement. Cette copie ne sert qu'au papier, et le script
       * la tient à jour pendant la frappe pour qu'on puisse imprimer un texte
       * pas encore enregistré.
       */
      ?>
      <div class="fiche__impression texte-riche-affiche" data-impression-fiche aria-hidden="true"><?= TexteRiche::versHtml($fiche) ?></div>
      <span class="champ__aide"><?= e(t('fiche.mise_en_forme_aide')) ?></span>
    </div>

    <div class="actions">
      <button class="bouton" type="submit"><?= e(t('fiche.enregistrer')) ?></button>
      <?php if ($surPage): ?>
        <?php
        // Toujours vers la liste des fiches, fenêtre ou non : la fermer ramènerait
        // à la page d'où on l'a ouverte, qui n'est souvent pas cette liste.
        ?>
        <a class="bouton bouton--discret" href="<?= url('revision') ?>"><?= e(t('fiche.retour_fiches')) ?></a>
      <?php else: ?>
        <a class="bouton bouton--discret" href="<?= url('cours/' . $cours['id']) ?>"><?= e(t('commun.fermer')) ?></a>
      <?php endif; ?>
    </div>
  </form>

    </div>

    <div class="fiche-grille__elements<?= $nbElements === 0 ? ' fiche-grille__elements--vide' : '' ?>">

  <h3 class="volet__section" style="margin-top:0">
    <?= e(t('fiche.elements')) ?>
    <?php if ($nbElements > 0): ?><span class="discret">(<?= $nbElements ?>)</span><?php endif; ?>
  </h3>
  <p class="champ__aide" style="margin:-.35rem 0 .9rem">
    <?= e(t('fiche.elements_aide')) ?>
  </p>

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

            <?php if ($estAudio || $estVideo || $pages > 1): ?>
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
                  <?php if ($pages > 1 && $pageAtteinte > 0): ?>
                    <?= e(t('fiche.page_sur', ['page' => $pageAtteinte, 'total' => $pages])) ?>
                  <?php elseif ($pages > 1): ?>
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

                <?php if ($pages > 1): ?>
                  <?php
                  /*
                   * La visionneuse du navigateur ne dit pas où l'on en est : ces
                   * flèches sont notre seul moyen de le savoir, et elles font
                   * avancer l'anneau à mesure qu'on tourne les pages.
                   */
                  ?>
                  <span class="fichier__pages">
                    <button class="bouton bouton--discret bouton--petit" type="button"
                            data-pdf-recule title="<?= e(t('fiche.page_precedente')) ?>">◀</button>
                    <span class="fichier__page" data-pdf-libelle aria-live="polite">
                      <?= e(t('fiche.page_sur', ['page' => $pageLue, 'total' => $pages])) ?>
                    </span>
                    <button class="bouton bouton--discret bouton--petit" type="button"
                            data-pdf-avance title="<?= e(t('fiche.page_suivante')) ?>">▶</button>
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
              <a href="<?= url('fichiers/' . $image['id']) ?>" target="_blank" rel="noopener"
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

    <form<?= $surPlace ?> method="post" action="<?= url('cours/' . $cours['id'] . '/revision/fichiers') ?>"
          enctype="multipart/form-data" class="depot depot--mince" data-depot>
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>"><?= $champPage ?>
      <label class="depot__zone" for="depot-fiche-<?= (int) $cours['id'] ?>">
        <span class="depot__icone" aria-hidden="true">📎</span>
        <span><strong><?= e(t('fiche.deposez')) ?></strong>
          <span class="discret"><?= e(t('fiche.deposez_aide')) ?></span></span>
      </label>
      <input type="file" id="depot-fiche-<?= (int) $cours['id'] ?>" name="fichiers[]" multiple
             class="depot__champ" data-depot-champ>
      <button class="bouton bouton--petit bouton--bloc" type="submit" data-depot-envoi><?= e(t('fiche.joindre')) ?></button>
    </form>
  </div>

  <?php // --- Cartes de révision --------------------------------------- ?>
  <?php
  /*
   * Les cartes du cours, vues depuis sa fiche : combien il y en a, combien
   * sont dues aujourd'hui, et de quoi s'y mettre. Le paquet lui-même se gère
   * ailleurs — ici, on ne fait que le rejoindre.
   */
  ?>
  <div class="fiche__rayon<?= $cartes['total'] === 0 ? ' fiche__rayon--vide' : '' ?>">
    <h4 class="fiche__titre"><?= e(t('fiche.cartes')) ?></h4>

    <div data-cartes-resume>
      <?php if ($cartes['total'] === 0): ?>
        <p class="discret fiche__vide"><?= e(t('fiche.aucune_carte')) ?></p>
      <?php else: ?>
        <p class="fiche__cartes">
          <?php // L'anneau du paquet : la boîte moyenne, de 1 à 5, ramenée en pourcentage. ?>
          <?= Vue::rendre('cours/_anneau', [
              'pourcentage' => $cartes['avancement'],
              'titre'       => t('fiche.cartes_avancement'),
          ]) ?>
          <span>
            <?= tn('fiche.nb_cartes', (int) $cartes['total']) ?>
          <?php if ($cartes['a_revoir'] > 0): ?>
            · <span class="carte-du"><?= e(t('fiche.a_revoir', ['n' => $cartes['a_revoir']])) ?></span>
          <?php else: ?>
            · <span class="discret"><?= e(t('fiche.rien_a_revoir')) ?></span>
          <?php endif; ?>
          </span>
        </p>
      <?php endif; ?>

      <p class="actions fiche__cartes-actions">
        <?php if ($cartes['a_revoir'] > 0): ?>
          <?php
          /*
           * Le lien mène à la page de révision : sans JavaScript, c'est ce qui
           * se passe. Avec, le script l'intercepte et déplie la séance ici même,
           * sans quitter la fiche qu'on est en train de relire.
           */
          ?>
          <a class="bouton bouton--petit" data-ouvrir-seance
             href="<?= url('cartes/seance', ['cours' => $cours['id']]) ?>"><?= e(t('fiche.reviser')) ?></a>
        <?php endif; ?>
        <?php // Les cartes se fabriquent dans l'onglet Cartes, et nulle part ailleurs. ?>
        <a class="bouton bouton--secondaire bouton--petit"
           href="<?= $cartes['total'] === 0
               ? url('cartes')
               : url('cours/' . $cours['id'] . '/cartes') ?>">
          <?= e(t($cartes['total'] === 0 ? 'fiche.en_fabriquer' : 'fiche.voir_paquet')) ?>
        </a>

        <?php if ($cartes['total'] > 0): ?>
          <form<?= $surPlace ?> method="post" action="<?= url('cours/' . $cours['id'] . '/cartes/rezero') ?>"
                class="en-ligne"
                data-confirmation="<?= e(t('fiche.rezero_sur', ['n' => $cartes['total']])) ?>">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <input type="hidden" name="retour" value="<?= $surPage ? 'fiche' : 'volet' ?>">
            <button class="bouton bouton--discret bouton--petit" type="submit"
                    title="<?= e(t('fiche.rezero_aide')) ?>">
              <?= e(t('fiche.rezero')) ?>
            </button>
          </form>
        <?php endif; ?>
      </p>
    </div>

    <?php if (($cartes['dues'] ?? []) !== []): ?>
      <div class="fiche__seance" data-seance-sur-place hidden>
        <?= Vue::rendre('cartes/_seance', [
            'cartes' => $cartes['dues'],
            'rezeroCours'    => (int) $cours['id'],
            'rezeroTotal'    => $cartes['total'],
            'duesEnTout'     => $cartes['a_revoir'],
            'paquetTotal'    => $cartes['total'],
            'paquetSomme'    => $cartes['somme_boites'],
            'rezeroRetour'   => $surPage ? 'fiche' : 'volet',
            'dansUneFenetre' => $dansUneFenetre,
        ]) ?>
      </div>
    <?php endif; ?>
  </div>

  <?php // --- Liens web ----------------------------------------------- ?>
  <div class="fiche__rayon<?= $parType['lien'] === [] ? ' fiche__rayon--vide' : '' ?>">
    <h4 class="fiche__titre"><?= e(t('fiche.liens')) ?></h4>

    <?php if ($parType['lien'] === []): ?>
      <p class="discret fiche__vide"><?= e(t('commun.rien')) ?></p>
    <?php else: ?>
      <ul class="fiche__liste">
        <?php foreach ($parType['lien'] as $lien): ?>
          <li>
            <a href="<?= e((string) $lien['url']) ?>" target="_blank" rel="noopener noreferrer">
              <?= e((string) $lien['libelle']) ?> ↗
            </a>
            <span class="fiche__url discret"><?= e((string) parse_url((string) $lien['url'], PHP_URL_HOST)) ?></span>
            <?= Vue::rendre('cours/_retirer-element', ['element' => $lien, 'surPage' => $surPage, 'dansUneFenetre' => $dansUneFenetre]) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <details class="fiche__ajout">
      <summary><?= e(t('fiche.ajouter_lien')) ?></summary>
      <form<?= $surPlace ?> method="post" action="<?= url('cours/' . $cours['id'] . '/revision/elements') ?>">
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>"><?= $champPage ?>
        <input type="hidden" name="type" value="lien">
        <div class="champ">
          <label for="lien-url"><?= e(t('fiche.adresse')) ?></label>
          <input type="url" id="lien-url" name="url" required placeholder="https://…">
        </div>
        <div class="champ">
          <label for="lien-libelle"><?= e(t('fiche.intitule')) ?> <span class="discret"><?= e(t('commun.facultatif')) ?></span></label>
          <input type="text" id="lien-libelle" name="libelle" maxlength="200"
                 placeholder="<?= e(t('fiche.lien_exemple')) ?>">
        </div>
        <button class="bouton bouton--petit" type="submit"><?= e(t('fiche.ajouter_le_lien')) ?></button>
      </form>
    </details>
  </div>

  <?php // --- Renvois vers d'autres cours ----------------------------- ?>
  <div class="fiche__rayon<?= $parType['cours'] === [] ? ' fiche__rayon--vide' : '' ?>">
    <h4 class="fiche__titre"><?= e(t('fiche.autres_cours')) ?></h4>

    <?php if ($parType['cours'] === []): ?>
      <p class="discret fiche__vide"><?= e(t('commun.rien')) ?></p>
    <?php else: ?>
      <ul class="fiche__liste">
        <?php foreach ($parType['cours'] as $renvoi): ?>
          <li>
            <a href="<?= url('cours/' . (int) $renvoi['cible_cours_id']) ?>">
              <?= e((string) $renvoi['cours_titre']) ?>
            </a>
            <?php if (($renvoi['libelle'] ?? '') !== ''): ?>
              <span class="fiche__url discret"><?= e((string) $renvoi['libelle']) ?></span>
            <?php endif; ?>
            <?= Vue::rendre('cours/_retirer-element', ['element' => $renvoi, 'surPage' => $surPage, 'dansUneFenetre' => $dansUneFenetre]) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($autresCours === []): ?>
      <p class="champ__aide"><?= e(t('fiche.aucun_autre_cours')) ?></p>
    <?php else: ?>
      <details class="fiche__ajout">
        <summary><?= e(t('fiche.renvoyer_cours')) ?></summary>
        <form<?= $surPlace ?> method="post" action="<?= url('cours/' . $cours['id'] . '/revision/elements') ?>">
          <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>"><?= $champPage ?>
          <input type="hidden" name="type" value="cours">
          <div class="champ">
            <label for="renvoi-cours"><?= e(t('fiche.cours')) ?></label>
            <select id="renvoi-cours" name="cible" required>
              <?php foreach ($autresCours as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= e($c['titre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="champ">
            <label for="renvoi-note"><?= e(t('fiche.pourquoi')) ?> <span class="discret"><?= e(t('commun.facultatif')) ?></span></label>
            <input type="text" id="renvoi-note" name="libelle" maxlength="200"
                   placeholder="<?= e(t('fiche.renvoi_exemple')) ?>">
          </div>
          <button class="bouton bouton--petit" type="submit"><?= e(t('fiche.ajouter_renvoi')) ?></button>
        </form>
      </details>
    <?php endif; ?>
  </div>

  <?php // --- Évènements du calendrier -------------------------------- ?>
  <div class="fiche__rayon<?= $parType['evenement'] === [] ? ' fiche__rayon--vide' : '' ?>">
    <h4 class="fiche__titre"><?= e(t('fiche.au_calendrier')) ?></h4>

    <?php if ($parType['evenement'] === []): ?>
      <p class="discret fiche__vide"><?= e(t('commun.rien')) ?></p>
    <?php else: ?>
      <ul class="fiche__liste">
        <?php foreach ($parType['evenement'] as $renvoi): ?>
          <li>
            <a href="<?= url('evenements/' . (int) $renvoi['cible_evenement_id'] . '/modifier') ?>">
              <?= e(($renvoi['type_icone'] ?? '📌') . ' ' . (string) $renvoi['evenement_titre']) ?>
            </a>
            <span class="fiche__url discret">
              <?= e(date_fr((string) $renvoi['evenement_debut'], (int) $renvoi['journee_entiere'] === 0)) ?>
              <?= (int) $renvoi['termine'] === 1 ? e(t('fiche.termine')) : '' ?>
            </span>
            <?= Vue::rendre('cours/_retirer-element', ['element' => $renvoi, 'surPage' => $surPage, 'dansUneFenetre' => $dansUneFenetre]) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($evenementsChoix === []): ?>
      <p class="champ__aide"><?= e(t('fiche.calendrier_vide')) ?></p>
    <?php else: ?>
      <details class="fiche__ajout">
        <summary><?= e(t('fiche.rattacher_evenement')) ?></summary>
        <form<?= $surPlace ?> method="post" action="<?= url('cours/' . $cours['id'] . '/revision/elements') ?>">
          <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>"><?= $champPage ?>
          <input type="hidden" name="type" value="evenement">
          <div class="champ">
            <label for="renvoi-evt"><?= e(t('fiche.evenement')) ?></label>
            <select id="renvoi-evt" name="cible" required>
              <?php foreach ($evenementsChoix as $evt): ?>
                <option value="<?= (int) $evt['id'] ?>">
                  <?= e(date_fr((string) $evt['debut'], false) . ' — ' . $evt['titre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="bouton bouton--petit" type="submit"><?= e(t('fiche.rattacher')) ?></button>
        </form>
      </details>
    <?php endif; ?>
  </div>
    </div>
  </div>
</section>
