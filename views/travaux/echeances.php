<?php
/**
 * Les échéances du projet : chacune a sa copie dans le calendrier de chaque
 * membre, avec ses rappels.
 *
 * @var array $projet
 * @var list<array> $echeances
 * @var string $onglet
 */
$csrf = Session::jetonCsrf();
$maintenant = date('Y-m-d H:i:s');
$avenir = array_filter($echeances, static fn (array $e): bool => (string) $e['fin'] >= $maintenant);
$passees = array_reverse(array_filter($echeances, static fn (array $e): bool => (string) $e['fin'] < $maintenant));

/** Une échéance de la liste, avec de quoi la modifier. */
$ligne = static function (array $e) use ($csrf): string {
    $n = Travaux::NATURES[$e['nature']];
    $journee = (int) $e['journee_entiere'] === 1;
    ob_start(); ?>
    <li class="travaux-echeance">
      <span class="travaux-echeance__icone" aria-hidden="true"><?= $n['icone'] ?></span>
      <span style="min-width:0;flex:1">
        <strong><?= e((string) $e['titre']) ?></strong>
        <span class="discret">· <?= e($n['nom']) ?></span><br>
        <?= e(ucfirst(date_fr((string) $e['debut'], !$journee))) ?><?= $journee ? ' — toute la journée' : '' ?>
        <?php if ((string) ($e['lieu'] ?? '') !== ''): ?><span class="discret">· <?= e((string) $e['lieu']) ?></span><?php endif; ?>
        <details class="travaux-modifier">
          <summary>Modifier</summary>
          <form method="post" action="<?= url('travaux/echeances/' . (int) $e['id'] . '/modifier') ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <?= Vue::rendre('travaux/_champs_echeance', ['suffixe' => (string) (int) $e['id'], 'e' => $e]) ?>
            <button class="bouton bouton--petit" type="submit">Enregistrer</button>
          </form>
          <form method="post" action="<?= url('travaux/echeances/' . (int) $e['id'] . '/supprimer') ?>" class="en-ligne"
                data-confirmation="Retirer « <?= e((string) $e['titre']) ?> » ? Elle quittera aussi le calendrier de chaque membre.">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button class="bouton bouton--discret bouton--petit" type="submit">Supprimer</button>
          </form>
        </details>
      </span>
    </li>
    <?php return (string) ob_get_clean();
};
?>
<?= Vue::rendre('travaux/_onglets', ['projet' => $projet, 'onglet' => $onglet]) ?>

<p style="margin:0 0 1rem"><a class="bouton bouton--petit" href="<?= url('travaux/' . (int) $projet['id'] . '/echeances/nouvelle') ?>" data-fenetre>+ Nouvelle échéance</a></p>

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
