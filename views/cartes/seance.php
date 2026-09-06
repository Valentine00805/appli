<?php
/**
 * Une séance : les cartes dues, une par une.
 *
 * Tout tient dans la page. Le script montre une carte, révèle la réponse, prend
 * le verdict et passe à la suivante ; le serveur n'est prévenu que du verdict.
 * Sans JavaScript, les cartes s'affichent simplement à la suite, question et
 * réponse visibles : on peut au moins les relire.
 *
 * @var array $cartes
 * @var ?array $cours  le cours, si la séance ne porte que sur lui
 */
$retour = $cours !== null ? url('cours/' . $cours['id'] . '/cartes') : url('cartes');
?>

<p><a href="<?= e($retour) ?>">← <?= $cours !== null ? e($cours['titre']) : 'Cartes' ?></a></p>

<?php if ($cartes === []): ?>
  <div class="vide">
    <span class="vide__icone">✅</span>
    <p>Rien à revoir<?= $cours !== null ? ' dans ce cours' : '' ?> pour aujourd'hui.</p>
    <a class="bouton bouton--secondaire" href="<?= e($retour) ?>">Retour</a>
  </div>
<?php else: ?>

  <div class="seance" data-seance data-jeton="<?= e(Session::jetonCsrf()) ?>" data-retour="<?= e($retour) ?>">
    <p class="seance__compteur" data-seance-compteur>
      <?= count($cartes) ?> carte<?= count($cartes) > 1 ? 's' : '' ?> à revoir
    </p>

    <?php foreach ($cartes as $rang => $c): ?>
      <section class="carte seance__carte" data-carte="<?= (int) $c['id'] ?>"
               data-url="<?= url('cartes/' . $c['id'] . '/reponse') ?>">
        <?php if ($cours === null): ?>
          <p class="seance__cours"><?= e($c['cours_titre']) ?></p>
        <?php endif; ?>

        <p class="seance__question"><?= e($c['question']) ?></p>

        <div class="seance__reponse" data-reponse><?= nl2br(e($c['reponse'])) ?></div>

        <div class="actions seance__actions">
          <button class="bouton" type="button" data-montrer>Voir la réponse</button>
          <button class="bouton bouton--secondaire" type="button" data-verdict="0" hidden>À revoir</button>
          <button class="bouton" type="button" data-verdict="1" hidden>Je la savais</button>
        </div>
      </section>
    <?php endforeach; ?>

    <div class="vide seance__fin" data-seance-fin hidden>
      <span class="vide__icone">✅</span>
      <p data-seance-bilan>Séance terminée.</p>
      <a class="bouton bouton--secondaire" href="<?= e($retour) ?>">Retour</a>
    </div>
  </div>

<?php endif; ?>
