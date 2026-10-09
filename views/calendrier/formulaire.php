<?php
/**
 * @var ?array $evenement
 * @var array $ouEnvoyer  les agendas où cet évènement peut partir
 * @var array $vises  ceux qu'il vise déjà
 * @var bool $venuDAilleurs  vient-il de l'agenda de quelqu'un d'autre ?
 * @var ?array $copie  la copie à soi qu'on en a déjà faite
 * @var array $matieres, $coursListe, $types
 * @var string $dateDefaut
 * @var ?int $typeDefaut
 * @var bool $dansUneFenetre  rendu seul, pour être posé dans une fenêtre
 * @var ?string $retour  la page où revenir une fois créé, modifié ou supprimé (l'accueil : « / »), ou null
 */
$dansUneFenetre = $dansUneFenetre ?? false;
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
    $html = '<span class="legende">' . e(t('evtf.jours_semaine')) . '</span><div class="jours-semaine">';
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

    return '<span class="legende">' . e(t('evtf.fin_repetition')) . '</span>'
        . '<div class="fin-repetition">'
        . '<label class="case"><input type="radio" name="fin_type" value="date"'
        . ($parNombre ? '' : ' checked') . '> ' . e(t('evtf.le')) . '</label>'
        . '<input type="date" id="' . $prefixe . '-jusqu" name="repeter_jusqu_au"'
        . ' aria-label="' . e(t('evtf.date_fin_repetition')) . '"'
        . ' value="' . $q((string) $date) . '">'
        . '<label class="case"><input type="radio" name="fin_type" value="nombre"'
        . ($parNombre ? ' checked' : '') . '> ' . e(t('evtf.apres')) . '</label>'
        . '<input type="number" id="' . $prefixe . '-nombre" name="repeter_nombre"'
        . ' min="1" max="200" step="1" aria-label="' . e(t('evtf.nombre_occurrences')) . '"'
        . ' value="' . ($parNombre ? (int) $nombre : '') . '">'
        . '<span class="discret">' . e(t('evtf.occurrences')) . '</span>'
        . '</div>';
};

/*
 * Un nouvel évènement ne commence pas dans le passé : pas avant aujourd'hui,
 * et aujourd'hui pas avant l'heure qu'il est — arrondie aux cinq minutes
 * suivantes, il dure une heure. Un autre jour, on garde 8 h – 9 h. Les heures
 * sont celles du fuseau de l'utilisateur : c'est celui de PHP pendant la page.
 * Modifier un évènement déjà passé reste permis.
 */
$aujourdhui = date('Y-m-d');
$dateDebut = $edition ? substr((string) $evenement['debut'], 0, 10) : ($dateDefaut ?: $aujourdhui);
if (!$edition && $dateDebut < $aujourdhui) {
    $dateDebut = $aujourdhui;
}
$dateFin   = $edition ? substr((string) $evenement['fin'], 0, 10) : $dateDebut;
$heureDebut = $edition ? substr((string) $evenement['debut'], 11, 5) : '08:00';
$heureFin   = $edition ? substr((string) $evenement['fin'], 11, 5) : '09:00';
if (!$edition && $dateDebut === $aujourdhui) {
    $debutPropose = (int) (ceil(time() / 300) * 300);
    if (date('Y-m-d', $debutPropose) === $aujourdhui) {
        $heureDebut = date('H:i', $debutPropose);
        $finProposee = $debutPropose + 3600;
        $dateFin = date('Y-m-d', $finProposee);
        $heureFin = date('H:i', $finProposee);
    } else {
        // 23 h 56 : il n'y a plus de créneau de cinq minutes aujourd'hui.
        $heureDebut = $heureFin = '23:59';
    }
}
$journee = $edition ? (int) $evenement['journee_entiere'] === 1 : false;
/*
 * Le type coché d'avance.
 *
 * À la création, le premier de la liste : on classe presque toujours ce qu'on
 * écrit, et l'imposer là ne coûte qu'un clic à qui n'en veut pas.
 *
 * À la modification, celui de l'évènement — et rien s'il n'en a pas. En
 * cocher un à sa place changerait sa couleur au premier enregistrement, ce
 * qui arrive surtout aux évènements venus d'un agenda distant : ils n'ont
 * jamais de type, et c'est la couleur de leur agenda qui les distingue.
 */
