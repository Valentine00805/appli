<?php
/**
 * Le déroulé d'une séance, sans son enveloppe.
 *
 * Le même fragment sert à la page « Réviser » et à la fiche de révision, où il
 * s'ouvre sur place. Toutes les cartes sont dans le document dès le départ : le
 * script n'en montre qu'une à la fois, dévoile la réponse à la demande, envoie
 * le verdict et passe à la suivante. Sans lui, elles s'affichent simplement à
 * la suite, question et réponse visibles — on peut au moins les relire.
 *
 * @var array $cartes    les cartes dues
 * @var bool $avecCours  vrai quand la séance mêle plusieurs cours, et qu'il
 *                       faut donc dire d'où vient chaque carte
 * @var ?string $retour  l'adresse du bouton de fin ; null pour rester sur place
 */
$avecCours = $avecCours ?? false;
$retour = $retour ?? null;
?>
<div class="seance" data-seance data-jeton="<?= e(Session::jetonCsrf()) ?>">
  <div class="seance__entete">
    <p class="seance__compteur" data-seance-compteur>
      <?= count($cartes) ?> carte<?= count($cartes) > 1 ? 's' : '' ?> à revoir
    </p>

    <?php
    /*
     * Le compte des verdicts donnés, qui monte au fil de la séance. Il part de
     * zéro à chaque fois : c'est le score du moment, pas un historique — celui-ci
     * se lit carte par carte dans le paquet.
     */
    ?>
    <p class="score" role="status">
      <span class="score__part score__part--rate">
        <span aria-hidden="true">✕</span>
        <strong data-score-rate>0</strong>
        <span class="score__mot">à revoir</span>
      </span>
      <span class="score__part score__part--su">
        <strong data-score-su>0</strong>
        <span aria-hidden="true">✓</span>
        <span class="score__mot">sues</span>
      </span>
    </p>
  </div>

  <?php foreach ($cartes as $c): ?>
    <section class="carte seance__carte" data-carte="<?= (int) $c['id'] ?>"
             data-url="<?= url('cartes/' . $c['id'] . '/reponse') ?>">
      <?php if ($avecCours): ?>
        <p class="seance__cours"><?= e((string) ($c['cours_titre'] ?? '')) ?></p>
      <?php endif; ?>

      <p class="seance__question"><?= e($c['question']) ?></p>

      <div class="seance__reponse" data-reponse><?= nl2br(e($c['reponse'])) ?></div>

      <div class="actions seance__actions">
        <?php // Le même bouton montre et recache : on peut se reprendre avant de trancher. ?>
        <button class="bouton" type="button" data-montrer aria-expanded="false">
          Voir la réponse
        </button>
        <button class="bouton bouton--secondaire" type="button" data-verdict="0" hidden>À revoir</button>
        <button class="bouton" type="button" data-verdict="1" hidden>Je la savais</button>
      </div>
    </section>
  <?php endforeach; ?>

  <div class="vide seance__fin" data-seance-fin hidden>
    <span class="vide__icone">✅</span>
    <p data-seance-bilan>Séance terminée.</p>
    <?php if ($retour !== null): ?>
      <a class="bouton bouton--secondaire" href="<?= e($retour) ?>">Retour</a>
    <?php else: ?>
      <?php // Sur la fiche, on recharge : les compteurs doivent dire le vrai. ?>
      <button class="bouton bouton--secondaire" type="button" data-fermer-seance>Terminer</button>
    <?php endif; ?>
  </div>
</div>
