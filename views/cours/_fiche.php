<?php
/**
 * La fiche de révision d'un cours : son texte et ce qui lui est rattaché.
 *
 * Le même fragment sert au volet ouvert depuis un cours et à la page qui
 * ne montre que la fiche. $surPage dit laquelle, pour que les formulaires
 * ramènent là d'où l'on vient.
 *
 * @var array $cours, $fichiersFiche, $parType, $autresCours, $evenementsChoix
 * @var array $cartes  combien de cartes a ce cours, et combien sont dues
 * @var string $fiche
 * @var bool $surPage
 */
$surPage = $surPage ?? false;
$nbElements = count($fichiersFiche) + array_sum(array_map('count', $parType));

// Sur sa propre page, chaque formulaire doit y ramener plutôt que d'ouvrir le cours.
$champPage = $surPage ? '<input type="hidden" name="page" value="fiche">' : '';

// Le lecteur enregistre sa position sans recharger la page : il lui faut le
// jeton, que les formulaires portent déjà mais qu'aucun ne lui prête.
$jetonLecture = Session::jetonCsrf();

// L'avancement de la fiche entière : la moyenne des anneaux qu'elle contient.
$avancementFiche = avancement_anneaux(fichiers_suivis($fichiersFiche));
?>
<span hidden data-jeton-lecture="<?= e($jetonLecture) ?>"></span>

