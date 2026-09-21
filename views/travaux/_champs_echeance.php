<?php
/**
 * Les champs d'une échéance, vides (nouvelle) ou remplis (à modifier).
 *
 * @var string $suffixe  pour des identifiants uniques dans la page
 * @var ?array $e
 * @var list<array> $types  les types d'échéance du projet
 */
$journee = $e !== null && (int) $e['journee_entiere'] === 1;
$duree = $e === null || $journee ? 60
    : (int) round((strtotime((string) $e['fin']) - strtotime((string) $e['debut'])) / 60);
$types = $types ?? [];
$choisi = $e === null ? (int) ($types[0]['id'] ?? 0) : (int) ($e['type_id'] ?? 0);
?>
<div class="champ">
  <label for="type-<?= e($suffixe) ?>">C’est</label>
  <select id="type-<?= e($suffixe) ?>" name="type_id">
    <?php foreach ($types as $t): ?>
      <option value="<?= (int) $t['id'] ?>"<?= $choisi === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['icone']) ?> <?= e($t['nom']) ?></option>
    <?php endforeach; ?>
    <option value=""<?= $choisi === 0 ? ' selected' : '' ?>><?= Travaux::ICONE_SANS_TYPE ?> Sans type</option>
  </select>
  <span class="champ__aide">Chaque type a son icône, sa couleur et ses rappels : ils se règlent dans « 🏷️ Types d’échéance ».</span>
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
