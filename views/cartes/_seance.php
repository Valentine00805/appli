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
 * @var ?int $rezeroCours   le cours dont on peut remettre le paquet à zéro
 * @var int $rezeroTotal    combien de cartes il compte
 * @var ?string $rezeroRetour  où revenir ensuite : 'fiche', 'volet', ou null
 * @var int $duesEnTout  cartes dues en tout, séance plafonnée comprise
 * @var int $paquetTotal  combien de cartes compte le paquet entier
 * @var int $paquetSomme  la somme de leurs boîtes, d'où se tire l'anneau
 */
$avecCours = $avecCours ?? false;
$retour = $retour ?? null;
$rezeroCours = $rezeroCours ?? null;
$rezeroTotal = $rezeroTotal ?? 0;
$rezeroRetour = $rezeroRetour ?? null;
$duesEnTout = $duesEnTout ?? count($cartes);
$paquetTotal = $paquetTotal ?? 0;
$paquetSomme = $paquetSomme ?? 0;
?>
<div class="seance" data-seance data-jeton="<?= e(Session::jetonCsrf()) ?>">
  <div class="seance__entete">
    <p class="seance__compteur" data-seance-compteur>
      <?= e(tn('crt.a_revoir', count($cartes))) ?>
    </p>

    <?php if (count($cartes) > 1): ?>
      <?php
      /*
       * Mélanger ce qui reste à voir, sans toucher aux cartes déjà tranchées :
       * on révise mal quand on reconnaît une réponse à sa place dans la pile.
       */
      ?>
      <button class="bouton bouton--discret bouton--petit" type="button" data-melanger>
        <?= e(t('crt.melanger')) ?>
      </button>
    <?php endif; ?>

    <?php if ($rezeroCours !== null): ?>
      <?php
      /*
       * Remettre tout le paquet à revoir sans quitter la séance des yeux. Elle
       * repartira du début, ce que la demande de confirmation annonce.
       */
      ?>
      <?php // Depuis une fiche ouverte en fenêtre, la remise à zéro s'y enregistre. ?>
      <form<?= ($dansUneFenetre ?? false) ? ' data-envoi-fenetre' : '' ?> method="post" action="<?= url('cours/' . $rezeroCours . '/cartes/rezero') ?>"
            class="en-ligne"
            data-confirmation="<?= e(t('crt.rezero_sur_seance', ['n' => $rezeroTotal])) ?>">
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
        <?php if ($rezeroRetour !== null): ?>
          <input type="hidden" name="retour" value="<?= e($rezeroRetour) ?>">
        <?php endif; ?>
        <?php
        /*
         * Toujours cliquable : pendant une séance, les cartes tranchées partent
         * dans des boîtes supérieures, et l'état calculé au chargement mentirait
         * une minute plus tard. Si rien n'a à bouger, le serveur le dit.
         */
        ?>
        <button class="bouton bouton--discret bouton--petit" type="submit"
                title="<?= e(t('fiche.rezero_aide')) ?>">
          <?= e(t('crt.tout_remettre')) ?>
        </button>
      </form>
    <?php endif; ?>

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
        <span class="score__mot"><?= e(t('crt.compteur_a_revoir')) ?></span>
      </span>
      <span class="score__part score__part--su">
        <strong data-score-su>0</strong>
        <span aria-hidden="true">✓</span>
        <span class="score__mot"><?= e(t('crt.compteur_sues')) ?></span>
      </span>
    </p>

    <?php if ($paquetTotal > 0): ?>
      <?php
      /*
       * L'avancement du paquet entier, boîte moyenne ramenée sur cent. Il se lit
       * ici plutôt que dans le résumé de la fiche, qui se replie pendant la
       * séance ; le script le redessine à chaque verdict, la carte tranchée
       * changeant de boîte à l'instant même.
       */
      ?>
      <span class="seance__paquet" data-anneau-paquet
            data-total="<?= $paquetTotal ?>" data-somme="<?= $paquetSomme ?>">
        <?= Vue::rendre('cours/_anneau', [
            'pourcentage' => avancement_cartes($paquetTotal, $paquetSomme / $paquetTotal),
            'titre'       => t('js.cartes.avancement'),
        ]) ?>
        <span class="score__mot"><?= e(t('crt.du_paquet')) ?></span>
      </span>
    <?php endif; ?>
  </div>

  <?php if ($duesEnTout > count($cartes)): ?>
    <?php
    /*
     * Une séance s'arrête à un nombre tenable. Sans cette phrase, l'écart entre
     * « 42 cartes à revoir » sur la fiche et « 40 » ici passe pour une erreur.
     */
    ?>
    <p class="champ__aide seance__plafond">
      <?= e(t('crt.seance_de', [
          'n' => count($cartes), 'total' => $duesEnTout, 'reste' => $duesEnTout - count($cartes),
      ])) ?>
    </p>
  <?php endif; ?>

  <?php foreach ($cartes as $c): ?>
    <section class="carte seance__carte" data-carte="<?= (int) $c['id'] ?>"
             data-boite="<?= (int) ($c['boite'] ?? 1) ?>"
             data-url="<?= url('cartes/' . $c['id'] . '/reponse') ?>">
      <?php if ($avecCours): ?>
        <p class="seance__cours"><?= e((string) ($c['cours_titre'] ?? '')) ?></p>
      <?php endif; ?>

      <p class="seance__question"><?= e($c['question']) ?></p>

      <div class="seance__reponse" data-reponse><?= nl2br(e($c['reponse'])) ?></div>

      <div class="actions seance__actions">
        <?php // Revenir en arrière : caché sur la première carte, où il n'irait nulle part. ?>
        <button class="bouton bouton--discret bouton--petit" type="button"
                data-precedente title="<?= e(t('crt.precedente')) ?>" hidden>◀</button>
        <?php // Le même bouton montre et recache : on peut se reprendre avant de trancher. ?>
        <button class="bouton" type="button" data-montrer aria-expanded="false">
          <?= e(t('js.cartes.voir_reponse')) ?>
        </button>
        <button class="bouton bouton--secondaire" type="button" data-verdict="0" hidden><?= e(t('crt.a_revoir_bouton')) ?></button>
        <button class="bouton" type="button" data-verdict="1" hidden><?= e(t('crt.je_savais')) ?></button>
      </div>
    </section>
  <?php endforeach; ?>

  <div class="vide seance__fin" data-seance-fin hidden>
    <span class="vide__icone">✅</span>
    <p data-seance-bilan><?= e(t('crt.seance_terminee')) ?></p>

    <?php
    /*
     * Refaire le tour des mêmes cartes. Les verdicts comptent de nouveau :
     * savoir une carte deux fois de suite la fait monter deux fois, et c'est
     * bien ce qu'on veut dire en la revoyant.
     */
    ?>
    <p class="actions seance__fin-actions">
      <button class="bouton" type="button" data-recommencer><?= e(t('crt.recommencer')) ?></button>
      <?php if ($retour !== null): ?>
        <?php // Dans une fenêtre, « Retour » la ferme, et la page derrière se met à jour. ?>
        <a class="bouton bouton--secondaire" href="<?= e($retour) ?>"<?= ($dansUneFenetre ?? false) ? ' data-fermer' : '' ?>><?= e(t('crt.retour')) ?></a>
      <?php else: ?>
        <?php // Sur la fiche, on recharge : les compteurs doivent dire le vrai. ?>
        <button class="bouton bouton--secondaire" type="button" data-fermer-seance><?= e(t('crt.terminer')) ?></button>
      <?php endif; ?>
    </p>
  </div>
</div>
