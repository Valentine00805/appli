<?php
/**
 * @var ?array $evenement
 * @var array $ouDeposer, $depots
 * @var int $partages
 * @var array $matieres, $coursListe, $types
 * @var string $dateDefaut
 * @var ?int $typeDefaut
 */
$edition = $evenement !== null;
$action = $edition ? url('evenements/' . $evenement['id'] . '/modifier') : url('evenements/nouveau');

$valeur = static function (string $champ, string $defaut = '') use ($evenement, $edition): string {
    if ($edition) {
        return (string) ($evenement[$champ] ?? '');
    }
    return post($champ, $defaut);
};

/**
 * Les cases des jours de la semaine.
 *
 * Elles ne valent que pour les rythmes hebdomadaires ; « chaque jour » les
 * prend déjà tous et « chaque mois » se compte en quantièmes. Aucune cochée,
 * la série garde le jour de sa date de départ — le comportement d'avant, pour
 * qui ne s'en préoccupe pas.
 */
$joursSemaine = static function (array $coches, string $prefixe): string {
    $noms = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi',
             5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];
    $html = '<span class="legende">Jours de la semaine</span><div class="jours-semaine">';
    foreach ($noms as $numero => $nom) {
        $id = $prefixe . '-jour-' . $numero;
        $html .= '<input type="checkbox" id="' . $id . '" name="jours[]" value="' . $numero . '"'
            . (in_array($numero, $coches, true) ? ' checked' : '') . '>'
            . '<label for="' . $id . '" title="' . e(ucfirst($nom)) . '">'
            . e(mb_strtoupper(mb_substr($nom, 0, 1))) . '<span class="sr-only">' . e($nom) . '</span></label>';
    }

    return $html . '</div>';
};

/**
 * Jusqu'à quand la répétition va.
 *
 * Deux façons de le dire, et l'on choisit la sienne : une date, ou un nombre
 * de fois. « Douze séances » se sait d'avance ; la date où elles se terminent
 * demanderait de compter soi-même les jours cochés et les mois sans 31.
 */
$borneRepetition = static function (?string $date, ?int $nombre, string $prefixe): string {
    $parNombre = $nombre !== null;
    $q = static fn (string $v): string => e($v);

    return '<span class="legende">Fin de la répétition</span>'
        . '<div class="fin-repetition">'
        . '<label class="case"><input type="radio" name="fin_type" value="date"'
        . ($parNombre ? '' : ' checked') . '> le</label>'
        . '<input type="date" id="' . $prefixe . '-jusqu" name="repeter_jusqu_au"'
        . ' aria-label="Date de fin de la répétition"'
        . ' value="' . $q((string) $date) . '">'
        . '<label class="case"><input type="radio" name="fin_type" value="nombre"'
        . ($parNombre ? ' checked' : '') . '> après</label>'
        . '<input type="number" id="' . $prefixe . '-nombre" name="repeter_nombre"'
        . ' min="1" max="200" step="1" aria-label="Nombre d\'occurrences"'
        . ' value="' . ($parNombre ? (int) $nombre : '') . '">'
        . '<span class="discret">occurrences</span>'
        . '</div>';
};

$dateDebut = $edition ? substr((string) $evenement['debut'], 0, 10) : ($dateDefaut ?: date('Y-m-d'));
$dateFin   = $edition ? substr((string) $evenement['fin'], 0, 10) : $dateDebut;
$heureDebut = $edition ? substr((string) $evenement['debut'], 11, 5) : '08:00';
$heureFin   = $edition ? substr((string) $evenement['fin'], 11, 5) : '09:00';
$journee = $edition ? (int) $evenement['journee_entiere'] === 1 : false;
$typeActif = $edition ? entier_ou_null($evenement['type_id']) : $typeDefaut;
if ($typeActif === null && $types !== []) {
    $typeActif = (int) $types[0]['id'];
}
$coursActif = $edition ? entier_ou_null($evenement['cours_id']) : entier_ou_null($_GET['cours'] ?? null);
$matiereActive = $edition ? entier_ou_null($evenement['matiere_id']) : null;
?>

<div class="entete-page">
  <div>
    <p class="discret" style="margin-bottom:.35rem">
      <a href="<?= url('calendrier', ['date' => $dateDebut]) ?>">← Retour au calendrier</a>
    </p>
    <h1><?= $edition ? "Modifier l'évènement" : 'Nouvel évènement' ?></h1>
  </div>
</div>

