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
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
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
            echo '<p class="difference__ajout"><span class="sr-only">Ajouté : </span>' . e((string) $p['texte']) . '</p>';
        } elseif ($p['etat'] === 'retrait') {
            echo '<p class="difference__retrait"><span class="sr-only">Retiré : </span>' . e((string) $p['texte']) . '</p>';
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
    <h1 style="margin:0">🕘 Modifications <span class="discret">(<?= count($modifications) ?>)</span></h1>
    <p class="discret" style="margin:.15rem 0 0"><?= $type === 'fiche' ? '📝 Fiche — ' : '📘 ' ?><?= e($titre) ?></p>
  </div>
</div>

<?php if ($modifications === []): ?>
  <section class="carte vide">
    <span class="vide__icone">🕘</span>
    <p>Personne d’autre n’a encore modifié ce document.</p>
  </section>
<?php else: ?>
  <section class="carte">
    <p class="difference__legende">
      <span class="difference__ajout">ajouté</span>
      <span class="difference__retrait">retiré</span>
    </p>
    <ul class="historique">
      <?php foreach ($modifications as $m): ?>
        <li>
          <div class="historique__qui">
            <?= Amis::avatar((int) $m['user_id'], (string) $m['pseudo'], 'avatar--mini') ?>
            <strong><?= e((string) $m['pseudo']) ?></strong>
            <span class="discret">
              <?= match ((string) $m['nature']) {
                  'texte' => 'a modifié le texte',
                  'ajout' => 'a ajouté un fichier',
                  default => 'a retiré un fichier',
              } ?>
              · <?= e(date_fr(Amis::local((string) $m['created_at'])->format('Y-m-d H:i:s'))) ?>
            </span>
          </div>

          <?php if ($m['nature'] === 'texte'): ?>
            <div class="difference">
              <?php if ($m['difference'] === []): ?>
                <p class="discret">Seule la mise en forme a changé.</p>
              <?php else: ?>
                <?php $rendreDifference($m['difference']); ?>
              <?php endif; ?>
            </div>

          <?php elseif ($m['nature'] === 'ajout'): ?>
            <p class="difference__ajout" style="margin:0">
              📎 <?= e((string) $m['nom_origine']) ?>
              <span class="discret">· <?= e(taille_lisible((int) $m['taille'])) ?></span>
              <?php if ($m['fichier_existe'] !== null): ?>
                · <a href="<?= url($chezMoi ? 'fichiers/' . (int) $m['fichier_id'] : 'partages/fichiers/' . (int) $m['fichier_id'] . '/contenu') ?>"
                     target="_blank" rel="noopener">Ouvrir</a>
              <?php else: ?>
                <span class="discret">· retiré depuis</span>
              <?php endif; ?>
            </p>

          <?php else: ?>
            <p class="difference__retrait" style="margin:0">
              🗑️ <?= e((string) $m['nom_origine']) ?>
              <span class="discret">· <?= e(taille_lisible((int) $m['taille'])) ?></span>
            </p>
            <?php if ((int) $m['restaure'] === 1): ?>
              <p class="discret" style="margin:.3rem 0 0">Remis à sa place.</p>
            <?php elseif ($chezMoi): ?>
              <?php // Il n'a pas été effacé : on l'ouvre, et on le remet si l'on veut. ?>
              <div class="actions" style="justify-content:flex-start;margin-top:.4rem">
                <a class="bouton bouton--secondaire bouton--petit" href="<?= url('partages/modifications/' . (int) $m['id'] . '/fichier') ?>"
                   target="_blank" rel="noopener">Ouvrir</a>
                <form method="post" action="<?= url('partages/modifications/' . (int) $m['id'] . '/restaurer') ?>"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <button class="bouton bouton--petit" type="submit">Remettre le fichier</button>
                </form>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>
