<?php
/**
 * La fiche de l'alternance : l'entreprise, le tuteur, les dates du contrat.
 *
 * @var array $contrat
 * @var array|null $avancement  ce que rend Alternance::avancementContrat()
 * @var array<string, bool> $auCalendrier  les dates déjà posées au calendrier
 * @var list<array> $taches  ce qui reste à faire dans la liste « Alternance »
 * @var string $onglet
 * @var array|null $situation
 */
$csrf = Session::jetonCsrf();
$v = static fn (string $champ): string => (string) ($contrat[$champ] ?? '');
$aDesDates = false;
foreach (Alternance::ECHEANCES as $champ) {
    $aDesDates = $aDesDates || $v($champ) !== '';
}
?>
<?= Vue::rendre('alternance/_onglets', ['onglet' => $onglet, 'situation' => $situation]) ?>

<div class="entete-page">
  <div>
    <h1><?= e(t('alt.en.titre')) ?></h1>
    <p><?= e(t('alt.en.aide')) ?></p>
  </div>
</div>

<div class="colonnes">
  <form method="post" action="<?= url('alternance/entreprise') ?>" class="pile">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <section class="carte">
      <h2><?= e(t('alt.en.entreprise')) ?></h2>
      <div class="champ">
        <label for="entreprise"><?= e(t('alt.en.nom')) ?></label>
        <input type="text" id="entreprise" name="entreprise" maxlength="150" value="<?= e($v('entreprise')) ?>">
      </div>
      <div class="champ">
        <label for="poste"><?= e(t('alt.en.poste')) ?></label>
        <input type="text" id="poste" name="poste" maxlength="150"
               placeholder="<?= e(t('alt.en.poste_exemple')) ?>" value="<?= e($v('poste')) ?>">
      </div>
      <div class="champ">
        <label for="adresse"><?= e(t('alt.en.adresse')) ?></label>
        <input type="text" id="adresse" name="adresse" maxlength="255" value="<?= e($v('adresse')) ?>">
      </div>
    </section>

    <section class="carte">
      <h2><?= e(t('alt.en.tuteur_titre')) ?></h2>
      <div class="champ">
        <label for="tuteur"><?= e(t('alt.en.tuteur')) ?></label>
        <input type="text" id="tuteur" name="tuteur" maxlength="120" value="<?= e($v('tuteur')) ?>">
      </div>
      <div class="ligne-champs">
        <div class="champ">
          <label for="tuteur_email"><?= e(t('alt.en.courriel')) ?></label>
          <input type="email" id="tuteur_email" name="tuteur_email" maxlength="190" value="<?= e($v('tuteur_email')) ?>">
        </div>
        <div class="champ">
          <label for="tuteur_tel"><?= e(t('alt.en.telephone')) ?></label>
          <input type="tel" id="tuteur_tel" name="tuteur_tel" maxlength="40" value="<?= e($v('tuteur_tel')) ?>">
        </div>
      </div>
      <div class="champ">
        <label for="referent"><?= e(t('alt.en.referent')) ?></label>
        <input type="text" id="referent" name="referent" maxlength="120" value="<?= e($v('referent')) ?>">
      </div>
    </section>

    <section class="carte">
      <h2><?= e(t('alt.en.dates')) ?></h2>
      <div class="ligne-champs">
        <div class="champ">
          <label for="debut"><?= e(t('alt.ech.debut.libelle')) ?></label>
          <input type="date" id="debut" name="debut" value="<?= e($v('debut')) ?>">
        </div>
        <div class="champ">
          <label for="fin"><?= e(t('alt.ech.fin.libelle')) ?></label>
          <input type="date" id="fin" name="fin" value="<?= e($v('fin')) ?>">
        </div>
      </div>
      <div class="ligne-champs">
        <div class="champ">
          <label for="remise_rapport"><?= e(t('alt.ech.remise_rapport.libelle')) ?></label>
          <input type="date" id="remise_rapport" name="remise_rapport" value="<?= e($v('remise_rapport')) ?>">
        </div>
        <div class="champ">
          <label for="soutenance"><?= e(t('alt.ech.soutenance.libelle')) ?></label>
          <input type="date" id="soutenance" name="soutenance" value="<?= e($v('soutenance')) ?>">
        </div>
      </div>
      <p class="actions">
        <button class="bouton" type="submit"><?= e(t('alt.en.enregistrer')) ?></button>
      </p>
    </section>
  </form>

  <div class="pile">
    <?php if ($avancement !== null): ?>
      <section class="carte">
        <h2><?= e(t('alt.en.ou_jen_suis')) ?></h2>
        <p class="alternance-avancement__chiffre">
          <strong><?= e(t('alt.en.part', ['n' => (int) $avancement['part']])) ?></strong> <?= e(t('alt.en.du_contrat')) ?>
        </p>
        <div class="alternance-avancement" role="img"
             aria-label="<?= e(t('alt.en.part_ecoulee', ['n' => (int) $avancement['part']])) ?>">
          <span style="width:<?= (int) $avancement['part'] ?>%"></span>
        </div>
        <p class="discret" style="margin:.5rem 0 0">
          <?= e(tn('alt.en.jours_sur', (int) $avancement['faits'], ['total' => (int) $avancement['total']])) ?> ·
          <?= e(tn('alt.en.restants', max(0, (int) $avancement['total'] - (int) $avancement['faits']))) ?>
        </p>
      </section>
    <?php endif; ?>

    <?php if ($taches !== []): ?>
      <?php // Ce qui reste à faire dans la liste « Alternance », échéances d'abord. ?>
      <section class="carte">
        <h2><?= e(t('alt.en.a_faire')) ?></h2>
        <ul class="alternance-echeances">
          <?php foreach ($taches as $t): ?>
            <li>
              <span><?= e((string) $t['titre']) ?></span>
              <?php if ($t['echeance'] !== null): ?>
                <span class="echeance echeance--<?= e(echeance_etat((string) $t['echeance'])) ?>">
                  <?= e(echeance_libelle((string) $t['echeance'])) ?>
                </span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="champ__aide" style="margin-top:.6rem">
          <a href="<?= url('taches') ?>"><?= e(t('alt.en.ouvrir_listes')) ?></a> <?= e(t('alt.en.pour_cocher')) ?>
        </p>
      </section>
    <?php endif; ?>

    <?php if ($aDesDates): ?>
      <section class="carte">
        <h2><?= e(t('alt.en.dates_cles')) ?></h2>
        <ul class="alternance-echeances">
          <?php foreach (Alternance::echeances() as $champ => $echeance): ?>
            <?php if ($v($champ) === '') { continue; } ?>
            <li>
              <span><?= e($echeance['libelle']) ?></span>
              <strong><?= e(Alternance::jourCourt($v($champ))) ?></strong>
              <?php if (!empty($auCalendrier[$champ])): ?>
                <span class="pastille" title="<?= e(t('alt.en.deja_calendrier')) ?>"><?= e(t('alt.en.posee')) ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <form method="post" action="<?= url('alternance/entreprise/calendrier') ?>" style="margin-top:.8rem">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--secondaire bouton--bloc" type="submit"><?= e(t('alt.en.poser_dates')) ?></button>
          <p class="champ__aide" style="margin-top:.5rem"><?= e(t('alt.en.poser_aide')) ?></p>
        </form>

        <?php if ($v('remise_rapport') !== '' || $v('soutenance') !== ''): ?>
          <?php // Le rapport ne s'écrit pas la veille : on pose les étapes à l'avance. ?>
          <form method="post" action="<?= url('alternance/entreprise/retroplanning') ?>" style="margin-top:.8rem">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button class="bouton bouton--secondaire bouton--bloc" type="submit"><?= e(t('alt.en.retroplanning')) ?></button>
            <p class="champ__aide" style="margin-top:.5rem"><?= e(t('alt.en.retroplanning_aide', ['liste' => Alternance::nomDeLaListe(Auth::id())])) ?></p>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($v('tuteur_email') !== '' || $v('tuteur_tel') !== '' || $v('adresse') !== ''): ?>
      <section class="carte">
        <h2><?= e(t('alt.en.joindre')) ?></h2>
        <ul class="alternance-joindre">
          <?php if ($v('tuteur_email') !== ''): ?>
            <li>✉️ <a href="mailto:<?= e($v('tuteur_email')) ?>"><?= e($v('tuteur_email')) ?></a></li>
          <?php endif; ?>
          <?php if ($v('tuteur_tel') !== ''): ?>
            <li>📞 <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $v('tuteur_tel')) ?? '') ?>"><?= e($v('tuteur_tel')) ?></a></li>
          <?php endif; ?>
          <?php if ($v('adresse') !== ''): ?>
            <li>📍 <?= e($v('adresse')) ?></li>
          <?php endif; ?>
        </ul>
      </section>
    <?php endif; ?>
  </div>
</div>
