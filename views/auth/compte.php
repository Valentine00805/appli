<?php
/** @var array $stats */
$moi = Auth::utilisateur();
?>

<div class="entete-page">
  <div>
    <h1>Mon compte</h1>
    <p>
      <?php if ((string) ($moi['pseudo'] ?? '') !== ''): ?><strong><?= e((string) $moi['pseudo']) ?></strong> · <?php endif; ?>
      <?= e((string) $moi['email']) ?> — inscrit le <?= e(date_fr((string) $moi['created_at'], false)) ?>
    </p>
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

<?php
/*
 * Le pseudo : choisi à l'inscription, il se change ici. Un compte d'avant
 * n'en a pas encore — la carte l'invite à en choisir un.
 *
 * Le pseudo se lit d'abord, sans champ : on ne le change qu'en passant par
 * « Modifier », et « Annuler » le remet tel qu'il était. Après un refus, le
 * champ reste ouvert avec ce qu'on avait tapé.
 */
$pseudoActuel = (string) ($moi['pseudo'] ?? '');
$pseudoSaisi = Session::reprendre('pseudo_saisi');
$enEdition = is_string($pseudoSaisi);
?>
<section class="carte" style="margin-bottom:1rem" id="pseudo-carte" data-pseudo>
  <h2 style="margin-top:0">🏷️ Mon pseudo</h2>

  <div class="pseudo-lecture" data-pseudo-lecture<?= $enEdition ? ' hidden' : '' ?>>
    <?php if ($pseudoActuel === ''): ?>
      <p class="discret" style="margin:0">Vous n’avez pas encore de pseudo.</p>
    <?php else: ?>
      <p class="pseudo-lecture__valeur"><?= e($pseudoActuel) ?></p>
    <?php endif; ?>
    <button class="bouton bouton--secondaire" type="button" data-pseudo-modifier>
      <?= $pseudoActuel === '' ? 'Créer mon pseudo' : '✎ Modifier' ?>
    </button>
  </div>

  <form method="post" action="<?= url('compte/pseudo') ?>" data-pseudo-edition<?= $enEdition ? '' : ' hidden' ?>>
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <label class="legende" for="pseudo"><?= $pseudoActuel === '' ? 'Votre pseudo' : 'Nouveau pseudo' ?></label>
    <div class="fuseau-choix">
      <input type="text" id="pseudo" name="pseudo" required autocomplete="nickname"
             minlength="<?= Auth::PSEUDO_MIN ?>" maxlength="<?= Auth::PSEUDO_MAX ?>"
             placeholder="Votre pseudo" data-valeur-actuelle="<?= e($pseudoActuel) ?>"
             value="<?= e($enEdition ? $pseudoSaisi : $pseudoActuel) ?>">
      <button class="bouton" type="submit">Enregistrer</button>
      <button class="bouton bouton--discret" type="button" data-pseudo-annuler>Annuler</button>
    </div>
    <p class="champ__aide">
      De <?= Auth::PSEUDO_MIN ?> à <?= Auth::PSEUDO_MAX ?> caractères : lettres, chiffres, point, tiret et tiret bas, sans espace.
      Unique : deux comptes ne peuvent pas porter le même.
    </p>
  </form>

  <p class="champ__aide" style="margin-bottom:0">
    Le nom sous lequel l’application vous appelle. Il sert aussi à se connecter, à la place de l’adresse e-mail.
  </p>
</section>

<?php
/*
 * Le fuseau horaire.
 *
 * Il ne sert pas qu'à l'affichage : c'est lui qui décide de l'heure à laquelle
 * un cours part dans Outlook et de celle à laquelle un rendez-vous en revient.
 * Mal réglé, il décale tout d'un bloc sans rien signaler.
 */
?>
<section class="carte" style="margin-bottom:1rem">
  <h2 style="margin-top:0">🕑 Fuseau horaire</h2>
  <p class="champ__aide" style="margin-top:0">
    Il est <strong><?= e(date('H:i')) ?></strong> pour l'application.
    Vos horaires d'évènements, ici comme dans Outlook, sont lus et écrits dans
    ce fuseau.
  </p>
  <form method="post" action="<?= url('compte/fuseau') ?>" class="fuseau-choix">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <label class="sr-only" for="fuseau">Fuseau horaire</label>
    <select id="fuseau" name="fuseau">
      <?php foreach ($fuseaux as $region => $liste): ?>
        <optgroup label="<?= e($region) ?>">
          <?php foreach ($liste as $f): ?>
            <option value="<?= e($f) ?>"<?= $f === $fuseau ? ' selected' : '' ?>>
              <?= e(str_replace('_', ' ', $f)) ?>
            </option>
          <?php endforeach; ?>
        </optgroup>
      <?php endforeach; ?>
    </select>
    <button class="bouton bouton--secondaire" type="submit">Enregistrer</button>
  </form>
  <p class="champ__aide">
    Changer de fuseau ne déplace pas ce qui est déjà noté : un cours à 8 h
    reste à 8 h, simplement lu dans la nouvelle heure. C'est ce qu'on veut en
    déménageant — moins en corrigeant un mauvais réglage, où il faudra
    reprendre les horaires à la main.
  </p>
</section>

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

  <div class="pile">
    <div class="carte">
      <h2>🔔 Notifications</h2>
      <p class="discret" style="margin-bottom:.8rem">
        Des rappels avant vos évènements et le matin de vos échéances, sur cet
        ordinateur ou votre téléphone — même application fermée.
      </p>
      <a class="bouton bouton--secondaire bouton--bloc" href="<?= url('notifications') ?>" data-fenetre>Régler les notifications</a>
    </div>

    <div class="carte" style="border-color:var(--accent)">
      <h2>💾 Sauvegarde</h2>
      <p class="discret" style="margin-bottom:.8rem">
        Vos données n'existent qu'à un seul endroit. Téléchargez-en une copie et
        rangez-la ailleurs : c'est la seule chose qui vous protège d'une panne.
      </p>
      <a class="bouton bouton--bloc" href="<?= url('compte/sauvegarde') ?>">
        Sauvegarder mes données
      </a>
    </div>

    <div class="carte">
      <h2>Où sont mes données ?</h2>
      <p class="discret">
        Les textes de cours, votre calendrier et vos comptes sont dans la base
        MySQL <code>mon_appli_cours</code> ; les fichiers joints dans le dossier
        <code>storage/uploads</code> de l'application.
      </p>
      <p class="discret" style="margin:0">
        Le code est sur GitHub, mais pas vos données : elles en sont exclues
        volontairement.
      </p>
    </div>
  </div>
</div>
