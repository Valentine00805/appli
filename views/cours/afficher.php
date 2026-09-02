<?php
/** @var array $cours, $fichiers, $tags, $evenements */
$images = array_filter($fichiers, static fn (array $f): bool => Fichiers::estImage($f['mime']));
?>

<div class="entete-page">
  <div>
    <p class="discret" style="margin-bottom:.35rem">
      <a href="<?= url('cours') ?>">← Mes cours</a>
    </p>
    <h1><?= e($cours['titre']) ?></h1>
    <p>
      <?php if ($cours['matiere_nom'] !== null): ?>
        <span class="pastille" style="background:<?= e($cours['matiere_couleur']) ?>;color:<?= e(couleur_texte($cours['matiere_couleur'])) ?>">
          <?= e($cours['matiere_nom']) ?>
        </span>
      <?php endif; ?>
      <span class="discret">Créé le <?= e(date_fr($cours['created_at'], false)) ?>
        · modifié le <?= e(date_fr($cours['updated_at'])) ?></span>
    </p>
  </div>

  <div class="actions">
    <form method="post" action="<?= url('cours/' . $cours['id'] . '/favori') ?>" class="en-ligne">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <button class="bouton bouton--secondaire" type="submit"
              title="<?= (int) $cours['favori'] === 1 ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>">
        <?= (int) $cours['favori'] === 1 ? '⭐ Favori' : '☆ Favori' ?>
      </button>
    </form>
    <a class="bouton bouton--secondaire" href="<?= url('evenements/nouveau', ['cours' => $cours['id']]) ?>">Planifier</a>
    <a class="bouton" href="<?= url('cours/' . $cours['id'] . '/modifier') ?>">Modifier</a>
  </div>
</div>

<div class="colonnes">
  <article class="carte">
    <?php if (trim((string) $cours['contenu']) === ''): ?>
      <p class="discret">Ce cours n'a pas encore de contenu écrit.
        <a href="<?= url('cours/' . $cours['id'] . '/modifier') ?>">En ajouter</a>.</p>
    <?php else: ?>
      <div class="contenu-cours"><?= e($cours['contenu']) ?></div>
    <?php endif; ?>
  </article>

  <div class="pile">
    <section class="carte">
      <h2>Fichiers joints <span class="discret">(<?= count($fichiers) ?>)</span></h2>

      <?php if ($fichiers === []): ?>
        <p class="discret">Aucun fichier joint pour l'instant.</p>
      <?php else: ?>
        <ul class="liste-fichiers">
          <?php foreach ($fichiers as $f): ?>
            <li class="fichier">
              <span class="fichier__icone" aria-hidden="true"><?= Fichiers::icone($f['mime'], $f['nom_origine']) ?></span>
              <span style="min-width:0">
                <a class="fichier__nom" href="<?= url('fichiers/' . $f['id']) ?>" target="_blank" rel="noopener">
                  <?= e($f['nom_origine']) ?>
                </a><br>
                <span class="fichier__meta"><?= e(taille_lisible((int) $f['taille'])) ?></span>
              </span>
              <span class="fichier__actions">
                <a class="bouton bouton--discret bouton--petit"
                   href="<?= url('fichiers/' . $f['id'], ['telecharger' => 1]) ?>" title="Télécharger">⬇</a>
                <form method="post" action="<?= url('fichiers/' . $f['id'] . '/supprimer') ?>" class="en-ligne"
                      data-confirmation="Supprimer définitivement ce fichier ?">
                  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                  <button class="bouton bouton--discret bouton--petit" type="submit" title="Supprimer">✕</button>
                </form>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php // Déposer des fichiers ici, sans passer par « Modifier ». ?>
      <form method="post" action="<?= url('cours/' . $cours['id'] . '/fichiers') ?>"
            enctype="multipart/form-data" class="depot" data-depot>
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

        <label class="depot__zone" for="depot-<?= (int) $cours['id'] ?>">
          <span class="depot__icone" aria-hidden="true">📎</span>
          <span>
            <strong>Déposez vos fichiers ici</strong><br>
            <span class="discret">ou cliquez pour les choisir — PDF, images, Word, audio…
              <?= e(taille_lisible((int) Config::get('app', 'taille_max_fichier'))) ?> par fichier</span>
          </span>
        </label>

        <input type="file" id="depot-<?= (int) $cours['id'] ?>" name="fichiers[]" multiple
               class="depot__champ" data-depot-champ>
        <button class="bouton bouton--petit bouton--bloc" type="submit" data-depot-envoi>Joindre</button>
      </form>
    </section>

    <?php if ($tags !== []): ?>
      <section class="carte">
        <h2>Tags</h2>
        <div style="display:flex;gap:.4rem;flex-wrap:wrap">
          <?php foreach ($tags as $t): ?>
            <a class="pastille" href="<?= url('cours', ['tag' => $t['id']]) ?>">#<?= e($t['nom']) ?></a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($evenements !== []): ?>
      <section class="carte">
        <h2>Au calendrier</h2>
        <div class="pile">
          <?php foreach ($evenements as $evt): ?>
            <a class="evt-ligne" href="<?= url('evenements/' . $evt['id'] . '/modifier') ?>">
              <span>
                <span class="evt-ligne__titre"><?= e(icone_evenement($evt) . ' ' . $evt['titre']) ?></span><br>
                <span class="evt-ligne__meta"><?= e(date_fr($evt['debut'])) ?></span>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($images !== []): ?>
      <section class="carte">
        <h2>Aperçu des images</h2>
        <div class="grille" style="grid-template-columns:repeat(auto-fill,minmax(110px,1fr))">
          <?php foreach ($images as $f): ?>
            <a href="<?= url('fichiers/' . $f['id']) ?>" target="_blank" rel="noopener">
              <img src="<?= url('fichiers/' . $f['id']) ?>" alt="<?= e($f['nom_origine']) ?>"
                   loading="lazy" style="width:100%;height:100px;object-fit:cover;border-radius:8px">
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <form method="post" action="<?= url('cours/' . $cours['id'] . '/supprimer') ?>"
          data-confirmation="Supprimer ce cours et tous ses fichiers ? Cette action est définitive.">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <button class="bouton bouton--danger bouton--bloc" type="submit">Supprimer ce cours</button>
    </form>
  </div>
</div>
