<?php
/**
 * Une conversation avec un ami : la liste des amis à gauche, le fil à droite.
 *
 * Le script de la page envoie sans recharger et va chercher les nouveaux
 * messages toutes les quelques secondes. Sans lui, le formulaire s'envoie
 * normalement et la page se relit.
 *
 * @var array{id: int, pseudo: string} $ami
 * @var list<array> $messages
 * @var int $vuJusqua
 * @var list<array> $amis
 * @var array<int, array> $derniers
 */
$csrf = Session::jetonCsrf();
$actif = (int) $ami['id'];
$dernierId = $messages === [] ? 0 : (int) end($messages)['id'];
$dernierMien = 0;
foreach ($messages as $m) {
    if ($m['moi']) { $dernierMien = $m['id']; }
}
?>

<div class="chat">
  <aside class="carte chat__amis" aria-label="Mes discussions">
    <p style="margin:0 0 .6rem"><a href="<?= url('amis') ?>">← Amis et demandes</a></p>
    <?php require __DIR__ . '/_liste.php'; ?>
  </aside>

  <section class="carte chat__fil"
           data-chat
           data-nouveaux="<?= e(url('amis/' . $actif . '/messages')) ?>"
           data-envoyer="<?= e(url('amis/' . $actif . '/messages')) ?>"
           data-jeton="<?= e($csrf) ?>"
           data-dernier="<?= $dernierId ?>"
           data-supprimer="<?= e(url('amis/messages/0/supprimer')) ?>"
           data-modifier="<?= e(url('amis/messages/0/modifier')) ?>"
           data-maintenant="<?= e($maintenant) ?>"
           data-ami="<?= e((string) $ami['pseudo']) ?>"
           data-vu="<?= (int) $vuJusqua ?>">
    <header class="chat__entete">
      <a class="chat__retour" href="<?= url('amis') ?>" aria-label="Retour aux amis">←</a>
      <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $ami['pseudo'], 0, 1))) ?></span>
      <h1 class="chat__titre"><?= e((string) $ami['pseudo']) ?></h1>
      <form method="post" action="<?= url('amis/' . $actif . '/retirer') ?>" class="en-ligne chat__retirer"
            data-confirmation="Retirer <?= e((string) $ami['pseudo']) ?> de vos amis ? Vous ne pourrez plus vous écrire.">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <button class="bouton bouton--discret bouton--petit" type="submit">Retirer des amis</button>
      </form>
    </header>

    <div class="chat__messages" data-chat-messages aria-live="polite">
      <?php if ($messages === []): ?>
        <p class="chat__vide" data-chat-vide>Aucun message pour l’instant. Écrivez le premier !</p>
      <?php endif; ?>
      <?php $jour = null; ?>
      <?php foreach ($messages as $m): ?>
        <?php if ($m['jour'] !== $jour): $jour = $m['jour']; ?>
          <p class="chat__jour" data-jour="<?= e($m['jour']) ?>"><span><?= e($m['jour_libelle']) ?></span></p>
        <?php endif; ?>
        <?php
        /*
         * Un clic, ou un appui long sur un téléphone, ouvre le menu de la bulle :
         * répondre, modifier (ses messages), supprimer. C'est le script qui le montre.
         */
        ?>
        <div class="bulle<?= $m['moi'] ? ' bulle--moi' : '' ?><?= $m['image'] !== null ? ' bulle--image' : '' ?><?= $m['supprime'] ? ' bulle--supprime' : '' ?>"
             data-message="<?= (int) $m['id'] ?>" id="message-<?= (int) $m['id'] ?>" tabindex="0" aria-haspopup="menu"
             <?= $m['image'] !== null || $m['fichier'] !== null ? 'data-piece' : '' ?>>
          <?php if ($m['reponse'] !== null): ?>
            <a class="bulle__citation" href="#message-<?= $m['reponse']['id'] ?>" data-citation="<?= $m['reponse']['id'] ?>">
              <span class="bulle__citation-auteur"><?= e($m['reponse']['auteur']) ?></span>
              <span class="bulle__citation-extrait" data-extrait-de="<?= $m['reponse']['id'] ?>"><?= e($m['reponse']['extrait']) ?></span>
            </a>
          <?php endif; ?>
          <?php if ($m['supprime']): ?>
            <p class="bulle__texte">🚫 Message supprimé</p>
          <?php endif; ?>
          <?php if ($m['image'] !== null): ?>
            <a class="bulle__image" href="<?= e($m['image']) ?>" target="_blank" rel="noopener" data-visionneuse>
              <img src="<?= e($m['image']) ?>" alt="Photo"
                   <?= $m['largeur'] > 0 ? 'width="' . $m['largeur'] . '" height="' . $m['hauteur'] . '"' : '' ?>>
            </a>
          <?php endif; ?>
          <?php if ($m['fichier'] !== null): ?>
            <div class="bulle__fichier">
              <span class="bulle__fichier-icone" aria-hidden="true"><?= e($m['fichier']['icone']) ?></span>
              <span class="bulle__fichier-infos">
                <a class="bulle__fichier-nom" href="<?= e($m['fichier']['url']) ?>" target="_blank" rel="noopener"><?= e($m['fichier']['nom']) ?></a>
                <span class="bulle__fichier-taille"><?= e($m['fichier']['taille']) ?></span>
              </span>
              <a class="bulle__fichier-telecharger" href="<?= e($m['fichier']['telecharger']) ?>"
                 title="Télécharger" aria-label="Télécharger <?= e($m['fichier']['nom']) ?>">⬇</a>
            </div>
          <?php endif; ?>
          <?php if ($m['texte'] !== ''): ?>
            <p class="bulle__texte"><?= nl2br(e($m['texte'])) ?></p>
          <?php endif; ?>
          <span class="bulle__heure"><?php if ($m['modifie']): ?><span class="bulle__modifie">modifié · </span><?php endif; ?><?= e($m['heure']) ?></span>
        </div>
        <?php // « Vu » sous mon dernier message, s'il a été lu — pas sous la réponse qui l'a suivi. ?>
        <?php if ($m['id'] === $dernierMien): ?>
          <p class="chat__vu" data-chat-vu<?= $vuJusqua >= $dernierMien ? '' : ' hidden' ?>>Vu</p>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if ($dernierMien === 0): ?>
        <p class="chat__vu" data-chat-vu hidden>Vu</p>
      <?php endif; ?>
    </div>

    <?php // Répondre à un message ou en modifier un : le bandeau le rappelle au-dessus de la saisie. ?>
    <div class="chat__contexte" data-chat-contexte hidden>
      <span class="chat__contexte-texte">
        <strong data-contexte-titre></strong>
        <span class="chat__contexte-extrait" data-contexte-extrait></span>
      </span>
      <button class="chat__contexte-annuler" type="button" data-contexte-annuler aria-label="Annuler" title="Annuler">✕</button>
    </div>

    <?php // Les photos et fichiers choisis, en attente d'envoi : le script les montre ici. ?>
    <div class="chat__apercus" data-chat-apercus hidden></div>

    <form class="chat__saisie" method="post" action="<?= url('amis/' . $actif . '/messages') ?>" data-chat-formulaire
          enctype="multipart/form-data"
          data-extensions="<?= e(implode(',', Amis::extensionsFichiers())) ?>"
          data-fichier-max="<?= Amis::fichierMax() ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <label class="sr-only" for="chat-texte">Message à <?= e((string) $ami['pseudo']) ?></label>
      <textarea id="chat-texte" name="texte" rows="1" maxlength="<?= Amis::MESSAGE_MAX ?>" required
                placeholder="Écrire à <?= e((string) $ami['pseudo']) ?>…" autofocus></textarea>
      <?php // Joindre des photos ou des fichiers : le bouton ouvre le choix de fichiers, gardé caché. ?>
      <input type="file" name="fichier" multiple
             accept="<?= e(implode(',', array_map(static fn (string $x): string => '.' . $x, Amis::extensionsFichiers()))) ?>"
             class="sr-only" id="chat-image" data-chat-image>
      <label class="chat__emoji-bouton chat__image-bouton" for="chat-image" title="Joindre une photo ou un fichier" data-chat-image-bouton>
        <span aria-hidden="true">📎</span><span class="sr-only">Joindre une photo ou un fichier</span>
      </label>
      <?php // Le choix des emojis : le panneau est rempli par le script, qui seul peut les insérer. ?>
      <button class="chat__emoji-bouton" type="button" data-emoji-bouton hidden
              aria-label="Insérer un emoji" title="Emojis" aria-expanded="false" aria-controls="chat-emojis">😊</button>
      <button class="bouton" type="submit">Envoyer</button>
      <div class="emojis" id="chat-emojis" data-emoji-panneau role="dialog" aria-label="Emojis" hidden></div>
    </form>
    <p class="chat__erreur" data-chat-erreur role="alert" hidden></p>
  </section>
</div>
