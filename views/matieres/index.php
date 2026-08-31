<?php
/** @var array $matieres, $palette @var int $sansMatiere */
$csrf = Session::jetonCsrf();
?>

<div class="entete-page">
  <div>
    <h1>Mes matières</h1>
    <p>Chaque matière a sa couleur : elle sert de repère dans le calendrier et dans la liste des cours.</p>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <?php if ($matieres === []): ?>
      <div class="vide">
        <span class="vide__icone">🎨</span>
        <p>Aucune matière pour le moment. Créez-en une avec le formulaire ci-contre.</p>
      </div>
    <?php else: ?>
      <?php foreach ($matieres as $m): ?>
        <section class="carte">
          <div class="matiere-carte">
            <span class="matiere-pastille" style="background:<?= e($m['couleur']) ?>"></span>
            <div style="flex:1;min-width:0">
              <h2 style="margin-bottom:.15rem"><?= e($m['nom']) ?></h2>
              <p class="discret" style="margin:0">
                <?= (int) $m['nb_cours'] ?> cours · <?= (int) $m['nb_evenements'] ?> évènement<?= (int) $m['nb_evenements'] > 1 ? 's' : '' ?>
                <?= $m['enseignant'] ? ' · ' . e($m['enseignant']) : '' ?>
              </p>
            </div>
            <div class="actions">
              <a class="bouton bouton--discret bouton--petit" href="<?= url('cours', ['matiere' => $m['id']]) ?>">Voir les cours</a>
              <button class="bouton bouton--secondaire bouton--petit" type="button"
                      data-bascule="edition-<?= (int) $m['id'] ?>">Modifier</button>
            </div>
          </div>

          <div id="edition-<?= (int) $m['id'] ?>" hidden style="margin-top:1rem">
            <hr class="separateur" style="margin:.75rem 0">
            <form method="post" action="<?= url('matieres/' . $m['id'] . '/modifier') ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

              <div class="ligne-champs">
                <div class="champ">
                  <label for="nom-<?= (int) $m['id'] ?>">Nom</label>
                  <input type="text" id="nom-<?= (int) $m['id'] ?>" name="nom" required maxlength="120"
                         value="<?= e($m['nom']) ?>">
                </div>
                <div class="champ">
                  <label for="ens-<?= (int) $m['id'] ?>">Enseignant</label>
                  <input type="text" id="ens-<?= (int) $m['id'] ?>" name="enseignant" maxlength="120"
                         value="<?= e((string) $m['enseignant']) ?>">
                </div>
              </div>

              <div class="champ">
                <span class="legende">Couleur</span>
                <div class="choix-couleurs">
                  <?php foreach ($palette as $i => $couleur): ?>
                    <?php $id = 'c-' . $m['id'] . '-' . $i; ?>
                    <input type="radio" id="<?= $id ?>" name="couleur" value="<?= e($couleur) ?>"
                           <?= strtolower((string) $m['couleur']) === $couleur ? ' checked' : '' ?>>
                    <label for="<?= $id ?>" style="background:<?= e($couleur) ?>"
                           title="<?= e($couleur) ?>"><span class="sr-only"></span></label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="actions">
                <button class="bouton" type="submit">Enregistrer</button>
              </div>
            </form>

            <form method="post" action="<?= url('matieres/' . $m['id'] . '/supprimer') ?>" style="margin-top:.75rem"
                  data-confirmation="Supprimer cette matière ? Les cours et évènements associés seront conservés, sans matière.">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--danger bouton--petit" type="submit">Supprimer la matière</button>
            </form>
          </div>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($sansMatiere > 0): ?>
      <p class="discret">
        <?= $sansMatiere ?> cours n'<?= $sansMatiere > 1 ? 'ont' : 'a' ?> pas de matière —
        <a href="<?= url('cours') ?>">les retrouver dans la liste</a>.
      </p>
    <?php endif; ?>
  </div>

  <div class="carte">
    <h2>Nouvelle matière</h2>
    <form method="post" action="<?= url('matieres') ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

      <div class="champ">
        <label for="nom">Nom</label>
        <input type="text" id="nom" name="nom" required maxlength="120" placeholder="Physique-Chimie">
      </div>

      <div class="champ">
        <label for="enseignant">Enseignant <span class="discret">(facultatif)</span></label>
        <input type="text" id="enseignant" name="enseignant" maxlength="120" placeholder="Mme Martin">
      </div>

      <div class="champ">
        <span class="legende">Couleur</span>
        <div class="choix-couleurs">
          <?php foreach ($palette as $i => $couleur): ?>
            <input type="radio" id="nc-<?= $i ?>" name="couleur" value="<?= e($couleur) ?>"<?= $i === 0 ? ' checked' : '' ?>>
            <label for="nc-<?= $i ?>" style="background:<?= e($couleur) ?>" title="<?= e($couleur) ?>"></label>
          <?php endforeach; ?>
        </div>
      </div>

      <button class="bouton bouton--bloc" type="submit">Créer la matière</button>
    </form>
  </div>
</div>
