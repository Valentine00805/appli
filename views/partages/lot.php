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
      Partagé par <strong><?= e((string) ($lot['proprietaire'] !== '' ? $lot['proprietaire'] : 'un compte Mes Cours')) ?></strong>
      · <?= count($documents) ?> document<?= count($documents) > 1 ? 's' : '' ?>
      · lecture seule
    </p>
  </div>
</div>

<?php if ($documents === []): ?>
  <section class="carte vide">
    <span class="vide__icone">🔗</span>
    <p>Ces documents ne sont plus disponibles.</p>
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
  Ces documents viennent de <strong>Mes Cours</strong>. Avec un compte, ce qu’on vous partage
  se retrouve dans votre onglet « Partagés », et vous pouvez en faire vos propres copies.
  <a href="<?= url('connexion') ?>">Se connecter</a>
</p>
