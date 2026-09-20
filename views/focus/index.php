<?php
/**
 * Le départ d'une session de révision, et ce que les précédentes ont donné.
 *
 * @var array|null $enCours       une session ouverte, à reprendre
 * @var array $bilan              ce que rend Focus::bilan()
 * @var list<array> $dernieres    les dernières sessions refermées
 * @var list<array> $cours        les cours, pour choisir quoi réviser
 * @var ?int $coursChoisi         celui proposé par l’adresse
 * @var array|null $dernierCours  le dernier cours révisé
 * @var int $objectif             l’objectif de la semaine, en minutes (0 : aucun)
 * @var array|null $avancement    où il en est, ou null sans objectif
 * @var array|null $apres         la session qu’on vient de finir, si elle portait sur un cours
 * @var callable $cartes         (int|null $coursId): int — les cartes à revoir aujourd’hui
 */
$csrf = Session::jetonCsrf();
$choisi = $coursChoisi ?? (int) ($dernierCours['id'] ?? 0);
?>

<div class="entete-page">
  <div>
    <h1>🎯 Session de révision</h1>
    <p>Un minuteur, un écran sans rien d’autre, et le compte de ce que vous avez
      vraiment travaillé. Le temps en pause ne compte pas.</p>
  </div>
</div>

<?php if ($enCours !== null): ?>
  <div class="carte" style="border-color:var(--accent);margin-bottom:1rem">
    <h2 style="margin-top:0">Une session est ouverte</h2>
    <p class="discret">Commencée à <?= e(date('H\hi', strtotime((string) $enCours['debut']))) ?>
      <?php if ($enCours['cours_titre'] !== null): ?>sur « <?= e((string) $enCours['cours_titre']) ?> »<?php endif; ?>.</p>
    <p class="actions">
      <a class="bouton" href="<?= url('focus/' . (int) $enCours['id']) ?>">Reprendre</a>
      <form method="post" action="<?= url('focus/' . (int) $enCours['id'] . '/abandonner') ?>" class="en-ligne"
            data-confirmation="Abandonner cette session ? Rien ne sera compté.">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <button class="bouton bouton--discret" type="submit">Abandonner</button>
      </form>
    </p>
  </div>
<?php endif; ?>

<?php if ($apres !== null): ?>
  <?php
  /*
   * Ce qui se joue juste après une session : on retient mieux en revoyant
   * plus tard qu'en relisant plus longtemps. C'est le moment de poser les
   * prochaines révisions, et de passer aux cartes que ce cours réclame.
   */
  $aRevoir = $cartes((int) $apres['cours_id']);
  ?>
  <div class="carte" style="border-color:var(--accent);margin-bottom:1rem">
    <h2 style="margin-top:0">Après « <?= e((string) $apres['cours_titre']) ?> »</h2>
    <p class="discret">Vous venez d’y passer <?= e(Focus::duree((int) $apres['secondes'])) ?>.</p>
    <p class="actions">
      <form method="post" action="<?= url('focus/espacer') ?>" class="en-ligne">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="cours_id" value="<?= (int) $apres['cours_id'] ?>">
        <button class="bouton" type="submit">🔁 Le revoir demain, dans 3 jours, dans une semaine</button>
      </form>
      <?php if ($aRevoir > 0): ?>
        <a class="bouton bouton--secondaire"
           href="<?= url('cartes/seance', ['cours' => (int) $apres['cours_id']]) ?>">
          🃏 <?= $aRevoir ?> carte<?= $aRevoir > 1 ? 's' : '' ?> à revoir
        </a>
      <?php endif; ?>
    </p>
    <p class="champ__aide" style="margin-bottom:0">Les révisions posées deviennent des tâches
      dans « <?= e(Focus::LISTE) ?> », avec leur échéance — elles reviendront donc au rappel du matin.</p>
  </div>
<?php endif; ?>

