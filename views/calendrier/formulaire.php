<?php
/**
 * @var ?array $evenement
 * @var array $matieres, $coursListe, $types
 * @var string $dateDefaut
 * @var ?int $typeDefaut
 */
$edition = $evenement !== null;
$action = $edition ? url('evenements/' . $evenement['id'] . '/modifier') : url('evenements/nouveau');

$valeur = static function (string $champ, string $defaut = '') use ($evenement, $edition): string {
    if ($edition) {
        return (string) ($evenement[$champ] ?? '');
    }
    return post($champ, $defaut);
};

$dateDebut = $edition ? substr((string) $evenement['debut'], 0, 10) : ($dateDefaut ?: date('Y-m-d'));
$dateFin   = $edition ? substr((string) $evenement['fin'], 0, 10) : $dateDebut;
$heureDebut = $edition ? substr((string) $evenement['debut'], 11, 5) : '08:00';
$heureFin   = $edition ? substr((string) $evenement['fin'], 11, 5) : '09:00';
$journee = $edition ? (int) $evenement['journee_entiere'] === 1 : false;
$typeActif = $edition ? entier_ou_null($evenement['type_id']) : $typeDefaut;
if ($typeActif === null && $types !== []) {
    $typeActif = (int) $types[0]['id'];
}
$coursActif = $edition ? entier_ou_null($evenement['cours_id']) : entier_ou_null($_GET['cours'] ?? null);
$matiereActive = $edition ? entier_ou_null($evenement['matiere_id']) : null;
?>

<div class="entete-page">
  <div>
    <p class="discret" style="margin-bottom:.35rem">
      <a href="<?= url('calendrier', ['date' => $dateDebut]) ?>">← Retour au calendrier</a>
    </p>
    <h1><?= $edition ? "Modifier l'évènement" : 'Nouvel évènement' ?></h1>
  </div>
</div>

<form method="post" action="<?= $action ?>">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

  <div class="colonnes">
    <div class="carte">
      <div class="champ">
        <label for="titre">Titre</label>
        <input type="text" id="titre" name="titre" required maxlength="200" autofocus
               placeholder="Contrôle de mathématiques" value="<?= e($valeur('titre')) ?>">
      </div>

      <fieldset>
        <legend>Type d'évènement</legend>
        <?php if ($types === []): ?>
          <p class="discret" style="margin:0">
            Vous n'avez aucun type. <a href="<?= url('organisation/types') ?>">En créer un</a> pour classer vos évènements.
          </p>
        <?php else: ?>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <?php foreach ($types as $t): ?>
              <label class="case">
                <input type="radio" name="type_id" value="<?= (int) $t['id'] ?>"<?= $typeActif === (int) $t['id'] ? ' checked' : '' ?>>
                <span class="pastille" style="background:<?= e($t['couleur']) ?>;color:<?= e(couleur_texte($t['couleur'])) ?>">
                  <?= e($t['icone'] . ' ' . $t['nom']) ?>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="champ__aide" style="margin-top:.5rem">
            <a href="<?= url('organisation/types') ?>">Gérer les types d'évènement</a>
          </p>
        <?php endif; ?>
      </fieldset>

      <div class="ligne-champs">
        <div class="champ">
          <label for="date_debut">Date de début</label>
          <input type="date" id="date_debut" name="date_debut" required value="<?= e($dateDebut) ?>">
        </div>
        <div class="champ">
          <label for="date_fin">Date de fin</label>
          <input type="date" id="date_fin" name="date_fin" value="<?= e($dateFin) ?>">
        </div>
      </div>

      <label class="case" style="margin-bottom:1rem">
        <input type="checkbox" id="journee_entiere" name="journee_entiere" value="1"<?= $journee ? ' checked' : '' ?>>
        Journée entière
      </label>

      <div class="ligne-champs" id="bloc-heures">
        <div class="champ">
          <label for="heure_debut">Heure de début</label>
          <input type="time" id="heure_debut" name="heure_debut" value="<?= e($heureDebut) ?>">
        </div>
        <div class="champ">
          <label for="heure_fin">Heure de fin</label>
          <input type="time" id="heure_fin" name="heure_fin" value="<?= e($heureFin) ?>">
        </div>
      </div>

      <div class="champ">
        <label for="lieu">Lieu</label>
        <input type="text" id="lieu" name="lieu" maxlength="160" placeholder="Salle B203, amphi, à la maison…"
               value="<?= e($valeur('lieu')) ?>">
      </div>

      <div class="champ">
        <label for="description">Notes</label>
        <textarea id="description" name="description" style="min-height:120px"
                  placeholder="Chapitres à réviser, matériel à apporter…"><?= e($valeur('description')) ?></textarea>
      </div>
    </div>

    <div class="pile">
      <div class="carte">
        <div class="champ">
          <label for="matiere_id">Matière</label>
          <select id="matiere_id" name="matiere_id">
            <option value="">— Aucune —</option>
            <?php foreach ($matieres as $m): ?>
              <option value="<?= (int) $m['id'] ?>"<?= $matiereActive === (int) $m['id'] ? ' selected' : '' ?>>
                <?= e($m['nom']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="champ__aide">Donne sa couleur à l'évènement dans le calendrier.</span>
        </div>

        <div class="champ">
          <label for="cours_id">Cours lié</label>
          <select id="cours_id" name="cours_id">
            <option value="">— Aucun —</option>
            <?php foreach ($coursListe as $c): ?>
              <option value="<?= (int) $c['id'] ?>"<?= $coursActif === (int) $c['id'] ? ' selected' : '' ?>>
                <?= e($c['titre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="champ__aide">Pratique pour retrouver ses notes le jour J.</span>
        </div>
      </div>

      <button class="bouton bouton--bloc" type="submit">
        <?= $edition ? 'Enregistrer' : 'Ajouter au calendrier' ?>
      </button>
      <a class="bouton bouton--secondaire bouton--bloc"
         href="<?= url('calendrier', ['date' => $dateDebut]) ?>">Annuler</a>
    </div>
  </div>
</form>

<?php if ($edition): ?>
  <form method="post" action="<?= url('evenements/' . $evenement['id'] . '/supprimer') ?>"
        data-confirmation="Supprimer cet évènement ?" style="margin-top:1rem;max-width:320px">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <button class="bouton bouton--danger" type="submit">Supprimer cet évènement</button>
  </form>
<?php endif; ?>
