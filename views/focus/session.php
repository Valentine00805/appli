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
 * @var list<array> $coursDeLaSession  tous les cours de la session, le principal d’abord
 */
$minutes = (int) $session['minutes_voulues'];
$coursDeLaSession = $coursDeLaSession ?? [];
?>
<div class="focus" data-focus
     data-id="<?= (int) $session['id'] ?>"
     data-minutes="<?= $minutes ?>"
     data-pause="<?= (int) $pause ?>"
     data-terminer="<?= e(url('focus/' . (int) $session['id'] . '/terminer')) ?>"
     data-csrf="<?= e(Session::jetonCsrf()) ?>">

  <div class="focus__minuteur">
    <p class="focus__quoi">
      <?php if (count($coursDeLaSession) > 1): ?>
        <?= tn('focus.nb_cours', count($coursDeLaSession)) ?>
        <span class="discret">· <?= e(implode(', ', array_map(
            static fn (array $c): string => (string) $c['titre'], array_slice($coursDeLaSession, 0, 3)))) ?><?php
          ?><?= count($coursDeLaSession) > 3 ? '…' : '' ?></span>
      <?php elseif ($session['cours_titre'] !== null): ?>
        📘 <strong><?= e((string) $session['cours_titre']) ?></strong>
        <?php if ($session['matiere_nom'] !== null): ?><span class="discret"> · <?= e((string) $session['matiere_nom']) ?></span><?php endif; ?>
      <?php else: ?>
        <strong><?= e(t('focus.revision_libre')) ?></strong>
      <?php endif; ?>
      <?php if ($session['sujet'] !== null): ?><br><span class="discret"><?= e((string) $session['sujet']) ?></span><?php endif; ?>
    </p>

    <div class="focus__anneau" data-focus-anneau role="img" aria-label="<?= e(t('focus.temps_restant')) ?>">
      <svg viewBox="0 0 120 120" aria-hidden="true">
        <circle class="focus__piste" cx="60" cy="60" r="54"></circle>
        <circle class="focus__trait" cx="60" cy="60" r="54" data-focus-trait></circle>
      </svg>
      <span class="focus__temps" data-focus-temps><?= $minutes ?>:00</span>
    </div>

    <p class="focus__phase" data-focus-phase aria-live="polite"><?= e(t('js.focus.pret')) ?></p>

    <p class="actions focus__boutons">
      <button class="bouton" type="button" data-focus-bascule><?= e(t('js.focus.bouton_demarrer')) ?></button>
      <button class="bouton bouton--secondaire" type="button" data-focus-plein-ecran><?= e(t('focus.plein_ecran')) ?></button>
      <button class="bouton bouton--secondaire" type="button" data-focus-fin><?= e(t('focus.terminer')) ?></button>
    </p>

    <p class="champ__aide" data-focus-compte><?= e(t('js.focus.compte', ['min' => 0, 'pauses' => t('js.focus.aucune_pause')])) ?></p>
    <?php if ((int) $session['ne_pas_deranger'] === 1): ?>
      <?php // Les rappels attendent : ils repartiront d'eux-mêmes à la fin. ?>
      <p class="champ__aide"><?= e(t('focus.rappels_attendent')) ?></p>
    <?php endif; ?>
  </div>

  <?php if ($coursDeLaSession !== []): ?>
    <?php
    /*
     * Ce qu'on révise, cours par cours : la fiche d'abord, le cours entier
     * replié dessous. Le premier est ouvert — c'est par là qu'on commence —,
     * les suivants attendent qu'on y vienne.
     */
    ?>
    <div class="focus__matiere">
      <?php foreach ($coursDeLaSession as $rang => $c): ?>
        <?php
        $fiche = trim((string) ($c['fiche_revision'] ?? ''));
        $contenu = trim((string) ($c['contenu'] ?? ''));
        $seul = count($coursDeLaSession) === 1;
        ?>
        <section<?= $rang > 0 ? ' style="margin-top:1.6rem"' : '' ?>>
          <h2 style="margin-bottom:.4rem">
            📘 <?= e((string) $c['titre']) ?>
            <?php if (($c['matiere_nom'] ?? null) !== null): ?>
              <span class="discret" style="font-size:.9rem">· <?= e((string) $c['matiere_nom']) ?></span>
            <?php endif; ?>
          </h2>

          <?php if ($fiche === '' && $contenu === ''): ?>
            <p class="discret"><?= e(t('focus.cours_vide')) ?></p>
          <?php else: ?>
            <?php if ($fiche !== ''): ?>
              <?php if ($seul): ?>
                <div class="texte-riche-affiche"><?= TexteRiche::versHtml($fiche) ?></div>
              <?php else: ?>
                <details<?= $rang === 0 ? ' open' : '' ?>>
                  <summary><strong><?= e(t('focus.sa_fiche')) ?></strong></summary>
                  <div class="texte-riche-affiche" style="margin-top:.6rem"><?= TexteRiche::versHtml($fiche) ?></div>
                </details>
              <?php endif; ?>
            <?php endif; ?>
            <?php if ($contenu !== ''): ?>
              <details<?= $fiche === '' && ($seul || $rang === 0) ? ' open' : '' ?> style="margin-top:.6rem">
                <summary><strong><?= e(t('focus.cours_entier')) ?></strong></summary>
                <div class="texte-riche-affiche" style="margin-top:.6rem"><?= TexteRiche::versHtml($contenu) ?></div>
              </details>
            <?php endif; ?>
          <?php endif; ?>

          <p class="champ__aide" style="margin-top:.6rem">
            <a href="<?= url('revision/' . (int) $c['id']) ?>"><?= e(t('focus.ouvrir_fiche')) ?></a>
            <?= e(t('focus.ouvrir_fiche_suite')) ?>
          </p>
        </section>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php // Terminer demande le ressenti : trois boutons, et c'est tout. ?>
  <dialog class="focus__bilan" data-focus-bilan>
    <form method="post" action="<?= url('focus/' . (int) $session['id'] . '/terminer') ?>" data-focus-formulaire>
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <input type="hidden" name="secondes" value="0" data-focus-secondes>
      <input type="hidden" name="pauses" value="0" data-focus-pauses>
      <h2 style="margin-top:0"><?= e(t('focus.session_terminee')) ?></h2>
      <p data-focus-bilan-texte class="discret"><?= e(t('js.focus.bilan_sans_pause', ['min' => 0])) ?></p>
      <p><?= e(t('focus.comment_passe')) ?></p>
      <div class="focus__ressentis">
        <?php foreach (Focus::RESSENTIS as $cle => $r): ?>
          <button class="bouton bouton--secondaire" type="submit" name="ressenti" value="<?= e($cle) ?>">
            <?= $r['icone'] ?> <?= e(Focus::nomRessenti((string) $cle)) ?>
          </button>
        <?php endforeach; ?>
      </div>
      <p class="actions" style="margin-bottom:0">
        <button class="bouton bouton--discret bouton--petit" type="submit" name="ressenti" value=""><?= e(t('focus.sans_reponse')) ?></button>
        <button class="bouton bouton--discret bouton--petit" type="button" data-focus-continuer><?= e(t('focus.continuer')) ?></button>
      </p>
    </form>
  </dialog>
</div>
