<?php
/**
 * La liste des discussions — amis et groupes mêlés, de la plus récente à la
 * plus ancienne —, avec la dernière ligne et les non-lus.
 *
 * @var list<array> $amis
 * @var array<int, array> $derniers  les derniers messages entre amis, par identifiant
 * @var list<array> $groupes
 * @var array<int, array> $derniersGroupes  les dernières lignes des groupes, par identifiant
 * @var ?int $actif  l'ami dont la conversation est ouverte
 * @var ?int $groupeActif  le groupe dont la conversation est ouvert
 */
$actif = $actif ?? null;
$groupeActif = $groupeActif ?? null;
$groupes = $groupes ?? [];
$derniersGroupes = $derniersGroupes ?? [];
$moi = Auth::id();

$discussions = [];
foreach ($amis as $a) {
    $dernier = $a['dernier_id'] !== null ? ($derniers[(int) $a['dernier_id']] ?? null) : null;
    $apercu = $dernier === null ? 'Dites bonjour 👋'
        : (($dernier['evenement'] ?? null) !== null
          ? Amis::texteEvenement((string) $dernier['evenement'], (int) $dernier['expediteur_id'] === $moi, (string) $a['pseudo'])
        : (($dernier['supprime_le'] ?? null) !== null ? '🚫 Message supprimé'
        : ((int) $dernier['expediteur_id'] === $moi ? 'Vous : ' : '')
          . (($dernier['image_nom'] ?? null) !== null ? '📷 Photo' . ((string) $dernier['texte'] !== '' ? ' · ' : '') : '')
          . (($dernier['fichier_origine'] ?? null) !== null ? '📎 ' . $dernier['fichier_origine'] . ((string) $dernier['texte'] !== '' ? ' · ' : '') : '')
          . (($dernier['audio_nom'] ?? null) !== null ? '🎤 Message vocal' . ((string) $dernier['texte'] !== '' ? ' · ' : '') : '')
          . (($dernier['partage_type'] ?? null) !== null ? '🔗 ' . Partages::libelle((string) $dernier['partage_type']) . ((string) $dernier['texte'] !== '' ? ' · ' : '') : '')
          . preg_replace('/\s+/u', ' ', (string) $dernier['texte'])));
    $discussions[] = [
        'url' => url('amis/' . (int) $a['id']),
        'actif' => $actif === (int) $a['id'],
        'nom' => (string) $a['pseudo'],
        'avatar' => mb_strtoupper(mb_substr((string) $a['pseudo'], 0, 1)),
        'avatar_html' => Amis::avatar((int) $a['id'], (string) $a['pseudo']),
        'groupe' => false,
        'apercu' => $apercu,
        'quand' => $dernier === null ? null : (string) $dernier['created_at'],
        'non_lus' => (int) $a['non_lus'],
        'tri' => $dernier === null ? '' : (string) $dernier['created_at'],
    ];
}
foreach ($groupes as $g) {
    $dernier = $g['dernier_id'] !== null ? ($derniersGroupes[(int) $g['dernier_id']] ?? null) : null;
    $discussions[] = [
        'url' => url('groupes/' . (int) $g['id']),
        'actif' => $groupeActif === (int) $g['id'],
        'nom' => (string) $g['nom'],
        'avatar' => '👥',
        'avatar_html' => Conversations::avatar((int) $g['id'], $g['photo_nom'] ?? null),
        'groupe' => true,
        'apercu' => Conversations::apercu($dernier, $moi),
        'quand' => $dernier === null ? null : (string) $dernier['created_at'],
        'non_lus' => (int) $g['non_lus'],
        'tri' => $dernier === null ? (string) $g['created_at'] : (string) $dernier['created_at'],
    ];
}
// La plus récente en tête ; une discussion encore vide, après, par nom.
usort($discussions, static fn (array $x, array $y): int => [$y['tri'], $x['nom']] <=> [$x['tri'], $y['nom']]);
?>
<?php if ($discussions === []): ?>
  <p class="discret" style="margin:0">Pas encore d’amis. Cherchez un pseudo pour envoyer une demande.</p>
<?php else: ?>
  <ul class="amis-liste" data-liste-discussions>
    <?php foreach ($discussions as $d): ?>
      <li data-nom="<?= e(mb_strtolower($d['nom'])) ?>">
        <a class="ami<?= $d['actif'] ? ' ami--actif' : '' ?><?= $d['non_lus'] > 0 ? ' ami--non-lu' : '' ?>"
           href="<?= e($d['url']) ?>"<?= $d['actif'] ? ' aria-current="page"' : '' ?>>
          <?php if (isset($d['avatar_html'])): ?>
            <?= $d['avatar_html'] ?>
          <?php else: ?>
            <span class="avatar" aria-hidden="true"><?= e($d['avatar']) ?></span>
          <?php endif; ?>
          <span class="ami__texte">
            <span class="ami__ligne">
              <span class="ami__pseudo"><?= e($d['nom']) ?></span>
              <?php if ($d['quand'] !== null): ?>
                <span class="ami__quand"><?= e(Amis::quand($d['quand'])) ?></span>
              <?php endif; ?>
            </span>
            <span class="ami__ligne">
              <?php // Un message vocal : le micro dessiné, comme sur le bouton d'enregistrement. ?>
              <span class="ami__apercu"><?= str_replace(e('🎤 '), Amis::micro() . ' ', e(mb_strimwidth($d['apercu'], 0, 80, '…'))) ?></span>
              <?php if ($d['non_lus'] > 0): ?>
                <span class="compteur" title="<?= $d['non_lus'] ?> message<?= $d['non_lus'] > 1 ? 's' : '' ?> non lu<?= $d['non_lus'] > 1 ? 's' : '' ?>"><?= $d['non_lus'] > 99 ? '99+' : $d['non_lus'] ?></span>
              <?php endif; ?>
            </span>
          </span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
  <p class="discret" data-filtre-vide hidden style="margin:.5rem .6rem 0">Aucune discussion ne correspond.</p>
<?php endif; ?>
