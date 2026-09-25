<?php
/** @var array $listes, $taches, $listesFiltrees, $compteurs, $palette, $icones @var ?int $listeOuverte @var string $vue */
$csrf = Session::jetonCsrf();

$onglets = [
    'tout'       => [t('taches.onglet_listes'),      $compteurs['a_faire']],
    'retard'     => [t('taches.onglet_retard'),      $compteurs['retard']],
    'aujourdhui' => [t('taches.onglet_aujourdhui'),  $compteurs['aujourdhui']],
    'semaine'    => [t('taches.onglet_semaine'),     $compteurs['semaine']],
    'terminees'  => [t('taches.onglet_terminees'),   $compteurs['terminees']],
];

/** La liste actuellement ouverte dans le volet. */
$ouverte = null;
foreach ($listes as $l) {
    if ((int) $l['id'] === (int) $listeOuverte) {
        $ouverte = $l;
        break;
    }
}

$aFaire    = array_filter($taches, static fn (array $t): bool => (int) $t['faite'] === 0);
$terminees = array_filter($taches, static fn (array $t): bool => (int) $t['faite'] === 1);

/*
 * La date à ne pas dépasser, tâche principale par tâche principale.
 *
 * Une sous-tâche ne peut pas être due après la sienne : le serveur le refuse,
 * et le champ de date le dit avant qu'on essaie. Le calendrier du navigateur
 * grise alors les jours d'après — c'est plus clair qu'un message d'erreur, et
 * ça arrive plus tôt.
 */
$plafonds = [];
foreach ($listes as $l) {
    $plafonds[(int) $l['id']] = (string) ($l['echeance'] ?? '');
}
$plafond = static function (mixed $listeId) use ($plafonds): string {
    return $plafonds[(int) $listeId] ?? '';
};

/** Le contexte à conserver quand un formulaire renvoie sur cette page. */
$contexte = static function () use ($vue, $listeOuverte, $csrf): string {
    $html  = '<input type="hidden" name="_csrf" value="' . e($csrf) . '">';
    $html .= '<input type="hidden" name="vue" value="' . e($vue) . '">';
    if ($listeOuverte !== null) {
        $html .= '<input type="hidden" name="liste" value="' . (int) $listeOuverte . '">';
    }
    return $html;
};

