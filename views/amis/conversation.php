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
        <div class="bulle<?= $m['moi'] ? ' bulle--moi' : '' ?>" data-message="<?= (int) $m['id'] ?>">
          <p class="bulle__texte"><?= nl2br(e($m['texte'])) ?></p>
          <span class="bulle__heure"><?= e($m['heure']) ?></span>
        </div>
      <?php endforeach; ?>
      <p class="chat__vu" data-chat-vu<?= $dernierMien > 0 && $vuJusqua >= $dernierMien ? '' : ' hidden' ?>>Vu</p>
    </div>

    <form class="chat__saisie" method="post" action="<?= url('amis/' . $actif . '/messages') ?>" data-chat-formulaire>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <label class="sr-only" for="chat-texte">Message à <?= e((string) $ami['pseudo']) ?></label>
      <textarea id="chat-texte" name="texte" rows="1" maxlength="<?= Amis::MESSAGE_MAX ?>" required
                placeholder="Écrire à <?= e((string) $ami['pseudo']) ?>…" autofocus></textarea>
      <button class="bouton" type="submit">Envoyer</button>
    </form>
    <p class="chat__erreur" data-chat-erreur role="alert" hidden></p>
  </section>
</div>
