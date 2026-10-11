<?php
/**
 * Le document commun : un texte que tout le groupe écrit, avec l'historique
 * de ses versions.
 *
 * @var array $projet
 * @var list<array> $versions
 * @var ?string $brouillon  ce qu'on avait tapé quand quelqu'un a écrit en même temps
 * @var ?array $auteur      qui l'a modifié en dernier
 * @var string $onglet
 * @var bool $enEdition  l'éditeur est ouvert (sinon, la lecture du document avec « Modifier »)
 */
$dansUneFenetre = $dansUneFenetre ?? false;
// Dans la fenêtre, on y reste : les formulaires s'y enregistrent, les liens s'y ouvrent.
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$lien = $dansUneFenetre ? ' data-fenetre' : '';
$csrf = Session::jetonCsrf();
?>
<?= Vue::rendre('travaux/_onglets', ['projet' => $projet, 'onglet' => $onglet, 'dansUneFenetre' => $dansUneFenetre]) ?>

<div class="colonnes">
  <div class="pile">
    <?php
    /*
     * D'abord la lecture : le document mis en forme, avec « Modifier ». C'est « Modifier » qui ouvre l'éditeur (« ?modifier=1 »), et un
     * enregistrement ramène ici. Sans rien d'écrit encore, l'éditeur s'ouvre tout de suite.
     */
    $modifie = $projet['document_le'] !== null
        ? t('tr.do.modifie_le', [
            'date' => date_fr((string) $projet['document_le']),
            'qui' => (string) ($auteur['pseudo'] ?? $auteur['nom'] ?? t('tr.ancien_membre')),
        ]) : '';
    $lecture = url('travaux/' . (int) $projet['id'] . '/document');
    ?>
    <?php if (!($enEdition ?? true)): ?>
      <section class="carte travaux-document">
        <div class="travaux-document__entete">
          <h2 style="margin:0"><?= e(t('tr.do.titre')) ?></h2>
          <a class="bouton bouton--secondaire" href="<?= url('travaux/' . (int) $projet['id'] . '/document', ['modifier' => 1]) ?>"<?= $lien ?>><?= e(t('commun.modifier')) ?></a>
        </div>
        <?php if ($modifie !== ''): ?><p class="discret" style="margin:.3rem 0 .8rem"><?= e($modifie) ?></p><?php endif; ?>
        <div class="texte-riche-affiche"><?= TexteRiche::versHtml((string) $projet['document']) ?></div>
      </section>
    <?php else: ?>
    <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/document') ?>" class="carte">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <?php // La version lue : si quelqu'un enregistre entre-temps, on ne l'écrase pas. ?>
      <input type="hidden" name="version" value="<?= (int) $projet['document_version'] ?>">
      <h2 style="margin-top:0"><?= e(t('tr.do.titre')) ?></h2>
      <?php if ($modifie !== ''): ?>
        <p class="discret" style="margin-top:-.4rem"><?= e($modifie) ?></p>
      <?php endif; ?>
      <label class="sr-only" for="document"><?= e(t('tr.do.label')) ?></label>
      <textarea id="document" name="document" style="min-height:420px" data-texte-riche="complet"
                data-tailles="<?= e(implode(',', TexteRiche::TAILLES)) ?>"
                placeholder="<?= e(t('tr.do.exemple')) ?>"><?= e(TexteRiche::pourEditeur($projet['document'])) ?></textarea>
      <div class="actions" style="margin-top:.8rem">
        <button class="bouton" type="submit"><?= e(t('tr.do.enregistrer')) ?></button>
        <?php // Rien d'écrit encore : pas de lecture où revenir. ?>
        <?php if (trim((string) $projet['document']) !== ''): ?>
          <a class="bouton bouton--discret" href="<?= $lecture ?>"<?= $lien ?>><?= e(t('commun.annuler')) ?></a>
        <?php endif; ?>
      </div>
    </form>
    <?php endif; ?>

    <?php if (is_string($brouillon) && trim($brouillon) !== ''): ?>
      <section class="carte travaux-brouillon">
        <h2><?= e(t('tr.do.non_enregistree')) ?></h2>
        <p class="discret"><?= e(t('tr.do.quelquun_avant')) ?></p>
        <div class="texte-riche-affiche"><?= TexteRiche::versHtml(TexteRiche::depuisFormulaire($brouillon)) ?></div>
      </section>
    <?php endif; ?>
  </div>

  <div class="pile">
    <section class="carte">
      <h2><?= e(t('tr.do.versions')) ?></h2>
      <?php if ($versions === []): ?>
        <p class="discret"><?= e(t('tr.do.versions_aide')) ?></p>
      <?php else: ?>
        <?php // Une tuile par version, cliquable en entier : la date d'abord, et qui l'a écrite — son avatar et son nom, bien lisibles. ?>
        <ul class="travaux-versions">
          <?php foreach ($versions as $v): ?>
            <?php $qui = (string) ($v['auteur'] ?? t('tr.ancien_membre')); ?>
            <li class="version-tuile">
              <?= $v['user_id'] !== null ? Amis::avatar((int) $v['user_id'], $qui, 'avatar--mini') : '<span class="avatar avatar--mini" aria-hidden="true">?</span>' ?>
              <span class="version-tuile__texte">
                <a class="version-tuile__date" href="<?= url('travaux/versions/' . (int) $v['id']) ?>"<?= $lien ?>><?= e(date_fr((string) $v['created_at'])) ?></a>
                <span class="version-tuile__auteur"><?= e(t('tr.do.par')) ?> <strong class="version-tuile__nom"><?= e($qui) ?></strong></span>
              </span>
              <span class="version-tuile__fleche" aria-hidden="true">›</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
</div>
