<?php
/**
 * Les agendas qu'on peut relier, et où chacun en est.
 *
 * @var array $etats  un par fournisseur : [f, configure, relie, compte,
 *                    evenements, envoyes, souci]
 * @var string $vue  la vue du calendrier qu'on retrouve en arrivant
 * @var bool $dansUneFenetre  rendu seul, pour être posé dans une fenêtre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$relies = array_filter($etats, static fn (array $e): bool => $e['relie']);
?>

<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p class="discret" style="margin-bottom:.35rem">
        <a href="<?= url('calendrier') ?>">← Calendrier</a>
      </p>
    <?php endif; ?>
    <h1>Mes agendas</h1>
    <p>Relier un agenda en ligne au calendrier de l'application — l'un, l'autre, ou les deux.</p>
  </div>

  <?php if ($relies !== []): ?>
    <div class="actions">
      <form method="post" action="<?= url('agenda/synchroniser') ?>">
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
        <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
        <button class="bouton" type="submit">↻ Tout synchroniser</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<div class="pile">
  <?php
  /*
   * La vue qu'on retrouve en arrivant.
   *
   * Le mois s'imposait à tous. Il convient à qui prend du recul sur son
   * trimestre, moins à qui vit sa semaine heure par heure : celui-là
   * commençait chaque visite par un clic pour arriver là où il voulait être.
   *
   * Le réglage ne ferme rien : changer de vue depuis le calendrier reste un
   * clic, et ne touche pas à ce choix-ci.
   */
  ?>
  <section class="carte">
    <form method="post" action="<?= url('agenda/vue') ?>" data-auto-envoi>
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
      <div class="champ" style="max-width:22rem;margin:0">
        <label for="vue-calendrier">La vue du calendrier à l'ouverture</label>
        <select id="vue-calendrier" name="vue">
          <?php foreach (['jour' => 'Jour', 'semaine' => 'Semaine',
                          'mois' => 'Mois', 'liste' => 'Liste'] as $cle => $libelle): ?>
            <option value="<?= e($cle) ?>"<?= $vue === $cle ? ' selected' : '' ?>>
              <?= e($libelle) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="champ__aide">
          Ce que vous voyez en arrivant sur le calendrier. Vous pouvez toujours
          en changer d'un clic sans que ce réglage bouge.
        </span>
      </div>
      <noscript>
        <button class="bouton bouton--secondaire bouton--petit" type="submit"
                style="margin-top:.5rem">Enregistrer</button>
      </noscript>
    </form>
  </section>

  <?php foreach ($etats as $etat): ?>
    <?php $f = $etat['f']; ?>
    <section class="carte agenda-carte">
      <div class="agenda-carte__titre">
        <h2><?= e($f->nom()) ?></h2>
        <?php if (!$etat['configure']): ?>
          <span class="pastille pastille--muette">non activé ici</span>
        <?php elseif ($etat['relie']): ?>
          <span class="pastille pastille--ok">relié</span>
        <?php else: ?>
          <span class="pastille pastille--muette">non relié</span>
        <?php endif; ?>
      </div>

      <?php if (!$etat['configure']): ?>
        <p class="champ__aide" style="margin-top:0">
          Cet agenda demande une inscription unique chez son fournisseur, faite
          une fois pour toutes par la personne qui héberge l'application — pas
          par chacun.
        </p>
      <?php elseif ($etat['relie']): ?>
        <p class="champ__aide" style="margin-top:0">
          <?= $etat['compte'] === '' ? 'Compte relié' : e($etat['compte']) ?>
          <?php if ($etat['evenements'] > 0 || $etat['envoyes'] > 0): ?>
            · <?= (int) $etat['evenements'] ?> évènement<?= $etat['evenements'] > 1 ? 's' : '' ?> venu<?= $etat['evenements'] > 1 ? 's' : '' ?> d'ici
            · <?= (int) $etat['envoyes'] ?> parti<?= $etat['envoyes'] > 1 ? 's' : '' ?> là-bas
          <?php endif; ?>
        </p>
        <?php if ($etat['souci'] !== null): ?>
          <p class="outlook-attention">
            <strong>La dernière synchronisation a échoué</strong>
            (le <?= e(date('d/m/Y à H:i', strtotime($etat['souci']['quand']))) ?>) :
            <?= e($etat['souci']['quoi']) ?>
          </p>
        <?php endif; ?>
      <?php else: ?>
        <p class="champ__aide" style="margin-top:0">
          Vos rendez-vous rejoindront le calendrier de l'application, et vos
          évènements d'ici rejoindront le vôtre.
        </p>
      <?php endif; ?>

      <a class="bouton <?= $etat['relie'] ? 'bouton--secondaire' : '' ?>"
         href="<?= url('agenda/' . $f->cle()) ?>"
         <?= $dansUneFenetre ? 'data-fenetre' : '' ?>>
        <?= $etat['relie'] ? 'Réglages' : ($etat['configure'] ? 'Relier mon compte' : 'En savoir plus') ?>
      </a>
    </section>
  <?php endforeach; ?>
</div>
