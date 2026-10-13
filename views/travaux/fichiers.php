<?php
/**
 * Les fichiers du groupe : tout le monde dépose, tout le monde télécharge — et les range dans des dossiers.
 *
 * @var array $projet
 * @var list<array> $fichiers  ceux de l'endroit où l'on est (la racine, ou un dossier)
 * @var ?array{courant: ?int, chemin: list<array>, sous: list<array>, tous: list<array{id: int, libelle: string}>} $dossiers
 *             null : la migration des dossiers n'est pas passée, les fichiers sont en vrac
 * @var string $onglet
 */
$dansUneFenetre = $dansUneFenetre ?? false;
// Dans la fenêtre, on y reste : les formulaires s'y enregistrent, les liens s'y ouvrent.
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$lien = $dansUneFenetre ? ' data-fenetre' : '';
$csrf = Session::jetonCsrf();
$admin = $projet['role'] === 'admin';
$idProjet = (int) $projet['id'];
$courant = $dossiers['courant'] ?? null;
$chemin = $dossiers['chemin'] ?? [];
$ici = $chemin === [] ? null : $chemin[count($chemin) - 1];
$sous = $dossiers['sous'] ?? [];
$racine = url('travaux/' . $idProjet . '/fichiers');
$versDossier = static fn (int $d): string => url('travaux/' . $idProjet . '/fichiers', ['dossier' => $d]);
?>
<?php
/*
 * Le menu « ⋮ » d'un fichier : le déplacer (dans une fenêtre, s'il y a des dossiers), le télécharger, le supprimer (celui qui l'a déposé, ou un
 * administrateur). « $depuis » : le dossier où l'on est, pour y revenir après.
 */
$menuFichier = static function (array $f, int $depuis) use ($dossiers, $courant, $admin, $csrf, $envoi): string {
    $fid = (int) $f['id'];
    $nom = (string) $f['nom_origine'];
    $peutDeplacer = $dossiers !== null && ($dossiers['tous'] !== [] || $courant !== null);
    ob_start(); ?>
    <details class="ami-menu">
      <summary class="ami-menu__bouton" aria-label="<?= e(t('tr.fd.options_fichier', ['nom' => $nom])) ?>" title="<?= e(t('tr.fd.options_fichier', ['nom' => $nom])) ?>">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true" focusable="false">
          <circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/>
        </svg>
      </summary>
      <div class="ami-menu__liste" role="menu">
        <?php if ($peutDeplacer): ?>
          <a class="ami-menu__item" role="menuitem" data-fenetre href="<?= url('travaux/fichiers/' . $fid . '/deplacer', ['depuis' => $depuis]) ?>">
            <span aria-hidden="true">📁</span> <?= e(t('tr.fd.deplacer')) ?>
          </a>
        <?php endif; ?>
        <a class="ami-menu__item" role="menuitem" href="<?= url('travaux/fichiers/' . $fid, ['telecharger' => 1]) ?>">
          <span aria-hidden="true">⬇</span> <?= e(t('tr.fi.telecharger')) ?>
        </a>
        <?php if ($admin || (int) ($f['user_id'] ?? 0) === Auth::id()): ?>
          <form method="post"<?= $envoi ?> action="<?= url('travaux/fichiers/' . $fid . '/supprimer') ?>" role="none"
                data-confirmation="<?= e(t('tr.fi.supprimer_confirmation', ['nom' => $nom])) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button type="submit" class="ami-menu__item" role="menuitem"><span aria-hidden="true">🗑️</span> <?= e(t('tr.fi.supprimer')) ?></button>
          </form>
        <?php endif; ?>
      </div>
    </details>
    <?php return (string) ob_get_clean();
};
?>
<?= Vue::rendre('travaux/_onglets', ['projet' => $projet, 'onglet' => $onglet, 'dansUneFenetre' => $dansUneFenetre]) ?>

