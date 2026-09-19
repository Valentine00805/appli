<?php
/**
 * Le rythme école / entreprise, posé à la main, période par période.
 *
 * @var array $periodes
 * @var array $bilan
 * @var string $onglet
 * @var array|null $situation
 */
$csrf = Session::jetonCsrf();
$auj = date('Y-m-d');
$aVenir = array_values(array_filter($periodes, static fn (array $p): bool => $p['fin'] >= $auj));
$passees = array_reverse(array_values(array_filter($periodes, static fn (array $p): bool => $p['fin'] < $auj)));

/** Les deux boutons École / Entreprise d'un formulaire. */
$choixLieu = static function (string $prefixe, string $coche): string {
    $html = '<div class="alternance-lieux" role="radiogroup" aria-label="Lieu">';
    foreach (Alternance::LIEUX as $cle => $l) {
        $id = $prefixe . '-' . $cle;
        $html .= '<input type="radio" id="' . e($id) . '" name="lieu" value="' . e($cle) . '"'
            . ($cle === $coche ? ' checked' : '') . ' required>'
            . '<label for="' . e($id) . '" class="alternance-lieux__' . e($cle) . '">'
            . $l['icone'] . ' ' . e($l['nom']) . '</label>';
    }
    return $html . '</div>';
};

$ligne = static function (array $p) use ($csrf, $choixLieu, $auj): void {
    $l = Alternance::LIEUX[$p['lieu']];
    $jours = Alternance::joursOuvres($p['debut'], $p['fin']);
    $enCours = $p['debut'] <= $auj && $p['fin'] >= $auj;
    ?>
    <li class="alternance-periode alternance-periode--<?= e($p['lieu']) ?><?= $enCours ? ' alternance-periode--en-cours' : '' ?>">
      <span class="alternance-periode__lieu"><?= $l['icone'] ?> <?= e($l['nom']) ?></span>
      <span class="alternance-periode__dates">
        <?= $p['debut'] === $p['fin']
            ? e(Alternance::jourCourt($p['debut']))
            : e(Alternance::jourCourt($p['debut'])) . ' → ' . e(Alternance::jourCourt($p['fin'])) ?>
        <span class="discret">· <?= $jours ?> jour<?= $jours > 1 ? 's' : '' ?><?= $enCours ? ' · en cours' : '' ?></span>
        <?php if ($p['note']): ?><br><span class="discret"><?= e($p['note']) ?></span><?php endif; ?>
      </span>
      <span class="alternance-periode__actions">
        <details class="alternance-periode__modifier">
          <summary class="bouton bouton--discret bouton--petit">Modifier</summary>
          <form method="post" action="<?= url('alternance/rythme/' . (int) $p['id']) ?>" class="alternance-periode__formulaire" data-periode>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <?= $choixLieu('p' . (int) $p['id'], (string) $p['lieu']) ?>
            <div class="ligne-champs">
              <div class="champ">
                <label for="p<?= (int) $p['id'] ?>-debut">Du</label>
                <input type="date" id="p<?= (int) $p['id'] ?>-debut" name="debut" required value="<?= e($p['debut']) ?>">
              </div>
              <div class="champ">
                <label for="p<?= (int) $p['id'] ?>-fin">Au</label>
                <input type="date" id="p<?= (int) $p['id'] ?>-fin" name="fin" required value="<?= e($p['fin']) ?>">
              </div>
            </div>
            <div class="champ">
              <label for="p<?= (int) $p['id'] ?>-note">Précision <span class="discret">(facultatif)</span></label>
              <input type="text" id="p<?= (int) $p['id'] ?>-note" name="note" maxlength="200" value="<?= e((string) $p['note']) ?>">
            </div>
            <button class="bouton bouton--petit" type="submit">Enregistrer</button>
          </form>
        </details>
        <form method="post" action="<?= url('alternance/rythme/' . (int) $p['id'] . '/supprimer') ?>" class="en-ligne"
              data-confirmation="Retirer cette période ?">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--discret bouton--petit" type="submit" title="Retirer" aria-label="Retirer cette période">✕</button>
        </form>
      </span>
    </li>
    <?php
};
?>
<?= Vue::rendre('alternance/_onglets', ['onglet' => $onglet, 'situation' => $situation]) ?>

<div class="entete-page">
  <div>
    <h1>🔁 Rythme école / entreprise</h1>
    <p>Posez vos périodes telles que l’école les donne. Elles s’affichent dans le
      <a href="<?= url('calendrier') ?>">calendrier</a>, du lundi au vendredi.</p>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <?php if ($periodes === []): ?>
      <div class="vide">
        <span class="vide__icone">🔁</span>
        <p>Aucune période pour l’instant. Posez la première avec « Poser une période » : du … au …, à l’école ou en entreprise.</p>
      </div>
    <?php else: ?>
      <section class="carte">
        <h2>À venir <span class="discret">(<?= count($aVenir) ?>)</span></h2>
        <?php if ($aVenir === []): ?>
          <p class="discret">Rien de posé pour la suite.</p>
        <?php else: ?>
          <ul class="alternance-periodes">
            <?php foreach ($aVenir as $p) { $ligne($p); } ?>
          </ul>
        <?php endif; ?>
      </section>
      <?php if ($passees !== []): ?>
        <details class="carte">
          <summary><h2 style="display:inline">Passées <span class="discret">(<?= count($passees) ?>)</span></h2></summary>
          <ul class="alternance-periodes" style="margin-top:.8rem">
            <?php foreach ($passees as $p) { $ligne($p); } ?>
          </ul>
        </details>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="pile">
    <section class="carte">
      <h2>Poser une période</h2>
      <form method="post" action="<?= url('alternance/rythme') ?>" data-periode>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <?= $choixLieu('nouvelle', 'ecole') ?>
        <div class="ligne-champs">
          <div class="champ">
            <label for="nouvelle-debut">Du</label>
            <input type="date" id="nouvelle-debut" name="debut" required>
          </div>
          <div class="champ">
            <label for="nouvelle-fin">Au</label>
            <input type="date" id="nouvelle-fin" name="fin" required>
          </div>
        </div>
        <div class="champ">
          <label for="nouvelle-note">Précision <span class="discret">(facultatif)</span></label>
          <input type="text" id="nouvelle-note" name="note" maxlength="200" placeholder="Semaine d’examens, télétravail…">
        </div>
        <button class="bouton bouton--bloc" type="submit">Ajouter</button>
        <p class="champ__aide" style="margin-top:.6rem">Une période posée sur des jours déjà prévus les remplace :
          pour une journée d’école au milieu d’une semaine en entreprise, posez-la simplement par-dessus.</p>
      </form>
    </section>

    <?php if ($periodes !== []): ?>
      <section class="carte">
        <h2>Bilan</h2>
        <ul class="alternance-bilan">
          <?php foreach (Alternance::LIEUX as $cle => $l): ?>
            <li class="alternance-bilan__<?= e($cle) ?>">
              <span><?= $l['icone'] ?> <?= e($l['nom']) ?></span>
              <strong><?= (int) $bilan[$cle]['total'] ?> j</strong>
              <span class="discret">dont <?= (int) $bilan[$cle]['a_venir'] ?> à venir</span>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="champ__aide">Jours du lundi au vendredi.</p>
      </section>
    <?php endif; ?>
  </div>
</div>
