<?php
/**
 * Les champs d'une échéance, vides (nouvelle) ou remplis (à modifier).
 *
 * @var string $suffixe  pour des identifiants uniques dans la page
 * @var ?array $e
 * @var list<string> $types  les types à soi déjà employés dans le projet
 */
$journee = $e !== null && (int) $e['journee_entiere'] === 1;
$duree = $e === null || $journee ? 60
    : (int) round((strtotime((string) $e['fin']) - strtotime((string) $e['debut'])) / 60);
$types = $types ?? [];
// Ce qui est choisi : « rendu », « autre:Projet », ou « autre » (un type à écrire).
$choisi = $e === null ? 'rendu'
    : (($e['nature'] === 'autre' && (string) ($e['type_nom'] ?? '') !== '') ? 'autre:' . $e['type_nom'] : (string) $e['nature']);
?>
<div class="champ">
  <label for="nature-<?= e($suffixe) ?>">C’est</label>
  <select id="nature-<?= e($suffixe) ?>" name="nature" data-type-echeance>
    <?php foreach (Travaux::NATURES as $cle => $n): ?>
      <?php if ($cle === 'autre') { continue; } ?>
      <option value="<?= e($cle) ?>"<?= $choisi === $cle ? ' selected' : '' ?>><?= $n['icone'] ?> <?= e($n['nom']) ?></option>
    <?php endforeach; ?>
    <?php foreach ($types as $type): ?>
      <option value="<?= e('autre:' . $type) ?>"<?= $choisi === 'autre:' . $type ? ' selected' : '' ?>><?= Travaux::NATURES['autre']['icone'] ?> <?= e($type) ?></option>
    <?php endforeach; ?>
    <option value="autre"<?= $choisi === 'autre' ? ' selected' : '' ?>>✏️ Un autre type…</option>
  </select>
</div>
<?php // N'apparaît que pour « Un autre type… » : on l'écrit, il rejoint ensuite le menu du projet. ?>
<div class="champ" data-type-libre<?= $choisi === 'autre' ? '' : ' hidden' ?>>
  <label for="type-<?= e($suffixe) ?>">Nom du type</label>
  <input type="text" id="type-<?= e($suffixe) ?>" name="type_nom" maxlength="40" placeholder="Projet, oral blanc, partiel…">
</div>
<div class="champ">
  <label for="titre-<?= e($suffixe) ?>">Titre <span class="discret">(facultatif)</span></label>
  <input type="text" id="titre-<?= e($suffixe) ?>" name="titre" maxlength="160" value="<?= e((string) ($e['titre'] ?? '')) ?>"
         placeholder="Rendu du dossier final">
</div>
<div class="ligne-champs">
  <div class="champ">
    <label for="jour-<?= e($suffixe) ?>">Jour</label>
    <input type="date" id="jour-<?= e($suffixe) ?>" name="jour" required value="<?= e($e === null ? '' : substr((string) $e['debut'], 0, 10)) ?>">
  </div>
  <div class="champ">
    <label for="heure-<?= e($suffixe) ?>">Heure <span class="discret">(vide : toute la journée)</span></label>
    <input type="time" id="heure-<?= e($suffixe) ?>" name="heure" value="<?= e($e === null || $journee ? '' : substr((string) $e['debut'], 11, 5)) ?>">
  </div>
</div>
<div class="ligne-champs">
  <div class="champ">
    <label for="duree-<?= e($suffixe) ?>">Durée (min)</label>
    <input type="number" id="duree-<?= e($suffixe) ?>" name="duree" min="15" max="600" step="15" value="<?= $duree ?>">
  </div>
  <div class="champ">
    <label for="lieu-<?= e($suffixe) ?>">Lieu <span class="discret">(facultatif)</span></label>
    <input type="text" id="lieu-<?= e($suffixe) ?>" name="lieu" maxlength="160" value="<?= e((string) ($e['lieu'] ?? '')) ?>">
  </div>
</div>
