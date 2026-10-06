<?php
/**
 * Les salons d'un serveur, dans leur propre fenêtre : la liste, renommer, supprimer, en ajouter. Ouverte par le « ＋ » de la colonne
 * des salons ; les administrateurs y règlent, les autres n'y lisent que la liste. Les formulaires restent dans la fenêtre.
 *
 * @var array $serveur
 * @var bool $gere
 * @var list<array{id: int, nom: string, non_lus: int}> $salons
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$id = (int) $serveur['id'];
$csrf = Session::jetonCsrf();
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$base = 'serveurs/' . $id;
?>
<div class="entete-page profil-ami"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div class="profil-ami__identite">
    <?= Serveurs::pastille((string) $serveur['nom'], Serveurs::adressePhoto($id, $serveur['photo_nom']), 'serveur-icone serveur-icone--grand', $serveur['couleur']) ?>
    <div>
      <?php if (!$dansUneFenetre): ?>
        <p class="discret" style="margin:0 0 .2rem"><a href="<?= url($base) ?>"><?= e(t('srv.retour_serveur')) ?></a></p>
      <?php endif; ?>
      <h1 style="margin:0"><?= e(t('srv.salons')) ?></h1>
      <p class="discret" style="margin:.15rem 0 0"><?= e((string) $serveur['nom']) ?> · <?= e(tn('srv.salons_n', count($salons))) ?></p>
    </div>
  </div>
</div>

<section class="carte profil-ami__section" id="salons">
  <?php // Chaque salon se lit d'abord ; « Modifier » ouvre son champ, qui s'enregistre ou s'annule. ?>
  <ul class="serveur-salons-gestion">
    <?php foreach ($salons as $s): ?>
      <li class="serveur-salon" data-reglage>
        <div class="reglage-lecture" data-reglage-lecture>
          <span class="groupe-membres__nom"><a href="<?= url('groupes/' . (int) $s['id']) ?>"># <?= e($s['nom']) ?></a></span>
          <?php if ($gere): ?>
            <span class="groupe-membres__actions">
              <button class="bouton bouton--secondaire bouton--petit" type="button" data-reglage-modifier><?= e(t('commun.modifier')) ?></button>
              <?php if (count($salons) > 1): ?>
                <form method="post" action="<?= url($base . '/salons/' . (int) $s['id'] . '/supprimer') ?>"<?= $envoi ?>
                      data-confirmation="<?= e(t('srv.supprimer_salon_confirmation', ['nom' => $s['nom']])) ?>">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('srv.supprimer')) ?></button>
                </form>
              <?php endif; ?>
            </span>
          <?php endif; ?>
        </div>
        <?php if ($gere): ?>
          <form method="post" action="<?= url($base . '/salons/' . (int) $s['id'] . '/renommer') ?>" data-reglage-edition hidden<?= $envoi ?>>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <label class="legende" for="salon-nom-<?= (int) $s['id'] ?>"><?= e(t('srv.renommer_salon')) ?></label>
            <div class="fuseau-choix">
              <input type="text" id="salon-nom-<?= (int) $s['id'] ?>" name="nom" required maxlength="<?= Serveurs::SALON_MAX ?>"
                     value="<?= e($s['nom']) ?>" data-valeur-actuelle="<?= e($s['nom']) ?>" autocomplete="off">
              <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
              <button class="bouton bouton--discret" type="button" data-reglage-annuler><?= e(t('commun.annuler')) ?></button>
            </div>
          </form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if ($gere && count($salons) < Serveurs::SALONS_MAX): ?>
    <h3 class="groupe-sous-titre"><?= e(t('srv.ajouter_salon')) ?></h3>
    <form method="post" action="<?= url($base . '/salons') ?>" class="fuseau-choix"<?= $envoi ?>>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <label class="sr-only" for="salon-nouveau"><?= e(t('srv.ajouter_salon')) ?></label>
      <input type="text" id="salon-nouveau" name="nom" required maxlength="<?= Serveurs::SALON_MAX ?>" placeholder="<?= e(t('srv.salon_exemple')) ?>" autocomplete="off">
      <button class="bouton" type="submit"><?= e(t('srv.ajouter_salon')) ?></button>
    </form>
    <p class="champ__aide"><?= e(t('srv.salon_aide_nom')) ?></p>
  <?php endif; ?>
</section>