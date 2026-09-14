<?php
/**
 * Télécharger un fichier : le fichier d'origine, ou — pour un document ou un
 * texte — sa mise en pages en PDF, au choix dans un petit menu.
 *
 * Le même partiel sert à l'aperçu (un vrai bouton) et aux listes de fichiers
 * du cours et de la fiche (le petit « ⬇ »). « details » suffit à ouvrir le
 * menu, sans une ligne de script ; le script le referme une fois le choix fait.
 *
 * @var array $fichier
 * @var bool  $compact  le petit bouton des listes, plutôt que celui de l'aperçu
 */
$compact = $compact ?? false;
$nomOrigine = (string) $fichier['nom_origine'];
$origine = url('fichiers/' . $fichier['id'], ['telecharger' => 1]);
?>
<?php if (!ExportPdf::possible($nomOrigine)): ?>
  <?php if ($compact): ?>
    <a class="bouton bouton--discret bouton--petit" href="<?= $origine ?>" title="Télécharger">⬇</a>
  <?php else: ?>
    <a class="bouton" href="<?= $origine ?>">⬇ Télécharger le fichier</a>
  <?php endif; ?>
<?php else: ?>
  <?php $extension = strtolower((string) pathinfo($nomOrigine, PATHINFO_EXTENSION)); ?>
  <details class="menu-telecharger<?= $compact ? ' menu-telecharger--compact' : '' ?>">
    <?php if ($compact): ?>
      <summary class="bouton bouton--discret bouton--petit" title="Télécharger">⬇<span aria-hidden="true">▾</span>
        <span class="sr-only">Télécharger — choisir le format</span></summary>
    <?php else: ?>
      <summary class="bouton">⬇ Télécharger <span aria-hidden="true">▾</span></summary>
    <?php endif; ?>
    <div class="menu-telecharger__choix carte" role="menu">
      <a role="menuitem" href="<?= $origine ?>">
        <span aria-hidden="true">📄</span>
        <span><strong><?= e(ucfirst(ApercuDocument::format($nomOrigine))) ?></strong> (.<?= e($extension) ?>)<br>
          <span class="discret">le fichier d’origine, modifiable</span></span>
      </a>
      <a role="menuitem" href="<?= url('fichiers/' . $fichier['id'] . '/pdf') ?>">
        <span aria-hidden="true">📕</span>
        <span><strong>PDF</strong> (.pdf)<br>
          <span class="discret">pour lire ou imprimer, tel que l’aperçu</span></span>
      </a>
    </div>
  </details>
<?php endif; ?>
