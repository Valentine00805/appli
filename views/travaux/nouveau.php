<?php
/**
 * Un nouveau travail de groupe, ouvert en fenêtre par-dessus la liste.
 *
 * @var list<array> $amis
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
?>
<div class="entete-page">
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p style="margin:0 0 .3rem"><a href="<?= url('travaux') ?>">← Tous les travaux de groupe</a></p>
    <?php endif; ?>
    <h1>👥 Nouveau travail de groupe</h1>
  </div>
</div>

<form method="post" action="<?= url('travaux') ?>" class="carte travaux-formulaire"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <div class="champ">
    <label for="nom-projet">Nom</label>
    <input type="text" id="nom-projet" name="nom" required autofocus maxlength="<?= Travaux::NOM_MAX ?>"
           placeholder="Exposé d’histoire, projet de fin d’année…">
  </div>
  <div class="champ">
    <label for="description-projet">Le sujet, les consignes <span class="discret">(facultatif)</span></label>
    <textarea id="description-projet" name="description" rows="3" maxlength="2000"></textarea>
  </div>
  <div class="champ">
    <span class="legende">Inviter des amis <span class="discret">(ils acceptent ou refusent)</span></span>
    <?php if ($amis === []): ?>
      <p class="discret" style="margin:.3rem 0 0">Pas encore d’amis dans l’appli :
        <a href="<?= url('amis') ?>">en ajouter</a>. Vous pourrez aussi ajouter des personnes sans compte.</p>
    <?php else: ?>
      <label class="discussions-recherche">
        <span class="sr-only">Rechercher un ami</span>
        <input type="search" placeholder="Rechercher un ami" autocomplete="off" data-filtre-liste="[data-liste-amis-projet]">
      </label>
      <ul class="groupe-choix__liste partage-liste" data-liste-amis-projet>
        <?php foreach ($amis as $a): ?>
          <li data-nom="<?= e(mb_strtolower((string) $a['pseudo'])) ?>">
            <label class="groupe-choix__ami">
              <input type="checkbox" name="amis[]" value="<?= (int) $a['id'] ?>">
              <?= Amis::avatar((int) $a['id'], (string) $a['pseudo'], 'avatar--mini') ?>
              <span class="partage-liste__nom"><?= e((string) $a['pseudo']) ?></span>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="discret" data-filtre-vide hidden style="margin:.4rem 0 0">Aucun ami ne porte ce nom.</p>
    <?php endif; ?>
  </div>
  <p class="actions">
    <button class="bouton" type="submit">Créer</button>
    <a class="bouton bouton--secondaire" href="<?= url('travaux') ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>>Annuler</a>
  </p>
</form>
