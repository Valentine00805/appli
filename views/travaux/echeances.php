<?php
/**
 * Les échéances du projet : chacune a sa copie dans le calendrier de chaque
 * membre, avec ses rappels.
 *
 * @var array $projet
 * @var list<array> $echeances
 * @var list<array> $types  les types d'échéance du projet
 * @var ?array $calendrier  le calendrier commun du projet, s'il en a un
 * @var list<array> $evenementsCalendrier  ses prochains évènements
 * @var string $onglet
 */
$dansUneFenetre = $dansUneFenetre ?? false;
// Dans la fenêtre, on y reste : les formulaires s'y enregistrent.
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$csrf = Session::jetonCsrf();
$maintenant = date('Y-m-d H:i:s');
$avenir = array_filter($echeances, static fn (array $e): bool => (string) $e['fin'] >= $maintenant);
$passees = array_reverse(array_filter($echeances, static fn (array $e): bool => (string) $e['fin'] < $maintenant));

/** Une échéance de la liste, avec de quoi la modifier. */
$ligne = static function (array $e) use ($csrf, $envoi, $types): string {
    $journee = (int) $e['journee_entiere'] === 1;
    ob_start(); ?>
    <li class="travaux-echeance">
      <span class="travaux-echeance__icone" aria-hidden="true"><?= e((string) ($e['type_icone'] ?? Travaux::ICONE_SANS_TYPE)) ?></span>
      <span style="min-width:0;flex:1">
        <strong><?= e((string) $e['titre']) ?></strong>
        <?php if ($e['type_nom'] !== null): ?>
          <span class="travaux-type" style="--couleur-type:<?= e((string) $e['type_couleur']) ?>"><?= e((string) $e['type_nom']) ?></span>
        <?php endif; ?><br>
        <?= e(ucfirst(date_fr((string) $e['debut'], !$journee))) ?><?= $journee ? e(t('tr.ec.toute_la_journee')) : '' ?>
        <?php if ((string) ($e['lieu'] ?? '') !== ''): ?><span class="discret">· <?= e((string) $e['lieu']) ?></span><?php endif; ?>
        <br><a class="bouton bouton--discret bouton--petit" href="<?= url('travaux/echeances/' . (int) $e['id'] . '/modifier') ?>"
               data-fenetre-dessus data-relire-derriere style="margin-top:.3rem"><?= e(t('tr.ec.modifier')) ?></a>
      </span>
    </li>
    <?php return (string) ob_get_clean();
};
?>
<?= Vue::rendre('travaux/_onglets', ['projet' => $projet, 'onglet' => $onglet, 'dansUneFenetre' => $dansUneFenetre]) ?>

<p class="actions" style="margin:0 0 1rem">
  <a class="bouton bouton--petit" href="<?= url('travaux/' . (int) $projet['id'] . '/echeances/nouvelle') ?>" data-fenetre><?= e(t('tr.ec.nouvelle')) ?></a>
  <a class="bouton bouton--secondaire bouton--petit" href="<?= url('travaux/' . (int) $projet['id'] . '/types') ?>" data-fenetre-dessus data-relire-derriere><?= e(t('tr.ty.titre')) ?></a>
</p>

<div class="pile" style="max-width:48rem">
  <section class="carte">
    <h2><?= e(t('tr.ec.a_venir')) ?></h2>
    <?php if ($avenir === []): ?>
      <p class="discret"><?= e(t('tr.ec.aucune')) ?></p>
    <?php else: ?>
      <ul class="travaux-echeances"><?php foreach ($avenir as $e) { echo $ligne($e); } ?></ul>
    <?php endif; ?>
  </section>
  <section class="carte">
    <h2>📅 <?= e(t('cam.cal_commun')) ?></h2>
    <?php if ($calendrier === null): ?>
      <p class="discret"><?= e(t('cam.cal_commun_aucun')) ?></p>
      <a class="bouton bouton--secondaire bouton--petit" href="<?= url('travaux/' . (int) $projet['id'] . '/membres') ?>" <?= $dansUneFenetre ? 'data-fenetre' : '' ?>><?= e(t('cam.projet_creer')) ?></a>
    <?php else: ?>
      <?php if ($evenementsCalendrier === []): ?>
        <p class="discret"><?= e(t('cam.rien_a_venir')) ?></p>
      <?php else: ?>
        <ul class="cam-evenements">
          <?php foreach ($evenementsCalendrier as $evt): ?>
            <?php
            $debut = strtotime((string) $evt['debut']);
            $quand = date(t('date.jour_mois'), $debut) . ((int) $evt['journee_entiere'] === 1 ? '' : ' · ' . date('H:i', $debut));
            ?>
            <li>
              <a href="<?= url('calendriers-amis/evenements/' . (int) $evt['id']) ?>" data-fenetre>
                <span class="cam-evenements__quand"><?= e($quand) ?></span>
                <span><?= e((string) $evt['titre']) ?>
                  <?php if ((int) $evt['nb_liens'] > 0): ?><span class="discret">· 🔗 <?= e(tn('cam.docs_n', (int) $evt['nb_liens'])) ?></span><?php endif; ?>
                </span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <div class="actions" style="margin-top:.6rem">
        <a class="bouton bouton--petit" href="<?= url('evenements/nouveau', ['agenda' => CalendriersAmis::cle((int) $calendrier['id'])]) ?>" data-fenetre>＋ <?= e(t('cam.evt_ajouter')) ?></a>
        <a class="bouton bouton--discret bouton--petit" href="<?= url('calendriers-amis/' . (int) $calendrier['id']) ?>" data-fenetre><?= e(t('cam.ouvrir')) ?></a>
      </div>
    <?php endif; ?>
  </section>
  <?php if ($passees !== []): ?>
    <details class="carte">
      <summary><strong><?= e(t('tr.ec.passees')) ?></strong> <span class="discret">(<?= count($passees) ?>)</span></summary>
      <ul class="travaux-echeances"><?php foreach ($passees as $e) { echo $ligne($e); } ?></ul>
    </details>
  <?php endif; ?>
</div>
