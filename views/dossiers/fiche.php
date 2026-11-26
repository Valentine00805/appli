<?php
/**
 * La fiche de révision d'un dossier, présentée comme celle d'un cours : la note à retenir d'un côté, ce qui lui est rattaché de l'autre
 * (fichiers, liens, autres cours, calendrier — et, des cours du dossier, cartes, cartes mentales, diaporamas), puis ce que le dossier contient.
 *
 * @var array $dossier  id, nom, icone, couleur, fiche_revision, matiere_nom, matiere_couleur
 * @var array{cours: list<array>, sous: list<array>} $contenu
 * @var bool $elementsPossibles  la seconde migration est-elle passée ?
 * @var array $fichiersFiche, $parType, $desCours, $autresCours, $evenementsChoix
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$fiche = (string) ($dossier['fiche_revision'] ?? '');
$id = (int) $dossier['id'];
$vide = $contenu['cours'] === [] && $contenu['sous'] === [];
$ouverture = $dansUneFenetre ? ' data-fenetre' : '';
$elementsPossibles = $elementsPossibles ?? false;
$fichiersFiche = $fichiersFiche ?? [];
$parType = $parType ?? ['lien' => [], 'cours' => [], 'evenement' => []];
$desCours = $desCours ?? ['cartes' => ['total' => 0, 'a_revoir' => 0, 'avancement' => null], 'cartesMentales' => [], 'diaporamas' => []];
$autresCours = $autresCours ?? [];
$evenementsChoix = $evenementsChoix ?? [];
$cartes = $desCours['cartes'];
$ouvertureDessus = $dansUneFenetre ? 'data-fenetre-dessus' : 'data-fenetre';
$actionElements = url('dossiers/' . $id . '/revision/elements');

// Les cours d'un côté, les fichiers gardés tels quels (« simples ») de l'autre.
$coursDuDossier = [];
$fichiersDuDossier = [];
foreach ($contenu['cours'] as $c) {
    if ((int) $c['est_fichier'] === 1 && $c['fichier_id'] !== null) {
        $fichiersDuDossier[] = $c;
    } else {
        $coursDuDossier[] = $c;
    }
}
$nbElements = $elementsPossibles
    ? count($fichiersFiche) + array_sum(array_map('count', $parType)) + count($desCours['diaporamas']) + count($desCours['cartesMentales']) + ($cartes['total'] > 0 ? 1 : 0)
    : 0;
?>
<?php if ($elementsPossibles): ?>
  <?php // Le lecteur enregistre sa position sans recharger la page : il lui faut le jeton, comme dans la fiche d'un cours. ?>
  <span hidden data-jeton-lecture="<?= e(Session::jetonCsrf()) ?>"></span>
<?php endif; ?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large data-document' : '' ?>>
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p class="discret sans-impression" style="margin-bottom:.35rem"><a href="<?= url('revision') ?>"><?= e(t('fiche.retour_revision')) ?></a></p>
    <?php endif; ?>
    <h1 style="overflow-wrap:anywhere"><?= e((string) $dossier['icone']) ?> <?= e((string) $dossier['nom']) ?></h1>
    <p>
      <?php if ($dossier['matiere_nom'] !== null): ?>
        <span class="pastille" style="background:<?= e((string) $dossier['matiere_couleur']) ?>;color:<?= e(couleur_texte((string) $dossier['matiere_couleur'])) ?>"><?= e((string) $dossier['matiere_nom']) ?></span>
      <?php endif; ?>
      <span class="discret"><?= e(t('dosf.sous_titre')) ?></span>
    </p>
  </div>
  <div class="actions sans-impression">
    <?php if ($dansUneFenetre): ?>
      <a class="bouton bouton--secondaire" href="<?= url('dossiers/' . $id . '/revision', ['imprimer' => 1]) ?>" target="_blank" rel="noopener"><?= e(t('fiche.imprimer')) ?></a>
    <?php else: ?>
      <button class="bouton bouton--secondaire" type="button" onclick="window.print()"><?= e(t('fiche.imprimer')) ?></button>
    <?php endif; ?>
    <?php if (trim($fiche) !== ''): ?>
      <a class="bouton bouton--secondaire" href="<?= url('dossiers/' . $id . '/revision/pdf') ?>" title="<?= e(t('fiche.pdf_aide')) ?>">⬇ PDF</a>
    <?php endif; ?>
    <a class="bouton bouton--secondaire" href="<?= url('cours', ['dossier' => $id]) ?>"><?= e(t('dosf.voir_dossier')) ?></a>
  </div>
</div>

<div class="fiche-seule">
  <section class="carte fiche" id="revision">
    <div class="volet__entete">
      <span class="volet__icone" aria-hidden="true">📝</span>
      <div style="min-width:0">
        <h2 style="margin:0"><?= e(t('fiche.titre')) ?></h2>
      </div>
    </div>

    <div class="fiche-grille">
      <div class="fiche-grille__note">
        <form method="post" action="<?= url('dossiers/' . $id . '/revision') ?>"<?= $envoi ?> style="margin-top:1rem">
          <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
          <div class="champ">
            <label for="fiche_revision"><?= e(t('fiche.a_retenir')) ?></label>
            <textarea id="fiche_revision" name="fiche_revision" class="fiche__texte" data-texte-riche
                      placeholder="<?= e(t('dosf.exemple')) ?>"><?= e(TexteRiche::pourEditeur($fiche)) ?></textarea>
            <?php // Une zone de saisie s'imprime mal : cette copie ne sert qu'au papier, le script la tient à jour pendant la frappe. ?>
            <div class="fiche__impression texte-riche-affiche" data-impression-fiche aria-hidden="true"><?= TexteRiche::versHtml($fiche) ?></div>
            <span class="champ__aide"><?= e(t('fiche.mise_en_forme_aide')) ?></span>
          </div>
          <div class="actions">
            <button class="bouton" type="submit"><?= e(t('fiche.enregistrer')) ?></button>
            <a class="bouton bouton--discret" href="<?= url('revision') ?>"><?= e(t('fiche.retour_fiches')) ?></a>
          </div>
        </form>
      </div>

      <div class="fiche-grille__elements<?= $nbElements === 0 && $vide ? ' fiche-grille__elements--vide' : '' ?>">
        <?php if ($elementsPossibles): ?>
          <h3 class="volet__section" style="margin-top:0">
            <?= e(t('fiche.elements')) ?>
            <?php if ($nbElements > 0): ?><span class="discret">(<?= $nbElements ?>)</span><?php endif; ?>
          </h3>
          <p class="champ__aide" style="margin:-.35rem 0 .9rem"><?= e(t('dosf.elements_aide')) ?></p>

          <?php // --- Diaporamas : ceux des cours du dossier ------------------------------- ?>
          <div class="fiche__rayon<?= $desCours['diaporamas'] === [] ? ' fiche__rayon--vide' : '' ?>">
            <h4 class="fiche__titre"><?= e(t('fiche.diaporamas')) ?></h4>
            <?php if ($desCours['diaporamas'] === []): ?>
              <p class="discret fiche__vide"><?= e(t('dosf.dia_aucun')) ?></p>
            <?php else: ?>
              <ul class="fiche__liste">
                <?php foreach ($desCours['diaporamas'] as $dia): ?>
                  <li>
                    <a href="<?= url('diaporamas/' . (int) $dia['id']) ?>" <?= $ouvertureDessus ?>>▶ <?= e((string) $dia['titre']) ?></a>
                    <span class="fiche__url discret"><?= e(tn('dia.nb_diapos', (int) $dia['nombre'])) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>

          <?php // --- Fichiers et images : propres à la fiche du dossier -------------------- ?>
          <?= Vue::rendre('cours/_fiche_fichiers', [
              'fichiersFiche' => $fichiersFiche, 'actionDepot' => url('dossiers/' . $id . '/revision/fichiers'),
              'idDepot' => 'dossier-' . $id, 'surPlace' => $envoi, 'champPage' => '', 'dansUneFenetre' => $dansUneFenetre,
          ]) ?>

          <?php // --- Cartes de révision : celles des cours du dossier ---------------------- ?>
          <div class="fiche__rayon<?= $cartes['total'] === 0 ? ' fiche__rayon--vide' : '' ?>">
            <h4 class="fiche__titre"><?= e(t('fiche.cartes')) ?></h4>
            <?php if ($cartes['total'] === 0): ?>
              <p class="discret fiche__vide"><?= e(t('dosf.aucune_carte')) ?></p>
            <?php else: ?>
              <p class="fiche__cartes">
                <?= Vue::rendre('cours/_anneau', ['pourcentage' => $cartes['avancement'], 'titre' => t('fiche.cartes_avancement')]) ?>
                <span>
                  <?= tn('fiche.nb_cartes', (int) $cartes['total']) ?>
                  <?php if ($cartes['a_revoir'] > 0): ?>
                    · <span class="carte-du"><?= e(t('fiche.a_revoir', ['n' => $cartes['a_revoir']])) ?></span>
                  <?php else: ?>
                    · <span class="discret"><?= e(t('fiche.rien_a_revoir')) ?></span>
                  <?php endif; ?>
                </span>
              </p>
            <?php endif; ?>
            <p class="actions fiche__cartes-actions">
              <?php if ($cartes['a_revoir'] > 0): ?>
                <a class="bouton bouton--petit" href="<?= url('cartes/seance', ['dossier' => $id]) ?>" <?= $ouvertureDessus ?>><?= e(t('fiche.reviser')) ?></a>
              <?php endif; ?>
              <a class="bouton bouton--secondaire bouton--petit" href="<?= url('cartes') ?>"><?= e(t($cartes['total'] === 0 ? 'fiche.en_fabriquer' : 'fiche.voir_paquet')) ?></a>
            </p>
          </div>

          <?php // --- Cartes mentales : celles des cours du dossier ------------------------- ?>
          <div class="fiche__rayon<?= $desCours['cartesMentales'] === [] ? ' fiche__rayon--vide' : '' ?>">
            <h4 class="fiche__titre"><?= e(t('fiche.cartes_mentales')) ?></h4>
            <?php if ($desCours['cartesMentales'] === []): ?>
              <p class="discret fiche__vide"><?= e(t('dosf.aucune_cm')) ?></p>
            <?php else: ?>
              <ul class="fiche__cartes-mentales">
                <?php foreach ($desCours['cartesMentales'] as $cm): ?>
                  <li>
                    <a href="<?= url('cartes-mentales/' . (int) $cm['id']) ?>" <?= $ouvertureDessus ?>><?= e((string) $cm['titre']) ?></a>
                    <span class="discret">
                      <?= e(tn('cm.idees', (int) $cm['idees'])) ?><?php if ((int) $cm['ia'] === 1): ?> · <?= e(t('cm.ia_badge')) ?><?php endif; ?>
                    </span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <p class="actions">
              <a class="bouton bouton--secondaire bouton--petit" href="<?= url('resumes') ?>"><?= e(t('fiche.resumer_ia')) ?></a>
            </p>
          </div>

          <?php // --- Liens web ------------------------------------------------------------- ?>
          <div class="fiche__rayon<?= $parType['lien'] === [] ? ' fiche__rayon--vide' : '' ?>">
            <h4 class="fiche__titre"><?= e(t('fiche.liens')) ?></h4>
            <?php if ($parType['lien'] === []): ?>
              <p class="discret fiche__vide"><?= e(t('commun.rien')) ?></p>
            <?php else: ?>
              <ul class="fiche__liste">
                <?php foreach ($parType['lien'] as $lien): ?>
                  <li>
                    <a href="<?= e((string) $lien['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e((string) $lien['libelle']) ?> ↗</a>
                    <span class="fiche__url discret"><?= e((string) parse_url((string) $lien['url'], PHP_URL_HOST)) ?></span>
                    <?= Vue::rendre('cours/_retirer-element', ['element' => $lien, 'surPage' => false, 'dansUneFenetre' => $dansUneFenetre]) ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <details class="fiche__ajout">
              <summary><?= e(t('fiche.ajouter_lien')) ?></summary>
              <form<?= $envoi ?> method="post" action="<?= $actionElements ?>">
                <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                <input type="hidden" name="type" value="lien">
                <div class="champ">
                  <label for="lien-url"><?= e(t('fiche.adresse')) ?></label>
                  <input type="url" id="lien-url" name="url" required placeholder="<?= e(t('fiche.url_exemple')) ?>">
                </div>
                <div class="champ">
                  <label for="lien-libelle"><?= e(t('fiche.intitule')) ?> <span class="discret"><?= e(t('commun.facultatif')) ?></span></label>
                  <input type="text" id="lien-libelle" name="libelle" maxlength="200" placeholder="<?= e(t('fiche.lien_exemple')) ?>">
                </div>
                <button class="bouton bouton--petit" type="submit"><?= e(t('fiche.ajouter_le_lien')) ?></button>
              </form>
            </details>
          </div>

          <?php // --- Renvois vers d'autres cours ------------------------------------------- ?>
          <div class="fiche__rayon<?= $parType['cours'] === [] ? ' fiche__rayon--vide' : '' ?>">
            <h4 class="fiche__titre"><?= e(t('fiche.autres_cours')) ?></h4>
            <?php if ($parType['cours'] === []): ?>
              <p class="discret fiche__vide"><?= e(t('commun.rien')) ?></p>
            <?php else: ?>
              <ul class="fiche__liste">
                <?php foreach ($parType['cours'] as $renvoi): ?>
                  <li>
                    <a href="<?= url('cours/' . (int) $renvoi['cible_cours_id']) ?>"><?= e((string) $renvoi['cours_titre']) ?></a>
                    <?php if (($renvoi['libelle'] ?? '') !== ''): ?>
                      <span class="fiche__url discret"><?= e((string) $renvoi['libelle']) ?></span>
                    <?php endif; ?>
                    <?= Vue::rendre('cours/_retirer-element', ['element' => $renvoi, 'surPage' => false, 'dansUneFenetre' => $dansUneFenetre]) ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <?php if ($autresCours === []): ?>
              <p class="champ__aide"><?= e(t('fiche.aucun_autre_cours')) ?></p>
            <?php else: ?>
              <details class="fiche__ajout">
                <summary><?= e(t('fiche.renvoyer_cours')) ?></summary>
                <form<?= $envoi ?> method="post" action="<?= $actionElements ?>">
                  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                  <input type="hidden" name="type" value="cours">
                  <div class="champ">
                    <label for="renvoi-cours"><?= e(t('fiche.cours')) ?></label>
                    <select id="renvoi-cours" name="cible" required>
                      <?php foreach ($autresCours as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= e((string) $c['titre']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="champ">
                    <label for="renvoi-note"><?= e(t('fiche.pourquoi')) ?> <span class="discret"><?= e(t('commun.facultatif')) ?></span></label>
                    <input type="text" id="renvoi-note" name="libelle" maxlength="200" placeholder="<?= e(t('fiche.renvoi_exemple')) ?>">
                  </div>
                  <button class="bouton bouton--petit" type="submit"><?= e(t('fiche.ajouter_renvoi')) ?></button>
                </form>
              </details>
            <?php endif; ?>
          </div>

          <?php // --- Évènements du calendrier ---------------------------------------------- ?>
          <div class="fiche__rayon<?= $parType['evenement'] === [] ? ' fiche__rayon--vide' : '' ?>">
            <h4 class="fiche__titre"><?= e(t('fiche.au_calendrier')) ?></h4>
            <?php if ($parType['evenement'] === []): ?>
              <p class="discret fiche__vide"><?= e(t('commun.rien')) ?></p>
            <?php else: ?>
              <ul class="fiche__liste">
                <?php foreach ($parType['evenement'] as $renvoi): ?>
                  <li>
                    <a href="<?= url('evenements/' . (int) $renvoi['cible_evenement_id']) ?>" data-fenetre><?= e(($renvoi['type_icone'] ?? '📌') . ' ' . (string) $renvoi['evenement_titre']) ?></a>
                    <span class="fiche__url discret">
                      <?= e(date_fr((string) $renvoi['evenement_debut'], (int) $renvoi['journee_entiere'] === 0)) ?>
                      <?= (int) $renvoi['termine'] === 1 ? e(t('fiche.termine')) : '' ?>
                    </span>
                    <?= Vue::rendre('cours/_retirer-element', ['element' => $renvoi, 'surPage' => false, 'dansUneFenetre' => $dansUneFenetre]) ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <?php if ($evenementsChoix === []): ?>
              <p class="champ__aide"><?= e(t('fiche.calendrier_vide')) ?></p>
            <?php else: ?>
              <details class="fiche__ajout">
                <summary><?= e(t('fiche.rattacher_evenement')) ?></summary>
                <form<?= $envoi ?> method="post" action="<?= $actionElements ?>">
                  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                  <input type="hidden" name="type" value="evenement">
                  <div class="champ">
                    <label for="renvoi-evt"><?= e(t('fiche.evenement')) ?></label>
                    <select id="renvoi-evt" name="cible" required>
                      <?php foreach ($evenementsChoix as $evt): ?>
                        <option value="<?= (int) $evt['id'] ?>"><?= e(date_fr((string) $evt['debut'], false) . ' — ' . $evt['titre']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <button class="bouton bouton--petit" type="submit"><?= e(t('fiche.rattacher')) ?></button>
                </form>
              </details>
            <?php endif; ?>
          </div>

          <h3 class="volet__section"><?= e(t('dosf.contenu')) ?></h3>
        <?php else: ?>
          <h3 class="volet__section" style="margin-top:0">
            <?= e(t('dosf.contenu')) ?>
            <?php if (!$vide): ?><span class="discret">(<?= count($contenu['sous']) + count($contenu['cours']) ?>)</span><?php endif; ?>
          </h3>
        <?php endif; ?>

        <?php if ($vide): ?>
          <p class="discret fiche__vide"><?= e(t('dosf.vide')) ?></p>
        <?php endif; ?>

        <?php if ($contenu['sous'] !== []): ?>
          <div class="fiche__rayon">
            <h4 class="fiche__titre"><?= e(t('dosf.rayon_sous')) ?></h4>
            <ul class="liste-fichiers">
              <?php foreach ($contenu['sous'] as $s): ?>
                <li class="fichier fichier--dossier">
                  <span class="fichier__icone" aria-hidden="true"><?= e((string) $s['icone']) ?></span>
                  <span style="min-width:0">
                    <a class="fichier__nom" href="<?= url('dossiers/' . (int) $s['id'] . '/revision') ?>"<?= $ouverture ?>><?= e((string) $s['nom']) ?></a><br>
                    <span class="fichier__meta"><?= e(tn('dosf.nb_cours', (int) $s['nb_cours'])) ?><?= (int) $s['a_fiche'] === 1 ? ' · 📝 ' . e(t('dosf.a_fiche')) : '' ?></span>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if ($coursDuDossier !== []): ?>
          <div class="fiche__rayon">
            <h4 class="fiche__titre"><?= e(t('dosf.rayon_cours')) ?></h4>
            <ul class="liste-fichiers">
              <?php foreach ($coursDuDossier as $c): ?>
                <li class="fichier">
                  <span class="fichier__icone" aria-hidden="true">📄</span>
                  <span style="min-width:0">
                    <a class="fichier__nom" href="<?= url('cours/' . (int) $c['id']) ?>"<?= $ouverture ?>><?= e((string) $c['titre']) ?></a><br>
                    <span class="fichier__meta">
                      <?php if ((int) $c['a_fiche'] === 1): ?>
                        <a href="<?= url('revision/' . (int) $c['id']) ?>"<?= $ouverture ?>>📝 <?= e(t('dosf.sa_fiche')) ?></a>
                      <?php else: ?>
                        <?= e(t('dosf.cours')) ?>
                      <?php endif; ?>
                    </span>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if ($fichiersDuDossier !== []): ?>
          <div class="fiche__rayon">
            <h4 class="fiche__titre"><?= e(t('dosf.rayon_fichiers')) ?></h4>
            <ul class="liste-fichiers">
              <?php foreach ($fichiersDuDossier as $c): ?>
                <?php
                $apercu = ApercuDocument::possible((string) $c['fichier_nom']);
                $adresse = url('fichiers/' . (int) $c['fichier_id'] . ($apercu ? '/apercu' : ''));
                ?>
                <li class="fichier">
                  <span class="fichier__icone" aria-hidden="true"><?= e(Fichiers::icone((string) $c['fichier_mime'], (string) $c['fichier_nom'])) ?></span>
                  <span style="min-width:0">
                    <a class="fichier__nom" href="<?= $adresse ?>"<?= $apercu ? $ouverture : ' target="_blank" rel="noopener"' ?>><?= e((string) $c['titre']) ?></a><br>
                    <span class="fichier__meta">
                      <?php if ((int) $c['a_fiche'] === 1): ?>
                        <a href="<?= url('revision/' . (int) $c['id']) ?>"<?= $ouverture ?>>📝 <?= e(t('dosf.sa_fiche')) ?></a>
                      <?php else: ?>
                        <?= e(t('dosf.fichier')) ?>
                      <?php endif; ?>
                    </span>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>
