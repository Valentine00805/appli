<?php
/**
 * Un calendrier partagé : ses prochains évènements, ses membres, et de quoi le gérer (son créateur) ou le quitter (les autres).
 *
 * @var array<string, mixed> $calendrier
 * @var list<array{id: int, pseudo: string, proprietaire: bool}> $membres
 * @var list<array> $aAjouter  mes amis qui n'y sont pas encore (le créateur seulement)
 * @var list<array<string, mixed>> $aVenir
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$id = (int) $calendrier['id'];
$base = 'calendriers-amis/' . $id;
$csrf = Session::jetonCsrf();
$gere = (bool) $calendrier['est_proprietaire'];
// Le calendrier commun d'un projet : ses membres sont ceux du projet, on ne les gère pas ici.
$duProjet = $calendrier['projet_id'] !== null;
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div style="display:flex;align-items:center;gap:.7rem">
    <span class="cam-pastille cam-pastille--grande" style="background:<?= e($calendrier['couleur']) ?>" aria-hidden="true"></span>
    <div>
      <?php if (!$dansUneFenetre): ?>
        <p class="discret" style="margin:0 0 .2rem"><a href="<?= url('calendrier') ?>"><?= e(t('evt.retour_calendrier')) ?></a></p>
      <?php endif; ?>
      <h1 style="margin:0"><?= e($calendrier['nom']) ?></h1>
      <p class="discret" style="margin:.15rem 0 0">
        <?= e(tn('cam.membres_n', (int) $calendrier['membres'])) ?>
        · <?php if ($duProjet): ?><?= e(t('cam.du_projet', ['nom' => (string) $calendrier['projet_nom']])) ?>
        <?php else: ?><?= e($gere ? t('cam.cree_par_moi') : t('cam.cree_par', ['qui' => $calendrier['proprietaire_pseudo']])) ?><?php endif; ?>
      </p>
    </div>
  </div>
  <div class="actions">
    <a class="bouton" href="<?= url('evenements/nouveau', ['agenda' => CalendriersAmis::cle($id)]) ?>" data-fenetre>＋ <?= e(t('cam.evt_ajouter')) ?></a>
  </div>
</div>

<section class="carte profil-ami__section">
  <h3 class="groupe-sous-titre"><?= e(t('cam.a_venir')) ?></h3>
  <?php if ($aVenir === []): ?>
    <p class="discret" style="margin:0"><?= e(t('cam.rien_a_venir')) ?></p>
  <?php else: ?>
    <ul class="cam-evenements">
      <?php foreach ($aVenir as $evt): ?>
        <?php
        $debut = strtotime((string) $evt['debut']);
        $quand = date(t('date.jour_mois'), $debut) . ((int) $evt['journee_entiere'] === 1 ? '' : ' · ' . date('H:i', $debut));
        ?>
        <li>
          <a href="<?= url('calendriers-amis/evenements/' . (int) $evt['id']) ?>" data-fenetre>
            <span class="cam-evenements__quand"><?= e($quand) ?></span>
            <span><?= e((string) $evt['titre']) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php if ($gere): ?>
  <section class="carte profil-ami__section">
    <h3 class="groupe-sous-titre"><?= e(t('cam.reglages')) ?></h3>
    <form method="post" action="<?= url($base . '/modifier') ?>" class="groupe-formulaire"<?= $envoi ?>>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <div class="champ">
        <label for="cam-nom"><?= e(t('cam.nom')) ?></label>
        <input type="text" id="cam-nom" name="nom" required maxlength="<?= CalendriersAmis::NOM_MAX ?>"
               value="<?= e($calendrier['nom']) ?>" autocomplete="off">
      </div>
      <div class="champ">
        <span class="legende"><?= e(t('cam.couleur')) ?></span>
        <?= Vue::rendre('calendriers-amis/_couleurs', ['choisie' => $calendrier['couleur_calendrier']]) ?>
      </div>
      <div class="actions"><button class="bouton" type="submit"><?= e(t('cam.enregistrer')) ?></button></div>
    </form>
  </section>
<?php endif; ?>

<section class="carte profil-ami__section">
  <h3 class="groupe-sous-titre"><?= e(t('cam.membres')) ?></h3>
  <ul class="groupe-membres">
    <?php foreach ($membres as $m): ?>
      <li class="groupe-membres__ligne">
        <?= Amis::avatar($m['id'], $m['pseudo']) ?>
        <span class="groupe-membres__nom"><?= e($m['pseudo']) ?></span>
        <?php if ($m['proprietaire'] && !$duProjet): ?>
          <span class="discret"><?= e(t('cam.createur')) ?></span>
        <?php elseif ($gere && !$duProjet): ?>
          <span class="groupe-membres__actions">
            <form method="post" action="<?= url($base . '/membres/' . $m['id'] . '/retirer') ?>"<?= $envoi ?>
                  data-confirmation="<?= e(t('cam.retirer_confirmation', ['qui' => $m['pseudo']])) ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('cam.retirer')) ?></button>
            </form>
          </span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($duProjet): ?>
    <p class="champ__aide" style="margin-top:.8rem"><?= e(t('cam.membres_du_projet_aide', ['nom' => (string) $calendrier['projet_nom']])) ?></p>
    <a class="bouton bouton--discret bouton--petit" href="<?= url('travaux/' . (int) $calendrier['projet_id'] . '/membres') ?>" <?= $dansUneFenetre ? 'data-fenetre' : '' ?>><?= e(t('cam.voir_projet')) ?></a>
  <?php elseif ($gere): ?>
    <h3 class="groupe-sous-titre"><?= e(t('cam.ajouter_amis')) ?></h3>
    <?php if ($aAjouter === []): ?>
      <p class="discret" style="margin:0"><?= e(t('cam.plus_d_amis')) ?></p>
    <?php else: ?>
      <form method="post" action="<?= url($base . '/membres') ?>" class="groupe-formulaire"<?= $envoi ?>>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <ul class="groupe-choix__liste">
          <?php foreach ($aAjouter as $a): ?>
            <li>
              <label class="groupe-choix__ami">
                <input type="checkbox" name="amis[]" value="<?= (int) $a['id'] ?>">
                <?= Amis::avatar((int) $a['id'], (string) $a['pseudo']) ?>
                <span><?= e((string) $a['pseudo']) ?></span>
              </label>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="actions"><button class="bouton" type="submit"><?= e(t('cam.ajouter_bouton')) ?></button></div>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</section>

<section class="carte profil-ami__section">
  <?php if ($gere): ?>
    <form method="post" action="<?= url($base . '/supprimer') ?>"<?= $envoi ?>
          data-confirmation="<?= e(t('cam.supprimer_confirmation', ['nom' => $calendrier['nom']])) ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <button class="bouton bouton--danger" type="submit"><?= e(t('cam.supprimer')) ?></button>
    </form>
    <p class="champ__aide"><?= e(t('cam.supprimer_aide')) ?></p>
  <?php elseif (!$duProjet): ?>
    <form method="post" action="<?= url($base . '/quitter') ?>"<?= $envoi ?>
          data-confirmation="<?= e(t('cam.quitter_confirmation', ['nom' => $calendrier['nom']])) ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <button class="bouton bouton--danger" type="submit"><?= e(t('cam.quitter')) ?></button>
    </form>
    <p class="champ__aide"><?= e(t('cam.quitter_aide')) ?></p>
  <?php endif; ?>
</section>