<form method="post" action="<?= $action ?>">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

  <div class="colonnes">
    <div class="carte">
      <div class="champ">
        <label for="titre">Titre</label>
        <input type="text" id="titre" name="titre" required maxlength="200" autofocus
               placeholder="Contrôle de mathématiques" value="<?= e($valeur('titre')) ?>">
      </div>

      <fieldset>
        <legend>Type d'évènement</legend>
        <?php if ($types === []): ?>
          <p class="discret" style="margin:0">
            Vous n'avez aucun type. <a href="<?= url('organisation/types') ?>">En créer un</a> pour classer vos évènements.
          </p>
        <?php else: ?>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <?php foreach ($types as $t): ?>
              <label class="case">
                <input type="radio" name="type_id" value="<?= (int) $t['id'] ?>"<?= $typeActif === (int) $t['id'] ? ' checked' : '' ?>>
                <span class="pastille" style="background:<?= e($t['couleur']) ?>;color:<?= e(couleur_texte($t['couleur'])) ?>">
                  <?= e($t['icone'] . ' ' . $t['nom']) ?>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="champ__aide" style="margin-top:.5rem">
            <a href="<?= url('organisation/types') ?>">Gérer les types d'évènement</a>
          </p>
        <?php endif; ?>
      </fieldset>

      <div class="ligne-champs">
        <div class="champ">
          <label for="date_debut">Date de début</label>
          <input type="date" id="date_debut" name="date_debut" required value="<?= e($dateDebut) ?>">
        </div>
        <div class="champ">
          <label for="date_fin">Date de fin</label>
          <input type="date" id="date_fin" name="date_fin" value="<?= e($dateFin) ?>">
        </div>
      </div>

      <label class="case" style="margin-bottom:1rem">
        <input type="checkbox" id="journee_entiere" name="journee_entiere" value="1"<?= $journee ? ' checked' : '' ?>>
        Journée entière
      </label>

      <div class="ligne-champs" id="bloc-heures">
        <div class="champ">
          <label for="heure_debut">Heure de début</label>
          <input type="time" id="heure_debut" name="heure_debut" value="<?= e($heureDebut) ?>">
        </div>
        <div class="champ">
          <label for="heure_fin">Heure de fin</label>
          <input type="time" id="heure_fin" name="heure_fin" value="<?= e($heureFin) ?>">
        </div>
      </div>

      <?php
      /*
       * La répétition ne se propose qu'à la création.
       *
       * Les occurrences sont écrites une par une : modifier celle-ci ne touche
       * pas aux autres, et rouvrir le choix ici laisserait croire le contraire.
       */
      ?>
      <?php if ($edition === false): ?>
        <div class="ligne-champs">
          <div class="champ">
            <label for="repetition">Répéter</label>
            <select id="repetition" name="repetition">
              <option value="jamais">Ne pas répéter</option>
              <option value="jour">Chaque jour</option>
              <option value="semaine">Chaque semaine</option>
              <option value="quinzaine">Toutes les deux semaines</option>
              <option value="mois">Chaque mois</option>
            </select>
          </div>

          <div class="champ">
            <?= $borneRepetition(null, null, 'neuf') ?>
            <span class="champ__aide">Deux ans au plus, deux cents occurrences au maximum.</span>
          </div>
        </div>

        <div class="champ">
          <?= $joursSemaine([], 'neuf') ?>
          <span class="champ__aide">
            Pour un rythme hebdomadaire : cochez les jours voulus, par exemple
            lundi et jeudi. Rien de coché garde le jour de la date de début.
          </span>
        </div>
      <?php elseif ($serie !== null): ?>
        <?php
        /*
         * À qui s'applique la modification.
         *
         * « Cette occurrence » d'abord : c'est le geste le moins destructeur,
         * et celui qu'on fait le plus souvent — déplacer un cours d'une
         * semaine, noter une salle différente.
         */
        ?>
        <fieldset class="serie-portee">
          <legend>
            🔁 Série
            <?= e(['jour' => 'quotidienne', 'semaine' => 'hebdomadaire',
                   'quinzaine' => 'toutes les deux semaines', 'mois' => 'mensuelle'][$serie['frequence']] ?? '') ?>
            de <?= (int) $serie['occurrences'] ?> occurrences, jusqu'au
            <?= e(date('d/m/Y', strtotime((string) $serie['jusqu_au']))) ?>
          </legend>
          <label class="case">
            <input type="radio" name="portee" value="occurrence" checked>
            Ne modifier que cette occurrence
          </label>
          <label class="case">
            <input type="radio" name="portee" value="serie">
            Modifier les <?= (int) $serie['occurrences'] ?> occurrences
          </label>
          <span class="champ__aide">
            Sur toute la série, chaque occurrence garde sa date — sans quoi
            elles se retrouveraient toutes le même jour. L'heure et la durée,
            elles, s'appliquent partout.
          </span>

          <?php
          /*
           * Le rythme lui-même.
           *
           * Redéplier une série ne repart pas de zéro : les séances qui
           * tombent encore sur une date attendue sont laissées telles quelles,
           * avec ce qu'on y avait retouché. Seules disparaissent celles qui ne
           * sont plus prévues.
           */
          ?>
          <div class="ligne-champs" style="margin-top:.7rem">
            <div class="champ">
              <label for="repetition">Rythme</label>
              <select id="repetition" name="repetition">
                <?php foreach (['jour' => 'Chaque jour', 'semaine' => 'Chaque semaine',
                                'quinzaine' => 'Toutes les deux semaines',
                                'mois' => 'Chaque mois'] as $cle => $libelle): ?>
                  <option value="<?= e($cle) ?>"<?= $serie['frequence'] === $cle ? ' selected' : '' ?>>
                    <?= e($libelle) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="champ">
              <?= $borneRepetition((string) $serie['jusqu_au'],
                  $serie['nombre_voulu'] === null ? null : (int) $serie['nombre_voulu'], 'serie') ?>
            </div>
          </div>
          <div class="champ" style="margin-top:.5rem">
            <?= $joursSemaine(
                array_map('intval', array_filter(explode(',', (string) ($serie['jours'] ?? '')), 'strlen')),
                'serie') ?>
          </div>
          <span class="champ__aide">
            Changer le rythme ou les jours n'a d'effet que sur toute la série.
            Les séances qui tombent encore sur une date prévue sont conservées
            telles quelles ; celles qui ne le sont plus disparaissent.
          </span>
        </fieldset>
      <?php endif; ?>

      <div class="champ">
        <label for="lieu">Lieu</label>
        <input type="text" id="lieu" name="lieu" maxlength="160" placeholder="Salle B203, amphi, à la maison…"
               value="<?= e($valeur('lieu')) ?>">
      </div>

      <div class="champ">
        <label for="description">Notes</label>
        <textarea id="description" name="description" style="min-height:120px"
                  placeholder="Chapitres à réviser, matériel à apporter…"><?= e($valeur('description')) ?></textarea>
      </div>
    </div>

    <div class="pile">
      <div class="carte">
        <div class="champ">
          <label for="matiere_id">Matière</label>
          <select id="matiere_id" name="matiere_id">
            <option value="">— Aucune —</option>
            <?php foreach ($matieres as $m): ?>
              <option value="<?= (int) $m['id'] ?>"<?= $matiereActive === (int) $m['id'] ? ' selected' : '' ?>>
                <?= e($m['nom']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="champ__aide">Donne sa couleur à l'évènement dans le calendrier.</span>
        </div>

        <div class="champ">
          <label for="cours_id">Cours lié</label>
          <select id="cours_id" name="cours_id">
            <option value="">— Aucun —</option>
            <?php foreach ($coursListe as $c): ?>
              <option value="<?= (int) $c['id'] ?>"<?= $coursActif === (int) $c['id'] ? ' selected' : '' ?>>
                <?= e($c['titre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="champ__aide">Pratique pour retrouver ses notes le jour J.</span>
        </div>
      </div>

      <?php
      /*
       * Donner une copie en même temps qu'on crée l'évènement.
       *
       * C'est le geste naturel — « je note ça pour Fanny » —, et le seul
       * endroit où l'application écrit dans l'agenda de quelqu'un d'autre.
       * Elle n'y écrira qu'une fois : la suite se dit avant, pas après.
       */
      ?>
      <?php if (!$edition && $ouDeposer !== []): ?>
        <div class="carte">
          <div class="champ">
            <span class="legende">Déposer aussi une copie chez</span>
            <?php foreach ($ouDeposer as $cal): ?>
              <label class="case" style="display:block">
                <input type="checkbox" name="deposer[]" value="<?= e($cal['cle']) ?>">
                <?= e($cal['nom']) ?>
                <span class="discret">
                  <?= $cal['chez'] === '' ? '' : '— ' . e($cal['chez']) ?>
                  (<?= e($cal['agenda']) ?>)
                </span>
              </label>
            <?php endforeach; ?>
            <span class="champ__aide">
              Une copie, donnée une fois. Elle leur appartiendra : la modifier
              ou la supprimer ici n'y changera plus rien.
            </span>
          </div>
        </div>
      <?php endif; ?>

      <button class="bouton bouton--bloc" type="submit">
        <?= $edition ? 'Enregistrer' : 'Ajouter au calendrier' ?>
      </button>
      <a class="bouton bouton--secondaire bouton--bloc"
         href="<?= url('calendrier', ['date' => $dateDebut]) ?>">Annuler</a>
    </div>
  </div>
</form>

<?php if ($edition): ?>
  <?php
  /*
   * Donner une copie de cet évènement.
   *
   * L'application ne touche à rien dans l'agenda des autres — sauf ici, et
   * seulement pour ajouter. Ce que l'on dépose ne sera plus jamais corrigé ni
   * repris : c'est dit avant le geste, parce qu'après il est trop tard.
   */
  ?>
  <section class="carte" style="margin-top:1rem;max-width:520px">
    <h2 style="margin-top:0">Déposer une copie chez quelqu'un</h2>

    <?php if ($depots !== []): ?>
      <ul class="champ__aide" style="margin:0 0 .75rem;padding-left:1.1rem">
        <?php foreach ($depots as $d): ?>
          <li>
            Déposé dans « <?= e((string) $d['calendrier_nom']) ?> »
            le <?= e(date('d/m/Y à H:i', strtotime((string) $d['depose_le']))) ?>
            <?php if ((string) $d['titre'] !== (string) $evenement['titre']): ?>
              — sous le titre « <?= e((string) $d['titre']) ?> »
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($ouDeposer === []): ?>
      <p class="champ__aide" style="margin-top:0">
        <?php if ($partages === 0): ?>
          Personne ne vous a partagé son agenda — ou la liste n'a pas encore été
          relue depuis. Elle se rafraîchit depuis
          <a href="<?= url('agenda') ?>">Mes agendas</a>.
        <?php else: ?>
          Les agendas qu'on vous a partagés le sont en lecture seule, ou leur
          droit d'écriture n'a pas encore été relevé. Passez par
          <a href="<?= url('agenda') ?>">Mes agendas</a> et cliquez
          « Actualiser la liste » : c'est là que ce droit se lit.
        <?php endif; ?>
      </p>
    <?php else: ?>
      <form method="post" action="<?= url('evenements/' . $evenement['id'] . '/deposer') ?>">
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

        <div class="champ">
          <span class="legende">Dans l'agenda de</span>
          <?php foreach ($ouDeposer as $cal): ?>
            <label class="case" style="display:block">
              <input type="checkbox" name="deposer[]" value="<?= e($cal['cle']) ?>">
              <?= e($cal['nom']) ?>
              <span class="discret">
                <?= $cal['chez'] === '' ? '' : '— ' . e($cal['chez']) ?>
                (<?= e($cal['agenda']) ?>)
              </span>
            </label>
          <?php endforeach; ?>
        </div>

        <?php if ($serie !== null): ?>
          <div class="champ">
            <label class="case">
              <input type="checkbox" name="serie" value="1">
              Déposer les <?= (int) $serie['occurrences'] ?> séances de la série
            </label>
          </div>
        <?php endif; ?>

        <span class="champ__aide">
          La copie leur appartiendra. La modifier ici, ou la supprimer ici, n'y
          changera plus rien : l'application n'y reviendra pas. Pour se
          reprendre, il faut le leur demander — ou le faire depuis leur agenda.
        </span>

        <button class="bouton bouton--secondaire" type="submit" style="margin-top:.6rem">
          Déposer la copie
        </button>
      </form>
    <?php endif; ?>
  </section>

  <form method="post" action="<?= url('evenements/' . $evenement['id'] . '/supprimer') ?>"
        data-confirmation="Supprimer cet évènement ?" style="margin-top:1rem;max-width:320px">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <button class="bouton bouton--danger" type="submit">Supprimer cet évènement</button>
  </form>

  <?php
  /*
   * Défaire toute la série.
   *
   * Cent occurrences créées d'un mauvais réglage se reprennent mal une par
   * une. Le bouton est distinct, et se confirme : supprimer un cours annulé
   * ne doit pas pouvoir effacer l'année par mégarde.
   */
  ?>
  <?php if ($serie !== null): ?>
    <form method="post" action="<?= url('evenements/' . $evenement['id'] . '/supprimer') ?>"
          data-confirmation="Supprimer les <?= (int) $serie['occurrences'] ?> occurrences de cette série ?"
          style="margin-top:.5rem;max-width:320px">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <input type="hidden" name="serie" value="1">
      <button class="bouton bouton--danger bouton--petit" type="submit">
        Supprimer toute la série (<?= (int) $serie['occurrences'] ?>)
      </button>
    </form>
  <?php endif; ?>
<?php endif; ?>
