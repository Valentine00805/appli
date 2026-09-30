<?php
/**
 * Les fichiers du groupe : tout le monde dépose, tout le monde télécharge.
 *
 * @var array $projet
 * @var list<array> $fichiers
 * @var string $onglet
 */
$dansUneFenetre = $dansUneFenetre ?? false;
// Dans la fenêtre, on y reste : les formulaires s'y enregistrent.
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$csrf = Session::jetonCsrf();
$admin = $projet['role'] === 'admin';
?>
<?= Vue::rendre('travaux/_onglets', ['projet' => $projet, 'onglet' => $onglet, 'dansUneFenetre' => $dansUneFenetre]) ?>

<div class="colonnes">
  <div class="pile">
    <?php if ($fichiers === []): ?>
      <div class="vide">
        <span class="vide__icone">📎</span>
        <p><?= e(t('tr.fi.aucun')) ?></p>
      </div>
    <?php else: ?>
      <section class="carte">
        <h2><?= e(t('tr.fi.titre')) ?> <span class="discret">(<?= count($fichiers) ?>)</span></h2>
        <ul class="liste-fichiers">
          <?php foreach ($fichiers as $f): ?>
            <li class="fichier">
              <span class="fichier__icone" aria-hidden="true"><?= Fichiers::icone((string) $f['mime'], (string) $f['nom_origine']) ?></span>
              <span style="min-width:0">
                <a class="fichier__nom" href="<?= url('travaux/fichiers/' . (int) $f['id']) ?>" target="_blank" rel="noopener">
                  <?= e((string) $f['nom_origine']) ?>
                </a><br>
                <span class="fichier__meta">
                  <?= e(taille_lisible((int) $f['taille'])) ?>
                  <?= e(t('tr.fi.depose_par', [
                      'qui' => (string) ($f['depose_par'] ?? t('tr.ancien_membre')),
                      'date' => date_fr((string) $f['created_at'], false),
                  ])) ?>
                </span>
              </span>
              <span class="fichier__actions">
                <a class="bouton bouton--discret bouton--petit"
                   href="<?= url('travaux/fichiers/' . (int) $f['id'], ['telecharger' => 1]) ?>"
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
      </section>
    <?php endif; ?>
  </div>

  <div class="pile">
    <section class="carte">
      <h2><?= e(t('tr.fi.deposer')) ?></h2>
      <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/fichiers') ?>" enctype="multipart/form-data" class="depot" data-depot>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <label class="depot__zone" for="depot-travaux">
          <span class="depot__icone" aria-hidden="true">📎</span>
          <span>
            <strong><?= e(t('tr.fi.zone')) ?></strong><br>
            <span class="discret"><?= e(t('tr.fi.zone_aide', ['taille' => taille_lisible(Fichiers::tailleMax())])) ?></span>
          </span>
        </label>
        <input type="file" id="depot-travaux" name="fichiers[]" multiple class="depot__champ" data-depot-champ>
        <button class="bouton bouton--petit bouton--bloc" type="submit" data-depot-envoi><?= e(t('tr.fi.deposer')) ?></button>
      </form>
    </section>
  </div>
</div>
