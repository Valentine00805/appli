<?php
/**
 * Un document partagé, en lecture : pour un ami (dans l'application) ou pour
 * n'importe qui (par le lien public).
 *
 * @var string $type   « cours » ou « fichier »
 * @var array $cible
 * @var bool $public   ouvert par le lien public
 * @var list<array> $fichiers  les fichiers joints d'un cours
 * @var callable $adresseFichier  (int $id, bool $telecharger): string
 * @var list<array> $mesCours  où ranger la copie d'un fichier
 * @var bool $recu     il figure dans « Partagés avec moi »
 * @var string $mot
 */
$proprietaire = (string) $cible['proprietaire'];
$csrf = $public ? '' : Session::jetonCsrf();
$base = 'partages/' . $mot . '/' . (int) $cible['id'];
$fichierSeul = $type === 'fichier';
$mime = $fichierSeul ? (string) $cible['mime'] : '';
$nom = $fichierSeul ? (string) $cible['nom_origine'] : '';
?>
<div class="entete-page">
  <div>
    <?php if (!$public): ?>
      <p class="discret" style="margin-bottom:.35rem"><a href="<?= url('cours') ?>#partages-recus">← Partagés avec moi</a></p>
    <?php endif; ?>
    <h1><?= $fichierSeul ? e(Fichiers::icone($mime, $nom)) . ' ' : '📘 ' ?><?= e((string) $cible['titre']) ?></h1>
    <p class="discret">
      <?= Partages::icone(15) ?> Partagé par <strong><?= e($proprietaire !== '' ? $proprietaire : 'un compte Mes Cours') ?></strong>
      <?php if (!$fichierSeul): ?>
        <?php if (($cible['matiere_nom'] ?? null) !== null): ?> · <?= e((string) $cible['matiere_nom']) ?><?php endif; ?>
        · mis à jour le <?= e(date_fr((string) $cible['updated_at'], false)) ?>
      <?php else: ?>
        · <?= e(taille_lisible((int) $cible['taille'])) ?>
      <?php endif; ?>
      · lecture seule
    </p>
  </div>
  <div class="actions">
    <?php if ($fichierSeul): ?>
      <a class="bouton" href="<?= e($adresseFichier((int) $cible['id'], true)) ?>">⬇ Télécharger</a>
    <?php endif; ?>
    <?php if (!$public): ?>
      <?php if (!$fichierSeul): ?>
        <form method="post" action="<?= url($base . '/copier') ?>" class="en-ligne">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--secondaire" type="submit">📥 Copier dans mes cours</button>
        </form>
      <?php endif; ?>
      <?php if ($recu): ?>
        <form method="post" action="<?= url($base . '/oublier') ?>" class="en-ligne"
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
        <form method="post" action="<?= url($base . '/copier') ?>" class="fuseau-choix">
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
<?php else: ?>
  <div class="colonnes">
    <article class="carte">
      <?php if (trim((string) $cible['contenu']) === ''): ?>
        <p class="discret" style="margin:0">Ce cours n’a pas de contenu écrit.</p>
      <?php else: ?>
        <div class="contenu-cours texte-riche-affiche"><?= TexteRiche::versHtml((string) $cible['contenu']) ?></div>
      <?php endif; ?>
    </article>
    <section class="carte">
      <h2 style="margin-top:0">Fichiers joints <span class="discret">(<?= count($fichiers) ?>)</span></h2>
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
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
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
