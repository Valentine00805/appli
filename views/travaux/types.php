<?php
/**
 * Les types d'échéance du projet, réglés comme les types d'évènement :
 * nom, icône, couleur, rappels, et leur ordre dans le menu.
 *
 * @var array $projet
 * @var list<array> $types
 * @var list<string> $palette
 * @var list<string> $icones
 * @var string $onglet
 */
$dansUneFenetre = $dansUneFenetre ?? false;
// Dans la fenêtre, on y reste : les formulaires s'y enregistrent.
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$csrf = Session::jetonCsrf();
$dernier = count($types) - 1;

/** Les champs d'un type : nom, icône, couleur, rappels. */
$champs = static function (string $suffixe, ?array $t) use ($palette, $icones): string {
    $icone = (string) ($t['icone'] ?? $icones[0]);
    $couleur = strtolower((string) ($t['couleur'] ?? $palette[0]));
    $rappels = Rappels::lire((string) ($t['rappels'] ?? '1440'));
    // L'icône d'un type ancien peut ne plus être proposée : on la garde au choix.
    $choixIcones = in_array($icone, $icones, true) ? $icones : array_merge([$icone], $icones);
    ob_start(); ?>
    <div class="champ">
      <label for="nom-<?= e($suffixe) ?>">Nom</label>
      <input type="text" id="nom-<?= e($suffixe) ?>" name="nom" required maxlength="40"
             value="<?= e((string) ($t['nom'] ?? '')) ?>" placeholder="Projet, oral blanc, partiel…">
    </div>
    <div class="champ">
      <span class="legende">Icône</span>
      <div class="choix-icones">
        <?php foreach ($choixIcones as $j => $i): ?>
          <input type="radio" id="icone-<?= e($suffixe) ?>-<?= $j ?>" name="icone" value="<?= e($i) ?>"<?= $i === $icone ? ' checked' : '' ?>>
          <label for="icone-<?= e($suffixe) ?>-<?= $j ?>"><?= e($i) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="champ">
      <span class="legende">Couleur</span>
      <div class="choix-couleurs">
        <?php foreach ($palette as $j => $c): ?>
          <input type="radio" id="couleur-<?= e($suffixe) ?>-<?= $j ?>" name="couleur" value="<?= e($c) ?>"<?= $c === $couleur ? ' checked' : '' ?>>
          <label for="couleur-<?= e($suffixe) ?>-<?= $j ?>" style="background:<?= e($c) ?>" title="<?= e($c) ?>"></label>
        <?php endforeach; ?>
      </div>
    </div>
    <fieldset class="rappels-choix">
      <legend>🔔 Rappels</legend>
      <div class="rappels-choix__liste">
        <?php foreach (array_reverse(Rappels::DELAIS_COURTS, true) as $minutes => $court): ?>
          <label class="rappels-choix__option" title="<?= e(Rappels::DELAIS[$minutes]) ?>">
            <input type="checkbox" name="rappels[]" value="<?= (int) $minutes ?>"<?= in_array($minutes, $rappels, true) ? ' checked' : '' ?>>
            <span class="pastille"><?= e($court) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <span class="champ__aide">Ceux que prend l’échéance en arrivant dans le calendrier de chaque membre ;
        chacun peut ensuite changer les siens.</span>
    </fieldset>
    <?php return (string) ob_get_clean();
};
?>
<?= Vue::rendre('travaux/_onglets', ['projet' => $projet, 'onglet' => $onglet, 'dansUneFenetre' => $dansUneFenetre]) ?>

<div class="entete-page">
  <div>
    <p style="margin:0 0 .3rem"><a href="<?= url('travaux/' . (int) $projet['id'] . '/echeances') ?>"<?= $dansUneFenetre ? ' data-fenetre' : '' ?>>← Les échéances</a></p>
    <h2 style="margin:0">🏷️ Types d’échéance</h2>
    <p>Ils classent les échéances du groupe. Chacun a son icône, sa couleur, ses rappels et sa place dans le menu —
      pour tous les membres du projet.</p>
  </div>
</div>

<div class="<?= $dansUneFenetre ? 'pile' : 'colonnes' ?>">
  <div class="pile">
    <?php if ($types === []): ?>
      <div class="vide">
        <span class="vide__icone">🏷️</span>
        <p>Aucun type : les échéances seront « Sans type ». Créez-en un.</p>
      </div>
    <?php endif; ?>
    <?php foreach ($types as $i => $t): ?>
      <?php $id = (int) $t['id']; $n = (int) $t['nb_echeances']; ?>
      <section class="carte">
        <div class="matiere-carte">
          <span class="matiere-pastille" style="background:<?= e((string) $t['couleur']) ?>;display:grid;place-items:center;font-size:1.2rem">
            <?= e((string) $t['icone']) ?>
          </span>
          <div style="flex:1;min-width:0">
            <h3 style="margin:0 0 .15rem"><?= e((string) $t['nom']) ?></h3>
            <p class="discret" style="margin:0">
              <?= $n ?> échéance<?= $n > 1 ? 's' : '' ?>
              <?php $dire = Rappels::dire((string) $t['rappels']); ?>
              · 🔔 <?= $dire === '' ? 'sans rappel' : e($dire) ?>
            </p>
          </div>
          <div class="actions">
            <form method="post"<?= $envoi ?> action="<?= url('travaux/types/' . $id . '/deplacer') ?>" class="en-ligne">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="sens" value="haut">
              <button class="bouton bouton--discret bouton--petit" type="submit" title="Monter"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
            </form>
            <form method="post"<?= $envoi ?> action="<?= url('travaux/types/' . $id . '/deplacer') ?>" class="en-ligne">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="sens" value="bas">
              <button class="bouton bouton--discret bouton--petit" type="submit" title="Descendre"<?= $i === $dernier ? ' disabled' : '' ?>>↓</button>
            </form>
          </div>
        </div>
        <details class="travaux-modifier">
          <summary>Modifier</summary>
          <form method="post"<?= $envoi ?> action="<?= url('travaux/types/' . $id . '/modifier') ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <?= $champs((string) $id, $t) ?>
            <button class="bouton bouton--petit" type="submit">Enregistrer</button>
          </form>
          <form method="post"<?= $envoi ?> action="<?= url('travaux/types/' . $id . '/supprimer') ?>" style="margin-top:.75rem"
                data-confirmation="Supprimer le type « <?= e((string) $t['nom']) ?> » ? Ses échéances restent, sans type.">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button class="bouton bouton--danger bouton--petit" type="submit">Supprimer ce type</button>
          </form>
        </details>
      </section>
    <?php endforeach; ?>
  </div>

  <section class="carte">
    <h2>Nouveau type</h2>
    <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/types') ?>">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <?= $champs('nouveau', null) ?>
      <button class="bouton bouton--bloc" type="submit">Créer le type</button>
    </form>
  </section>
</div>
