<?php
/** @var array $types, $palette, $icones @var int $sansType */
$csrf = Session::jetonCsrf();
$dernier = count($types) - 1;
?>

<div class="entete-page">
  <div>
    <h1>Types d'évènement</h1>
    <p>Ils classent ce que vous mettez au calendrier. Chacun a son icône, sa couleur et sa place dans la liste.</p>
  </div>
  <div class="actions">
    <a class="bouton bouton--secondaire" href="<?= url('calendrier') ?>">Voir le calendrier</a>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <?php if ($types === []): ?>
      <div class="vide">
        <span class="vide__icone">🏷️</span>
        <p>Aucun type pour le moment. Sans type, vos évènements resteront classés « Sans type ».</p>
      </div>
    <?php else: ?>
      <?php foreach ($types as $i => $t): ?>
        <section class="carte">
          <div class="matiere-carte">
            <span class="matiere-pastille"
                  style="background:<?= e($t['couleur']) ?>;display:grid;place-items:center;font-size:1.2rem">
              <?= e($t['icone']) ?>
            </span>

            <div style="flex:1;min-width:0">
              <h2 style="margin-bottom:.15rem"><?= e($t['nom']) ?></h2>
              <p class="discret" style="margin:0">
                <?= (int) $t['nb_evenements'] ?> évènement<?= (int) $t['nb_evenements'] > 1 ? 's' : '' ?>
                <?php if ((int) $t['est_echeance'] === 1): ?>
                  · <span title="Apparaît dans « Examens &amp; devoirs » sur l'accueil">⏳ compte comme échéance</span>
                <?php endif; ?>
              </p>
            </div>

            <div class="actions">
              <form method="post" action="<?= url('types/' . $t['id'] . '/deplacer') ?>" class="en-ligne">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="sens" value="haut">
                <button class="bouton bouton--discret bouton--petit" type="submit"
                        title="Monter"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
              </form>
              <form method="post" action="<?= url('types/' . $t['id'] . '/deplacer') ?>" class="en-ligne">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="sens" value="bas">
                <button class="bouton bouton--discret bouton--petit" type="submit"
                        title="Descendre"<?= $i === $dernier ? ' disabled' : '' ?>>↓</button>
              </form>
              <a class="bouton bouton--discret bouton--petit"
                 href="<?= url('calendrier', ['vue' => 'liste', 'type' => $t['id']]) ?>">Voir</a>
              <button class="bouton bouton--secondaire bouton--petit" type="button"
                      data-bascule="edition-type-<?= (int) $t['id'] ?>">Modifier</button>
            </div>
          </div>

          <div id="edition-type-<?= (int) $t['id'] ?>" hidden style="margin-top:1rem">
            <hr class="separateur" style="margin:.75rem 0">

            <form method="post" action="<?= url('types/' . $t['id'] . '/modifier') ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

              <div class="champ">
                <label for="nom-t-<?= (int) $t['id'] ?>">Nom</label>
                <input type="text" id="nom-t-<?= (int) $t['id'] ?>" name="nom" required maxlength="60"
                       value="<?= e($t['nom']) ?>">
              </div>

              <div class="champ">
                <span class="legende">Icône</span>
                <div class="choix-icones">
                  <?php foreach ($icones as $j => $icone): ?>
                    <?php $idIcone = 'i-' . $t['id'] . '-' . $j; ?>
                    <input type="radio" id="<?= $idIcone ?>" name="icone" value="<?= e($icone) ?>"
                           <?= $t['icone'] === $icone ? ' checked' : '' ?>>
                    <label for="<?= $idIcone ?>"><?= e($icone) ?></label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="champ">
                <span class="legende">Couleur</span>
                <div class="choix-couleurs">
                  <?php foreach ($palette as $j => $couleur): ?>
                    <?php $idCouleur = 'ct-' . $t['id'] . '-' . $j; ?>
                    <input type="radio" id="<?= $idCouleur ?>" name="couleur" value="<?= e($couleur) ?>"
                           <?= strtolower((string) $t['couleur']) === $couleur ? ' checked' : '' ?>>
                    <label for="<?= $idCouleur ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
                  <?php endforeach; ?>
                </div>
                <span class="champ__aide">Utilisée dans le calendrier quand l'évènement n'a pas de matière.</span>
              </div>

              <label class="case" style="margin-bottom:1rem">
                <input type="checkbox" name="est_echeance" value="1"<?= (int) $t['est_echeance'] === 1 ? ' checked' : '' ?>>
                Compte comme une échéance
              </label>
              <span class="champ__aide" style="display:block;margin:-.75rem 0 1rem">
                Les évènements de ce type apparaissent sur l'accueil avec un compte à rebours (J-5, demain…).
              </span>

              <div class="actions">
                <button class="bouton" type="submit">Enregistrer</button>
              </div>
            </form>

            <form method="post" action="<?= url('types/' . $t['id'] . '/supprimer') ?>" style="margin-top:.75rem"
                  data-confirmation="Supprimer ce type ? Les évènements concernés seront conservés, sans type.">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--danger bouton--petit" type="submit">Supprimer ce type</button>
            </form>
          </div>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($sansType > 0): ?>
      <p class="discret">
        <?= $sansType ?> évènement<?= $sansType > 1 ? 's n\'ont' : ' n\'a' ?> plus de type —
        <a href="<?= url('calendrier', ['vue' => 'liste']) ?>">les retrouver au calendrier</a>
        pour leur en attribuer un.
      </p>
    <?php endif; ?>
  </div>

  <div class="carte">
    <h2>Nouveau type</h2>
    <form method="post" action="<?= url('types') ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

      <div class="champ">
        <label for="nom">Nom</label>
        <input type="text" id="nom" name="nom" required maxlength="60" placeholder="Oral, TP, Sortie…">
      </div>

      <div class="champ">
        <span class="legende">Icône</span>
        <div class="choix-icones">
          <?php foreach ($icones as $j => $icone): ?>
            <input type="radio" id="ni-<?= $j ?>" name="icone" value="<?= e($icone) ?>"<?= $j === 0 ? ' checked' : '' ?>>
            <label for="ni-<?= $j ?>"><?= e($icone) ?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="champ">
        <span class="legende">Couleur</span>
        <div class="choix-couleurs">
          <?php foreach ($palette as $j => $couleur): ?>
            <input type="radio" id="nct-<?= $j ?>" name="couleur" value="<?= e($couleur) ?>"<?= $j === 0 ? ' checked' : '' ?>>
            <label for="nct-<?= $j ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
          <?php endforeach; ?>
        </div>
      </div>

      <label class="case" style="margin-bottom:1rem">
        <input type="checkbox" name="est_echeance" value="1">
        Compte comme une échéance
      </label>

      <button class="bouton bouton--bloc" type="submit">Créer le type</button>
    </form>
  </div>
</div>
