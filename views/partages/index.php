<?php
/**
 * L'onglet « Partagés », en deux volets : ce que mes amis m'ont partagé, et ce
 * que je partage.
 *
 * Rien n'est copié ici : chaque ligne mène au document tel qu'il est
 * aujourd'hui chez son propriétaire, et disparaît avec lui.
 *
 * @var string $vue           recus ou envoyes
 * @var list<array> $recus    ce qu'on m'a partagé, du plus récent au plus ancien
 * @var list<array> $envoyes  ce que je partage, et avec qui
 */
$csrf = Session::jetonCsrf();
$lesRecus = $vue === 'recus';
?>
<div class="entete-page">
  <div>
    <h1><?= Partages::icone(22) ?> <?= $lesRecus ? 'Partagés avec moi' : 'Ce que je partage' ?></h1>
    <p>
      <?php if ($lesRecus): ?>
        <?= $recus === [] ? 'Rien pour l’instant' : count($recus) . ' document' . (count($recus) > 1 ? 's' : '') . ' en lecture, toujours à jour' ?>
      <?php else: ?>
        <?= $envoyes === [] ? 'Rien pour l’instant' : count($envoyes) . ' document' . (count($envoyes) > 1 ? 's' : '') . ' ouverts à d’autres' ?>
      <?php endif; ?>
    </p>
  </div>
  <?php // Ce volet ne se lit que : rien à faire en tête de page. ?>
  <?php if (!$lesRecus): ?>
    <div class="actions">
      <a class="bouton bouton-partage" href="<?= url('partager/plusieurs') ?>"
         data-fenetre title="Partager plusieurs cours ou fichiers en un envoi"><?= Partages::icone() ?> Partager plusieurs</a>
    </div>
  <?php endif; ?>
</div>

<?= Vue::rendre('partages/_onglets', ['vue' => $vue, 'nbRecus' => count($recus), 'nbEnvoyes' => count($envoyes)]) ?>

<?php if ($lesRecus): ?>

  <?php if ($recus === []): ?>
    <div class="carte vide">
      <span class="vide__icone">📥</span>
      <p>Personne ne vous a encore partagé de document.</p>
      <p class="discret">
        Un ami qui partage un cours, une fiche de révision, un dossier ou un fichier le fait
        paraître ici, et vous l’envoie aussi dans votre discussion.
      </p>
    </div>
  <?php else: ?>
    <section class="carte">
      <label class="discussions-recherche">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
          <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
        </svg>
        <span class="sr-only">Rechercher un document partagé</span>
        <input type="search" placeholder="Rechercher" autocomplete="off" data-filtre-liste="[data-liste-partages]">
      </label>

      <ul class="partage-lignes" data-liste-partages>
        <?php foreach ($recus as $p): ?>
          <li class="partage-ligne" data-nom="<?= e($p['titre'] . ' ' . $p['proprietaire']) ?>">
            <a class="partage-ligne__lien" href="<?= e($p['url']) ?>" data-fenetre>
              <span class="partage-ligne__icone" aria-hidden="true"><?= e($p['icone']) ?></span>
              <span class="partage-ligne__texte">
                <span class="partage-ligne__titre"><?= e($p['titre']) ?></span>
                <span class="partage-ligne__detail">
                  <?= e(Partages::libelle($p['type'])) ?><?= $p['detail'] === '' ? '' : ' · ' . e($p['detail']) ?>
                </span>
                <span class="partage-ligne__qui">
                  <?= Amis::avatar($p['proprietaire_id'], $p['proprietaire'], 'avatar--mini') ?>
                  <?= e($p['proprietaire']) ?> · <?= e(date_fr(Amis::local($p['quand'])->format('Y-m-d H:i:s'), false)) ?>
                </span>
              </span>
            </a>
            <form method="post" action="<?= url('partages/' . Partages::mot($p['type']) . '/' . (int) $p['id'] . '/oublier') ?>"
                  data-confirmation="Retirer « <?= e($p['titre']) ?> » de votre liste ? Il faudra qu’on vous le partage de nouveau pour le revoir.">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--discret bouton--petit" type="submit">Retirer</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="discret" data-filtre-vide hidden style="margin:.6rem 0 0">Aucun document ne porte ce nom.</p>
    </section>
  <?php endif; ?>

<?php else: ?>

  <?php // L'autre sens : ce que je partage, et où le reprendre en main. ?>
  <?php if ($envoyes === []): ?>
    <div class="carte vide">
      <span class="vide__icone">📤</span>
      <p>Vous ne partagez rien pour l’instant.</p>
      <p class="discret">
        Le bouton « Partager » est sur la page d’un cours, d’une fiche de révision,
        d’un dossier et de chaque fichier joint. Vous choisissez alors des amis, un groupe,
        ou un lien public pour ceux qui n’ont pas de compte.
      </p>
    </div>
  <?php else: ?>
    <section class="carte">
      <label class="discussions-recherche">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
          <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
        </svg>
        <span class="sr-only">Rechercher un de mes partages</span>
        <input type="search" placeholder="Rechercher" autocomplete="off" data-filtre-liste="[data-liste-envoyes]">
      </label>

      <ul class="partage-lignes" data-liste-envoyes>
        <?php foreach ($envoyes as $p): ?>
          <li class="partage-ligne" data-nom="<?= e((string) $p['titre']) ?>">
            <a class="partage-ligne__lien" href="<?= e($p['gerer']) ?>" data-fenetre>
              <span class="partage-ligne__icone" aria-hidden="true"><?= e($p['icone']) ?></span>
              <span class="partage-ligne__texte">
                <span class="partage-ligne__titre"><?= e($p['titre']) ?></span>
                <span class="partage-ligne__detail">
                  <?= e(Partages::libelle($p['type'])) ?>
                  · <?= $p['destinataires'] === 0 ? 'aucun ami' : $p['destinataires'] . ' ami' . ($p['destinataires'] > 1 ? 's' : '') ?>
                  <?php if ($p['lien']): ?> · lien public (<?= (int) $p['vues'] ?> ouverture<?= (int) $p['vues'] > 1 ? 's' : '' ?>)<?php endif; ?>
                </span>
              </span>
            </a>
            <span class="discret" style="font-size:.85rem">Gérer</span>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="discret" data-filtre-vide hidden style="margin:.6rem 0 0">Aucun document ne porte ce nom.</p>
    </section>
  <?php endif; ?>

<?php endif; ?>
