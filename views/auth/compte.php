<?php
/** @var array $stats */
$moi = Auth::utilisateur();
?>

<div class="entete-page">
  <div>
    <h1>Mon compte</h1>
    <p><?= e((string) $moi['email']) ?> — inscrit le <?= e(date_fr((string) $moi['created_at'], false)) ?></p>
  </div>
</div>

<div class="grille grille--4" style="margin-bottom:1.5rem">
  <div class="carte stat"><div class="stat__valeur"><?= (int) $stats['cours'] ?></div><div class="stat__libelle">cours</div></div>
  <div class="carte stat"><div class="stat__valeur"><?= (int) $stats['matieres'] ?></div><div class="stat__libelle">matières</div></div>
  <div class="carte stat"><div class="stat__valeur"><?= (int) $stats['evenements'] ?></div><div class="stat__libelle">évènements</div></div>
  <div class="carte stat">
    <div class="stat__valeur"><?= (int) $stats['fichiers'] ?></div>
    <div class="stat__libelle">fichiers · <?= e(taille_lisible((int) $stats['octets'])) ?></div>
  </div>
</div>

<div class="colonnes">
  <div class="carte">
    <h2>Changer de mot de passe</h2>
    <form method="post" action="<?= url('compte/mot-de-passe') ?>">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

      <div class="champ">
        <label for="mot_de_passe_actuel">Mot de passe actuel</label>
        <input type="password" id="mot_de_passe_actuel" name="mot_de_passe_actuel" required
               autocomplete="current-password">
      </div>

      <div class="ligne-champs">
        <div class="champ">
          <label for="nouveau_mot_de_passe">Nouveau mot de passe</label>
          <input type="password" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" required minlength="8"
                 autocomplete="new-password">
        </div>
        <div class="champ">
          <label for="nouveau_mot_de_passe_confirmation">Confirmation</label>
          <input type="password" id="nouveau_mot_de_passe_confirmation" name="nouveau_mot_de_passe_confirmation"
                 required minlength="8" autocomplete="new-password">
        </div>
      </div>

      <button class="bouton" type="submit">Mettre à jour</button>
    </form>
  </div>

  <div class="carte">
    <h2>Où sont mes données ?</h2>
    <p class="discret">
      Tout est stocké sur cet ordinateur : les textes de cours dans la base MySQL
      <code>mon_appli_cours</code>, les fichiers joints dans le dossier
      <code>storage/uploads</code> de l'application.
    </p>
    <p class="discret">
      Pour une sauvegarde complète, copiez ce dossier et exportez la base
      (par exemple depuis phpMyAdmin).
    </p>
  </div>
</div>
