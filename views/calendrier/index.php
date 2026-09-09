<?php
/**
 * @var string $vue
 * @var DateTimeImmutable $ancre, $debut, $fin
 * @var array $evenements, $parJour, $matieres, $types, $aVenir
 * @var ?int $matiereId, $typeId
 * @var array $sources  les agendas à montrer ou masquer, par fournisseur
 */
$aujourdhui = (new DateTimeImmutable('today'))->format('Y-m-d');

$filtresUrl = array_filter([
    'matiere' => $matiereId,
    'type'    => $typeId,
], static fn ($v): bool => $v !== null);

/** Lien de navigation en gardant les filtres. */
$lien = static function (string $vueCible, DateTimeInterface $date) use ($filtresUrl): string {
    return url('calendrier', $filtresUrl + ['vue' => $vueCible, 'date' => $date->format('Y-m-d')]);
};

// Les flèches avancent du pas de la vue : un jour, une semaine, un mois.
$pas = match ($vue) {
    'jour'    => '1 day',
    'semaine' => '7 days',
    default   => '1 month',
};
$precedent = $ancre->modify('-' . $pas);
$suivant   = $ancre->modify('+' . $pas);

if ($vue === 'jour') {
    $titre = ucfirst(date_fr($ancre->format('Y-m-d') . ' 00:00:00', false));
} elseif ($vue === 'semaine') {
    $titre = 'Semaine du ' . $debut->format('j') . ' au ' . $fin->format('j') . ' '
        . strtolower(nom_mois((int) $fin->format('n'))) . ' ' . $fin->format('Y');
} else {
    $titre = nom_mois((int) $ancre->format('n')) . ' ' . $ancre->format('Y');
}

/**
 * Où mène un élément du calendrier : un vrai évènement s'ouvre en
 * modification, une échéance de tâche ouvre sa liste.
 */
$destination = static function (array $evt): string {
    return empty($evt['est_tache'])
        ? url('evenements/' . $evt['id'] . '/modifier')
        : url('taches', ['liste' => $evt['liste_id']]);
};

/** Rend une puce d'évènement pour la grille mensuelle. */
$puce = static function (array $evt) use ($destination): string {
    $couleur = couleur_evenement($evt);
    $fond = 'color-mix(in srgb, ' . $couleur . ' 16%, transparent)';
    $heure = $evt['journee_entiere'] ? '' : '<span class="evt__heure">' . date('H:i', strtotime($evt['debut'])) . '</span> ';
    $classes = 'evt' . ($evt['termine'] ? ' evt--termine' : '') . (empty($evt['est_tache']) ? '' : ' evt--tache');
    return '<a class="' . $classes . '"'
        . ' href="' . $destination($evt) . '"'
        . ' style="background:' . e($fond) . ';border-left-color:' . e($couleur) . ';color:inherit"'
        . ' title="' . e(libelle_type($evt) . ' · ' . $evt['titre']) . '">'
        . $heure . e(icone_evenement($evt)) . ' ' . e($evt['titre'])
        . '</a>';
};
?>

<div class="entete-page">
  <div>
    <h1>Calendrier</h1>
    <p>Vos évènements et les échéances de vos tâches, au même endroit.</p>
  </div>
  <div class="actions">
    <?php
    /*
     * Relire sans quitter le calendrier : c'est ici qu'on s'aperçoit qu'il
     * manque un rendez-vous, et faire un détour par une page de réglages pour
     * appuyer sur un bouton n'a jamais eu de sens.
     */
    ?>
    <?php if (Agenda::relies(Auth::id()) !== []): ?>
      <form method="post" action="<?= url('agenda/synchroniser') ?>">
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
        <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
        <button class="bouton bouton--secondaire" type="submit"
                title="Synchroniser mes agendas maintenant">↻</button>
      </form>
    <?php endif; ?>
    <a class="bouton bouton--secondaire" href="<?= url('agenda') ?>">📆 Mes agendas</a>
    <a class="bouton bouton--secondaire" href="<?= url('organisation/types') ?>">Gérer les types</a>
    <a class="bouton" href="<?= url('evenements/nouveau') ?>">+ Évènement</a>
  </div>
</div>

