<?php
/**
 * @var array $cours, $fichiers, $tags, $evenements
 * @var array $fichiersFiche, $elements, $autresCours, $evenementsChoix
 * @var bool $revision  le volet de révision est-il ouvert ?
 */
$images = array_filter($fichiers, static fn (array $f): bool => Fichiers::estImage($f['mime']));
$fiche = (string) ($cours['fiche_revision'] ?? '');

// Les éléments arrivent triés par type : on les range par rayon pour l'affichage.
$parType = ['lien' => [], 'cours' => [], 'evenement' => []];
foreach ($elements as $element) {
    $parType[$element['type']][] = $element;
}
$nbElements = count($elements) + count($fichiersFiche);
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
    <?php // Le même bouton ouvre et referme le volet. ?>
    <a class="bouton bouton--secondaire<?= $revision ? ' est-actif' : '' ?>"
       href="<?= $revision
           ? url('cours/' . $cours['id'])
           : url('cours/' . $cours['id'], ['revision' => 1]) . '#revision' ?>">
      📝 Révision<?= $fiche !== '' || $nbElements > 0 ? ' •' : '' ?>
    </a>
    <a class="bouton bouton--secondaire" href="<?= url('evenements/nouveau', ['cours' => $cours['id']]) ?>">Planifier</a>
    <a class="bouton" href="<?= url('cours/' . $cours['id'] . '/modifier') ?>">Modifier</a>
  </div>
</div>

