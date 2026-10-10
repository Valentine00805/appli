<?php
/**
 * La fenêtre « Partager » d'un cours ou d'un fichier : d'abord à ses amis (et
 * ses groupes), puis par un lien, pour ceux qui n'ont pas de compte.
 *
 * @var string $type    « cours » ou « fichier »
 * @var string $mot     le même, tel qu'il s'écrit dans l'adresse
 * @var array $cible
 * @var list<array> $amis
 * @var list<array> $groupes
 * @var list<array{id: int, pseudo: string, droit: string}> $destinataires
 * @var ?list<array{id: int, nom: string, lien_id: ?int}> $projets  mes travaux de groupe (cours et dossiers seulement), null sinon
 * @var list<array> $commentaires  ce qu'on a écrit sous ce document
 * @var ?string $lien
 * @var int $vues
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$surPlace = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$csrf = Session::jetonCsrf();
$base = 'partager/' . $mot . '/' . (int) $cible['id'];
$ontAcces = array_flip(array_column($destinataires, 'id'));
// Les droits qu'on peut donner : un fichier ou un évènement ne se modifie pas.
$droitsPossibles = array_values(array_filter(Partages::DROITS, static fn (string $d): bool => !(in_array($type, ['fichier', 'evenement'], true) && $d === 'modification')));
$icone = match ($type) {
    'cours' => '📘',
    'fiche' => '📝',
    'dossier' => (string) $cible['icone'],
    'evenement' => '📅',
    default => Fichiers::icone((string) $cible['mime'], (string) $cible['nom_origine']),
};
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <h1 style="margin:0"><?= Partages::icone() ?> <?= e(t('pt.partager')) ?></h1>
    <p class="discret" style="margin:.15rem 0 0"><?= e($icone) ?> <?= e((string) $cible['titre']) ?></p>
  </div>
</div>

<?php
/*
 * À qui : ses amis et ses groupes de discussion, un travail de groupe, ou un lien public pour ceux qui n'ont pas de compte. Un onglet par
 * destination, une seule à la fois, comme dans « Partager plusieurs ». S'ouvre d'abord celle où l'on a des gens à qui parler (amis, groupes, ou
 * ceux qui ont déjà accès), sinon le travail de groupe, sinon le lien.
 */
$destinations = ['amis' => ['👥', 'pt.dest_amis']];
if (($projets ?? null) !== null) { $destinations['projets'] = ['🤝', 'pt.dest_projets']; }
$destinations['lien'] = ['🔗', 'pt.dest_lien'];
$destActive = $amis !== [] || $groupes !== [] || $destinataires !== [] ? 'amis' : (isset($destinations['projets']) && $projets !== [] ? 'projets' : 'lien');
?>
<div data-dest-portee>
<div class="partage-genres" data-destinations>
  <p class="legende partage-genres__titre" id="partage-dest-titre"><?= e(t('pt.dest_choisir')) ?></p>
  <div class="partage-genres__onglets" role="tablist" aria-labelledby="partage-dest-titre">
    <?php foreach ($destinations as $cle => [$icone, $libelle]): ?>
      <button type="button" class="partage-genre<?= $destActive === $cle ? ' partage-genre--actif' : '' ?>" role="tab"
              aria-selected="<?= $destActive === $cle ? 'true' : 'false' ?>" data-dest-onglet="<?= e($cle) ?>">
        <span aria-hidden="true"><?= $icone ?></span> <?= e(t($libelle)) ?>
      </button>
    <?php endforeach; ?>
  </div>
</div>

