<?php
/**
 * Partager plusieurs documents d'un coup : on coche des cours, des fiches de
 * révision, des dossiers et des fichiers, puis des amis.
 *
 * Un envoi, une carte par document dans la discussion, et un seul accès par
 * document : c'est le même partage qu'un par un, fait en une fois. Le lien
 * public, lui, reste propre à un document — il se crée sur sa page.
 *
 * @var list<array> $mesCours     mes cours, du plus récemment modifié au plus ancien
 * @var list<array> $mesFichiers  mes fichiers joints, du plus récent au plus ancien
 * @var list<array> $mesDossiers  mes dossiers, dans l'ordre de l'arborescence
 * @var list<array> $mesFiches    mes cours qui ont une fiche de révision
 * @var list<array> $mesLots      mes liens de plusieurs documents, le dernier d'abord
 * @var array<int, int> $choisis, $choisisFichiers, $choisisDossiers, $choisisFiches  ce qui est coché d'avance
 * @var list<array> $amis
 * @var list<array> $groupes
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$csrf = Session::jetonCsrf();
$droitsPossibles = Partages::DROITS;   // les droits qu'on peut donner, pour tous ou à chacun
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <h1 style="margin:0"><?= Partages::icone() ?> <?= e(t('pt.plusieurs_titre')) ?></h1>
    <p class="discret" style="margin:.15rem 0 0"><?= e(t('pt.plusieurs_aide', ['max' => Partages::LOT_MAX])) ?></p>
  </div>
</div>

<?php if ($mesCours === [] && $mesFichiers === [] && $mesDossiers === [] && $mesFiches === []): ?>
  <section class="carte">
    <p class="discret" style="margin:0"><?= e(t('pt.rien_a_partager')) ?></p>
  </section>
<?php else: ?>
  <?php
  /*
   * D'abord de quoi il s'agit (un cours, une fiche de révision, un dossier, un fichier), puis ce qu'on en choisit. Une seule liste se montre à la
   * fois ; ce qui est coché dans les autres reste coché, et part avec l'envoi : on peut mêler les genres. Le nombre de cases cochées se lit sur
   * chaque onglet. Celui d'où l'on vient (cases cochées d'avance) s'ouvre le premier, sinon le premier genre qui a quelque chose à partager.
   */
  $genres = array_filter([
      'cours' => [$mesCours, '📘', 'pt.genre_cours', count($choisis)],
      'fiches' => [$mesFiches, '📝', 'pt.genre_fiches', count($choisisFiches)],
      'dossiers' => [$mesDossiers, '📁', 'pt.genre_dossiers', count($choisisDossiers)],
      'fichiers' => [$mesFichiers, '📎', 'pt.genre_fichiers', count($choisisFichiers)],
  ], static fn (array $g): bool => $g[0] !== []);
  $genreActif = (string) (array_key_first(array_filter($genres, static fn (array $g): bool => $g[3] > 0)) ?? array_key_first($genres));
  ?>
  <form method="post" action="<?= url('partager/plusieurs/amis') ?>"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <div class="partage-genres" data-genres>
      <p class="legende partage-genres__titre" id="partage-genres-titre"><?= e(t('pt.genre_choisir')) ?></p>
      <div class="partage-genres__onglets" role="tablist" aria-labelledby="partage-genres-titre">
        <?php foreach ($genres as $cle => [, $icone, $libelle, $coches]): ?>
          <button type="button" class="partage-genre<?= $genreActif === $cle ? ' partage-genre--actif' : '' ?>" role="tab"
                  aria-selected="<?= $genreActif === $cle ? 'true' : 'false' ?>" data-genre-onglet="<?= e($cle) ?>">
            <span aria-hidden="true"><?= $icone ?></span> <?= e(t($libelle)) ?>
            <span class="partage-genre__compte" data-genre-compte<?= $coches > 0 ? '' : ' hidden' ?>><?= $coches > 0 ? (int) $coches : '' ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($mesCours !== []): ?>
    <section class="carte partage-section" data-genre-section="cours" role="tabpanel"<?= $genreActif === 'cours' ? '' : ' hidden' ?>>
      <h2 style="margin-top:0"><?= e(t('pt.cours_titre')) ?></h2>
      <label class="discussions-recherche">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
          <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
        </svg>
        <span class="sr-only"><?= e(t('pt.chercher_mes_cours')) ?></span>
        <input type="search" placeholder="<?= e(t('pt.chercher_cours')) ?>" autocomplete="off" data-filtre-liste="[data-liste-mes-cours]">
      </label>
      <p style="margin:.5rem 0 0">
        <button class="bouton bouton--discret bouton--petit" type="button"
                data-cocher-tout="[data-liste-mes-cours]"><?= e(t('pt.cocher_tout')) ?></button>
      </p>
      <ul class="groupe-choix__liste partage-liste" data-liste-mes-cours>
        <?php foreach ($mesCours as $c): ?>
          <li data-nom="<?= e(mb_strtolower((string) $c['titre'] . ' ' . (string) ($c['matiere_nom'] ?? '') . ' ' . (string) ($c['dossier_nom'] ?? ''))) ?>">
            <label class="groupe-choix__ami">
              <input type="checkbox" name="cours[]" value="<?= (int) $c['id'] ?>"<?= isset($choisis[(int) $c['id']]) ? ' checked' : '' ?>>
              <span aria-hidden="true">📘</span>
              <span class="partage-liste__nom">
                <?= e((string) $c['titre']) ?>
                <span class="discret">
                  <?php if (($c['matiere_nom'] ?? null) !== null): ?>· <?= e((string) $c['matiere_nom']) ?><?php endif; ?>
                  <?php if (($c['dossier_nom'] ?? null) !== null): ?>· <?= e((string) $c['dossier_nom']) ?><?php endif; ?>
                </span>
              </span>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="discret" data-filtre-vide hidden style="margin:.4rem 0 0"><?= e(t('pt.aucun_cours_nom')) ?></p>
    </section>
    <?php endif; ?>

    <?php if ($mesFiches !== []): ?>
    <?php // Une fiche se partage sans son cours : son texte, ses liens, ses fichiers. ?>
    <section class="carte partage-section" data-genre-section="fiches" role="tabpanel"<?= $genreActif === 'fiches' ? '' : ' hidden' ?>>
      <h2 style="margin-top:0"><?= e(t('pt.fiches_titre')) ?></h2>
      <label class="discussions-recherche">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
          <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
        </svg>
        <span class="sr-only"><?= e(t('pt.chercher_mes_fiches')) ?></span>
        <input type="search" placeholder="<?= e(t('pt.chercher_fiche')) ?>" autocomplete="off" data-filtre-liste="[data-liste-mes-fiches]">
      </label>
      <p style="margin:.5rem 0 0">
        <button class="bouton bouton--discret bouton--petit" type="button"
                data-cocher-tout="[data-liste-mes-fiches]"><?= e(t('pt.cocher_tout')) ?></button>
      </p>
      <ul class="groupe-choix__liste partage-liste" data-liste-mes-fiches>
        <?php foreach ($mesFiches as $f): ?>
          <li data-nom="<?= e(mb_strtolower((string) $f['titre'] . ' ' . (string) ($f['matiere_nom'] ?? ''))) ?>">
            <label class="groupe-choix__ami">
              <input type="checkbox" name="fiches[]" value="<?= (int) $f['id'] ?>"<?= isset($choisisFiches[(int) $f['id']]) ? ' checked' : '' ?>>
              <span aria-hidden="true">📝</span>
              <span class="partage-liste__nom">
                <?= e((string) $f['titre']) ?>
                <span class="discret">
                  <?php if (($f['matiere_nom'] ?? null) !== null): ?>· <?= e((string) $f['matiere_nom']) ?><?php endif; ?>
                  <?php if ((int) $f['nb_fichiers'] > 0): ?>· <?= e(tn('pt.combien_fichier', (int) $f['nb_fichiers'])) ?><?php endif; ?>
                </span>
              </span>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="discret" data-filtre-vide hidden style="margin:.4rem 0 0"><?= e(t('pt.aucune_fiche_nom')) ?></p>
      <p class="champ__aide" style="margin-bottom:0">
        <?= e(t('pt.fiche_seule')) ?>
      </p>
    </section>
    <?php endif; ?>

    <?php if ($mesDossiers !== []): ?>
    <?php // Un dossier coché ouvre tout ce qu'il contient, sous-dossiers compris. ?>
    <section class="carte partage-section" data-genre-section="dossiers" role="tabpanel"<?= $genreActif === 'dossiers' ? '' : ' hidden' ?>>
      <h2 style="margin-top:0"><?= e(t('pt.dossiers_titre')) ?></h2>
      <label class="discussions-recherche">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
          <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
        </svg>
        <span class="sr-only"><?= e(t('pt.chercher_mes_dossiers')) ?></span>
        <input type="search" placeholder="<?= e(t('pt.chercher_dossier')) ?>" autocomplete="off" data-filtre-liste="[data-liste-mes-dossiers]">
      </label>
      <p style="margin:.5rem 0 0">
        <button class="bouton bouton--discret bouton--petit" type="button"
                data-cocher-tout="[data-liste-mes-dossiers]"><?= e(t('pt.cocher_tout')) ?></button>
      </p>
      <ul class="groupe-choix__liste partage-liste" data-liste-mes-dossiers>
        <?php foreach ($mesDossiers as $d): ?>
          <li data-nom="<?= e(mb_strtolower((string) $d['chemin'])) ?>">
            <label class="groupe-choix__ami" style="padding-left:<?= min((int) $d['profondeur'], 4) * 1.1 ?>rem">
              <input type="checkbox" name="dossiers[]" value="<?= (int) $d['id'] ?>"<?= isset($choisisDossiers[(int) $d['id']]) ? ' checked' : '' ?>>
              <span aria-hidden="true"><?= e((string) $d['icone']) ?></span>
              <span class="partage-liste__nom">
                <?= e((string) $d['nom']) ?>
                <span class="discret">· <?= e(Partages::compteCours(Partages::nbCours((int) $d['id'], (int) $d['user_id']))) ?></span>
              </span>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="discret" data-filtre-vide hidden style="margin:.4rem 0 0"><?= e(t('pt.aucun_dossier_nom')) ?></p>
      <p class="champ__aide" style="margin-bottom:0">
        <?= e(t('pt.dossier_aide')) ?>
      </p>
    </section>
    <?php endif; ?>

    <?php if ($mesFichiers !== []): ?>
    <?php // Les fichiers joints de mes cours : chacun se partage seul, tel quel. ?>
    <section class="carte partage-section" data-genre-section="fichiers" role="tabpanel"<?= $genreActif === 'fichiers' ? '' : ' hidden' ?>>
      <h2 style="margin-top:0"><?= e(t('pt.fichiers_titre')) ?></h2>
      <label class="discussions-recherche">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
          <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
        </svg>
        <span class="sr-only"><?= e(t('pt.chercher_mes_fichiers')) ?></span>
        <input type="search" placeholder="<?= e(t('pt.chercher_fichier')) ?>" autocomplete="off" data-filtre-liste="[data-liste-mes-fichiers]">
      </label>
      <p style="margin:.5rem 0 0">
        <button class="bouton bouton--discret bouton--petit" type="button"
                data-cocher-tout="[data-liste-mes-fichiers]"><?= e(t('pt.cocher_tout')) ?></button>
      </p>
      <ul class="groupe-choix__liste partage-liste" data-liste-mes-fichiers>
        <?php foreach ($mesFichiers as $f): ?>
          <li data-nom="<?= e(mb_strtolower((string) $f['nom_origine'] . ' ' . (string) $f['cours_titre'])) ?>">
            <label class="groupe-choix__ami">
              <input type="checkbox" name="fichiers[]" value="<?= (int) $f['id'] ?>"<?= isset($choisisFichiers[(int) $f['id']]) ? ' checked' : '' ?>>
              <span aria-hidden="true"><?= e(Fichiers::icone((string) $f['mime'], (string) $f['nom_origine'])) ?></span>
              <span class="partage-liste__nom">
                <?= e((string) $f['nom_origine']) ?>
                <span class="discret">
                  · <?= e(taille_lisible((int) $f['taille'])) ?>
                  · <?= e((string) $f['cours_titre']) ?><?= (int) $f['pour_fiche'] === 1 ? ' ' . e(t('pt.pour_fiche')) : '' ?>
                </span>
              </span>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="discret" data-filtre-vide hidden style="margin:.4rem 0 0"><?= e(t('pt.aucun_fichier_nom')) ?></p>
    </section>
    <?php endif; ?>

    <?php
    /*
     * Puis à qui : ses amis et ses groupes de discussion, un travail de groupe, ou un lien public pour ceux qui n'ont pas de compte. Même principe
     * que pour le genre de document : un onglet par destination, une seule à la fois, chacune avec son propre bouton d'envoi.
     */
    $destinations = ['amis' => ['👥', 'pt.dest_amis']];
    if (($projets ?? []) !== []) { $destinations['projets'] = ['🤝', 'pt.dest_projets']; }
    $destinations['lien'] = ['🔗', 'pt.dest_lien'];
    $destActive = $amis !== [] || $groupes !== [] ? 'amis' : (isset($destinations['projets']) ? 'projets' : 'lien');
    ?>
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
        <p class="discret" style="margin:0">
          <?= e(t('pt.pas_encore_amis')) ?>
          <a href="<?= url('amis') ?>"><?= e(t('pt.chercher_pseudo')) ?></a>
        </p>
      <?php else: ?>
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

      <?= Vue::rendre('partages/_droits', ['droitsPossibles' => $droitsPossibles]) ?>
      <div class="champ" style="margin-top:.75rem">
        <label for="lot-texte"><?= e(t('pt.message_facultatif')) ?></label>
        <textarea id="lot-texte" name="texte" rows="2" maxlength="<?= Amis::MESSAGE_MAX ?>"
                  placeholder="<?= e(t('pt.message_exemple')) ?>"></textarea>
      </div>
      <button class="bouton" type="submit"><?= e(t('pt.envoyer')) ?></button>
      <?php endif; ?>
      <p class="champ__aide" style="margin-bottom:0">
        <?= e(t('pt.envoi_aide')) ?>
      </p>
    </section>

    <?php
    /*
     * Les mêmes cours, dossiers et fichiers cochés, mis dans des travaux de groupe : leurs membres les consultent depuis l'onglet « Cours ». Le
     * bouton envoie le formulaire ailleurs, les amis cochés plus haut n'y sont pour rien.
     */
    ?>
    <?php if (($projets ?? []) !== []): ?>
    <section class="carte partage-section" data-dest-section="projets" role="tabpanel"<?= $destActive === 'projets' ? '' : ' hidden' ?>>
      <h2 style="margin-top:0">👥 <?= e(t('pt.avec_projet')) ?></h2>
      <p class="discret" style="margin-top:0"><?= e(t('pt.projets_lot_aide')) ?></p>
      <ul class="groupe-choix__liste">
        <?php foreach ($projets as $p): ?>
          <li>
            <label class="groupe-choix__ami">
              <input type="checkbox" name="projets[]" value="<?= (int) $p['id'] ?>">
              <span class="avatar avatar--mini" aria-hidden="true">👥</span>
              <span class="partage-liste__nom"><?= e($p['nom']) ?></span>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <button class="bouton bouton--secondaire" type="submit"
              formaction="<?= url('partager/plusieurs/projets') ?>"><?= e(t('pt.projets_lot_ajouter')) ?></button>
    </section>
    <?php endif; ?>

    <?php
    /*
     * Le même choix, mais pour ceux qui n'ont pas de compte : un lien unique
     * qui montre tous les documents cochés. Le bouton envoie le formulaire
     * ailleurs — les cases cochées partent donc telles quelles.
     */
    ?>
    <section class="carte partage-section" data-dest-section="lien" role="tabpanel"<?= $destActive === 'lien' ? '' : ' hidden' ?>>
      <h2 style="margin-top:0"><?= e(t('pt.avec_lien')) ?></h2>
      <p class="discret" style="margin-top:0">
        <?= e(t('pt.lien_aide')) ?>
      </p>
      <div class="champ">
        <label for="lot-nom"><?= e(t('pt.nom_lien')) ?></label>
        <input type="text" id="lot-nom" name="nom" maxlength="120" placeholder="<?= e(t('pt.nom_lien_exemple')) ?>">
      </div>
      <button class="bouton bouton--secondaire" type="submit"
              formaction="<?= url('partager/plusieurs/lien') ?>"><?= e(t('pt.creer_lien')) ?></button>
    </section>
  </form>

  <?php if ($mesLots !== []): ?>
    <?php // Les liens déjà créés : à copier, à suivre, à défaire. ?>
    <section class="carte partage-section">
      <h2 style="margin-top:0"><?= e(t('pt.mes_lots')) ?> <span class="discret">(<?= count($mesLots) ?>)</span></h2>
      <?php foreach ($mesLots as $unLot): ?>
        <?php if ($unLot['jeton'] === null) { continue; } ?>
        <div style="margin-bottom:1rem">
          <p style="margin:0 0 .3rem">
            <strong><?= e((string) $unLot['nom']) ?></strong>
            <span class="discret">· <?= e(tn('pt.combien_document', count($unLot['documents']))) ?></span>
          </p>
          <div class="partage-lien" data-partage-lien>
            <label class="sr-only" for="lot-lien-<?= (int) $unLot['id'] ?>"><?= e(t('pt.lien_de_partage')) ?></label>
            <input type="text" id="lot-lien-<?= (int) $unLot['id'] ?>" readonly
                   value="<?= e(Partages::adresseLien((string) $unLot['jeton'])) ?>" data-partage-adresse>
            <button class="bouton" type="button" data-partage-copier><?= e(t('pt.copier')) ?></button>
            <button class="bouton bouton--secondaire" type="button" data-partage-natif hidden
                    data-titre="<?= e((string) $unLot['nom']) ?>"><?= Partages::icone() ?> <?= e(t('pt.envoyer_points')) ?></button>
          </div>
          <p class="champ__aide" data-partage-etat aria-live="polite" style="margin-bottom:.3rem">
            <?= e(t('pt.ouvert_fois', ['n' => (int) $unLot['vues']])) ?> · <?= e(implode(', ', array_map(
                static fn (array $d): string => $d['icone'] . ' ' . mb_strimwidth((string) $d['titre'], 0, 40, '…'),
                array_slice($unLot['documents'], 0, 4)
            ))) ?><?= count($unLot['documents']) > 4 ? '…' : '' ?>
          </p>
          <form method="post" action="<?= url('partager/lots/' . (int) $unLot['id'] . '/desactiver') ?>"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>
                data-confirmation="<?= e(t('pt.desactiver_confirmation')) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('pt.desactiver_lien')) ?></button>
          </form>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
<?php endif; ?>
