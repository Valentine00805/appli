<?php
/**
 * La fiche de révision d'un cours : son texte et ce qui lui est rattaché.
 *
 * Le même fragment sert au volet ouvert depuis un cours et à la page qui
 * ne montre que la fiche. $surPage dit laquelle, pour que les formulaires
 * ramènent là d'où l'on vient.
 *
 * @var array $cours, $fichiersFiche, $parType, $autresCours, $evenementsChoix
 * @var string $fiche
 * @var bool $surPage
 */
$surPage = $surPage ?? false;
$nbElements = count($fichiersFiche) + array_sum(array_map('count', $parType));

// Sur sa propre page, chaque formulaire doit y ramener plutôt que d'ouvrir le cours.
$champPage = $surPage ? '<input type="hidden" name="page" value="fiche">' : '';
?>

<section class="carte fiche<?= $surPage ? '' : ' volet' ?>" id="revision">
  <div class="volet__entete">
    <span class="volet__icone" aria-hidden="true">📝</span>
    <div style="min-width:0">
      <h2 style="margin:0">Fiche de révision</h2>
      <?php if (!$surPage): ?>
        <p class="discret" style="margin:.15rem 0 0"><?= e($cours['titre']) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <?php
  /*
   * Où l'on en est : c'est l'utilisateur qui le dit, l'application se contente
   * d'en faire un total sur l'onglet Révision. Un bouton par état, celui en
   * cours étant simplement mis en avant.
   */
  $etatActuel = (int) ($cours['etat_revision'] ?? 0);
  ?>
  <div class="fiche-etat" role="group" aria-label="Avancement de cette révision">
    <?php foreach (etats_revision() as $valeur => $etat): ?>
      <form method="post" action="<?= url('cours/' . $cours['id'] . '/revision/etat') ?>" class="en-ligne">
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>"><?= $champPage ?>
        <input type="hidden" name="etat" value="<?= (int) $valeur ?>">
        <button type="submit"
                class="fiche-etat__choix fiche-etat__choix--<?= e($etat['classe']) ?><?= $valeur === $etatActuel ? ' est-actif' : '' ?>"
                <?= $valeur === $etatActuel ? ' aria-pressed="true"' : '' ?>>
          <span aria-hidden="true"><?= $etat['icone'] ?></span> <?= e($etat['libelle']) ?>
        </button>
      </form>
    <?php endforeach; ?>
  </div>

  <?php // Sur sa propre page, la note et ce qui lui est rattaché se font face. ?>
  <div class="fiche-grille">
    <div class="fiche-grille__note">
  <form method="post" action="<?= url('cours/' . $cours['id'] . '/revision') ?>" style="margin-top:1rem">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>"><?= $champPage ?>

    <div class="champ">
      <label for="fiche_revision">Ce qu'il faut retenir</label>
      <textarea id="fiche_revision" name="fiche_revision" class="fiche__texte"
                placeholder="Définitions, formules, dates, plan du chapitre, questions à se poser…"><?= e($fiche) ?></textarea>
      <?php
      /*
       * Une zone de saisie s'imprime mal : seule la partie visible sort, avec
       * sa barre de défilement. Cette copie ne sert qu'au papier, et le script
       * la tient à jour pendant la frappe pour qu'on puisse imprimer un texte
       * pas encore enregistré.
       */
      ?>
      <div class="fiche__impression" data-impression-fiche aria-hidden="true"><?= e($fiche) ?></div>
      <span class="champ__aide">Le texte est affiché tel quel, sauts de ligne compris.</span>
    </div>

    <div class="actions">
      <button class="bouton" type="submit">Enregistrer la fiche</button>
      <?php if ($surPage): ?>
        <a class="bouton bouton--discret" href="<?= url('revision') ?>">Retour aux fiches</a>
      <?php else: ?>
        <a class="bouton bouton--discret" href="<?= url('cours/' . $cours['id']) ?>">Fermer</a>
      <?php endif; ?>
    </div>
  </form>

    </div>

    <div class="fiche-grille__elements<?= $nbElements === 0 ? ' fiche-grille__elements--vide' : '' ?>">

  <h3 class="volet__section" style="margin-top:0">
    Éléments rattachés
    <?php if ($nbElements > 0): ?><span class="discret">(<?= $nbElements ?>)</span><?php endif; ?>
  </h3>
  <p class="champ__aide" style="margin:-.35rem 0 .9rem">
    Ce qui ne vient pas du cours lui-même : documents, liens, autres chapitres, échéances.
  </p>

  <?php // --- Fichiers et images propres à la fiche ------------------- ?>
  <div class="fiche__rayon<?= $fichiersFiche === [] ? ' fiche__rayon--vide' : '' ?>">
    <h4 class="fiche__titre">📎 Fichiers et images</h4>

    <?php if ($fichiersFiche === []): ?>
      <p class="discret fiche__vide">Rien pour l'instant.</p>
    <?php else: ?>
      <ul class="liste-fichiers">
        <?php foreach ($fichiersFiche as $f): ?>
          <?php
          $estImage = Fichiers::estImage($f['mime']);
          $estAudio = Fichiers::estAudio((string) $f['mime'], (string) $f['nom_origine']);
          $estVideo = Fichiers::estVideo((string) $f['mime'], (string) $f['nom_origine']);
          ?>
          <li class="fichier<?= $estAudio || $estVideo ? ' fichier--media' : '' ?>">
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
                <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>"><?= $champPage ?>
                <button class="bouton bouton--discret bouton--petit" type="submit" title="Retirer">✕</button>
              </form>
            </span>

            <?php // Le lecteur du navigateur suffit : rien à charger de plus. ?>
            <?php if ($estAudio): ?>
              <audio class="fichier__lecteur" controls preload="metadata"
                     src="<?= url('fichiers/' . $f['id']) ?>">
                <a href="<?= url('fichiers/' . $f['id'], ['telecharger' => 1]) ?>">
                  Télécharger l'enregistrement
                </a>
              </audio>
            <?php elseif ($estVideo): ?>
              <video class="fichier__lecteur fichier__lecteur--video" controls preload="metadata"
                     src="<?= url('fichiers/' . $f['id']) ?>">
                <a href="<?= url('fichiers/' . $f['id'], ['telecharger' => 1]) ?>">
                  Télécharger la vidéo
                </a>
              </video>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="post" action="<?= url('cours/' . $cours['id'] . '/revision/fichiers') ?>"
          enctype="multipart/form-data" class="depot depot--mince" data-depot>
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>"><?= $champPage ?>
      <label class="depot__zone" for="depot-fiche-<?= (int) $cours['id'] ?>">
        <span class="depot__icone" aria-hidden="true">📎</span>
        <span><strong>Déposez ici</strong>
          <span class="discret">— photo du tableau, schéma, annales, audio, vidéo…</span></span>
      </label>
      <input type="file" id="depot-fiche-<?= (int) $cours['id'] ?>" name="fichiers[]" multiple
             class="depot__champ" data-depot-champ>
      <button class="bouton bouton--petit bouton--bloc" type="submit" data-depot-envoi>Joindre à la fiche</button>
    </form>
  </div>

  <?php // --- Liens web ----------------------------------------------- ?>
  <div class="fiche__rayon<?= $parType['lien'] === [] ? ' fiche__rayon--vide' : '' ?>">
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
            <?= Vue::rendre('cours/_retirer-element', ['element' => $lien, 'surPage' => $surPage]) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <details class="fiche__ajout">
      <summary>+ Ajouter un lien</summary>
      <form method="post" action="<?= url('cours/' . $cours['id'] . '/revision/elements') ?>">
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>"><?= $champPage ?>
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
  <div class="fiche__rayon<?= $parType['cours'] === [] ? ' fiche__rayon--vide' : '' ?>">
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
            <?= Vue::rendre('cours/_retirer-element', ['element' => $renvoi, 'surPage' => $surPage]) ?>
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
          <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>"><?= $champPage ?>
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
  <div class="fiche__rayon<?= $parType['evenement'] === [] ? ' fiche__rayon--vide' : '' ?>">
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
            <?= Vue::rendre('cours/_retirer-element', ['element' => $renvoi, 'surPage' => $surPage]) ?>
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
          <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>"><?= $champPage ?>
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
    </div>
  </div>
</section>
