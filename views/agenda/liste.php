<?php
/**
 * Les agendas qu'on peut relier, et où chacun en est.
 *
 * @var array $etats  un par fournisseur : [f, configure, relie, compte,
 *                    evenements, envoyes, souci]
 */
$relies = array_filter($etats, static fn (array $e): bool => $e['relie']);
?>

<div class="entete-page">
  <div>
    <p class="discret" style="margin-bottom:.35rem">
      <a href="<?= url('calendrier') ?>">← Calendrier</a>
    </p>
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
         href="<?= url('agenda/' . $f->cle()) ?>">
        <?= $etat['relie'] ? 'Réglages' : ($etat['configure'] ? 'Relier mon compte' : 'En savoir plus') ?>
      </a>
    </section>
  <?php endforeach; ?>
</div>
