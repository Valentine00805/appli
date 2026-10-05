<?php
/**
 * Un diaporama commenté : ses diapositives, lues une à une, avec une voix.
 *
 * @var array $diaporama  la ligne de diaporamas
 * @var array $diapos     ses diapositives : [{t, p: [points], c: commentaire, a?: nom du fichier voix}]
 * @var ?string $cleFin   la fin de la clé Gemini de l'utilisateur, ou null
 * @var list<array{id: int, cours: string, lie: bool}> $coursFiche  les cours qu'il a lus, et s'il est déjà dans leur fiche
 * @var int $debut       la diapositive par laquelle commencer (0 : la première)
 * @var bool $dansUneFenetre  rendu seul, pour être posé dans une fenêtre
 */
$id = (int) $diaporama['id'];
$dansUneFenetre = $dansUneFenetre ?? false;
// Dans une fenêtre, le lecteur prend toute la place que l'écran laisse, et la croix ramène à la page d'où l'on vient.
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';

// Ce que le script reçoit : pas le nom des fichiers voix (ils se servent par l'adresse), seulement s'il y en a un.
$pourLeScript = array_map(static fn (array $d): array => [
    't' => $d['t'], 'p' => $d['p'], 'c' => $d['c'], 'a' => isset($d['a']),
], $diapos);
$voixChoisie = in_array((string) ($diaporama['voix'] ?? ''), Gemini::VOIX, true) ? (string) $diaporama['voix'] : Gemini::VOIX[0];
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large data-document' : '' ?>>
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p class="discret" style="margin-bottom:.35rem"><a href="<?= url('resumes') ?>"><?= e(t('ria.retour')) ?></a></p>
    <?php endif; ?>
    <h1><?= e((string) $diaporama['titre']) ?></h1>
    <p class="discret">
      <?= e(tn('dia.nb_diapos', count($diapos))) ?> · <?= e(date_fr((string) $diaporama['created_at'])) ?>
      <?php if ($diaporama['modele'] !== null): ?> · <?= e((string) $diaporama['modele']) ?><?php endif; ?>
    </p>
  </div>
</div>

