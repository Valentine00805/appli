<?php
/** @var array $dossiers, $descendants, $palette, $icones @var int $sansDossier */
$csrf = Session::jetonCsrf();
$dernier = count($dossiers) - 1;
?>

<?= Vue::rendre('organisation/_onglets', ['onglet' => 'dossiers']) ?>

<div class="entete-page">
  <div>
    <h1>Mes dossiers</h1>
    <p>Un autre rangement que les matières : par semestre, par projet, par archive.</p>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <?php if ($dossiers === []): ?>
      <div class="vide">
        <span class="vide__icone">📁</span>
        <p>Aucun dossier pour le moment. Créez-en un avec le formulaire ci-contre.</p>
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
          &$rendreDossier, $parNiveau, $descendants, $dossiers, $icones, $palette, $csrf
      ): void {
          $enfants = $parNiveau[(int) $d['id']] ?? [];
          ?>
        <section class="carte dossier-carte">
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
                  <span class="dossier-plier__nom"><?= e($d['nom']) ?></span>
                  <span class="discret" style="display:block;font-size:.84rem">
                    <?= (int) $d['nb_cours'] ?> cours ·
                    <?= count($enfants) ?> sous-dossier<?= count($enfants) > 1 ? 's' : '' ?>
                  </span>
                </span>
              </button>
            <?php else: ?>
              <div style="flex:1;min-width:0">
                <h2 style="margin-bottom:.15rem"><?= e($d['nom']) ?></h2>
                <p class="discret" style="margin:0">
                  <?= (int) $d['nb_cours'] ?> cours
                </p>
              </div>
            <?php endif; ?>
            <div class="actions">
              <?php if (count($freres) > 1): ?>
                <form method="post" action="<?= url('dossiers/' . $d['id'] . '/deplacer') ?>" class="en-ligne">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="sens" value="haut">
                  <button class="bouton bouton--discret bouton--petit" type="submit"
                          title="Monter"<?= $rangFrere === 0 ? ' disabled' : '' ?>>↑</button>
                </form>
                <form method="post" action="<?= url('dossiers/' . $d['id'] . '/deplacer') ?>" class="en-ligne">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="sens" value="bas">
                  <button class="bouton bouton--discret bouton--petit" type="submit"
                          title="Descendre"<?= $rangFrere === count($freres) - 1 ? ' disabled' : '' ?>>↓</button>
                </form>
              <?php endif; ?>
              <a class="bouton bouton--discret bouton--petit"
                 href="<?= url('cours', ['dossier' => $d['id']]) ?>">Voir les cours</a>
              <button class="bouton bouton--secondaire bouton--petit" type="button"
                      data-bascule="edition-<?= (int) $d['id'] ?>">Modifier</button>
            </div>
          </div>

          <div id="edition-<?= (int) $d['id'] ?>" hidden style="margin-top:1rem">
            <hr class="separateur" style="margin:.75rem 0">
            <form method="post" action="<?= url('dossiers/' . $d['id'] . '/modifier') ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

              <div class="ligne-champs">
                <div class="champ">
                  <label for="nom-<?= (int) $d['id'] ?>">Nom</label>
                  <input type="text" id="nom-<?= (int) $d['id'] ?>" name="nom" required maxlength="120"
                         value="<?= e($d['nom']) ?>">
                </div>
                <div class="champ">
                  <label for="par-<?= (int) $d['id'] ?>">Rangé dans</label>
                  <select id="par-<?= (int) $d['id'] ?>" name="parent_id">
                    <option value="">— À la racine —</option>
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

              <div class="champ">
                <span class="legende">Icône</span>
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
                <span class="legende">Couleur</span>
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
                <button class="bouton" type="submit">Enregistrer</button>
              </div>
            </form>

            <form method="post" action="<?= url('dossiers/' . $d['id'] . '/supprimer') ?>" style="margin-top:.75rem"
                  data-confirmation="Supprimer ce dossier ? Ses cours seront conservés sans dossier, et ses sous-dossiers remonteront à la racine.">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--danger bouton--petit" type="submit">Supprimer le dossier</button>
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
          <?php
      };
      ?>

      <?php foreach ($parNiveau[0] ?? [] as $rangFrere => $d): ?>
        <?= $rendreDossier($d, $parNiveau[0], $rangFrere) ?>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($sansDossier > 0): ?>
      <p class="discret">
        <?= $sansDossier ?> cours <?= $sansDossier > 1 ? 'ne sont' : "n'est" ?> rangé<?= $sansDossier > 1 ? 's' : '' ?>
        dans aucun dossier — <a href="<?= url('cours') ?>">les retrouver dans la liste</a>.
      </p>
    <?php endif; ?>
  </div>

  <div class="carte">
    <h2>Nouveau dossier</h2>
    <form method="post" action="<?= url('dossiers') ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

      <div class="champ">
        <label for="nom">Nom</label>
        <input type="text" id="nom" name="nom" required maxlength="120" placeholder="Semestre 1">
      </div>

      <div class="champ">
        <label for="parent_id">Rangé dans</label>
        <select id="parent_id" name="parent_id">
          <option value="">— À la racine —</option>
          <?php foreach ($dossiers as $d): ?>
            <option value="<?= (int) $d["id"] ?>"><?= e(retrait_dossier($d) . $d["icone"] . " " . $d["nom"]) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="champ__aide">Laissez vide pour un dossier de premier niveau.</span>
      </div>

      <div class="champ">
        <span class="legende">Icône</span>
        <div class="choix-icones">
          <?php foreach ($icones as $i => $icone): ?>
            <input type="radio" id="ndi-<?= $i ?>" name="icone" value="<?= e($icone) ?>"<?= $i === 0 ? ' checked' : '' ?>>
            <label for="ndi-<?= $i ?>"><?= e($icone) ?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="champ">
        <span class="legende">Couleur</span>
        <div class="choix-couleurs">
          <?php foreach ($palette as $i => $couleur): ?>
            <input type="radio" id="ndc-<?= $i ?>" name="couleur" value="<?= e($couleur) ?>"<?= $i === 0 ? ' checked' : '' ?>>
            <label for="ndc-<?= $i ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
          <?php endforeach; ?>
        </div>
      </div>

      <button class="bouton bouton--bloc" type="submit">Créer le dossier</button>
    </form>
  </div>
</div>
