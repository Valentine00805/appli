<?php
/**
 * Le contenu d'un paquet : ses cartes, et ce qu'on peut en faire.
 *
 * Le même fragment sert à la page d'un paquet et à l'onglet Cartes, où il se
 * déplie sous le paquet sans changer de page. « retour » dit où revenir après
 * une remise à zéro : sur l'onglet, on ne veut pas se retrouver ailleurs.
 *
 * @var array $cours   le cours dont c'est le paquet
 * @var array $cartes  ses cartes, rangées
 * @var ?string $retour  'onglet' pour rester sur l'onglet, null pour la page
 */
$retour = $retour ?? null;

/** D'où vient une carte, dit en clair. */
$provenance = static function (array $c): string {
    return match ($c['origine']) {
        'cours'   => t('crt.venue_cours'),
        'fiche'   => t('crt.venue_fiche'),
        'fichier' => $c['source'] !== ''
            ? t('crt.venue_fichier', ['nom' => $c['source']])
            : t('crt.venue_document'),
        'ia'      => t('crt.venue_ia'),
        default   => t('crt.ecrite_main'),
    };
};
?>

<?php if ($cartes === []): ?>
  <p class="discret"><?= e(t('crt.vide')) ?></p>
<?php else: ?>
  <ul class="paquet">
    <?php foreach ($cartes as $c): ?>
      <li class="carte-ligne">
        <details>
          <summary>
            <span class="carte-ligne__question"><?= e($c['question']) ?></span>
            <span class="carte-ligne__boite" title="<?= e(t('crt.boite_sur', ['n' => (int) $c['boite']])) ?>">
              <?= str_repeat('●', (int) $c['boite']) ?><span class="discret"><?= str_repeat('○', 5 - (int) $c['boite']) ?></span>
            </span>
          </summary>

          <form method="post" action="<?= url('cartes/' . $c['id'] . '/modifier') ?>" class="pile carte-ligne__edition">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <div class="champ">
              <label for="q-<?= (int) $c['id'] ?>"><?= e(t('crt.question')) ?></label>
              <input type="text" id="q-<?= (int) $c['id'] ?>" name="question"
                     value="<?= e($c['question']) ?>" maxlength="500">
            </div>
            <div class="champ">
              <label for="r-<?= (int) $c['id'] ?>"><?= e(t('crt.reponse')) ?></label>
              <textarea id="r-<?= (int) $c['id'] ?>" name="reponse" rows="3"><?= e($c['reponse']) ?></textarea>
            </div>
            <p class="champ__aide">
              <?= e(t('crt.venue_de', ['source' => $provenance($c)])) ?>
              <?php if ((int) $c['vues'] > 0): ?>
                <?= e(t('crt.revue_sue', ['revue' => (int) $c['vues'], 'sue' => (int) $c['reussies']])) ?>
              <?php endif; ?>
              <?= e(t('crt.prochaine_le', ['date' => date_fr((string) $c['revoir_le'], false)])) ?>
            </p>
            <div class="actions">
              <button class="bouton bouton--secondaire bouton--petit" type="submit"><?= e(t('commun.enregistrer')) ?></button>
            </div>
          </form>

          <form method="post" action="<?= url('cartes/' . $c['id'] . '/supprimer') ?>"
                data-confirmation="<?= e(t('crt.supprimer_sur')) ?>">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('commun.supprimer')) ?></button>
          </form>
        </details>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php
  /*
   * Reprendre à zéro ne supprime rien : les cartes restent, leur histoire
   * aussi. C'est l'échéancier qui repart, quand on veut refaire tout le tour
   * avant un examen plutôt que d'attendre son tour boîte par boîte.
   */
  ?>
  <section class="carte reprise">
    <div>
      <h3><?= e(t('crt.reprendre_zero')) ?></h3>
      <p class="champ__aide">
        <?= e(t('crt.reprendre_aide', ['n' => count($cartes)])) ?>
      </p>
    </div>
    <form method="post" action="<?= url('cours/' . $cours['id'] . '/cartes/rezero') ?>"
          data-confirmation="<?= e(t('fiche.rezero_sur', ['n' => count($cartes)])) ?>">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <?php if ($retour !== null): ?>
        <input type="hidden" name="retour" value="<?= e($retour) ?>">
      <?php endif; ?>
      <button class="bouton bouton--secondaire" type="submit"
              title="<?= e(t('fiche.rezero_aide')) ?>">
        <?= e(t('fiche.rezero')) ?>
      </button>
    </form>
  </section>

  <?php
  /*
   * Vider le paquet efface aussi ce qu'on savait de chaque carte : la boîte
   * où elle était montée, les fois où on l'a sue. Le bouton le dit, et la
   * confirmation le redit avec le compte.
   */
  ?>
  <section class="carte zone-danger">
    <div>
      <h3><?= e(t('crt.vider_paquet')) ?></h3>
      <p class="champ__aide">
        <?= e(t('crt.vider_aide', ['n' => count($cartes)])) ?>
      </p>
    </div>
    <form method="post" action="<?= url('cours/' . $cours['id'] . '/cartes/vider') ?>"
          data-confirmation="<?= e(t('crt.vider_sur', ['n' => count($cartes)])) ?>">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <button class="bouton bouton--danger" type="submit"><?= e(t('crt.supprimer_n', ['n' => count($cartes)])) ?></button>
    </form>
  </section>
<?php endif; ?>
