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
 * @var ?array $serveur  le serveur, quand la discussion est l'un de ses salons (alors $salons et $membresServeur sont là aussi)
 */
$csrf = Session::jetonCsrf();
$enGroupe = isset($groupe);
$enSalon = isset($serveur);
// Tout ce qui diffère entre une discussion à deux et un groupe : les adresses et les titres.
if ($enGroupe) {
    $actif = null;
    $groupeActif = (int) $groupe['id'];
    $base = 'groupes/' . $groupeActif;
    $baseMessages = 'groupes/messages';
    $titre = ($enSalon ? '# ' : '') . (string) $groupe['nom'];
    $aide = $enSalon ? t('srv.salon_aide', ['serveur' => (string) $serveur['nom'], 'membres' => tn('srv.membres_n', $nombreMembres)]) : t('chat.membres_reglages', ['n' => $nombreMembres]);
    $lienInfo = url($base . '/reglages');
    $libelleInfo = t('chat.reglages');
    $initiale = '👥';
    $destinataire = $enSalon ? t('srv.vers_salon', ['nom' => (string) $groupe['nom']]) : t('chat.au_groupe');
} else {
    $actif = (int) $ami['id'];
    $groupeActif = null;
    $base = 'amis/' . $actif;
    $baseMessages = 'amis/messages';
    $titre = (string) $ami['pseudo'];
    $aide = t('chat.profil_aide');
    $lienInfo = url($base . '/profil');
    $libelleInfo = t('chat.profil');
    $initiale = mb_strtoupper(mb_substr($titre, 0, 1));
    $destinataire = t('chat.a_qui', ['qui' => $titre]);
}
$dernierId = $messages === [] ? 0 : (int) end($messages)['id'];
$dernierMien = 0;
foreach ($messages as $m) {
    if ($m['moi'] && $m['evenement'] === null) { $dernierMien = $m['id']; }
}
?>

