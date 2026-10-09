<?php
/**
 * Un évènement d'un calendrier partagé, en lecture : quand, où, qui l'a écrit, et de quoi le modifier ou le supprimer.
 *
 * @var array<string, mixed> $evenement  avec « calendrier » et « peut_modifier »
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$calendrier = $evenement['calendrier'];
$id = (int) $evenement['id'];
$journee = (int) $evenement['journee_entiere'] === 1;
$debut = new DateTimeImmutable((string) $evenement['debut']);
$fin = new DateTimeImmutable((string) $evenement['fin']);
$memeJour = $debut->format('Y-m-d') === $fin->format('Y-m-d');

if ($journee) {
    $quand = $memeJour
        ? t('evt.toute_la_journee', ['date' => ucfirst(date_fr((string) $evenement['debut'], false))])
        : t('evt.du_au', ['debut' => date_fr((string) $evenement['debut'], false), 'fin' => date_fr((string) $evenement['fin'], false)]);
} elseif ($memeJour) {
    $quand = t('evt.de_a', ['date' => ucfirst(date_fr((string) $evenement['debut'], false)), 'debut' => $debut->format('H:i'), 'fin' => $fin->format('H:i')]);
} else {
    $quand = t('evt.du_au', ['debut' => date_fr((string) $evenement['debut']), 'fin' => date_fr((string) $evenement['fin'])]);
}

/** Une ligne de la fiche, tue quand elle n'a rien à dire. */
$ligne = static function (string $etiquette, string $valeur): string {
    return trim(strip_tags($valeur)) === '' ? '' : '<div class="fiche__ligne"><span class="fiche__etiquette">' . e($etiquette)
        . '</span><span class="fiche__valeur">' . $valeur . '</span></div>';
};
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p class="discret" style="margin-bottom:.35rem">
        <a href="<?= url('calendrier', ['date' => $debut->format('Y-m-d')]) ?>"><?= e(t('evt.retour_calendrier')) ?></a>
      </p>
    <?php endif; ?>
    <h1 style="display:flex;align-items:center;gap:.6rem">
      <span class="fiche__teinte" style="background:<?= e($calendrier['couleur']) ?>"></span>
      👥 <?= e((string) $evenement['titre']) ?>
    </h1>
  </div>
  <div class="actions">
    <a class="bouton bouton--discret" href="<?= url('calendriers-amis/' . (int) $calendrier['id']) ?>" <?= $dansUneFenetre ? 'data-fenetre' : '' ?>>
      <?= e(t('cam.voir_calendrier')) ?>
    </a>
    <?php if ($evenement['peut_modifier']): ?>
      <a class="bouton" href="<?= url('calendriers-amis/evenements/' . $id . '/modifier') ?>" <?= $dansUneFenetre ? 'data-fenetre' : '' ?>>
        <?= e(t('commun.modifier')) ?>
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="pile"<?= $dansUneFenetre ? '' : ' style="max-width:44rem"' ?>>
  <section class="carte fiche">
    <?php
    echo $ligne(t('evt.quand'), e($quand));
    echo $ligne(t('evt.lieu'), e((string) ($evenement['lieu'] ?? '')));
    echo $ligne(t('cam.calendrier'), e((string) $calendrier['nom']) . ' · ' . e(tn('cam.membres_n', (int) $calendrier['membres'])));
    echo $ligne(t('cam.ecrit_par'), e((string) $evenement['auteur_pseudo']));
    ?>
    <?php if ((string) ($evenement['description'] ?? '') !== ''): ?>
      <div class="fiche__notes">
        <span class="fiche__etiquette"><?= e(t('evt.notes')) ?></span>
        <div class="texte-riche-affiche"><?= TexteRiche::versHtml((string) $evenement['description']) ?></div>
      </div>
    <?php endif; ?>
  </section>

  <?php if (($liensEvenement ?? null) !== null): ?>
    <?= Vue::rendre('travaux/_liens_evenement', $liensEvenement + ['dansUneFenetre' => $dansUneFenetre]) ?>
  <?php endif; ?>

  <?php if ($evenement['peut_modifier']): ?>
    <form method="post" action="<?= url('calendriers-amis/evenements/' . $id . '/supprimer') ?>"<?= $envoi ?>
          data-confirmation="<?= e(t('cam.evt_supprimer_confirmation', ['titre' => (string) $evenement['titre']])) ?>">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <button class="bouton bouton--danger" type="submit"><?= e(t('evt.supprimer')) ?></button>
    </form>
  <?php endif; ?>
</div>
