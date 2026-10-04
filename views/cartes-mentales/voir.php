<?php
/**
 * Une carte mentale, dans son éditeur.
 *
 * @var array $carte  la ligne de cartes_mentales
 * @var array $arbre  la carte, nettoyée : {t, c, p}
 * @var array $cours  le cours auquel elle appartient (id, titre)
 * @var bool $dansUneFenetre  rendue seule, pour être posée dans une fenêtre
 */
$id = (int) $carte['id'];
$dansUneFenetre = $dansUneFenetre ?? false;
// Dans une fenêtre, l'éditeur prend toute la place que l'écran laisse (« data-document »), et la croix ramène à la page
// d'où l'on vient : pas de lien de retour. Effacer la carte s'y fait sans la quitter.
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large data-document' : '' ?>>
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p class="discret" style="margin-bottom:.35rem">
        <a href="<?= url('revision/' . (int) $cours['id']) ?>"><?= e(t('cm.retour', ['cours' => (string) $cours['titre']])) ?></a>
      </p>
    <?php endif; ?>
    <h1 data-cm-titre-page><?= e((string) $carte['titre']) ?></h1>
    <p class="discret">
      <span data-cm-compte><?= e(tn('cm.idees', CarteMentale::compter($arbre))) ?></span>
      <?php if ((int) $carte['ia'] === 1): ?> · <?= e(t('cm.ia_badge')) ?><?php endif; ?>
    </p>
  </div>
</div>

<section class="carte cm" data-carte-mentale
         data-arbre="<?= e((string) json_encode($arbre, JSON_UNESCAPED_UNICODE)) ?>"
         data-url="<?= url('cartes-mentales/' . $id) ?>"
         data-jeton="<?= e(Session::jetonCsrf()) ?>"
         data-max-noeuds="<?= CarteMentale::NOEUDS_MAX ?>"
         data-max-niveaux="<?= CarteMentale::NIVEAUX_MAX ?>"
         data-max-texte="<?= CarteMentale::TEXTE_MAX ?>"
         data-nom-fichier="<?= e(preg_replace('/[^\p{L}\p{N}._-]+/u', '_', (string) $carte['titre']) ?: 'carte-mentale') ?>">

  <div class="champ cm__titre">
    <label for="cm-titre"><?= e(t('cm.titre_label')) ?></label>
    <input id="cm-titre" type="text" maxlength="<?= CarteMentale::TITRE_MAX ?>" value="<?= e((string) $carte['titre']) ?>" data-cm-titre>
  </div>

  <?php // La barre d'outils : tout ce qu'on peut faire à l'idée choisie, au pointeur comme au clavier. ?>
  <div class="cm__barre" role="toolbar" aria-label="<?= e(t('js.cm.arbre_label')) ?>" hidden data-cm-barre>
    <button type="button" class="bouton bouton--petit" data-cm-action="enfant" title="<?= e(t('cm.b.enfant')) ?>">＋ <?= e(t('cm.b.enfant')) ?></button>
    <button type="button" class="bouton bouton--secondaire bouton--petit" data-cm-action="frere" title="<?= e(t('cm.b.frere')) ?>">↳ <?= e(t('cm.b.frere')) ?></button>
    <button type="button" class="bouton bouton--secondaire bouton--petit" data-cm-action="renommer" title="<?= e(t('cm.b.renommer')) ?> (F2)">✎ <?= e(t('cm.b.renommer')) ?></button>
    <button type="button" class="bouton bouton--secondaire bouton--petit" data-cm-action="replier" title="<?= e(t('cm.b.replier')) ?>">⇔ <?= e(t('cm.b.replier')) ?></button>
    <button type="button" class="bouton bouton--discret bouton--petit" data-cm-action="monter" title="<?= e(t('cm.b.monter')) ?>">↑</button>
    <button type="button" class="bouton bouton--discret bouton--petit" data-cm-action="descendre" title="<?= e(t('cm.b.descendre')) ?>">↓</button>
    <button type="button" class="bouton bouton--discret bouton--petit" data-cm-action="supprimer" title="<?= e(t('cm.b.supprimer')) ?>">✕ <?= e(t('cm.b.supprimer')) ?></button>
    <span class="cm__sep" aria-hidden="true"></span>
    <button type="button" class="bouton bouton--discret bouton--petit" data-cm-action="annuler" title="<?= e(t('cm.b.annuler')) ?> (Ctrl+Z)">↶ <?= e(t('cm.b.annuler')) ?></button>
    <button type="button" class="bouton bouton--discret bouton--petit" data-cm-action="zoom-moins" title="<?= e(t('cm.b.zoom_moins')) ?>">−</button>
    <button type="button" class="bouton bouton--discret bouton--petit" data-cm-action="zoom-plus" title="<?= e(t('cm.b.zoom_plus')) ?>">＋</button>
    <button type="button" class="bouton bouton--discret bouton--petit" data-cm-action="ajuster" title="<?= e(t('cm.b.ajuster')) ?>">⤢</button>
    <button type="button" class="bouton bouton--discret bouton--petit" data-cm-action="image" title="<?= e(t('cm.b.image')) ?>">⬇ <?= e(t('cm.b.image')) ?></button>
  </div>

  <p class="champ__aide" hidden data-cm-aide><?= e(t('cm.aide')) ?></p>

  <?php // La toile : le script y dessine la carte. Sans lui, le plan en liste ci-dessous en dit autant. ?>
  <div class="cm__toile" data-cm-toile hidden></div>

  <p class="cm__etat" role="status" aria-live="polite" data-cm-etat></p>

  <div class="cm__plan" data-cm-plan>
    <h2><?= e(t('cm.plan')) ?></h2>
    <ul><?= CarteMentale::plan($arbre) ?></ul>
  </div>
</section>

<form method="post" action="<?= url('cartes-mentales/' . $id . '/supprimer') ?>" data-confirmation="<?= e(t('cm.supprimer_sur')) ?>"<?= $envoi ?>>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <button class="bouton bouton--discret" type="submit"><?= e(t('cm.supprimer')) ?></button>
</form>
