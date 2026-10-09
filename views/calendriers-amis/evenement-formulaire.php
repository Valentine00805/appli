<?php
/**
 * Écrire ou modifier un évènement d'un calendrier partagé : titre, dates, heures, lieu, notes. Pas de rappels ni de répétition : ce
 * calendrier est commun, et chacun garde ses propres notifications pour ses évènements personnels.
 *
 * @var array<string, mixed> $calendrier
 * @var ?array<string, mixed> $evenement  l'évènement modifié, ou null pour un nouveau
 * @var string $date  la date proposée (Y-m-d)
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$edition = $evenement !== null;
$journee = $edition && (int) $evenement['journee_entiere'] === 1;
$dateDebut = $edition ? substr((string) $evenement['debut'], 0, 10) : $date;
$dateFin = $edition ? substr((string) $evenement['fin'], 0, 10) : $date;
$heureDebut = $edition && !$journee ? substr((string) $evenement['debut'], 11, 5) : '08:00';
$heureFin = $edition && !$journee ? substr((string) $evenement['fin'], 11, 5) : '09:00';
$action = $edition
    ? url('calendriers-amis/evenements/' . (int) $evenement['id'])
    : url('calendriers-amis/' . (int) $calendrier['id'] . '/evenements');
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div style="display:flex;align-items:center;gap:.7rem">
    <span class="cam-pastille cam-pastille--grande" style="background:<?= e($calendrier['couleur']) ?>" aria-hidden="true"></span>
    <div>
      <h1 style="margin:0"><?= e($edition ? t('cam.evt_modifier') : t('cam.evt_nouveau')) ?></h1>
      <p class="discret" style="margin:.15rem 0 0">👥 <?= e($calendrier['nom']) ?> · <?= e(tn('cam.membres_n', (int) $calendrier['membres'])) ?></p>
    </div>
  </div>
</div>

<form method="post" action="<?= e($action) ?>" class="carte"<?= $envoi ?>>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

  <div class="champ">
    <label for="titre"><?= e(t('evtf.titre')) ?></label>
    <input type="text" id="titre" name="titre" required maxlength="200" autocomplete="off"
           value="<?= e($edition ? (string) $evenement['titre'] : '') ?>" placeholder="<?= e(t('cam.evt_titre_exemple')) ?>">
  </div>

  <div class="ligne-champs">
    <div class="champ">
      <label for="date_debut"><?= e(t('evtf.date_debut')) ?></label>
      <input type="date" id="date_debut" name="date_debut" required value="<?= e($dateDebut) ?>">
    </div>
    <div class="champ">
      <label for="date_fin"><?= e(t('evtf.date_fin')) ?></label>
      <input type="date" id="date_fin" name="date_fin" value="<?= e($dateFin) ?>">
    </div>
  </div>

  <label class="case" style="margin-bottom:1rem">
    <input type="checkbox" id="journee_entiere" name="journee_entiere" value="1"<?= $journee ? ' checked' : '' ?>>
    <?= e(t('evtf.journee_entiere')) ?>
  </label>

  <div class="ligne-champs" id="bloc-heures">
    <div class="champ">
      <label for="heure_debut"><?= e(t('evtf.heure_debut')) ?></label>
      <input type="time" id="heure_debut" name="heure_debut" value="<?= e($heureDebut) ?>">
    </div>
    <div class="champ">
      <label for="heure_fin"><?= e(t('evtf.heure_fin')) ?></label>
      <input type="time" id="heure_fin" name="heure_fin" value="<?= e($heureFin) ?>">
    </div>
  </div>

  <div class="champ">
    <label for="lieu"><?= e(t('evtf.lieu')) ?></label>
    <input type="text" id="lieu" name="lieu" maxlength="160" value="<?= e($edition ? (string) ($evenement['lieu'] ?? '') : '') ?>"
           placeholder="<?= e(t('evtf.lieu_exemple')) ?>">
  </div>

  <div class="champ">
    <label for="description"><?= e(t('evtf.notes')) ?></label>
    <textarea id="description" name="description" style="min-height:120px" data-texte-riche
              placeholder="<?= e(t('evtf.notes_exemple')) ?>"><?= e($edition ? TexteRiche::pourEditeur((string) ($evenement['description'] ?? '')) : '') ?></textarea>
  </div>

  <div class="actions">
    <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
    <a class="bouton bouton--secondaire"
       href="<?= url($edition ? 'calendriers-amis/evenements/' . (int) $evenement['id'] : 'calendriers-amis/' . (int) $calendrier['id']) ?>"
       <?= $dansUneFenetre ? 'data-fenetre' : '' ?>><?= e(t('commun.annuler')) ?></a>
  </div>
</form>