<div class="avec-barre">
<?= Vue::rendre('serveurs/_barre', ['barreActive' => $enSalon ? (int) $serveur['id'] : 'messages']) ?>
<div class="chat avec-barre__page">
  <?php if ($enSalon): ?>
    <?= Vue::rendre('serveurs/_rail', ['serveur' => $serveur, 'salons' => $salons, 'membresServeur' => $membresServeur, 'salonActif' => $groupeActif]) ?>
  <?php else: ?>
  <aside class="carte chat__amis" aria-label="<?= e(t('chat.mes_discussions')) ?>">
    <?php require __DIR__ . '/_entete_discussions.php'; ?>
    <?php require __DIR__ . '/_liste.php'; ?>
  </aside>
  <?php endif; ?>

  <section class="carte chat__fil"
           data-chat
           data-nouveaux="<?= e(url($base . '/messages')) ?>"
           data-envoyer="<?= e(url($base . '/messages')) ?>"
           data-jeton="<?= e($csrf) ?>"
           data-dernier="<?= $dernierId ?>"
           data-supprimer="<?= e(url($baseMessages . '/0/supprimer')) ?>"
           data-modifier="<?= e(url($baseMessages . '/0/modifier')) ?>"
           data-reagir="<?= e(url($baseMessages . '/0/reaction')) ?>"
           data-sondages="<?= e(url($base . '/sondages')) ?>"
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
      <a class="chat__retour" href="<?= url($enSalon ? 'serveurs' : 'amis') ?>" aria-label="<?= e(t('chat.retour_amis')) ?>">←</a>
      <?php // Le profil de l'ami, ou les réglages du groupe, en fenêtre. ?>
      <a class="chat__profil" href="<?= e($lienInfo) ?>" data-fenetre title="<?= e(t($enGroupe ? 'chat.reglages_groupe' : 'chat.voir_profil')) ?>">
        <?php if ($enGroupe): ?>
          <?= $enSalon ? '<span class="avatar avatar--groupe" aria-hidden="true">#</span>' : Conversations::avatar($groupeActif, $groupe['photo_nom']) ?>
        <?php else: ?>
          <?= Amis::avatar($actif, $titre) ?>
        <?php endif; ?>
        <span class="chat__profil-texte">
          <h1 class="chat__titre" data-chat-titre><?= e($titre) ?></h1>
          <span class="chat__profil-aide"><?= e($aide) ?></span>
        </span>
      </a>
      <?php // Chercher dans la conversation : le champ s'ouvre sous le bouton, un résultat ramène au message. ?>
      <button class="bouton bouton--secondaire bouton--petit chat__recherche-bouton" type="button" data-recherche-bouton
              aria-expanded="false" aria-controls="chat-recherche" title="<?= e(t('chat.rechercher')) ?>" aria-label="<?= e(t('chat.rechercher')) ?>">🔎</button>
      <?php // Les messages épinglés : la liste s'ouvre sous le bouton, un clic ramène au message. ?>
      <button class="bouton bouton--secondaire bouton--petit chat__epingles-bouton" type="button" data-epingles-bouton
              aria-expanded="false" aria-controls="chat-epingles" title="<?= e(t('chat.epingles_bouton')) ?>">
        📌 <span class="chat__epingles-nombre" data-epingles-nombre><?= count($epingles) ?></span>
      </button>
      <a class="bouton bouton--secondaire bouton--petit chat__profil-bouton" href="<?= e($lienInfo) ?>" data-fenetre><?= e($libelleInfo) ?></a>
    </header>

    <div class="epingles recherche-chat" id="chat-recherche" data-recherche-panneau hidden role="search">
      <label class="sr-only" for="chat-recherche-champ"><?= e(t('chat.rechercher')) ?></label>
      <input type="search" id="chat-recherche-champ" class="recherche-chat__champ" data-recherche-champ
             placeholder="<?= e(t('chat.rechercher_message')) ?>" autocomplete="off" maxlength="100">
      <p class="epingles__titre recherche-chat__etat" data-recherche-etat aria-live="polite"><?= e(t('chat.deux_caracteres')) ?></p>
      <ul class="epingles__liste" data-recherche-liste></ul>
    </div>

    <div class="epingles" id="chat-epingles" data-epingles-panneau hidden>
      <p class="epingles__titre"><?= e(t('chat.epingles_titre')) ?></p>
      <ul class="epingles__liste" data-epingles-liste>
        <?php foreach ($epingles as $ep): ?>
          <li class="epingles__ligne">
            <button type="button" class="epingles__element" data-aller-message="<?= $ep['id'] ?>">
              <span class="epingles__entete"><strong><?= e($ep['auteur']) ?></strong><span><?= e($ep['quand']) ?></span></span>
              <span class="epingles__extrait"><?= Amis::extraitHtml($ep['extrait']) ?></span>
            </button>
            <button type="button" class="epingles__retirer" data-desepingler="<?= $ep['id'] ?>"
                    title="<?= e(t('chat.desepingler')) ?>" aria-label="<?= e(t('chat.desepingler')) ?>"><?= Amis::poubelle() ?></button>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="epingles__vide" data-epingles-vide<?= $epingles === [] ? '' : ' hidden' ?>>
        <?= e(t('chat.aucun_epingle')) ?>
      </p>
    </div>

    <?php // Le fond d'écran, commun aux deux amis ; la page suit ses changements. ?>
    <div class="chat__messages<?= $adresseFond !== null ? ' chat__messages--fond' : '' ?>" data-chat-messages aria-live="polite"
         data-fond="<?= e((string) $adresseFond) ?>"<?= $adresseFond !== null ? ' style="--fond-discussion: url(&quot;' . e($adresseFond) . '&quot;)"' : '' ?>>
      <?php if ($messages === []): ?>
        <p class="chat__vide" data-chat-vide><?= e(t('chat.aucun_message')) ?></p>
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
             <?= $m['image'] !== null || $m['fichier'] !== null || $m['vocal'] !== null || $m['partage'] !== null || $m['sondage'] !== null ? 'data-piece' : '' ?>>
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
            <p class="bulle__texte"><?= e(t('js.chat.message_supprime')) ?></p>
          <?php endif; ?>
          <?php if ($m['partage'] !== null): ?>
            <?php // Un cours ou un fichier partagé : une carte qui l'ouvre, en lecture. ?>
            <?php $p = $m['partage']; ?>
            <<?= $p['url'] !== null ? 'a href="' . e($p['url']) . '" data-fenetre' : 'div' ?> class="bulle__partage<?= $p['url'] === null ? ' bulle__partage--mort' : '' ?>">
              <span class="bulle__partage-icone" aria-hidden="true"><?= e($p['icone']) ?></span>
              <span class="bulle__partage-texte">
                <span class="bulle__partage-titre"><?= e($p['titre']) ?></span>
                <span class="bulle__partage-detail"><?= Partages::icone(12) ?> <?= e($p['detail']) ?></span>
              </span>
            </<?= $p['url'] !== null ? 'a' : 'div' ?>>
          <?php endif; ?>
          <?php if ($m['sondage'] !== null): ?>
            <?php
            /*
             * Un sondage : le script dessine les options et prend les votes (depuis « data-sondage »). Sans lui, la question
             * et les résultats se lisent quand même.
             */
            $sondage = $m['sondage'];
            ?>
            <div class="bulle__sondage" data-sondage="<?= e((string) json_encode($sondage, JSON_UNESCAPED_UNICODE)) ?>">
              <p class="sondage__question"><?= e($sondage['question']) ?></p>
              <ul class="sondage__options">
                <?php foreach ($sondage['options'] as $o): ?>
                  <li class="sondage__option<?= $o['moi'] ? ' sondage__option--moi' : '' ?>">
                    <span class="sondage__texte"><?= e($o['texte']) ?></span> <span class="sondage__nombre"><?= (int) $o['nombre'] ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <?php if ($m['image'] !== null): ?>
            <a class="bulle__image" href="<?= e($m['image']) ?>" target="_blank" rel="noopener" data-visionneuse>
              <img src="<?= e($m['image']) ?>" alt="<?= e(t('chat.photo')) ?>"
                   <?= $m['largeur'] > 0 ? 'width="' . $m['largeur'] . '" height="' . $m['hauteur'] . '"' : '' ?>>
            </a>
          <?php endif; ?>
          <?php if ($m['vocal'] !== null): ?>
            <?php // Un message vocal : un lecteur compact, que le script anime. ?>
            <div class="bulle__vocal" data-vocal data-duree="<?= $m['vocal']['duree'] ?>">
              <button type="button" class="bulle__vocal-lecture" data-vocal-lecture aria-label="<?= e(t('chat.ecouter_vocal')) ?>">▶</button>
              <span class="bulle__vocal-piste" data-vocal-piste><span class="bulle__vocal-avance" data-vocal-avance></span></span>
              <span class="bulle__vocal-temps" data-vocal-temps><?= e($m['vocal']['duree_texte']) ?></span>
              <audio preload="none" src="<?= e($m['vocal']['url']) ?>"></audio>
            </div>
            <?php if ($m['vocal']['transcription'] !== null): ?>
              <details class="bulle__transcription">
                <summary><?= e(t('js.chat.transcription')) ?></summary>
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
                 title="<?= e(t('commun.telecharger')) ?>" aria-label="<?= e(t('prf.telecharger_nom', ['nom' => $m['fichier']['nom']])) ?>">⬇</a>
            </div>
          <?php endif; ?>
          <?php if ($m['texte'] !== '' && $m['sondage'] === null): ?>
            <p class="bulle__texte"><?= nl2br(e($m['texte'])) ?></p>
          <?php endif; ?>
          <span class="bulle__heure"><span class="bulle__epingle" title="<?= e(t('chat.epingle')) ?>" aria-label="<?= e(t('chat.epingle')) ?>">📌 </span><?php if ($m['modifie']): ?><span class="bulle__modifie"><?= e(t('js.chat.modifie')) ?></span><?php endif; ?><?= e($m['heure']) ?></span>
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
          <p class="chat__vu" data-chat-vu<?= $vuJusqua >= $dernierMien ? '' : ' hidden' ?>><?= e(t($enGroupe ? 'js.chat.vu_tous' : 'js.chat.vu')) ?></p>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if ($dernierMien === 0): ?>
        <p class="chat__vu" data-chat-vu hidden><?= e(t($enGroupe ? 'js.chat.vu_tous' : 'js.chat.vu')) ?></p>
      <?php endif; ?>
    </div>

    <?php // Remonté dans la conversation : la flèche ramène en bas, et compte les messages arrivés entre-temps. ?>
    <button type="button" class="chat__en-bas" data-aller-en-bas hidden
            title="<?= e(t('chat.revenir_en_bas')) ?>" aria-label="<?= e(t('chat.revenir_en_bas')) ?>">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 5v14"/><path d="M6 13l6 6 6-6"/></svg>
      <span class="chat__en-bas-nombre" data-en-bas-nombre hidden></span>
    </button>

    <?php // Répondre à un message ou en modifier un : le bandeau le rappelle au-dessus de la saisie. ?>
    <div class="chat__contexte" data-chat-contexte hidden>
      <span class="chat__contexte-texte">
        <strong data-contexte-titre></strong>
        <span class="chat__contexte-extrait" data-contexte-extrait></span>
      </span>
      <button class="chat__contexte-annuler" type="button" data-contexte-annuler aria-label="<?= e(t('commun.annuler')) ?>" title="<?= e(t('commun.annuler')) ?>">✕</button>
    </div>

    <?php // Pendant un enregistrement vocal, cette barre prend la place de la saisie. ?>
    <div class="chat__enregistrement" data-vocal-barre hidden>
      <span class="chat__enregistrement-point" aria-hidden="true"></span>
      <span class="chat__enregistrement-texte"><?= e(t('js.chat.enregistrement')) ?> <strong data-vocal-chrono>0:00</strong>
        <span class="chat__enregistrement-transcription" data-vocal-transcription hidden></span>
      </span>
      <button class="bouton bouton--discret" type="button" data-vocal-annuler><?= e(t('js.chat.annuler_croix')) ?></button>
      <button class="bouton" type="button" data-vocal-envoyer><?= e(t('js.chat.envoyer')) ?></button>
    </div>

    <?php // Les photos et fichiers choisis, en attente d'envoi : le script les montre ici. ?>
    <div class="chat__apercus" data-chat-apercus hidden></div>

    <form class="chat__saisie" method="post" action="<?= url($base . '/messages') ?>" data-chat-formulaire
          enctype="multipart/form-data"
          data-extensions="<?= e(implode(',', Amis::extensionsFichiers())) ?>"
          data-fichier-max="<?= Amis::fichierMax() ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <label class="sr-only" for="chat-texte"><?= e(t('chat.message_a', ['qui' => $destinataire])) ?></label>
      <textarea id="chat-texte" name="texte" rows="1" maxlength="<?= Amis::MESSAGE_MAX ?>" required
                placeholder="<?= e(t('chat.ecrire_a', ['qui' => $destinataire])) ?>" autofocus></textarea>
      <?php // Joindre des photos ou des fichiers : le bouton ouvre le choix de fichiers, gardé caché. ?>
      <input type="file" name="fichier" multiple
             accept="<?= e(implode(',', array_map(static fn (string $x): string => '.' . $x, Amis::extensionsFichiers()))) ?>"
             class="sr-only" id="chat-image" data-chat-image>
      <label class="chat__emoji-bouton chat__image-bouton" for="chat-image" title="<?= e(t('chat.joindre')) ?>" data-chat-image-bouton>
        <span aria-hidden="true">📎</span><span class="sr-only"><?= e(t('chat.joindre')) ?></span>
      </label>
      <?php // Un sondage : le bouton n'apparaît que si le navigateur sait ouvrir la fenêtre (le script le montre). ?>
      <button class="chat__emoji-bouton chat__sondage-bouton" type="button" data-sondage-ouvrir hidden
              title="<?= e(t('son.titre')) ?>" aria-label="<?= e(t('son.titre')) ?>">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
          <path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>
        </svg>
      </button>
      <?php // Un message vocal : le bouton n'apparaît que si le navigateur sait enregistrer. ?>
      <button class="chat__emoji-bouton chat__vocal-bouton" type="button" data-vocal-bouton hidden
              title="<?= e(t('chat.vocal_bouton')) ?>" aria-label="<?= e(t('chat.vocal_bouton')) ?>">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
          <rect x="9" y="3" width="6" height="12" rx="3" fill="currentColor"/><path d="M5.5 11a6.5 6.5 0 0 0 13 0"/><path d="M12 17.5V21"/>
        </svg>
      </button>
      <?php // Le choix des emojis : le panneau est rempli par le script, qui seul peut les insérer. ?>
      <button class="chat__emoji-bouton" type="button" data-emoji-bouton hidden
              aria-label="<?= e(t('chat.inserer_emoji')) ?>" title="<?= e(t('chat.emojis')) ?>" aria-expanded="false" aria-controls="chat-emojis">😊</button>
      <button class="bouton" type="submit"><?= e(t('js.chat.envoyer')) ?></button>
      <div class="emojis" id="chat-emojis" data-emoji-panneau role="dialog" aria-label="<?= e(t('chat.emojis')) ?>" hidden></div>
    </form>
    <p class="chat__erreur" data-chat-erreur role="alert" hidden></p>

    <?php // Créer un sondage : la fenêtre s'ouvre depuis le bouton de la saisie ; le script ajoute des options au fil de l'écriture. ?>
    <dialog class="sondage-fenetre" id="sondage-dialogue" data-sondage-dialogue aria-labelledby="sondage-titre">
      <form class="sondage-form" method="post" action="<?= e(url($base . '/sondages')) ?>" data-sondage-formulaire autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <header class="sondage-form__entete">
          <button type="button" class="sondage-form__fermer" data-sondage-fermer aria-label="<?= e(t('son.fermer')) ?>" title="<?= e(t('son.fermer')) ?>">✕</button>
          <h2 class="sondage-form__titre-fenetre" id="sondage-titre"><?= e(t('son.titre')) ?></h2>
        </header>
        <div class="sondage-form__corps">
          <h3 class="sondage-form__rubrique"><?= e(t('son.question')) ?></h3>
          <input class="sondage-form__champ" type="text" name="question" maxlength="<?= Sondages::QUESTION_MAX ?>" required
                 placeholder="<?= e(t('son.question_aide')) ?>" aria-label="<?= e(t('son.question')) ?>">
          <h3 class="sondage-form__rubrique"><?= e(t('son.options')) ?></h3>
          <div class="sondage-form__options" data-sondage-options>
            <?php for ($i = 0; $i < 2; $i++): ?>
              <input class="sondage-form__champ" type="text" name="options[]" maxlength="<?= Sondages::OPTION_MAX ?>" placeholder="<?= e(t('son.option_aide')) ?>">
            <?php endfor; ?>
          </div>
          <label class="sondage-form__plusieurs">
            <span><?= e(t('son.plusieurs')) ?></span>
            <input type="checkbox" name="multiple" value="1" checked>
          </label>
          <p class="sondage-form__erreur" data-sondage-erreur role="alert" hidden></p>
        </div>
        <footer class="sondage-form__pied">
          <button type="submit" class="sondage-form__envoyer" aria-label="<?= e(t('son.envoyer')) ?>" title="<?= e(t('son.envoyer')) ?>">
            <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor" aria-hidden="true" focusable="false"><path d="M3 20.5v-6.7l9-1.8-9-1.8V3.5L22 12z"/></svg>
          </button>
        </footer>
      </form>
    </dialog>
  </section>
</div>
</div>
<script src="<?= asset('assets/js/sondages.js') ?>" defer></script>
