<?php
/**
 * Les livrables du groupe : les documents finaux à rendre (le rapport, le diaporama, les annexes…). Chacun a son titre, sa date de rendu, sa
 * consigne, ses documents, et un bouton « Marquer comme rendu ». Un nouveau livrable se crée dans une fenêtre à part (« + Nouveau livrable »).
 *
 * @var array $projet
 * @var list<array<string, mixed>> $livrables  chacun avec ses « fichiers » et son « etat » (a_rendre | pret | rendu)
 * @var string $onglet
 */
$dansUneFenetre = $dansUneFenetre ?? false;
// Dans la fenêtre, on y reste : les formulaires s'y enregistrent.
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$csrf = Session::jetonCsrf();
$idProjet = (int) $projet['id'];
$admin = (string) $projet['role'] === 'admin';
$total = count($livrables);
$rendus = count(array_filter($livrables, static fn (array $l): bool => $l['etat'] === 'rendu'));
$part = $total === 0 ? null : (int) round($rendus * 100 / $total);
?>
<?= Vue::rendre('travaux/_onglets', ['projet' => $projet, 'onglet' => $onglet, 'dansUneFenetre' => $dansUneFenetre]) ?>

<div class="pile" style="max-width:48rem">
  <p class="discret" style="margin:0"><?= e(t('tr.lv.aide')) ?></p>

  <p class="actions" style="margin:0">
    <a class="bouton bouton--petit" href="<?= url('travaux/' . $idProjet . '/livrables/nouveau') ?>" data-fenetre><?= e(t('tr.lv.nouveau')) ?></a>
  </p>

  <?php if ($total === 0): ?>
    <div class="vide">
      <span class="vide__icone">📦</span>
      <p><?= e(t('tr.lv.aucun')) ?></p>
      <p class="discret"><?= e(t('tr.lv.aucun_aide')) ?></p>
    </div>
  <?php else: ?>
    <section class="carte">
      <div class="bloc-liste__entete">
        <h2 style="margin:0"><?= e(t('tr.lv.bilan', ['rendus' => $rendus, 'total' => $total])) ?></h2>
        <?php if ($rendus === $total): ?><span class="pastille pastille--ok">✅ <?= e(t('tr.lv.tout_rendu')) ?></span><?php endif; ?>
      </div>
      <div class="jauge" title="<?= e(t('tr.li.part_faite', ['n' => $part])) ?>"><span style="width:<?= $part ?>%;background:var(--accent)"></span></div>
    </section>
  <?php endif; ?>

  <?php foreach ($livrables as $l): ?>
    <?php
    $lid = (int) $l['id'];
    $rendu = $l['etat'] === 'rendu';
    $fichiers = $l['fichiers'];
    $retard = echeance_libelle($l['echeance'], $rendu);
    ?>
    <section class="carte livrable livrable--<?= e($l['etat']) ?>" id="livrable-<?= $lid ?>">
      <div class="bloc-liste__entete">
        <h2><?= e((string) $l['titre']) ?></h2>
        <?php if ($rendu): ?>
          <span class="pastille pastille--ok">✅ <?= e(t('tr.lv.etat_rendu_court')) ?></span>
        <?php elseif ($l['etat'] === 'pret'): ?>
          <span class="pastille pastille--active"><?= e(t('tr.lv.etat_pret')) ?></span>
        <?php else: ?>
          <span class="pastille pastille--muette"><?= e(t('tr.lv.etat_a_rendre')) ?></span>
        <?php endif; ?>
        <input type="checkbox" id="livrable-ouvrir-<?= $lid ?>" class="acces-bascule sr-only">
        <label for="livrable-ouvrir-<?= $lid ?>" class="bouton bouton--secondaire bouton--petit acces-bouton" role="button"><?= e(t('commun.modifier')) ?></label>
        <div class="acces-panneau">
          <form method="post"<?= $envoi ?> action="<?= url('travaux/livrables/' . $lid . '/modifier') ?>" class="acces-panneau__droit">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <label class="acces-panneau__etiquette" for="titre-livrable-<?= $lid ?>"><?= e(t('tr.lv.titre_label')) ?></label>
            <input type="text" id="titre-livrable-<?= $lid ?>" name="titre" required maxlength="<?= Travaux::LIVRABLE_TITRE_MAX ?>" value="<?= e((string) $l['titre']) ?>" autocomplete="off">
            <label class="acces-panneau__etiquette" for="date-livrable-<?= $lid ?>" style="margin-top:.7rem"><?= e(t('tr.lv.date_label')) ?></label>
            <input type="date" id="date-livrable-<?= $lid ?>" name="echeance" value="<?= e((string) ($l['echeance'] ?? '')) ?>">
            <label class="acces-panneau__etiquette" for="note-livrable-<?= $lid ?>" style="margin-top:.7rem"><?= e(t('tr.lv.note_label')) ?></label>
            <input type="text" id="note-livrable-<?= $lid ?>" name="note" maxlength="<?= Travaux::LIVRABLE_NOTE_MAX ?>" value="<?= e((string) ($l['note'] ?? '')) ?>" autocomplete="off">
            <div class="acces-panneau__ligne" style="margin-top:.8rem">
              <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
              <label for="livrable-ouvrir-<?= $lid ?>" class="bouton bouton--discret" role="button"><?= e(t('commun.annuler')) ?></label>
            </div>
          </form>
          <?php if ($admin): ?>
            <form method="post"<?= $envoi ?> action="<?= url('travaux/livrables/' . $lid . '/supprimer') ?>" class="acces-panneau__retrait"
                  data-confirmation="<?= e(t('tr.lv.supprimer_confirmation', ['titre' => (string) $l['titre']])) ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('tr.lv.supprimer')) ?></button>
            </form>
          <?php else: ?>
            <p class="discret" style="margin:0;font-size:.85rem"><?= e(t('tr.lv.supprimer_admin')) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <p class="livrable__meta">
        <?php if ($rendu): ?>
          <span class="discret"><?= e(t('tr.lv.etat_rendu', ['date' => date_fr((string) $l['rendu_le'], false), 'qui' => (string) ($l['rendu_par_nom'] ?? t('tr.ancien_membre'))])) ?></span>
        <?php elseif ($retard !== ''): ?>
          <span class="echeance echeance--<?= e(echeance_etat($l['echeance'], false)) ?>">📅 <?= e(t('tr.lv.a_rendre_le', ['date' => $retard])) ?></span>
        <?php endif; ?>
      </p>
      <?php if ((string) ($l['note'] ?? '') !== ''): ?>
        <p class="livrable__note"><?= nl2br(e((string) $l['note'])) ?></p>
      <?php endif; ?>

      <?php if ($fichiers === []): ?>
        <p class="discret" style="margin:.6rem 0"><?= e(t('tr.lv.aucun_fichier')) ?></p>
      <?php else: ?>
        <ul class="liste-fichiers">
          <?php foreach ($fichiers as $f): ?>
            <li class="fichier">
              <span class="fichier__icone" aria-hidden="true"><?= Fichiers::icone((string) $f['mime'], (string) $f['nom_origine']) ?></span>
              <span style="min-width:0">
                <?= Vue::rendre('travaux/_nom_fichier', ['f' => $f, 'dansUneFenetre' => $dansUneFenetre]) ?><br>
                <span class="fichier__meta">
                  <?= e(taille_lisible((int) $f['taille'])) ?>
                  <?= e(t('tr.fi.depose_par', ['qui' => (string) ($f['depose_par'] ?? t('tr.ancien_membre')), 'date' => date_fr((string) $f['created_at'], false)])) ?>
                </span>
              </span>
              <span class="fichier__actions">
                <a class="bouton bouton--discret bouton--petit" href="<?= url('travaux/fichiers/' . (int) $f['id'], ['telecharger' => 1]) ?>"
                   title="<?= e(t('tr.fi.telecharger')) ?>" aria-label="<?= e(t('tr.fi.telecharger_nom', ['nom' => (string) $f['nom_origine']])) ?>">⬇</a>
                <?php if ($admin || (int) ($f['user_id'] ?? 0) === Auth::id()): ?>
                  <form method="post"<?= $envoi ?> action="<?= url('travaux/fichiers/' . (int) $f['id'] . '/supprimer') ?>" class="en-ligne"
                        data-confirmation="<?= e(t('tr.fi.supprimer_confirmation', ['nom' => (string) $f['nom_origine']])) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button class="bouton bouton--discret bouton--petit" type="submit" title="<?= e(t('tr.fi.supprimer')) ?>"
                            aria-label="<?= e(t('tr.fi.supprimer_nom', ['nom' => (string) $f['nom_origine']])) ?>">✕</button>
                  </form>
                <?php endif; ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <form method="post"<?= $envoi ?> action="<?= url('travaux/livrables/' . $lid . '/fichiers') ?>" enctype="multipart/form-data" class="depot" data-depot>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <label class="depot__zone" for="depot-livrable-<?= $lid ?>">
          <span class="depot__icone" aria-hidden="true">📎</span>
          <span>
            <strong><?= e(t('tr.lv.zone')) ?></strong><br>
            <span class="discret"><?= e(t('tr.fi.zone_aide', ['taille' => taille_lisible(Fichiers::tailleMax())])) ?></span>
          </span>
        </label>
        <input type="file" id="depot-livrable-<?= $lid ?>" name="fichiers[]" multiple class="depot__champ" data-depot-champ>
        <button class="bouton bouton--petit bouton--bloc" type="submit" data-depot-envoi><?= e(t('tr.fi.deposer')) ?></button>
      </form>

      <form method="post"<?= $envoi ?> action="<?= url('travaux/livrables/' . $lid . '/rendre') ?>" class="livrable__rendre"
            <?php if (!$rendu && $fichiers === []): ?>data-confirmation="<?= e(t('tr.lv.rendre_sans_fichier', ['titre' => (string) $l['titre']])) ?>"<?php endif; ?>>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="rendu" value="<?= $rendu ? '0' : '1' ?>">
        <?php if ($rendu): ?>
          <button class="bouton bouton--discret" type="submit">↩ <?= e(t('tr.lv.pas_rendu')) ?></button>
        <?php else: ?>
          <button class="bouton" type="submit">✅ <?= e(t('tr.lv.rendre')) ?></button>
        <?php endif; ?>
      </form>
    </section>
  <?php endforeach; ?>
</div>
