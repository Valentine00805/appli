<?php
/**
 * Le paquet d'un cours : ses cartes, et de quoi en fabriquer.
 *
 * @var array $cours
 * @var array $cartes
 */
$dues = 0;
foreach ($cartes as $c) {
    if ($c['revoir_le'] <= date('Y-m-d')) {
        $dues++;
    }
}

/** D'où vient une carte, dit en clair. */
$provenance = static function (array $c): string {
    return match ($c['origine']) {
        'cours'   => 'du cours',
        'fiche'   => 'de la fiche',
        'fichier' => $c['source'] !== '' ? 'de ' . $c['source'] : 'd\'un document',
        default   => 'écrite à la main',
    };
};
?>

<p><a href="<?= url('cartes') ?>">← Cartes</a></p>

<div class="entete-page">
  <div>
    <h1><?= e($cours['titre']) ?></h1>
    <p class="discret">
      <?php if ($cartes === []): ?>
        Aucune carte pour l'instant.
      <?php else: ?>
        <?= count($cartes) ?> carte<?= count($cartes) > 1 ? 's' : '' ?>
        <?php if ($dues > 0): ?>· <?= $dues ?> à revoir aujourd'hui<?php endif; ?>
      <?php endif; ?>
    </p>
  </div>

  <div class="actions">
    <a class="bouton bouton--secondaire" href="<?= url('cartes') ?>">Fabriquer des cartes</a>
    <?php if ($dues > 0): ?>
      <a class="bouton" href="<?= url('cartes/seance', ['cours' => $cours['id']]) ?>">Réviser</a>
    <?php endif; ?>
    <a class="bouton bouton--secondaire" href="<?= url('cours/' . $cours['id']) ?>">Voir le cours</a>
  </div>
</div>

  <section class="carte">
    <h2>Le paquet</h2>

    <?php if ($cartes === []): ?>
      <p class="discret">Vide pour l'instant.</p>
    <?php else: ?>
      <ul class="paquet">
        <?php foreach ($cartes as $c): ?>
          <li class="carte-ligne">
            <details>
              <summary>
                <span class="carte-ligne__question"><?= e($c['question']) ?></span>
                <span class="carte-ligne__boite" title="Boîte <?= (int) $c['boite'] ?> sur 5">
                  <?= str_repeat('●', (int) $c['boite']) ?><span class="discret"><?= str_repeat('○', 5 - (int) $c['boite']) ?></span>
                </span>
              </summary>

              <form method="post" action="<?= url('cartes/' . $c['id'] . '/modifier') ?>" class="pile carte-ligne__edition">
                <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                <div class="champ">
                  <label for="q-<?= (int) $c['id'] ?>">Question</label>
                  <input type="text" id="q-<?= (int) $c['id'] ?>" name="question"
                         value="<?= e($c['question']) ?>" maxlength="500">
                </div>
                <div class="champ">
                  <label for="r-<?= (int) $c['id'] ?>">Réponse</label>
                  <textarea id="r-<?= (int) $c['id'] ?>" name="reponse" rows="3"><?= e($c['reponse']) ?></textarea>
                </div>
                <p class="champ__aide">
                  Venue <?= e($provenance($c)) ?>.
                  <?php if ((int) $c['vues'] > 0): ?>
                    Revue <?= (int) $c['vues'] ?> fois, sue <?= (int) $c['reussies'] ?> fois.
                  <?php endif; ?>
                  Prochaine révision le <?= e(date_fr((string) $c['revoir_le'], false)) ?>.
                </p>
                <div class="actions">
                  <button class="bouton bouton--secondaire bouton--petit" type="submit">Enregistrer</button>
                </div>
              </form>

              <form method="post" action="<?= url('cartes/' . $c['id'] . '/supprimer') ?>"
                    data-confirmation="Supprimer cette carte ?">
                <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                <button class="bouton bouton--discret bouton--petit" type="submit">Supprimer</button>
              </form>
            </details>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

<?php if ($cartes !== []): ?>
  <?php
  /*
   * Reprendre à zéro ne supprime rien : les cartes restent, leur histoire
   * aussi. C'est l'échéancier qui repart, quand on veut refaire tout le tour
   * avant un examen plutôt que d'attendre son tour boîte par boîte.
   */
  ?>
  <section class="carte reprise">
    <div>
      <h2>Reprendre le paquet à zéro</h2>
      <p class="champ__aide">
        Les <?= count($cartes) ?> cartes reviennent en boîte 1, toutes à revoir
        aujourd'hui. Rien n'est supprimé : les questions, les réponses et le
        nombre de fois où vous les avez sues restent tels quels.
      </p>
    </div>
    <form method="post" action="<?= url('cours/' . $cours['id'] . '/cartes/rezero') ?>"
          data-confirmation="Remettre les <?= count($cartes) ?> cartes de ce cours à revoir aujourd'hui ?">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <button class="bouton bouton--secondaire" type="submit">Tout remettre à revoir</button>
    </form>
  </section>
<?php endif; ?>

<?php if ($cartes !== []): ?>
  <?php
  /*
   * Vider le paquet efface aussi ce qu'on savait de chaque carte : la boîte
   * où elle était montée, les fois où on l'a sue. Le bouton le dit, et la
   * confirmation le redit avec le compte.
   */
  ?>
  <section class="carte zone-danger">
    <div>
      <h2>Vider le paquet</h2>
      <p class="champ__aide">
        Les <?= count($cartes) ?> cartes de ce cours seront supprimées, ainsi que
        votre avancement sur chacune. Le cours, sa fiche et ses documents ne
        sont pas touchés : vous pourrez en refabriquer des cartes.
      </p>
    </div>
    <form method="post" action="<?= url('cours/' . $cours['id'] . '/cartes/vider') ?>"
          data-confirmation="Supprimer les <?= count($cartes) ?> cartes de ce cours, et votre avancement sur chacune ?">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <button class="bouton bouton--danger" type="submit">Supprimer les <?= count($cartes) ?> cartes</button>
    </form>
  </section>
<?php endif; ?>