<div class="colonnes<?= $revision ? ' colonnes--volet' : '' ?>">
  <article class="carte">
    <?php if (trim((string) $cours['contenu']) === ''): ?>
      <p class="discret">Ce cours n'a pas encore de contenu écrit.
        <a href="<?= url('cours/' . $cours['id'] . '/modifier') ?>">En ajouter</a>.</p>
    <?php else: ?>
      <div class="contenu-cours"><?= e($cours['contenu']) ?></div>
    <?php endif; ?>
  </article>

  <?php if ($revision): ?>
    <section class="carte volet fiche" id="revision">
      <div class="volet__entete">
        <span class="volet__icone" aria-hidden="true">📝</span>
        <div style="min-width:0">
          <h2 style="margin:0">Fiche de révision</h2>
          <p class="discret" style="margin:.15rem 0 0"><?= e($cours['titre']) ?></p>
        </div>
      </div>

      <form method="post" action="<?= url('cours/' . $cours['id'] . '/revision') ?>" style="margin-top:1rem">
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

        <div class="champ">
          <label for="fiche_revision">Ce qu'il faut retenir</label>
          <textarea id="fiche_revision" name="fiche_revision" class="fiche__texte"
                    placeholder="Définitions, formules, dates, plan du chapitre, questions à se poser…"><?= e($fiche) ?></textarea>
          <span class="champ__aide">Le texte est affiché tel quel, sauts de ligne compris.</span>
        </div>

        <div class="actions">
          <button class="bouton" type="submit">Enregistrer la fiche</button>
          <a class="bouton bouton--discret" href="<?= url('cours/' . $cours['id']) ?>">Fermer</a>
        </div>
      </form>

      <hr class="separateur">

      <h3 class="volet__section" style="margin-top:0">
        Éléments rattachés
        <?php if ($nbElements > 0): ?><span class="discret">(<?= $nbElements ?>)</span><?php endif; ?>
      </h3>
      <p class="champ__aide" style="margin:-.35rem 0 .9rem">
        Ce qui ne vient pas du cours lui-même : documents, liens, autres chapitres, échéances.
      </p>

      <?php // --- Fichiers et images propres à la fiche ------------------- ?>
      <div class="fiche__rayon">
        <h4 class="fiche__titre">📎 Fichiers et images</h4>

        <?php if ($fichiersFiche === []): ?>
          <p class="discret fiche__vide">Rien pour l'instant.</p>
        <?php else: ?>
          <ul class="liste-fichiers">
            <?php foreach ($fichiersFiche as $f): ?>
              <?php $estImage = Fichiers::estImage($f['mime']); ?>
              <li class="fichier">
                <?php if ($estImage): ?>
                  <a href="<?= url('fichiers/' . $f['id']) ?>" target="_blank" rel="noopener" class="fiche__vignette">
                    <img src="<?= url('fichiers/' . $f['id']) ?>" alt="<?= e($f['nom_origine']) ?>" loading="lazy">
                  </a>
                <?php else: ?>
                  <span class="fichier__icone" aria-hidden="true"><?= Fichiers::icone($f['mime'], $f['nom_origine']) ?></span>
                <?php endif; ?>
                <span style="min-width:0">
                  <?php $apercu = ApercuDocument::possible((string) $f['nom_origine']); ?>
                  <a class="fichier__nom"
                     href="<?= url('fichiers/' . $f['id'] . ($apercu ? '/apercu' : '')) ?>"
                     <?= $apercu ? '' : ' target="_blank" rel="noopener"' ?>>
                    <?= e($f['nom_origine']) ?>
                  </a><br>
                  <span class="fichier__meta"><?= e(taille_lisible((int) $f['taille'])) ?></span>
                </span>
                <span class="fichier__actions">
                  <a class="bouton bouton--discret bouton--petit"
                     href="<?= url('fichiers/' . $f['id'], ['telecharger' => 1]) ?>" title="Télécharger">⬇</a>
                  <form method="post" action="<?= url('fichiers/' . $f['id'] . '/supprimer') ?>" class="en-ligne"
                        data-confirmation="Retirer ce fichier de la fiche ?">
                    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                    <button class="bouton bouton--discret bouton--petit" type="submit" title="Retirer">✕</button>
                  </form>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <form method="post" action="<?= url('cours/' . $cours['id'] . '/revision/fichiers') ?>"
              enctype="multipart/form-data" class="depot depot--mince" data-depot>
          <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
          <label class="depot__zone" for="depot-fiche-<?= (int) $cours['id'] ?>">
            <span class="depot__icone" aria-hidden="true">📎</span>
            <span><strong>Déposez ici</strong> <span class="discret">— photo du tableau, schéma, annales…</span></span>
          </label>
          <input type="file" id="depot-fiche-<?= (int) $cours['id'] ?>" name="fichiers[]" multiple
                 class="depot__champ" data-depot-champ>
          <button class="bouton bouton--petit bouton--bloc" type="submit" data-depot-envoi>Joindre à la fiche</button>
        </form>
      </div>

      <?php // --- Liens web ----------------------------------------------- ?>
      <div class="fiche__rayon">
        <h4 class="fiche__titre">🔗 Liens</h4>

        <?php if ($parType['lien'] === []): ?>
          <p class="discret fiche__vide">Rien pour l'instant.</p>
        <?php else: ?>
          <ul class="fiche__liste">
            <?php foreach ($parType['lien'] as $lien): ?>
              <li>
                <a href="<?= e((string) $lien['url']) ?>" target="_blank" rel="noopener noreferrer">
                  <?= e((string) $lien['libelle']) ?> ↗
                </a>
                <span class="fiche__url discret"><?= e((string) parse_url((string) $lien['url'], PHP_URL_HOST)) ?></span>
                <?= Vue::rendre('cours/_retirer-element', ['element' => $lien]) ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <details class="fiche__ajout">
          <summary>+ Ajouter un lien</summary>
          <form method="post" action="<?= url('cours/' . $cours['id'] . '/revision/elements') ?>">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <input type="hidden" name="type" value="lien">
            <div class="champ">
              <label for="lien-url">Adresse</label>
              <input type="url" id="lien-url" name="url" required placeholder="https://…">
            </div>
            <div class="champ">
              <label for="lien-libelle">Intitulé <span class="discret">(facultatif)</span></label>
              <input type="text" id="lien-libelle" name="libelle" maxlength="200"
                     placeholder="Vidéo sur les fonctions affines">
            </div>
            <button class="bouton bouton--petit" type="submit">Ajouter le lien</button>
          </form>
        </details>
      </div>

      <?php // --- Renvois vers d'autres cours ----------------------------- ?>
      <div class="fiche__rayon">
        <h4 class="fiche__titre">📘 Autres cours</h4>

        <?php if ($parType['cours'] === []): ?>
          <p class="discret fiche__vide">Rien pour l'instant.</p>
        <?php else: ?>
          <ul class="fiche__liste">
            <?php foreach ($parType['cours'] as $renvoi): ?>
              <li>
                <a href="<?= url('cours/' . (int) $renvoi['cible_cours_id']) ?>">
                  <?= e((string) $renvoi['cours_titre']) ?>
                </a>
                <?php if (($renvoi['libelle'] ?? '') !== ''): ?>
                  <span class="fiche__url discret"><?= e((string) $renvoi['libelle']) ?></span>
                <?php endif; ?>
                <?= Vue::rendre('cours/_retirer-element', ['element' => $renvoi]) ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <?php if ($autresCours === []): ?>
          <p class="champ__aide">Vous n'avez pas d'autre cours pour l'instant.</p>
        <?php else: ?>
          <details class="fiche__ajout">
            <summary>+ Renvoyer vers un cours</summary>
            <form method="post" action="<?= url('cours/' . $cours['id'] . '/revision/elements') ?>">
              <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
              <input type="hidden" name="type" value="cours">
              <div class="champ">
                <label for="renvoi-cours">Cours</label>
                <select id="renvoi-cours" name="cible" required>
                  <?php foreach ($autresCours as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= e($c['titre']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="champ">
                <label for="renvoi-note">Pourquoi <span class="discret">(facultatif)</span></label>
                <input type="text" id="renvoi-note" name="libelle" maxlength="200"
                       placeholder="Les dérivées y sont expliquées">
              </div>
              <button class="bouton bouton--petit" type="submit">Ajouter le renvoi</button>
            </form>
          </details>
        <?php endif; ?>
      </div>

      <?php // --- Évènements du calendrier -------------------------------- ?>
      <div class="fiche__rayon">
        <h4 class="fiche__titre">📅 Au calendrier</h4>

        <?php if ($parType['evenement'] === []): ?>
          <p class="discret fiche__vide">Rien pour l'instant.</p>
        <?php else: ?>
          <ul class="fiche__liste">
            <?php foreach ($parType['evenement'] as $renvoi): ?>
              <li>
                <a href="<?= url('evenements/' . (int) $renvoi['cible_evenement_id'] . '/modifier') ?>">
                  <?= e(($renvoi['type_icone'] ?? '📌') . ' ' . (string) $renvoi['evenement_titre']) ?>
                </a>
                <span class="fiche__url discret">
                  <?= e(date_fr((string) $renvoi['evenement_debut'], (int) $renvoi['journee_entiere'] === 0)) ?>
                  <?= (int) $renvoi['termine'] === 1 ? '· terminé' : '' ?>
                </span>
                <?= Vue::rendre('cours/_retirer-element', ['element' => $renvoi]) ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <?php if ($evenementsChoix === []): ?>
          <p class="champ__aide">Votre calendrier est encore vide.</p>
        <?php else: ?>
          <details class="fiche__ajout">
            <summary>+ Rattacher un évènement</summary>
            <form method="post" action="<?= url('cours/' . $cours['id'] . '/revision/elements') ?>">
              <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
              <input type="hidden" name="type" value="evenement">
              <div class="champ">
                <label for="renvoi-evt">Évènement</label>
                <select id="renvoi-evt" name="cible" required>
                  <?php foreach ($evenementsChoix as $evt): ?>
                    <option value="<?= (int) $evt['id'] ?>">
                      <?= e(date_fr((string) $evt['debut'], false) . ' — ' . $evt['titre']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <button class="bouton bouton--petit" type="submit">Rattacher</button>
            </form>
          </details>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

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
                <?php // Un document Word ou PowerPoint s'ouvre en aperçu texte,
                      // le navigateur ne sachant pas l'afficher lui-même. ?>
                <?php $apercu = ApercuDocument::possible((string) $f['nom_origine']); ?>
                <a class="fichier__nom"
                   href="<?= url('fichiers/' . $f['id'] . ($apercu ? '/apercu' : '')) ?>"
                   <?= $apercu ? '' : ' target="_blank" rel="noopener"' ?>>
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
