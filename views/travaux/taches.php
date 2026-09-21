<?php
/**
 * Qui fait quoi : les tâches du projet, en trois colonnes, et la répartition.
 *
 * @var array $projet
 * @var list<array> $taches
 * @var list<array> $membres  ceux à qui l'on peut confier une tâche
 * @var string $filtre  tous | moi | personne
 * @var string $onglet
 */
$dansUneFenetre = $dansUneFenetre ?? false;
// Dans la fenêtre, on y reste : les formulaires s'y enregistrent, les liens s'y ouvrent.
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$lien = $dansUneFenetre ? ' data-fenetre' : '';
$csrf = Session::jetonCsrf();
// Changer un statut ramène au même filtre.
$adresseRetour = (string) ($_SERVER['REQUEST_URI'] ?? '');
if ($dansUneFenetre) {
    $adresseRetour .= (str_contains($adresseRetour, '?') ? '&' : '?') . 'fenetre=1';
}
$retour = '<input type="hidden" name="retour" value="' . e($adresseRetour) . '">';
$moi = (int) $projet['mon_membre_id'];
$visibles = array_filter($taches, static fn (array $t): bool => match ($filtre) {
    'moi' => (int) ($t['membre_id'] ?? 0) === $moi,
    'personne' => $t['membre_id'] === null,
    default => true,
});
$parStatut = array_fill_keys(array_keys(Travaux::STATUTS), []);
foreach ($visibles as $t) {
    $parStatut[$t['statut']][] = $t;
}
$total = count($taches);
$faites = count(array_filter($taches, static fn (array $t): bool => $t['statut'] === 'fait'));
$avancement = Travaux::avancement($total, $faites);

/** Le menu « qui s'en occupe », pour l'ajout et la modification. */
$choixMembre = static function (string $id, ?int $choisi) use ($membres): string {
    $html = '<select id="' . e($id) . '" name="membre_id"><option value="">Personne pour l’instant</option>';
    foreach ($membres as $m) {
        $html .= '<option value="' . (int) $m['id'] . '"' . ((int) $m['id'] === $choisi ? ' selected' : '') . '>'
            . e((string) $m['nom_affiche']) . ($m['user_id'] === null ? ' (sans compte)' : '') . '</option>';
    }

    return $html . '</select>';
};
?>
<?= Vue::rendre('travaux/_onglets', ['projet' => $projet, 'onglet' => $onglet, 'dansUneFenetre' => $dansUneFenetre]) ?>

