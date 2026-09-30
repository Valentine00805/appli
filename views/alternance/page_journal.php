<?php
/**
 * La page d'une semaine du journal, à écrire ou à compléter.
 *
 * @var string $semaine  son lundi
 * @var array|null $page
 * @var array $lieux     le lieu de chaque jour de la semaine
 * @var string $onglet
 * @var array|null $situation
 * @var bool $dansUneFenetre  ouverte par « Écrire cette semaine », par-dessus le journal
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$edition = $page !== null;
$vendredi = (new DateTimeImmutable($semaine))->modify('+4 days')->format('Y-m-d');
$joursEntreprise = count(array_filter($lieux, static fn (array $l): bool => $l['lieu'] === 'entreprise'));
?>
<?php if (!$dansUneFenetre): ?>
  <?= Vue::rendre('alternance/_onglets', ['onglet' => $onglet, 'situation' => $situation]) ?>
<?php endif; ?>

<?php // Large : l'éditeur a besoin de place pour sa barre d'outils. ?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p style="margin:0 0 .3rem"><a href="<?= url('alternance/journal') ?>"><?= e(t('alt.pj.retour')) ?></a></p>
    <?php endif; ?>
    <h1><?= e(t('alt.pj.titre', [
        'debut' => Alternance::jourCourt($semaine),
        'fin' => Alternance::jourCourt($vendredi),
    ])) ?></h1>
    <?php if ($lieux !== []): ?>
      <p class="alternance-semaine">
        <?php for ($i = 0; $i < 5; $i++): ?>
          <?php
          $jour = (new DateTimeImmutable($semaine))->modify('+' . $i . ' days')->format('Y-m-d');
          $l = $lieux[$jour] ?? null;
          ?>
          <span class="alternance-semaine__jour<?= $l ? ' alternance-semaine__jour--' . e($l['lieu']) : '' ?>"
                title="<?= e(Alternance::jourCourt($jour)) ?><?= $l ? ' : ' . e(Alternance::lieuNom($l['lieu'])) : '' ?>">
            <?= e(mb_substr(Alternance::jourCourt($jour), 0, 3)) ?>
            <?= $l ? Alternance::LIEUX[$l['lieu']]['icone'] : '·' ?>
          </span>
        <?php endfor; ?>
        <span class="discret"><?= e(tn('alt.pj.jours_entreprise', $joursEntreprise)) ?></span>
      </p>
    <?php endif; ?>
  </div>
</div>

<form method="post" action="<?= url('alternance/journal') ?>" class="carte"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>
      data-brouillon="journal-<?= e($semaine) ?>">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <?php if ($edition): ?>
    <input type="hidden" name="id" value="<?= (int) $page['id'] ?>">
  <?php endif; ?>
  <div class="champ" style="max-width:260px">
    <label for="semaine"><?= e(t('alt.pj.semaine_du')) ?></label>
    <input type="date" id="semaine" name="semaine" required value="<?= e($semaine) ?>">
    <span class="champ__aide"><?= e(t('alt.pj.semaine_aide')) ?></span>
  </div>
  <div class="champ">
    <label for="missions"><?= e(t('alt.pj.missions')) ?></label>
    <textarea id="missions" name="missions" style="min-height:240px" data-texte-riche="complet"
              data-tailles="<?= e(implode(',', TexteRiche::TAILLES)) ?>"
              placeholder="<?= e(t('alt.pj.missions_exemple')) ?>"><?= e(TexteRiche::pourEditeur($edition ? $page['missions'] : post('missions'))) ?></textarea>
  </div>
  <div class="champ">
    <label for="competences"><?= e(t('alt.pj.competences')) ?></label>
    <input type="text" id="competences" name="competences" maxlength="500"
           placeholder="<?= e(t('alt.pj.competences_exemple')) ?>"
           value="<?= e($edition ? (string) $page['competences'] : post('competences')) ?>">
    <span class="champ__aide"><?= e(t('alt.pj.virgules')) ?></span>
  </div>
  <p class="actions">
    <button class="bouton" type="submit"><?= e(t('alt.pj.enregistrer')) ?></button>
    <a class="bouton bouton--secondaire" href="<?= url('alternance/journal') ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>><?= e(t('alt.nt.annuler')) ?></a>
  </p>
</form>
