<?php
/**
 * Des points à cocher — ceux d'une tâche ou d'une liste. Tous les membres du groupe cochent, ajoutent et retirent.
 *
 * @var list<array{id: int, texte: string, fait: bool, fait_par: ?string}> $points
 * @var ?string $ajout    l'adresse où poster un nouveau point ; null : pas de formulaire d'ajout
 * @var string $envoi     attributs du formulaire quand on est dans une fenêtre (data-envoi-fenetre)
 * @var string $retour    « <input type="hidden" name="retour"> » pour revenir à la page d'où l'on vient (facultatif)
 * @var bool $compact     sur une carte du tableau : sans qui a coché, ni retrait
 * @var string $idBase    de quoi donner des identifiants uniques aux champs
 */
$envoi = $envoi ?? '';
$retour = $retour ?? '';
$compact = $compact ?? false;
$csrf = Session::jetonCsrf();
?>
<?php if ($points !== []): ?>
  <ul class="cochees<?= $compact ? ' cochees--compact' : '' ?>">
    <?php foreach ($points as $p): ?>
      <li class="coche<?= $p['fait'] ? ' coche--fait' : '' ?>">
        <form method="post"<?= $envoi ?> action="<?= url('travaux/points/' . $p['id'] . '/cocher') ?>" class="coche__case" data-auto-envoi data-garder-defilement>
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <?= $retour ?>
          <label class="coche__libelle">
            <input type="checkbox" name="fait" value="1"<?= $p['fait'] ? ' checked' : '' ?>>
            <span><?= e($p['texte']) ?></span>
          </label>
          <noscript><button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('tr.ta.ok')) ?></button></noscript>
        </form>
        <?php if (!$compact && $p['fait'] && $p['fait_par'] !== null): ?>
          <span class="coche__par discret">✓ <?= e($p['fait_par']) ?></span>
        <?php endif; ?>
        <?php if (!$compact): ?>
          <form method="post"<?= $envoi ?> action="<?= url('travaux/points/' . $p['id'] . '/supprimer') ?>" class="coche__retrait" data-garder-defilement>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <?= $retour ?>
            <button class="coche__retirer" type="submit" title="<?= e(t('tr.li2.retirer')) ?>"
                    aria-label="<?= e(t('tr.li2.retirer_point', ['texte' => $p['texte']])) ?>">×</button>
          </form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php if (($ajout ?? null) !== null): ?>
  <form method="post"<?= $envoi ?> action="<?= e($ajout) ?>" class="coche-ajout" data-garder-defilement>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <?= $retour ?>
    <label class="sr-only" for="<?= e($idBase) ?>"><?= e(t('tr.li2.ajouter_label')) ?></label>
    <input type="text" id="<?= e($idBase) ?>" name="texte" required maxlength="<?= Travaux::POINT_TEXTE_MAX ?>"
           placeholder="<?= e(t('tr.li2.ajouter_placeholder')) ?>" autocomplete="off">
    <button class="bouton bouton--secondaire bouton--petit" type="submit"><?= e(t('tr.li2.ajouter')) ?></button>
  </form>
<?php endif; ?>
