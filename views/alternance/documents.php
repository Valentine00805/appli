<?php
/**
 * Les documents de l'alternance, rangés par catégorie.
 *
 * @var array $parCategorie  catégorie => documents
 * @var string $onglet
 * @var array|null $situation
 */
$csrf = Session::jetonCsrf();
$total = array_sum(array_map('count', $parCategorie));
?>
<?= Vue::rendre('alternance/_onglets', ['onglet' => $onglet, 'situation' => $situation]) ?>

<div class="entete-page">
  <div>
    <h1>📁 Documents d’alternance</h1>
    <p>Le contrat, le livret d’apprentissage, les évaluations, le rapport : tout au même endroit.</p>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <?php if ($total === 0): ?>
      <div class="vide">
        <span class="vide__icone">📁</span>
        <p>Aucun document pour l’instant. Déposez le premier avec « Déposer ».</p>
      </div>
    <?php endif; ?>

    <?php foreach (Alternance::CATEGORIES as $cle => $cat): ?>
      <?php if ($parCategorie[$cle] === []) { continue; } ?>
      <section class="carte">
        <h2><?= $cat['icone'] ?> <?= e($cat['nom']) ?> <span class="discret">(<?= count($parCategorie[$cle]) ?>)</span></h2>
        <ul class="liste-fichiers">
          <?php foreach ($parCategorie[$cle] as $d): ?>
            <li class="fichier">
              <span class="fichier__icone" aria-hidden="true"><?= Fichiers::icone($d['mime'], $d['nom_origine']) ?></span>
              <span style="min-width:0">
                <a class="fichier__nom" href="<?= url('alternance/documents/' . (int) $d['id']) ?>" target="_blank" rel="noopener">
                  <?= e($d['nom_origine']) ?>
                </a><br>
                <span class="fichier__meta">
                  <?= e(taille_lisible((int) $d['taille'])) ?> · déposé le <?= e(date_fr((string) $d['created_at'], false)) ?>
                </span>
              </span>
              <span class="fichier__actions">
                <a class="bouton bouton--discret bouton--petit"
                   href="<?= url('alternance/documents/' . (int) $d['id'], ['telecharger' => 1]) ?>"
                   title="Télécharger" aria-label="Télécharger <?= e($d['nom_origine']) ?>">⬇</a>
                <form method="post" action="<?= url('alternance/documents/' . (int) $d['id'] . '/categorie') ?>"
                      class="en-ligne alternance-ranger" data-auto-envoi>
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <label class="sr-only" for="ranger-<?= (int) $d['id'] ?>">Ranger « <?= e($d['nom_origine']) ?> » dans</label>
                  <select id="ranger-<?= (int) $d['id'] ?>" name="categorie">
                    <?php foreach (Alternance::CATEGORIES as $autre => $c): ?>
                      <option value="<?= e($autre) ?>"<?= $autre === $cle ? ' selected' : '' ?>><?= e($c['nom']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <noscript><button class="bouton bouton--discret bouton--petit" type="submit">Ranger</button></noscript>
                </form>
                <form method="post" action="<?= url('alternance/documents/' . (int) $d['id'] . '/supprimer') ?>" class="en-ligne"
                      data-confirmation="Supprimer définitivement « <?= e($d['nom_origine']) ?> » ?">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <button class="bouton bouton--discret bouton--petit" type="submit" title="Supprimer"
                          aria-label="Supprimer <?= e($d['nom_origine']) ?>">✕</button>
                </form>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
  </div>

  <div class="pile">
    <section class="carte">
      <h2>Déposer</h2>
      <form method="post" action="<?= url('alternance/documents') ?>" enctype="multipart/form-data" class="depot" data-depot>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="champ">
          <label for="categorie">Ranger dans</label>
          <select id="categorie" name="categorie">
            <?php foreach (Alternance::CATEGORIES as $cle => $cat): ?>
              <option value="<?= e($cle) ?>"><?= $cat['icone'] ?> <?= e($cat['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <label class="depot__zone" for="depot-alternance">
          <span class="depot__icone" aria-hidden="true">📎</span>
          <span>
            <strong>Déposez vos fichiers ici</strong><br>
            <span class="discret">ou cliquez pour les choisir — PDF, images, Word…
              <?= e(taille_lisible(Fichiers::tailleMax())) ?> par fichier</span>
          </span>
        </label>
        <input type="file" id="depot-alternance" name="fichiers[]" multiple class="depot__champ" data-depot-champ>
        <button class="bouton bouton--petit bouton--bloc" type="submit" data-depot-envoi>Déposer</button>
      </form>
    </section>
  </div>
</div>
