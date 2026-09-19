<?php
/**
 * Un document partagé, en lecture : pour un ami (dans l'application) ou pour
 * n'importe qui (par le lien public).
 *
 * @var string $type   « cours » ou « fichier »
 * @var array $cible
 * @var bool $public   ouvert par le lien public
 * @var list<array> $fichiers  les fichiers joints d'un cours, ou ceux d'une fiche
 * @var list<array> $liens     les liens d'une fiche
 * @var list<array> $groupes   le contenu d'un dossier : ses cours, par sous-dossier
 * @var callable $adresseFichier  (int $id, bool $telecharger): string
 * @var list<array> $mesCours  où ranger la copie d'un fichier
 * @var bool $recu     il figure dans « Partagés avec moi »
 * @var string $droit  lecture, commentaire ou modification
 * @var list<array> $commentaires
 * @var string $mot
 * @var bool $dansUneFenetre  rendu seul, pour être posé dans une fenêtre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$droit = $droit ?? 'lecture';
$commentaires = $commentaires ?? [];
// Le public ne fait que lire : ce qui suit ne vaut que dans l'application.
$peutEcrire = !$public && Partages::permet($droit, 'modification');
$peutCommenter = !$public && Partages::permet($droit, 'commentaire');
$surPlace = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$proprietaire = (string) $cible['proprietaire'];
$csrf = $public ? '' : Session::jetonCsrf();
$base = 'partages/' . $mot . '/' . (int) $cible['id'];
$fichierSeul = $type === 'fichier';
$estFiche = $type === 'fiche';
$estDossier = $type === 'dossier';
$estEvenement = $type === 'evenement';
// Déjà dans mon calendrier — ajouté par moi, ou affiché d'office : rien à y ajouter.
$maCopie = $maCopie ?? null;
$dejaDansCalendrier = $estEvenement && !$public && ($maCopie !== null || !empty($afficheDOffice));
$liens = $liens ?? [];
$groupes = $groupes ?? [];
$retour = $retour ?? null;
$mime = $fichierSeul ? (string) $cible['mime'] : '';
$nom = $fichierSeul ? (string) $cible['nom_origine'] : '';
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large data-document' : '' ?>>
  <div>
    <?php if ($dansUneFenetre): ?>
    <?php elseif ($retour !== null): ?>
      <p class="discret" style="margin-bottom:.35rem"><a href="<?= e((string) $retour['url']) ?>"><?= e((string) $retour['texte']) ?></a></p>
    <?php elseif (!$public): ?>
      <p class="discret" style="margin-bottom:.35rem"><a href="<?= url('partages') ?>">← Partagés avec moi</a></p>
    <?php endif; ?>
    <h1><?= $fichierSeul ? e(Fichiers::icone($mime, $nom)) . ' ' : ($estFiche ? '📝 ' : ($estDossier ? e((string) $cible['icone']) . ' ' : ($estEvenement ? e((string) ($cible['type_icone'] ?: '📅')) . ' ' : '📘 '))) ?><?= e((string) ($estFiche ? $cible['titre_cours'] : $cible['titre'])) ?></h1>
    <?php if ($estFiche): ?><p style="margin:0 0 .2rem"><span class="pastille">Fiche de révision</span></p><?php endif; ?>
    <?php if ($estDossier): ?><p style="margin:0 0 .2rem"><span class="pastille">Dossier · <?= e(Partages::compteCours((int) $cible['nb_cours'])) ?></span></p><?php endif; ?>
    <p class="discret">
      <?= Partages::icone(15) ?> Partagé par <strong><?= e($proprietaire !== '' ? $proprietaire : 'un compte Mes Cours') ?></strong>
      <?php if ($estDossier): ?>
        · ses sous-dossiers compris
      <?php elseif ($estEvenement): ?>
      <?php elseif (!$fichierSeul): ?>
        <?php if (($cible['matiere_nom'] ?? null) !== null): ?> · <?= e((string) $cible['matiere_nom']) ?><?php endif; ?>
        · mis à jour le <?= e(date_fr((string) $cible['updated_at'], false)) ?>
      <?php else: ?>
        · <?= e(taille_lisible((int) $cible['taille'])) ?>
      <?php endif; ?>
      · <?= e(mb_strtolower(Partages::libelleDroit($droit ?? 'lecture'))) ?>
    </p>
  </div>
  <div class="actions">
    <?php if ($fichierSeul): ?>
      <a class="bouton" href="<?= e($adresseFichier((int) $cible['id'], true)) ?>">⬇ Télécharger</a>
    <?php endif; ?>
    <?php if ($dejaDansCalendrier): ?>
      <?php // Il y est déjà : on le dit, et l'on mène à sa copie s'il en a une. ?>
      <span class="pastille pastille--ok">✓ Déjà dans votre calendrier</span>
      <?php if ($maCopie !== null): ?>
        <a class="bouton bouton--discret" href="<?= url('evenements/' . (int) $maCopie) ?>"
           <?= $dansUneFenetre ? 'data-fenetre' : '' ?>>Ouvrir le mien</a>
      <?php endif; ?>
    <?php elseif ($estEvenement): ?>
      <?php // Pour tout agenda : Google, Outlook, Apple — même sans compte ici. ?>
      <a class="bouton bouton--secondaire" href="<?= e((string) ($adresseIcs ?? '')) ?>"
         title="Un fichier .ics, que votre agenda sait ouvrir">📆 Ajouter à mon agenda</a>
    <?php endif; ?>
    <?php if (!$public): ?>
      <?php if (!$fichierSeul && !$dejaDansCalendrier): ?>
        <form method="post" action="<?= url($base . '/copier') ?>" class="en-ligne"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--secondaire" type="submit"><?= $estEvenement ? '📅 Ajouter à mon calendrier' : '📥 Copier ' . ($estDossier ? 'le dossier chez moi' : 'dans mes cours') ?></button>
        </form>
      <?php endif; ?>
      <?php if ($recu): ?>
        <form method="post" action="<?= url($base . '/oublier') ?>" class="en-ligne"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>
              data-confirmation="Retirer ce document de vos partages ? Il faudra qu’on vous le partage de nouveau pour le revoir.">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--discret" type="submit">Retirer de ma liste</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($fichierSeul): ?>
  <?php // Ce que le navigateur sait montrer ; le reste se télécharge. ?>
  <section class="carte partage-apercu">
    <?php if (Fichiers::estImage($mime)): ?>
      <img src="<?= e($adresseFichier((int) $cible['id'])) ?>" alt="<?= e($nom) ?>" class="partage-apercu__image">
    <?php elseif (Fichiers::estPdf($mime, $nom)): ?>
      <iframe src="<?= e($adresseFichier((int) $cible['id'])) ?>" title="<?= e($nom) ?>" class="partage-apercu__pdf"></iframe>
    <?php elseif (Fichiers::estAudio($mime, $nom)): ?>
      <audio controls preload="metadata" src="<?= e($adresseFichier((int) $cible['id'])) ?>" style="width:100%"></audio>
    <?php elseif (Fichiers::estVideo($mime, $nom)): ?>
      <video controls preload="metadata" src="<?= e($adresseFichier((int) $cible['id'])) ?>" style="width:100%;max-height:70vh"></video>
    <?php else: ?>
      <p class="discret" style="margin:0">Ce fichier ne s’affiche pas dans le navigateur : téléchargez-le pour l’ouvrir.</p>
    <?php endif; ?>
  </section>

  <?php if (!$public): ?>
    <section class="carte">
      <h2 style="margin-top:0">📥 Copier dans un de mes cours</h2>
      <?php if ($mesCours === []): ?>
        <p class="discret" style="margin:0">Créez d’abord un cours pour y ranger ce fichier.</p>
      <?php else: ?>
        <form method="post" action="<?= url($base . '/copier') ?>" class="fuseau-choix"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <label class="sr-only" for="copie-cours">Cours</label>
          <select id="copie-cours" name="cours" required>
            <?php foreach ($mesCours as $c): ?>
              <option value="<?= (int) $c['id'] ?>"><?= e((string) $c['titre']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="bouton" type="submit">Copier</button>
        </form>
      <?php endif; ?>
    </section>
  <?php endif; ?>
<?php elseif ($estEvenement): ?>
  <?php
  // Quand, où, quoi : ce qu'on vient chercher dans un évènement.
  $debutEvt = new DateTimeImmutable((string) $cible['debut']);
  $finEvt = new DateTimeImmutable((string) $cible['fin']);
  $memeJour = $debutEvt->format('Y-m-d') === $finEvt->format('Y-m-d');
  if ((int) $cible['journee_entiere'] === 1) {
      $quand = $memeJour
          ? ucfirst(date_fr((string) $cible['debut'], false)) . ' — toute la journée'
          : 'Du ' . date_fr((string) $cible['debut'], false) . ' au ' . date_fr((string) $cible['fin'], false);
  } elseif ($memeJour) {
      $quand = ucfirst(date_fr((string) $cible['debut'], false)) . ', de ' . $debutEvt->format('H:i') . ' à ' . $finEvt->format('H:i');
  } else {
      $quand = 'Du ' . date_fr((string) $cible['debut']) . ' au ' . date_fr((string) $cible['fin']);
  }
  ?>
  <?php if (!$public && (int) $cible['user_id'] !== Auth::id()): ?>
    <?php // Le réglage de cet ami, là où l'on reçoit ses évènements. ?>
    <form method="post" action="<?= url('partages/calendrier/' . (int) $cible['user_id']) ?>" class="partage-calendrier">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
      <input type="hidden" name="afficher" value="<?= !empty($afficheDOffice) ? '0' : '1' ?>">
      <span>
        📅 Les évènements que <strong><?= e($proprietaire) ?></strong> vous partage
        <?= !empty($afficheDOffice) ? 's’affichent d’office dans votre calendrier.' : 'ne s’affichent pas d’office dans votre calendrier.' ?>
      </span>
      <button class="bouton bouton--discret bouton--petit" type="submit">
        <?= !empty($afficheDOffice) ? 'Ne plus les afficher' : 'Les afficher d’office' ?>
      </button>
    </form>
  <?php endif; ?>
  <?php
  // Les mêmes lignes que la fiche de son auteur, tues quand elles n'ont rien à dire.
  $ligneEvt = static function (string $etiquette, string $valeur): string {
      return trim(strip_tags($valeur)) === '' ? ''
          : '<div class="fiche__ligne"><span class="fiche__etiquette">' . e($etiquette)
            . '</span><span class="fiche__valeur">' . $valeur . '</span></div>';
  };
  $pastille = static fn (?string $nom, ?string $couleur, string $icone = ''): string => (string) $nom === '' ? ''
      : '<span class="pastille" style="background:' . e((string) ($couleur ?: '#94a3b8'))
        . ';color:' . e(couleur_texte((string) ($couleur ?: '#94a3b8'))) . '">'
        . e(trim($icone . ' ' . $nom)) . '</span>';
  ?>
  <section class="carte fiche" style="max-width:44rem">
    <?= $ligneEvt('Quand', e($quand)) ?>
    <?php if ((int) $cible['journee_entiere'] !== 1): ?>
      <?php
      $duree = $debutEvt->diff($finEvt);
      $heures = $duree->days * 24 + $duree->h;
      ?>
      <?= $ligneEvt('Durée', e($heures > 0 ? $heures . ' h' . ($duree->i > 0 ? ' ' . $duree->i : '') : $duree->i . ' min')) ?>
    <?php endif; ?>
    <?= $ligneEvt('Lieu', e((string) ($cible['lieu'] ?? ''))) ?>
    <?= $ligneEvt('Type', $pastille($cible['type_nom'] ?? null, $cible['type_couleur'] ?? null, (string) ($cible['type_icone'] ?? ''))) ?>
    <?= $ligneEvt('Matière', $pastille($cible['matiere_nom'] ?? null, $cible['matiere_couleur'] ?? null)) ?>
    <?php if (trim((string) ($cible['description'] ?? '')) !== ''): ?>
      <div class="fiche__notes">
        <span class="fiche__etiquette">Notes</span>
        <div class="texte-riche-affiche"><?= TexteRiche::versHtml((string) $cible['description']) ?></div>
      </div>
    <?php endif; ?>
  </section>
<?php elseif ($estDossier): ?>
  <?php // Les cours du dossier, puis ceux de chaque sous-dossier qui en contient. ?>
  <?php foreach ($groupes as $i => $groupe): ?>
    <section class="carte" style="margin-left:<?= min((int) $groupe['profondeur'], 4) * 1.1 ?>rem">
      <h2 style="margin-top:0">
        <?= e((string) $groupe['icone']) ?> <?= $i === 0 ? 'Dans ce dossier' : e((string) $groupe['nom']) ?>
        <span class="discret">(<?= count($groupe['cours']) ?>)</span>
      </h2>
      <?php if ($groupe['cours'] === []): ?>
        <p class="discret" style="margin:0">Ce dossier ne contient aucun cours pour l’instant.</p>
      <?php else: ?>
        <ul class="partage-cours">
          <?php foreach ($groupe['cours'] as $c): ?>
            <li>
              <a href="<?= e($adresseCours((int) $c['id'])) ?>"<?= $dansUneFenetre ? ' data-fenetre' : '' ?>>📘 <?= e((string) $c['titre']) ?></a>
              <span class="discret">
                <?php if (($c['matiere_nom'] ?? null) !== null): ?><?= e((string) $c['matiere_nom']) ?> · <?php endif; ?>
                <?php if ((int) $c['nb_fichiers'] > 0): ?><?= (int) $c['nb_fichiers'] ?> fichier<?= (int) $c['nb_fichiers'] > 1 ? 's' : '' ?> · <?php endif; ?>
                <?= e(date_fr((string) $c['updated_at'], false)) ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
<?php else: ?>
  <?php $texte = (string) ($estFiche ? $cible['fiche_revision'] : $cible['contenu']); ?>
  <div class="colonnes">
    <article class="carte">
      <?php if (trim($texte) === ''): ?>
        <p class="discret" style="margin:0"><?= $estFiche ? 'Cette fiche n’a pas de texte.' : 'Ce cours n’a pas de contenu écrit.' ?></p>
      <?php else: ?>
        <div class="contenu-cours texte-riche-affiche"><?= TexteRiche::versHtml($texte) ?></div>
      <?php endif; ?>

      <?php if ($peutEcrire): ?>
        <?php if (Partages::nbModifications($type, (int) $cible['id']) > 0): ?>
          <p class="discret" style="margin:.4rem 0 0">
            🕘 <a href="<?= e(Partages::adresseHistorique($type, (int) $cible['id'])) ?>"<?= $dansUneFenetre ? ' data-fenetre-dessus' : '' ?>>Voir les modifications</a>
          </p>
        <?php endif; ?>
        <?php // On m'a donné le droit d'écrire : le même éditeur que chez moi. ?>
        <details class="edition-contenu"<?= trim($texte) === '' ? ' open' : '' ?>>
          <summary class="edition-contenu__ouvrir">
            ✏️ <?= trim($texte) === '' ? 'Écrire' : 'Modifier le texte' ?>
          </summary>
          <form method="post" action="<?= url($base . '/contenu') ?>"<?= $surPlace ?>>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="champ">
              <label class="legende" for="partage-contenu"><?= $estFiche ? 'La fiche de révision' : 'Le cours lui-même' ?></label>
              <textarea id="partage-contenu" name="contenu" class="edition-contenu__texte" data-texte-riche="complet"
                        data-tailles="<?= e(implode(',', TexteRiche::TAILLES)) ?>"><?= e(TexteRiche::pourEditeur($texte)) ?></textarea>
            </div>
            <p class="actions">
              <button class="bouton bouton--petit" type="submit">Enregistrer</button>
            </p>
          </form>
        </details>
      <?php endif; ?>
    </article>
    <div class="pile">
    <?php if ($estFiche && $liens !== []): ?>
      <section class="carte">
        <h2 style="margin-top:0">🔗 Liens</h2>
        <ul class="partage-liens">
          <?php foreach ($liens as $l): ?>
            <li><a href="<?= e((string) $l['url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e((string) ($l['libelle'] ?: $l['url'])) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
    <section class="carte">
      <h2 style="margin-top:0"><?= $estFiche ? 'Fichiers de la fiche' : 'Fichiers joints' ?> <span class="discret">(<?= count($fichiers) ?>)</span></h2>
      <?php if ($fichiers === []): ?>
        <p class="discret" style="margin:0">Aucun fichier joint.</p>
      <?php else: ?>
        <ul class="liste-fichiers">
          <?php foreach ($fichiers as $f): ?>
            <li class="fichier">
              <span class="fichier__icone" aria-hidden="true"><?= Fichiers::icone((string) $f['mime'], (string) $f['nom_origine']) ?></span>
              <span style="min-width:0">
                <a class="fichier__nom" href="<?= e($adresseFichier((int) $f['id'])) ?>" target="_blank" rel="noopener"><?= e((string) $f['nom_origine']) ?></a><br>
                <span class="fichier__meta"><?= e(taille_lisible((int) $f['taille'])) ?></span>
              </span>
              <span class="fichier__actions">
                <a class="bouton bouton--discret bouton--petit" href="<?= e($adresseFichier((int) $f['id'], true)) ?>" title="Télécharger"
                   aria-label="Télécharger <?= e((string) $f['nom_origine']) ?>">⬇</a>
                <?php if ($peutEcrire): ?>
                  <form method="post" action="<?= url('partages/fichiers/' . (int) $f['id'] . '/retirer') ?>"<?= $surPlace ?>
                        data-confirmation="Retirer « <?= e((string) $f['nom_origine']) ?> » de ce document ? Il sera supprimé pour tout le monde.">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button class="bouton bouton--discret bouton--petit" type="submit" title="Retirer"
                            aria-label="Retirer <?= e((string) $f['nom_origine']) ?>">✕</button>
                  </form>
                <?php endif; ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ($peutEcrire): ?>
        <form method="post" action="<?= url($base . '/fichiers') ?>" enctype="multipart/form-data" style="margin-top:.6rem">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <div class="champ">
            <label class="legende" for="partage-joindre">Joindre des fichiers</label>
            <input type="file" id="partage-joindre" name="fichiers[]" multiple>
          </div>
          <button class="bouton bouton--petit" type="submit">Joindre</button>
        </form>
      <?php endif; ?>
    </section>
    </div>
  </div>
<?php endif; ?>

<?php if ($peutCommenter): ?>
  <?php // Une conversation sous le document, que son propriétaire lit aussi. ?>
  <section class="carte" id="commentaires">
    <h2 style="margin-top:0">💬 Commentaires <span class="discret">(<?= count($commentaires) ?>)</span></h2>
    <?= Vue::rendre('partages/_fil', [
        'commentaires' => $commentaires,
        'cible' => $cible,
        'base' => $base,
        'surPlace' => $surPlace,
        'depuis' => '',
    ]) ?>
  </section>
<?php endif; ?>

<?php if ($public): ?>
  <p class="discret partage-invitation">
    Partagé avec <strong><?= e((string) Config::get('app', 'nom')) ?></strong> —
    <?php if (Auth::connecte()): ?>
      <a href="<?= url('') ?>">retour à l’application</a>.
    <?php else: ?>
      vos cours, vos fichiers et votre planning au même endroit. <a href="<?= url('connexion') ?>">Se connecter</a>
    <?php endif; ?>
  </p>
<?php endif; ?>
