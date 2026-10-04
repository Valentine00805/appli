<?php
/**
 * Le départ d'une session de révision, et ce que les précédentes ont donné.
 *
 * @var array|null $enCours       une session ouverte, à reprendre
 * @var array $bilan              ce que rend Focus::bilan()
 * @var list<array> $dernieres    les dernières sessions refermées
 * @var list<array> $cours        les cours, pour choisir quoi réviser
 * @var list<int> $coches        les cours cochés d’avance
 * @var array|null $dernierCours  le dernier cours révisé
 * @var int $objectif             l’objectif de la semaine, en minutes (0 : aucun)
 * @var array|null $avancement    où il en est, ou null sans objectif
 * @var array|null $apres         la session qu’on vient de finir, si elle portait sur un cours
 * @var list<array> $apresCours   les cours de cette session
 * @var list<array> $dossiers     les dossiers de cours, pour en prendre un entier
 */
$csrf = Session::jetonCsrf();
$coches = array_flip(array_map('intval', $coches));
$dansUneFenetre = $dansUneFenetre ?? false;
?>

<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <h1><?= e(t('focus.titre')) ?></h1>
    <p><?= e(t('focus.sous_titre')) ?></p>
  </div>
</div>

<?php if ($enCours !== null): ?>
  <div class="carte" style="border-color:var(--accent);margin-bottom:1rem">
    <h2 style="margin-top:0"><?= e(t('focus.session_ouverte')) ?></h2>
    <p class="discret"><?= e(t('focus.commencee_a', ['heure' => heure_courte((int) strtotime((string) $enCours['debut']))])) ?>
      <?php if ($enCours['cours_titre'] !== null): ?><?= e(t('focus.sur_cours', ['nom' => (string) $enCours['cours_titre']])) ?><?php endif; ?>.</p>
    <p class="actions">
      <a class="bouton" href="<?= url('focus/' . (int) $enCours['id']) ?>"><?= e(t('focus.reprendre')) ?></a>
      <form method="post" action="<?= url('focus/' . (int) $enCours['id'] . '/abandonner') ?>" class="en-ligne"
            data-confirmation="<?= e(t('focus.abandonner_sur')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <button class="bouton bouton--discret" type="submit"><?= e(t('focus.abandonner')) ?></button>
      </form>
    </p>
  </div>
<?php endif; ?>

<?php if ($apres !== null && $apresCours !== []): ?>
  <?php
  /*
   * Ce qui se joue juste après une session : on retient mieux en revoyant
   * plus tard qu'en relisant plus longtemps. C'est le moment de poser les
   * prochaines révisions, et de passer aux cartes que ces cours réclament.
   */
  $aRevoir = 0;
  foreach ($apresCours as $c) {
    $aRevoir += Focus::cartesAReviser((int) Auth::id(), (int) $c['id']);
  }
  $plusieurs = count($apresCours) > 1;
  ?>
  <div class="carte" style="border-color:var(--accent);margin-bottom:1rem">
    <h2 style="margin-top:0">
      <?= e($plusieurs
          ? t('focus.apres_plusieurs', ['n' => count($apresCours)])
          : t('focus.apres_un', ['nom' => (string) $apresCours[0]['titre']])) ?>
    </h2>
    <p class="discret">
      <?= e(t('focus.venez_passer', ['duree' => Focus::duree((int) $apres['secondes'])])) ?><?php
        ?><?= $plusieurs ? ' : ' . e(implode(', ', array_map(
            static fn (array $c): string => (string) $c['titre'], $apresCours))) : '' ?>.
    </p>
    <p class="actions">
      <form method="post" action="<?= url('focus/espacer') ?>" class="en-ligne">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="session_id" value="<?= (int) $apres['id'] ?>">
        <button class="bouton" type="submit">
          <?= e(t($plusieurs ? 'focus.espacer_plusieurs' : 'focus.espacer_un')) ?>
        </button>
      </form>
      <?php if ($aRevoir > 0): ?>
        <a class="bouton bouton--secondaire"
           href="<?= url('cartes/seance', $plusieurs ? [] : ['cours' => (int) $apresCours[0]['id']]) ?>">
          <?= e(tn('focus.cartes_a_revoir', $aRevoir)) ?>
        </a>
      <?php endif; ?>
    </p>
    <p class="champ__aide" style="margin-bottom:0"><?= e(t('focus.espacer_aide', ['liste' => Focus::nomDeLaListe(Auth::id())])) ?></p>
  </div>
