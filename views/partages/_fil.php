<?php
/**
 * Le fil des commentaires d'un document : chacun, ses réponses dessous, et de
 * quoi répondre ou aimer. Sert la fenêtre des commentaires et la page de
 * lecture d'un partage.
 *
 * @var list<array> $commentaires  les commentaires, chacun avec ses « reponses »
 * @var array $cible               le document commenté
 * @var string $base               « partages/cours/12 »
 * @var string $surPlace           l'attribut qui garde l'envoi dans la fenêtre
 * @var string $depuis             « fil » depuis la fenêtre des commentaires
 */
$csrf = Session::jetonCsrf();
$moi = Auth::id();
$chezMoi = (int) $cible['user_id'] === $moi;
$depuis = $depuis ?? '';

// Un commentaire : qui, quand, quoi, puis aimer, répondre, retirer.
$unCommentaire = static function (array $c, bool $estReponse) use ($csrf, $moi, $chezMoi, $base, $surPlace, $depuis): void {
    $champDepuis = $depuis === '' ? '' : '<input type="hidden" name="depuis" value="' . e($depuis) . '">';
    ?>
    <div class="partage-commentaires__qui">
      <?= Amis::avatar((int) $c['user_id'], (string) $c['pseudo'], 'avatar--mini') ?>
      <strong><?= e((string) $c['pseudo']) ?></strong>
      <span class="discret"><?= e(date_fr(Amis::local((string) $c['created_at'])->format('Y-m-d H:i:s'))) ?></span>
    </div>
    <p class="partage-commentaires__texte"><?= nl2br(e((string) $c['texte'])) ?></p>
    <div class="partage-commentaires__gestes">
      <form method="post" action="<?= url('partages/commentaires/' . (int) $c['id'] . '/aimer') ?>"<?= $surPlace ?>>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <?= $champDepuis ?>
        <button class="bouton bouton--discret bouton--petit bouton-jaime" type="submit"
                aria-pressed="<?= $c['moi_jaime'] ? 'true' : 'false' ?>"
                title="<?= $c['moi_jaime'] ? 'Je n’aime plus' : 'J’aime' ?>">
          <?= $c['moi_jaime'] ? '♥' : '♡' ?><?= $c['nb_jaime'] > 0 ? ' ' . (int) $c['nb_jaime'] : '' ?>
          <span class="sr-only"><?= $c['moi_jaime'] ? 'Je n’aime plus' : 'J’aime' ?></span>
        </button>
      </form>
      <?php if ((int) $c['user_id'] === $moi || $chezMoi): ?>
        <form method="post" action="<?= url('partages/commentaires/' . (int) $c['id'] . '/retirer') ?>"<?= $surPlace ?>
              data-confirmation="<?= $estReponse ? 'Retirer cette réponse ?' : 'Retirer ce commentaire et ses réponses ?' ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <?= $champDepuis ?>
          <button class="bouton bouton--discret bouton--petit" type="submit">Retirer</button>
        </form>
      <?php endif; ?>
    </div>
    <?php // Répondre : un champ qui s'ouvre sous le commentaire, sans script. ?>
    <details class="partage-commentaires__repondre">
      <summary>Répondre</summary>
      <form method="post" action="<?= url($base . '/commentaires') ?>"<?= $surPlace ?>>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="reponse_a" value="<?= (int) $c['id'] ?>">
        <?= $champDepuis ?>
        <label class="sr-only" for="reponse-<?= (int) $c['id'] ?>">Répondre à <?= e((string) $c['pseudo']) ?></label>
        <textarea id="reponse-<?= (int) $c['id'] ?>" class="champ-commentaire" name="texte" rows="2" maxlength="<?= Amis::MESSAGE_MAX ?>"
                  placeholder="Répondre à <?= e((string) $c['pseudo']) ?>…"></textarea>
        <button class="bouton bouton--petit" type="submit">Répondre</button>
      </form>
    </details>
    <?php
};
?>
<?php if ($commentaires === []): ?>
  <p class="discret" style="margin:0 0 .6rem">Aucun commentaire pour l’instant.</p>
<?php else: ?>
  <ul class="partage-commentaires">
    <?php foreach ($commentaires as $c): ?>
      <li>
        <?php $unCommentaire($c, false); ?>
        <?php if ($c['reponses'] !== []): ?>
          <ul class="partage-commentaires__reponses">
            <?php foreach ($c['reponses'] as $r): ?>
              <li><?php $unCommentaire($r, true); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<form method="post" action="<?= url($base . '/commentaires') ?>"<?= $surPlace ?>>
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
  <?php if ($depuis !== ''): ?><input type="hidden" name="depuis" value="<?= e($depuis) ?>"><?php endif; ?>
  <div class="champ">
    <label class="sr-only" for="nouveau-commentaire">Votre commentaire</label>
    <textarea id="nouveau-commentaire" class="champ-commentaire" name="texte" rows="2" maxlength="<?= Amis::MESSAGE_MAX ?>"
              placeholder="Une remarque, une question…"></textarea>
  </div>
  <button class="bouton bouton--petit" type="submit">Commenter</button>
</form>
