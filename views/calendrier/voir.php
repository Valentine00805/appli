<?php
/**
 * Un évènement, en lecture.
 *
 * Ce qu'on vient y chercher tient en trois lignes : quand, où, quoi. Le reste
 * — la matière, le cours, la série, les agendas où il part — n'apparaît que
 * s'il y a quelque chose à en dire. Une fiche pleine de « — » ne se lit pas
 * plus vite qu'un formulaire.
 *
 * @var array $evenement
 * @var ?array $serie   la série dont il fait partie, s'il en fait partie
 * @var array $vises    les agendas où il part, sous leur nom
 * @var ?array $copie   la copie à soi qu'on en a faite
 * @var ?array $origine l'évènement dont il est la copie
 */
$couleur = couleur_evenement($evenement);
$journee = (int) $evenement['journee_entiere'] === 1;
$debut = new DateTimeImmutable((string) $evenement['debut']);
$fin = new DateTimeImmutable((string) $evenement['fin']);
$memeJour = $debut->format('Y-m-d') === $fin->format('Y-m-d');
$venuDAilleurs = (string) ($evenement['agenda_nom'] ?? '') !== '';

/** Une ligne de la fiche, tue quand elle n'a rien à dire. */
$ligne = static function (string $etiquette, string $valeur): string {
    if (trim(strip_tags($valeur)) === '') {
        return '';
    }

    return '<div class="fiche__ligne"><span class="fiche__etiquette">' . e($etiquette)
        . '</span><span class="fiche__valeur">' . $valeur . '</span></div>';
};
?>

<div class="entete-page">
  <div>
    <p class="discret" style="margin-bottom:.35rem">
      <a href="<?= url('calendrier', ['date' => $debut->format('Y-m-d')]) ?>">← Calendrier</a>
    </p>
    <h1 style="display:flex;align-items:center;gap:.6rem">
      <span class="fiche__teinte" style="background:<?= e($couleur) ?>"></span>
      <?= e(icone_evenement($evenement)) ?> <?= e((string) $evenement['titre']) ?>
    </h1>
  </div>

  <div class="actions">
    <a class="bouton" href="<?= url('evenements/' . (int) $evenement['id'] . '/modifier') ?>">
      ✎ Modifier
    </a>
  </div>
</div>