<div class="colonnes">
  <div class="pile">
    <section class="carte">
      <h2 style="margin-top:0">Démarrer</h2>
      <form method="post" action="<?= url('focus/demarrer') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

        <div class="champ">
          <label for="cours_id">Ce que je révise</label>
          <select id="cours_id" name="cours_id">
            <option value="">Sans cours précis</option>
            <?php foreach ($cours as $c): ?>
              <option value="<?= (int) $c['id'] ?>"<?= (int) $c['id'] === $choisi ? ' selected' : '' ?>>
                <?= e((string) $c['titre']) ?><?= $c['matiere_nom'] !== null ? ' · ' . e((string) $c['matiere_nom']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="champ__aide">Sa fiche de révision s’ouvrira avec le minuteur.</span>
        </div>

        <div class="champ">
          <label for="sujet">Sur quoi, précisément <span class="discret">(facultatif)</span></label>
          <input type="text" id="sujet" name="sujet" maxlength="150" placeholder="Chapitre 3, les annales de 2025…">
        </div>

        <div class="champ">
          <label for="minutes">Rythme</label>
          <select id="minutes" name="minutes">
            <?php foreach (Focus::RYTHMES as $minutes => $rythme): ?>
              <option value="<?= (int) $minutes ?>"<?= (int) $minutes === 25 ? ' selected' : '' ?>><?= e($rythme['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <label class="case" style="margin:.2rem 0 .9rem">
          <input type="checkbox" name="ne_pas_deranger" value="1" checked>
          🔕 Retenir les rappels pendant la session
        </label>

        <button class="bouton bouton--bloc" type="submit">▶️ Commencer</button>
      </form>
    </section>

    <section class="carte">
      <h2>Planifier une session</h2>
      <p class="discret" style="margin-top:0">Posée au calendrier, avec un rappel un quart d’heure avant :
        décider maintenant coûte moins que décider au moment de s’y mettre.</p>
      <form method="post" action="<?= url('focus/planifier') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="ligne-champs">
          <div class="champ">
            <label for="jour">Quel jour</label>
            <input type="date" id="jour" name="jour" required min="<?= e(date('Y-m-d')) ?>"
                   value="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>">
          </div>
          <div class="champ">
            <label for="heure">À quelle heure</label>
            <input type="time" id="heure" name="heure" required value="18:00">
          </div>
        </div>
        <div class="ligne-champs">
          <div class="champ">
            <label for="planif-cours">Sur quel cours</label>
            <select id="planif-cours" name="cours_id">
              <option value="">Sans cours précis</option>
              <?php foreach ($cours as $c): ?>
                <option value="<?= (int) $c['id'] ?>"<?= (int) $c['id'] === $choisi ? ' selected' : '' ?>><?= e((string) $c['titre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="champ">
            <label for="planif-minutes">Combien de temps</label>
            <select id="planif-minutes" name="minutes">
              <?php foreach (Focus::RYTHMES as $minutes => $rythme): ?>
                <option value="<?= (int) $minutes ?>"<?= (int) $minutes === 25 ? ' selected' : '' ?>><?= (int) $minutes ?> min</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <button class="bouton bouton--secondaire bouton--bloc" type="submit">📅 Poser au calendrier</button>
      </form>
    </section>

    <?php if ($dernieres !== []): ?>
      <section class="carte">
        <h2>Dernières sessions</h2>
        <ul class="focus-liste">
          <?php foreach ($dernieres as $s): ?>
            <li>
              <span class="focus-liste__barre" style="background:<?= e((string) ($s['matiere_couleur'] ?? '#94a3b8')) ?>"></span>
              <span style="flex:1;min-width:0">
                <strong><?= e((string) ($s['cours_titre'] ?? $s['sujet'] ?? 'Révision libre')) ?></strong><br>
                <span class="discret">
                  <?= e(ucfirst(date_fr((string) $s['debut'], false))) ?> à <?= e(date('H\hi', strtotime((string) $s['debut']))) ?>
                  <?php if ((int) $s['pauses'] > 0): ?> · <?= (int) $s['pauses'] ?> pause<?= (int) $s['pauses'] > 1 ? 's' : '' ?><?php endif; ?>
                  <?php if ($s['ressenti'] !== null): ?> · <?= Focus::RESSENTIS[$s['ressenti']]['icone'] ?><?php endif; ?>
                </span>
              </span>
              <strong><?= e(Focus::duree((int) $s['secondes'])) ?></strong>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  </div>

  <div class="pile">
    <section class="carte">
      <h2 style="margin-top:0">Où j’en suis</h2>
      <p class="focus-chiffre"><strong><?= e(Focus::duree((int) $bilan['aujourdhui'])) ?></strong> aujourd’hui</p>
      <p class="discret" style="margin:.2rem 0 0">
        <?= e(Focus::duree((int) $bilan['semaine'])) ?> cette semaine,
        en <?= (int) $bilan['sessions'] ?> session<?= (int) $bilan['sessions'] > 1 ? 's' : '' ?>.
      </p>
      <?php if ((int) $bilan['serie'] > 0): ?>
        <p style="margin:.6rem 0 0">
          🔥 <strong><?= (int) $bilan['serie'] ?> jour<?= (int) $bilan['serie'] > 1 ? 's' : '' ?></strong> d’affilée
          <?php if ((int) $bilan['aujourdhui'] === 0): ?>
            <span class="discret">— rien encore aujourd’hui, la série tient jusqu’à ce soir.</span>
          <?php endif; ?>
        </p>
      <?php endif; ?>
    </section>

    <section class="carte">
      <h2>Objectif de la semaine</h2>
      <?php if ($avancement !== null): ?>
        <p class="focus-chiffre" style="font-size:1.1rem">
          <strong><?= (int) $avancement['part'] ?> %</strong> de <?= e(Focus::duree($avancement['minutes'] * 60)) ?>
        </p>
        <div class="alternance-avancement" role="img"
             aria-label="<?= (int) $avancement['part'] ?> % de l’objectif de la semaine">
          <span style="width:<?= (int) $avancement['part'] ?>%"></span>
        </div>
        <p class="discret" style="margin:.5rem 0 .8rem">
          <?php if ($avancement['reste'] === 0): ?>
            C’est fait pour cette semaine. Tout ce qui suit est du bonus.
          <?php else: ?>
            Il reste <?= e(Focus::duree($avancement['reste'] * 60)) ?>,
            soit environ <?= (int) $avancement['par_jour'] ?> min par jour
            sur les <?= (int) $avancement['jours'] ?> jour<?= $avancement['jours'] > 1 ? 's' : '' ?> qui restent.
          <?php endif; ?>
        </p>
      <?php else: ?>
        <p class="discret" style="margin-top:0">Sans objectif, le suivi compte quand même le temps passé.</p>
      <?php endif; ?>
      <form method="post" action="<?= url('focus/objectif') ?>" class="filtres" style="margin:0" data-auto-envoi>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="champ" style="flex:1">
          <label for="objectif">Me fixer</label>
          <select id="objectif" name="minutes">
            <?php foreach (Focus::OBJECTIFS as $minutes => $nom): ?>
              <option value="<?= (int) $minutes ?>"<?= (int) $minutes === (int) $objectif ? ' selected' : '' ?>><?= e($nom) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <noscript><button class="bouton bouton--petit" type="submit">Enregistrer</button></noscript>
      </form>
    </section>

    <?php if ($bilan['par_matiere'] !== []): ?>
      <section class="carte">
        <h2>Cette semaine, par matière</h2>
        <?php $plus = max(array_map(static fn (array $l): int => (int) $l['secondes'], $bilan['par_matiere'])); ?>
        <ul class="focus-matieres">
          <?php foreach ($bilan['par_matiere'] as $ligne): ?>
            <li>
              <span class="focus-matieres__nom"><?= e((string) $ligne['matiere']) ?></span>
              <span class="focus-matieres__barre">
                <span style="width:<?= $plus > 0 ? (int) round((int) $ligne['secondes'] / $plus * 100) : 0 ?>%;
                             background:<?= e((string) $ligne['couleur']) ?>"></span>
              </span>
              <span class="discret"><?= e(Focus::duree((int) $ligne['secondes'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  </div>
</div>
