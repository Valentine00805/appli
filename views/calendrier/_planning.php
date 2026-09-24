<?php
/**
 * Une journée en grille d'heures.
 *
 * Une liste dit ce qu'il y a, une grille dit quand : les heures libres se
 * voient sans avoir à soustraire deux horaires, et deux rendez-vous qui se
 * chevauchent se montrent côte à côte plutôt que l'un sous l'autre.
 *
 * Le calcul est ailleurs — PlanningJour —, cette vue ne fait que traduire des
 * minutes en styles. Une heure vaut « --heure » de haut, et tout se place à
 * partir de là : la même mesure sert au trait des heures, aux évènements et au
 * repère de l'heure qu'il est.
 *
 * Le calendrier et l'accueil s'en servent tous les deux. En avoir eu deux
 * versions, c'eût été en corriger une sur deux.
 *
 * @var array $planning  ce que rend PlanningJour::disposer()
 * @var string $cle      le jour montré, au format Y-m-d
 * @var bool $estAujourdhui
 * @var bool $compact    resserré, pour tenir dans une colonne d'accueil
 */
$compact = $compact ?? false;
// École ou entreprise ce jour-là. Le calendrier le donne ; l'accueil le demande.
$rythme = $rythme ?? null;
if ($rythme === null && Auth::connecte()) {
    $rythme = Alternance::lieuDuJour(Auth::id(), $cle);
}

/** Où mène un élément : un évènement à sa fiche, une échéance à sa liste. */
$ou = static function (array $evt): string {
    if (!empty($evt['lien'])) {
        return (string) $evt['lien'];
    }
    return empty($evt['est_tache'])
        ? url('evenements/' . $evt['id'])
        : url('taches', ['liste' => $evt['liste_id']]);
};
?>
<section class="jour-planning<?= $estAujourdhui ? ' jour-planning--aujourdhui' : '' ?><?php
    ?><?= $compact ? ' jour-planning--compact' : '' ?>">
  <header class="jour-planning__entete">
    <span class="jour-planning__titre">
      <?= $estAujourdhui ? e(t('planning.aujourdhui')) : e(ucfirst(date_fr($cle . ' 00:00:00', false))) ?>
    </span>
    <?php if (is_array($rythme)): ?>
      <?php $lieu = Alternance::LIEUX[$rythme['lieu']]; ?>
      <a class="rythme rythme--<?= e($rythme['lieu']) ?>" href="<?= url('alternance/rythme') ?>"
         title="<?= e($lieu['nom'] . ($rythme['note'] ? ' · ' . $rythme['note'] : '')) ?>">
        <span aria-hidden="true"><?= $lieu['icone'] ?></span><span class="rythme__nom"> <?= e($lieu['nom']) ?></span>
      </a>
    <?php endif; ?>
    <a class="discret" href="<?= url('evenements/nouveau', ['date' => $cle]) ?>" data-fenetre><?= e(t('planning.ajouter')) ?></a>
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
          <a class="evt<?= empty($evt['termine']) ? '' : ' evt--termine' ?><?php
              ?><?= empty($evt['est_tache']) ? '' : ' evt--tache' ?>"
             href="<?= $ou($evt) ?>"
             <?= empty($evt['est_tache']) ? 'data-fenetre' : '' ?>
             style="background:color-mix(in srgb, <?= e(couleur_evenement($evt)) ?> 16%, transparent);
                    border-left-color:<?= e(couleur_evenement($evt)) ?>;color:inherit"
             title="<?= e(libelle_type($evt) . ' · ' . $evt['titre']) ?>">
            <?= e(icone_evenement($evt)) ?> <?= e($evt['titre']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="jour-planning__grille"
       style="--heures:<?= (int) ($planning['fin'] - $planning['debut']) ?>">
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
        <p class="jour-planning__vide discret"><?= e(t('planning.rien')) ?></p>
      <?php endif; ?>

      <?php foreach ($planning['blocs'] as $bloc): ?>
        <?php
        $evt = $bloc['evt'];
        $couleur = couleur_evenement($evt);
        $largeur = 100 / $bloc['colonnes'];
        ?>
        <a class="jour-planning__evt<?= $evt['termine'] ? ' jour-planning__evt--termine' : ''
           ?><?= $bloc['court'] ? ' jour-planning__evt--court' : '' ?>"
           href="<?= $ou($evt) ?>"
           <?= empty($evt['est_tache']) ? 'data-fenetre' : '' ?>
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
