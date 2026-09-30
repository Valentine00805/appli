<?php
/**
 * Le rythme école / entreprise, posé à la main, période par période.
 *
 * @var array $periodes
 * @var array $bilan
 * @var string $lienIcs  le lien d’abonnement au rythme, ou une chaîne vide
 * @var string $onglet
 * @var array|null $situation
 */
$csrf = Session::jetonCsrf();
$auj = date('Y-m-d');
$aVenir = array_values(array_filter($periodes, static fn (array $p): bool => $p['fin'] >= $auj));
$passees = array_reverse(array_values(array_filter($periodes, static fn (array $p): bool => $p['fin'] < $auj)));

/** Les lieux possibles, en boutons, dans un formulaire. */
$choixLieu = static function (string $prefixe, string $coche): string {
    $html = '<div class="alternance-lieux" role="radiogroup" aria-label="' . e(t('alt.ry.lieu')) . '">';
    foreach (Alternance::lieux() as $cle => $l) {
        $id = $prefixe . '-' . $cle;
        $html .= '<input type="radio" id="' . e($id) . '" name="lieu" value="' . e($cle) . '"'
            . ($cle === $coche ? ' checked' : '') . ' required>'
            . '<label for="' . e($id) . '" class="alternance-lieux__' . e($cle) . '">'
            . $l['icone'] . ' ' . e($l['nom']) . '</label>';
    }
    return $html . '</div>';
};

$ligne = static function (array $p) use ($csrf, $choixLieu, $auj): void {
    $l = Alternance::lieux()[$p['lieu']];
    $jours = Alternance::joursOuvres($p['debut'], $p['fin']);
    $enCours = $p['debut'] <= $auj && $p['fin'] >= $auj;
    ?>
    <li class="alternance-periode alternance-periode--<?= e($p['lieu']) ?><?= $enCours ? ' alternance-periode--en-cours' : '' ?>">
      <span class="alternance-periode__lieu"><?= $l['icone'] ?> <?= e($l['nom']) ?></span>
      <span class="alternance-periode__dates">
        <?= $p['debut'] === $p['fin']
            ? e(Alternance::jourCourt($p['debut']))
            : e(Alternance::jourCourt($p['debut'])) . ' → ' . e(Alternance::jourCourt($p['fin'])) ?>
        <span class="discret">· <?= e(tn('alt.ry.jours', $jours)) ?><?= $enCours ? e(t('alt.ry.en_cours')) : '' ?></span>
        <?php if ($p['note']): ?><br><span class="discret"><?= e($p['note']) ?></span><?php endif; ?>
      </span>
      <span class="alternance-periode__actions">
        <details class="alternance-periode__modifier">
          <summary class="bouton bouton--discret bouton--petit"><?= e(t('alt.ry.modifier')) ?></summary>
          <form method="post" action="<?= url('alternance/rythme/' . (int) $p['id']) ?>" class="alternance-periode__formulaire" data-periode>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <?= $choixLieu('p' . (int) $p['id'], (string) $p['lieu']) ?>
            <div class="ligne-champs">
              <div class="champ">
                <label for="p<?= (int) $p['id'] ?>-debut"><?= e(t('alt.ry.du')) ?></label>
                <input type="date" id="p<?= (int) $p['id'] ?>-debut" name="debut" required value="<?= e($p['debut']) ?>">
              </div>
              <div class="champ">
                <label for="p<?= (int) $p['id'] ?>-fin"><?= e(t('alt.ry.au')) ?></label>
                <input type="date" id="p<?= (int) $p['id'] ?>-fin" name="fin" required value="<?= e($p['fin']) ?>">
              </div>
            </div>
            <div class="champ">
              <label for="p<?= (int) $p['id'] ?>-note"><?= e(t('alt.ry.precision')) ?> <span class="discret"><?= e(t('alt.ry.facultatif')) ?></span></label>
              <input type="text" id="p<?= (int) $p['id'] ?>-note" name="note" maxlength="200" value="<?= e((string) $p['note']) ?>">
            </div>
            <button class="bouton bouton--petit" type="submit"><?= e(t('alt.ry.enregistrer')) ?></button>
          </form>
        </details>
        <form method="post" action="<?= url('alternance/rythme/' . (int) $p['id'] . '/supprimer') ?>" class="en-ligne"
              data-confirmation="<?= e(t('alt.ry.retirer_confirmation')) ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--discret bouton--petit" type="submit" title="<?= e(t('alt.ry.retirer')) ?>" aria-label="<?= e(t('alt.ry.retirer_periode')) ?>">✕</button>
        </form>
      </span>
    </li>
    <?php
};
?>
<?= Vue::rendre('alternance/_onglets', ['onglet' => $onglet, 'situation' => $situation]) ?>

