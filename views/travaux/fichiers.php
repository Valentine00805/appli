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
        <p>Aucun fichier pour l’instant : les sources, les brouillons, les diapositives… tout le groupe les retrouvera ici.</p>
      </div>
    <?php else: ?>
      <section class="carte">
        <h2>Fichiers du groupe <span class="discret">(<?= count($fichiers) ?>)</span></h2>
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
                  · déposé par <?= e((string) ($f['depose_par'] ?? 'un ancien membre')) ?>
                  le <?= e(date_fr((string) $f['created_at'], false)) ?>
                </span>
              </span>
              <span class="fichier__actions">
                <a class="bouton bouton--discret bouton--petit"
                   href="<?= url('travaux/fichiers/' . (int) $f['id'], ['telecharger' => 1]) ?>"
                   title="Télécharger" aria-label="Télécharger <?= e((string) $f['nom_origine']) ?>">⬇</a>
                <?php if ($admin || (int) ($f['user_id'] ?? 0) === Auth::id()): ?>
                  <form method="post"<?= $envoi ?> action="<?= url('travaux/fichiers/' . (int) $f['id'] . '/supprimer') ?>" class="en-ligne"
                        data-confirmation="Supprimer « <?= e((string) $f['nom_origine']) ?> » pour tout le groupe ?">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button class="bouton bouton--discret bouton--petit" type="submit" title="Supprimer"
                            aria-label="Supprimer <?= e((string) $f['nom_origine']) ?>">✕</button>
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
      <h2>Déposer</h2>
      <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/fichiers') ?>" enctype="multipart/form-data" class="depot" data-depot>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <label class="depot__zone" for="depot-travaux">
          <span class="depot__icone" aria-hidden="true">📎</span>
          <span>
            <strong>Déposez vos fichiers ici</strong><br>
            <span class="discret">ou cliquez pour les choisir —
              <?= e(taille_lisible(Fichiers::tailleMax())) ?> par fichier</span>
          </span>
        </label>
        <input type="file" id="depot-travaux" name="fichiers[]" multiple class="depot__champ" data-depot-champ>
        <button class="bouton bouton--petit bouton--bloc" type="submit" data-depot-envoi>Déposer</button>
      </form>
    </section>
  </div>
</div>