<section class="carte partage-section" data-dest-section="amis" role="tabpanel"<?= $destActive === 'amis' ? '' : ' hidden' ?>>
  <h2 style="margin-top:0"><?= e(t('pt.avec_mes_amis')) ?></h2>
  <?php if ($amis === [] && $groupes === []): ?>
    <p class="discret" style="margin:0"><?= e(t('pt.pas_encore_ami')) ?> <a href="<?= url('amis') ?>"><?= e(t('pt.chercher_pseudo')) ?></a></p>
  <?php else: ?>
    <form method="post" action="<?= url($base . '/amis') ?>"<?= $surPlace ?>>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <label class="discussions-recherche">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
          <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
        </svg>
        <span class="sr-only"><?= e(t('pt.chercher_ami_groupe')) ?></span>
        <input type="search" placeholder="<?= e(t('pt.chercher')) ?>" autocomplete="off" data-filtre-liste="[data-liste-partage]">
      </label>
      <ul class="groupe-choix__liste partage-liste" data-liste-partage>
        <?php foreach ($amis as $a): ?>
          <li data-nom="<?= e(mb_strtolower((string) $a['pseudo'])) ?>">
            <label class="groupe-choix__ami">
              <input type="checkbox" name="amis[]" value="<?= (int) $a['id'] ?>">
              <?= Amis::avatar((int) $a['id'], (string) $a['pseudo']) ?>
              <span class="partage-liste__nom"><?= e((string) $a['pseudo']) ?></span>
              <?php if (isset($ontAcces[(int) $a['id']])): ?><span class="pastille"><?= e(t('pt.a_acces')) ?></span><?php endif; ?>
              <?= Vue::rendre('partages/_droit_perso', ['champ' => 'droits_amis', 'qui' => (int) $a['id'], 'nom' => (string) $a['pseudo'], 'droitsPossibles' => $droitsPossibles]) ?>
            </label>
          </li>
        <?php endforeach; ?>
        <?php foreach ($groupes as $g): ?>
          <li data-nom="<?= e(mb_strtolower((string) $g['nom'])) ?>">
            <label class="groupe-choix__ami">
              <input type="checkbox" name="groupes[]" value="<?= (int) $g['id'] ?>">
              <?= Conversations::avatar((int) $g['id'], $g['photo_nom'] ?? null) ?>
              <span class="partage-liste__nom"><?= e((string) $g['nom']) ?> <span class="discret">· <?= e(t('pt.groupe')) ?></span></span>
              <?= Vue::rendre('partages/_droit_perso', ['champ' => 'droits_groupes', 'qui' => (int) $g['id'], 'nom' => (string) $g['nom'], 'droitsPossibles' => $droitsPossibles]) ?>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="discret" data-filtre-vide hidden style="margin:.4rem 0 0"><?= e(t('pt.personne_correspond')) ?></p>
      <?php // Ce qu'ils pourront en faire : le même droit pour tous, ou un droit par personne ; le partage le dit dès l'envoi. ?>
      <?= Vue::rendre('partages/_droits', ['droitsPossibles' => $droitsPossibles]) ?>
      <?php
      /*
       * Un évènement lié à un cours : on choisit de partager ou non le cours avec lui. Le cours n'est jamais envoyé sans
       * qu'on le demande — il peut contenir des notes qu'on ne veut pas donner. Même droit, mêmes destinataires.
       */
      if ($type === 'evenement' && !empty($cible['cours_id']) && (string) $cible['cours_titre'] !== ''): ?>
        <div class="champ partage-droits" style="margin-top:.75rem">
        <label class="partage-droits__choix partage-cours">
          <input type="checkbox" name="avec_cours" value="1">
          <span>
            <strong><?= e(t('pt.avec_cours', ['titre' => mb_strimwidth((string) $cible['cours_titre'], 0, 60, '…')])) ?></strong>
            <span class="discret"><?= e(t('pt.avec_cours_aide')) ?></span>
          </span>
        </label>
        </div>
      <?php endif; ?>
      <div class="champ" style="margin-top:.75rem">
        <label for="partage-texte"><?= e(t('pt.message_facultatif')) ?></label>
        <textarea id="partage-texte" name="texte" rows="2" maxlength="<?= Amis::MESSAGE_MAX ?>" placeholder="<?= e(t('pt.exemple_' . (in_array($type, ['cours', 'fiche', 'dossier', 'evenement'], true) ? $type : 'fichier'))) ?>"></textarea>
      </div>
      <button class="bouton" type="submit"><?= e(t('pt.envoyer')) ?></button>
      <p class="champ__aide" style="margin-bottom:0">
        <?= e(t('pt.envoi_aide_un')) ?><?php if (in_array($type, ['cours', 'fiche', 'dossier', 'evenement'], true)): ?> <?= e(t('pt.envoi_suite_' . $type)) ?><?php endif; ?>
      </p>
    </form>
  <?php endif; ?>

  <?php if ($destinataires !== []): ?>
    <h3 class="groupe-sous-titre"><?= e(t('pt.ont_acces')) ?></h3>
    <ul class="groupe-membres">
      <?php foreach ($destinataires as $d): ?>
        <li class="groupe-membres__ligne">
          <?= Amis::avatar($d['id'], $d['pseudo']) ?>
          <span class="groupe-membres__nom"><?= e($d['pseudo']) ?> <span class="pastille"><?= e(Partages::libelleDroit((string) $d['droit'])) ?></span></span>
          <?php
          /*
           * D'abord le droit tel qu'il est, et un bouton « Modifier » : c'est lui qui ouvre le panneau où l'on change le droit ou retire l'accès.
           * Rien ne se fait d'un clic distrait. Sans script : une case cachée porte l'état, et son étiquette est le bouton.
           */
          ?>
          <input type="checkbox" id="acces-ouvrir-<?= (int) $d['id'] ?>" class="acces-bascule sr-only">
          <label for="acces-ouvrir-<?= (int) $d['id'] ?>" class="bouton bouton--secondaire bouton--petit acces-bouton" role="button"><?= e(t('commun.modifier')) ?></label>
          <div class="acces-panneau">
            <form method="post" action="<?= url($base . '/acces/' . $d['id'] . '/droit') ?>"<?= $surPlace ?> class="acces-panneau__droit">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <label class="acces-panneau__etiquette" for="droit-<?= (int) $d['id'] ?>"><?= e(t('pt.ce_que_peut_faire', ['qui' => $d['pseudo']])) ?></label>
              <div class="acces-panneau__ligne">
                <select id="droit-<?= (int) $d['id'] ?>" name="droit">
                  <?php foreach (Partages::DROITS as $unDroit): ?>
                    <?php if (in_array($type, ['fichier', 'evenement'], true) && $unDroit === 'modification') { continue; } ?>
                    <option value="<?= e($unDroit) ?>"<?= $d['droit'] === $unDroit ? ' selected' : '' ?>><?= e(Partages::libelleDroit($unDroit)) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="bouton" type="submit"><?= e(t('pt.changer')) ?></button>
              </div>
            </form>
            <form method="post" action="<?= url($base . '/acces/' . $d['id'] . '/retirer') ?>"<?= $surPlace ?> class="acces-panneau__retrait">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('pt.retirer_acces')) ?></button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php if (($projets ?? null) !== null): ?>
  <?php // Un cours ou un dossier se met aussi dans un travail de groupe : ses membres le lisent. ?>
  <section class="carte partage-section" data-dest-section="projets" role="tabpanel"<?= $destActive === 'projets' ? '' : ' hidden' ?>>
    <h2 style="margin-top:0">👥 <?= e(t('pt.avec_projet')) ?></h2>
    <p class="discret" style="margin-top:0"><?= e(t($type === 'fichier' ? 'pt.projets_aide_fichier' : 'pt.projets_aide')) ?></p>
    <?php if ($projets === []): ?>
      <p class="discret" style="margin:0"><?= e(t('pt.projets_aucun')) ?> <a href="<?= url('travaux') ?>"><?= e(t('pt.projets_creer')) ?></a></p>
    <?php else: ?>
      <?php $libres = array_filter($projets, static fn (array $p): bool => $p['lien_id'] === null); ?>
      <?php $dedans = array_filter($projets, static fn (array $p): bool => $p['lien_id'] !== null); ?>
      <?php if ($libres !== []): ?>
        <form method="post" action="<?= url($base . '/projets') ?>"<?= $surPlace ?>>
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <ul class="groupe-choix__liste">
            <?php foreach ($libres as $p): ?>
              <li>
                <label class="groupe-choix__ami">
                  <input type="checkbox" name="projets[]" value="<?= (int) $p['id'] ?>">
                  <span class="avatar avatar--mini" aria-hidden="true">👥</span>
                  <span class="partage-liste__nom"><?= e($p['nom']) ?></span>
                </label>
              </li>
            <?php endforeach; ?>
          </ul>
          <button class="bouton" type="submit"><?= e(t('pt.projets_ajouter')) ?></button>
        </form>
      <?php endif; ?>
      <?php if ($dedans !== []): ?>
        <h3 class="groupe-sous-titre"><?= e(t('pt.projets_deja')) ?></h3>
        <ul class="groupe-membres">
          <?php foreach ($dedans as $p): ?>
            <li class="groupe-membres__ligne">
              <span class="avatar avatar--mini" aria-hidden="true">👥</span>
              <span class="groupe-membres__nom"><?= e($p['nom']) ?></span>
              <form method="post" action="<?= url($base . '/projets/' . (int) $p['lien_id'] . '/retirer') ?>"<?= $surPlace ?>>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('pt.projets_retirer')) ?></button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($commentaires !== []): ?>
  <?php // Les commentaires ont leur propre fenêtre, qui s'ouvre par-dessus. ?>
  <p class="discret" style="margin:0 0 1rem">
    💬 <a href="<?= url('partages/' . $mot . '/' . (int) $cible['id'] . '/commentaires') ?>"
          <?= $dansUneFenetre ? 'data-fenetre-dessus' : '' ?>><?= e(tn('pt.commentaires_nb', count($commentaires))) ?></a>
    <?= e(t('pt.sur_ce_document')) ?>
  </p>
