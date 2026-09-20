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
foreach (array_keys(Alternance::ECHEANCES) as $champ) {
    $aDesDates = $aDesDates || $v($champ) !== '';
}
?>
<?= Vue::rendre('alternance/_onglets', ['onglet' => $onglet, 'situation' => $situation]) ?>

<div class="entete-page">
  <div>
    <h1>🏢 Mon alternance</h1>
    <p>L’entreprise, le tuteur et les dates du contrat. De quoi retrouver un
      numéro sans chercher, et savoir où vous en êtes.</p>
  </div>
</div>

<div class="colonnes">
  <form method="post" action="<?= url('alternance/entreprise') ?>" class="pile">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <section class="carte">
      <h2>L’entreprise</h2>
      <div class="champ">
        <label for="entreprise">Nom de l’entreprise</label>
        <input type="text" id="entreprise" name="entreprise" maxlength="150" value="<?= e($v('entreprise')) ?>">
      </div>
      <div class="champ">
        <label for="poste">Poste occupé</label>
        <input type="text" id="poste" name="poste" maxlength="150"
               placeholder="Assistant qualité, développeur…" value="<?= e($v('poste')) ?>">
      </div>
      <div class="champ">
        <label for="adresse">Adresse</label>
        <input type="text" id="adresse" name="adresse" maxlength="255" value="<?= e($v('adresse')) ?>">
      </div>
    </section>

    <section class="carte">
      <h2>Mon tuteur</h2>
      <div class="champ">
        <label for="tuteur">Nom du tuteur ou maître d’apprentissage</label>
        <input type="text" id="tuteur" name="tuteur" maxlength="120" value="<?= e($v('tuteur')) ?>">
      </div>
      <div class="ligne-champs">
        <div class="champ">
          <label for="tuteur_email">Adresse électronique</label>
          <input type="email" id="tuteur_email" name="tuteur_email" maxlength="190" value="<?= e($v('tuteur_email')) ?>">
        </div>
        <div class="champ">
          <label for="tuteur_tel">Téléphone</label>
          <input type="tel" id="tuteur_tel" name="tuteur_tel" maxlength="40" value="<?= e($v('tuteur_tel')) ?>">
        </div>
      </div>
      <div class="champ">
        <label for="referent">Référent à l’école</label>
        <input type="text" id="referent" name="referent" maxlength="120" value="<?= e($v('referent')) ?>">
      </div>
    </section>

    <section class="carte">
      <h2>Les dates</h2>
      <div class="ligne-champs">
        <div class="champ">
          <label for="debut">Début du contrat</label>
          <input type="date" id="debut" name="debut" value="<?= e($v('debut')) ?>">
        </div>
        <div class="champ">
          <label for="fin">Fin du contrat</label>
          <input type="date" id="fin" name="fin" value="<?= e($v('fin')) ?>">
        </div>
      </div>
      <div class="ligne-champs">
        <div class="champ">
          <label for="remise_rapport">Remise du rapport</label>
          <input type="date" id="remise_rapport" name="remise_rapport" value="<?= e($v('remise_rapport')) ?>">
        </div>
        <div class="champ">
          <label for="soutenance">Soutenance</label>
          <input type="date" id="soutenance" name="soutenance" value="<?= e($v('soutenance')) ?>">
        </div>
      </div>
      <p class="actions">
        <button class="bouton" type="submit">Enregistrer la fiche</button>
      </p>
    </section>
  </form>

  <div class="pile">
    <?php if ($avancement !== null): ?>
      <section class="carte">
        <h2>Où j’en suis</h2>
        <p class="alternance-avancement__chiffre">
          <strong><?= (int) $avancement['part'] ?> %</strong> du contrat
        </p>
        <div class="alternance-avancement" role="img"
             aria-label="<?= (int) $avancement['part'] ?> % du contrat écoulé">
          <span style="width:<?= (int) $avancement['part'] ?>%"></span>
        </div>
        <p class="discret" style="margin:.5rem 0 0">
          <?= (int) $avancement['faits'] ?> jour<?= $avancement['faits'] > 1 ? 's' : '' ?> sur
          <?= (int) $avancement['total'] ?>, du lundi au vendredi ·
          <?= max(0, $avancement['total'] - $avancement['faits']) ?> restant<?= $avancement['total'] - $avancement['faits'] > 1 ? 's' : '' ?>
        </p>
      </section>
    <?php endif; ?>

    <?php if ($taches !== []): ?>
      <?php // Ce qui reste à faire dans la liste « Alternance », échéances d'abord. ?>
      <section class="carte">
        <h2>✅ À faire</h2>
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
          <a href="<?= url('taches') ?>">Ouvrir mes listes</a> pour les cocher.
        </p>
      </section>
    <?php endif; ?>

    <?php if ($aDesDates): ?>
      <section class="carte">
        <h2>Dates clés</h2>
        <ul class="alternance-echeances">
          <?php foreach (Alternance::ECHEANCES as $champ => $echeance): ?>
            <?php if ($v($champ) === '') { continue; } ?>
            <li>
              <span><?= e($echeance['libelle']) ?></span>
              <strong><?= e(Alternance::jourCourt($v($champ))) ?></strong>
              <?php if (!empty($auCalendrier[$champ])): ?>
                <span class="pastille" title="Déjà au calendrier">📅 posée</span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <form method="post" action="<?= url('alternance/entreprise/calendrier') ?>" style="margin-top:.8rem">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--secondaire bouton--bloc" type="submit">📅 Poser ces dates au calendrier</button>
          <p class="champ__aide" style="margin-top:.5rem">Des journées entières, que vous pourrez ouvrir
            pour leur ajouter un rappel. Une date déjà posée ne l’est pas deux fois.</p>
        </form>
      </section>
    <?php endif; ?>

    <?php if ($v('tuteur_email') !== '' || $v('tuteur_tel') !== '' || $v('adresse') !== ''): ?>
      <section class="carte">
        <h2>Joindre</h2>
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
