<?php
/**
 * Les paquets de cartes, un par cours, et ce qui est dû aujourd'hui.
 *
 * @var array $paquets  un cours par ligne, avec ses compteurs
 * @var int $aRevoir    cartes dues, tous cours confondus
 * @var int $total      cartes existantes
 * @var array $cours     tous les cours, pour choisir où puiser
 * @var array $propositions   ce que le générateur vient de trouver, à valider
 * @var ?array $coursPropose  le cours dont elles viennent
 */
?>

<div class="entete-page">
  <div>
    <h1>🃏 Cartes</h1>
    <p>
      <?php if ($total === 0): ?>
        Des questions courtes, revues au bon moment.
      <?php elseif ($aRevoir === 0): ?>
        Rien à revoir aujourd'hui. <?= $total ?> carte<?= $total > 1 ? 's' : '' ?> en tout.
      <?php else: ?>
        <?= $aRevoir ?> carte<?= $aRevoir > 1 ? 's' : '' ?> à revoir aujourd'hui,
        sur <?= $total ?>.
      <?php endif; ?>
    </p>
  </div>

  <?php if ($aRevoir > 0): ?>
    <a class="bouton" href="<?= url('cartes/seance') ?>">Réviser <?= $aRevoir ?> carte<?= $aRevoir > 1 ? 's' : '' ?></a>
  <?php endif; ?>
</div>

<?php if ($propositions !== []): ?>
  <?php
  /*
   * Les propositions ne sont pas encore des cartes : rien n'est enregistré tant
   * que l'utilisateur n'a pas coché. Chaque question et chaque réponse reste
   * modifiable ici, parce qu'une règle se trompe parfois de découpe.
   */
  ?>
  <form class="carte propositions" method="post" action="<?= url('cartes/retenir') ?>">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <input type="hidden" name="cours" value="<?= (int) $coursPropose['id'] ?>">

    <div class="propositions__entete">
      <h2><?= count($propositions) ?> proposition<?= count($propositions) > 1 ? 's' : '' ?>
        <span class="discret">pour « <?= e($coursPropose['titre']) ?> »</span></h2>
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
      <a class="bouton bouton--discret" href="<?= url('cartes') ?>">Abandonner</a>
    </div>
  </form>
<?php endif; ?>

<section class="carte fabrique">
  <h2>Fabriquer des cartes</h2>
  <?php if ($cours === []): ?>
    <p class="discret">Vous n'avez pas encore de cours.
      <a href="<?= url('cours/nouveau') ?>">En créer un</a>.</p>
  <?php else: ?>
    <p class="champ__aide">
      Choisissez un cours et ce que l'application doit relire. Elle propose une
      carte partout où elle reconnaît un terme suivi de sa définition, une
      question de devoir, ou une phrase dont un élément mérite d'être caché.
      Rien n'est enregistré : vous validez ensuite ce que vous gardez.
    </p>
    <form method="post" action="<?= url('cartes/proposer') ?>" class="fabrique__form">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <div class="champ">
        <label for="cours">Cours</label>
        <select id="cours" name="cours" required>
          <?php $matiere = false; ?>
          <?php foreach ($cours as $c): ?>
            <?php if ($c['matiere_nom'] !== $matiere): ?>
              <?php if ($matiere !== false): ?></optgroup><?php endif; ?>
              <?php $matiere = $c['matiere_nom']; ?>
              <optgroup label="<?= e($matiere ?? 'Sans matière') ?>">
            <?php endif; ?>
            <option value="<?= (int) $c['id'] ?>">
              <?= e($c['titre']) ?>
              <?php if (!$c['a_fiche']): ?> — sans fiche<?php endif; ?>
            </option>
          <?php endforeach; ?>
          <?php if ($matiere !== false): ?></optgroup><?php endif; ?>
        </select>
      </div>

      <?= Vue::rendre('cartes/_sources', ['cours' => null]) ?>

      <button class="bouton" type="submit">Proposer des cartes</button>
    </form>

    <hr class="separateur">

    <h3>Ou écrire une carte</h3>
    <form method="post" action="<?= url('cartes/carte') ?>" class="fabrique__form">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <div class="champ">
        <label for="cours-carte">Cours</label>
        <select id="cours-carte" name="cours" required>
          <?php $matiere = false; ?>
          <?php foreach ($cours as $c): ?>
            <?php if ($c['matiere_nom'] !== $matiere): ?>
              <?php if ($matiere !== false): ?></optgroup><?php endif; ?>
              <?php $matiere = $c['matiere_nom']; ?>
              <optgroup label="<?= e($matiere ?? 'Sans matière') ?>">
            <?php endif; ?>
            <option value="<?= (int) $c['id'] ?>">
              <?= e($c['titre']) ?>
              <?php if (!$c['a_fiche']): ?> — sans fiche<?php endif; ?>
            </option>
          <?php endforeach; ?>
          <?php if ($matiere !== false): ?></optgroup><?php endif; ?>
        </select>
      </div>
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
  <?php endif; ?>
</section>

<?php if ($paquets === []): ?>
  <div class="vide">
    <span class="vide__icone">🃏</span>
    <p>Aucune carte pour l'instant. Choisissez un cours ci-dessus et laissez
       l'application vous en proposer.</p>
  </div>
<?php else: ?>
  <div class="grille grille--fiches">
    <?php foreach ($paquets as $p): ?>
      <?php
      $dues = (int) $p['a_revoir'];
      $sues = (int) $p['sues'];
      $nb = (int) $p['total'];
      ?>
      <a class="carte fiche-carte" href="<?= url('cours/' . $p['id'] . '/cartes') ?>">
        <p class="fiche-carte__entete">
          <?php if ($p['matiere_nom'] !== null): ?>
            <span class="pastille" style="background:<?= e($p['matiere_couleur']) ?>;color:<?= e(couleur_texte($p['matiere_couleur'])) ?>">
              <?= e($p['matiere_nom']) ?>
            </span>
          <?php else: ?>
            <span class="discret">Sans matière</span>
          <?php endif; ?>
          <?php if ($dues > 0): ?>
            <span class="carte-du"><?= $dues ?> à revoir</span>
          <?php endif; ?>
        </p>

        <h3 class="fiche-carte__titre"><?= e($p['titre']) ?></h3>

        <?php
        /*
         * Le même anneau que sur la fiche : la boîte moyenne du paquet, de 1 à 5,
         * ramenée en pourcentage. Un paquet neuf est vide, un paquet su est plein.
         */
        ?>
        <p class="fiche-carte__ecoute">
          <?= Vue::rendre('cours/_anneau', [
              'pourcentage' => avancement_cartes($nb, (float) $p['boite_moyenne']),
              'titre'       => 'Avancement des cartes',
          ]) ?>
          <span class="discret">
            <?= $nb ?> carte<?= $nb > 1 ? 's' : '' ?>
            <?php if ($sues > 0): ?>· <?= $sues ?> sue<?= $sues > 1 ? 's' : '' ?><?php endif; ?>
          </span>
        </p>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
