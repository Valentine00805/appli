<?php
/**
 * Les cours et dossiers liés au groupe : chaque membre y range les siens ; les autres les lisent et les ajoutent à leur espace
 * (la copie d'un document partagé), et quitter le groupe retire l'accès.
 *
 * @var array $projet
 * @var list<array> $liens  ce qui est lié (voir Travaux::liens)
 * @var array{cours: list<array>, dossiers: list<array>} $aLier  mes cours et dossiers encore à lier
 * @var string $onglet
 */
$dansUneFenetre = $dansUneFenetre ?? false;
// Dans la fenêtre, on y reste : les formulaires s'y enregistrent, les documents s'y ouvrent.
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$ouvre = $dansUneFenetre ? ' data-fenetre' : '';
$csrf = Session::jetonCsrf();
$admin = $projet['role'] === 'admin';
$moi = Auth::id();
?>
<?= Vue::rendre('travaux/_onglets', ['projet' => $projet, 'onglet' => $onglet, 'dansUneFenetre' => $dansUneFenetre]) ?>

<div class="colonnes">
  <div class="pile">
    <p class="discret" style="margin:0"><?= e(t('tr.co.aide')) ?></p>
    <?php if ($liens === []): ?>
      <div class="vide">
        <span class="vide__icone">📘</span>
        <p><?= e(t('tr.co.aucun')) ?></p>
      </div>
    <?php else: ?>
      <section class="carte">
        <h2><?= e(t('tr.co.titre')) ?> <span class="discret">(<?= count($liens) ?>)</span></h2>
        <ul class="liste-fichiers travaux-liens">
          <?php foreach ($liens as $l): ?>
            <?php
            $dossier = $l['type'] === 'dossier';
            $mot = $dossier ? 'dossiers' : 'cours';
            $le_mien = $l['proprietaire_id'] === $moi;
            ?>
            <li class="fichier">
              <span class="fichier__icone" aria-hidden="true"><?= e($l['icone']) ?></span>
              <span style="min-width:0">
                <a class="fichier__nom" href="<?= url('partages/' . $mot . '/' . $l['cible_id'], $le_mien ? ['apercu' => 1] : []) ?>"<?= $ouvre ?>><?= e($l['titre']) ?></a>
                <?php if ($l['matiere'] !== null): ?>
                  <span class="pastille" style="background:<?= e($l['couleur'] ?: '#94a3b8') ?>;color:<?= e(couleur_texte($l['couleur'] ?: '#94a3b8')) ?>"><?= e($l['matiere']) ?></span>
                <?php endif; ?><br>
                <span class="fichier__meta">
                  <?= e(t('tr.co.lie_par', ['qui' => $l['par'] !== '' ? $l['par'] : t('tr.ancien_membre'), 'date' => date_fr($l['created_at'], false)])) ?>
                </span>
                <?= Vue::rendre('travaux/_evenements_lies', ['evenements' => LiensEvenements::evenementsDe((int) $projet['id'], $l['type'], (int) $l['cible_id']), 'ouvre' => $ouvre]) ?>
              </span>
              <span class="fichier__actions">
                <?php if (!$le_mien && $l['ma_copie'] !== null): ?>
                  <?php // Déjà copié chez moi : on le dit, et on mène à ma copie au lieu de reproposer l'ajout. La copie est à part : ses changements ne sont pas ceux du groupe. ?>
                  <span class="pastille pastille--ok" title="<?= e(t('tr.co.modifiable_aide')) ?>">✎ <?= e(t('tr.co.modifiable')) ?></span>
                  <span class="pastille pastille--ok"><?= e(t($dossier ? 'pt.deja_copie_dossier' : 'pt.deja_copie')) ?></span>
                  <a class="bouton bouton--discret bouton--petit" href="<?= url($dossier ? 'cours' : 'cours/' . $l['ma_copie'], $dossier ? ['dossier' => $l['ma_copie']] : []) ?>"><?= e(t('pt.ouvrir_ma_copie')) ?></a>
                <?php elseif (!$le_mien): ?>
                  <span class="pastille pastille--ok" title="<?= e(t('tr.co.modifiable_aide')) ?>">✎ <?= e(t('tr.co.modifiable')) ?></span>
                  <form method="post"<?= $envoi ?> action="<?= url('partages/' . $mot . '/' . $l['cible_id'] . '/copier') ?>" class="en-ligne">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button class="bouton bouton--secondaire bouton--petit" type="submit"><?= e(t($dossier ? 'tr.co.ajouter_dossier' : 'tr.co.ajouter_cours')) ?></button>
                  </form>
                <?php else: ?>
                  <span class="discret"><?= e(t('tr.co.le_mien')) ?></span>
                <?php endif; ?>
                <?php if ($admin || $l['ajoute_par'] === $moi): ?>
                  <form method="post"<?= $envoi ?> action="<?= url('travaux/liens/' . $l['id'] . '/supprimer') ?>" class="en-ligne"
                        data-confirmation="<?= e(t('tr.co.delier_confirmation', ['nom' => $l['titre']])) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button class="bouton bouton--discret bouton--petit" type="submit" title="<?= e(t('tr.co.delier')) ?>"
                            aria-label="<?= e(t('tr.co.delier_nom', ['nom' => $l['titre']])) ?>">✕</button>
                  </form>
                <?php endif; ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  </div>

  <div class="pile">
    <section class="carte">
      <h2><?= e(t('tr.co.lier_titre')) ?></h2>
      <?php if ($aLier['cours'] === [] && $aLier['dossiers'] === []): ?>
        <p class="discret" style="margin:0"><?= e(t('tr.co.rien_a_lier')) ?></p>
      <?php else: ?>
        <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/liens') ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <div class="champ">
            <label class="sr-only" for="travaux-lien"><?= e(t('tr.co.lier_titre')) ?></label>
            <select id="travaux-lien" name="lien" required>
              <option value=""><?= e(t('tr.co.choisir')) ?></option>
              <?php if ($aLier['dossiers'] !== []): ?>
                <optgroup label="<?= e(t('tr.co.mes_dossiers')) ?>">
                  <?php foreach ($aLier['dossiers'] as $d): ?>
                    <option value="dossier:<?= (int) $d['id'] ?>"><?= e(str_repeat('— ', (int) ($d['profondeur'] ?? 0)) . trim((string) ($d['icone'] ?? '') . ' ' . (string) $d['nom'])) ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endif; ?>
              <?php if ($aLier['cours'] !== []): ?>
                <optgroup label="<?= e(t('tr.co.mes_cours')) ?>">
                  <?php foreach ($aLier['cours'] as $c): ?>
                    <option value="cours:<?= (int) $c['id'] ?>">📘 <?= e((string) $c['titre']) ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endif; ?>
            </select>
          </div>
          <button class="bouton bouton--petit" type="submit"><?= e(t('tr.co.lier')) ?></button>
        </form>
      <?php endif; ?>
    </section>
  </div>
</div>
