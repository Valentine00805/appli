<?php
/**
 * Le document commun : un texte que tout le groupe écrit, avec l'historique
 * de ses versions.
 *
 * @var array $projet
 * @var list<array> $versions
 * @var ?string $brouillon  ce qu'on avait tapé quand quelqu'un a écrit en même temps
 * @var ?array $auteur      qui l'a modifié en dernier
 * @var string $onglet
 */
$dansUneFenetre = $dansUneFenetre ?? false;
// Dans la fenêtre, on y reste : les formulaires s'y enregistrent, les liens s'y ouvrent.
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$lien = $dansUneFenetre ? ' data-fenetre' : '';
$csrf = Session::jetonCsrf();
?>
<?= Vue::rendre('travaux/_onglets', ['projet' => $projet, 'onglet' => $onglet, 'dansUneFenetre' => $dansUneFenetre]) ?>

<div class="colonnes">
  <div class="pile">
    <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/document') ?>" class="carte">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <?php // La version lue : si quelqu'un enregistre entre-temps, on ne l'écrase pas. ?>
      <input type="hidden" name="version" value="<?= (int) $projet['document_version'] ?>">
      <h2 style="margin-top:0">📝 Document commun</h2>
      <?php if ($projet['document_le'] !== null): ?>
        <p class="discret" style="margin-top:-.4rem">Modifié le <?= e(date_fr((string) $projet['document_le'])) ?>
          par <?= e((string) ($auteur['pseudo'] ?? $auteur['nom'] ?? 'un ancien membre')) ?>.</p>
      <?php endif; ?>
      <label class="sr-only" for="document">Document commun</label>
      <textarea id="document" name="document" style="min-height:420px" data-texte-riche="complet"
                data-tailles="<?= e(implode(',', TexteRiche::TAILLES)) ?>"
                placeholder="Le plan, la répartition des parties, les idées, le brouillon du rendu…"><?= e(TexteRiche::pourEditeur($projet['document'])) ?></textarea>
      <button class="bouton" type="submit" style="margin-top:.8rem">Enregistrer</button>
    </form>

    <?php if (is_string($brouillon) && trim($brouillon) !== ''): ?>
      <section class="carte travaux-brouillon">
        <h2>Votre version, non enregistrée</h2>
        <p class="discret">Quelqu’un a enregistré avant vous. Reprenez d’ici ce qui manque au document ci-dessus.</p>
        <div class="texte-riche-affiche"><?= TexteRiche::versHtml(TexteRiche::depuisFormulaire($brouillon)) ?></div>
      </section>
    <?php endif; ?>
  </div>

  <div class="pile">
    <section class="carte">
      <h2>Versions précédentes</h2>
      <?php if ($versions === []): ?>
        <p class="discret">Chaque enregistrement garde la version d’avant : on peut toujours revenir en arrière.</p>
      <?php else: ?>
        <ul class="travaux-versions">
          <?php foreach ($versions as $v): ?>
            <li>
              <a href="<?= url('travaux/versions/' . (int) $v['id']) ?>"<?= $lien ?>><?= e(date_fr((string) $v['created_at'])) ?></a>
              <span class="discret">· <?= e((string) ($v['auteur'] ?? 'un ancien membre')) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
</div>