<section class="carte diapo" data-diaporama
         data-diapos="<?= e((string) json_encode($pourLeScript, JSON_UNESCAPED_UNICODE)) ?>"
         data-langue="<?= e((string) $diaporama['langue']) ?>"
         data-debut="<?= (int) ($debut ?? 0) ?>"
         data-titre="<?= e((string) $diaporama['titre']) ?>"
         data-nom-fichier="<?= e(preg_replace('/[^\p{L}\p{N}._-]+/u', '_', (string) $diaporama['titre']) ?: 'diaporama') ?>"
         data-url-audio="<?= url('diaporamas/' . $id . '/audio') ?>"
         data-url-voix="<?= url('diaporamas/' . $id . '/voix') ?>"
         data-jeton="<?= e(Session::jetonCsrf()) ?>">

  <?php // La diapositive : le script la dessine. Sans lui, le plan en liste, plus bas, dit la même chose. ?>
  <div class="diapo__scene" data-dia-scene hidden>
    <p class="diapo__compteur" data-dia-compteur aria-live="polite"></p>
    <h2 class="diapo__titre" data-dia-titre></h2>
    <ul class="diapo__points" data-dia-points></ul>
  </div>

  <div class="diapo__barre" role="toolbar" hidden data-dia-barre>
    <button type="button" class="bouton bouton--secondaire bouton--petit" data-dia-action="precedent" title="<?= e(t('dia.precedent')) ?> (←)">◀</button>
    <button type="button" class="bouton bouton--petit" data-dia-action="lire" aria-pressed="false" data-dia-lire
            data-texte-lire="▶ <?= e(t('dia.lire')) ?>" data-texte-pause="⏸ <?= e(t('dia.pause')) ?>">▶ <?= e(t('dia.lire')) ?></button>
    <button type="button" class="bouton bouton--secondaire bouton--petit" data-dia-action="suivant" title="<?= e(t('dia.suivant')) ?> (→)">▶</button>

    <label class="diapo__choix"><?= e(t('dia.voix_source')) ?>
      <select data-dia-source>
        <option value="navigateur"><?= e(t('dia.voix_navigateur')) ?></option>
        <option value="gemini"><?= e(t('dia.voix_gemini')) ?></option>
      </select>
    </label>
    <label class="diapo__choix"><?= e(t('dia.vitesse')) ?>
      <select data-dia-vitesse>
        <?php foreach (['0.75', '1', '1.25', '1.5'] as $v): ?>
          <option value="<?= $v ?>" <?= $v === '1' ? 'selected' : '' ?>>×<?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="button" class="bouton bouton--secondaire bouton--petit" data-dia-action="transcription" aria-pressed="false">📄 <?= e(t('dia.transcription')) ?></button>
  </div>

  <?php
  /*
   * La transcription : tout ce qui est dit, diapositive par diapositive. Pendant la lecture, la diapositive en cours est
   * marquée et la phrase dite surlignée ; un clic sur une phrase y emmène. Le texte peut se copier ou se télécharger.
   * Le script la remplit (et la montre) ; sans lui, le texte complet est dans le plan, plus bas.
   */
  ?>
  <div class="diapo__trans" data-dia-trans hidden>
    <div class="actions">
      <button type="button" class="bouton bouton--secondaire bouton--petit" data-dia-action="copier">⧉ <?= e(t('dia.trans_copier')) ?></button>
      <button type="button" class="bouton bouton--secondaire bouton--petit" data-dia-action="telecharger">⬇ <?= e(t('dia.trans_telecharger')) ?></button>
      <span class="cm__etat" role="status" aria-live="polite" data-dia-trans-etat></span>
    </div>
    <p class="champ__aide"><?= e(t('dia.trans_aide')) ?></p>
    <div class="diapo__trans-texte" data-dia-trans-texte tabindex="0"></div>
  </div>

  <?php // La voix Gemini : une par diapositive, fabriquée à la demande, et gardée. Sans clé : un renvoi vers « Mon compte ». ?>
  <div class="diapo__gemini" data-dia-gemini hidden>
    <?php if ($cleFin === null): ?>
      <p class="champ__aide"><?= e(t('ria.pas_de_cle')) ?> <a href="<?= url('compte') ?>#gemini"><?= e(t('ria.ajouter_cle')) ?></a></p>
    <?php else: ?>
      <div class="actions">
        <label class="diapo__choix"><?= e(t('dia.voix_gemini_choix')) ?>
          <select data-dia-voix-gemini>
            <?php foreach (Gemini::VOIX as $voix): ?>
              <option value="<?= e($voix) ?>" <?= $voix === $voixChoisie ? 'selected' : '' ?>><?= e($voix) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button type="button" class="bouton bouton--secondaire bouton--petit" data-dia-action="generer" data-dia-generer><?= e(t('dia.generer_voix')) ?></button>
      </div>
      <p class="champ__aide"><?= e(t('dia.generer_aide')) ?></p>
    <?php endif; ?>
  </div>

  <p class="cm__etat" role="status" aria-live="polite" data-dia-etat></p>

  <div class="diapo__plan" data-dia-plan>
    <h2><?= e(t('dia.plan')) ?></h2>
    <ol>
      <?php foreach ($diapos as $d): ?>
        <li>
          <strong><?= e($d['t']) ?></strong>
          <?php if ($d['p'] !== []): ?>
            <ul><?php foreach ($d['p'] as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
          <p class="discret"><?= e($d['c']) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
<?php
/*
 * Le ranger dans la fiche de révision d'un des cours lus (il s'y rouvre, avec sa voix), ou en joindre le PDF — titres,
 * points et commentaires — aux fichiers de la fiche. Le cours vient du diaporama lui-même : rien d'autre ne se choisit.
 */
$lies = array_values(array_filter($coursFiche, static fn (array $c): bool => $c['lie']));
$aLier = array_values(array_filter($coursFiche, static fn (array $c): bool => !$c['lie']));
?>
<section class="carte" data-dia-fiche>
  <h2><?= e(t('dia.fiche_titre')) ?></h2>
  <p class="champ__aide"><?= e(t('dia.fiche_aide')) ?></p>

  <?php if ($lies !== []): ?>
    <ul class="ria-liste">
      <?php foreach ($lies as $c): ?>
        <li>
          <span><?= e(t('dia.fiche_dans')) ?> <a href="<?= url('revision/' . $c['id']) ?>"><strong><?= e($c['cours']) ?></strong></a></span>
          <form method="post" action="<?= url('diaporamas/' . $id . '/fiche/retirer') ?>" class="en-ligne"<?= $envoi ?>>
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <input type="hidden" name="cours" value="<?= (int) $c['id'] ?>">
            <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('dia.fiche_retirer')) ?></button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($aLier !== []): ?>
    <form method="post" action="<?= url('diaporamas/' . $id . '/fiche') ?>" class="fabrique__form"<?= $envoi ?>>
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <?php if (count($aLier) === 1): ?>
        <input type="hidden" name="cours" value="<?= (int) $aLier[0]['id'] ?>">
      <?php else: ?>
        <div class="champ">
          <label for="dia_cours_fiche"><?= e(t('ria.fiche_vers')) ?></label>
          <select id="dia_cours_fiche" name="cours">
            <?php foreach ($aLier as $c): ?>
              <option value="<?= (int) $c['id'] ?>"><?= e($c['cours']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <button class="bouton bouton--secondaire" type="submit"><?= e(t('dia.fiche_ajouter')) ?><?php if (count($aLier) === 1): ?> — <?= e($aLier[0]['cours']) ?><?php endif; ?></button>
    </form>
  <?php endif; ?>

  <p class="actions" style="margin-top:.75rem">
    <a class="bouton bouton--secondaire" href="<?= url('diaporamas/' . $id . '/pdf') ?>"><?= e(t('ria.pdf_telecharger')) ?></a>
  </p>
  <?php if ($coursFiche !== []): ?>
    <form method="post" action="<?= url('diaporamas/' . $id . '/pdf-fiche') ?>" class="fabrique__form"<?= $envoi ?>>
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <?php if (count($coursFiche) === 1): ?>
        <input type="hidden" name="cours" value="<?= (int) $coursFiche[0]['id'] ?>">
      <?php else: ?>
        <div class="champ">
          <label for="dia_cours_pdf"><?= e(t('ria.fiche_vers')) ?></label>
          <select id="dia_cours_pdf" name="cours">
            <?php foreach ($coursFiche as $c): ?>
              <option value="<?= (int) $c['id'] ?>"><?= e($c['cours']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <button class="bouton bouton--secondaire" type="submit"><?= e(t('ria.pdf_joindre')) ?><?php if (count($coursFiche) === 1): ?> — <?= e($coursFiche[0]['cours']) ?><?php endif; ?></button>
    </form>
  <?php endif; ?>
</section>

<p class="champ__aide"><?= e(t('ria.avertissement_ia')) ?></p>

<form method="post" action="<?= url('diaporamas/' . $id . '/supprimer') ?>" data-confirmation="<?= e(t('dia.supprimer_sur')) ?>"<?= $envoi ?>>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <button class="bouton bouton--discret" type="submit"><?= e(t('dia.supprimer')) ?></button>
</form>
