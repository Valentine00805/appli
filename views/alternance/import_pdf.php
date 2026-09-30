<?php
/**
 * Un planning PDF relu : à chaque couleur trouvée, dire ce qu'elle veut dire.
 *
 * L'application ne devine pas : un rouge peut être l'école ici et les congés
 * ailleurs. Elle montre donc ce qu'elle a lu — la couleur, le nombre de jours,
 * les premiers d'entre eux — et attend la légende.
 *
 * @var string $nomFichier
 * @var array<string, string> $jours     date => couleur
 * @var array<string, int> $couleurs     couleur => nombre de jours
 * @var string $methode                  « dates » ou « grille »
 * @var string $defaut                   le lieu choisi à l’envoi
 * @var string $onglet
 * @var array|null $situation
 */
$dates = array_keys($jours);
sort($dates);
$parCouleur = [];
foreach ($jours as $jour => $couleur) {
    $parCouleur[$couleur][] = $jour;
}
foreach ($parCouleur as &$liste) {
    sort($liste);
}
unset($liste);
?>
<?= Vue::rendre('alternance/_onglets', ['onglet' => $onglet, 'situation' => $situation]) ?>

<div class="entete-page">
  <div>
    <p style="margin:0 0 .3rem"><a href="<?= url('alternance/rythme') ?>"><?= e(t('alt.ip.retour')) ?></a></p>
    <h1><?= e(t('alt.ip.titre', ['nom' => $nomFichier])) ?></h1>
    <p>
      <?= e(tn('alt.ip.lus', count($dates))) ?>
      <?= e(t('alt.ip.du_au', [
          'debut' => Alternance::jourCourt($dates[0]),
          'fin' => Alternance::jourCourt($dates[count($dates) - 1]),
      ])) ?>
      <span class="discret">· <?= e(t($methode === 'dates' ? 'alt.ip.methode_dates' : 'alt.ip.methode_grille')) ?></span>.
      <?= e(t('alt.ip.dites')) ?>
    </p>
  </div>
</div>

<form method="post" action="<?= url('alternance/rythme/couleurs') ?>">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <input type="hidden" name="jours" value="<?= e((string) json_encode($jours, JSON_UNESCAPED_SLASHES)) ?>">

  <section class="carte">
    <ul class="alternance-legende">
      <?php $numero = 0; $laPlusVue = $couleurs === [] ? 0 : max($couleurs); ?>
      <?php foreach ($couleurs as $rang => $combien): ?>
        <?php
        $couleur = (string) $rang;
        $exemples = array_slice($parCouleur[$couleur] ?? [], 0, 3);
        $numero++;
        ?>
        <li class="alternance-legende__ligne">
          <input type="hidden" name="couleurs[]" value="<?= e($couleur) ?>">
          <span class="alternance-legende__pastille"
                style="background:<?= $couleur === 'sans' ? 'transparent' : e($couleur) ?>"
                title="<?= $couleur === 'sans' ? e(t('alt.ip.sans_fond')) : e($couleur) ?>" aria-hidden="true">
            <?= $couleur === 'sans' ? '—' : '' ?>
          </span>
          <span class="alternance-legende__texte">
            <strong><?= $couleur === 'sans' ? e(t('alt.ip.sans_couleur')) : e(strtoupper($couleur)) ?></strong><br>
            <span class="discret">
              <?= e(tn('alt.ip.jours', (int) $combien)) ?>
              <?php foreach ($exemples as $rangExemple => $jour): ?>
                <?= $rangExemple > 0 ? ', ' : '' ?><?= e(Alternance::jourCourt($jour)) ?>
              <?php endforeach; ?>
              <?= $combien > 3 ? '…' : '' ?>
            </span>
          </span>
          <span class="alternance-legende__choix">
            <label class="sr-only" for="lieu-<?= $numero ?>">
              <?= e(t('alt.ip.ce_que_veut_dire', ['couleur' => $couleur])) ?>
            </label>
            <select id="lieu-<?= $numero ?>" name="lieux[]">
              <option value=""><?= e(t('alt.ip.ne_pas_importer')) ?></option>
              <?php foreach (Alternance::lieux() as $cle => $l): ?>
                <option value="<?= e($cle) ?>"<?= $cle === $defaut && $combien === $laPlusVue ? ' selected' : '' ?>>
                  <?= $l['icone'] ?> <?= e($l['nom']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>

    <p class="champ__aide" style="margin-top:.9rem">
      <?= e(t('alt.ip.aide')) ?>
    </p>

    <p class="actions">
      <button class="bouton" type="submit"><?= e(t('alt.ip.importer')) ?></button>
      <a class="bouton bouton--secondaire" href="<?= url('alternance/rythme') ?>"><?= e(t('alt.nt.annuler')) ?></a>
    </p>
  </section>
</form>
