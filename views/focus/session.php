<?php
/**
 * L'écran d'une session : le minuteur, et ce qu'on révise. Rien d'autre.
 *
 * Le menu est masqué pendant la session (la page porte une classe que le
 * style suit), et la fiche du cours est là, sous le minuteur : on révise dans
 * la page, on ne la quitte pas.
 *
 * @var array $session  la ligne de sessions_revision, avec le cours
 * @var int $pause      les minutes de pause du rythme choisi
 */
$minutes = (int) $session['minutes_voulues'];
$fiche = (string) ($session['fiche_revision'] ?? '');
$contenu = (string) ($session['contenu'] ?? '');
?>
<div class="focus" data-focus
     data-id="<?= (int) $session['id'] ?>"
     data-minutes="<?= $minutes ?>"
     data-pause="<?= (int) $pause ?>"
     data-terminer="<?= e(url('focus/' . (int) $session['id'] . '/terminer')) ?>"
     data-csrf="<?= e(Session::jetonCsrf()) ?>">

  <div class="focus__minuteur">
    <p class="focus__quoi">
      <?php if ($session['cours_titre'] !== null): ?>
        📘 <strong><?= e((string) $session['cours_titre']) ?></strong>
        <?php if ($session['matiere_nom'] !== null): ?><span class="discret"> · <?= e((string) $session['matiere_nom']) ?></span><?php endif; ?>
      <?php else: ?>
        <strong>Révision libre</strong>
      <?php endif; ?>
      <?php if ($session['sujet'] !== null): ?><br><span class="discret"><?= e((string) $session['sujet']) ?></span><?php endif; ?>
    </p>

    <div class="focus__anneau" data-focus-anneau role="img" aria-label="Temps restant">
      <svg viewBox="0 0 120 120" aria-hidden="true">
        <circle class="focus__piste" cx="60" cy="60" r="54"></circle>
        <circle class="focus__trait" cx="60" cy="60" r="54" data-focus-trait></circle>
      </svg>
      <span class="focus__temps" data-focus-temps><?= $minutes ?>:00</span>
    </div>

    <p class="focus__phase" data-focus-phase aria-live="polite">Prêt à commencer</p>

    <p class="actions focus__boutons">
      <button class="bouton" type="button" data-focus-bascule>▶️ Démarrer</button>
      <button class="bouton bouton--secondaire" type="button" data-focus-plein-ecran>⛶ Plein écran</button>
      <button class="bouton bouton--secondaire" type="button" data-focus-fin>Terminer la session</button>
    </p>

    <p class="champ__aide" data-focus-compte>Temps travaillé : 0 min · aucune pause</p>
    <?php if ((int) $session['ne_pas_deranger'] === 1): ?>
      <?php // Les rappels attendent : ils repartiront d'eux-mêmes à la fin. ?>
      <p class="champ__aide">🔕 Vos rappels et notifications attendent la fin de la session.</p>
    <?php endif; ?>
  </div>

  <?php if (trim($fiche) !== '' || trim($contenu) !== ''): ?>
    <div class="focus__matiere">
      <?php if (trim($fiche) !== ''): ?>
        <h2>📝 Fiche de révision</h2>
        <div class="texte-riche-affiche"><?= TexteRiche::versHtml($fiche) ?></div>
      <?php endif; ?>
      <?php if (trim($contenu) !== ''): ?>
        <details<?= trim($fiche) === '' ? ' open' : '' ?> style="margin-top:1rem">
          <summary><strong>📘 Le cours en entier</strong></summary>
          <div class="texte-riche-affiche" style="margin-top:.6rem"><?= TexteRiche::versHtml($contenu) ?></div>
        </details>
      <?php endif; ?>
      <?php if ($session['cours_id'] !== null): ?>
        <p class="champ__aide" style="margin-top:1rem">
          <a href="<?= url('revision/' . (int) $session['cours_id']) ?>">Ouvrir la fiche entière</a> —
          la session continue de tourner ailleurs dans l’application.
        </p>
      <?php endif; ?>
    </div>
  <?php elseif ($session['cours_id'] !== null): ?>
    <p class="discret focus__matiere">Ce cours n’a encore ni contenu ni fiche de révision.</p>
  <?php endif; ?>

  <?php // Terminer demande le ressenti : trois boutons, et c'est tout. ?>
  <dialog class="focus__bilan" data-focus-bilan>
    <form method="post" action="<?= url('focus/' . (int) $session['id'] . '/terminer') ?>" data-focus-formulaire>
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <input type="hidden" name="secondes" value="0" data-focus-secondes>
      <input type="hidden" name="pauses" value="0" data-focus-pauses>
      <h2 style="margin-top:0">Session terminée</h2>
      <p data-focus-bilan-texte class="discret">Temps travaillé : 0 min.</p>
      <p>Comment ça s’est passé ?</p>
      <div class="focus__ressentis">
        <?php foreach (Focus::RESSENTIS as $cle => $r): ?>
          <button class="bouton bouton--secondaire" type="submit" name="ressenti" value="<?= e($cle) ?>">
            <?= $r['icone'] ?> <?= e($r['nom']) ?>
          </button>
        <?php endforeach; ?>
      </div>
      <p class="actions" style="margin-bottom:0">
        <button class="bouton bouton--discret bouton--petit" type="submit" name="ressenti" value="">Sans réponse</button>
        <button class="bouton bouton--discret bouton--petit" type="button" data-focus-continuer>Continuer la session</button>
      </p>
    </form>
  </dialog>
</div>