<section class="carte fiche<?= $surPage ? '' : ' volet' ?>" id="revision">
  <div class="volet__entete">
    <span class="volet__icone" aria-hidden="true">📝</span>
    <div style="min-width:0">
      <h2 style="margin:0">Fiche de révision</h2>
      <?php if (!$surPage): ?>
        <p class="discret" style="margin:.15rem 0 0"><?= e($cours['titre']) ?></p>
      <?php endif; ?>
    </div>

    <?php if ($avancementFiche['total'] > 0): ?>
      <?php
      /*
       * Où l'on en est de toute la fiche : la moyenne des anneaux ci-dessous.
       * Le script la refait à chaque fois que l'un d'eux bouge, pour qu'elle
       * ne mente pas pendant qu'on écoute ou qu'on tourne une page.
       */
      ?>
      <span class="fiche__total" data-total-fiche>
        <?= Vue::rendre('cours/_anneau', [
            'pourcentage' => $avancementFiche['pourcentage'],
            'titre'       => 'Avancement de cette fiche',
        ]) ?>
        <span class="fiche__total-mot">
          <?= $avancementFiche['total'] ?> document<?= $avancementFiche['total'] > 1 ? 's' : '' ?><br>
          suivi<?= $avancementFiche['total'] > 1 ? 's' : '' ?>
        </span>
      </span>
    <?php endif; ?>
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
          // Un PDF se lit sur place, comme une vidéo : le navigateur sait le faire seul.
          $estPdf = Fichiers::estPdf((string) $f['mime'], (string) $f['nom_origine']);
          // Pour un PDF, « durée » veut dire nombre de pages, et « position » page atteinte.
          $pages = $estPdf ? (int) $f['duree_lecture'] : 0;
          // Page atteinte : zéro tant qu'on n'a pas tourné de page, comme un
          // enregistrement jamais lancé. La page ouverte, elle, vaut au moins 1.
          $pageAtteinte = $pages > 0 ? min(max(0, (int) $f['position_lecture']), $pages) : 0;
          $pageLue = max(1, $pageAtteinte);
          ?>
          <li class="fichier<?= $estAudio || $estVideo || $estPdf ? ' fichier--media' : '' ?>">
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

            <?php if ($estAudio || $estVideo || $pages > 1): ?>
              <?php
              /*
               * L'anneau dit où l'on en est : dans l'enregistrement pour un
               * média, dans les pages pour un PDF. Le lecteur et les flèches
               * le mettent à jour en cours de route ; sans JavaScript, il
               * montre la dernière position connue.
               */
              $avance = avancement_lecture($f);
              ?>
              <span class="fichier__avancement" data-avancement="<?= (int) $f['id'] ?>">
                <?= Vue::rendre('cours/_anneau', [
                    'pourcentage' => $avance,
                    'titre'       => 'Avancement de « ' . $f['nom_origine'] . ' »',
                ]) ?>
                <span class="fichier__minutage">
                  <?php if ($pages > 1 && $pageAtteinte > 0): ?>
                    Page <?= $pageAtteinte ?> sur <?= $pages ?>
                  <?php elseif ($pages > 1): ?>
                    pas encore lu
                  <?php elseif ((int) $f['duree_lecture'] > 0): ?>
                    <?= e(duree_lisible((int) $f['position_lecture'])) ?>
                    / <?= e(duree_lisible((int) $f['duree_lecture'])) ?>
                  <?php else: ?>
                    pas encore lu
                  <?php endif; ?>
                </span>
              </span>
            <?php endif; ?>

            <?php // Le lecteur du navigateur suffit : rien à charger de plus. ?>
            <?php if ($estAudio): ?>
              <audio class="fichier__lecteur" controls preload="metadata"
                     data-lecteur="<?= (int) $f['id'] ?>"
                     data-position="<?= (int) $f['position_lecture'] ?>"
                     data-position-url="<?= url('fichiers/' . $f['id'] . '/position') ?>"
                     src="<?= url('fichiers/' . $f['id']) ?>">
                <a href="<?= url('fichiers/' . $f['id'], ['telecharger' => 1]) ?>">
                  Télécharger l'enregistrement
                </a>
              </audio>
            <?php elseif ($estVideo): ?>
              <video class="fichier__lecteur fichier__lecteur--video" controls preload="metadata"
                     data-lecteur="<?= (int) $f['id'] ?>"
                     data-position="<?= (int) $f['position_lecture'] ?>"
                     data-position-url="<?= url('fichiers/' . $f['id'] . '/position') ?>"
                     src="<?= url('fichiers/' . $f['id']) ?>">
                <a href="<?= url('fichiers/' . $f['id'], ['telecharger' => 1]) ?>">
                  Télécharger la vidéo
                </a>
              </video>
            <?php endif; ?>

            <?php if ($estPdf): ?>
              <?php
              /*
               * La visionneuse du navigateur, dans la fiche même : pas de page
               * intermédiaire pour relire deux annales. « lazy » évite de
               * charger tous les documents d'un coup quand il y en a plusieurs.
               */
              ?>
              <span class="fichier__pdf" data-pdf="<?= (int) $f['id'] ?>"
                    data-pages="<?= $pages ?>" data-page="<?= $pageLue ?>"
                    data-atteinte="<?= $pageAtteinte ?>"
                    data-position-url="<?= url('fichiers/' . $f['id'] . '/position') ?>">
                <?php // Le volet est étroit : la page est ajustée à sa largeur, sans le panneau des vignettes. ?>
                <iframe src="<?= url('fichiers/' . $f['id']) ?>#page=<?= $pageLue ?>&amp;navpanes=0&amp;view=FitH"
                        loading="lazy" title="<?= e($f['nom_origine']) ?>"></iframe>

                <?php if ($pages > 1): ?>
                  <?php
                  /*
                   * La visionneuse du navigateur ne dit pas où l'on en est : ces
                   * flèches sont notre seul moyen de le savoir, et elles font
                   * avancer l'anneau à mesure qu'on tourne les pages.
                   */
                  ?>
                  <span class="fichier__pages">
                    <button class="bouton bouton--discret bouton--petit" type="button"
                            data-pdf-recule title="Page précédente">◀</button>
                    <span class="fichier__page" data-pdf-libelle aria-live="polite">
                      Page <?= $pageLue ?> sur <?= $pages ?>
                    </span>
                    <button class="bouton bouton--discret bouton--petit" type="button"
                            data-pdf-avance title="Page suivante">▶</button>
                  </span>
                <?php endif; ?>

                <span class="fichier__repli">
                  Le document ne s'affiche pas ?
                  <a href="<?= url('fichiers/' . $f['id']) ?>" target="_blank" rel="noopener">
                    L'ouvrir dans un onglet
                  </a>
                </span>
              </span>
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

  <?php // --- Cartes de révision --------------------------------------- ?>
  <?php
  /*
   * Les cartes du cours, vues depuis sa fiche : combien il y en a, combien
   * sont dues aujourd'hui, et de quoi s'y mettre. Le paquet lui-même se gère
   * ailleurs — ici, on ne fait que le rejoindre.
   */
  $cartes = $cartes ?? ['total' => 0, 'a_revoir' => 0];
  ?>
  <div class="fiche__rayon<?= $cartes['total'] === 0 ? ' fiche__rayon--vide' : '' ?>">
    <h4 class="fiche__titre">🃏 Cartes</h4>

    <?php if ($cartes['total'] === 0): ?>
      <p class="discret fiche__vide">Aucune carte pour ce cours.</p>
    <?php else: ?>
      <p class="fiche__cartes">
        <strong><?= $cartes['total'] ?></strong> carte<?= $cartes['total'] > 1 ? 's' : '' ?>
        <?php if ($cartes['a_revoir'] > 0): ?>
          · <span class="carte-du"><?= $cartes['a_revoir'] ?> à revoir</span>
        <?php else: ?>
          · <span class="discret">rien à revoir aujourd'hui</span>
        <?php endif; ?>
      </p>
    <?php endif; ?>

    <p class="actions fiche__cartes-actions">
      <?php if ($cartes['a_revoir'] > 0): ?>
        <a class="bouton bouton--petit"
           href="<?= url('cartes/seance', ['cours' => $cours['id']]) ?>">Réviser</a>
      <?php endif; ?>
      <a class="bouton bouton--secondaire bouton--petit"
         href="<?= url('cours/' . $cours['id'] . '/cartes') ?>">
        <?= $cartes['total'] === 0 ? 'En fabriquer' : 'Voir le paquet' ?>
      </a>
    </p>
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