<div class="pile" style="max-width:44rem">
  <section class="carte fiche">
    <?php
    /*
     * Quand. Une journée entière ne se dit pas en heures, et deux dates
     * identiques ne se répètent pas.
     */
    if ($journee) {
        $quand = $memeJour
            ? ucfirst(date_fr($evenement['debut'], false)) . ' — toute la journée'
            : 'Du ' . date_fr($evenement['debut'], false) . ' au ' . date_fr($evenement['fin'], false);
    } elseif ($memeJour) {
        $quand = ucfirst(date_fr($evenement['debut'], false))
            . ', de ' . $debut->format('H:i') . ' à ' . $fin->format('H:i');
    } else {
        $quand = 'Du ' . date_fr($evenement['debut']) . ' au ' . date_fr($evenement['fin']);
    }
    echo $ligne('Quand', e($quand));

    if (!$journee) {
        $duree = $debut->diff($fin);
        $heures = $duree->days * 24 + $duree->h;
        echo $ligne('Durée', e(
            ($heures > 0 ? $heures . ' h' . ($duree->i > 0 ? ' ' . $duree->i : '') : $duree->i . ' min')
        ));
    }

    echo $ligne('Lieu', e((string) ($evenement['lieu'] ?? '')));
    echo $ligne('Type', (string) ($evenement['type_nom'] ?? '') === '' ? '' :
        '<span class="pastille" style="background:' . e((string) $evenement['type_couleur'])
        . ';color:' . e(couleur_texte((string) $evenement['type_couleur'])) . '">'
        . e((string) $evenement['type_icone'] . ' ' . (string) $evenement['type_nom']) . '</span>');
    echo $ligne('Matière', e((string) ($evenement['matiere_nom'] ?? '')));
    echo $ligne('Cours lié', (string) ($evenement['cours_titre'] ?? '') === '' ? '' :
        '<a href="' . url('cours/' . (int) $evenement['cours_id']) . '">'
        . e((string) $evenement['cours_titre']) . '</a>');
    echo $ligne('État', (int) $evenement['termine'] === 1 ? 'Terminé' : '');
    ?>

    <?php if ((string) ($evenement['description'] ?? '') !== ''): ?>
      <div class="fiche__notes">
        <span class="fiche__etiquette">Notes</span>
        <?php // « pre-line » garde déjà les retours à la ligne : les doubler
           // avec nl2br ferait un blanc entre chaque phrase. ?>
        <p><?= e((string) $evenement['description']) ?></p>
      </div>
    <?php endif; ?>
  </section>

  <?php
  /*
   * D'où il vient et où il va. Cette carte ne s'affiche que s'il y a quelque
   * chose à en dire : un évènement écrit ici, qui ne part nulle part et ne
   * répète rien, n'a pas de provenance à raconter.
   */
  $aDire = $venuDAilleurs || $serie !== null || $vises !== [] || $copie !== null || $origine !== null;
  ?>
  <?php if ($aDire): ?>
    <section class="carte fiche">
      <?php if ($venuDAilleurs): ?>
        <?= $ligne('Vient de',
            '<span class="fiche__teinte fiche__teinte--puce" style="background:'
            . e((string) ($evenement['agenda_couleur'] ?: '#94a3b8')) . '"></span> '
            . e((string) $evenement['agenda_nom'])
            . ((int) ($evenement['agenda_partage'] ?? 0) === 1
                ? ' <span class="discret">— agenda partagé</span>' : '')) ?>
        <p class="champ__aide" style="margin:.2rem 0 0">
          Cet évènement appartient à son agenda. Le modifier ici ne le change
          là-bas que si vous en êtes propriétaire.
        </p>
      <?php else: ?>
        <?= $ligne('Part dans', $vises === [] ? 'Mes évènements'
            : e(implode(', ', $vises))) ?>
      <?php endif; ?>

      <?php if ($serie !== null): ?>
        <?= $ligne('Répétition', e(
            ucfirst((string) $serie['frequence']) . ' · '
            . (int) $serie['occurrences'] . ' occurrence'
            . ((int) $serie['occurrences'] > 1 ? 's' : '')
            . ' jusqu’au ' . date('d/m/Y', strtotime((string) $serie['jusqu_au'])))) ?>
      <?php endif; ?>

      <?php if ($copie !== null): ?>
        <?= $ligne('Copie à moi',
            '<a href="' . url('evenements/' . (int) $copie['id']) . '">'
            . e((string) $copie['titre']) . '</a>') ?>
      <?php endif; ?>

      <?php if ($origine !== null): ?>
        <?= $ligne('Copié depuis',
            '<a href="' . url('evenements/' . (int) $origine['id']) . '">'
            . e((string) $origine['titre']) . '</a>') ?>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <div class="fiche__actions">
    <form method="post" action="<?= url('evenements/' . (int) $evenement['id'] . '/termine') ?>">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <input type="hidden" name="retour" value="<?= e('evenements/' . (int) $evenement['id']) ?>">
      <button class="bouton bouton--secondaire" type="submit">
        <?= (int) $evenement['termine'] === 1 ? '☐ Marquer comme à faire' : '☑ Marquer comme terminé' ?>
      </button>
    </form>

    <form method="post" action="<?= url('evenements/' . (int) $evenement['id'] . '/supprimer') ?>"
          data-confirmation="Supprimer cet évènement ?">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <button class="bouton bouton--danger bouton--petit" type="submit">Supprimer</button>
    </form>
  </div>
</div>
