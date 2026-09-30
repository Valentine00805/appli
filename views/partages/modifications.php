<?php
/**
 * Ce que d'autres ont changé dans un cours ou une fiche, du plus récent au
 * plus ancien : le texte ajouté en vert, le texte retiré barré en rouge, les
 * fichiers joints et retirés.
 *
 * Un fichier retiré par un ami n'a pas été effacé : son propriétaire peut
 * l'ouvrir, et le remettre à sa place.
 *
 * @var string $type        cours ou fiche
 * @var string $mot
 * @var array $cible
 * @var list<array> $modifications
 * @var bool $chezMoi       le document est à moi
 * @var bool $peutAnnuler   je peux défaire ce qui a été fait
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$peutAnnuler = $peutAnnuler ?? $chezMoi;
$csrf = Session::jetonCsrf();
$titre = (string) ($cible['titre_cours'] ?? $cible['titre']);

// Un paragraphe inchangé entre deux changements se replie : on garde de quoi
// se repérer, pas tout le cours.
$rendreDifference = static function (array $difference): void {
    $changes = [];
    foreach ($difference as $rang => $p) {
        if ($p['etat'] !== 'egal') {
            $changes[] = $rang;
        }
    }
    $proche = static function (int $rang) use ($changes): bool {
        foreach ($changes as $c) {
            if (abs($c - $rang) <= 1) {
                return true;
            }
        }
        return false;
    };
    $replie = false;
    foreach ($difference as $rang => $p) {
        if ($p['etat'] === 'egal' && !$proche($rang)) {
            if (!$replie) {
                echo '<p class="difference__saut">…</p>';
                $replie = true;
            }
            continue;
        }
        $replie = false;
        if ($p['etat'] === 'egal') {
            echo '<p>' . e((string) $p['texte']) . '</p>';
        } elseif ($p['etat'] === 'ajout') {
            echo '<p class="difference__ajout"><span class="sr-only">' . e(t('pt.ajoute_deux_points')) . '</span>' . e((string) $p['texte']) . '</p>';
        } elseif ($p['etat'] === 'retrait') {
            echo '<p class="difference__retrait"><span class="sr-only">' . e(t('pt.retire_deux_points')) . '</span>' . e((string) $p['texte']) . '</p>';
        } else {
            echo '<p>';
            foreach ($p['morceaux'] as [$etat, $texte]) {
                echo match ($etat) {
                    'ajout' => '<ins>' . e($texte) . '</ins>',
                    'retrait' => '<del>' . e($texte) . '</del>',
                    default => e($texte),
                };
            }
            echo '</p>';
        }
    }
};
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <h1 style="margin:0"><?= e(t('pt.mods_titre')) ?> <span class="discret">(<?= count($modifications) ?>)</span></h1>
    <p class="discret" style="margin:.15rem 0 0"><?= $type === 'fiche' ? e(t('pt.mods_fiche')) : '📘 ' ?><?= e($titre) ?></p>
  </div>
</div>

<?php if ($modifications === []): ?>
  <section class="carte vide">
    <span class="vide__icone">🕘</span>
    <p><?= e(t('pt.mods_vide')) ?></p>
  </section>
<?php else: ?>
  <section class="carte">
    <p class="difference__legende">
      <span class="difference__ajout"><?= e(t('pt.legende_ajoute')) ?></span>
      <span class="difference__retrait"><?= e(t('pt.legende_retire')) ?></span>
    </p>
    <ul class="historique">
      <?php foreach ($modifications as $m): ?>
        <li>
          <div class="historique__qui">
            <?= Amis::avatar((int) $m['user_id'], (string) $m['pseudo'], 'avatar--mini') ?>
            <strong><?= e((string) $m['pseudo']) ?></strong><?= (int) $m['user_id'] === Auth::id() ? ' <span class="discret">' . e(t('pt.vous')) . '</span>' : '' ?>
            <span class="discret">
              <?= e(t('pt.mod_' . (in_array((string) $m['nature'], ['texte', 'ajout'], true) ? (string) $m['nature'] : 'retrait'))) ?>
              · <?= e(date_fr(Amis::local((string) $m['created_at'])->format('Y-m-d H:i:s'))) ?>
            </span>
            <?php if ((int) $m['annulee'] === 1 || (int) $m['restaure'] === 1): ?>
              <span class="pastille"><?= e(t('pt.annulee')) ?></span>
            <?php endif; ?>
          </div>

          <?php if ($m['nature'] === 'texte'): ?>
            <div class="difference">
              <?php if ($m['difference'] === []): ?>
                <p class="discret"><?= e(t('pt.mise_en_forme')) ?></p>
              <?php else: ?>
                <?php $rendreDifference($m['difference']); ?>
              <?php endif; ?>
            </div>
            <?php if ($peutAnnuler && (int) $m['annulee'] === 0): ?>
              <?php
              // Revenir en arrière efface aussi ce qui a changé depuis : on le dit.
              $garde = t($m['change_depuis'] ? 'pt.annuler_garde_depuis' : 'pt.annuler_garde');
              ?>
              <form method="post" action="<?= url('partages/modifications/' . (int) $m['id'] . '/annuler') ?>"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>
                    data-confirmation="<?= e($garde) ?>" style="margin-top:.4rem">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button class="bouton bouton--secondaire bouton--petit" type="submit"><?= e(t('pt.annuler_mod')) ?></button>
              </form>
            <?php endif; ?>

          <?php elseif ($m['nature'] === 'ajout'): ?>
            <p class="difference__ajout" style="margin:0">
              📎 <?= e((string) $m['nom_origine']) ?>
              <span class="discret">· <?= e(taille_lisible((int) $m['taille'])) ?></span>
              <?php if ($m['fichier_existe'] !== null): ?>
                · <a href="<?= url($chezMoi ? 'fichiers/' . (int) $m['fichier_id'] : 'partages/fichiers/' . (int) $m['fichier_id'] . '/contenu') ?>"
                     target="_blank" rel="noopener"><?= e(t('pt.ouvrir')) ?></a>
              <?php else: ?>
                <span class="discret"><?= e(t('pt.retire_depuis')) ?></span>
              <?php endif; ?>
            </p>
            <?php if ($peutAnnuler && (int) $m['annulee'] === 0 && $m['fichier_existe'] !== null): ?>
              <form method="post" action="<?= url('partages/modifications/' . (int) $m['id'] . '/annuler') ?>"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>
                    data-confirmation="<?= e(t('pt.annuler_retirer_garde', ['nom' => (string) $m['nom_origine']])) ?>" style="margin-top:.4rem">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button class="bouton bouton--secondaire bouton--petit" type="submit"><?= e(t('pt.annuler_retirer')) ?></button>
              </form>
            <?php endif; ?>

          <?php else: ?>
            <p class="difference__retrait" style="margin:0">
              🗑️ <?= e((string) $m['nom_origine']) ?>
              <span class="discret">· <?= e(taille_lisible((int) $m['taille'])) ?></span>
            </p>
            <?php if ((int) $m['restaure'] === 1): ?>
              <p class="discret" style="margin:.3rem 0 0"><?= e(t('pt.remis')) ?></p>
            <?php elseif ($m['nom_stocke'] === null): ?>
              <p class="discret" style="margin:.3rem 0 0"><?= e(t('pt.supprime_proprietaire')) ?></p>
            <?php elseif ($peutAnnuler): ?>
              <?php // Il n'a pas été effacé : on l'ouvre, et on le remet si l'on veut. ?>
              <div class="actions" style="justify-content:flex-start;margin-top:.4rem">
                <a class="bouton bouton--secondaire bouton--petit" href="<?= url('partages/modifications/' . (int) $m['id'] . '/fichier') ?>"
                   target="_blank" rel="noopener"><?= e(t('pt.ouvrir')) ?></a>
                <form method="post" action="<?= url('partages/modifications/' . (int) $m['id'] . '/annuler') ?>"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <button class="bouton bouton--petit" type="submit"><?= e(t('pt.annuler_remettre')) ?></button>
                </form>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>