<div class="cal-barre">
  <div class="actions">
    <a class="bouton bouton--secondaire bouton--petit" href="<?= $lien($vue, $precedent) ?>" aria-label="Période précédente">←</a>
    <a class="bouton bouton--secondaire bouton--petit" href="<?= $lien($vue, new DateTimeImmutable('today')) ?>">Aujourd'hui</a>
    <a class="bouton bouton--secondaire bouton--petit" href="<?= $lien($vue, $suivant) ?>" aria-label="Période suivante">→</a>
    <h2 class="cal-titre"><?= e($titre) ?></h2>
  </div>

  <div class="actions">
    <form method="get" action="<?= url('calendrier') ?>" data-auto-envoi>
      <input type="hidden" name="vue" value="<?= e($vue) ?>">
      <input type="hidden" name="date" value="<?= e($ancre->format('Y-m-d')) ?>">
      <select name="matiere" aria-label="Filtrer par matière">
        <option value="">Toutes les matières</option>
        <?php foreach ($matieres as $m): ?>
          <option value="<?= (int) $m['id'] ?>"<?= $matiereId === (int) $m['id'] ? ' selected' : '' ?>>
            <?= e($m['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select name="type" aria-label="Filtrer par type">
        <option value="">Tous les types</option>
        <?php foreach ($types as $t): ?>
          <option value="<?= (int) $t['id'] ?>"<?= $typeId === (int) $t['id'] ? ' selected' : '' ?>>
            <?= e($t['icone'] . ' ' . $t['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="bouton bouton--secondaire bouton--petit" type="submit">OK</button></noscript>
    </form>

    <nav class="cal-onglets" aria-label="Type d'affichage">
      <a href="<?= $lien('jour', $ancre) ?>"<?= $vue === 'jour' ? ' aria-current="page"' : '' ?>>Jour</a>
      <a href="<?= $lien('semaine', $ancre) ?>"<?= $vue === 'semaine' ? ' aria-current="page"' : '' ?>>Semaine</a>
      <a href="<?= $lien('mois', $ancre) ?>"<?= $vue === 'mois' ? ' aria-current="page"' : '' ?>>Mois</a>
      <a href="<?= $lien('liste', $ancre) ?>"<?= $vue === 'liste' ? ' aria-current="page"' : '' ?>>Liste</a>
    </nav>
  </div>
</div>

<div class="cal-avec-volet<?= $sources === [] ? '' : ' cal-avec-volet--volet' ?>">

<?php if ($sources !== []): ?>
  <?php
  /*
   * Le volet des agendas.
   *
   * Cocher ici ne touche à rien : ni synchronisation, ni suppression. C'est
   * un rideau qu'on tire sur l'agenda d'un proche le temps d'y voir clair,
   * et qu'on rouvre sans que rien n'ait à être retéléchargé.
   */
  ?>
  <aside class="cal-volet" aria-label="Agendas affichés"
         data-volet="<?= e(url('calendrier/volet')) ?>">
    <form method="post" action="<?= url('calendrier/agendas') ?>" data-auto-envoi>
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
      <h2 class="cal-volet__titre">Agendas</h2>
      <?php
      /*
       * Chaque section se plie. « details » le fait sans une ligne de script :
       * replier reste possible quand JavaScript ne répond pas, et l'état
       * s'écrit dans le HTML plutôt qu'après coup — pas de section qu'on
       * verrait s'ouvrir puis se refermer au chargement.
       */
      ?>
      <?php foreach ($sources as $section): ?>
      <details class="cal-volet__section" data-section="<?= e($section['cle']) ?>"
               <?= $section['replie'] ? '' : 'open' ?>>
        <summary class="cal-volet__groupe"><?= e($section['titre']) ?></summary>
        <ul class="cal-volet__liste">
        <?php foreach ($section['sources'] as $source): ?>
          <li>
            <label>
              <input type="checkbox" name="sources[]"
                     value="<?= e($source['cle']) ?>"<?= $source['affiche'] ? ' checked' : '' ?>>
              <span><?= e($source['nom']) ?></span>
            </label>
            <?php
            /*
             * Le nuancier du navigateur, celui-là même que l'éditeur de
             * documents ouvre : toutes les teintes plutôt qu'une palette de
             * dix, et un outil que la personne connaît déjà.
             */
            ?>
            <input type="color" class="cal-volet__teinte"
                   name="couleur[<?= e($source['cle']) ?>]"
                   value="<?= e($source['couleur']) ?>"
                   title="Couleur de <?= e($source['nom']) ?>"
                   aria-label="Couleur de <?= e($source['nom']) ?>">
          </li>
        <?php endforeach; ?>
        </ul>
      </details>
      <?php endforeach; ?>
      <noscript>
        <button class="bouton bouton--secondaire bouton--petit" type="submit">Appliquer</button>
      </noscript>
      <p class="champ__aide cal-volet__note">
        La couleur d'un agenda ne s'applique qu'aux évènements sans matière ni
        type : celles-ci gardent la main.
        Masquer un agenda ne le désynchronise pas et n'efface rien.
        <a href="<?= url('agenda') ?>">Choisir ceux à synchroniser</a>
      </p>
    </form>
  </aside>
<?php endif; ?>

<div class="cal-corps">

<?php if ($vue === 'jour'): ?>

  <?php
  /*
   * La journée en grille.
   *
   * Une liste dit ce qu'il y a, une grille dit quand : les heures libres se
   * voient sans avoir à soustraire deux horaires, et deux rendez-vous qui se
   * chevauchent se montrent côte à côte plutôt que l'un sous l'autre.
   *
   * Le calcul est ailleurs — PlanningJour —, la vue ne fait que traduire des
   * minutes en styles. Une heure vaut « --heure » de haut, et tout se place à
   * partir de là : la même mesure sert au trait des heures, aux évènements et
   * au repère de l'heure qu'il est.
   */
  $cle = $ancre->format('Y-m-d');
  $duJour = $parJour[$cle] ?? [];
  $planning = PlanningJour::disposer($duJour, $ancre);
  ?>
  <section class="jour-planning<?= $cle === $aujourdhui ? ' jour-planning--aujourdhui' : '' ?>"
           style="--heures:<?= (int) ($planning['fin'] - $planning['debut']) ?>">
    <header class="jour-planning__entete">
      <span class="jour-planning__titre">
        <?= $cle === $aujourdhui ? "Aujourd'hui" : e(ucfirst(date_fr($cle . ' 00:00:00', false))) ?>
      </span>
      <a class="discret" href="<?= url('evenements/nouveau', ['date' => $cle]) ?>">+ ajouter</a>
    </header>

    <?php if ($planning['journee'] !== []): ?>
      <?php
      /*
       * Le bandeau du haut : ce qui n'a pas d'heure, ou qui déborde du jour.
       * L'étaler sur toute la hauteur de la grille masquerait tout le reste.
       */
      ?>
      <div class="jour-planning__bandeau">
        <span class="jour-planning__etiquette">Journée</span>
        <div class="jour-planning__toutlejour">
          <?php foreach ($planning['journee'] as $evt): ?>
            <?= $puce($evt) ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="jour-planning__grille">
      <div class="jour-planning__heures">
        <?php for ($h = $planning['debut']; $h < $planning['fin']; $h++): ?>
          <div class="jour-planning__heure">
            <span><?= str_pad((string) $h, 2, '0', STR_PAD_LEFT) ?>:00</span>
          </div>
        <?php endfor; ?>
      </div>

      <div class="jour-planning__piste">
        <?php for ($h = $planning['debut']; $h < $planning['fin']; $h++): ?>
          <div class="jour-planning__ligne"></div>
        <?php endfor; ?>

        <?php if ($planning['maintenant'] !== null): ?>
          <div class="jour-planning__maintenant"
               style="--minute:<?= (float) $planning['maintenant'] ?>"
               aria-hidden="true"></div>
        <?php endif; ?>

        <?php if ($planning['blocs'] === []): ?>
          <p class="jour-planning__vide discret">Rien de prévu à cette heure-là.</p>
        <?php endif; ?>

        <?php foreach ($planning['blocs'] as $bloc): ?>
          <?php
          $evt = $bloc['evt'];
          $couleur = couleur_evenement($evt);
          $largeur = 100 / $bloc['colonnes'];
          ?>
          <a class="jour-planning__evt<?= $evt['termine'] ? ' jour-planning__evt--termine' : ''
             ?><?= $bloc['court'] ? ' jour-planning__evt--court' : '' ?>"
             href="<?= $destination($evt) ?>"
             style="--minute:<?= (float) $bloc['haut'] ?>;--duree:<?= (float) $bloc['hauteur'] ?>;
                    --gauche:<?= round($bloc['colonne'] * $largeur, 3) ?>%;
                    --largeur:<?= round($largeur, 3) ?>%;
                    --teinte:<?= e($couleur) ?>"
             title="<?= e(libelle_type($evt) . ' · ' . $evt['titre']) ?>">
            <span class="jour-planning__evt-heure">
              <?= e(date('H:i', strtotime($evt['debut']))) ?>–<?= e(date('H:i', strtotime($evt['fin']))) ?>
            </span>
            <span class="jour-planning__evt-titre">
              <?= e(icone_evenement($evt)) ?> <?= e($evt['titre']) ?>
            </span>
            <?php if (!$bloc['court'] && (string) ($evt['lieu'] ?? '') !== ''): ?>
              <span class="jour-planning__evt-lieu"><?= e($evt['lieu']) ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php elseif ($vue === 'mois'): ?>

  <div class="cal-grille">
    <?php foreach (jours_semaine() as $jour): ?>
      <div class="cal-entete-jour"><?= e($jour) ?></div>
    <?php endforeach; ?>

    <?php
    $curseur = $debut;
    $moisAffiche = (int) $ancre->format('n');
    while ($curseur <= $fin):
        $cle = $curseur->format('Y-m-d');
        $duJour = $parJour[$cle] ?? [];
        $classes = 'cal-jour';
        if ((int) $curseur->format('n') !== $moisAffiche) {
            $classes .= ' cal-jour--hors';
        } elseif ((int) $curseur->format('N') >= 6) {
            $classes .= ' cal-jour--weekend';
        }
        if ($cle === $aujourdhui) {
            $classes .= ' cal-jour--aujourdhui';
        }
        ?>
        <div class="<?= $classes ?>">
          <div class="cal-jour__haut">
            <span class="cal-jour__numero"><?= (int) $curseur->format('j') ?></span>
            <a class="cal-jour__ajout" href="<?= url('evenements/nouveau', ['date' => $cle]) ?>"
               title="Ajouter un évènement le <?= e($curseur->format('d/m/Y')) ?>">+</a>
          </div>
          <?php foreach (array_slice($duJour, 0, 4) as $evt): ?>
            <?= $puce($evt) ?>
          <?php endforeach; ?>
          <?php if (count($duJour) > 4): ?>
            <a class="discret" style="font-size:.72rem" href="<?= $lien('semaine', $curseur) ?>">
              +<?= count($duJour) - 4 ?> autre<?= count($duJour) - 4 > 1 ? 's' : '' ?>
            </a>
          <?php endif; ?>
        </div>
        <?php
        $curseur = $curseur->modify('+1 day');
    endwhile;
    ?>
  </div>

<?php elseif ($vue === 'semaine'): ?>

  <?php
  /*
   * La semaine, à la même échelle que la journée.
   *
   * Les heures montrées sont communes aux sept jours : calculées colonne par
   * colonne, les lignes ne tomberaient pas en face et la semaine deviendrait
   * illisible. C'est PlanningJour qui s'en charge, et la vue ne fait ici que
   * répéter sept fois ce qu'elle fait une fois pour le jour.
   */
  $jours = [];
  $curseur = $debut;
  while ($curseur <= $fin) {
      $jours[] = $curseur;
      $curseur = $curseur->modify('+1 day');
  }
  $planning = PlanningJour::semaine($parJour, $jours);
  $bandeau = false;
  foreach ($planning['jours'] as $unJour) {
      $bandeau = $bandeau || $unJour['journee'] !== [];
  }
  ?>
  <div class="sem-planning"
       style="--jours:<?= count($jours) ?>;--heures:<?= (int) ($planning['fin'] - $planning['debut']) ?>">
    <div class="sem-planning__defile">
      <div class="sem-planning__cadre">

        <div class="sem-planning__entetes">
          <div class="sem-planning__coin"></div>
          <?php foreach ($planning['jours'] as $unJour): ?>
            <a class="sem-planning__jour<?= $unJour['cle'] === $aujourdhui ? ' sem-planning__jour--aujourdhui' : '' ?>"
               href="<?= $lien('jour', $unJour['date']) ?>"
               title="Voir le <?= e($unJour['date']->format('d/m/Y')) ?>">
              <span class="sem-planning__jour-nom"><?= e(jours_semaine()[(int) $unJour['date']->format('N') - 1]) ?></span>
              <span class="sem-planning__jour-numero"><?= (int) $unJour['date']->format('j') ?></span>
            </a>
          <?php endforeach; ?>
        </div>

        <?php if ($bandeau): ?>
          <?php
          /*
           * Le bandeau du haut, colonne par colonne : ce qui n'a pas d'heure.
           * Il n'apparaît que si la semaine en compte — une bande vide sur
           * sept jours ne dirait rien et prendrait la place du reste.
           */
          ?>
          <div class="sem-planning__bandeau">
            <span class="sem-planning__etiquette">Journée</span>
            <?php foreach ($planning['jours'] as $unJour): ?>
              <div class="sem-planning__toutlejour">
                <?php foreach ($unJour['journee'] as $evt): ?>
                  <?= $puce($evt) ?>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="sem-planning__grille">
          <div class="sem-planning__heures">
            <?php for ($h = $planning['debut']; $h < $planning['fin']; $h++): ?>
              <div class="jour-planning__heure">
                <span><?= str_pad((string) $h, 2, '0', STR_PAD_LEFT) ?>:00</span>
              </div>
            <?php endfor; ?>
          </div>

          <?php foreach ($planning['jours'] as $unJour): ?>
            <div class="sem-planning__piste<?= $unJour['cle'] === $aujourdhui ? ' sem-planning__piste--aujourdhui' : '' ?>">
              <?php for ($h = $planning['debut']; $h < $planning['fin']; $h++): ?>
                <div class="jour-planning__ligne"></div>
              <?php endfor; ?>

              <?php if ($unJour['maintenant'] !== null): ?>
                <div class="jour-planning__maintenant"
                     style="--minute:<?= (float) $unJour['maintenant'] ?>"
                     aria-hidden="true"></div>
              <?php endif; ?>

              <?php foreach ($unJour['blocs'] as $bloc): ?>
                <?php
                $evt = $bloc['evt'];
                $couleur = couleur_evenement($evt);
                $largeur = 100 / $bloc['colonnes'];
                ?>
                <a class="jour-planning__evt<?= $evt['termine'] ? ' jour-planning__evt--termine' : ''
                   ?><?= $bloc['court'] ? ' jour-planning__evt--court' : '' ?>"
                   href="<?= $destination($evt) ?>"
                   style="--minute:<?= (float) $bloc['haut'] ?>;--duree:<?= (float) $bloc['hauteur'] ?>;
                          --gauche:<?= round($bloc['colonne'] * $largeur, 3) ?>%;
                          --largeur:<?= round($largeur, 3) ?>%;
                          --teinte:<?= e($couleur) ?>"
                   title="<?= e(libelle_type($evt) . ' · ' . $evt['titre']) ?>">
                  <span class="jour-planning__evt-heure">
                    <?= e(date('H:i', strtotime($evt['debut']))) ?>
                  </span>
                  <span class="jour-planning__evt-titre">
                    <?= e(icone_evenement($evt)) ?> <?= e($evt['titre']) ?>
                  </span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

<?php else: ?>

  <?php if ($evenements === []): ?>
    <div class="vide">
      <span class="vide__icone">🗓️</span>
      <p>Aucun évènement à partir de <?= e(strtolower(nom_mois((int) $ancre->format('n')))) ?>
         <?= e($ancre->format('Y')) ?>.</p>
      <a class="bouton" href="<?= url('evenements/nouveau') ?>">Planifier quelque chose</a>
    </div>
  <?php else: ?>
    <div class="pile">
      <?php
      $jourCourant = null;
      foreach ($evenements as $evt):
          $jour = substr((string) $evt['debut'], 0, 10);
          if ($jour !== $jourCourant):
              $jourCourant = $jour; ?>
              <h3 style="margin:1rem 0 .25rem;text-transform:capitalize">
                <?= e(date_fr($evt['debut'], false)) ?>
              </h3>
          <?php endif; ?>
          <?= Vue::rendre('calendrier/_ligne', ['evt' => $evt]) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php endif; ?>

</div><!-- .cal-corps -->
</div><!-- .cal-avec-volet -->

<div class="legende-types" style="margin-top:1rem">
  <?php foreach ($types as $t): ?>
    <a class="pastille" href="<?= url('calendrier', ['vue' => $vue, 'date' => $ancre->format('Y-m-d'), 'type' => $t['id']]) ?>"
       style="background:<?= e($t['couleur']) ?>;color:<?= e(couleur_texte($t['couleur'])) ?>">
      <?= e($t['icone'] . ' ' . $t['nom']) ?>
    </a>
  <?php endforeach; ?>
  <a class="pastille" href="<?= url('organisation/types') ?>">⚙ Gérer</a>
</div>

<?php if ($aVenir !== []): ?>
  <section class="carte" style="margin-top:1.5rem">
    <h2>Prochainement</h2>
    <p class="discret" style="margin:-.4rem 0 .75rem">Les prochains évènements et échéances, quels que soient les filtres ci-dessus.</p>
    <div class="pile">
      <?php foreach ($aVenir as $evt): ?>
        <?= Vue::rendre('calendrier/_ligne', ['evt' => $evt, 'avecDate' => true]) ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
