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
    <p>Posez vos périodes telles que l’école les donne — et vos congés, jours fériés ou absences. Elles s’affichent dans le
      <a href="<?= url('calendrier') ?>">calendrier</a>, du lundi au vendredi.</p>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <?php if ($periodes === []): ?>
      <div class="vide">
        <span class="vide__icone">🔁</span>
        <p>Aucune période pour l’instant. Posez la première avec « Poser une période » : du … au …, à l’école, en entreprise, en congés…</p>
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

    <details class="carte">
      <summary class="alternance-export__ouvrir">📥 Importer un planning</summary>
      <form method="post" action="<?= url('alternance/rythme/import') ?>" enctype="multipart/form-data" style="margin-top:.8rem">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="champ">
          <label for="planning">Le fichier de l’école</label>
          <input type="file" id="planning" name="planning" accept=".ics,.csv,.txt,text/calendar,text/csv,text/plain">
          <span class="champ__aide">Un agenda (.ics) ou un tableau enregistré en CSV.</span>
        </div>
        <div class="champ">
          <label for="colle">… ou collez votre tableau</label>
          <textarea id="colle" name="colle" rows="4"
                    placeholder="2026-10-05 ; 2026-10-09 ; école&#10;12/10/2026 ; 23/10/2026 ; entreprise ; atelier"></textarea>
          <span class="champ__aide">Une ligne par période : début ; fin ; lieu ; précision.</span>
        </div>
        <div class="champ">
          <label for="lieu_defaut">Quand le fichier ne dit pas où</label>
          <select id="lieu_defaut" name="lieu_defaut">
            <?php foreach (Alternance::LIEUX as $cle => $l): ?>
              <option value="<?= e($cle) ?>"<?= $cle === 'entreprise' ? ' selected' : '' ?>><?= $l['icone'] ?> <?= e($l['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="bouton bouton--bloc" type="submit">Importer</button>
        <p class="champ__aide" style="margin-top:.6rem">Les périodes importées remplacent les jours
          déjà prévus sur leurs dates, comme si vous les posiez une à une.</p>
      </form>
    </details>

    <section class="carte">
      <h2>Dans un autre agenda</h2>
      <?php if ($lienIcs === ''): ?>
        <p class="discret" style="margin-top:0">Un lien d’abonnement met votre rythme dans Outlook,
          Google Agenda ou celui de votre téléphone. Ils le relisent tout seuls : une période
          que vous changez ici les suit.</p>
        <form method="post" action="<?= url('alternance/rythme/lien') ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--secondaire bouton--bloc" type="submit">📆 Créer le lien d’abonnement</button>
        </form>
      <?php else: ?>
        <div class="partage-lien" data-partage-lien>
          <label class="sr-only" for="lien-alternance">Lien d’abonnement au rythme</label>
          <input type="text" id="lien-alternance" readonly value="<?= e($lienIcs) ?>" data-partage-adresse>
          <button class="bouton" type="button" data-partage-copier>Copier</button>
        </div>
        <p class="champ__aide" data-partage-etat aria-live="polite">
          Dans Outlook ou Google Agenda : « Ajouter un calendrier » puis « À partir du Web ».
          Qui a ce lien voit votre rythme : ne le donnez qu’à qui de droit.
        </p>
        <form method="post" action="<?= url('alternance/rythme/lien') ?>"
              data-confirmation="Renouveler le lien ? L’ancien cessera de fonctionner et il faudra réabonner vos agendas.">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="renouveler" value="1">
          <button class="bouton bouton--discret bouton--petit" type="submit">Renouveler le lien</button>
        </form>
      <?php endif; ?>
    </section>

    <?php if ($periodes !== []): ?>
      <section class="carte">
        <h2>Bilan</h2>
        <ul class="alternance-bilan">
          <?php foreach (Alternance::LIEUX as $cle => $l): ?>
            <?php // Les congés, fériés et absences ne s'affichent que s'il y en a. ?>
            <?php if ($bilan[$cle]['total'] === 0 && !in_array($cle, ['ecole', 'entreprise'], true)) { continue; } ?>
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
