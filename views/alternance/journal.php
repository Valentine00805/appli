<?php
/**
 * Le journal des missions : une page par semaine, la plus récente en haut.
 *
 * @var array $pages
 * @var list<string> $aEcrire  les lundis des semaines en entreprise sans page
 * @var string $cetteSemaine   le lundi de cette semaine
 * @var string $onglet
 * @var array|null $situation
 */
$csrf = Session::jetonCsrf();
$ecrite = in_array($cetteSemaine, array_column($pages, 'semaine'), true);
?>
<?= Vue::rendre('alternance/_onglets', ['onglet' => $onglet, 'situation' => $situation]) ?>

<div class="entete-page">
  <div>
    <h1>📓 Journal des missions</h1>
    <p>Chaque semaine, ce que vous avez fait et appris en entreprise. Le jour du
      livret ou du rapport, tout est déjà là.</p>
  </div>
  <span class="alternance-page__actions">
    <?php if ($pages !== []): ?>
      <a class="bouton bouton--secondaire" href="<?= url('alternance/journal/competences') ?>">🎯 Compétences</a>
    <?php endif; ?>
    <a class="bouton" href="<?= url('alternance/journal/semaine', ['semaine' => $cetteSemaine]) ?>" data-fenetre>
      <?= $ecrite ? 'Compléter cette semaine' : '+ Écrire cette semaine' ?>
    </a>
  </span>
</div>

<?php if ($pages !== []): ?>
  <?php
  /*
   * Le journal en PDF : c'est ce qu'on recopie dans le livret, ou qu'on joint
   * au rapport. Les dates sont facultatives — sans elles, tout sort — et
   * proposent d'avance la première et la dernière semaine écrites.
   */
  $premiere = (string) $pages[count($pages) - 1]['semaine'];
  $derniere = (new DateTimeImmutable((string) $pages[0]['semaine']))->modify('+4 days')->format('Y-m-d');
  ?>
  <details class="carte alternance-export">
    <summary class="alternance-export__ouvrir">⬇️ Exporter le journal en PDF</summary>
    <form method="get" action="<?= url('alternance/journal/pdf') ?>">
      <div class="ligne-champs">
        <div class="champ">
          <label for="du">Depuis</label>
          <input type="date" id="du" name="du" value="<?= e($premiere) ?>">
        </div>
        <div class="champ">
          <label for="au">Jusqu’au</label>
          <input type="date" id="au" name="au" value="<?= e($derniere) ?>">
        </div>
      </div>
      <button class="bouton" type="submit">Télécharger le PDF</button>
      <p class="champ__aide">Une semaine entamée sort en entier. Videz les dates pour tout prendre.
        Le PDF finit par le récapitulatif de vos compétences, de la plus travaillée à la moins travaillée.</p>
    </form>
  </details>
<?php endif; ?>

<?php if ($aEcrire !== []): ?>
  <div class="carte alternance-rappel">
    <strong>✍️ <?= count($aEcrire) ?> semaine<?= count($aEcrire) > 1 ? 's' : '' ?> en entreprise sans page :</strong>
    <?php foreach ($aEcrire as $lundi): ?>
      <a class="pastille" href="<?= url('alternance/journal/semaine', ['semaine' => $lundi]) ?>" data-fenetre>
        semaine du <?= e(Alternance::jourCourt($lundi)) ?>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($pages === []): ?>
  <div class="vide">
    <span class="vide__icone">📓</span>
    <p>Le journal est vide. Commencez par cette semaine : quelques lignes suffisent.</p>
  </div>
<?php else: ?>
  <div class="pile">
    <?php foreach ($pages as $p): ?>
      <article class="carte alternance-page">
        <header class="alternance-page__entete">
          <h2>Semaine du <?= e(Alternance::jourCourt((string) $p['semaine'])) ?></h2>
          <span class="alternance-page__actions">
            <a class="bouton bouton--discret bouton--petit"
               href="<?= url('alternance/journal/semaine', ['semaine' => $p['semaine']]) ?>" data-fenetre>Modifier</a>
            <form method="post" action="<?= url('alternance/journal/' . (int) $p['id'] . '/supprimer') ?>" class="en-ligne"
                  data-confirmation="Supprimer la page de la semaine du <?= e(Alternance::jourCourt((string) $p['semaine'])) ?> ?">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--discret bouton--petit" type="submit" title="Supprimer"
                      aria-label="Supprimer cette page">✕</button>
            </form>
          </span>
        </header>
        <?php if ($p['missions']): ?>
          <div class="texte-riche-affiche"><?= TexteRiche::versHtml($p['missions']) ?></div>
        <?php endif; ?>
        <?php $competences = Alternance::competences($p['competences']); ?>
        <?php if ($competences !== []): ?>
          <p class="alternance-competences">
            <span class="discret">Compétences :</span>
            <?php foreach ($competences as $c): ?>
              <span class="pastille"><?= e($c) ?></span>
            <?php endforeach; ?>
          </p>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
