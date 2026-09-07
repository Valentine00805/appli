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

  <?= Vue::rendre('cartes/_seance', [
      'cartes' => $cartes, 'avecCours' => $cours === null, 'retour' => $retour,
  ]) ?>

<?php endif; ?>
