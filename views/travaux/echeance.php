<?php
/**
 * Modifier une échéance du groupe, dans une fenêtre par-dessus la liste.
 *
 * @var array $projet
 * @var array $echeance
 * @var list<array> $types  les types d'échéance du projet
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$envoi = $dansUneFenetre ? ' data-envoi-fenetre data-fermer-apres' : '';
$id = (int) $echeance['id'];
$retour = url('travaux/' . (int) $projet['id'] . '/echeances');
?>
<div class="entete-page" data-large>
  <div>
    <?php if (!$dansUneFenetre): ?>
      <p style="margin:0 0 .3rem"><a href="<?= $retour ?>">← <?= e((string) $projet['nom']) ?></a></p>
    <?php endif; ?>
    <h1>✎ <?= e((string) $echeance['titre']) ?></h1>
    <p><?= e((string) $projet['nom']) ?></p>
  </div>
</div>

<form method="post"<?= $envoi ?> action="<?= url('travaux/echeances/' . $id . '/modifier') ?>" class="carte travaux-formulaire">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <?= Vue::rendre('travaux/_champs_echeance', ['suffixe' => (string) $id, 'e' => $echeance, 'types' => $types]) ?>
  <p class="champ__aide" style="margin-top:0">Le changement arrive dans le calendrier de chaque membre ; les rappels qu’il a réglés restent les siens.</p>
  <p class="actions">
    <button class="bouton" type="submit">Enregistrer</button>
    <a class="bouton bouton--secondaire" href="<?= $retour ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>>Annuler</a>
  </p>
</form>

<form method="post"<?= $envoi ?> action="<?= url('travaux/echeances/' . $id . '/supprimer') ?>" style="margin-top:.75rem"
      data-confirmation="Retirer « <?= e((string) $echeance['titre']) ?> » ? Elle quittera aussi le calendrier de chaque membre.">
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <button class="bouton bouton--danger bouton--petit" type="submit">Supprimer cette échéance</button>
</form>
