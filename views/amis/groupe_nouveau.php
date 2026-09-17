<?php
/**
 * Créer un groupe : un nom, et les amis à y inviter.
 *
 * @var list<array> $amis
 * @var bool $aUnPseudo
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <h1 style="margin:0">👥 Nouveau groupe</h1>
    <p class="discret" style="margin:.15rem 0 0">Une discussion à plusieurs, avec vos amis.</p>
  </div>
</div>

<?php if (!$aUnPseudo): ?>
  <div class="flash flash--info">
    Choisissez d’abord un pseudo : c’est lui que verront les membres du groupe.
    <a href="<?= url('compte') ?>">Choisir mon pseudo</a>
  </div>
<?php elseif ($amis === []): ?>
  <p class="discret">Il vous faut au moins un ami pour créer un groupe. Cherchez un pseudo sur la page « Amis ».</p>
<?php else: ?>
  <form method="post" action="<?= url('groupes') ?>" class="carte groupe-formulaire">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <div class="champ">
      <label for="groupe-nom">Nom du groupe</label>
      <input type="text" id="groupe-nom" name="nom" required maxlength="<?= Conversations::NOM_MAX ?>"
             placeholder="Ex. : Révisions de maths" autocomplete="off">
    </div>
    <fieldset class="groupe-choix">
      <legend class="legende">Amis à ajouter</legend>
      <ul class="groupe-choix__liste">
        <?php foreach ($amis as $a): ?>
          <li>
            <label class="groupe-choix__ami">
              <input type="checkbox" name="membres[]" value="<?= (int) $a['id'] ?>">
              <?= Amis::avatar((int) $a['id'], (string) $a['pseudo']) ?>
              <span><?= e((string) $a['pseudo']) ?></span>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="champ__aide">Au moins un ami, <?= Conversations::MEMBRES_MAX - 1 ?> au plus. Vous en serez l’administrateur.</p>
    </fieldset>
    <div class="actions">
      <button class="bouton" type="submit">Créer le groupe</button>
    </div>
  </form>
<?php endif; ?>
