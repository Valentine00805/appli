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
    <h1><?= Partages::icone(22) ?> <?= e(t($lesRecus ? 'pt.recus_titre' : 'pt.envoyes_titre')) ?></h1>
    <p>
      <?php if ($lesRecus): ?>
        <?= e($recus === [] ? t('pt.rien_pour_instant') : tn('pt.recus_compte', count($recus))) ?>
      <?php else: ?>
        <?= e($envoyes === [] ? t('pt.rien_pour_instant') : tn('pt.envoyes_compte', count($envoyes))) ?>
      <?php endif; ?>
    </p>
  </div>
  <?php // Ce volet ne se lit que : rien à faire en tête de page. ?>
  <?php if (!$lesRecus): ?>
    <div class="actions">
      <a class="bouton bouton-partage" href="<?= url('partager/plusieurs') ?>"
         data-fenetre title="<?= e(t('pt.plusieurs_titre_aide')) ?>"><?= Partages::icone() ?> <?= e(t('pt.plusieurs_titre')) ?></a>
    </div>
  <?php endif; ?>
</div>

<?= Vue::rendre('partages/_onglets', ['vue' => $vue, 'nbRecus' => count($recus), 'nbEnvoyes' => count($envoyes)]) ?>

<?php if ($lesRecus): ?>

  <?php if ($recus === []): ?>
    <div class="carte vide">
      <span class="vide__icone">📥</span>
      <p><?= e(t('pt.aucun_recu')) ?></p>
      <p class="discret">
        <?= e(t('pt.aucun_recu_aide')) ?>
      </p>
    </div>
  <?php else: ?>
    <section class="carte">
      <label class="discussions-recherche">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
          <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
        </svg>
        <span class="sr-only"><?= e(t('pt.chercher_partage')) ?></span>
        <input type="search" placeholder="<?= e(t('pt.chercher')) ?>" autocomplete="off" data-filtre-liste="[data-liste-partages]">
      </label>

      <ul class="partage-lignes" data-liste-partages>
        <?php foreach ($recus as $p): ?>
          <li class="partage-ligne" data-nom="<?= e($p['titre'] . ' ' . $p['proprietaire']) ?>">
            <a class="partage-ligne__lien" href="<?= e($p['url']) ?>" data-fenetre>
              <span class="partage-ligne__icone" aria-hidden="true"><?= e($p['icone']) ?></span>
              <span class="partage-ligne__texte">
                <span class="partage-ligne__titre">
                  <?= e($p['titre']) ?>
                  <?php if (!empty($p['nouveau'])): ?><span class="pastille pastille--nouveau"><?= e(t('pt.nouveau')) ?></span><?php endif; ?>
                </span>
                <span class="partage-ligne__detail">
                  <?= e(Partages::libelle($p['type'])) ?>
                  · <?= e(mb_strtolower(Partages::libelleDroit($p['droit']))) ?><?= $p['detail'] === '' ? '' : ' · ' . e($p['detail']) ?>
                </span>
                <?php if (($p['message'] ?? '') !== ''): ?>
                  <span class="partage-ligne__mot">« <?= e(mb_strimwidth((string) $p['message'], 0, 140, '…')) ?> »</span>
                <?php endif; ?>
                <span class="partage-ligne__qui">
                  <?= Amis::avatar($p['proprietaire_id'], $p['proprietaire'], 'avatar--mini') ?>
                  <?= e($p['proprietaire']) ?> · <?= e(date_fr(Amis::local($p['quand'])->format('Y-m-d H:i:s'), false)) ?>
                </span>
              </span>
            </a>
            <form method="post" action="<?= url('partages/' . Partages::mot($p['type']) . '/' . (int) $p['id'] . '/oublier') ?>"
                  data-confirmation="<?= e(t('pt.oublier_un_confirmation', ['titre' => $p['titre']])) ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('pt.retirer')) ?></button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="discret" data-filtre-vide hidden style="margin:.6rem 0 0"><?= e(t('pt.aucun_doc_nom')) ?></p>
    </section>
  <?php endif; ?>

<?php else: ?>

  <?php // L'autre sens : ce que je partage, et où le reprendre en main. ?>
  <?php if ($envoyes === []): ?>
    <div class="carte vide">
      <span class="vide__icone">📤</span>
      <p><?= e(t('pt.rien_partage')) ?></p>
      <p class="discret">
        <?= e(t('pt.rien_partage_aide')) ?>
      </p>
    </div>
  <?php else: ?>
    <section class="carte">
      <label class="discussions-recherche">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
          <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
        </svg>
        <span class="sr-only"><?= e(t('pt.chercher_mes_partages')) ?></span>
        <input type="search" placeholder="<?= e(t('pt.chercher')) ?>" autocomplete="off" data-filtre-liste="[data-liste-envoyes]">
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
                  · <?= e($p['destinataires'] === 0 ? t('pt.aucun_ami') : tn('pt.amis_nb', (int) $p['destinataires'])) ?>
                  <?php if ($p['lien']): ?> · <?= e(tn('pt.lien_public_vues', (int) $p['vues'])) ?><?php endif; ?>
                </span>
              </span>
            </a>
            <span class="discret" style="font-size:.85rem"><?= e(t('pt.gerer')) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="discret" data-filtre-vide hidden style="margin:.6rem 0 0"><?= e(t('pt.aucun_doc_nom')) ?></p>
    </section>
  <?php endif; ?>

<?php endif; ?>
