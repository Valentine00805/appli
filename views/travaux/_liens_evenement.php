<?php
/**
 * Les documents du projet liés à un évènement du projet (un évènement du calendrier commun, ou une échéance).
 *
 * La carte se lit d'abord : la liste, avec de quoi ouvrir chaque document. Le bouton « Modifier » l'ouvre à la modification — retirer un
 * lien, en ajouter un —, et « Terminer » la referme. Ajouter ou retirer un lien revient sur la carte encore ouverte (« ?liens=1 »), pour
 * en enchaîner plusieurs.
 *
 * @var int $projet  le projet
 * @var string $type  « evenement » ou « echeance »
 * @var int $id  l'évènement
 * @var list<array> $liens  voir LiensEvenements::liensDe
 * @var array{cours: list<array>, dossiers: list<array>, fichiers: list<array>} $aLier  voir LiensEvenements::aLier
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$ouvre = $dansUneFenetre ? ' data-fenetre' : '';
$csrf = Session::jetonCsrf();
$rien = $aLier['cours'] === [] && $aLier['dossiers'] === [] && $aLier['fichiers'] === [];
$edition = ($_GET['liens'] ?? '') === '1';
?>
<section class="carte" style="margin-top:1rem" data-liens>
  <div class="entete-carte" style="display:flex;align-items:center;justify-content:space-between;gap:.6rem;flex-wrap:wrap">
    <h2 style="margin:0">🔗 <?= e(t('cam.liens_titre')) ?></h2>
    <button class="bouton bouton--secondaire bouton--petit" type="button" data-liens-modifier<?= $edition ? ' hidden' : '' ?>><?= e(t('commun.modifier')) ?></button>
    <button class="bouton bouton--secondaire bouton--petit" type="button" data-liens-terminer<?= $edition ? '' : ' hidden' ?>><?= e(t('cam.liens_terminer')) ?></button>
  </div>

  <?php if ($liens === []): ?>
    <p class="discret" style="margin:.6rem 0 0"><?= e(t('cam.liens_aucun')) ?></p>
  <?php else: ?>
    <ul class="liste-fichiers travaux-liens" style="margin-top:.6rem">
      <?php foreach ($liens as $l): ?>
        <li class="fichier">
          <span class="fichier__icone" aria-hidden="true"><?= e($l['icone']) ?></span>
          <span style="min-width:0">
            <a class="fichier__nom" href="<?= e($l['url']) ?>"<?= $l['type'] === 'fichier' ? ' target="_blank" rel="noopener"' : $ouvre ?>><?= e($l['titre']) ?></a>
          </span>
          <span class="fichier__actions" data-liens-edition<?= $edition ? '' : ' hidden' ?>>
            <form method="post"<?= $envoi ?> action="<?= url('travaux/evenements-liens/' . $l['id'] . '/supprimer') ?>" class="en-ligne">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--discret bouton--petit" type="submit" title="<?= e(t('tr.co.delier')) ?>"
                      aria-label="<?= e(t('cam.liens_delier_nom', ['nom' => $l['titre']])) ?>">✕</button>
            </form>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <div data-liens-edition<?= $edition ? '' : ' hidden' ?> style="margin-top:.8rem">
    <?php if ($rien): ?>
      <p class="champ__aide" style="margin:0"><?= e(t('cam.liens_rien_a_lier')) ?></p>
    <?php else: ?>
      <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet . '/evenements-liens') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="evenement_type" value="<?= e($type) ?>">
        <input type="hidden" name="evenement_id" value="<?= (int) $id ?>">
        <div class="champ" style="margin-bottom:.5rem">
          <label class="sr-only" for="lien-evt-<?= e($type) ?>-<?= (int) $id ?>"><?= e(t('cam.liens_titre')) ?></label>
          <select id="lien-evt-<?= e($type) ?>-<?= (int) $id ?>" name="lien" required>
            <option value=""><?= e(t('cam.liens_choisir')) ?></option>
            <?php if ($aLier['cours'] !== [] || $aLier['dossiers'] !== []): ?>
              <optgroup label="<?= e(t('cam.liens_cours')) ?>">
                <?php foreach ($aLier['dossiers'] as $d): ?>
                  <option value="dossier:<?= (int) $d['cible_id'] ?>"><?= e(trim($d['icone'] . ' ' . $d['titre'])) ?></option>
                <?php endforeach; ?>
                <?php foreach ($aLier['cours'] as $c): ?>
                  <option value="cours:<?= (int) $c['cible_id'] ?>"><?= e(trim($c['icone'] . ' ' . $c['titre'])) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
            <?php if ($aLier['fichiers'] !== []): ?>
              <optgroup label="<?= e(t('cam.liens_fichiers')) ?>">
                <?php foreach ($aLier['fichiers'] as $f): ?>
                  <option value="fichier:<?= (int) $f['id'] ?>">📎 <?= e((string) $f['nom_origine']) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
          </select>
        </div>
        <button class="bouton bouton--petit" type="submit"><?= e(t('cam.liens_lier')) ?></button>
        <span class="champ__aide" style="display:block;margin-top:.4rem"><?= e(t('cam.liens_aide')) ?></span>
      </form>
    <?php endif; ?>
  </div>
</section>
