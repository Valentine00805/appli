<?php
/**
 * @var array $cours, $fichiers, $tags, $evenements
 * @var array $fichiersFiche, $elements, $autresCours, $evenementsChoix
 * @var bool $revision  le volet de révision est-il ouvert ?
 * @var bool $dansUneFenetre  rendue seule, pour être posée dans une fenêtre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
// Les formulaires qui s'enregistrent sans quitter la fenêtre.
$surPlace = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$images = array_filter($fichiers, static fn (array $f): bool => Fichiers::estImage($f['mime']));
$fiche = (string) ($cours['fiche_revision'] ?? '');

// Les éléments arrivent triés par type : on les range par rayon pour l'affichage.
$parType = ['lien' => [], 'cours' => [], 'evenement' => []];
foreach ($elements as $element) {
    $parType[$element['type']][] = $element;
}
$nbElements = count($elements) + count($fichiersFiche);
?>

<div class="entete-page"<?= $dansUneFenetre ? ' data-large data-document' : '' ?>>
  <div>
    <?php // Dans une fenêtre, la liste des cours est juste derrière : la croix y ramène. ?>
    <?php if (!$dansUneFenetre): ?>
      <p class="discret" style="margin-bottom:.35rem">
        <a href="<?= url('cours') ?>">← Mes cours</a>
      </p>
    <?php endif; ?>
    <h1><?= e($cours['titre']) ?></h1>
    <p>
      <?php if ($cours['matiere_nom'] !== null): ?>
        <span class="pastille" style="background:<?= e($cours['matiere_couleur']) ?>;color:<?= e(couleur_texte($cours['matiere_couleur'])) ?>">
          <?= e($cours['matiere_nom']) ?>
        </span>
      <?php endif; ?>
      <span class="discret"><?= e(t('cours.cree_modifie', ['cree' => date_fr($cours['created_at'], false), 'modifie' => date_fr($cours['updated_at'])])) ?></span>
    </p>
  </div>

  <div class="actions">
    <form method="post" action="<?= url('cours/' . $cours['id'] . '/favori') ?>" class="en-ligne"<?= $surPlace ?>>
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <button class="bouton bouton--secondaire" type="submit"
              title="<?= e((int) $cours['favori'] === 1 ? t('cours.retirer_favori') : t('cours.ajouter_favori')) ?>">
        <?= (int) $cours['favori'] === 1 ? '⭐' : '☆' ?> <?= e(t('cours.favori')) ?>
      </button>
    </form>
    <?php
    /*
     * La fiche de révision s'ouvre dans une fenêtre, par-dessus le cours. Sans
     * script, le lien mène à la fiche seule ; le volet reste à l'adresse
     * « ?revision=1 », qui l'ouvre à côté du cours.
     */
    ?>
    <a class="bouton bouton--secondaire<?= $revision ? ' est-actif' : '' ?>"
       href="<?= url('revision/' . $cours['id']) ?>" data-fenetre>
      <?= e(t('cours.revision')) ?><?= $fiche !== '' || $nbElements > 0 ? ' •' : '' ?>
    </a>
    <?php // Partager le cours : à ses amis, ou par un lien. ?>
    <a class="bouton bouton--secondaire bouton-partage" href="<?= url('partager/cours/' . $cours['id']) ?>" <?= $dansUneFenetre ? 'data-fenetre-dessus' : 'data-fenetre' ?>>
      <?= Partages::icone() ?> <?= e(t('evt.partager')) ?>
    </a>
    <?php // Le nouvel évènement, déjà lié au cours, s'ouvre dans une fenêtre. ?>
    <a class="bouton bouton--secondaire" href="<?= url('evenements/nouveau', ['cours' => $cours['id']]) ?>" data-fenetre><?= e(t('cours.planifier')) ?></a>
    <?php // Le formulaire s'ouvre dans une fenêtre, par-dessus le cours. ?>
    <a class="bouton" href="<?= url('cours/' . $cours['id'] . '/modifier') ?>" data-fenetre><?= e(t('evt.modifier')) ?></a>
  </div>
</div>

