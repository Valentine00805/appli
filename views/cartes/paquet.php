<?php
/**
 * Le paquet d'un cours : ses cartes, et de quoi en fabriquer.
 *
 * @var array $cours
 * @var array $cartes
 * @var array $propositions  ce que le générateur vient de trouver, à valider
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
    <?php if ($dues > 0): ?>
      <a class="bouton" href="<?= url('cartes/seance', ['cours' => $cours['id']]) ?>">Réviser</a>
    <?php endif; ?>
    <a class="bouton bouton--secondaire" href="<?= url('cours/' . $cours['id']) ?>">Voir le cours</a>
  </div>
</div>

<?php if ($propositions !== []): ?>
  <?php
  /*
   * Les propositions ne sont pas encore des cartes : rien n'est enregistré tant
   * que l'utilisateur n'a pas coché. Chaque question et chaque réponse reste
   * modifiable ici, parce qu'une règle se trompe parfois de découpe.
   */
  ?>
  <form class="carte propositions" method="post" action="<?= url('cours/' . $cours['id'] . '/cartes/retenir') ?>">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

    <div class="propositions__entete">
      <h2><?= count($propositions) ?> proposition<?= count($propositions) > 1 ? 's' : '' ?></h2>
      <span class="discret">Décochez ce qui ne vous sert pas, corrigez le reste.</span>
    </div>

    <ul class="propositions__liste">
      <?php foreach ($propositions as $rang => $p): ?>
        <li class="proposition">
          <label class="proposition__garder">
            <input type="checkbox" name="carte[<?= $rang ?>][garder]" value="1" checked>
            <span class="proposition__source">
              <?= e(match ($p['origine']) {
                  'cours'   => 'du cours',
                  'fiche'   => 'de la fiche',
                  'fichier' => $p['source'] !== '' ? 'de ' . $p['source'] : 'd\'un document',
                  default   => '',
              }) ?>
            </span>
            <?php $genre = $p['genre'] ?? 'definition'; ?>
            <?php if ($genre !== 'definition'): ?>
              <span class="proposition__genre proposition__genre--<?= e($genre) ?>">
                <?= $genre === 'devoir' ? 'question du devoir — écrivez la réponse' : 'texte à trous' ?>
              </span>
            <?php endif; ?>
          </label>
          <div class="proposition__couple">
            <input type="text" name="carte[<?= $rang ?>][question]" value="<?= e($p['question']) ?>"
                   aria-label="Question" maxlength="500">
            <input type="text" name="carte[<?= $rang ?>][reponse]" value="<?= e($p['reponse']) ?>"
                   aria-label="Réponse" placeholder="<?= $p['reponse'] === '' ? 'À écrire — sans réponse, la carte est ignorée' : '' ?>">
          </div>
          <input type="hidden" name="carte[<?= $rang ?>][origine]" value="<?= e($p['origine']) ?>">
          <input type="hidden" name="carte[<?= $rang ?>][source]" value="<?= e($p['source']) ?>">
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="actions">
      <button class="bouton" type="submit">Ajouter les cartes cochées</button>
      <a class="bouton bouton--discret" href="<?= url('cours/' . $cours['id'] . '/cartes') ?>">Abandonner</a>
    </div>
  </form>
<?php endif; ?>

<div class="colonnes colonnes--cartes">
  <section class="carte">
    <h2>Fabriquer des cartes</h2>
    <p class="champ__aide">
      L'application relit le texte du cours, sa fiche de révision et ses documents
      (PDF, Word, OpenDocument, PowerPoint, tableurs, texte), et propose une carte
      partout où elle trouve un terme suivi de sa définition — « Terme : définition »,
      « Terme = définition », « Terme — définition ». Elle ne devine rien : ce
      qu'elle propose, vous le gardez ou vous le jetez. Un PDF scanné, lui, n'est
      qu'une suite d'images : elle vous dira qu'elle n'a rien pu y lire.
    </p>
    <form method="post" action="<?= url('cours/' . $cours['id'] . '/cartes/proposer') ?>">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <?= Vue::rendre('cartes/_sources', ['cours' => $cours]) ?>
      <button class="bouton bouton--secondaire" type="submit">Proposer des cartes</button>
    </form>

    <hr class="separateur">

    <h3>Ou écrire la vôtre</h3>
    <form method="post" action="<?= url('cours/' . $cours['id'] . '/cartes') ?>" class="pile">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <div class="champ">
        <label for="question">Question</label>
        <input type="text" id="question" name="question" maxlength="500" required>
      </div>
      <div class="champ">
        <label for="reponse">Réponse</label>
        <textarea id="reponse" name="reponse" rows="3" required></textarea>
      </div>
      <button class="bouton bouton--secondaire" type="submit">Ajouter la carte</button>
    </form>
  </section>

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
</div>

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
