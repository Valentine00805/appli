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
    <?php // Le texte lu : si un ami le modifie entre-temps (fiche partagée en modification), le nôtre ne l'écrase pas. ?>
    <input type="hidden" name="base" value="<?= e(Partages::empreinte($fiche)) ?>">

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
  <?= Vue::rendre('partages/_brouillon', ['brouillon' => Partages::brouillon('fiche', (int) $cours['id'])]) ?>

    </div>

    <div class="fiche-grille__elements<?= $nbElements === 0 ? ' fiche-grille__elements--vide' : '' ?>">

  <h3 class="volet__section" style="margin-top:0">
    <?= e(t('fiche.elements')) ?>
    <?php if ($nbElements > 0): ?><span class="discret">(<?= $nbElements ?>)</span><?php endif; ?>
  </h3>
  <p class="champ__aide" style="margin:-.35rem 0 .9rem">
    <?= e(t('fiche.elements_aide')) ?>
  </p>

  <?php // --- Diaporamas commentés ------------------------------------- ?>
  <?php
  /*
   * Les diaporamas rangés dans cette fiche (depuis leur page, bouton « Ajouter à la fiche de révision ») : ils s'ouvrent
   * dans une fenêtre, avec leur lecture commentée. Retirer d'ici n'efface pas le diaporama.
   */
  $diaporamasFiche = $diaporamasFiche ?? Diaporama::duCours((int) $cours['id'], Auth::id());
  ?>
  <div class="fiche__rayon<?= $diaporamasFiche === [] ? ' fiche__rayon--vide' : '' ?>" data-diaporamas-fiche>
    <h4 class="fiche__titre"><?= e(t('fiche.diaporamas')) ?></h4>

    <?php if ($diaporamasFiche === []): ?>
      <p class="discret fiche__vide"><?= e(t('dia.fiche_aucun')) ?></p>
    <?php else: ?>
      <?php
      /*
       * Un aperçu par diaporama : sa première diapositive en miniature (le script permet d'en feuilleter les autres sur
       * place), et un clic l'ouvre dans une fenêtre, à la diapositive montrée, avec sa lecture commentée. Sans script,
       * c'est la première diapositive, et le lien l'ouvre.
       */
      $ouverture = $dansUneFenetre ? 'data-fenetre-dessus' : 'data-fenetre';
      ?>
      <?php foreach ($diaporamasFiche as $dia): ?>
        <?php $premiere = $dia['apercu'][0] ?? ['t' => (string) $dia['titre'], 'p' => []]; ?>
        <div class="diapo-apercu" data-dia-apercu data-url="<?= url('diaporamas/' . $dia['id']) ?>"
             data-diapos="<?= e((string) json_encode($dia['apercu'], JSON_UNESCAPED_UNICODE)) ?>">
          <a class="diapo-apercu__scene" href="<?= url('diaporamas/' . $dia['id']) ?>" <?= $ouverture ?> data-dia-apercu-lien
             title="<?= e(t('dia.lire')) ?>">
            <span class="diapo-apercu__compteur" data-dia-apercu-compteur><?= e(t('js.dia.diapo', ['i' => 1, 'n' => $dia['nombre']])) ?></span>
            <strong class="diapo-apercu__titre" data-dia-apercu-titre><?= e($premiere['t']) ?></strong>
            <ul class="diapo-apercu__points" data-dia-apercu-points>
              <?php foreach (array_slice($premiere['p'], 0, 4) as $point): ?><li><?= e($point) ?></li><?php endforeach; ?>
            </ul>
            <span class="diapo-apercu__jouer">▶ <?= e(t('dia.lire')) ?></span>
          </a>
          <div class="diapo-apercu__pied">
            <span class="diapo-apercu__nav" data-dia-apercu-nav hidden>
              <button type="button" class="bouton bouton--discret bouton--petit" data-dia-apercu-action="precedent" title="<?= e(t('dia.precedent')) ?>">◀</button>
              <span class="discret" data-dia-apercu-position>1 / <?= (int) $dia['nombre'] ?></span>
              <button type="button" class="bouton bouton--discret bouton--petit" data-dia-apercu-action="suivant" title="<?= e(t('dia.suivant')) ?>">▶</button>
            </span>
            <a href="<?= url('diaporamas/' . $dia['id']) ?>" <?= $ouverture ?>><?= e($dia['titre']) ?></a>
            <span class="discret"><?= e(tn('dia.nb_diapos', $dia['nombre'])) ?></span>
            <form<?= $surPlace ?> method="post" action="<?= url('diaporamas/' . $dia['id'] . '/fiche/retirer') ?>" class="en-ligne">
              <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
              <input type="hidden" name="cours" value="<?= (int) $cours['id'] ?>">
              <input type="hidden" name="retour" value="<?= $surPage ? 'fiche' : 'volet' ?>">
              <button class="bouton bouton--discret bouton--petit" type="submit" title="<?= e(t('dia.fiche_retirer')) ?>">✕</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?= Vue::rendre('cours/_fiche_fichiers', [
      'fichiersFiche' => $fichiersFiche, 'actionDepot' => url('cours/' . $cours['id'] . '/revision/fichiers'),
      'idDepot' => (string) (int) $cours['id'], 'surPlace' => $surPlace, 'champPage' => $champPage, 'dansUneFenetre' => $dansUneFenetre,
  ]) ?>

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

  <?php // --- Cartes mentales ------------------------------------------ ?>
  <?php
  /*
   * Les cartes mentales du cours : on les retrouve ici et on les ouvre dans leur éditeur (une page à elles).
   * Elles se créent dans « Résumés IA », écrites par l'IA ou vierges.
   */
  $cartesMentales = $cartesMentales ?? CarteMentale::duCours((int) $cours['id'], Auth::id());
  ?>
  <div class="fiche__rayon<?= $cartesMentales === [] ? ' fiche__rayon--vide' : '' ?>" data-cartes-mentales>
    <h4 class="fiche__titre"><?= e(t('fiche.cartes_mentales')) ?></h4>

    <?php if ($cartesMentales === []): ?>
      <p class="discret fiche__vide"><?= e(t('cm.aucune')) ?></p>
    <?php else: ?>
      <ul class="fiche__cartes-mentales">
        <?php foreach ($cartesMentales as $cm): ?>
          <li>
            <a href="<?= url('cartes-mentales/' . $cm['id']) ?>" <?= $dansUneFenetre ? 'data-fenetre-dessus' : 'data-fenetre' ?>><?= e($cm['titre']) ?></a>
            <span class="discret">
              <?= e(tn('cm.idees', $cm['idees'])) ?><?php if ($cm['ia'] === 1): ?> · <?= e(t('cm.ia_badge')) ?><?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php // Elles se créent dans « Résumés IA » (une case « Carte mentale », ou une carte vierge) ; ici, on les retrouve. ?>
    <?php
    /*
     * Tout ce que l'IA écrit à partir du cours — résumé, points clés, flash cards, carte mentale, diaporama — se demande
     * depuis « Résumés IA », où ce cours est déjà coché : un seul bouton y mène.
     */
    ?>
    <p class="actions">
      <a class="bouton bouton--secondaire bouton--petit" data-resumer-ia
         href="<?= url('resumes', ['cours' => $cours['id']]) ?>"><?= e(t('fiche.resumer_ia')) ?></a>
    </p>
  </div>

  <?php // --- Quizz : des pages web (.html) jouées sur place ---------------- ?>
  <?= Vue::rendre('cours/_fiche_quizz', [
      'fichiersFiche' => $fichiersFiche, 'actionDepot' => url('cours/' . $cours['id'] . '/revision/fichiers'),
      'idDepot' => (string) (int) $cours['id'], 'surPlace' => $surPlace, 'champPage' => $champPage, 'dansUneFenetre' => $dansUneFenetre,
  ]) ?>

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
          <input type="url" id="lien-url" name="url" required placeholder="<?= e(t('fiche.url_exemple')) ?>">
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
            <a href="<?= url('evenements/' . (int) $renvoi['cible_evenement_id']) ?>" data-fenetre>
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
