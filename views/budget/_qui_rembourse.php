<?php
/**
 * Qui doit vous rembourser : à choisir dans la liste, ou à nommer.
 *
 * Un champ libre obligeait à se rappeler l'orthographe exacte, et « Papa » y
 * faisait un créancier différent de « papa » : les totaux se scindaient en
 * deux sans qu'on comprenne pourquoi. La liste rassemble les personnes déjà
 * nommées ; « Quelqu'un d'autre » ouvre le champ pour en ajouter une.
 *
 * Sans JavaScript, ce champ reste visible : le nom qu'on y écrit l'emporte
 * alors sur la liste, et rien n'est perdu.
 *
 * @var list<string> $personnes  les personnes déjà nommées
 * @var ?string $valeur   celle qui est déjà retenue, s'il y en a une
 * @var string $libelle   ce que dit l'étiquette
 * @var string $cle       de quoi rendre les identifiants uniques dans la page
 * @var ?string $form     le formulaire visé, quand le champ est posé en dehors
 */
$valeur = $valeur ?? null;
$libelle = $libelle ?? 'Par qui';
$cle = $cle ?? '';
$form = $form ?? null;

/*
 * La personne retenue figure toujours dans la liste, même si elle n'a encore
 * servi nulle part : sinon le formulaire la perdrait en silence.
 */
$liste = $personnes;
if ($valeur !== null && $valeur !== '' && !in_array($valeur, $liste, true)) {
    $liste[] = $valeur;
    sort($liste, SORT_NATURAL | SORT_FLAG_CASE);
}
$attributForm = $form === null ? '' : ' form="' . e($form) . '"';
?>
<div class="champ qui-rembourse">
  <label for="rembourse_par<?= e($cle) ?>"><?= e($libelle) ?></label>

  <select id="rembourse_par<?= e($cle) ?>" name="rembourse_par"<?= $attributForm ?>
          data-qui-rembourse="<?= e($cle) ?>">
    <option value="">— Non précisé —</option>
    <?php foreach ($liste as $personne): ?>
      <option value="<?= e($personne) ?>"<?= $personne === $valeur ? ' selected' : '' ?>>
        <?= e($personne) ?>
      </option>
    <?php endforeach; ?>
    <option value="+">➕ Quelqu'un d'autre…</option>
  </select>

  <input type="text" class="qui-rembourse__autre" data-qui-autre="<?= e($cle) ?>"
         id="rembourse_par_autre<?= e($cle) ?>" name="rembourse_par_autre"<?= $attributForm ?>
         maxlength="80" placeholder="Son nom" aria-label="Nom d'une autre personne">
</div>
