<?php
/**
 * Le document commun : un texte que tout le groupe écrit, avec l'historique
 * de ses versions.
 *
 * @var array $projet
 * @var list<array> $versions
 * @var ?string $brouillon  ce qu'on avait tapé quand quelqu'un a écrit en même temps
 * @var ?array $auteur      qui l'a modifié en dernier
 * @var ?array $entete  l'en-tête du PDF (titre, sous_titre, membres, date, autre) ; null : la migration n'est pas passée, pas de réglage
 * @var ?list<array> $livrables  les livrables du projet (null : la migration n'est pas passée, le document ne se met pas dans un livrable)
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
          <div class="actions" style="margin:0">
            <a class="bouton bouton--secondaire" href="<?= url('travaux/' . (int) $projet['id'] . '/document/pdf') ?>" title="<?= e(t('tr.do.pdf_aide')) ?>">⬇ <?= e(t('tr.do.pdf')) ?></a>
            <?php if (($entete ?? null) !== null): ?>
              <label for="document-entete-ouvrir" class="bouton bouton--secondaire acces-bouton" role="button" title="<?= e(t('tr.do.entete_aide')) ?>">🏷️ <?= e(t('tr.do.entete')) ?></label>
            <?php endif; ?>
            <?php if (($livrables ?? null) !== null): ?>
              <label for="document-livrable-ouvrir" class="bouton bouton--secondaire acces-bouton" role="button" title="<?= e(t('tr.do.vers_livrable_aide')) ?>">📤 <?= e(t('tr.do.vers_livrable')) ?></label>
            <?php endif; ?>
            <a class="bouton bouton--secondaire" href="<?= url('travaux/' . (int) $projet['id'] . '/document', ['modifier' => 1]) ?>"<?= $lien ?>><?= e(t('commun.modifier')) ?></a>
          </div>
        </div>
        <?php // Les réglages du PDF : « En-tête du PDF » (ce qu'on met sous le titre) et « Mettre dans les livrables ». Une case cachée par panneau porte son état (le bouton en est l'étiquette), chacune dans son bloc. ?>
        <?php if (($entete ?? null) !== null): ?>
          <div>
            <input type="checkbox" id="document-entete-ouvrir" class="acces-bascule sr-only">
            <div class="acces-panneau" style="margin-top:.8rem">
              <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/document/entete') ?>" class="acces-panneau__droit">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <p class="champ__aide" style="margin:0 0 .7rem"><?= e(t('tr.do.entete_aide')) ?></p>
                <label class="acces-panneau__etiquette" for="entete-titre"><?= e(t('tr.do.entete_titre')) ?></label>
                <input type="text" id="entete-titre" name="titre" maxlength="<?= Travaux::ENTETE_TITRE_MAX ?>" value="<?= e($entete['titre']) ?>" placeholder="<?= e((string) $projet['nom']) ?>" autocomplete="off">
                <label class="acces-panneau__etiquette" for="entete-sous-titre" style="margin-top:.7rem"><?= e(t('tr.do.entete_sous_titre')) ?></label>
                <input type="text" id="entete-sous-titre" name="sous_titre" maxlength="<?= Travaux::ENTETE_TITRE_MAX ?>" value="<?= e($entete['sous_titre']) ?>" autocomplete="off">
                <span class="acces-panneau__etiquette" style="margin-top:.7rem"><?= e(t('tr.do.entete_en_plus')) ?></span>
                <label class="case-simple"><input type="checkbox" name="membres" value="1"<?= $entete['membres'] ? ' checked' : '' ?>> <?= e(t('tr.do.entete_membres')) ?></label>
                <label class="case-simple"><input type="checkbox" name="date" value="1"<?= $entete['date'] ? ' checked' : '' ?>> <?= e(t('tr.do.entete_date')) ?></label>
                <label class="acces-panneau__etiquette" for="entete-autre" style="margin-top:.7rem"><?= e(t('tr.do.entete_autre')) ?></label>
                <textarea id="entete-autre" name="autre" rows="3" placeholder="<?= e(t('tr.do.entete_autre_exemple')) ?>"><?= e($entete['autre']) ?></textarea>
                <div class="acces-panneau__ligne" style="margin-top:.8rem">
                  <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
                  <label for="document-entete-ouvrir" class="bouton bouton--discret" role="button"><?= e(t('commun.annuler')) ?></label>
                </div>
              </form>
            </div>
          </div>
        <?php endif; ?>
        <?php if (($livrables ?? null) !== null): ?>
          <div>
          <input type="checkbox" id="document-livrable-ouvrir" class="acces-bascule sr-only">
          <div class="acces-panneau" style="margin-top:.8rem">
            <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/document/livrable') ?>" class="acces-panneau__droit">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <label class="acces-panneau__etiquette" for="document-livrable"><?= e(t('tr.do.livrable_label')) ?></label>
              <div class="acces-panneau__ligne">
                <select id="document-livrable" name="livrable">
                  <option value="nouveau" selected><?= e(t('tr.do.livrable_nouveau', ['titre' => t('tr.do.livrable_titre')])) ?></option>
                  <?php foreach ($livrables as $l): ?>
                    <option value="<?= (int) $l['id'] ?>"><?= e((string) $l['titre']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="bouton" type="submit"><?= e(t('tr.do.mettre')) ?></button>
                <label for="document-livrable-ouvrir" class="bouton bouton--discret" role="button"><?= e(t('commun.annuler')) ?></label>
              </div>
              <p class="champ__aide" style="margin:.5rem 0 0"><?= e(t('tr.do.vers_livrable_aide')) ?></p>
            </form>
          </div>
          </div>
        <?php endif; ?>
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