/** Une ligne de tâche : la case, le libellé, l'échéance, les actions. */
$ligneTache = static function (array $t) use ($csrf, $contexte, $listes, $vue, $plafond): string {
    $faite = (int) $t['faite'] === 1;
    $etat  = echeance_etat($t['echeance'], $faite);
    $texte = echeance_libelle($t['echeance'], $faite);
    ob_start(); ?>
    <li class="tache<?= $faite ? ' tache--faite' : '' ?>" data-tache-id="<?= (int) $t['id'] ?>">
      <form method="post" action="<?= url('taches/' . $t['id'] . '/cocher') ?>" class="tache__bascule">
        <?= $contexte() ?>
        <input type="checkbox" class="tache__case" id="case-<?= (int) $t['id'] ?>"
               data-envoi-immediat<?= $faite ? ' checked' : '' ?>
               title="<?= e($faite ? t('taches.decocher') : t('taches.cocher')) ?>">
        <noscript><button class="bouton bouton--petit" type="submit">OK</button></noscript>
      </form>

      <label class="tache__titre" for="case-<?= (int) $t['id'] ?>">
        <?= e($t['titre']) ?>
        <?php if (isset($t['liste_nom'])): ?>
          <span class="tache__liste"><?= e($t['liste_icone'] . ' ' . $t['liste_nom']) ?></span>
        <?php endif; ?>
      </label>

      <?php if ($texte !== ''): ?>
        <span class="echeance echeance--<?= e($etat) ?>"><?= e($texte) ?></span>
      <?php endif; ?>
      <?php if ((string) ($t['recurrence'] ?? '') !== ''): ?>
        <?php // Elle reviendra : la suivante se pose quand on coche celle-ci. ?>
        <span class="pastille" title="<?= e(t('taches.recurrence.' . $t['recurrence'])) ?>">🔁</span>
      <?php endif; ?>

      <span class="tache__actions">
        <button class="bouton bouton--discret bouton--petit" type="button"
                data-bascule="tache-<?= (int) $t['id'] ?>"><?= e(t('evt.modifier')) ?></button>
      </span>
    </li>

    <li id="tache-<?= (int) $t['id'] ?>" hidden class="tache-edition">
      <form method="post" action="<?= url('taches/' . $t['id'] . '/modifier') ?>" class="tache-edition__forme">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="champ">
          <label for="titre-<?= (int) $t['id'] ?>"><?= e(t('taches.tache')) ?></label>
          <input type="text" id="titre-<?= (int) $t['id'] ?>" name="titre" required maxlength="200"
                 value="<?= e($t['titre']) ?>">
        </div>
        <div class="ligne-champs">
          <div class="champ">
            <label for="ech-<?= (int) $t['id'] ?>"><?= e(t('taches.echeance')) ?></label>
            <input type="date" id="ech-<?= (int) $t['id'] ?>" name="echeance"
                   value="<?= e((string) $t['echeance']) ?>"
                   data-plafond-de="lst-<?= (int) $t['id'] ?>"
                   <?php $max = $plafond($t['liste_id']); ?>
                   <?= $max === '' ? '' : 'max="' . e($max) . '"' ?>>
          </div>
          <div class="champ">
            <label for="lst-<?= (int) $t['id'] ?>"><?= e(t('taches.deplacer')) ?></label>
            <select id="lst-<?= (int) $t['id'] ?>" name="liste_id">
              <?php foreach ($listes as $l): ?>
                <option value="<?= (int) $l['id'] ?>"<?= (int) $l['id'] === (int) $t['liste_id'] ? ' selected' : '' ?>
                        data-echeance="<?= e((string) ($l['echeance'] ?? '')) ?>">
                  <?= e($l['icone'] . ' ' . $l['nom']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="champ__aide">
              <?= e(count($listes) > 1 ? t('taches.deplacer_aide') : t('taches.deplacer_aide_seule')) ?>
            </span>
          </div>
          <div class="champ">
            <label for="rep-<?= (int) $t['id'] ?>"><?= e(t('taches.se_repete')) ?></label>
            <select id="rep-<?= (int) $t['id'] ?>" name="recurrence">
              <option value=""><?= e(t('taches.une_fois')) ?></option>
              <?php foreach (TachesController::RECURRENCES as $cle => $r): ?>
                <option value="<?= e($cle) ?>"<?= (string) ($t['recurrence'] ?? '') === $cle ? ' selected' : '' ?>><?= e(t('taches.recurrence.' . $cle)) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="champ__aide"><?= e(t('taches.recurrence_aide')) ?></span>
          </div>
        </div>
        <div class="actions">
          <button class="bouton bouton--petit" type="submit"><?= e(t('commun.enregistrer')) ?></button>
        </div>
      </form>

      <form method="post" action="<?= url('taches/' . $t['id'] . '/supprimer') ?>"
            data-confirmation="<?= e(t('taches.supprimer_sur')) ?>">
        <?= $contexte() ?>
        <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('commun.supprimer')) ?></button>
      </form>
    </li>
    <?php
    return (string) ob_get_clean();
};
?>

<div class="entete-page">
  <div>
    <h1><?= e(t('taches.titre')) ?></h1>
    <p><?= e(t('taches.sous_titre')) ?></p>
  </div>
</div>

