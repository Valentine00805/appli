<?php
/**
 * Les champs d'un type d'échéance : nom, icône, couleur, rappels.
 *
 * @var string $suffixe  pour des identifiants uniques
 * @var ?array $t  le type à modifier, ou null
 * @var list<string> $palette
 * @var list<string> $icones
 */
$icone = (string) ($t['icone'] ?? $icones[0]);
$couleur = strtolower((string) ($t['couleur'] ?? $palette[0]));
$rappels = Rappels::lire((string) ($t['rappels'] ?? '1440'));
// L'icône d'un type ancien peut ne plus être proposée : on la garde au choix.
$choixIcones = in_array($icone, $icones, true) ? $icones : array_merge([$icone], $icones);
?>
<div class="champ">
  <label for="nom-<?= e($suffixe) ?>">Nom</label>
  <input type="text" id="nom-<?= e($suffixe) ?>" name="nom" required maxlength="40"
         value="<?= e((string) ($t['nom'] ?? '')) ?>" placeholder="Projet, oral blanc, partiel…">
</div>
<div class="champ">
  <span class="legende">Icône</span>
  <div class="choix-icones">
    <?php foreach ($choixIcones as $j => $i): ?>
      <input type="radio" id="icone-<?= e($suffixe) ?>-<?= $j ?>" name="icone" value="<?= e($i) ?>"<?= $i === $icone ? ' checked' : '' ?>>
      <label for="icone-<?= e($suffixe) ?>-<?= $j ?>"><?= e($i) ?></label>
    <?php endforeach; ?>
  </div>
</div>
<div class="champ">
  <span class="legende">Couleur</span>
  <div class="choix-couleurs">
    <?php foreach ($palette as $j => $c): ?>
      <input type="radio" id="couleur-<?= e($suffixe) ?>-<?= $j ?>" name="couleur" value="<?= e($c) ?>"<?= $c === $couleur ? ' checked' : '' ?>>
      <label for="couleur-<?= e($suffixe) ?>-<?= $j ?>" style="background:<?= e($c) ?>" title="<?= e($c) ?>"></label>
    <?php endforeach; ?>
  </div>
</div>
<fieldset class="rappels-choix">
  <legend>🔔 Rappels</legend>
  <div class="rappels-choix__liste">
    <?php foreach (array_reverse(Rappels::DELAIS_COURTS, true) as $minutes => $court): ?>
      <label class="rappels-choix__option" title="<?= e(Rappels::libelle((int) $minutes)) ?>">
        <input type="checkbox" name="rappels[]" value="<?= (int) $minutes ?>"<?= in_array($minutes, $rappels, true) ? ' checked' : '' ?>>
        <span class="pastille"><?= e(Rappels::court((int) $minutes)) ?></span>
      </label>
    <?php endforeach; ?>
  </div>
  <span class="champ__aide">Ceux que prend l’échéance en arrivant dans le calendrier de chaque membre ;
    chacun peut ensuite changer les siens.</span>
</fieldset>
