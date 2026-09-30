<?php
/**
 * Les trois sources dans lesquelles puiser, à cocher.
 *
 * Le même bloc sert depuis l'onglet Cartes et depuis un paquet. Quand on sait
 * de quel cours il s'agit, on grise ce qu'il n'a pas : proposer de relire une
 * fiche vide ne mènerait nulle part.
 *
 * @var ?array $cours  le cours visé, s'il est déjà connu
 */
$cours = $cours ?? null;
/** Y a-t-il quelque chose à lire de ce côté-là ? */
$garni = static function (?array $cours, string $colonne, string $drapeau): bool {
    if ($cours === null) {
        return true;   // liste de tous les cours : on ne peut rien préjuger
    }
    return array_key_exists($drapeau, $cours)
        ? (bool) $cours[$drapeau]
        : trim((string) ($cours[$colonne] ?? '')) !== '';
};

$sources = [
    'cours'     => [$garni($cours, 'contenu', 'a_contenu')],
    'fiche'     => [$garni($cours, 'fiche_revision', 'a_fiche')],
    'documents' => [$cours === null || (int) ($cours['nb_fichiers'] ?? 1) > 0],
];
?>
<fieldset class="sources">
  <legend><?= e(t('crt.a_partir_de')) ?></legend>
  <?php foreach ($sources as $cle => [$possible]): ?>
    <label class="sources__choix<?= $possible ? '' : ' sources__choix--vide' ?>">
      <input type="checkbox" name="sources[]" value="<?= e($cle) ?>"
             <?= $possible ? 'checked' : 'disabled' ?>>
      <span>
        <?= e(t('crt.src.' . $cle)) ?>
        <span class="discret"><?= e($possible ? t('crt.src.' . $cle . '_aide') : t('crt.rien_a_lire')) ?></span>
      </span>
    </label>
  <?php endforeach; ?>
</fieldset>
