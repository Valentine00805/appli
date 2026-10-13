<?php
/**
 * Créer ou modifier un groupe de calendriers : son nom, et les calendriers qu'il réunit dans le volet du calendrier.
 *
 * @var ?array{id: int, nom: string, replie: bool, calendriers: list<int>} $groupe  null : un nouveau groupe
 * @var list<array<string, mixed>> $calendriers  mes calendriers partagés
 * @var list<array{id: int, nom: string}> $groupes  mes groupes
 * @var string $retour  la page du calendrier qu'on regardait (pour y revenir)
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$nouveau = $groupe === null;
$nomsDesGroupes = array_column($groupes, 'nom', 'id');
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <h1 style="margin:0">📁 <?= e(t($nouveau ? 'cam.gr.nouveau_titre' : 'cam.gr.modifier_titre')) ?></h1>
    <p class="discret" style="margin:.15rem 0 0"><?= e(t('cam.gr.sous_titre')) ?></p>
  </div>
</div>

<form method="post" action="<?= $nouveau ? url('calendriers-amis/groupes') : url('calendriers-amis/groupes/' . (int) $groupe['id'] . '/modifier') ?>" class="carte groupe-formulaire"<?= $envoi ?>>
  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
  <input type="hidden" name="retour" value="<?= e($retour) ?>">
  <div class="champ">
    <label for="cam-groupe-nom"><?= e(t('cam.gr.nom')) ?></label>
    <input type="text" id="cam-groupe-nom" name="nom" required maxlength="<?= CalendriersAmis::GROUPE_NOM_MAX ?>"
           value="<?= e((string) ($groupe['nom'] ?? '')) ?>" placeholder="<?= e(t('cam.gr.nom_exemple')) ?>" autocomplete="off">
  </div>
  <div class="champ">
    <span class="legende"><?= e(t('cam.gr.calendriers')) ?></span>
    <?php if ($calendriers === []): ?>
      <p class="champ__aide" style="margin:0"><?= e(t('cam.gr.aucun_calendrier')) ?></p>
    <?php else: ?>
      <ul class="groupe-choix__liste">
        <?php foreach ($calendriers as $c): ?>
          <?php
          $dedans = $groupe !== null && in_array($c['id'], $groupe['calendriers'], true);
          $ailleurs = !$dedans && $c['groupe_id'] !== null ? ($nomsDesGroupes[$c['groupe_id']] ?? null) : null;
          ?>
          <li>
            <label class="groupe-choix__ami">
              <input type="checkbox" name="calendriers[]" value="<?= (int) $c['id'] ?>"<?= $dedans ? ' checked' : '' ?>>
              <span class="cam-pastille" style="background:<?= e((string) $c['couleur']) ?>" aria-hidden="true"></span>
              <span><?= e((string) $c['nom']) ?></span>
              <?php if ($ailleurs !== null): ?><span class="discret"><?= e(t('cam.gr.dans_autre', ['nom' => (string) $ailleurs])) ?></span><?php endif; ?>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="champ__aide"><?= e(t('cam.gr.aide')) ?></p>
    <?php endif; ?>
  </div>
  <div class="actions">
    <button class="bouton" type="submit"><?= e(t($nouveau ? 'cam.gr.creer' : 'cam.gr.enregistrer')) ?></button>
    <a class="bouton bouton--secondaire" href="<?= e($retour !== '' ? $retour : url('calendrier')) ?>"<?= $dansUneFenetre ? ' data-fermer' : '' ?>><?= e(t('commun.annuler')) ?></a>
  </div>
</form>

<?php if (!$nouveau): ?>
  <form method="post" action="<?= url('calendriers-amis/groupes/' . (int) $groupe['id'] . '/supprimer') ?>"<?= $envoi ?>
        data-confirmation="<?= e(t('cam.gr.supprimer_confirmation', ['nom' => $groupe['nom']])) ?>" style="margin-top:.8rem">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <input type="hidden" name="retour" value="<?= e($retour) ?>">
    <button class="bouton bouton--discret bouton--danger" type="submit">🗑️ <?= e(t('cam.gr.supprimer')) ?></button>
  </form>
<?php endif; ?>