<?php
/**
 * La page d'une semaine du journal, à écrire ou à compléter.
 *
 * @var string $semaine  son lundi
 * @var array|null $page
 * @var array $lieux     le lieu de chaque jour de la semaine
 * @var string $onglet
 * @var array|null $situation
 */
$edition = $page !== null;
$vendredi = (new DateTimeImmutable($semaine))->modify('+4 days')->format('Y-m-d');
$joursEntreprise = count(array_filter($lieux, static fn (array $l): bool => $l['lieu'] === 'entreprise'));
?>
<?= Vue::rendre('alternance/_onglets', ['onglet' => $onglet, 'situation' => $situation]) ?>

<div class="entete-page">
  <div>
    <p style="margin:0 0 .3rem"><a href="<?= url('alternance/journal') ?>">← Tout le journal</a></p>
    <h1>📓 Semaine du <?= e(Alternance::jourCourt($semaine)) ?> au <?= e(Alternance::jourCourt($vendredi)) ?></h1>
    <?php if ($lieux !== []): ?>
      <p class="alternance-semaine">
        <?php for ($i = 0; $i < 5; $i++): ?>
          <?php
          $jour = (new DateTimeImmutable($semaine))->modify('+' . $i . ' days')->format('Y-m-d');
          $l = $lieux[$jour] ?? null;
          ?>
          <span class="alternance-semaine__jour<?= $l ? ' alternance-semaine__jour--' . e($l['lieu']) : '' ?>"
                title="<?= e(Alternance::jourCourt($jour)) ?><?= $l ? ' : ' . e(Alternance::LIEUX[$l['lieu']]['nom']) : '' ?>">
            <?= e(mb_substr(Alternance::jourCourt($jour), 0, 3)) ?>
            <?= $l ? Alternance::LIEUX[$l['lieu']]['icone'] : '·' ?>
          </span>
        <?php endfor; ?>
        <span class="discret"><?= $joursEntreprise ?> jour<?= $joursEntreprise > 1 ? 's' : '' ?> en entreprise</span>
      </p>
    <?php endif; ?>
  </div>
</div>

<form method="post" action="<?= url('alternance/journal') ?>" class="carte">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <?php if ($edition): ?>
    <input type="hidden" name="id" value="<?= (int) $page['id'] ?>">
  <?php endif; ?>
  <div class="champ" style="max-width:260px">
    <label for="semaine">Semaine du</label>
    <input type="date" id="semaine" name="semaine" required value="<?= e($semaine) ?>">
    <span class="champ__aide">N’importe quel jour : la page se range à son lundi.</span>
  </div>
  <div class="champ">
    <label for="missions">Missions et tâches réalisées</label>
    <textarea id="missions" name="missions" style="min-height:240px" data-texte-riche="complet"
              data-tailles="<?= e(implode(',', TexteRiche::TAILLES)) ?>"
              placeholder="Ce que vous avez fait, avec qui, les outils utilisés, ce qui a posé problème…"><?= e(TexteRiche::pourEditeur($edition ? $page['missions'] : post('missions'))) ?></textarea>
  </div>
  <div class="champ">
    <label for="competences">Compétences travaillées</label>
    <input type="text" id="competences" name="competences" maxlength="500"
           placeholder="Travail en équipe, Excel, relation client…"
           value="<?= e($edition ? (string) $page['competences'] : post('competences')) ?>">
    <span class="champ__aide">Séparées par des virgules.</span>
  </div>
  <p class="actions">
    <button class="bouton" type="submit">Enregistrer la semaine</button>
    <a class="bouton bouton--secondaire" href="<?= url('alternance/journal') ?>">Annuler</a>
  </p>
</form>
