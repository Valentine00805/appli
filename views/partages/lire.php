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
$matiereAjoutable = $matiereAjoutable ?? null;
$coursLie = $coursLie ?? null;
$perso = $perso ?? null;
/** Le bouton « Ajouter à mes matières » (ou « ✓ Dans tes matières ») : la matière de son auteur, reprise chez soi d'un clic. */
$formMatiere = static function () use ($matiereAjoutable, $public, $dansUneFenetre, $base, $csrf): string {
    if ($public || $matiereAjoutable === null) {
        return '';
    }
    if ($matiereAjoutable['deja']) {
        return ' <span class="pastille pastille--ok">' . e(t('pt.matiere_deja')) . '</span>';
    }

    return ' <form method="post" action="' . e(url($base . '/matiere')) . '" class="en-ligne"' . ($dansUneFenetre ? ' data-envoi-fenetre' : '') . '>'
        . '<input type="hidden" name="_csrf" value="' . e($csrf) . '">'
        . '<button class="bouton bouton--secondaire bouton--petit" type="submit">' . e(t('pt.matiere_ajouter')) . '</button></form>';
};
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
      <p class="discret" style="margin-bottom:.35rem"><a href="<?= url('partages') ?>"><?= e(t('pt.retour_recus')) ?></a></p>
    <?php endif; ?>
    <h1><?= $fichierSeul ? e(Fichiers::icone($mime, $nom)) . ' ' : ($estFiche ? '📝 ' : ($estDossier ? e((string) $cible['icone']) . ' ' : ($estEvenement ? e((string) ($cible['type_icone'] ?: '📅')) . ' ' : '📘 '))) ?><?= e((string) ($estFiche ? $cible['titre_cours'] : $cible['titre'])) ?></h1>
    <?php if ($estFiche): ?><p style="margin:0 0 .2rem"><span class="pastille"><?= e(t('pt.pastille_fiche')) ?></span></p><?php endif; ?>
    <?php if ($estDossier): ?><p style="margin:0 0 .2rem"><span class="pastille"><?= e(t('pt.pastille_dossier', ['combien' => Partages::compteCours((int) $cible['nb_cours'])])) ?></span></p><?php endif; ?>
    <p class="discret">
      <?= Partages::icone(15) ?> <?= e(t('pt.partage_par_qui')) ?> <strong><?= e($proprietaire !== '' ? $proprietaire : t('pt.un_compte')) ?></strong>
      <?php if ($estDossier): ?>
        · <?= e(t('pt.sous_dossiers_compris')) ?>
      <?php elseif ($estEvenement): ?>
      <?php elseif (!$fichierSeul): ?>
        · <?= e(t('pt.mis_a_jour', ['date' => date_fr((string) $cible['updated_at'], false)])) ?>
      <?php else: ?>
        · <?= e(taille_lisible((int) $cible['taille'])) ?>
      <?php endif; ?>
      · <?= e(mb_strtolower(Partages::libelleDroit($droit ?? 'lecture'))) ?>
    </p>
    <?php if (!$estEvenement && !$estDossier && !$fichierSeul && (string) ($cible['matiere_nom'] ?? '') !== ''): ?>
      <?php // La matière de son auteur, comme chez lui : une puce de sa couleur, et de quoi l'ajouter à ses matières. ?>
      <p class="partage-matiere" style="margin:.15rem 0 0">
        <span class="pastille" style="background:<?= e((string) ($cible['matiere_couleur'] ?: '#94a3b8')) ?>;color:<?= e(couleur_texte((string) ($cible['matiere_couleur'] ?: '#94a3b8'))) ?>"><?= e((string) $cible['matiere_nom']) ?></span>
        <?= $formMatiere() ?>
      </p>
    <?php endif; ?>
  </div>
  <div class="actions">
    <?php if ($fichierSeul): ?>
      <a class="bouton" href="<?= e($adresseFichier((int) $cible['id'], true)) ?>"><?= e(t('pt.telecharger')) ?></a>
    <?php endif; ?>
    <?php if ($dejaDansCalendrier): ?>
      <?php // Il y est déjà : on le dit, et l'on mène à sa copie s'il en a une. ?>
      <span class="pastille pastille--ok"><?= e(t('pt.deja_calendrier')) ?></span>
      <?php if ($maCopie !== null): ?>
        <a class="bouton bouton--discret" href="<?= url('evenements/' . (int) $maCopie) ?>"
           <?= $dansUneFenetre ? 'data-fenetre' : '' ?>><?= e(t('pt.ouvrir_le_mien')) ?></a>
      <?php endif; ?>
    <?php elseif ($estEvenement): ?>
      <?php // Pour tout agenda : Google, Outlook, Apple — même sans compte ici. ?>
      <a class="bouton bouton--secondaire" href="<?= e((string) ($adresseIcs ?? '')) ?>"
         title="<?= e(t('pt.ics_titre')) ?>"><?= e(t('pt.ajouter_agenda')) ?></a>
    <?php endif; ?>
    <?php if (!$public): ?>
      <?php if (!$fichierSeul && !$dejaDansCalendrier): ?>
        <form method="post" action="<?= url($base . '/copier') ?>" class="en-ligne"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--secondaire" type="submit"><?= e(t($estEvenement ? 'pt.ajouter_calendrier' : ($estDossier ? 'pt.copier_dossier' : 'pt.copier_cours'))) ?></button>
        </form>
      <?php endif; ?>
      <?php if ($recu): ?>
        <form method="post" action="<?= url($base . '/oublier') ?>" class="en-ligne"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>
              data-confirmation="<?= e(t('pt.oublier_confirmation')) ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--discret" type="submit"><?= e(t('pt.retirer_liste')) ?></button>
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
      <p class="discret" style="margin:0"><?= e(t('pt.pas_dapercu')) ?></p>
    <?php endif; ?>
  </section>

  <?php if (!$public): ?>
    <section class="carte">
      <h2 style="margin-top:0"><?= e(t('pt.copier_dans_cours')) ?></h2>
      <?php if ($mesCours === []): ?>
        <p class="discret" style="margin:0"><?= e(t('pt.creez_un_cours')) ?></p>
      <?php else: ?>
        <form method="post" action="<?= url($base . '/copier') ?>" class="fuseau-choix"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <label class="sr-only" for="copie-cours"><?= e(t('pt.cours')) ?></label>
          <select id="copie-cours" name="cours" required>
            <?php foreach ($mesCours as $c): ?>
              <option value="<?= (int) $c['id'] ?>"><?= e((string) $c['titre']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="bouton" type="submit"><?= e(t('pt.copier')) ?></button>
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
          ? t('evt.toute_la_journee', ['date' => ucfirst(date_fr((string) $cible['debut'], false))])
          : t('evt.du_au', [
              'debut' => date_fr((string) $cible['debut'], false),
              'fin' => date_fr((string) $cible['fin'], false),
          ]);
  } elseif ($memeJour) {
      $quand = t('evt.de_a', [
          'date' => ucfirst(date_fr((string) $cible['debut'], false)),
          'debut' => heure_courte($debutEvt->getTimestamp()),
          'fin' => heure_courte($finEvt->getTimestamp()),
      ]);
  } else {
      $quand = t('evt.du_au', [
          'debut' => date_fr((string) $cible['debut']),
          'fin' => date_fr((string) $cible['fin']),
      ]);
  }
  ?>
  <?php if (!$public && (int) $cible['user_id'] !== Auth::id()): ?>
    <?php // Le réglage de cet ami, là où l'on reçoit ses évènements. ?>
    <form method="post" action="<?= url('partages/calendrier/' . (int) $cible['user_id']) ?>" class="partage-calendrier">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
      <input type="hidden" name="afficher" value="<?= !empty($afficheDOffice) ? '0' : '1' ?>">
      <span>
        <?= e(t('pt.evt_partages_texte', ['qui' => $proprietaire])) ?>
        <?= e(t(!empty($afficheDOffice) ? 'pt.evt_office_oui' : 'pt.evt_office_non')) ?>
      </span>
      <button class="bouton bouton--discret bouton--petit" type="submit">
        <?= e(t(!empty($afficheDOffice) ? 'pt.ne_plus_afficher' : 'pt.afficher_office')) ?>
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
    <?= $ligneEvt(t('evt.quand'), e($quand)) ?>
    <?php if ((int) $cible['journee_entiere'] !== 1): ?>
      <?php
      $duree = $debutEvt->diff($finEvt);
      $heures = $duree->days * 24 + $duree->h;
      ?>
      <?= $ligneEvt(t('evt.duree'), e($heures > 0
          ? t('pt.duree_heures', ['h' => $heures, 'min' => $duree->i > 0 ? ' ' . $duree->i : ''])
          : t('pt.duree_minutes', ['min' => $duree->i]))) ?>
    <?php endif; ?>
    <?= $ligneEvt(t('evt.lieu'), e((string) ($cible['lieu'] ?? ''))) ?>
    <?= $ligneEvt(t('evt.type'), $pastille($cible['type_nom'] ?? null, $cible['type_couleur'] ?? null, (string) ($cible['type_icone'] ?? ''))) ?>
    <?= $ligneEvt(t('evt.matiere'), $pastille($cible['matiere_nom'] ?? null, $cible['matiere_couleur'] ?? null) . ($pastille($cible['matiere_nom'] ?? null, null) === '' ? '' : $formMatiere())) ?>
    <?php if ($coursLie !== null): ?>
      <?php // Le cours lié, s'il m'est partagé aussi : un lien, comme chez son auteur. ?>
      <?= $ligneEvt(t('evt.cours_lie'), '<a href="' . e($adresseCours($coursLie['id'])) . '"' . ($dansUneFenetre ? ' data-fenetre' : '') . '>' . e($coursLie['titre']) . '</a>') ?>
    <?php endif; ?>
    <?php if (trim((string) ($cible['description'] ?? '')) !== ''): ?>
      <div class="fiche__notes">
        <span class="fiche__etiquette"><?= e(t('evt.notes')) ?></span>
        <div class="texte-riche-affiche"><?= TexteRiche::versHtml((string) $cible['description']) ?></div>
      </div>
    <?php endif; ?>
  </section>
  <?php if (!$public && $perso !== null && (int) $cible['user_id'] !== Auth::id()): ?>
    <?php
    /*
     * Ce qu'on peut régler sur l'évènement d'un ami, sans le modifier : ses propres rappels (pour soi seul) et ses notes, que
     * l'ami lit aussi. Le reste — titre, date, lieu, notes de l'ami — reste à lui.
     */
    ?>
    <section class="carte" style="max-width:44rem;margin-top:1rem" id="mes-reglages">
      <h2 style="margin-top:0"><?= e(t('pt.perso_titre')) ?></h2>
      <p class="discret" style="margin-top:0"><?= e(t('pt.perso_aide', ['qui' => $proprietaire])) ?></p>
      <form method="post" action="<?= url('partages/evenements/' . (int) $cible['id'] . '/perso') ?>"<?= $surPlace ?>>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <fieldset class="rappels-choix" data-rappels>
          <legend><?= e(t('evtf.rappels')) ?></legend>
          <div class="rappels-choix__liste">
            <?php foreach (array_reverse(Rappels::DELAIS_COURTS, true) as $minutes => $court): ?>
              <label class="rappels-choix__option" title="<?= e(Rappels::libelle((int) $minutes)) ?>">
                <input type="checkbox" name="rappels[]" value="<?= (int) $minutes ?>"<?= in_array($minutes, $perso['rappels'], true) ? ' checked' : '' ?>>
                <span class="pastille"><?= e(Rappels::court((int) $minutes)) ?></span>
              </label>
            <?php endforeach; ?>
            <label class="rappels-choix__option" title="<?= e(t('evtf.rappel_aucun_titre')) ?>">
              <input type="checkbox" data-rappel-aucun<?= $perso['rappels'] === [] ? ' checked' : '' ?>>
              <span class="pastille"><?= e(t('evtf.rappel_aucun')) ?></span>
            </label>
          </div>
        </fieldset>
        <div class="champ" style="margin-top:.75rem">
          <label for="perso-note"><?= e(t('pt.perso_notes')) ?></label>
          <textarea id="perso-note" name="note" rows="3" maxlength="<?= Partages::NOTE_MAX ?>"><?= e($perso['note']) ?></textarea>
          <span class="champ__aide"><?= e(t('pt.perso_notes_aide', ['qui' => $proprietaire])) ?></span>
        </div>
        <button class="bouton" type="submit"><?= e(t('pt.enregistrer')) ?></button>
      </form>
    </section>
  <?php endif; ?>