$typeActif = $edition ? entier_ou_null($evenement['type_id']) : $typeDefaut;
if (!$edition && $typeActif === null && $types !== []) {
    $typeActif = (int) $types[0]['id'];
}
$coursActif = $edition ? entier_ou_null($evenement['cours_id']) : entier_ou_null($_GET['cours'] ?? null);
$matiereActive = $edition ? entier_ou_null($evenement['matiere_id']) : null;
?>

<?php
/*
 * « data-large » dit à la fenêtre de s'élargir : le formulaire tient sur deux
 * colonnes, là où une fiche de six lignes se lit mieux étroite. C'est le
 * contenu qui annonce la place qu'il lui faut, plutôt que le script qui
 * devine ce qu'il vient de charger.
 */
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p class="discret" style="margin-bottom:.35rem">
        <a href="<?= url('calendrier', ['date' => $dateDebut]) ?>"><?= e(t('evt.retour_calendrier')) ?></a>
      </p>
    <?php endif; ?>
    <h1><?= e($edition ? t('evtf.modifier') : t('evtf.nouveau')) ?></h1>
  </div>
</div>

<form method="post" action="<?= $action ?>">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <?php if (($retour ?? null) !== null): ?>
    <input type="hidden" name="retour" value="<?= e($retour) ?>">
  <?php endif; ?>

  <div class="colonnes">
    <div class="carte">
      <div class="champ">
        <label for="titre"><?= e(t('evtf.titre')) ?></label>
        <input type="text" id="titre" name="titre" required maxlength="200" autofocus
               placeholder="<?= e(t('evtf.titre_exemple')) ?>" value="<?= e($valeur('titre')) ?>">
      </div>

      <fieldset>
        <legend><?= e(t('evtf.type')) ?></legend>
        <?php if ($types === []): ?>
          <p class="discret" style="margin:0">
            <?= e(t('evtf.aucun_type')) ?> <a href="<?= url('organisation/types') ?>"><?= e(t('evtf.creer_type')) ?></a> <?= e(t('evtf.creer_type_suite')) ?>
          </p>
        <?php else: ?>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <label class="case">
              <input type="radio" name="type_id" value=""<?= $typeActif === null ? ' checked' : '' ?>>
              <span class="pastille pastille--muette"><?= e(t('evtf.aucun')) ?></span>
            </label>
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
            <a href="<?= url('organisation/types') ?>"><?= e(t('evtf.gerer_types')) ?></a>
          </p>
        <?php endif; ?>
      </fieldset>

      <div class="ligne-champs">
        <div class="champ">
          <label for="date_debut"><?= e(t('evtf.date_debut')) ?></label>
          <input type="date" id="date_debut" name="date_debut" required value="<?= e($dateDebut) ?>"
                 <?php if (!$edition): ?>min="<?= e($aujourdhui) ?>" data-maintenant="<?= e(date('Y-m-d\TH:i')) ?>"<?php endif; ?>>
        </div>
        <div class="champ">
          <label for="date_fin"><?= e(t('evtf.date_fin')) ?></label>
          <input type="date" id="date_fin" name="date_fin" value="<?= e($dateFin) ?>">
        </div>
      </div>

      <label class="case" style="margin-bottom:1rem">
        <input type="checkbox" id="journee_entiere" name="journee_entiere" value="1"<?= $journee ? ' checked' : '' ?>>
        <?= e(t('evtf.journee_entiere')) ?>
      </label>

      <div class="ligne-champs" id="bloc-heures">
        <div class="champ">
          <label for="heure_debut"><?= e(t('evtf.heure_debut')) ?></label>
          <input type="time" id="heure_debut" name="heure_debut" value="<?= e($heureDebut) ?>">
        </div>
        <div class="champ">
          <label for="heure_fin"><?= e(t('evtf.heure_fin')) ?></label>
          <input type="time" id="heure_fin" name="heure_fin" value="<?= e($heureFin) ?>">
        </div>
      </div>

      <?php
      /*
       * Les rappels : une notification sur les appareils abonnés, à chacun des
       * délais cochés. Juste sous l'heure, qu'ils accompagnent. Quinze minutes
       * pour un nouvel évènement.
       */
      $rappelsActuels = $edition ? Rappels::lire((string) ($evenement['rappels'] ?? '')) : [15];
      ?>
      <fieldset class="rappels-choix" data-rappels>
        <legend><?= e(t('evtf.rappels')) ?></legend>
        <div class="rappels-choix__liste">
          <?php foreach (array_reverse(Rappels::DELAIS_COURTS, true) as $minutes => $court): ?>
            <label class="rappels-choix__option" title="<?= e(Rappels::libelle((int) $minutes)) ?>">
              <input type="checkbox" name="rappels[]" value="<?= (int) $minutes ?>"<?= in_array($minutes, $rappelsActuels, true) ? ' checked' : '' ?>>
              <span class="pastille"><?= e(Rappels::court((int) $minutes)) ?></span>
            </label>
          <?php endforeach; ?>
          <?php // « Aucun » ne part pas : sans aucun délai envoyé, l'évènement n'a pas de rappel. ?>
          <label class="rappels-choix__option" title="<?= e(t('evtf.rappel_aucun_titre')) ?>">
            <input type="checkbox" data-rappel-aucun<?= $rappelsActuels === [] ? ' checked' : '' ?>>
            <span class="pastille"><?= e(t('evtf.rappel_aucun')) ?></span>
          </label>
        </div>
        <span class="champ__aide">
          <?= e(t('evtf.rappels_aide')) ?>
          (<a href="<?= url('notifications') ?>" data-fenetre-dessus><?= e(t('evtf.regler_notifications')) ?></a>)
        </span>
      </fieldset>

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
            <label for="repetition"><?= e(t('evtf.repeter')) ?></label>
            <select id="repetition" name="repetition">
              <option value="jamais"><?= e(t('evtf.jamais')) ?></option>
              <option value="jour"><?= e(t('taches.recurrence.jour')) ?></option>
              <option value="semaine"><?= e(t('taches.recurrence.semaine')) ?></option>
              <option value="quinzaine"><?= e(t('evtf.quinzaine')) ?></option>
              <option value="mois"><?= e(t('taches.recurrence.mois')) ?></option>
            </select>
          </div>

          <div class="champ">
            <?= $borneRepetition(null, null, 'neuf') ?>
            <span class="champ__aide"><?= e(t('evtf.repetition_limite')) ?></span>
          </div>
        </div>

        <div class="champ">
          <?= $joursSemaine([], 'neuf') ?>
          <span class="champ__aide">
            <?= e(t('evtf.jours_aide')) ?>
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
            <?= e(tn('evtf.serie_titre', (int) $serie['occurrences'], [
                'rythme' => t('evtf.serie.' . $serie['frequence']),
                'date' => date_numerique((string) $serie['jusqu_au']),
            ])) ?>
          </legend>
          <label class="case">
            <input type="radio" name="portee" value="occurrence" checked>
            <?= e(t('evtf.serie_occurrence')) ?>
          </label>
          <label class="case">
            <input type="radio" name="portee" value="serie">
            <?= e(t('evtf.serie_toutes', ['n' => (int) $serie['occurrences']])) ?>
          </label>
          <span class="champ__aide">
            <?= e(t('evtf.serie_dates_aide')) ?>
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
              <label for="repetition"><?= e(t('evtf.rythme')) ?></label>
              <select id="repetition" name="repetition">
                <?php foreach (['jour', 'semaine', 'quinzaine', 'mois'] as $cle): ?>
                  <option value="<?= e($cle) ?>"<?= $serie['frequence'] === $cle ? ' selected' : '' ?>>
                    <?= e(t('evtf.rythme.' . $cle)) ?>
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
            <?= e(t('evtf.serie_rythme_aide')) ?>
          </span>
        </fieldset>
      <?php endif; ?>

      <div class="champ">
        <label for="lieu"><?= e(t('evtf.lieu')) ?></label>
        <input type="text" id="lieu" name="lieu" maxlength="160" placeholder="<?= e(t('evtf.lieu_exemple')) ?>"
               value="<?= e($valeur('lieu')) ?>">
      </div>


      <div class="champ">
        <label for="description"><?= e(t('evtf.notes')) ?></label>
        <textarea id="description" name="description" style="min-height:120px" data-texte-riche
                  placeholder="<?= e(t('evtf.notes_exemple')) ?>"><?= e(TexteRiche::pourEditeur($valeur('description'))) ?></textarea>
      </div>
    </div>

    <div class="pile">
      <div class="carte">
        <div class="champ">
          <label for="matiere_id"><?= e(t('cours.matiere')) ?></label>
          <select id="matiere_id" name="matiere_id">
            <option value=""><?= e(t('commun.aucune')) ?></option>
            <?php foreach ($matieres as $m): ?>
              <option value="<?= (int) $m['id'] ?>"<?= $matiereActive === (int) $m['id'] ? ' selected' : '' ?>>
                <?= e($m['nom']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="champ__aide"><?= e(t('evtf.matiere_aide')) ?></span>
        </div>

        <div class="champ">
          <label for="cours_id"><?= e(t('evtf.cours_lie')) ?></label>
          <select id="cours_id" name="cours_id">
            <option value=""><?= e(t('commun.aucun')) ?></option>
            <?php foreach ($coursListe as $c): ?>
              <option value="<?= (int) $c['id'] ?>"<?= $coursActif === (int) $c['id'] ? ' selected' : '' ?>>
                <?= e($c['titre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="champ__aide"><?= e(t('evtf.cours_aide')) ?></span>
        </div>
      </div>

      <?php
      /*
       * Où va cet évènement.
       *
       * « Mes évènements » est le calendrier de l'application, et le choix par
       * défaut. Cocher d'autres agendas l'y envoie aussi — plusieurs à la
       * fois, chacun recevant sa copie. Ce ne sont pas des copies mortes :
       * changer l'horaire ici le change partout, supprimer l'évènement ici le
       * retire de partout, décocher un agenda l'en retire lui seul.
       *
       * Ne sont proposés que les agendas où le fournisseur nous autorise à
       * écrire. Un agenda désigné puis disparu ne se coche plus : l'évènement
       * retombe alors sur « Mes évènements », ici comme à l'envoi.
       *
       * Un évènement venu de l'agenda de quelqu'un d'autre ne se range pas
       * ainsi : il ne nous appartient pas, et rien de ce qu'on cocherait ne
       * l'emmènerait nulle part. Les mêmes cases servent alors à en faire une
       * copie qui, elle, sera à nous.
       */
      $connus = array_column($ouEnvoyer, 'cle');
      $coches = $venuDAilleurs ? [] : array_values(array_filter(
          $vises,
          static fn (string $v): bool => $v === Agenda::DEFAUT || in_array($v, $connus, true)
      ));
      // Ouvert depuis un calendrier partagé : c'est lui qui est coché, « Mes évènements » ne l'est pas.
      if (($agendaCoche ?? null) !== null) { $coches = []; }
      if (!$venuDAilleurs && $coches === [] && ($agendaCoche ?? null) === null) { $coches = [Agenda::DEFAUT]; }
      ?>
      <?php if (($agendaCoche ?? null) !== null): ?>
        <?php // Ouvert depuis le « ＋ » d'un calendrier partagé : pas de choix, l'évènement y va d'office. ?>
        <?php $ici = current(array_filter($calendriersAmis, static fn (array $c): bool => $c['id'] === $agendaCoche)); ?>
        <div class="carte">
          <input type="hidden" name="agendas[]" value="<?= e(CalendriersAmis::cle((int) $agendaCoche)) ?>">
          <div class="champ">
            <span class="legende"><?= e(t('cam.destination_fixe')) ?></span>
            <span style="display:flex;align-items:center;gap:.5rem;font-weight:600">
              <span class="cam-pastille" style="background:<?= e($ici['couleur']) ?>" aria-hidden="true"></span>
              <?= e($ici['nom']) ?>
            </span>
            <span class="champ__aide"><?= e(tn('cam.membres_n', (int) $ici['membres'])) ?> · <?= e(t('cam.destination_fixe_aide')) ?></span>
          </div>
        </div>

      <?php elseif ($ouEnvoyer !== [] && $venuDAilleurs && $copie !== null): ?>
        <div class="carte">
          <div class="champ">
            <span class="legende"><?= e(t('evtf.autre_agenda')) ?></span>
            <span class="champ__aide">
              <?= e(t('evtf.copie_existe')) ?>
              <a href="<?= url('evenements/' . (int) $copie['id'] . '/modifier') ?>">
                <?= e((string) $copie['titre']) ?></a>.
              <?= e(t('evtf.copie_existe_suite')) ?>
            </span>
          </div>
        </div>

      <?php elseif ($ouEnvoyer !== [] && $venuDAilleurs): ?>
        <div class="carte">
          <div class="champ">
            <span class="legende"><?= e(t('evtf.en_faire_le_mien')) ?></span>

            <label class="case" style="display:block">
              <input type="checkbox" name="agendas[]" value="">
              <?= e(t('evtf.mes_evenements')) ?>
              <span class="discret"><?= e(t('evtf.calendrier_appli')) ?></span>
            </label>

            <?php foreach ($ouEnvoyer as $cal): ?>
              <label class="case" style="display:block">
                <input type="checkbox" name="agendas[]" value="<?= e($cal['cle']) ?>">
                <?= e($cal['nom']) ?>
                <span class="discret">
                  (<?= e($cal['agenda']) ?>)<?= $cal['partage'] ? e(t('evtf.partage_suffixe')) : '' ?>
                </span>
              </label>
            <?php endforeach; ?>

            <span class="champ__aide">
              <?= e(t('evtf.copie_aide')) ?>
            </span>
          </div>
        </div>

      <?php elseif ($ouEnvoyer !== [] || $calendriersAmis !== []): ?>
        <div class="carte">
          <div class="champ">
            <span class="legende"><?= e(t('evtf.ou_envoyer')) ?></span>

            <label class="case" style="display:block">
              <input type="checkbox" name="agendas[]" value=""
                     <?= in_array(Agenda::DEFAUT, $coches, true) ? 'checked' : '' ?>>
              <?= e(t('evtf.mes_evenements')) ?>
              <span class="discret"><?= e(t('evtf.calendrier_appli')) ?></span>
            </label>

            <?php foreach ($ouEnvoyer as $cal): ?>
              <label class="case" style="display:block">
                <input type="checkbox" name="agendas[]" value="<?= e($cal['cle']) ?>"
                       <?= in_array($cal['cle'], $coches, true) ? 'checked' : '' ?>>
                <?= e($cal['nom']) ?>
                <span class="discret">
                  (<?= e($cal['agenda']) ?>)<?= $cal['partage'] ? e(t('evtf.partage_suffixe')) : '' ?>
                </span>
              </label>
            <?php endforeach; ?>

            <?php // Les calendriers partagés avec des amis : l'évènement y est ajouté pour tous leurs membres. ?>
            <?php foreach ($calendriersAmis as $cal): ?>
              <label class="case" style="display:block">
                <input type="checkbox" name="agendas[]" value="<?= e(CalendriersAmis::cle($cal['id'])) ?>"<?= ($agendaCoche ?? null) === $cal['id'] ? ' checked' : '' ?>>
                <span class="cam-pastille" style="background:<?= e($cal['couleur']) ?>" aria-hidden="true"></span>
                <?= e($cal['nom']) ?>
                <span class="discret">(<?= e(t('cam.destination', ['n' => $cal['membres']])) ?>)</span>
              </label>
            <?php endforeach; ?>

            <span class="champ__aide">
              <?= e($ouEnvoyer !== [] ? t('evtf.ou_envoyer_aide') : t('cam.destination_aide_seul')) ?>
              <?php if ($calendriersAmis !== []): ?><?= e(t('cam.destination_aide')) ?><?php endif; ?>
            </span>
          </div>
        </div>
      <?php endif; ?>
      <button class="bouton bouton--bloc" type="submit">

        <?= e($edition ? t('evtf.enregistrer') : t('evtf.ajouter')) ?>
      </button>
      <a class="bouton bouton--secondaire bouton--bloc"
         href="<?= url('calendrier', ['date' => $dateDebut]) ?>"
         <?php // Dans une fenêtre, annuler c'est la refermer : recharger le
            // calendrier pour revenir là où l'on n'a jamais cessé d'être
            // ferait clignoter la page pour rien. ?>
         <?= $dansUneFenetre ? 'data-fermer' : '' ?>><?= e(t('commun.annuler')) ?></a>
    </div>
  </div>
</form>

<?php if ($edition): ?>
  <form method="post" action="<?= url('evenements/' . $evenement['id'] . '/supprimer') ?>"
        data-confirmation="<?= e(t('evtf.supprimer_confirmation')) ?>" style="margin-top:1rem;max-width:320px">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <?php if (($retour ?? null) !== null): ?><input type="hidden" name="retour" value="<?= e($retour) ?>"><?php endif; ?>
    <button class="bouton bouton--danger" type="submit"><?= e(t('evtf.supprimer')) ?></button>
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
          data-confirmation="<?= e(t('evtf.serie_supprimer_confirmation', ['n' => (int) $serie['occurrences']])) ?>"
          style="margin-top:.5rem;max-width:320px">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <?php if (($retour ?? null) !== null): ?><input type="hidden" name="retour" value="<?= e($retour) ?>"><?php endif; ?>
      <input type="hidden" name="serie" value="1">
      <button class="bouton bouton--danger bouton--petit" type="submit">
        <?= e(t('evtf.serie_supprimer', ['n' => (int) $serie['occurrences']])) ?>
      </button>
    </form>
  <?php endif; ?>
<?php endif; ?>