<?php endif; ?>

<div class="colonnes">
  <div class="pile">
    <section class="carte">
      <h2 style="margin-top:0"><?= e(t('focus.demarrer')) ?></h2>
      <form method="post" action="<?= url('focus/demarrer') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

        <?php
        /*
         * On révise rarement un seul cours : on coche ceux qu'on veut, ou
         * l'on prend un dossier entier — ses sous-dossiers avec lui. Rien de
         * coché vaut « sans cours précis », et le minuteur tourne quand même.
         */
        ?>
        <div class="champ">
          <span class="legende"><?= e(t('focus.ce_que_je_revise')) ?></span>
          <label class="discussions-recherche">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
              <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
            </svg>
            <span class="sr-only"><?= e(t('focus.rechercher_aide')) ?></span>
            <input type="search" placeholder="<?= e(t('focus.rechercher')) ?>" autocomplete="off"
                   data-filtre-liste="[data-liste-focus-cours]">
          </label>
          <p style="margin:.5rem 0 0">
            <button class="bouton bouton--discret bouton--petit" type="button"
                    data-cocher-tout="[data-liste-focus-cours]"><?= e(t('focus.tout_cocher')) ?></button>
          </p>
          <ul class="groupe-choix__liste partage-liste focus-choix" data-liste-focus-cours>
            <?php foreach ($cours as $c): ?>
              <li data-nom="<?= e(mb_strtolower((string) $c['titre'] . ' ' . (string) ($c['matiere_nom'] ?? '')
                  . ' ' . (string) ($c['dossier_nom'] ?? ''))) ?>">
                <label class="groupe-choix__ami">
                  <input type="checkbox" name="cours[]" value="<?= (int) $c['id'] ?>"<?= isset($coches[(int) $c['id']]) ? ' checked' : '' ?>>
                  <span aria-hidden="true">📘</span>
                  <span class="partage-liste__nom">
                    <?= e((string) $c['titre']) ?>
                    <span class="discret">
                      <?php if (($c['matiere_nom'] ?? null) !== null): ?>· <?= e((string) $c['matiere_nom']) ?><?php endif; ?>
                      <?php if (($c['dossier_nom'] ?? null) !== null): ?>· <?= e((string) $c['dossier_nom']) ?><?php endif; ?>
                    </span>
                  </span>
                </label>
              </li>
            <?php endforeach; ?>
          </ul>
          <p class="discret" data-filtre-vide hidden style="margin:.4rem 0 0"><?= e(t('focus.aucun_cours')) ?></p>
          <span class="champ__aide"><?= e(t('focus.cours_aide')) ?></span>
        </div>

        <?php if ($dossiers !== []): ?>
          <details class="champ">
            <summary class="legende" style="cursor:pointer"><?= e(t('focus.ou_dossiers')) ?></summary>
            <ul class="groupe-choix__liste partage-liste focus-choix" style="margin-top:.5rem">
              <?php foreach ($dossiers as $d): ?>
                <li>
                  <label class="groupe-choix__ami">
                    <input type="checkbox" name="dossiers[]" value="<?= (int) $d['id'] ?>">
                    <span aria-hidden="true"><?= e((string) ($d['icone'] ?: '📁')) ?></span>
                    <span class="partage-liste__nom">
                      <?= e((string) $d['nom']) ?>
                      <span class="discret"><?= e(tn('focus.dossier_cours', (int) $d['nb_cours'])) ?></span>
                    </span>
                  </label>
                </li>
              <?php endforeach; ?>
            </ul>
            <span class="champ__aide"><?= e(t('focus.dossiers_aide')) ?></span>
          </details>
        <?php endif; ?>

        <div class="champ">
          <label for="sujet"><?= e(t('focus.sur_quoi')) ?> <span class="discret"><?= e(t('commun.facultatif')) ?></span></label>
          <input type="text" id="sujet" name="sujet" maxlength="150" placeholder="<?= e(t('focus.sur_quoi_exemple')) ?>">
        </div>

        <div class="champ">
          <label for="minutes"><?= e(t('focus.rythme')) ?></label>
          <select id="minutes" name="minutes">
            <?php foreach (array_keys(Focus::RYTHMES) as $minutes): ?>
              <option value="<?= (int) $minutes ?>"<?= (int) $minutes === 25 ? ' selected' : '' ?>><?= e(Focus::nomRythme((int) $minutes)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <label class="case" style="margin:.2rem 0 .9rem">
          <input type="checkbox" name="ne_pas_deranger" value="1" checked>
          <?= e(t('focus.ne_pas_deranger')) ?>
        </label>

        <button class="bouton bouton--bloc" type="submit"><?= e(t('focus.commencer')) ?></button>
      </form>
    </section>

    <section class="carte">
      <h2><?= e(t('focus.planifier')) ?></h2>
      <p class="discret" style="margin-top:0"><?= e(t('focus.planifier_aide')) ?></p>
      <form method="post" action="<?= url('focus/planifier') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="ligne-champs">
          <div class="champ">
            <label for="jour"><?= e(t('focus.quel_jour')) ?></label>
            <input type="date" id="jour" name="jour" required min="<?= e(date('Y-m-d')) ?>"
                   value="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>">
          </div>
          <div class="champ">
            <label for="heure"><?= e(t('focus.quelle_heure')) ?></label>
            <input type="time" id="heure" name="heure" required value="18:00">
          </div>
        </div>
        <?php // Les mêmes cases qu'au démarrage : un cours, plusieurs, ou des dossiers. ?>
        <div class="champ">
          <span class="legende"><?= e(t('focus.sur_quels_cours')) ?></span>
          <label class="discussions-recherche">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
              <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
            </svg>
            <span class="sr-only"><?= e(t('focus.rechercher_aide')) ?></span>
            <input type="search" placeholder="<?= e(t('focus.rechercher')) ?>" autocomplete="off"
                   data-filtre-liste="[data-liste-planif-cours]">
          </label>
          <ul class="groupe-choix__liste partage-liste focus-choix" style="margin-top:.5rem" data-liste-planif-cours>
            <?php foreach ($cours as $c): ?>
              <li data-nom="<?= e(mb_strtolower((string) $c['titre'] . ' ' . (string) ($c['matiere_nom'] ?? '')
                  . ' ' . (string) ($c['dossier_nom'] ?? ''))) ?>">
                <label class="groupe-choix__ami">
                  <input type="checkbox" name="cours[]" value="<?= (int) $c['id'] ?>">
                  <span aria-hidden="true">📘</span>
                  <span class="partage-liste__nom">
                    <?= e((string) $c['titre']) ?>
                    <span class="discret">
                      <?php if (($c['matiere_nom'] ?? null) !== null): ?>· <?= e((string) $c['matiere_nom']) ?><?php endif; ?>
                      <?php if (($c['dossier_nom'] ?? null) !== null): ?>· <?= e((string) $c['dossier_nom']) ?><?php endif; ?>
                    </span>
                  </span>
                </label>
              </li>
            <?php endforeach; ?>
          </ul>
          <p class="discret" data-filtre-vide hidden style="margin:.4rem 0 0"><?= e(t('focus.aucun_cours')) ?></p>
        </div>

        <?php if ($dossiers !== []): ?>
          <details class="champ">
            <summary class="legende" style="cursor:pointer"><?= e(t('focus.ou_dossiers')) ?></summary>
            <ul class="groupe-choix__liste partage-liste focus-choix" style="margin-top:.5rem">
              <?php foreach ($dossiers as $d): ?>
                <li>
                  <label class="groupe-choix__ami">
                    <input type="checkbox" name="dossiers[]" value="<?= (int) $d['id'] ?>">
                    <span aria-hidden="true"><?= e((string) ($d['icone'] ?: '📁')) ?></span>
                    <span class="partage-liste__nom">
                      <?= e((string) $d['nom']) ?>
                      <span class="discret"><?= e(tn('focus.dossier_cours', (int) $d['nb_cours'])) ?></span>
                    </span>
                  </label>
                </li>
              <?php endforeach; ?>
            </ul>
          </details>
        <?php endif; ?>

        <div class="ligne-champs">
          <div class="champ">
            <label for="planif-minutes"><?= e(t('focus.combien_temps')) ?></label>
            <select id="planif-minutes" name="minutes">
              <?php foreach (array_keys(Focus::RYTHMES) as $minutes): ?>
                <option value="<?= (int) $minutes ?>"<?= (int) $minutes === 25 ? ' selected' : '' ?>><?= (int) $minutes ?> min</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <button class="bouton bouton--secondaire bouton--bloc" type="submit"><?= e(t('focus.poser')) ?></button>
      </form>
    </section>

    <?php if ($dernieres !== []): ?>
      <section class="carte">
        <h2><?= e(t('focus.dernieres')) ?></h2>
        <ul class="focus-liste">
          <?php foreach ($dernieres as $s): ?>
            <li>
              <span class="focus-liste__barre" style="background:<?= e((string) ($s['matiere_couleur'] ?? '#94a3b8')) ?>"></span>
              <span style="flex:1;min-width:0">
                <strong><?= e((string) ($s['cours_titre'] ?? $s['sujet'] ?? t('focus.revision_libre'))) ?></strong><br>
                <span class="discret">
                  <?= e(ucfirst(date_fr((string) $s['debut'], false))) ?> à <?= e(heure_courte((int) strtotime((string) $s['debut']))) ?>
                  <?php if ((int) $s['pauses'] > 0): ?> · <?= e(tn('focus.pauses', (int) $s['pauses'])) ?><?php endif; ?>
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
      <h2 style="margin-top:0"><?= e(t('focus.ou_jen_suis')) ?></h2>
      <p class="focus-chiffre"><strong><?= e(Focus::duree((int) $bilan['aujourdhui'])) ?></strong> <?= e(t('focus.aujourdhui')) ?></p>
      <p class="discret" style="margin:.2rem 0 0">
        <?= e(t('focus.cette_semaine', ['duree' => Focus::duree((int) $bilan['semaine'])])) ?>
        <?= e(tn('focus.en_sessions', (int) $bilan['sessions'])) ?>
      </p>
      <?php if ((int) $bilan['serie'] > 0): ?>
        <p style="margin:.6rem 0 0">
          <?= tn('focus.serie', (int) $bilan['serie']) ?>
          <?php if ((int) $bilan['aujourdhui'] === 0): ?>
            <span class="discret"><?= e(t('focus.serie_tient')) ?></span>
          <?php endif; ?>
        </p>
      <?php endif; ?>
    </section>

    <section class="carte">
      <h2><?= e(t('focus.objectif')) ?></h2>
      <?php if ($avancement !== null): ?>
        <p class="focus-chiffre" style="font-size:1.1rem">
          <?= t('focus.part_de', ['part' => (int) $avancement['part'], 'duree' => e(Focus::duree($avancement['minutes'] * 60))]) ?>
        </p>
        <div class="alternance-avancement" role="img"
             aria-label="<?= e(t('focus.part_aria', ['part' => (int) $avancement['part']])) ?>">
          <span style="width:<?= (int) $avancement['part'] ?>%"></span>
        </div>
        <p class="discret" style="margin:.5rem 0 .8rem">
          <?php if ($avancement['reste'] === 0): ?>
            <?= e(t('focus.objectif_atteint')) ?>
          <?php else: ?>
            <?= e(tn('focus.objectif_reste', (int) $avancement['jours'], [
                'duree' => Focus::duree($avancement['reste'] * 60),
                'parjour' => (int) $avancement['par_jour'],
            ])) ?>
          <?php endif; ?>
        </p>
      <?php else: ?>
        <p class="discret" style="margin-top:0"><?= e(t('focus.sans_objectif')) ?></p>
      <?php endif; ?>
      <form method="post" action="<?= url('focus/objectif') ?>" class="filtres" style="margin:0" data-auto-envoi>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="champ" style="flex:1">
          <label for="objectif"><?= e(t('focus.me_fixer')) ?></label>
          <select id="objectif" name="minutes">
            <?php foreach (array_keys(Focus::OBJECTIFS) as $minutes): ?>
              <option value="<?= (int) $minutes ?>"<?= (int) $minutes === (int) $objectif ? ' selected' : '' ?>><?= e(Focus::nomObjectif((int) $minutes)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <noscript><button class="bouton bouton--petit" type="submit"><?= e(t('commun.enregistrer')) ?></button></noscript>
      </form>
    </section>

    <?php if ($bilan['par_matiere'] !== []): ?>
      <section class="carte">
        <h2><?= e(t('focus.par_matiere')) ?></h2>
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
