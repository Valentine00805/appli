<?php
/**
 * Une conversation avec un ami, ou un groupe : la liste des discussions à
 * gauche, le fil à droite.
 *
 * Le script de la page envoie sans recharger et va chercher les nouveaux
 * messages toutes les quelques secondes. Sans lui, le formulaire s'envoie
 * normalement et la page se relit.
 *
 * @var ?array{id: int, pseudo: string} $ami     l'ami, pour une discussion à deux
 * @var ?array $groupe                           le groupe, pour une discussion de groupe
 * @var int $nombreMembres
 * @var list<array> $messages
 * @var int $vuJusqua
 * @var list<array> $amis
 * @var array<int, array> $derniers
 * @var list<array> $epingles  mes messages épinglés dans cette conversation
 * @var ?int $cible  le message sur lequel ouvrir la conversation
 */
$csrf = Session::jetonCsrf();
$enGroupe = isset($groupe);
// Tout ce qui diffère entre une discussion à deux et un groupe : les adresses et les titres.
if ($enGroupe) {
    $actif = null;
    $groupeActif = (int) $groupe['id'];
    $base = 'groupes/' . $groupeActif;
    $baseMessages = 'groupes/messages';
    $titre = (string) $groupe['nom'];
    $aide = $nombreMembres . ' membres · réglages, photos et fichiers';
    $lienInfo = url($base . '/reglages');
    $libelleInfo = '⚙️ Réglages';
    $initiale = '👥';
    $destinataire = 'au groupe';
} else {
    $actif = (int) $ami['id'];
    $groupeActif = null;
    $base = 'amis/' . $actif;
    $baseMessages = 'amis/messages';
    $titre = (string) $ami['pseudo'];
    $aide = 'Profil, photos et fichiers';
    $lienInfo = url($base . '/profil');
    $libelleInfo = 'ℹ️ Profil';
    $initiale = mb_strtoupper(mb_substr($titre, 0, 1));
    $destinataire = 'à ' . $titre;
}
$dernierId = $messages === [] ? 0 : (int) end($messages)['id'];
$dernierMien = 0;
foreach ($messages as $m) {
    if ($m['moi'] && $m['evenement'] === null) { $dernierMien = $m['id']; }
}
?>

