<?php
/**
 * La liste des amis, avec le dernier message et les non-lus.
 *
 * @var list<array> $amis
 * @var array<int, array> $derniers  les derniers messages, par identifiant
 * @var ?int $actif  l'ami dont la conversation est ouverte
 */
$actif = $actif ?? null;
?>
<?php if ($amis === []): ?>
  <p class="discret" style="margin:0">Pas encore d’amis. Cherchez un pseudo pour envoyer une demande.</p>
<?php else: ?>
  <ul class="amis-liste">
    <?php foreach ($amis as $a): ?>
      <?php
      $dernier = $a['dernier_id'] !== null ? ($derniers[(int) $a['dernier_id']] ?? null) : null;
      $nonLus = (int) $a['non_lus'];
      $apercu = $dernier === null ? 'Dites bonjour 👋'
          : ((int) $dernier['expediteur_id'] === Auth::id() ? 'Vous : ' : '') . preg_replace('/\s+/u', ' ', (string) $dernier['texte']);
      ?>
      <li>
        <a class="ami<?= $actif === (int) $a['id'] ? ' ami--actif' : '' ?><?= $nonLus > 0 ? ' ami--non-lu' : '' ?>"
           href="<?= url('amis/' . (int) $a['id']) ?>"<?= $actif === (int) $a['id'] ? ' aria-current="page"' : '' ?>>
          <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $a['pseudo'], 0, 1))) ?></span>
          <span class="ami__texte">
            <span class="ami__ligne">
              <span class="ami__pseudo"><?= e((string) $a['pseudo']) ?></span>
              <?php if ($dernier !== null): ?>
                <span class="ami__quand"><?= e(Amis::quand((string) $dernier['created_at'])) ?></span>
              <?php endif; ?>
            </span>
            <span class="ami__ligne">
              <span class="ami__apercu"><?= e(mb_strimwidth($apercu, 0, 80, '…')) ?></span>
              <?php if ($nonLus > 0): ?>
                <span class="compteur" title="<?= $nonLus ?> message<?= $nonLus > 1 ? 's' : '' ?> non lu<?= $nonLus > 1 ? 's' : '' ?>"><?= $nonLus > 99 ? '99+' : $nonLus ?></span>
              <?php endif; ?>
            </span>
          </span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
