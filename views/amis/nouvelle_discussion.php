<?php
/**
 * « Nouveau message » : choisir un ami pour lui écrire, ou créer un groupe.
 *
 * @var list<array> $amis
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
?>
<div class="entete-page">
  <div>
    <h1 style="margin:0">Nouveau message</h1>
    <p class="discret" style="margin:.15rem 0 0">À qui voulez-vous écrire ?</p>
  </div>
</div>

<div class="carte nouvelle-discussion">
  <a class="ami nouvelle-discussion__groupe" href="<?= url('groupes/nouveau') ?>" data-fenetre>
    <span class="avatar avatar--groupe" aria-hidden="true">👥</span>
    <span class="ami__texte"><span class="ami__pseudo">Créer un groupe</span><span class="ami__apercu">Une discussion à plusieurs</span></span>
  </a>

  <?php if ($amis === []): ?>
    <p class="discret" style="margin:.75rem 0 0">Pas encore d’amis. <a href="<?= url('amis') ?>">Chercher un pseudo</a></p>
  <?php else: ?>
    <label class="discussions-recherche" style="margin-top:.75rem">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
        <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
      </svg>
      <span class="sr-only">Rechercher un ami</span>
      <input type="search" placeholder="Rechercher un ami" autocomplete="off" autofocus data-filtre-liste="[data-liste-nouveau]">
    </label>
    <p class="legende" style="margin:.75rem 0 .25rem">Amis</p>
    <ul class="amis-liste" data-liste-nouveau>
      <?php foreach ($amis as $a): ?>
        <li data-nom="<?= e(mb_strtolower((string) $a['pseudo'])) ?>">
          <a class="ami" href="<?= url('amis/' . (int) $a['id']) ?>">
            <?= Amis::avatar((int) $a['id'], (string) $a['pseudo']) ?>
            <span class="ami__texte"><span class="ami__pseudo"><?= e((string) $a['pseudo']) ?></span></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="discret" data-filtre-vide hidden style="margin:.5rem 0 0">Aucun ami ne correspond.</p>
    <p class="discret" style="margin:.75rem 0 0"><a href="<?= url('amis') ?>">Trouver d’autres personnes par leur pseudo</a></p>
  <?php endif; ?>
</div>