<div class="entete-page">
  <div>
    <h1><?= e(t('alt.ry.titre')) ?></h1>
    <p><?= e(t('alt.ry.aide')) ?>
      <a href="<?= url('calendrier') ?>"><?= e(t('alt.ry.calendrier')) ?></a><?= e(t('alt.ry.aide_suite')) ?></p>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <?php if ($periodes === []): ?>
      <div class="vide">
        <span class="vide__icone">🔁</span>
        <p><?= e(t('alt.ry.aucune')) ?></p>
      </div>
    <?php else: ?>
      <section class="carte">
        <h2><?= e(t('alt.ry.a_venir')) ?> <span class="discret">(<?= count($aVenir) ?>)</span></h2>
        <?php if ($aVenir === []): ?>
          <p class="discret"><?= e(t('alt.ry.rien_ensuite')) ?></p>
        <?php else: ?>
          <ul class="alternance-periodes">
            <?php foreach ($aVenir as $p) { $ligne($p); } ?>
          </ul>
        <?php endif; ?>
      </section>
      <?php if ($passees !== []): ?>
        <details class="carte">
          <summary><h2 style="display:inline"><?= e(t('alt.ry.passees')) ?> <span class="discret">(<?= count($passees) ?>)</span></h2></summary>
          <ul class="alternance-periodes" style="margin-top:.8rem">
            <?php foreach ($passees as $p) { $ligne($p); } ?>
          </ul>
        </details>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="pile">
    <section class="carte">
      <h2><?= e(t('alt.ry.poser')) ?></h2>
      <form method="post" action="<?= url('alternance/rythme') ?>" data-periode>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <?= $choixLieu('nouvelle', 'ecole') ?>
        <div class="ligne-champs">
          <div class="champ">
            <label for="nouvelle-debut"><?= e(t('alt.ry.du')) ?></label>
            <input type="date" id="nouvelle-debut" name="debut" required>
          </div>
          <div class="champ">
            <label for="nouvelle-fin"><?= e(t('alt.ry.au')) ?></label>
            <input type="date" id="nouvelle-fin" name="fin" required>
          </div>
        </div>
        <div class="champ">
          <label for="nouvelle-note"><?= e(t('alt.ry.precision')) ?> <span class="discret"><?= e(t('alt.ry.facultatif')) ?></span></label>
          <input type="text" id="nouvelle-note" name="note" maxlength="200" placeholder="<?= e(t('alt.ry.precision_exemple')) ?>">
        </div>
        <button class="bouton bouton--bloc" type="submit"><?= e(t('alt.ry.ajouter')) ?></button>
        <p class="champ__aide" style="margin-top:.6rem"><?= e(t('alt.ry.remplace')) ?></p>
      </form>
    </section>

    <details class="carte">
      <summary class="alternance-export__ouvrir"><?= e(t('alt.ry.importer_titre')) ?></summary>
      <form method="post" action="<?= url('alternance/rythme/import') ?>" enctype="multipart/form-data" style="margin-top:.8rem">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="champ">
          <label for="planning"><?= e(t('alt.ry.fichier_ecole')) ?></label>
          <input type="file" id="planning" name="planning"
                 accept=".ics,.csv,.txt,.pdf,text/calendar,text/csv,text/plain,application/pdf">
          <span class="champ__aide"><?= e(t('alt.ry.fichier_aide')) ?></span>
        </div>
        <div class="champ">
          <label for="annee"><?= e(t('alt.ry.annee')) ?> <span class="discret"><?= e(t('alt.ry.facultatif')) ?></span></label>
          <input type="number" id="annee" name="annee" min="2000" max="2100" step="1"
                 placeholder="<?= (int) date('Y') ?>">
          <span class="champ__aide"><?= e(t('alt.ry.annee_aide')) ?></span>
        </div>
        <div class="champ">
          <label for="colle"><?= e(t('alt.ry.coller')) ?></label>
          <textarea id="colle" name="colle" rows="4"
                    placeholder="<?= e(t('alt.ry.coller_exemple')) ?>"></textarea>
          <span class="champ__aide"><?= e(t('alt.ry.coller_aide')) ?></span>
        </div>
        <div class="champ">
          <label for="lieu_defaut"><?= e(t('alt.ry.lieu_defaut')) ?></label>
          <select id="lieu_defaut" name="lieu_defaut">
            <?php foreach (Alternance::lieux() as $cle => $l): ?>
              <option value="<?= e($cle) ?>"<?= $cle === 'entreprise' ? ' selected' : '' ?>><?= $l['icone'] ?> <?= e($l['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="bouton bouton--bloc" type="submit"><?= e(t('alt.ry.importer')) ?></button>
        <p class="champ__aide" style="margin-top:.6rem"><?= e(t('alt.ry.import_remplace')) ?></p>
      </form>
    </details>

    <section class="carte">
      <h2><?= e(t('alt.ry.autre_agenda')) ?></h2>
      <?php if ($lienIcs === ''): ?>
        <p class="discret" style="margin-top:0"><?= e(t('alt.ry.abonnement_aide')) ?></p>
        <form method="post" action="<?= url('alternance/rythme/lien') ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--secondaire bouton--bloc" type="submit"><?= e(t('alt.ry.creer_lien')) ?></button>
        </form>
      <?php else: ?>
        <div class="partage-lien" data-partage-lien>
          <label class="sr-only" for="lien-alternance"><?= e(t('alt.ry.lien_abonnement')) ?></label>
          <input type="text" id="lien-alternance" readonly value="<?= e($lienIcs) ?>" data-partage-adresse>
          <button class="bouton" type="button" data-partage-copier><?= e(t('alt.ry.copier')) ?></button>
        </div>
        <p class="champ__aide" data-partage-etat aria-live="polite">
          <?= e(t('alt.ry.lien_aide')) ?>
        </p>
        <form method="post" action="<?= url('alternance/rythme/lien') ?>"
              data-confirmation="<?= e(t('alt.ry.renouveler_confirmation')) ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="renouveler" value="1">
          <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('alt.ry.renouveler')) ?></button>
        </form>
      <?php endif; ?>
    </section>

    <?php if ($periodes !== []): ?>
      <section class="carte">
        <h2><?= e(t('alt.ry.bilan')) ?></h2>
        <ul class="alternance-bilan">
          <?php foreach (Alternance::lieux() as $cle => $l): ?>
            <?php // Les congés, fériés et absences ne s'affichent que s'il y en a. ?>
            <?php if ($bilan[$cle]['total'] === 0 && !in_array($cle, ['ecole', 'entreprise'], true)) { continue; } ?>
            <li class="alternance-bilan__<?= e($cle) ?>">
              <span><?= $l['icone'] ?> <?= e($l['nom']) ?></span>
              <strong><?= e(t('alt.ry.jours_court', ['n' => (int) $bilan[$cle]['total']])) ?></strong>
              <span class="discret"><?= e(t('alt.ry.dont_a_venir', ['n' => (int) $bilan[$cle]['a_venir']])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="champ__aide"><?= e(t('alt.ry.jours_ouvres')) ?></p>
      </section>
    <?php endif; ?>
  </div>
</div>