<div class="barre-taches">
  <?php if ($listes !== []): ?>
    <nav class="onglets" aria-label="<?= e(t('taches.filtrer')) ?>">
      <?php foreach ($onglets as $cle => [$libelle, $nombre]): ?>
        <a href="<?= $cle === 'tout' ? url('taches') : url('taches', ['vue' => $cle]) ?>"
           <?= $vue === $cle ? ' aria-current="page"' : '' ?>>
          <?= e($libelle) ?>
          <?php if ($nombre > 0): ?>
            <span class="onglets__compteur<?= $cle === 'retard' ? ' onglets__compteur--alerte' : '' ?>"><?= $nombre ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
  <?php else: ?>
    <span></span>
  <?php endif; ?>

  <div class="barre-taches__actions">
  <?php // Le formulaire s'ouvre en panneau, sous le bouton. ?>
  <details class="nouvelle-liste"<?= $listes === [] ? ' open' : '' ?>>
    <summary class="bouton bouton--petit"><?= e(t('taches.bouton_nouvelle_liste')) ?></summary>
    <div class="nouvelle-liste__panneau carte">
      <?php // La croix referme le panneau, comme celle d'une fenêtre. ?>
      <button class="panneau-fermer" type="button" data-fermer-panneau title="<?= e(t('commun.fermer')) ?>" aria-label="<?= e(t('taches.fermer_nouvelle_liste')) ?>">✕</button>
      <h2 style="margin-top:0"><?= e(t('taches.nouvelle_liste')) ?></h2>
      <form method="post" action="<?= url('taches/listes') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

        <div class="champ">
          <label for="nom"><?= e(t('taches.nom')) ?></label>
          <input type="text" id="nom" name="nom" required maxlength="120" placeholder="<?= e(t('taches.nom_exemple')) ?>">
        </div>

        <div class="champ">
          <label for="ech-liste"><?= e(t('taches.echeance')) ?> <span class="discret"><?= e(t('taches.echeance_facultative')) ?></span></label>
          <input type="date" id="ech-liste" name="echeance">
          <span class="champ__aide"><?= e(t('taches.liste_echeance_aide')) ?></span>
        </div>

        <div class="champ">
          <span class="legende"><?= e(t('taches.icone')) ?></span>
          <div class="choix-icones">
            <?php foreach ($icones as $i => $icone): ?>
              <input type="radio" id="ni-<?= $i ?>" name="icone" value="<?= e($icone) ?>"<?= $i === 0 ? ' checked' : '' ?>>
              <label for="ni-<?= $i ?>"><?= e($icone) ?></label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="champ">
          <span class="legende"><?= e(t('taches.couleur')) ?></span>
          <div class="choix-couleurs">
            <?php foreach ($palette as $i => $couleur): ?>
              <input type="radio" id="nc-<?= $i ?>" name="couleur" value="<?= e($couleur) ?>"<?= $i === 0 ? ' checked' : '' ?>>
              <label for="nc-<?= $i ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
            <?php endforeach; ?>
          </div>
        </div>

        <button class="bouton bouton--bloc" type="submit"><?= e(t('taches.creer_liste')) ?></button>
      </form>
    </div>
  </details>


  <?php // Une sous-tâche, rangée dans la tâche principale de votre choix. ?>
  <?php if ($listes !== []): ?>
    <details class="nouvelle-liste nouvelle-tache">
      <summary class="bouton bouton--petit bouton--secondaire"><?= e(t('taches.bouton_nouvelle_sous_tache')) ?></summary>
      <div class="nouvelle-liste__panneau carte">
        <button class="panneau-fermer" type="button" data-fermer-panneau title="<?= e(t('commun.fermer')) ?>" aria-label="<?= e(t('taches.fermer_nouvelle_sous_tache')) ?>">✕</button>
        <h2 style="margin-top:0"><?= e(t('taches.nouvelle_sous_tache')) ?></h2>
        <form method="post" action="<?= url('taches') ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

          <div class="champ">
            <label for="st-liste"><?= e(t('taches.tache_principale')) ?></label>
            <select id="st-liste" name="liste_id" required>
              <?php foreach ($listes as $l): ?>
                <option value="<?= (int) $l['id'] ?>"
                        <?= (int) $l['id'] === (int) $listeOuverte ? ' selected' : '' ?>
                        data-echeance="<?= e((string) ($l['echeance'] ?? '')) ?>">
                  <?= e($l['icone'] . ' ' . $l['nom']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="champ__aide"><?= e(t('taches.liste_aide')) ?></span>
          </div>

          <div class="champ">
            <label for="st-titre"><?= e(t('taches.tache')) ?></label>
            <input type="text" id="st-titre" name="titre" required maxlength="200"
                   placeholder="<?= e(t('taches.tache_exemple')) ?>">
          </div>

          <?php
          // Le plafond de la tâche principale déjà sélectionnée : sans choix
          // explicite, le navigateur retient la première de la liste.
          $choisie = isset($plafonds[(int) $listeOuverte])
              ? (int) $listeOuverte
              : (int) $listes[array_key_first($listes)]['id'];
          $maxNouvelle = $plafond($choisie);
          ?>
          <div class="champ">
            <label for="st-echeance"><?= e(t('taches.echeance')) ?> <span class="discret"><?= e(t('taches.echeance_facultative')) ?></span></label>
            <input type="date" id="st-echeance" name="echeance" data-plafond-de="st-liste"
                   <?= $maxNouvelle === '' ? '' : 'max="' . e($maxNouvelle) . '"' ?>>
            <span class="champ__aide"><?= e(t('taches.sous_tache_echeance_aide')) ?></span>
          </div>
          <div class="champ">
            <label for="st-recurrence"><?= e(t('taches.se_repete')) ?> <span class="discret"><?= e(t('taches.echeance_facultative')) ?></span></label>
            <select id="st-recurrence" name="recurrence">
              <option value=""><?= e(t('taches.une_fois')) ?></option>
              <?php foreach (TachesController::RECURRENCES as $cle => $r): ?>
                <option value="<?= e($cle) ?>"><?= e(t('taches.recurrence.' . $cle)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <button class="bouton bouton--bloc" type="submit"><?= e(t('taches.ajouter_sous_tache')) ?></button>
        </form>
      </div>
    </details>
  <?php endif; ?>

  </div>
</div>

<div class="taches-vue">

  <!-- Colonne de gauche : rien que les tâches principales. -->
  <div class="pile" data-listes-triables>
    <?php if ($listes === []): ?>
      <div class="vide">
        <span class="vide__icone">📋</span>
        <p>
          <?= e(t('taches.aucune_liste')) ?>
        </p>
      </div>
    <?php else: ?>
      <?php $derniere = count($listes) - 1; ?>
      <?php foreach ($listes as $rang => $liste): ?>
        <?php
        $total     = (int) $liste['reste'] + (int) $liste['finies'];
        $terminee  = $total > 0 && (int) $liste['reste'] === 0;
        $partielle = (int) $liste['finies'] > 0 && (int) $liste['reste'] > 0;
        $active    = (int) $liste['id'] === (int) $listeOuverte && $vue === 'tout';
        ?>
        <div class="liste-carte<?= $active ? ' liste-carte--active' : '' ?><?= $terminee ? ' liste-carte--faite' : '' ?>"
             data-liste-id="<?= (int) $liste['id'] ?>"
             style="border-left-color:<?= e($liste['couleur']) ?>">
          <form method="post" action="<?= url('taches/listes/' . $liste['id'] . '/cocher') ?>"
                class="tache__bascule">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="vue" value="<?= e($vue) ?>">
            <input type="hidden" name="liste" value="<?= (int) $liste['id'] ?>">
            <input type="checkbox" class="tache__case tache__case--liste"
                   data-envoi-immediat<?= $terminee ? ' checked' : '' ?><?= $partielle ? ' data-partiel' : '' ?>
                   <?= $total === 0 ? ' disabled' : '' ?>
                   aria-label="<?= e(t($terminee ? 'taches.rouvrir_liste' : 'taches.terminer_liste', ['nom' => $liste['nom']])) ?>"
                   title="<?= e($total === 0
                       ? t('taches.liste_vide')
                       : t($terminee ? 'taches.rouvrir_tout' : 'taches.terminer_tout')) ?>">
            <noscript><button class="bouton bouton--petit" type="submit">OK</button></noscript>
          </form>

          <?php // Un clic ouvre la liste, un second la referme. ?>
          <?php // draggable=false : sans quoi le navigateur glisserait l'URL du lien. ?>
          <a class="liste-carte__lien" draggable="false"
             href="<?= $active ? url('taches') : url('taches', ['liste' => $liste['id']]) . '#volet' ?>"
             aria-label="<?= e(t($active ? 'taches.fermer_liste' : 'taches.ouvrir_liste', ['nom' => $liste['nom']])) ?>"
             aria-expanded="<?= $active ? 'true' : 'false' ?>"
             <?= $active ? ' aria-current="true"' : '' ?>>
            <span class="liste-carte__icone" aria-hidden="true"><?= e($liste['icone']) ?></span>
            <span style="flex:1;min-width:0">
              <span class="liste-carte__nom"><?= e($liste['nom']) ?></span>
              <span class="liste-carte__meta">
                <?php if ($total === 0): ?>
                  <?= e(t('taches.vide')) ?>
                <?php elseif ($terminee): ?>
                  <?= e(t('taches.tout_fait')) ?>
                <?php else: ?>
                  <?= e(t('taches.a_faire_n', ['n' => (int) $liste['reste']])) ?>
                  <?php if ((int) $liste['en_retard'] > 0): ?>
                    · <strong class="alerte"><?= e(t('taches.en_retard_n', ['n' => (int) $liste['en_retard']])) ?></strong>
                  <?php elseif ($liste['prochaine'] !== null): ?>
                    · <?= e(echeance_libelle((string) $liste['prochaine'])) ?>
                  <?php endif; ?>
                <?php endif; ?>
              </span>
            </span>

            <?php // L'échéance de la tâche principale, distincte de celles de ses sous-tâches. ?>
            <?php if ($liste['echeance'] !== null): ?>
              <span class="echeance echeance--<?= e(echeance_etat($liste['echeance'], $terminee)) ?>">
                <?= e(echeance_libelle($liste['echeance'], $terminee)) ?>
              </span>
            <?php endif; ?>

            <span class="liste-carte__chevron" aria-hidden="true">›</span>
          </a>

          <?php // Ordre choisi : deux crans, sans glisser-déposer. ?>
          <?php if (count($listes) > 1): ?>
            <span class="liste-carte__ordre">
              <form method="post" action="<?= url('taches/listes/' . $liste['id'] . '/deplacer') ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="vue" value="<?= e($vue) ?>">
                <?php if ($listeOuverte !== null): ?>
                  <input type="hidden" name="liste" value="<?= (int) $listeOuverte ?>">
                <?php endif; ?>
                <input type="hidden" name="sens" value="haut">
                <button type="submit" title="<?= e(t('taches.monter_liste', ['nom' => $liste['nom']])) ?>"
                        aria-label="<?= e(t('taches.monter_liste_aide', ['nom' => $liste['nom']])) ?>"
                        <?= $rang === 0 ? ' disabled' : '' ?>>↑</button>
              </form>
              <form method="post" action="<?= url('taches/listes/' . $liste['id'] . '/deplacer') ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="vue" value="<?= e($vue) ?>">
                <?php if ($listeOuverte !== null): ?>
                  <input type="hidden" name="liste" value="<?= (int) $listeOuverte ?>">
                <?php endif; ?>
                <input type="hidden" name="sens" value="bas">
                <button type="submit" title="<?= e(t('taches.descendre_liste', ['nom' => $liste['nom']])) ?>"
                        aria-label="<?= e(t('taches.descendre_liste_aide', ['nom' => $liste['nom']])) ?>"
                        <?= $rang === $derniere ? ' disabled' : '' ?>>↓</button>
              </form>
            </span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php // Le glisser-déposer poste ici le classement complet. ?>
    <form method="post" action="<?= url('taches/listes/ordre') ?>" id="forme-ordre" hidden>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="vue" value="<?= e($vue) ?>">
      <?php if ($listeOuverte !== null): ?>
        <input type="hidden" name="liste" value="<?= (int) $listeOuverte ?>">
      <?php endif; ?>
      <input type="hidden" name="ordre" value="">
    </form>

    <?php // Une sous-tâche déposée sur une carte de gauche passe par ici. ?>
    <form method="post" action="<?= url('taches/ranger') ?>" id="forme-ranger" hidden>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="vue" value="<?= e($vue) ?>">
      <?php if ($listeOuverte !== null): ?>
        <input type="hidden" name="liste" value="<?= (int) $listeOuverte ?>">
      <?php endif; ?>
      <input type="hidden" name="tache" value="">
      <input type="hidden" name="cible" value="">
    </form>

    <?php // Le classement des sous-tâches d'une liste. ?>
    <?php if ($listeOuverte !== null): ?>
      <form method="post" action="<?= url('taches/ordre') ?>" id="forme-ordre-taches" hidden>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="vue" value="<?= e($vue) ?>">
        <input type="hidden" name="liste" value="<?= (int) $listeOuverte ?>">
        <input type="hidden" name="cible" value="<?= (int) $listeOuverte ?>">
        <input type="hidden" name="ordre" value="">
      </form>
    <?php endif; ?>

  </div>

  <!-- Volet de droite : la tâche principale ouverte, et ses sous-tâches. -->
  <div class="volet" id="volet">
    <?php if ($vue !== 'tout'): ?>
      <?php $titres = ['retard' => '⏰ ' . t('taches.onglet_retard'), 'aujourdhui' => '📅 ' . t('taches.onglet_aujourdhui'),
                       'semaine' => '🗓️ ' . t('taches.onglet_semaine'), 'terminees' => '✓ ' . t('taches.onglet_terminees')]; ?>
      <section class="carte">
        <div class="volet__entete">
          <div style="flex:1;min-width:0">
            <h2 style="margin:0"><?= e($titres[$vue] ?? '') ?></h2>
            <p class="discret" style="margin:.15rem 0 0;font-size:.84rem">
              <?php $nbTotal = count($taches) + count($listesFiltrees); ?>
              <?= e(tn('taches.elements', $nbTotal)) ?>
            </p>
          </div>
          <a class="bouton bouton--discret bouton--petit" href="<?= url('taches') ?>"><?= e(t('taches.mes_listes')) ?></a>
        </div>

        <?php if ($listesFiltrees !== []): ?>
          <h3 class="volet__section"><?= e(t('taches.taches_principales')) ?></h3>
          <div class="pile">
            <?php foreach ($listesFiltrees as $l): ?>
              <?php $lTerminee = ((int) $l['reste'] + (int) $l['finies']) > 0 && (int) $l['reste'] === 0; ?>
              <div class="liste-carte" style="border-left-color:<?= e($l['couleur']) ?>">
                <form method="post" action="<?= url('taches/listes/' . $l['id'] . '/cocher') ?>"
                      class="tache__bascule">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="vue" value="<?= e($vue) ?>">
                  <input type="checkbox" class="tache__case tache__case--liste"
                         data-envoi-immediat<?= $lTerminee ? ' checked' : '' ?>
                         <?= ((int) $l['reste'] + (int) $l['finies']) === 0 ? ' disabled' : '' ?>
                         aria-label="<?= e(t('taches.terminer_liste', ['nom' => $l['nom']])) ?>">
                  <noscript><button class="bouton bouton--petit" type="submit">OK</button></noscript>
                </form>
                <a class="liste-carte__lien" href="<?= url('taches', ['liste' => $l['id']]) ?>"
                   aria-label="<?= e(t('taches.ouvrir_liste', ['nom' => $l['nom']])) ?>">
                  <span class="liste-carte__icone" aria-hidden="true"><?= e($l['icone']) ?></span>
                  <span style="flex:1;min-width:0">
                    <span class="liste-carte__nom"><?= e($l['nom']) ?></span>
                    <span class="liste-carte__meta"><?= e(tn('taches.sous_taches_restantes', (int) $l['reste'])) ?></span>
                  </span>
                  <span class="echeance echeance--<?= e(echeance_etat($l['echeance'])) ?>">
                    <?= e(echeance_libelle($l['echeance'])) ?>
                  </span>
                  <span class="liste-carte__chevron" aria-hidden="true">›</span>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($taches === [] && $listesFiltrees === []): ?>
          <p class="discret" style="margin:1rem 0 0"><?= e(t('taches.rien_ici')) ?></p>
        <?php elseif ($taches !== []): ?>
          <?php if ($listesFiltrees !== []): ?>
            <h3 class="volet__section"><?= e(t('taches.sous_taches')) ?></h3>
          <?php endif; ?>
          <ul class="taches">
            <?php foreach ($taches as $t): ?><?= $ligneTache($t) ?><?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

    <?php elseif ($ouverte === null): ?>
      <div class="vide">
        <span class="vide__icone">👈</span>
        <p>
          <?= e($listes === [] ? t('taches.premiere_liste') : t('taches.cliquez_liste')) ?>
        </p>
      </div>

    <?php else: ?>
      <?php
      $total     = (int) $ouverte['reste'] + (int) $ouverte['finies'];
      $terminee  = $total > 0 && (int) $ouverte['reste'] === 0;
      $partielle = (int) $ouverte['finies'] > 0 && (int) $ouverte['reste'] > 0;
      ?>
      <section class="carte volet__carte<?= $terminee ? ' liste-taches--faite' : '' ?>"
               style="border-top:4px solid <?= e($ouverte['couleur']) ?>">

        <div class="volet__entete">
          <form method="post" action="<?= url('taches/listes/' . $ouverte['id'] . '/cocher') ?>"
                class="tache__bascule">
            <?= $contexte() ?>
            <input type="checkbox" class="tache__case tache__case--liste" id="volet-case"
                   data-envoi-immediat<?= $terminee ? ' checked' : '' ?><?= $partielle ? ' data-partiel' : '' ?>
                   <?= $total === 0 ? ' disabled' : '' ?>
                   title="<?= e($total === 0
                       ? t('taches.liste_vide')
                       : t($terminee ? 'taches.rouvrir_tout' : 'taches.terminer_tout')) ?>">
            <noscript><button class="bouton bouton--petit" type="submit">OK</button></noscript>
          </form>

          <span class="volet__icone" aria-hidden="true"><?= e($ouverte['icone']) ?></span>

          <div style="flex:1;min-width:0">
            <h2 style="margin:0">
              <label for="volet-case"><?= e($ouverte['nom']) ?></label>
              <?php if ($ouverte['echeance'] !== null): ?>
                <span class="echeance echeance--<?= e(echeance_etat($ouverte['echeance'], $terminee)) ?>"
                      style="vertical-align:middle;margin-left:.4rem">
                  <?= e(echeance_libelle($ouverte['echeance'], $terminee)) ?>
                </span>
              <?php endif; ?>
            </h2>
            <p class="discret" style="margin:.15rem 0 0;font-size:.84rem">
              <?php if ($total === 0): ?>
                <?= e(t('taches.aucune_sous_tache')) ?>
              <?php elseif ($terminee): ?>
                <?= e(t('taches.tout_fait_maj')) ?>
              <?php else: ?>
                <?= e(t('taches.a_faire_sur', ['n' => (int) $ouverte['reste'], 'total' => $total])) ?>
                <?php if ((int) $ouverte['en_retard'] > 0): ?>
                  · <strong class="alerte"><?= e(t('taches.en_retard_n', ['n' => (int) $ouverte['en_retard']])) ?></strong>
                <?php elseif ($ouverte['prochaine'] !== null): ?>
                  · <?= e(t('taches.prochaine')) ?> <?= e(echeance_libelle((string) $ouverte['prochaine'])) ?>
                <?php endif; ?>
              <?php endif; ?>
            </p>
          </div>

          <button class="bouton bouton--secondaire bouton--petit" type="button"
                  data-bascule="reglages-liste"><?= e(t('evt.modifier')) ?></button>
        </div>

        <div id="reglages-liste" hidden class="liste-taches__reglages">
          <hr class="separateur" style="margin:.75rem 0">
          <form method="post" action="<?= url('taches/listes/' . $ouverte['id'] . '/modifier') ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="ligne-champs">
              <div class="champ">
                <label for="ln"><?= e(t('taches.nom_liste')) ?></label>
                <input type="text" id="ln" name="nom" required maxlength="120" value="<?= e($ouverte['nom']) ?>">
              </div>
              <?php
              /*
               * La règle vue de l'autre côté : une tâche principale ne peut pas
               * être due avant ce qu'elle contient. On lit la sous-tâche la plus
               * tardive pour poser le plancher du champ.
               */
              $plancher = '';
              foreach ($taches as $t) {
                  if ($t['echeance'] !== null && (string) $t['echeance'] > $plancher) {
                      $plancher = (string) $t['echeance'];
                  }
              }
              ?>
              <div class="champ">
                <label for="le"><?= e(t('taches.echeance')) ?> <span class="discret"><?= e(t('taches.facultative')) ?></span></label>
                <input type="date" id="le" name="echeance" value="<?= e((string) $ouverte['echeance']) ?>"
                       <?= $plancher === '' ? '' : 'min="' . e($plancher) . '"' ?>>
                <?php if ($plancher !== ''): ?>
                  <span class="champ__aide">
                    <?= e(t('taches.pas_avant', ['date' => date_fr($plancher, false)])) ?>
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <div class="champ">
              <span class="legende"><?= e(t('taches.icone')) ?></span>
              <div class="choix-icones">
                <?php foreach ($icones as $i => $icone): ?>
                  <input type="radio" id="li-<?= $i ?>" name="icone" value="<?= e($icone) ?>"
                         <?= $ouverte['icone'] === $icone ? ' checked' : '' ?>>
                  <label for="li-<?= $i ?>"><?= e($icone) ?></label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="champ">
              <span class="legende"><?= e(t('taches.couleur')) ?></span>
              <div class="choix-couleurs">
                <?php foreach ($palette as $i => $couleur): ?>
                  <input type="radio" id="lc-<?= $i ?>" name="couleur" value="<?= e($couleur) ?>"
                         <?= strtolower((string) $ouverte['couleur']) === $couleur ? ' checked' : '' ?>>
                  <label for="lc-<?= $i ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="actions">
              <button class="bouton bouton--petit" type="submit"><?= e(t('commun.enregistrer')) ?></button>
            </div>
          </form>

          <div class="actions" style="margin-top:.75rem">
            <?php if ((int) $ouverte['finies'] > 0): ?>
              <form method="post" action="<?= url('taches/listes/' . $ouverte['id'] . '/vider') ?>"
                    data-confirmation="<?= e(t('taches.vider_sur')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button class="bouton bouton--discret bouton--petit" type="submit">
                  <?= e(tn('taches.retirer_terminees', (int) $ouverte['finies'])) ?>
                </button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= url('taches/listes/' . $ouverte['id'] . '/supprimer') ?>"
                  data-confirmation="<?= e(t('taches.supprimer_liste_sur')) ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('taches.supprimer_liste')) ?></button>
            </form>
          </div>
        </div>

        <?php // Seules les sous-tâches à faire se réordonnent : les autres sont classées. ?>
        <?php if ($aFaire !== []): ?>
          <ul class="taches" data-taches-triables>
            <?php foreach ($aFaire as $t): ?><?= $ligneTache($t) ?><?php endforeach; ?>
          </ul>
        <?php elseif ($terminees === []): ?>
          <p class="discret" style="margin:1rem 0 0">
            <?= e(t('taches.aucune_sous_tache_ajout')) ?>
          </p>
        <?php endif; ?>

        <?php if ($terminees !== []): ?>
          <details class="taches-terminees">
            <summary><?= e(tn('taches.terminees', count($terminees))) ?></summary>
            <ul class="taches">
              <?php foreach ($terminees as $t): ?><?= $ligneTache($t) ?><?php endforeach; ?>
            </ul>
          </details>
        <?php endif; ?>

        <form method="post" action="<?= url('taches') ?>" class="tache-ajout">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="liste_id" value="<?= (int) $ouverte['id'] ?>">
          <input type="text" name="titre" required maxlength="200" placeholder="<?= e(t('taches.ajouter_placeholder')) ?>"
                 aria-label="<?= e(t('taches.nouvelle_sous_tache_dans', ['nom' => $ouverte['nom']])) ?>">
          <input type="date" name="echeance" aria-label="<?= e(t('taches.echeance_facultative')) ?>"
                 <?= $ouverte['echeance'] === null ? '' : 'max="' . e((string) $ouverte['echeance'])
                     . '" title="' . e(t('taches.au_plus_tard', ['date' => date_fr((string) $ouverte['echeance'], false)])) . '"' ?>>
          <button class="bouton bouton--petit" type="submit"><?= e(t('taches.ajouter')) ?></button>
        </form>
      </section>
    <?php endif; ?>
  </div>
</div>