<div class="colonnes">
  <div class="pile">
    <?php if ($dossiers !== null): ?>
      <?php
      /*
       * Le menu « ⋮ » d'un dossier : le renommer (dans une fenêtre) ou le supprimer (son contenu remonte ; celui qui l'a créé ou un administrateur).
       * « $depuis » : le dossier où l'on est, pour y revenir après.
       */
      $menuDossier = static function (array $d, int $depuis) use ($idProjet, $csrf, $envoi, $admin): string {
          $peutSupprimer = $admin || (int) ($d['cree_par'] ?? 0) === Auth::id();
          ob_start(); ?>
          <details class="ami-menu">
            <summary class="ami-menu__bouton" aria-label="<?= e(t('tr.fd.options', ['nom' => $d['nom']])) ?>" title="<?= e(t('tr.fd.options', ['nom' => $d['nom']])) ?>">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true" focusable="false">
                <circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/>
              </svg>
            </summary>
            <div class="ami-menu__liste" role="menu">
              <a class="ami-menu__item" role="menuitem" data-fenetre href="<?= url('travaux/dossiers/' . (int) $d['id'] . '/renommer', ['depuis' => $depuis]) ?>">
                <span aria-hidden="true">✎</span> <?= e(t('tr.fd.renommer')) ?>
              </a>
              <?php if ($peutSupprimer): ?>
                <a class="ami-menu__item" role="menuitem" data-fenetre href="<?= url('travaux/dossiers/' . (int) $d['id'] . '/supprimer', ['depuis' => $depuis]) ?>">
                  <span aria-hidden="true">🗑️</span> <?= e(t('tr.fd.supprimer')) ?>
                </a>
              <?php endif; ?>
            </div>
          </details>
          <?php return (string) ob_get_clean();
      };
      ?>
      <?php // Où l'on est : « Fichiers › Sources › Brouillons » — avec, après le dossier où l'on est, son menu « ⋮ » (renommer, supprimer) —, et de quoi créer un dossier ici. ?>
      <div class="fichiers-barre">
        <nav class="fichiers-chemin" aria-label="<?= e(t('tr.fd.chemin')) ?>">
          <?php if ($ici === null): ?>
            <strong>📎 <?= e(t('tr.fd.racine')) ?></strong>
          <?php else: ?>
            <a href="<?= $racine ?>"<?= $lien ?>>📎 <?= e(t('tr.fd.racine')) ?></a>
            <?php foreach ($chemin as $i => $etape): ?>
              <span aria-hidden="true">›</span>
              <?php if ($i === count($chemin) - 1): ?>
                <strong>📁 <?= e($etape['nom']) ?></strong>
                <?= $menuDossier($etape + ['parent_nom' => count($chemin) > 1 ? $chemin[count($chemin) - 2]['nom'] : t('tr.fd.racine')], (int) ($courant ?? 0)) ?>
              <?php else: ?>
                <a href="<?= $versDossier($etape['id']) ?>"<?= $lien ?>>📁 <?= e($etape['nom']) ?></a>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </nav>
        <a class="bouton bouton--petit" href="<?= url('travaux/' . $idProjet . '/dossiers/nouveau', $courant !== null ? ['parent' => $courant] : []) ?>" data-fenetre><?= e(t('tr.fd.nouveau')) ?></a>
      </div>

      <?php if ($sous !== []): ?>
        <ul class="liste-fichiers">
          <?php foreach ($sous as $d): ?>
            <li class="fichier fichier--dossier">
              <span class="fichier__icone" aria-hidden="true">📁</span>
              <span style="min-width:0">
                <a class="fichier__nom" href="<?= $versDossier($d['id']) ?>"<?= $lien ?>><?= e($d['nom']) ?></a><br>
                <span class="fichier__meta"><?= e(tn('tr.fd.fichiers', $d['nb_fichiers'])) ?><?= $d['nb_dossiers'] > 0 ? ' · ' . e(tn('tr.fd.dossiers', $d['nb_dossiers'])) : '' ?></span>
              </span>
              <?= $menuDossier($d + ['parent_nom' => $ici !== null ? $ici['nom'] : t('tr.fd.racine')], (int) ($courant ?? 0)) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($fichiers === [] && $sous === []): ?>
      <div class="vide">
        <span class="vide__icone"><?= $ici !== null ? '📁' : '📎' ?></span>
        <p><?= e(t($ici !== null ? 'tr.fd.vide_dossier' : 'tr.fi.aucun')) ?></p>
      </div>
    <?php elseif ($fichiers !== []): ?>
      <section class="carte">
        <h2><?= e(t('tr.fi.titre')) ?> <span class="discret">(<?= count($fichiers) ?>)</span></h2>
        <ul class="liste-fichiers">
          <?php foreach ($fichiers as $f): ?>
            <?php $fid = (int) $f['id']; ?>
            <li class="fichier">
              <span class="fichier__icone" aria-hidden="true"><?= Fichiers::icone((string) $f['mime'], (string) $f['nom_origine']) ?></span>
              <span style="min-width:0">
                <?= Vue::rendre('travaux/_nom_fichier', ['f' => $f, 'dansUneFenetre' => $dansUneFenetre]) ?><br>
                <span class="fichier__meta">
                  <?= e(taille_lisible((int) $f['taille'])) ?>
                  <?= e(t('tr.fi.depose_par', [
                      'qui' => (string) ($f['depose_par'] ?? t('tr.ancien_membre')),
                      'date' => date_fr((string) $f['created_at'], false),
                  ])) ?>
                </span>
                <?= Vue::rendre('travaux/_evenements_lies', ['evenements' => LiensEvenements::evenementsDe($idProjet, 'fichier', $fid), 'ouvre' => $dansUneFenetre ? ' data-fenetre' : '']) ?>
              </span>
              <?= $menuFichier($f, (int) ($courant ?? 0)) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  </div>

  <div class="pile">
    <section class="carte">
      <h2><?= e(t('tr.fi.deposer')) ?></h2>
      <form method="post"<?= $envoi ?> action="<?= url('travaux/' . $idProjet . '/fichiers') ?>" enctype="multipart/form-data" class="depot" data-depot>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <?php // Les fichiers déposés vont dans le dossier où l'on est. ?>
        <?php if ($courant !== null): ?><input type="hidden" name="dossier" value="<?= (int) $courant ?>"><?php endif; ?>
        <label class="depot__zone" for="depot-travaux">
          <span class="depot__icone" aria-hidden="true">📎</span>
          <span>
            <strong><?= e(t('tr.fi.zone')) ?></strong><br>
            <span class="discret"><?= e(t('tr.fi.zone_aide', ['taille' => taille_lisible(Fichiers::tailleMax())])) ?></span>
            <?php if ($ici !== null): ?><br><span class="discret"><?= e(t('tr.fd.depose_dans', ['dossier' => $ici['nom']])) ?></span><?php endif; ?>
          </span>
        </label>
        <input type="file" id="depot-travaux" name="fichiers[]" multiple class="depot__champ" data-depot-champ>
        <button class="bouton bouton--petit bouton--bloc" type="submit" data-depot-envoi><?= e(t('tr.fi.deposer')) ?></button>
      </form>
    </section>
  </div>
</div>
