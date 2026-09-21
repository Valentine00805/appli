<?php
/**
 * Les échéances du projet : chacune a sa copie dans le calendrier de chaque
 * membre, avec ses rappels.
 *
 * @var array $projet
 * @var list<array> $echeances
 * @var list<array> $types  les types d'échéance du projet
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
        <?= e(ucfirst(date_fr((string) $e['debut'], !$journee))) ?><?= $journee ? ' — toute la journée' : '' ?>
        <?php if ((string) ($e['lieu'] ?? '') !== ''): ?><span class="discret">· <?= e((string) $e['lieu']) ?></span><?php endif; ?>
        <br><a class="bouton bouton--discret bouton--petit" href="<?= url('travaux/echeances/' . (int) $e['id'] . '/modifier') ?>"
               data-fenetre-dessus data-relire-derriere style="margin-top:.3rem">✎ Modifier</a>
      </span>
    </li>
    <?php return (string) ob_get_clean();
};
?>
<?= Vue::rendre('travaux/_onglets', ['projet' => $projet, 'onglet' => $onglet, 'dansUneFenetre' => $dansUneFenetre]) ?>

<p class="actions" style="margin:0 0 1rem">
  <a class="bouton bouton--petit" href="<?= url('travaux/' . (int) $projet['id'] . '/echeances/nouvelle') ?>" data-fenetre>+ Nouvelle échéance</a>
  <a class="bouton bouton--secondaire bouton--petit" href="<?= url('travaux/' . (int) $projet['id'] . '/types') ?>" data-fenetre-dessus data-relire-derriere>🏷️ Types d’échéance</a>
</p>

<div class="pile" style="max-width:48rem">
  <section class="carte">
    <h2>À venir</h2>
    <?php if ($avenir === []): ?>
      <p class="discret">Aucune échéance à venir. Posez la date de rendu : elle arrivera dans le calendrier de chaque membre.</p>
    <?php else: ?>
      <ul class="travaux-echeances"><?php foreach ($avenir as $e) { echo $ligne($e); } ?></ul>
    <?php endif; ?>
  </section>
  <?php if ($passees !== []): ?>
    <details class="carte">
      <summary><strong>Passées</strong> <span class="discret">(<?= count($passees) ?>)</span></summary>
      <ul class="travaux-echeances"><?php foreach ($passees as $e) { echo $ligne($e); } ?></ul>
    </details>
  <?php endif; ?>
</div>
