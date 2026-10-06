<?php
/**
 * Les amis : la liste des conversations, la recherche de pseudo et les demandes.
 *
 * @var string $recherche
 * @var list<array{id: int, pseudo: string, etat: string}> $resultats
 * @var list<array> $recues
 * @var list<array> $envoyees
 * @var list<array> $amis
 * @var array<int, array> $derniers
 * @var bool $aUnPseudo
 * @var list<array> $bloques
 */
$csrf = Session::jetonCsrf();

// Un bouton qui agit sur un compte : demander, accepter, refuser, annuler.
$geste = static function (string $action, int $compte, string $libelle, string $classe, ?string $confirmation = null)
    use ($csrf, $recherche): string {
    $adresse = $action === 'demande' ? url('amis/demande') : url('amis/' . $compte . '/' . $action);
    return '<form method="post" action="' . e($adresse) . '" class="en-ligne"'
        . ($confirmation !== null ? ' data-confirmation="' . e($confirmation) . '"' : '') . '>'
        . '<input type="hidden" name="_csrf" value="' . e($csrf) . '">'
        . '<input type="hidden" name="compte" value="' . $compte . '">'
        . '<input type="hidden" name="recherche" value="' . e($recherche) . '">'
        . '<button class="bouton bouton--petit ' . $classe . '" type="submit">' . e($libelle) . '</button>'
        . '</form>';
};
?>

<div class="entete-page">
  <div>
    <h1><?= e(t('ami.titre')) ?></h1>
    <p><?= e(t('ami.sous_titre')) ?>
      <a href="<?= url('notifications') ?>" data-fenetre><?= e(t('ami.etre_prevenu')) ?></a></p>
  </div>
</div>

<?php if (!$aUnPseudo): ?>
  <div class="flash flash--info" style="margin-bottom:1rem">
    <?= e(t('ami.pas_de_pseudo')) ?>
    <a href="<?= url('compte') ?>"><?= e(t('ami.choisir_pseudo')) ?></a>
  </div>
<?php endif; ?>