<div class="colonnes<?= $revision ? ' colonnes--volet' : '' ?>">
  <?php // Contenu du cours et cartes qui en dependent : une seule colonne. ?>
  <div class="cours-colonne">
    <?php $sansContenu = trim((string) $cours['contenu']) === ''; ?>
    <article class="carte">
      <?php if ($sansContenu): ?>
        <p class="discret"><?= e(t('cours.sans_contenu')) ?></p>
      <?php else: ?>
        <?php // Le contenu, à emporter : en PDF, mis en pages avec son sommaire. ?>
        <p class="contenu-cours__actions">
          <a class="bouton bouton--secondaire bouton--petit" href="<?= url('cours/' . $cours['id'] . '/pdf') ?>"
             title="<?= e(t('cours.pdf_aide')) ?>"><?= e(t('cours.pdf')) ?></a>
        </p>
        <?php // Nettoyé à l'affichage : la mise en forme passe, rien d'autre. ?>
        <div class="contenu-cours texte-riche-affiche"><?= TexteRiche::versHtml($cours['contenu']) ?></div>
      <?php endif; ?>

      <?php
      /*
       * Le texte se corrige ici même : « Modifier » en haut de page ouvre la
       * fiche entière — titre, matière, dossier —, ce qui est beaucoup pour une
       * faute de frappe. « details » suffit à déplier, sans une ligne de script.
       */
      ?>
      <?php // Ce qu'on a écrit sous ce cours, quand on l'a partagé. ?>
      <?php $nbModifications = Partages::nbModifications('cours', (int) $cours['id']); ?>
      <?php if ($nbModifications > 0): ?>
        <p class="discret" style="margin:.4rem 0 0">
          🕘 <a href="<?= e(Partages::adresseHistorique('cours', (int) $cours['id'])) ?>" <?= $dansUneFenetre ? 'data-fenetre-dessus' : 'data-fenetre' ?>><?= $nbModifications ?> modification<?= $nbModifications > 1 ? 's' : '' ?></a>
          depuis que ce cours est partagé.
        </p>
      <?php endif; ?>
      <?php $nbCommentaires = Partages::nbCommentaires('cours', (int) $cours['id']); ?>
      <?php if ($nbCommentaires > 0): ?>
        <p class="discret" style="margin:.4rem 0 0">
          💬 <a href="<?= url('partages/cours/' . (int) $cours['id'] . '/commentaires') ?>" <?= $dansUneFenetre ? 'data-fenetre-dessus' : 'data-fenetre' ?>><?= e(t('cours.commentaires', ['n' => $nbCommentaires])) ?></a>
          <?= e(t('cours.commentaires_suite')) ?>
        </p>
      <?php endif; ?>
      <details class="edition-contenu"<?= $sansContenu ? ' open' : '' ?>>
        <summary class="edition-contenu__ouvrir">
          ✏️ <?= e($sansContenu ? t('cours.ecrire_contenu') : t('cours.modifier_contenu')) ?>
        </summary>

        <form method="post" action="<?= url('cours/' . $cours['id'] . '/contenu') ?>"<?= $surPlace ?>>
          <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
          <?php // Revenir là où l'on était : le volet de révision reste ouvert. ?>
          <?php if ($revision): ?>
            <input type="hidden" name="revision" value="1">
          <?php endif; ?>

          <div class="champ">
            <label for="contenu-cours" class="legende"><?= e(t('cours.le_cours')) ?></label>
            <textarea id="contenu-cours" name="contenu" class="edition-contenu__texte" data-texte-riche="complet" data-tailles="<?= e(implode(',', TexteRiche::TAILLES)) ?>"
                      placeholder="<?= e(t('cours.contenu_placeholder')) ?>"><?= e(TexteRiche::pourEditeur($cours['contenu'])) ?></textarea>
          </div>

          <p class="actions">
            <button class="bouton bouton--petit" type="submit"><?= e(t('cours.enregistrer_contenu')) ?></button>
          </p>
        </form>
      </details>
    </article>

    <div class="pile">
      <section class="carte">
        <h2><?= e(t('cours.fichiers_joints')) ?> <span class="discret">(<?= count($fichiers) ?>)</span></h2>

        <?php if ($fichiers === []): ?>
          <p class="discret"><?= e(t('cours.aucun_fichier')) ?></p>
        <?php else: ?>
          <ul class="liste-fichiers">
            <?php foreach ($fichiers as $f): ?>
              <li class="fichier">
                <span class="fichier__icone" aria-hidden="true"><?= Fichiers::icone($f['mime'], $f['nom_origine']) ?></span>
                <span style="min-width:0">
                  <?php // Un document Word ou PowerPoint s'ouvre en aperçu texte,
                        // le navigateur ne sachant pas l'afficher lui-même. ?>
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
                  <?php // Le fichier d'origine, ou le PDF pour un document. ?>
                  <?= Vue::rendre('cours/_telecharger', ['fichier' => $f, 'compact' => true]) ?>
                  <a class="bouton bouton--discret bouton--petit bouton-partage" href="<?= url('partager/fichiers/' . $f['id']) ?>"
                     <?= $dansUneFenetre ? 'data-fenetre-dessus' : 'data-fenetre' ?> title="<?= e(t('cours.partager_fichier')) ?>" aria-label="<?= e(t('cours.partager_nom', ['nom' => $f['nom_origine']])) ?>"><?= Partages::icone(15) ?></a>
                  <form method="post" action="<?= url('fichiers/' . $f['id'] . '/supprimer') ?>" class="en-ligne"<?= $surPlace ?>
                        data-confirmation="Supprimer définitivement ce fichier ?">
                    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                    <button class="bouton bouton--discret bouton--petit" type="submit" title="<?= e(t('commun.supprimer')) ?>">✕</button>
                  </form>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <?php // Déposer des fichiers ici, sans passer par « Modifier ». ?>
        <form method="post" action="<?= url('cours/' . $cours['id'] . '/fichiers') ?>"
              enctype="multipart/form-data" class="depot" data-depot<?= $surPlace ?>>
          <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

          <label class="depot__zone" for="depot-<?= (int) $cours['id'] ?>">
            <span class="depot__icone" aria-hidden="true">📎</span>
            <span>
              <strong><?= e(t('cours.deposer_ici')) ?></strong><br>
              <span class="discret"><?= e(t('cours.deposer_aide', ['taille' => taille_lisible(Fichiers::tailleMax())])) ?></span>
            </span>
          </label>

          <input type="file" id="depot-<?= (int) $cours['id'] ?>" name="fichiers[]" multiple
                 class="depot__champ" data-depot-champ>
          <button class="bouton bouton--petit bouton--bloc" type="submit" data-depot-envoi><?= e(t('cours.joindre')) ?></button>
        </form>
      </section>

      <?php if ($tags !== []): ?>
        <section class="carte">
          <h2><?= e(t('cours.tags')) ?></h2>
          <div style="display:flex;gap:.4rem;flex-wrap:wrap">
            <?php foreach ($tags as $t): ?>
              <a class="pastille" href="<?= url('cours', ['tag' => $t['id']]) ?>">#<?= e($t['nom']) ?></a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($evenements !== []): ?>
        <section class="carte">
          <h2><?= e(t('cours.au_calendrier')) ?></h2>
          <div class="pile">
            <?php foreach ($evenements as $evt): ?>
              <a class="evt-ligne" href="<?= url('evenements/' . $evt['id'] . '/modifier') ?>">
                <span>
                  <span class="evt-ligne__titre"><?= e(icone_evenement($evt) . ' ' . $evt['titre']) ?></span><br>
                  <span class="evt-ligne__meta"><?= e(date_fr($evt['debut'])) ?></span>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($images !== []): ?>
        <section class="carte">
          <h2><?= e(t('cours.apercu_images')) ?></h2>
          <div class="grille" style="grid-template-columns:repeat(auto-fill,minmax(110px,1fr))">
            <?php foreach ($images as $f): ?>
              <a href="<?= url('fichiers/' . $f['id']) ?>" target="_blank" rel="noopener">
                <img src="<?= url('fichiers/' . $f['id']) ?>" alt="<?= e($f['nom_origine']) ?>"
                     loading="lazy" style="width:100%;height:100px;object-fit:cover;border-radius:8px">
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <form method="post" action="<?= url('cours/' . $cours['id'] . '/supprimer') ?>"
            data-confirmation="<?= e(t('cours.supprimer_confirmation')) ?>">
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
        <button class="bouton bouton--danger bouton--bloc" type="submit"><?= e(t('cours.supprimer')) ?></button>
      </form>
    </div>
  </div>

  <?php if ($revision): ?>
    <?= Vue::rendre('cours/_fiche', [
        'cours' => $cours, 'fiche' => $fiche, 'fichiersFiche' => $fichiersFiche,
        'parType' => $parType, 'autresCours' => $autresCours,
        'evenementsChoix' => $evenementsChoix, 'cartes' => $cartes, 'surPage' => false,
    ]) ?>
  <?php endif; ?>

</div>
