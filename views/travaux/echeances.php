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

/** Les champs d'une échéance, vides ou remplis. */
$champs = static function (string $suffixe, ?array $e): string {
    $journee = $e !== null && (int) $e['journee_entiere'] === 1;
    $duree = $e === null || $journee ? 60
        : (int) round((strtotime((string) $e['fin']) - strtotime((string) $e['debut'])) / 60);
    ob_start(); ?>
    <div class="champ">
      <label for="nature-<?= $suffixe ?>">C’est</label>
      <select id="nature-<?= $suffixe ?>" name="nature">
        <?php foreach (Travaux::NATURES as $cle => $n): ?>
          <option value="<?= e($cle) ?>"<?= ($e['nature'] ?? 'rendu') === $cle ? ' selected' : '' ?>><?= $n['icone'] ?> <?= e($n['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="champ">
      <label for="titre-<?= $suffixe ?>">Titre <span class="discret">(facultatif)</span></label>
      <input type="text" id="titre-<?= $suffixe ?>" name="titre" maxlength="160" value="<?= e((string) ($e['titre'] ?? '')) ?>"
             placeholder="Rendu du dossier final">
    </div>
    <div class="ligne-champs">
      <div class="champ">
        <label for="jour-<?= $suffixe ?>">Jour</label>
        <input type="date" id="jour-<?= $suffixe ?>" name="jour" required value="<?= e($e === null ? '' : substr((string) $e['debut'], 0, 10)) ?>">
      </div>
      <div class="champ">
        <label for="heure-<?= $suffixe ?>">Heure <span class="discret">(vide : toute la journée)</span></label>
        <input type="time" id="heure-<?= $suffixe ?>" name="heure" value="<?= e($e === null || $journee ? '' : substr((string) $e['debut'], 11, 5)) ?>">
      </div>
    </div>
    <div class="ligne-champs">
      <div class="champ">
        <label for="duree-<?= $suffixe ?>">Durée (min)</label>
        <input type="number" id="duree-<?= $suffixe ?>" name="duree" min="15" max="600" step="15" value="<?= $duree ?>">
      </div>
      <div class="champ">
        <label for="lieu-<?= $suffixe ?>">Lieu <span class="discret">(facultatif)</span></label>
        <input type="text" id="lieu-<?= $suffixe ?>" name="lieu" maxlength="160" value="<?= e((string) ($e['lieu'] ?? '')) ?>">
      </div>
    </div>
    <?php return (string) ob_get_clean();
};

/** Une échéance de la liste, avec de quoi la modifier. */
$ligne = static function (array $e) use ($csrf, $champs): string {
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
            <?= $champs((string) (int) $e['id'], $e) ?>
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

<div class="colonnes">
  <div class="pile">
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

  <div class="pile">
    <section class="carte">
      <h2>Nouvelle échéance</h2>
      <form method="post" action="<?= url('travaux/' . (int) $projet['id'] . '/echeances') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <?= $champs('nouvelle', null) ?>
        <button class="bouton bouton--bloc" type="submit">Poser dans le calendrier du groupe</button>
        <p class="champ__aide">Chaque membre la retrouve dans son calendrier, avec ses rappels
          (rendu : 2 jours et la veille ; soutenance : la veille et 1 h avant ; réunion : 1 h et 15 min avant).</p>
      </form>
    </section>
  </div>
</div>