<div class="chat">
  <aside class="carte chat__amis" aria-label="Mes discussions">
    <p class="discussions-titre" style="margin:0 0 .6rem">
      <a href="<?= url('amis') ?>">← Amis et demandes</a>
      <a class="bouton bouton--secondaire bouton--petit" href="<?= url('groupes/nouveau') ?>" data-fenetre>👥 Nouveau groupe</a>
    </p>
    <?php require __DIR__ . '/_liste.php'; ?>
  </aside>

  <section class="carte chat__fil"
           data-chat
           data-nouveaux="<?= e(url($base . '/messages')) ?>"
           data-envoyer="<?= e(url($base . '/messages')) ?>"
           data-jeton="<?= e($csrf) ?>"
           data-dernier="<?= $dernierId ?>"
           data-supprimer="<?= e(url($baseMessages . '/0/supprimer')) ?>"
           data-modifier="<?= e(url($baseMessages . '/0/modifier')) ?>"
           data-reagir="<?= e(url($baseMessages . '/0/reaction')) ?>"
           data-epingler="<?= e(url($baseMessages . '/0/epingle')) ?>"
           data-conversation="<?= e(url($base)) ?>"
           data-rechercher="<?= e(url($base . '/recherche')) ?>"
           <?= $enGroupe ? 'data-groupe' : '' ?>
           <?= $cible !== null ? 'data-cible="' . (int) $cible . '"' : '' ?>
           data-reactions-rapides="<?= e(implode(' ', Amis::REACTIONS_RAPIDES)) ?>"
           data-maintenant="<?= e($maintenant) ?>"
           data-ami="<?= e($titre) ?>"
           data-transcription="<?= (int) (Auth::utilisateur()['transcription_vocale'] ?? 1) ?>"
           data-vu="<?= (int) $vuJusqua ?>">
    <header class="chat__entete">
      <a class="chat__retour" href="<?= url('amis') ?>" aria-label="Retour aux amis">←</a>
      <?php // Le profil de l'ami, ou les réglages du groupe, en fenêtre. ?>
      <a class="chat__profil" href="<?= e($lienInfo) ?>" data-fenetre title="<?= $enGroupe ? 'Réglages du groupe' : 'Voir le profil' ?>">
        <?php if ($enGroupe): ?>
          <?= Conversations::avatar($groupeActif, $groupe['photo_nom']) ?>
        <?php else: ?>
          <span class="avatar" aria-hidden="true"><?= e($initiale) ?></span>
        <?php endif; ?>
        <span class="chat__profil-texte">
          <h1 class="chat__titre" data-chat-titre><?= e($titre) ?></h1>
          <span class="chat__profil-aide"><?= e($aide) ?></span>
        </span>
      </a>
      <?php // Chercher dans la conversation : le champ s'ouvre sous le bouton, un résultat ramène au message. ?>
      <button class="bouton bouton--secondaire bouton--petit chat__recherche-bouton" type="button" data-recherche-bouton
              aria-expanded="false" aria-controls="chat-recherche" title="Rechercher dans la conversation" aria-label="Rechercher dans la conversation">🔎</button>
      <?php // Les messages épinglés : la liste s'ouvre sous le bouton, un clic ramène au message. ?>
      <button class="bouton bouton--secondaire bouton--petit chat__epingles-bouton" type="button" data-epingles-bouton
              aria-expanded="false" aria-controls="chat-epingles" title="Messages épinglés">
        📌 <span class="chat__epingles-nombre" data-epingles-nombre><?= count($epingles) ?></span>
      </button>
      <a class="bouton bouton--secondaire bouton--petit chat__profil-bouton" href="<?= e($lienInfo) ?>" data-fenetre><?= e($libelleInfo) ?></a>
    </header>

    <div class="epingles recherche-chat" id="chat-recherche" data-recherche-panneau hidden role="search">
      <label class="sr-only" for="chat-recherche-champ">Rechercher dans la conversation</label>
      <input type="search" id="chat-recherche-champ" class="recherche-chat__champ" data-recherche-champ
             placeholder="Rechercher un message…" autocomplete="off" maxlength="100">
      <p class="epingles__titre recherche-chat__etat" data-recherche-etat aria-live="polite">Tapez au moins deux caractères.</p>
      <ul class="epingles__liste" data-recherche-liste></ul>
    </div>

    <div class="epingles" id="chat-epingles" data-epingles-panneau hidden>
      <p class="epingles__titre">📌 Messages épinglés</p>
      <ul class="epingles__liste" data-epingles-liste>
        <?php foreach ($epingles as $ep): ?>
          <li class="epingles__ligne">
            <button type="button" class="epingles__element" data-aller-message="<?= $ep['id'] ?>">
              <span class="epingles__entete"><strong><?= e($ep['auteur']) ?></strong><span><?= e($ep['quand']) ?></span></span>
              <span class="epingles__extrait"><?= Amis::extraitHtml($ep['extrait']) ?></span>
            </button>
            <button type="button" class="epingles__retirer" data-desepingler="<?= $ep['id'] ?>"
                    title="Retirer des messages épinglés" aria-label="Retirer des messages épinglés"><?= Amis::poubelle() ?></button>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="epingles__vide" data-epingles-vide<?= $epingles === [] ? '' : ' hidden' ?>>
        Aucun message épinglé. Cliquez sur un message, puis « Épingler ».
      </p>
    </div>

    <?php // Le fond d'écran, commun aux deux amis ; la page suit ses changements. ?>
    <div class="chat__messages<?= $adresseFond !== null ? ' chat__messages--fond' : '' ?>" data-chat-messages aria-live="polite"
         data-fond="<?= e((string) $adresseFond) ?>"<?= $adresseFond !== null ? ' style="--fond-discussion: url(&quot;' . e($adresseFond) . '&quot;)"' : '' ?>>
      <?php if ($messages === []): ?>
        <p class="chat__vide" data-chat-vide>Aucun message pour l’instant. Écrivez le premier !</p>
      <?php endif; ?>
      <?php $jour = null; $auteurPrecedent = null; ?>
      <?php foreach ($messages as $m): ?>
        <?php if ($m['jour'] !== $jour): $jour = $m['jour']; ?>
          <p class="chat__jour" data-jour="<?= e($m['jour']) ?>"><span><?= e($m['jour_libelle']) ?></span></p>
        <?php $auteurPrecedent = null; endif; ?>
        <?php if ($m['evenement'] !== null): $auteurPrecedent = null; ?>
          <?php // Une note de la discussion : au centre, sans menu. ?>
          <p class="chat__evenement" data-evenement="<?= (int) $m['id'] ?>"><span><?= e($m['evenement']) ?> · <?= e($m['heure']) ?></span></p>
          <?php continue; ?>
        <?php endif; ?>
        <?php
        /*
         * Un clic, ou un appui long sur un téléphone, ouvre le menu de la bulle :
         * répondre, modifier (ses messages), supprimer. C'est le script qui le montre.
         */
        ?>
        <div class="bulle<?= $m['moi'] ? ' bulle--moi' : '' ?><?= $m['image'] !== null ? ' bulle--image' : '' ?><?= $m['supprime'] ? ' bulle--supprime' : '' ?><?= $m['epingle'] ? ' bulle--epingle' : '' ?>"
             data-message="<?= (int) $m['id'] ?>" id="message-<?= (int) $m['id'] ?>" tabindex="0" aria-haspopup="menu"
             <?= $enGroupe ? 'data-auteur="' . e($m['auteur']) . '" data-auteur-id="' . (int) $m['auteur_id'] . '"' : '' ?>
             <?= $m['image'] !== null || $m['fichier'] !== null || $m['vocal'] !== null ? 'data-piece' : '' ?>>
          <?php // Dans un groupe, le nom de qui écrit, en tête d'une suite de ses messages. ?>
          <?php if ($enGroupe && !$m['moi'] && $auteurPrecedent !== $m['auteur_id']): ?>
            <span class="bulle__auteur"><?= e($m['auteur']) ?></span>
          <?php endif; ?>
          <?php $auteurPrecedent = $m['auteur_id'] ?? null; ?>
          <?php if ($m['reponse'] !== null): ?>
            <a class="bulle__citation" href="#message-<?= $m['reponse']['id'] ?>" data-citation="<?= $m['reponse']['id'] ?>">
              <span class="bulle__citation-auteur"><?= e($m['reponse']['auteur']) ?></span>
              <span class="bulle__citation-extrait" data-extrait-de="<?= $m['reponse']['id'] ?>"><?= Amis::extraitHtml($m['reponse']['extrait']) ?></span>
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
          <?php if ($m['vocal'] !== null): ?>
            <?php // Un message vocal : un lecteur compact, que le script anime. ?>
            <div class="bulle__vocal" data-vocal data-duree="<?= $m['vocal']['duree'] ?>">
              <button type="button" class="bulle__vocal-lecture" data-vocal-lecture aria-label="Écouter le message vocal">▶</button>
              <span class="bulle__vocal-piste" data-vocal-piste><span class="bulle__vocal-avance" data-vocal-avance></span></span>
              <span class="bulle__vocal-temps" data-vocal-temps><?= e($m['vocal']['duree_texte']) ?></span>
              <audio preload="none" src="<?= e($m['vocal']['url']) ?>"></audio>
            </div>
            <?php if ($m['vocal']['transcription'] !== null): ?>
              <details class="bulle__transcription">
                <summary>Transcription</summary>
                <p><?= e($m['vocal']['transcription']) ?></p>
              </details>
            <?php endif; ?>
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
          <span class="bulle__heure"><span class="bulle__epingle" title="Épinglé" aria-label="Épinglé">📌 </span><?php if ($m['modifie']): ?><span class="bulle__modifie">modifié · </span><?php endif; ?><?= e($m['heure']) ?></span>
          <?php // Les réactions : un clic sur l'une pose ou retire la sienne. ?>
          <?php if ($m['reactions'] !== []): ?>
            <div class="bulle__reactions">
              <?php foreach ($m['reactions'] as $r): ?>
                <button type="button" class="reaction<?= $r['moi'] ? ' reaction--moi' : '' ?>" data-reaction="<?= e($r['emoji']) ?>"
                        aria-pressed="<?= $r['moi'] ? 'true' : 'false' ?>" title="<?= e($r['qui']) ?>"><?= e($r['emoji']) ?><?php if ($r['nombre'] > 1): ?> <span class="reaction__nombre"><?= (int) $r['nombre'] ?></span><?php endif; ?></button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php // « Vu » sous mon dernier message, s'il a été lu — pas sous la réponse qui l'a suivi. ?>
        <?php if ($m['id'] === $dernierMien): ?>
          <p class="chat__vu" data-chat-vu<?= $vuJusqua >= $dernierMien ? '' : ' hidden' ?>><?= $enGroupe ? 'Vu par tous' : 'Vu' ?></p>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if ($dernierMien === 0): ?>
        <p class="chat__vu" data-chat-vu hidden><?= $enGroupe ? 'Vu par tous' : 'Vu' ?></p>
      <?php endif; ?>
    </div>

    <?php // Remonté dans la conversation : la flèche ramène en bas, et compte les messages arrivés entre-temps. ?>
    <button type="button" class="chat__en-bas" data-aller-en-bas hidden
            title="Revenir en bas de la conversation" aria-label="Revenir en bas de la conversation">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 5v14"/><path d="M6 13l6 6 6-6"/></svg>
      <span class="chat__en-bas-nombre" data-en-bas-nombre hidden></span>
    </button>

    <?php // Répondre à un message ou en modifier un : le bandeau le rappelle au-dessus de la saisie. ?>
    <div class="chat__contexte" data-chat-contexte hidden>
      <span class="chat__contexte-texte">
        <strong data-contexte-titre></strong>
        <span class="chat__contexte-extrait" data-contexte-extrait></span>
      </span>
      <button class="chat__contexte-annuler" type="button" data-contexte-annuler aria-label="Annuler" title="Annuler">✕</button>
    </div>

    <?php // Pendant un enregistrement vocal, cette barre prend la place de la saisie. ?>
    <div class="chat__enregistrement" data-vocal-barre hidden>
      <span class="chat__enregistrement-point" aria-hidden="true"></span>
      <span class="chat__enregistrement-texte">Enregistrement… <strong data-vocal-chrono>0:00</strong>
        <span class="chat__enregistrement-transcription" data-vocal-transcription hidden></span>
      </span>
      <button class="bouton bouton--discret" type="button" data-vocal-annuler>✕ Annuler</button>
      <button class="bouton" type="button" data-vocal-envoyer>Envoyer</button>
    </div>

    <?php // Les photos et fichiers choisis, en attente d'envoi : le script les montre ici. ?>
    <div class="chat__apercus" data-chat-apercus hidden></div>

    <form class="chat__saisie" method="post" action="<?= url($base . '/messages') ?>" data-chat-formulaire
          enctype="multipart/form-data"
          data-extensions="<?= e(implode(',', Amis::extensionsFichiers())) ?>"
          data-fichier-max="<?= Amis::fichierMax() ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <label class="sr-only" for="chat-texte">Message <?= e($destinataire) ?></label>
      <textarea id="chat-texte" name="texte" rows="1" maxlength="<?= Amis::MESSAGE_MAX ?>" required
                placeholder="Écrire <?= e($destinataire) ?>…" autofocus></textarea>
      <?php // Joindre des photos ou des fichiers : le bouton ouvre le choix de fichiers, gardé caché. ?>
      <input type="file" name="fichier" multiple
             accept="<?= e(implode(',', array_map(static fn (string $x): string => '.' . $x, Amis::extensionsFichiers()))) ?>"
             class="sr-only" id="chat-image" data-chat-image>
      <label class="chat__emoji-bouton chat__image-bouton" for="chat-image" title="Joindre une photo ou un fichier" data-chat-image-bouton>
        <span aria-hidden="true">📎</span><span class="sr-only">Joindre une photo ou un fichier</span>
      </label>
      <?php // Un message vocal : le bouton n'apparaît que si le navigateur sait enregistrer. ?>
      <button class="chat__emoji-bouton chat__vocal-bouton" type="button" data-vocal-bouton hidden
              title="Enregistrer un message vocal" aria-label="Enregistrer un message vocal">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
          <rect x="9" y="3" width="6" height="12" rx="3" fill="currentColor"/><path d="M5.5 11a6.5 6.5 0 0 0 13 0"/><path d="M12 17.5V21"/>
        </svg>
      </button>
      <?php // Le choix des emojis : le panneau est rempli par le script, qui seul peut les insérer. ?>
      <button class="chat__emoji-bouton" type="button" data-emoji-bouton hidden
              aria-label="Insérer un emoji" title="Emojis" aria-expanded="false" aria-controls="chat-emojis">😊</button>
      <button class="bouton" type="submit">Envoyer</button>
      <div class="emojis" id="chat-emojis" data-emoji-panneau role="dialog" aria-label="Emojis" hidden></div>
    </form>
    <p class="chat__erreur" data-chat-erreur role="alert" hidden></p>
  </section>
</div>
