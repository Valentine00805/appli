<?php
/**
 * Créer un dossier sans quitter la page des cours.
 *
 * Le même bloc sert à deux endroits : au bas de la colonne des dossiers quand
 * il y en a, et dans l'en-tête quand il n'y en a pas encore — la colonne ne
 * paraît qu'une fois qu'on a un dossier, et il faut bien pouvoir créer le
 * premier.
 *
 * @var array $dossiers   les dossiers du compte, à plat, avec leur profondeur
 * @var ?int $dossierId   le dossier ouvert, proposé comme destination
 * @var bool $dansEntete  posé dans la barre d'actions plutôt que dans la colonne
 */
$dansEntete = $dansEntete ?? false;
?>
<details class="nouveau-dossier<?= $dansEntete ? ' nouveau-dossier--entete' : '' ?>">
  <summary class="bouton bouton--secondaire<?= $dansEntete ? '' : ' bouton--petit bouton--bloc' ?>">
    ＋ Nouveau dossier
  </summary>
  <form class="nouveau-dossier__panneau" method="post" action="<?= url('dossiers') ?>">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <input type="hidden" name="retour" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">

    <label class="sr-only" for="nouveau-dossier-nom">Nom du dossier</label>
    <input type="text" id="nouveau-dossier-nom" name="nom" maxlength="120"
           placeholder="Nom du dossier" required>

    <?php
    /*
     * Sans aucun dossier, il n'y a rien à choisir : le premier va forcément à
     * la racine, et une liste d'un seul choix ne dirait rien à personne.
     */
    ?>
    <?php if ($dossiers !== []): ?>
      <label for="nouveau-dossier-parent">Ranger dans</label>
      <select id="nouveau-dossier-parent" name="parent_id">
        <option value="">— À la racine</option>
        <?php foreach ($dossiers as $d): ?>
          <option value="<?= (int) $d['id'] ?>"<?= $dossierId === (int) $d['id'] ? ' selected' : '' ?>>
            <?= retrait_dossier($d) ?><?= e($d['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>

    <button class="bouton bouton--petit bouton--bloc" type="submit">Créer le dossier</button>
  </form>
</details>
