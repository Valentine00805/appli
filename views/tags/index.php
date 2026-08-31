<?php
/** @var array $tags @var string $tri @var int $inutilises */
$csrf = Session::jetonCsrf();
$total = count($tags);
?>

<?= Vue::rendre('organisation/_onglets', ['onglet' => 'tags']) ?>

<div class="entete-page">
  <div>
    <h1>Mes tags</h1>
    <p>
      <?= $total ?> tag<?= $total > 1 ? 's' : '' ?>.
      Ils affinent le classement des cours, en plus des matières.
    </p>
  </div>
  <div class="actions">
    <a class="bouton bouton--secondaire" href="<?= url('cours') ?>">Voir les cours</a>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <?php if ($tags === []): ?>
      <div class="vide">
        <span class="vide__icone">🏷️</span>
        <p>Aucun tag pour le moment.</p>
        <p class="discret">
          Créez-en ici, ou saisissez-en directement dans le champ « Tags » d'un cours :
          ils seront ajoutés automatiquement.
        </p>
      </div>
    <?php else: ?>
      <div class="filtres" style="margin-bottom:.75rem">
        <span class="discret">Trier :</span>
        <a class="pastille" href="<?= url('organisation/tags', ['tri' => 'nom']) ?>"
           <?= $tri === 'nom' ? 'style="background:var(--accent-doux);color:var(--accent-fonce)"' : '' ?>>A → Z</a>
        <a class="pastille" href="<?= url('organisation/tags', ['tri' => 'usage']) ?>"
           <?= $tri === 'usage' ? 'style="background:var(--accent-doux);color:var(--accent-fonce)"' : '' ?>>Les plus utilisés</a>
      </div>

      <?php foreach ($tags as $t): ?>
        <section class="carte">
          <div class="matiere-carte">
            <span class="matiere-pastille"
                  style="background:var(--fond-doux);display:grid;place-items:center;font-size:1.1rem;color:var(--texte-doux)">#</span>

            <div style="flex:1;min-width:0">
              <h2 style="margin-bottom:.15rem"><?= e($t['nom']) ?></h2>
              <p class="discret" style="margin:0">
                <?php if ((int) $t['nb_cours'] === 0): ?>
                  sur aucun cours
                <?php else: ?>
                  sur <?= (int) $t['nb_cours'] ?> cours
                <?php endif; ?>
              </p>
            </div>

            <div class="actions">
              <?php if ((int) $t['nb_cours'] > 0): ?>
                <a class="bouton bouton--discret bouton--petit"
                   href="<?= url('cours', ['tag' => $t['id']]) ?>">Voir</a>
              <?php endif; ?>
              <button class="bouton bouton--secondaire bouton--petit" type="button"
                      data-bascule="edition-tag-<?= (int) $t['id'] ?>">Modifier</button>
            </div>
          </div>

          <div id="edition-tag-<?= (int) $t['id'] ?>" hidden style="margin-top:1rem">
            <hr class="separateur" style="margin:.75rem 0">

            <form method="post" action="<?= url('tags/' . $t['id'] . '/modifier') ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <div class="champ">
                <label for="nom-tag-<?= (int) $t['id'] ?>">Renommer</label>
                <input type="text" id="nom-tag-<?= (int) $t['id'] ?>" name="nom" required maxlength="60"
                       value="<?= e($t['nom']) ?>">
                <span class="champ__aide">Le nouveau nom s'applique à tous les cours concernés.</span>
              </div>
              <button class="bouton" type="submit">Enregistrer</button>
            </form>

            <?php if ($total > 1): ?>
              <hr class="separateur" style="margin:1rem 0">
              <form method="post" action="<?= url('tags/' . $t['id'] . '/fusionner') ?>"
                    data-confirmation="Fusionner ce tag dans l'autre ? Les cours seront reportés et ce tag disparaîtra.">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="champ">
                  <label for="cible-<?= (int) $t['id'] ?>">Fusionner dans</label>
                  <select id="cible-<?= (int) $t['id'] ?>" name="cible_id" required>
                    <option value="">— Choisir un tag —</option>
                    <?php foreach ($tags as $autre): ?>
                      <?php if ((int) $autre['id'] !== (int) $t['id']): ?>
                        <option value="<?= (int) $autre['id'] ?>"><?= e($autre['nom']) ?></option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </select>
                  <span class="champ__aide">
                    Pratique pour réunir deux écritures d'un même tag (« revision » et « révision »).
                  </span>
                </div>
                <button class="bouton bouton--secondaire" type="submit">Fusionner</button>
              </form>
            <?php endif; ?>

            <form method="post" action="<?= url('tags/' . $t['id'] . '/supprimer') ?>" style="margin-top:1rem"
                  data-confirmation="Supprimer ce tag ? Il sera retiré des cours, qui seront conservés.">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--danger bouton--petit" type="submit">Supprimer ce tag</button>
            </form>
          </div>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="pile">
    <div class="carte">
      <h2>Nouveaux tags</h2>
      <form method="post" action="<?= url('tags') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="champ">
          <label for="nom">Nom</label>
          <input type="text" id="nom" name="nom" required maxlength="300"
                 placeholder="révision, chapitre 3, important">
          <span class="champ__aide">
            Plusieurs d'un coup en les séparant par des virgules.
          </span>
        </div>
        <button class="bouton bouton--bloc" type="submit">Créer</button>
      </form>
    </div>

    <?php if ($inutilises > 0): ?>
      <div class="carte">
        <h2>Ménage</h2>
        <p class="discret">
          <?= $inutilises ?> tag<?= $inutilises > 1 ? 's ne sont' : ' n\'est' ?> sur aucun cours.
        </p>
        <form method="post" action="<?= url('tags/nettoyer') ?>"
              data-confirmation="Supprimer tous les tags qui ne sont sur aucun cours ?">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--secondaire bouton--bloc" type="submit">
            Supprimer les tags inutilisés
          </button>
        </form>
      </div>
    <?php endif; ?>

    <div class="carte">
      <h2>Comment ça marche</h2>
      <p class="discret" style="margin-bottom:.6rem">
        Un tag saisi dans le champ « Tags » d'un cours est créé automatiquement s'il n'existe pas.
      </p>
      <p class="discret" style="margin:0">
        Contrairement à la matière, dont un cours n'a qu'une seule valeur, un cours peut porter
        autant de tags que vous voulez.
      </p>
    </div>
  </div>
</div>
