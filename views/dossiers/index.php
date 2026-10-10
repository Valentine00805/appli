<?php
/** @var array $dossiers, $descendants, $palette, $icones, $matieres @var int $sansDossier */
$csrf = Session::jetonCsrf();
$dernier = count($dossiers) - 1;
// La matière d'un dossier, en pastille de sa couleur (rien s'il n'en a pas).
$matieresParId = [];
foreach ($matieres as $m) {
    $matieresParId[(int) $m['id']] = $m;
}
$pastilleMatiere = static function (array $d) use ($matieresParId): string {
    $m = $matieresParId[(int) ($d['matiere_id'] ?? 0)] ?? null;
    if ($m === null) {
        return '';
    }
    $couleur = (string) ($m['couleur'] ?: '#94a3b8');

    return ' <span class="pastille" style="background:' . e($couleur) . ';color:' . e(couleur_texte($couleur)) . '">' . e((string) $m['nom']) . '</span>';
};
?>

<?= Vue::rendre('organisation/_onglets', ['onglet' => 'dossiers']) ?>

<div class="entete-page">
  <div>
    <h1><?= e(t('dos.titre')) ?></h1>
    <p><?= e(t('dos.sous_titre')) ?></p>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <?php if ($dossiers === []): ?>
      <div class="vide">
        <span class="vide__icone">📁</span>
        <p><?= e(t('dos.aucun')) ?></p>
      </div>
    <?php else: ?>
      <?php
      // On regroupe par parent : seuls les dossiers de premier niveau sont
      // affichés d'emblée, chacun repliant les siens.
      $parNiveau = [];
      foreach ($dossiers as $d) {
          $parNiveau[(int) ($d['parent_id'] ?? 0)][] = $d;
      }
      ?>
      <?php
      /**
       * Rend un dossier, puis ses enfants dans un bloc que l'on replie.
       * Sans JavaScript, ce bloc reste ouvert : l'arborescence est simplement
       * affichée en entier, ce qui reste utilisable.
       */
      $rendreDossier = function (array $d, array $freres, int $rangFrere) use (
          &$rendreDossier, $parNiveau, $descendants, $dossiers, $icones, $palette, $csrf, $pastilleMatiere, $matieres
      ): void {
          $enfants = $parNiveau[(int) $d['id']] ?? [];
          ?>
        <?php // Le nœud enveloppe le dossier ET sa descendance : un dossier ne
              // peut alors pas être déposé chez lui-même, il suffit de regarder
              // si la cible est à l'intérieur. ?>
        <div class="dossier-noeud" data-dossier="<?= (int) $d['id'] ?>">
        <section class="carte dossier-carte"
                 title="<?= e(t('dos.glisser_aide')) ?>">
          <div class="matiere-carte">
            <span class="matiere-pastille" style="background:<?= e($d['couleur']) ?>;display:grid;place-items:center;font-size:1.1rem">
              <?= e($d['icone']) ?>
            </span>

            <?php // Un dossier qui en contient d'autres se plie et se déplie d'un clic. ?>
            <?php if ($enfants !== []): ?>
              <button class="dossier-plier" type="button"
                      data-plier="enfants-<?= (int) $d['id'] ?>"
                      aria-expanded="true" aria-controls="enfants-<?= (int) $d['id'] ?>">
                <span class="dossier-plier__chevron" aria-hidden="true">›</span>
                <span style="flex:1;min-width:0;text-align:left">
                  <span class="dossier-plier__nom"><?= e($d['nom']) ?></span><?= $pastilleMatiere($d) ?>
                  <span class="discret" style="display:block;font-size:.84rem">
                    <?= e(tn('cours.nb_cours', (int) $d['nb_cours'])) ?> ·
                    <?= e(tn('dos.sous_dossiers', count($enfants))) ?>
                  </span>
                </span>
              </button>
            <?php else: ?>
              <div style="flex:1;min-width:0">
                <h2 style="margin-bottom:.15rem"><?= e($d['nom']) ?><?= $pastilleMatiere($d) ?></h2>
                <p class="discret" style="margin:0">
                  <?= e(tn('cours.nb_cours', (int) $d['nb_cours'])) ?>
                </p>
              </div>
            <?php endif; ?>
            <div class="actions">
              <?php if (count($freres) > 1): ?>
                <form method="post" action="<?= url('dossiers/' . $d['id'] . '/deplacer') ?>" class="en-ligne">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="sens" value="haut">
                  <button class="bouton bouton--discret bouton--petit" type="submit"
                          title="<?= e(t('commun.monter')) ?>"<?= $rangFrere === 0 ? ' disabled' : '' ?>>↑</button>
                </form>
                <form method="post" action="<?= url('dossiers/' . $d['id'] . '/deplacer') ?>" class="en-ligne">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="sens" value="bas">
                  <button class="bouton bouton--discret bouton--petit" type="submit"
                          title="<?= e(t('commun.descendre')) ?>"<?= $rangFrere === count($freres) - 1 ? ' disabled' : '' ?>>↓</button>
                </form>
              <?php endif; ?>
              <a class="bouton bouton--discret bouton--petit"
                 href="<?= url('cours', ['dossier' => $d['id']]) ?>"><?= e(t('mat.voir_cours')) ?></a>
              <a class="bouton bouton--secondaire bouton--petit bouton-partage"
                 href="<?= url('partager/dossiers/' . $d['id']) ?>" data-fenetre><?= Partages::icone(15) ?> <?= e(t('evt.partager')) ?></a>
              <button class="bouton bouton--secondaire bouton--petit" type="button"
                      data-bascule="edition-<?= (int) $d['id'] ?>"><?= e(t('evt.modifier')) ?></button>
            </div>
          </div>

          <div id="edition-<?= (int) $d['id'] ?>" hidden style="margin-top:1rem">
            <hr class="separateur" style="margin:.75rem 0">
            <form method="post" action="<?= url('dossiers/' . $d['id'] . '/modifier') ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

              <div class="ligne-champs">
                <div class="champ">
                  <label for="nom-<?= (int) $d['id'] ?>"><?= e(t('commun.nom')) ?></label>
                  <input type="text" id="nom-<?= (int) $d['id'] ?>" name="nom" required maxlength="120"
                         value="<?= e($d['nom']) ?>">
                </div>
                <div class="champ">
                  <label for="par-<?= (int) $d['id'] ?>"><?= e(t('dos.range_dans')) ?></label>
                  <select id="par-<?= (int) $d['id'] ?>" name="parent_id">
                    <option value=""><?= e(t('dos.racine')) ?></option>
                    <?php foreach ($dossiers as $autre): ?>
                      <?php // Ni lui-même, ni l'un de ses propres sous-dossiers. ?>
                      <?php if (in_array((int) $autre['id'], $descendants[(int) $d['id']], true)) { continue; } ?>
                      <option value="<?= (int) $autre['id'] ?>"
                              <?= (int) ($d['parent_id'] ?? 0) === (int) $autre['id'] ? ' selected' : '' ?>>
                        <?= e(retrait_dossier($autre) . $autre['icone'] . ' ' . $autre['nom']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <?= Vue::rendre('dossiers/_matiere', ['matieres' => $matieres, 'choisie' => $d['matiere_id'] === null ? null : (int) $d['matiere_id'], 'idChamp' => 'mat-' . (int) $d['id']]) ?>
              <label class="case" style="display:block;margin:-.4rem 0 1rem">
                <input type="checkbox" name="appliquer_matiere" value="1" checked>
                <?= e(t('dos.appliquer_matiere')) ?>
              </label>

              <div class="champ">
                <span class="legende"><?= e(t('commun.icone')) ?></span>
                <div class="choix-icones">
                  <?php foreach ($icones as $i => $icone): ?>
                    <?php $idIcone = 'di-' . $d['id'] . '-' . $i; ?>
                    <input type="radio" id="<?= $idIcone ?>" name="icone" value="<?= e($icone) ?>"
                           <?= $d['icone'] === $icone ? ' checked' : '' ?>>
                    <label for="<?= $idIcone ?>"><?= e($icone) ?></label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="champ">
                <span class="legende"><?= e(t('commun.couleur')) ?></span>
                <div class="choix-couleurs">
                  <?php foreach ($palette as $i => $couleur): ?>
                    <?php $idCouleur = 'dc-' . $d['id'] . '-' . $i; ?>
                    <input type="radio" id="<?= $idCouleur ?>" name="couleur" value="<?= e($couleur) ?>"
                           <?= strtolower((string) $d['couleur']) === $couleur ? ' checked' : '' ?>>
                    <label for="<?= $idCouleur ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="actions">
                <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
              </div>
            </form>

            <form method="post" action="<?= url('dossiers/' . $d['id'] . '/supprimer') ?>" style="margin-top:.75rem"
                  data-confirmation="<?= e(t('dos.supprimer_sur')) ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('dos.supprimer')) ?></button>
            </form>
          </div>
        </section>

          <?php if ($enfants !== []): ?>
            <div class="dossier-enfants" id="enfants-<?= (int) $d['id'] ?>" data-repliable>
              <?php foreach ($enfants as $rang => $enfant): ?>
                <?= $rendreDossier($enfant, $enfants, $rang) ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
          <?php
      };
      ?>

      <div data-dossiers-arbre>
        <?php foreach ($parNiveau[0] ?? [] as $rangFrere => $d): ?>
          <?= $rendreDossier($d, $parNiveau[0], $rangFrere) ?>
        <?php endforeach; ?>
      </div>

      <?php // Déposer ici sort un dossier de son parent. ?>
      <div class="dossier-racine" data-dossier="">
        <?= e(t('dos.racine_depot')) ?>
      </div>

      <?php // Le glisser-déposer poste ici le dossier et son parent d'arrivée. ?>
      <form method="post" action="<?= url('dossiers/ranger') ?>" id="forme-ranger-dossier" hidden>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="dossier" value="">
        <input type="hidden" name="parent" value="">
      </form>
    <?php endif; ?>

    <?php if ($sansDossier > 0): ?>
      <p class="discret">
        <?= e(tn('dos.sans_dossier', $sansDossier)) ?>
        <a href="<?= url('cours') ?>"><?= e(t('mat.sans_matiere_lien')) ?></a>.
      </p>
    <?php endif; ?>
  </div>

  <div class="carte">
    <h2><?= e(t('dos.nouveau')) ?></h2>
    <form method="post" action="<?= url('dossiers') ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

      <div class="champ">
        <label for="nom"><?= e(t('commun.nom')) ?></label>
        <input type="text" id="nom" name="nom" required maxlength="120" placeholder="<?= e(t('dos.nom_exemple')) ?>">
      </div>

      <div class="champ">
        <label for="parent_id"><?= e(t('dos.range_dans')) ?></label>
        <select id="parent_id" name="parent_id">
          <option value=""><?= e(t('dos.racine')) ?></option>
          <?php foreach ($dossiers as $d): ?>
            <option value="<?= (int) $d["id"] ?>"><?= e(retrait_dossier($d) . $d["icone"] . " " . $d["nom"]) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="champ__aide"><?= e(t('dos.parent_aide')) ?></span>
      </div>

      <?= Vue::rendre('dossiers/_matiere', ['matieres' => $matieres, 'choisie' => null, 'idChamp' => 'nouveau-matiere']) ?>

      <div class="champ">
        <span class="legende"><?= e(t('commun.icone')) ?></span>
        <div class="choix-icones">
          <?php foreach ($icones as $i => $icone): ?>
            <input type="radio" id="ndi-<?= $i ?>" name="icone" value="<?= e($icone) ?>"<?= $i === 0 ? ' checked' : '' ?>>
            <label for="ndi-<?= $i ?>"><?= e($icone) ?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="champ">
        <span class="legende"><?= e(t('commun.couleur')) ?></span>
        <div class="choix-couleurs">
          <?php foreach ($palette as $i => $couleur): ?>
            <input type="radio" id="ndc-<?= $i ?>" name="couleur" value="<?= e($couleur) ?>"<?= $i === 0 ? ' checked' : '' ?>>
            <label for="ndc-<?= $i ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
          <?php endforeach; ?>
        </div>
      </div>

      <button class="bouton bouton--bloc" type="submit"><?= e(t('dos.creer')) ?></button>
    </form>
  </div>
</div>