<?php endif; ?>

<section class="carte partage-section" data-dest-section="lien" role="tabpanel"<?= $destActive === 'lien' ? '' : ' hidden' ?>>
  <h2 style="margin-top:0"><?= e(t('pt.avec_lien')) ?></h2>
  <p class="discret" style="margin-top:0"><?= e(t('pt.lien_un_aide')) ?></p>
  <?php if ($lien === null): ?>
    <form method="post" action="<?= url($base . '/lien') ?>"<?= $surPlace ?>>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <button class="bouton bouton--secondaire" type="submit"><?= e(t('pt.creer_lien_un')) ?></button>
    </form>
  <?php else: ?>
    <div class="partage-lien" data-partage-lien>
      <label class="sr-only" for="partage-lien-adresse"><?= e(t('pt.lien_de_partage')) ?></label>
      <input type="text" id="partage-lien-adresse" readonly value="<?= e($lien) ?>" data-partage-adresse>
      <button class="bouton" type="button" data-partage-copier><?= e(t('pt.copier')) ?></button>
      <button class="bouton bouton--secondaire" type="button" data-partage-natif hidden
              data-titre="<?= e((string) $cible['titre']) ?>"><?= Partages::icone() ?> <?= e(t('pt.envoyer_points')) ?></button>
    </div>
    <p class="champ__aide" data-partage-etat aria-live="polite"><?= e(t('pt.ouvert_fois_point', ['n' => $vues])) ?></p>
    <form method="post" action="<?= url($base . '/lien/desactiver') ?>"<?= $surPlace ?>>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('pt.desactiver_le_lien')) ?></button>
    </form>
  <?php endif; ?>
</section>
</div>
