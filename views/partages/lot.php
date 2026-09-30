<?php
/**
 * Un lien public qui montre plusieurs documents : la page d'accueil du lot.
 *
 * Elle ne demande pas de compte : chaque ligne mène au document, tel qu'il est
 * aujourd'hui chez son propriétaire.
 *
 * @var array $lot
 * @var list<array> $documents  ce que le lot montre, dans l'ordre choisi
 * @var callable $adresse       (string $type, int $id): string
 */
?>
<div class="entete-page">
  <div>
    <h1><?= Partages::icone(22) ?> <?= e((string) $lot['titre']) ?></h1>
    <p class="discret">
      <?= e(t('pt.partage_par_qui')) ?> <strong><?= e((string) ($lot['proprietaire'] !== '' ? $lot['proprietaire'] : t('pt.un_compte'))) ?></strong>
      · <?= e(tn('pt.combien_document', count($documents))) ?>
      · <?= e(t('pt.lecture_seule')) ?>
    </p>
  </div>
</div>

<?php if ($documents === []): ?>
  <section class="carte vide">
    <span class="vide__icone">🔗</span>
    <p><?= e(t('pt.lot_vide')) ?></p>
  </section>
<?php else: ?>
  <section class="carte">
    <ul class="partage-lignes">
      <?php foreach ($documents as $d): ?>
        <li class="partage-ligne">
          <a class="partage-ligne__lien" href="<?= e($adresse((string) $d['type'], (int) $d['id'])) ?>"
             <?= $d['type'] === 'fichier' ? 'target="_blank" rel="noopener"' : '' ?>>
            <span class="partage-ligne__icone" aria-hidden="true"><?= e((string) $d['icone']) ?></span>
            <span class="partage-ligne__texte">
              <span class="partage-ligne__titre"><?= e((string) $d['titre']) ?></span>
              <span class="partage-ligne__detail"><?= e((string) $d['detail']) ?></span>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<p class="discret partage-invitation">
  <?= e(t('pt.lot_invitation', ['appli' => (string) Config::get('app', 'nom')])) ?>
  <a href="<?= url('connexion') ?>"><?= e(t('pt.se_connecter')) ?></a>
</p>
