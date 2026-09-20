<?php
/**
 * Ce que le journal dit de vos compétences : lesquelles reviennent, lesquelles
 * n'ont été vues qu'une fois. C'est ce qu'on recopie dans le livret.
 *
 * @var list<array{nom: string, semaines: list<string>}> $competences
 * @var int $semaines  le nombre de pages du journal
 * @var string $onglet
 * @var array|null $situation
 */
$plusVue = $competences === [] ? 0 : count($competences[0]['semaines']);
?>
<?= Vue::rendre('alternance/_onglets', ['onglet' => $onglet, 'situation' => $situation]) ?>

<div class="entete-page">
  <div>
    <p style="margin:0 0 .3rem"><a href="<?= url('alternance/journal') ?>">← Tout le journal</a></p>
    <h1>🎯 Compétences travaillées</h1>
    <p>D’après vos <?= (int) $semaines ?> semaine<?= $semaines > 1 ? 's' : '' ?> de journal.
      Celles du bas sont celles que vous n’avez vues qu’une fois : de quoi savoir quoi demander à votre tuteur.</p>
  </div>
</div>

<?php if ($competences === []): ?>
  <div class="vide">
    <span class="vide__icone">🎯</span>
    <p>Aucune compétence notée pour l’instant. Ajoutez-en au bas d’une semaine du journal,
      séparées par des virgules.</p>
    <p><a class="bouton bouton--secondaire" href="<?= url('alternance/journal') ?>">Aller au journal</a></p>
  </div>
<?php else: ?>
  <section class="carte">
    <ul class="alternance-competence-liste">
      <?php foreach ($competences as $c): ?>
        <?php $n = count($c['semaines']); ?>
        <li class="alternance-competence">
          <span class="alternance-competence__nom"><?= e($c['nom']) ?></span>
          <span class="alternance-competence__barre" aria-hidden="true">
            <span style="width:<?= $plusVue === 0 ? 0 : (int) round($n / $plusVue * 100) ?>%"></span>
          </span>
          <span class="alternance-competence__compte"><?= $n ?> semaine<?= $n > 1 ? 's' : '' ?></span>
          <span class="alternance-competence__semaines">
            <?php foreach (array_slice($c['semaines'], 0, 6) as $lundi): ?>
              <a class="pastille" href="<?= url('alternance/journal/semaine', ['semaine' => $lundi]) ?>" data-fenetre>
                <?= e(Alternance::jourCourt($lundi)) ?>
              </a>
            <?php endforeach; ?>
            <?php if ($n > 6): ?><span class="discret">+<?= $n - 6 ?></span><?php endif; ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>
