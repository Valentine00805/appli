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
    <h1>💬 Amis</h1>
    <p>Retrouvez d’autres comptes par leur pseudo, et discutez avec vos amis.
      <a href="<?= url('notifications') ?>" data-fenetre>🔔 Être prévenu des messages</a></p>
  </div>
</div>

<?php if (!$aUnPseudo): ?>
  <div class="flash flash--info" style="margin-bottom:1rem">
    Vous n’avez pas encore de pseudo : personne ne peut vous trouver.
    <a href="<?= url('compte') ?>">Choisir mon pseudo</a>
  </div>
<?php endif; ?>

<div class="colonnes">
  <section class="carte">
    <h2 style="margin-top:0">Mes discussions</h2>
    <?php require __DIR__ . '/_liste.php'; ?>
  </section>

  <div class="pile">
    <section class="carte">
      <h2 style="margin-top:0">🔎 Chercher un pseudo</h2>
      <form method="get" action="<?= url('amis') ?>" class="fuseau-choix" role="search">
        <label class="sr-only" for="pseudo-recherche">Pseudo</label>
        <input type="search" id="pseudo-recherche" name="pseudo" value="<?= e($recherche) ?>"
               placeholder="Pseudo d’un ami" minlength="2" maxlength="<?= Auth::PSEUDO_MAX ?>"
               autocomplete="off" autocapitalize="none" spellcheck="false" style="flex:1 1 12rem">
        <button class="bouton" type="submit">Chercher</button>
      </form>

      <?php if ($recherche !== ''): ?>
        <?php if (mb_strlen($recherche) < 2): ?>
          <p class="champ__aide">Tapez au moins deux caractères.</p>
        <?php elseif ($resultats === []): ?>
          <p class="discret" style="margin:.75rem 0 0">Aucun pseudo ne contient « <?= e($recherche) ?> ».</p>
        <?php else: ?>
          <ul class="amis-resultats">
            <?php foreach ($resultats as $r): ?>
              <li class="amis-resultat">
                <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($r['pseudo'], 0, 1))) ?></span>
                <span class="amis-resultat__pseudo"><?= e($r['pseudo']) ?></span>
                <span class="actions">
                  <?php if ($r['etat'] === 'ami'): ?>
                    <a class="bouton bouton--petit bouton--secondaire" href="<?= url('amis/' . $r['id']) ?>">💬 Discuter</a>
                  <?php elseif ($r['etat'] === 'envoyee'): ?>
                    <span class="pastille">Demande envoyée</span>
                  <?php elseif ($r['etat'] === 'recue'): ?>
                    <?= $geste('accepter', $r['id'], 'Accepter', '') ?>
                  <?php else: ?>
                    <?= $geste('demande', $r['id'], '+ Ajouter en ami', '') ?>
                  <?php endif; ?>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <?php if ($recues !== []): ?>
      <section class="carte">
        <h2 style="margin-top:0">Demandes reçues <span class="compteur"><?= count($recues) ?></span></h2>
        <ul class="amis-resultats">
          <?php foreach ($recues as $d): ?>
            <li class="amis-resultat">
              <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $d['pseudo'], 0, 1))) ?></span>
              <span class="amis-resultat__pseudo"><?= e((string) $d['pseudo']) ?></span>
              <span class="actions">
                <?= $geste('accepter', (int) $d['id'], 'Accepter', '') ?>
                <?= $geste('retirer', (int) $d['id'], 'Refuser', 'bouton--discret') ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($envoyees !== []): ?>
      <section class="carte">
        <h2 style="margin-top:0">Demandes envoyées</h2>
        <ul class="amis-resultats">
          <?php foreach ($envoyees as $d): ?>
            <li class="amis-resultat">
              <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $d['pseudo'], 0, 1))) ?></span>
              <span class="amis-resultat__pseudo"><?= e((string) $d['pseudo']) ?>
                <span class="discret" style="font-size:.8rem;font-weight:400">· en attente</span></span>
              <span class="actions">
                <?= $geste('retirer', (int) $d['id'], 'Annuler', 'bouton--discret') ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  </div>
</div>
