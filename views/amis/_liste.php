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
// Mes discussions épinglées (genre => identifiants) : elles montent dans une section « Épinglées », en haut de la liste.
$epinglees = Amis::discussionsEpinglees($moi);

$discussions = [];
foreach ($amis as $a) {
    $dernier = $a['dernier_id'] !== null ? ($derniers[(int) $a['dernier_id']] ?? null) : null;
    $apercu = $dernier === null ? t('ami.dites_bonjour')
        : (($dernier['evenement'] ?? null) !== null
          ? Amis::texteEvenement((string) $dernier['evenement'], (int) $dernier['expediteur_id'] === $moi, (string) $a['pseudo'])
        : (($dernier['supprime_le'] ?? null) !== null ? t('ami.message_supprime')
        : ((int) $dernier['expediteur_id'] === $moi ? t('ami.vous_deux_points') : '')
          . (($dernier['image_nom'] ?? null) !== null ? t('ami.photo') . ((string) $dernier['texte'] !== '' ? ' · ' : '') : '')
          . (($dernier['fichier_origine'] ?? null) !== null ? '📎 ' . $dernier['fichier_origine'] . ((string) $dernier['texte'] !== '' ? ' · ' : '') : '')
          . (($dernier['audio_nom'] ?? null) !== null ? t('ami.message_vocal') . ((string) $dernier['texte'] !== '' ? ' · ' : '') : '')
          . (($dernier['sondage_id'] ?? null) !== null ? t('ami.sondage') . ' · ' : '')
          . (($dernier['partage_type'] ?? null) !== null ? '🔗 ' . Partages::libelle((string) $dernier['partage_type']) . ((string) $dernier['texte'] !== '' ? ' · ' : '') : '')
          . preg_replace('/\s+/u', ' ', (string) $dernier['texte'])));
    $discussions[] = [
        'url' => url('amis/' . (int) $a['id']),
        'genre' => 'ami',
        'cible' => (int) $a['id'],
        'epingle' => in_array((int) $a['id'], $epinglees['ami'], true),
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
        'genre' => 'groupe',
        'cible' => (int) $g['id'],
        'epingle' => in_array((int) $g['id'], $epinglees['groupe'], true),
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
<?php
/*
 * Deux sections pliables : « Messages » (une personne) et « Groupes ». Chacune garde son état d'ouverture (voir app.js,
 * [data-memoire]) ; fermée, elle montre ce qu'elle cache de non lu. La discussion ouverte garde sa section ouverte.
 */
// Les épinglées d'abord (de la plus récente à la plus ancienne), puis les autres, comme avant. Une section dont tout est épinglé ne dit pas
// « pas d'amis » : elle est simplement vide.
$epingleesLignes = array_values(array_filter($discussions, static fn (array $d): bool => $d['epingle']));
$sections = [];
if ($epingleesLignes !== []) {
    $sections[] = ['cle' => 'epinglees', 'titre' => t('ami.epinglees'), 'lignes' => $epingleesLignes, 'vide' => ''];
}
foreach ([['messages', 'ami.messages', false, 'ami.pas_encore_amis'], ['groupes', 'ami.groupes_section', true, 'ami.aucun_groupe']] as [$cle, $titre, $groupes_, $vide]) {
    $tous = array_filter($discussions, static fn (array $d): bool => $d['groupe'] === $groupes_);
    $lignes = array_values(array_filter($tous, static fn (array $d): bool => !$d['epingle']));
    $sections[] = ['cle' => $cle, 'titre' => t($titre), 'lignes' => $lignes, 'vide' => $tous === [] ? t($vide) : ''];
}
?>
<?php foreach ($sections as $section): ?>
  <?php
  $nonLus = array_sum(array_column($section['lignes'], 'non_lus'));
  $contientActif = in_array(true, array_column($section['lignes'], 'actif'), true);
  ?>
  <details class="liste-section" data-memoire="amis-<?= e($section['cle']) ?>" open<?= $contientActif ? ' data-garder-ouvert' : '' ?>>
    <summary class="liste-section__titre">
      <span class="liste-section__fleche" aria-hidden="true"></span>
      <span class="liste-section__nom"><?= e($section['titre']) ?></span>
      <span class="liste-section__nombre"><?= count($section['lignes']) ?></span>
      <?php if ($nonLus > 0): ?>
        <span class="compteur liste-section__non-lus" title="<?= e(tn('ami.non_lus', (int) $nonLus)) ?>"><?= $nonLus > 99 ? '99+' : $nonLus ?></span>
      <?php endif; ?>
    </summary>
    <?php if ($section['lignes'] === []): ?>
      <?php if ($section['vide'] !== ''): ?><p class="discret liste-section__vide"><?= e($section['vide']) ?></p><?php endif; ?>
    <?php else: ?>
      <ul class="amis-liste" data-liste-discussions>
        <?php foreach ($section['lignes'] as $d): ?>
          <li class="ami-ligne" data-nom="<?= e(mb_strtolower($d['nom'])) ?>">
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
                    <span class="compteur" title="<?= e(tn('ami.non_lus', (int) $d['non_lus'])) ?>"><?= $d['non_lus'] > 99 ? '99+' : $d['non_lus'] ?></span>
                  <?php endif; ?>
                </span>
              </span>
            </a>
            <?php
            /*
             * Le menu de la discussion, trois points verticaux : épingler en haut de la liste (ou désépingler — à soi seul, l'autre ne le voit
             * pas) et voir le profil (ou les réglages, pour un groupe). Natif : « details » s'ouvre et se referme sans script, le script de la
             * page ferme en plus celui qu'on quitte du clic.
             */
            ?>
            <details class="ami-menu">
              <summary class="ami-menu__bouton" aria-label="<?= e(t('ami.options_de', ['nom' => $d['nom']])) ?>" title="<?= e(t('ami.options')) ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true" focusable="false">
                  <circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/>
                </svg>
              </summary>
              <div class="ami-menu__liste" role="menu">
                <form method="post" action="<?= url('discussions/epingler') ?>" role="none">
                  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                  <input type="hidden" name="genre" value="<?= e($d['genre']) ?>">
                  <input type="hidden" name="cible" value="<?= (int) $d['cible'] ?>">
                  <input type="hidden" name="epingle" value="<?= $d['epingle'] ? '0' : '1' ?>">
                  <input type="hidden" name="retour" value="<?= e(ROUTE) ?>">
                  <button type="submit" class="ami-menu__item" role="menuitem">
                    <span aria-hidden="true">📌</span> <?= e(t($d['epingle'] ? 'ami.desepingler_discussion' : 'ami.epingler_discussion')) ?>
                  </button>
                </form>
                <a class="ami-menu__item" role="menuitem" data-fenetre
                   href="<?= e($d['genre'] === 'groupe' ? url('groupes/' . (int) $d['cible'] . '/reglages') : url('amis/' . (int) $d['cible'] . '/profil')) ?>">
                  <span aria-hidden="true"><?= $d['genre'] === 'groupe' ? '⚙️' : '👤' ?></span>
                  <?= e(t($d['genre'] === 'groupe' ? 'chat.reglages_groupe' : 'chat.voir_profil')) ?>
                </a>
              </div>
            </details>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </details>
<?php endforeach; ?>
<p class="discret" data-filtre-vide hidden style="margin:.5rem .6rem 0"><?= e(t('ami.aucune_discussion')) ?></p>
