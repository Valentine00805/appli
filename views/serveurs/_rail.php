<?php
/**
 * Le côté d'un serveur, à gauche d'un salon : son nom et ses réglages, ses salons, ses membres.
 *
 * @var array $serveur  id, nom, photo_nom, role
 * @var list<array{id: int, nom: string, non_lus: int}> $salons
 * @var list<array{id: int, pseudo: string, role: string}> $membresServeur
 * @var int $salonActif
 */
$moi = Auth::id();
$gere = Serveurs::gere((string) $serveur['role']);
?>
<aside class="carte chat__amis chat__serveur" aria-label="<?= e(t('srv.aside', ['nom' => (string) $serveur['nom']])) ?>">
  <p class="serveur-retour"><a href="<?= url('serveurs') ?>"><?= e(t('srv.retour')) ?></a></p>
  <div class="serveur-entete">
    <?= Serveurs::pastille((string) $serveur['nom'], Serveurs::adressePhoto((int) $serveur['id'], $serveur['photo_nom']), 'serveur-icone', $serveur['couleur']) ?>
    <strong class="serveur-entete__nom"><?= e((string) $serveur['nom']) ?></strong>
    <a class="bouton bouton--discret bouton--petit" href="<?= url('serveurs/' . (int) $serveur['id'] . '/reglages') ?>" data-fenetre
       title="<?= e(t('srv.reglages')) ?>" aria-label="<?= e(t('srv.reglages')) ?>">⚙️</a>
  </div>

  <h2 class="serveur-sous-titre"><?= e(t('srv.salons')) ?>
    <?php if ($gere): ?>
      <a class="serveur-sous-titre__ajout" href="<?= url('serveurs/' . (int) $serveur['id'] . '/reglages') ?>#salons" data-fenetre
         title="<?= e(t('srv.ajouter_salon')) ?>" aria-label="<?= e(t('srv.ajouter_salon')) ?>">＋</a>
    <?php endif; ?>
  </h2>
  <ul class="serveur-salons">
    <?php foreach ($salons as $s): ?>
      <li>
        <a href="<?= url('groupes/' . (int) $s['id']) ?>"<?= (int) $s['id'] === $salonActif ? ' aria-current="page"' : '' ?>
           class="<?= $s['non_lus'] > 0 && (int) $s['id'] !== $salonActif ? 'serveur-salons__non-lu' : '' ?>">
          <span class="serveur-salons__nom"># <?= e($s['nom']) ?></span>
          <?php if ($s['non_lus'] > 0 && (int) $s['id'] !== $salonActif): ?>
            <span class="compteur"><?= $s['non_lus'] > 99 ? '99+' : $s['non_lus'] ?></span>
          <?php endif; ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

  <h2 class="serveur-sous-titre"><?= e(t('srv.membres')) ?> <span class="discret"><?= count($membresServeur) ?></span></h2>
  <ul class="serveur-membres">
    <?php foreach ($membresServeur as $m): ?>
      <li>
        <?= Amis::avatar($m['id'], $m['pseudo']) ?>
        <span class="serveur-membres__nom"><?= e($m['pseudo']) ?><?= $m['id'] === $moi ? ' <span class="discret">' . e(t('grp.vous')) . '</span>' : '' ?></span>
        <?php if ($m['role'] === 'proprietaire'): ?>
          <span class="pastille" title="<?= e(t('srv.role_proprietaire')) ?>">👑</span>
        <?php elseif ($m['role'] === 'admin'): ?>
          <span class="pastille" title="<?= e(t('srv.role_admin')) ?>">🛡️</span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</aside>