<div class="<?= $dansUneFenetre ? 'pile' : 'colonnes' ?>">
  <div class="pile">
    <div class="travaux-filtres">
      <a class="bouton bouton--petit" href="<?= url('travaux/' . (int) $projet['id'] . '/taches/nouvelle') ?>" data-fenetre>+ Nouvelle tâche</a>
      <?php foreach (['tous' => 'Toutes', 'moi' => 'Les miennes', 'personne' => 'Sans personne'] as $cle => $nom): ?>
        <a class="pastille<?= $filtre === $cle ? ' pastille--active' : '' ?>"
           href="<?= url('travaux/' . (int) $projet['id'], $cle === 'tous' ? [] : ['voir' => $cle]) ?>"<?= $lien ?>><?= e($nom) ?></a>
      <?php endforeach; ?>
    </div>

    <?php if ($total === 0): ?>
      <div class="vide">
        <span class="vide__icone">✅</span>
        <p>Aucune tâche pour l’instant. Découpez le travail en morceaux, et dites qui fait quoi.</p>
        <p><a class="bouton bouton--secondaire" href="<?= url('travaux/' . (int) $projet['id'] . '/taches/nouvelle') ?>" data-fenetre>Ajouter la première</a></p>
      </div>
    <?php endif; ?>

    <div class="kanban travaux-kanban">
      <?php foreach (Travaux::STATUTS as $statut => $s): ?>
        <section class="kanban__colonne">
          <h2 class="kanban__entete"><?= $s['icone'] ?> <?= e($s['nom']) ?>
            <span class="kanban__compteur"><?= count($parStatut[$statut]) ?></span></h2>
          <ul class="kanban__pile" style="list-style:none;padding:0;margin:0">
            <?php if ($parStatut[$statut] === []): ?>
              <li class="kanban__vide discret">Rien ici.</li>
            <?php endif; ?>
            <?php foreach ($parStatut[$statut] as $t): ?>
              <?php $id = (int) $t['id']; $fait = $statut === 'fait'; ?>
              <li class="kanban-carte<?= $fait ? ' kanban-carte--faite' : '' ?>" id="tache-<?= $id ?>">
                <p class="kanban-carte__titre"><?= e((string) $t['titre']) ?></p>
                <?php if ((string) ($t['note'] ?? '') !== ''): ?>
                  <p class="discret" style="margin:.2rem 0 0;font-size:.85rem"><?= nl2br(e((string) $t['note'])) ?></p>
                <?php endif; ?>
                <p class="kanban-carte__meta">
                  <?php if ($t['membre_id'] !== null): ?>
                    <span class="pastille<?= (int) $t['membre_id'] === $moi ? ' pastille--active' : '' ?>">
                      👤 <?= (int) $t['membre_id'] === $moi ? 'Moi' : e((string) $t['membre_nom']) ?></span>
                  <?php else: ?>
                    <span class="pastille pastille--muette">Personne</span>
                  <?php endif; ?>
                  <?php $texte = echeance_libelle($t['echeance'], $fait); ?>
                  <?php if ($texte !== ''): ?>
                    <span class="echeance echeance--<?= e(echeance_etat($t['echeance'], $fait)) ?>"><?= e($texte) ?></span>
                  <?php endif; ?>
                </p>
                <div class="travaux-tache__actions">
                  <form method="post"<?= $envoi ?> action="<?= url('travaux/taches/' . $id . '/statut') ?>" class="en-ligne" data-auto-envoi>
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <?= $retour ?>
                    <label class="sr-only" for="statut-<?= $id ?>">Où en est « <?= e((string) $t['titre']) ?> »</label>
                    <select id="statut-<?= $id ?>" name="statut">
                      <?php foreach (Travaux::STATUTS as $autre => $a): ?>
                        <option value="<?= e($autre) ?>"<?= $autre === $statut ? ' selected' : '' ?>><?= e($a['nom']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <noscript><button class="bouton bouton--discret bouton--petit" type="submit">OK</button></noscript>
                  </form>
                  <?php if ($t['membre_id'] === null && !$fait): ?>
                    <form method="post"<?= $envoi ?> action="<?= url('travaux/taches/' . $id . '/prendre') ?>" class="en-ligne">
                      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                      <?= $retour ?>
                      <button class="bouton bouton--secondaire bouton--petit" type="submit">✋ Je m’en occupe</button>
                    </form>
                  <?php endif; ?>
                </div>
                <details class="travaux-modifier">
                  <summary>Modifier</summary>
                  <form method="post"<?= $envoi ?> action="<?= url('travaux/taches/' . $id . '/modifier') ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <div class="champ">
                      <label for="titre-<?= $id ?>">Tâche</label>
                      <input type="text" id="titre-<?= $id ?>" name="titre" required maxlength="200" value="<?= e((string) $t['titre']) ?>">
                    </div>
                    <div class="champ">
                      <label for="membre-<?= $id ?>">Qui s’en occupe</label>
                      <?= $choixMembre('membre-' . $id, $t['membre_id'] === null ? null : (int) $t['membre_id']) ?>
                    </div>
                    <div class="champ">
                      <label for="echeance-<?= $id ?>">Pour le</label>
                      <input type="date" id="echeance-<?= $id ?>" name="echeance" value="<?= e((string) ($t['echeance'] ?? '')) ?>">
                    </div>
                    <div class="champ">
                      <label for="note-<?= $id ?>">Précisions</label>
                      <textarea id="note-<?= $id ?>" name="note" rows="2" maxlength="2000"><?= e((string) ($t['note'] ?? '')) ?></textarea>
                    </div>
                    <button class="bouton bouton--petit" type="submit">Enregistrer</button>
                  </form>
                  <form method="post"<?= $envoi ?> action="<?= url('travaux/taches/' . $id . '/supprimer') ?>" class="en-ligne"
                        data-confirmation="Supprimer la tâche « <?= e((string) $t['titre']) ?> » pour tout le groupe ?">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button class="bouton bouton--discret bouton--petit" type="submit">Supprimer</button>
                  </form>
                </details>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="pile">
    <section class="carte">
      <h2>Répartition</h2>
      <?php if ($avancement !== null): ?>
        <div class="jauge" title="<?= $avancement ?> % des tâches faites">
          <span style="width:<?= $avancement ?>%;background:var(--accent)"></span>
        </div>
        <p class="discret" style="margin:0 0 .6rem"><?= $faites ?> / <?= $total ?> tâches faites (<?= $avancement ?> %)</p>
      <?php endif; ?>
      <ul class="travaux-repartition">
        <?php foreach ($membres as $m): ?>
          <li>
            <span><?= e((string) $m['nom_affiche']) ?><?= (int) $m['id'] === $moi ? ' <span class="discret">(moi)</span>' : '' ?>
              <?php if ($m['user_id'] === null): ?><span class="discret">· sans compte</span><?php endif; ?></span>
            <span class="discret"><?= (int) $m['a_faire'] ?> à faire · <?= (int) $m['faites'] ?> faite<?= (int) $m['faites'] > 1 ? 's' : '' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>
</div>