<div class="avec-barre">
<?= Vue::rendre('serveurs/_barre', ['barreActive' => 'messages']) ?>
<div class="colonnes avec-barre__page">
  <section class="carte">
    <?php $lienDemandes = '#demandes'; require __DIR__ . '/_entete_discussions.php'; ?>
    <?php require __DIR__ . '/_liste.php'; ?>
  </section>

  <div class="pile" id="demandes">
    <section class="carte">
      <h2 style="margin-top:0"><?= e(t('ami.chercher_pseudo')) ?></h2>
      <form method="get" action="<?= url('amis') ?>" class="fuseau-choix" role="search">
        <label class="sr-only" for="pseudo-recherche"><?= e(t('ami.pseudo')) ?></label>
        <input type="search" id="pseudo-recherche" name="pseudo" value="<?= e($recherche) ?>"
               placeholder="<?= e(t('ami.pseudo_exemple')) ?>" minlength="2" maxlength="<?= Auth::PSEUDO_MAX ?>"
               autocomplete="off" autocapitalize="none" spellcheck="false" style="flex:1 1 12rem">
        <button class="bouton" type="submit"><?= e(t('ami.chercher')) ?></button>
      </form>

      <?php if ($recherche !== ''): ?>
        <?php if (mb_strlen($recherche) < 2): ?>
          <p class="champ__aide"><?= e(t('ami.deux_caracteres')) ?></p>
        <?php elseif ($resultats === []): ?>
          <p class="discret" style="margin:.75rem 0 0"><?= e(t('ami.aucun_pseudo', ['quoi' => $recherche])) ?></p>
        <?php else: ?>
          <ul class="amis-resultats">
            <?php foreach ($resultats as $r): ?>
              <li class="amis-resultat">
                <?= Amis::avatar($r['id'], $r['pseudo']) ?>
                <span class="amis-resultat__pseudo"><?= e($r['pseudo']) ?></span>
                <span class="actions">
                  <?php if ($r['etat'] === 'bloque'): ?>
                    <span class="pastille"><?= e(t('ami.bloque')) ?></span>
                    <?= $geste('debloquer', $r['id'], t('ami.debloquer'), 'bouton--discret') ?>
                  <?php elseif ($r['etat'] === 'ami'): ?>
                    <a class="bouton bouton--petit bouton--secondaire" href="<?= url('amis/' . $r['id']) ?>"><?= e(t('ami.discuter')) ?></a>
                  <?php elseif ($r['etat'] === 'envoyee'): ?>
                    <span class="pastille"><?= e(t('ami.demande_envoyee')) ?></span>
                  <?php elseif ($r['etat'] === 'recue'): ?>
                    <?= $geste('accepter', $r['id'], t('ami.accepter'), '') ?>
                  <?php else: ?>
                    <?= $geste('demande', $r['id'], t('ami.ajouter'), '') ?>
                  <?php endif; ?>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <?php if ($bloques !== []): ?>
      <section class="carte">
        <h2 style="margin-top:0"><?= e(t('ami.bloques_titre')) ?></h2>
        <p class="champ__aide" style="margin-top:0"><?= e(t('ami.bloques_aide')) ?></p>
        <ul class="amis-resultats">
          <?php foreach ($bloques as $b): ?>
            <li class="amis-resultat">
              <?= Amis::avatar((int) $b['id'], (string) $b['pseudo']) ?>
              <span class="amis-resultat__pseudo"><?= e((string) $b['pseudo']) ?></span>
              <span class="actions">
                <?= $geste('debloquer', (int) $b['id'], t('ami.debloquer'), 'bouton--secondaire',
                    t('ami.debloquer_sur', ['nom' => $b['pseudo']])) ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($invitationsGroupes !== []): ?>
      <?php // Les invitations dans un groupe : on n'y entre qu'en acceptant. ?>
      <section class="carte" id="invitations-groupes">
        <h2 style="margin-top:0"><?= e(t('ami.invitations_groupes')) ?> <span class="compteur"><?= count($invitationsGroupes) ?></span></h2>
        <ul class="amis-resultats">
          <?php foreach ($invitationsGroupes as $inv): ?>
            <li class="amis-resultat">
              <?= Conversations::avatar((int) $inv['id'], $inv['photo_nom']) ?>
              <span class="amis-resultat__pseudo"><?= e((string) $inv['nom']) ?>
                <span class="discret" style="font-size:.8rem;font-weight:400"><?= e(tn('ami.membres', (int) $inv['membres'])) ?><?= $inv['par'] !== '' ? e(t('ami.invite_par', ['qui' => (string) $inv['par']])) : '' ?></span></span>
              <span class="actions">
                <form method="post" action="<?= url('groupes/' . (int) $inv['id'] . '/rejoindre') ?>">
                  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                  <button class="bouton bouton--petit" type="submit"><?= e(t('ami.rejoindre')) ?></button>
                </form>
                <form method="post" action="<?= url('groupes/' . (int) $inv['id'] . '/refuser') ?>">
                  <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
                  <button class="bouton bouton--petit bouton--discret" type="submit"><?= e(t('ami.refuser')) ?></button>
                </form>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($recues !== []): ?>
      <section class="carte">
        <h2 style="margin-top:0"><?= e(t('ami.demandes_recues')) ?> <span class="compteur"><?= count($recues) ?></span></h2>
        <ul class="amis-resultats">
          <?php foreach ($recues as $d): ?>
            <li class="amis-resultat">
              <?= Amis::avatar((int) $d['id'], (string) $d['pseudo']) ?>
              <span class="amis-resultat__pseudo"><?= e((string) $d['pseudo']) ?></span>
              <span class="actions">
                <?= $geste('accepter', (int) $d['id'], t('ami.accepter'), '') ?>
                <?= $geste('retirer', (int) $d['id'], t('ami.refuser'), 'bouton--discret') ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($envoyees !== []): ?>
      <section class="carte">
        <h2 style="margin-top:0"><?= e(t('ami.demandes_envoyees')) ?></h2>
        <ul class="amis-resultats">
          <?php foreach ($envoyees as $d): ?>
            <li class="amis-resultat">
              <?= Amis::avatar((int) $d['id'], (string) $d['pseudo']) ?>
              <span class="amis-resultat__pseudo"><?= e((string) $d['pseudo']) ?>
                <span class="discret" style="font-size:.8rem;font-weight:400"><?= e(t('ami.en_attente')) ?></span></span>
              <span class="actions">
                <?= $geste('retirer', (int) $d['id'], t('ami.annuler'), 'bouton--discret') ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  </div>
</div>
</div>