<?php elseif ($estDossier): ?>
  <?php // Les cours du dossier, puis ceux de chaque sous-dossier qui en contient. ?>
  <?php foreach ($groupes as $i => $groupe): ?>
    <section class="carte" style="margin-left:<?= min((int) $groupe['profondeur'], 4) * 1.1 ?>rem">
      <h2 style="margin-top:0">
        <?= e((string) $groupe['icone']) ?> <?= $i === 0 ? e(t('pt.dans_ce_dossier')) : e((string) $groupe['nom']) ?>
        <span class="discret">(<?= count($groupe['cours']) ?>)</span>
      </h2>
      <?php if ($groupe['cours'] === []): ?>
        <p class="discret" style="margin:0"><?= e(t('pt.dossier_vide')) ?></p>
      <?php else: ?>
        <ul class="partage-cours">
          <?php foreach ($groupe['cours'] as $c): ?>
            <li>
              <a href="<?= e($adresseCours((int) $c['id'])) ?>"<?= $dansUneFenetre ? ' data-fenetre' : '' ?>>📘 <?= e((string) $c['titre']) ?></a>
              <span class="discret">
                <?php if (($c['matiere_nom'] ?? null) !== null): ?><?= e((string) $c['matiere_nom']) ?> · <?php endif; ?>
                <?php if ((int) $c['nb_fichiers'] > 0): ?><?= e(tn('pt.combien_fichier', (int) $c['nb_fichiers'])) ?> · <?php endif; ?>
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
        <p class="discret" style="margin:0"><?= e(t($estFiche ? 'pt.fiche_sans_texte' : 'pt.cours_sans_texte')) ?></p>
      <?php else: ?>
        <div class="contenu-cours texte-riche-affiche"><?= TexteRiche::versHtml($texte) ?></div>
      <?php endif; ?>

      <?php if ($peutEcrire): ?>
        <?php if (Partages::nbModifications($type, (int) $cible['id']) > 0): ?>
          <p class="discret" style="margin:.4rem 0 0">
            🕘 <a href="<?= e(Partages::adresseHistorique($type, (int) $cible['id'])) ?>"<?= $dansUneFenetre ? ' data-fenetre-dessus' : '' ?>><?= e(t('pt.voir_modifications')) ?></a>
          </p>
        <?php endif; ?>
        <?php // On m'a donné le droit d'écrire : le même éditeur que chez moi. ?>
        <details class="edition-contenu"<?= trim($texte) === '' ? ' open' : '' ?>>
          <summary class="edition-contenu__ouvrir">
            ✏️ <?= e(t(trim($texte) === '' ? 'pt.ecrire' : 'pt.modifier_texte')) ?>
          </summary>
          <form method="post" action="<?= url($base . '/contenu') ?>"<?= $surPlace ?>>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="champ">
              <label class="legende" for="partage-contenu"><?= e(t($estFiche ? 'pt.la_fiche' : 'pt.le_cours')) ?></label>
              <textarea id="partage-contenu" name="contenu" class="edition-contenu__texte" data-texte-riche="complet"
                        data-tailles="<?= e(implode(',', TexteRiche::TAILLES)) ?>"><?= e(TexteRiche::pourEditeur($texte)) ?></textarea>
            </div>
            <p class="actions">
              <button class="bouton bouton--petit" type="submit"><?= e(t('pt.enregistrer')) ?></button>
            </p>
          </form>
        </details>
      <?php endif; ?>
    </article>
    <div class="pile">
    <?php if ($estFiche && $liens !== []): ?>
      <section class="carte">
        <h2 style="margin-top:0"><?= e(t('pt.liens')) ?></h2>
        <ul class="partage-liens">
          <?php foreach ($liens as $l): ?>
            <li><a href="<?= e((string) $l['url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e((string) ($l['libelle'] ?: $l['url'])) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
    <section class="carte">
      <h2 style="margin-top:0"><?= e(t($estFiche ? 'pt.fichiers_fiche' : 'pt.fichiers_joints')) ?> <span class="discret">(<?= count($fichiers) ?>)</span></h2>
      <?php if ($fichiers === []): ?>
        <p class="discret" style="margin:0"><?= e(t('pt.aucun_joint')) ?></p>
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
                <a class="bouton bouton--discret bouton--petit" href="<?= e($adresseFichier((int) $f['id'], true)) ?>" title="<?= e(t('commun.telecharger')) ?>"
                   aria-label="<?= e(t('prf.telecharger_nom', ['nom' => (string) $f['nom_origine']])) ?>">⬇</a>
                <?php if ($peutEcrire): ?>
                  <form method="post" action="<?= url('partages/fichiers/' . (int) $f['id'] . '/retirer') ?>"<?= $surPlace ?>
                        data-confirmation="<?= e(t('pt.retirer_fichier_confirmation', ['nom' => (string) $f['nom_origine']])) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button class="bouton bouton--discret bouton--petit" type="submit" title="<?= e(t('pt.retirer')) ?>"
                            aria-label="<?= e(t('pt.retirer_nom', ['nom' => (string) $f['nom_origine']])) ?>">✕</button>
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
            <label class="legende" for="partage-joindre"><?= e(t('pt.joindre_fichiers')) ?></label>
            <input type="file" id="partage-joindre" name="fichiers[]" multiple>
          </div>
          <button class="bouton bouton--petit" type="submit"><?= e(t('pt.joindre')) ?></button>
        </form>
      <?php endif; ?>
    </section>
    </div>
  </div>
<?php endif; ?>

<?php if ($peutCommenter): ?>
  <?php // Une conversation sous le document, que son propriétaire lit aussi. ?>
  <section class="carte" id="commentaires">
    <h2 style="margin-top:0"><?= e(t('pt.commentaires')) ?> <span class="discret">(<?= count($commentaires) ?>)</span></h2>
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
    <?= e(t('pt.invitation_publique')) ?> <strong><?= e((string) Config::get('app', 'nom')) ?></strong> —
    <?php if (Auth::connecte()): ?>
      <a href="<?= url('') ?>"><?= e(t('pt.retour_appli')) ?></a>.
    <?php else: ?>
      <?= e(t('pt.invitation_argument')) ?> <a href="<?= url('connexion') ?>"><?= e(t('pt.se_connecter')) ?></a>
    <?php endif; ?>
  </p>
<?php endif; ?>
