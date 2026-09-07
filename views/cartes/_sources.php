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
    'cours'     => ['📘 Le cours', 'son texte',
                    $garni($cours, 'contenu', 'a_contenu')],
    'fiche'     => ['📝 La fiche de révision', 'ce que vous en avez écrit',
                    $garni($cours, 'fiche_revision', 'a_fiche')],
    'documents' => ['📎 Les documents joints', 'PDF, Word, tableurs, texte',
                    $cours === null || (int) ($cours['nb_fichiers'] ?? 1) > 0],
];
?>
<fieldset class="sources">
  <legend>À partir de</legend>
  <?php foreach ($sources as $cle => [$libelle, $aide, $possible]): ?>
    <label class="sources__choix<?= $possible ? '' : ' sources__choix--vide' ?>">
      <input type="checkbox" name="sources[]" value="<?= e($cle) ?>"
             <?= $possible ? 'checked' : 'disabled' ?>>
      <span>
        <?= e($libelle) ?>
        <span class="discret"><?= $possible ? e($aide) : 'rien à lire' ?></span>
      </span>
    </label>
  <?php endforeach; ?>
</fieldset>
