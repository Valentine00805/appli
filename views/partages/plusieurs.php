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
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <h1 style="margin:0"><?= Partages::icone() ?> Partager plusieurs</h1>
    <p class="discret" style="margin:.15rem 0 0">Des cours, des fiches, des dossiers et des fichiers en un seul envoi, jusqu’à <?= Partages::LOT_MAX ?> à la fois.</p>
  </div>
</div>

<?php if ($mesCours === [] && $mesFichiers === [] && $mesDossiers === [] && $mesFiches === []): ?>
  <section class="carte">
    <p class="discret" style="margin:0">Vous n’avez pas encore de cours, de fichier ni de dossier à partager.</p>
  </section>
<?php else: ?>
  <form method="post" action="<?= url('partager/plusieurs/amis') ?>"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <?php if ($mesCours !== []): ?>
    <section class="carte partage-section">
      <h2 style="margin-top:0">📘 Les cours à partager</h2>
      <label class="discussions-recherche">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
          <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
        </svg>
        <span class="sr-only">Rechercher un de mes cours</span>
        <input type="search" placeholder="Rechercher un cours" autocomplete="off" data-filtre-liste="[data-liste-mes-cours]">
      </label>
      <p style="margin:.5rem 0 0">
        <button class="bouton bouton--discret bouton--petit" type="button"
                data-cocher-tout="[data-liste-mes-cours]">Tout cocher, ou décocher</button>
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
      <p class="discret" data-filtre-vide hidden style="margin:.4rem 0 0">Aucun cours ne porte ce nom.</p>
    </section>
    <?php endif; ?>

    <?php if ($mesFiches !== []): ?>
    <?php // Une fiche se partage sans son cours : son texte, ses liens, ses fichiers. ?>
    <section class="carte partage-section">
      <h2 style="margin-top:0">📝 Les fiches de révision à partager</h2>
      <label class="discussions-recherche">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
          <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
        </svg>
        <span class="sr-only">Rechercher une de mes fiches</span>
        <input type="search" placeholder="Rechercher une fiche" autocomplete="off" data-filtre-liste="[data-liste-mes-fiches]">
      </label>
      <p style="margin:.5rem 0 0">
        <button class="bouton bouton--discret bouton--petit" type="button"
                data-cocher-tout="[data-liste-mes-fiches]">Tout cocher, ou décocher</button>
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
                  <?php if ((int) $f['nb_fichiers'] > 0): ?>· <?= (int) $f['nb_fichiers'] ?> fichier<?= (int) $f['nb_fichiers'] > 1 ? 's' : '' ?><?php endif; ?>
                </span>
              </span>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="discret" data-filtre-vide hidden style="margin:.4rem 0 0">Aucune fiche ne porte ce nom.</p>
      <p class="champ__aide" style="margin-bottom:0">
        Une fiche part seule : son texte, ses liens et ses fichiers, sans le contenu du cours.
      </p>
    </section>
    <?php endif; ?>

    <?php if ($mesDossiers !== []): ?>
    <?php // Un dossier coché ouvre tout ce qu'il contient, sous-dossiers compris. ?>
    <section class="carte partage-section">
      <h2 style="margin-top:0">📁 Les dossiers à partager</h2>
      <label class="discussions-recherche">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
          <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
        </svg>
        <span class="sr-only">Rechercher un de mes dossiers</span>
        <input type="search" placeholder="Rechercher un dossier" autocomplete="off" data-filtre-liste="[data-liste-mes-dossiers]">
      </label>
      <p style="margin:.5rem 0 0">
        <button class="bouton bouton--discret bouton--petit" type="button"
                data-cocher-tout="[data-liste-mes-dossiers]">Tout cocher, ou décocher</button>
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
      <p class="discret" data-filtre-vide hidden style="margin:.4rem 0 0">Aucun dossier ne porte ce nom.</p>
      <p class="champ__aide" style="margin-bottom:0">
        Un dossier partagé ouvre ses cours et ceux de ses sous-dossiers, aujourd’hui comme demain :
        inutile de cocher aussi ses cours un par un.
      </p>
    </section>
    <?php endif; ?>

    <?php if ($mesFichiers !== []): ?>
    <?php // Les fichiers joints de mes cours : chacun se partage seul, tel quel. ?>
    <section class="carte partage-section">
      <h2 style="margin-top:0">📎 Les fichiers à partager</h2>
      <label class="discussions-recherche">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
          <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
        </svg>
        <span class="sr-only">Rechercher un de mes fichiers</span>
        <input type="search" placeholder="Rechercher un fichier" autocomplete="off" data-filtre-liste="[data-liste-mes-fichiers]">
      </label>
      <p style="margin:.5rem 0 0">
        <button class="bouton bouton--discret bouton--petit" type="button"
                data-cocher-tout="[data-liste-mes-fichiers]">Tout cocher, ou décocher</button>
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
                  · <?= e((string) $f['cours_titre']) ?><?= (int) $f['pour_fiche'] === 1 ? ' (fiche)' : '' ?>
                </span>
              </span>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="discret" data-filtre-vide hidden style="margin:.4rem 0 0">Aucun fichier ne porte ce nom.</p>
    </section>
    <?php endif; ?>

    <section class="carte partage-section">
      <h2 style="margin-top:0">👥 Avec mes amis</h2>
      <?php if ($amis === [] && $groupes === []): ?>
        <p class="discret" style="margin:0">
          Pas encore d’amis à qui les envoyer — le lien ci-dessous, lui, marche déjà.
          <a href="<?= url('amis') ?>">Chercher un pseudo</a>
        </p>
      <?php else: ?>
      <label class="discussions-recherche">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">
          <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
        </svg>
        <span class="sr-only">Rechercher un ami ou un groupe</span>
        <input type="search" placeholder="Rechercher" autocomplete="off" data-filtre-liste="[data-liste-partage]">
      </label>
      <ul class="groupe-choix__liste partage-liste" data-liste-partage>
        <?php foreach ($amis as $a): ?>
          <li data-nom="<?= e(mb_strtolower((string) $a['pseudo'])) ?>">
            <label class="groupe-choix__ami">
              <input type="checkbox" name="amis[]" value="<?= (int) $a['id'] ?>">
              <?= Amis::avatar((int) $a['id'], (string) $a['pseudo']) ?>
              <span class="partage-liste__nom"><?= e((string) $a['pseudo']) ?></span>
            </label>
          </li>
        <?php endforeach; ?>
        <?php foreach ($groupes as $g): ?>
          <li data-nom="<?= e(mb_strtolower((string) $g['nom'])) ?>">
            <label class="groupe-choix__ami">
              <input type="checkbox" name="groupes[]" value="<?= (int) $g['id'] ?>">
              <?= Conversations::avatar((int) $g['id'], $g['photo_nom'] ?? null) ?>
              <span class="partage-liste__nom"><?= e((string) $g['nom']) ?> <span class="discret">· groupe</span></span>
            </label>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="discret" data-filtre-vide hidden style="margin:.4rem 0 0">Personne ne correspond.</p>

      <fieldset class="champ partage-droits" style="margin-top:.75rem">
        <legend class="legende">Ce qu’ils pourront faire</legend>
        <?php foreach (Partages::DROITS as $rang => $unDroit): ?>
          <label class="partage-droits__choix">
            <input type="radio" name="droit" value="<?= e($unDroit) ?>"<?= $rang === 0 ? ' checked' : '' ?>>
            <span>
              <strong><?= e(Partages::libelleDroit($unDroit)) ?></strong>
              <span class="discret"><?= e(Partages::expliqueDroit($unDroit)) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </fieldset>
      <div class="champ" style="margin-top:.75rem">
        <label for="lot-texte">Message (facultatif)</label>
        <textarea id="lot-texte" name="texte" rows="2" maxlength="<?= Amis::MESSAGE_MAX ?>"
                  placeholder="Voilà mes documents…"></textarea>
      </div>
      <button class="bouton" type="submit">Envoyer</button>
      <?php endif; ?>
      <p class="champ__aide" style="margin-bottom:0">
        Chaque document part dans votre discussion sous sa propre carte, et paraît dans « Partagés avec moi »,
        en lecture seule. Un cours emporte ses fichiers joints, pas sa fiche de révision. Pour ouvrir aussi
        ce que vous rangerez plus tard, partagez plutôt le dossier.
      </p>
    </section>

    <?php
    /*
     * Le même choix, mais pour ceux qui n'ont pas de compte : un lien unique
     * qui montre tous les documents cochés. Le bouton envoie le formulaire
     * ailleurs — les cases cochées partent donc telles quelles.
     */
    ?>
    <section class="carte partage-section">
      <h2 style="margin-top:0">🔗 Avec un lien</h2>
      <p class="discret" style="margin-top:0">
        Un seul lien pour tout ce que vous avez coché : qui l’a voit ces documents et les télécharge,
        sans compte. Vous pourrez le désactiver quand vous voudrez.
      </p>
      <div class="champ">
        <label for="lot-nom">Nom du lien (facultatif)</label>
        <input type="text" id="lot-nom" name="nom" maxlength="120" placeholder="Révisions du partiel">
      </div>
      <button class="bouton bouton--secondaire" type="submit"
              formaction="<?= url('partager/plusieurs/lien') ?>">Créer un lien pour ce que j’ai coché</button>
    </section>
  </form>

  <?php if ($mesLots !== []): ?>
    <?php // Les liens déjà créés : à copier, à suivre, à défaire. ?>
    <section class="carte partage-section">
      <h2 style="margin-top:0">Mes liens de plusieurs documents <span class="discret">(<?= count($mesLots) ?>)</span></h2>
      <?php foreach ($mesLots as $unLot): ?>
        <?php if ($unLot['jeton'] === null) { continue; } ?>
        <div style="margin-bottom:1rem">
          <p style="margin:0 0 .3rem">
            <strong><?= e((string) $unLot['nom']) ?></strong>
            <span class="discret">· <?= count($unLot['documents']) ?> document<?= count($unLot['documents']) > 1 ? 's' : '' ?></span>
          </p>
          <div class="partage-lien" data-partage-lien>
            <label class="sr-only" for="lot-lien-<?= (int) $unLot['id'] ?>">Lien de partage</label>
            <input type="text" id="lot-lien-<?= (int) $unLot['id'] ?>" readonly
                   value="<?= e(Partages::adresseLien((string) $unLot['jeton'])) ?>" data-partage-adresse>
            <button class="bouton" type="button" data-partage-copier>Copier</button>
            <button class="bouton bouton--secondaire" type="button" data-partage-natif hidden
                    data-titre="<?= e((string) $unLot['nom']) ?>"><?= Partages::icone() ?> Envoyer…</button>
          </div>
          <p class="champ__aide" data-partage-etat aria-live="polite" style="margin-bottom:.3rem">
            Ouvert <?= (int) $unLot['vues'] ?> fois · <?= e(implode(', ', array_map(
                static fn (array $d): string => $d['icone'] . ' ' . mb_strimwidth((string) $d['titre'], 0, 40, '…'),
                array_slice($unLot['documents'], 0, 4)
            ))) ?><?= count($unLot['documents']) > 4 ? '…' : '' ?>
          </p>
          <form method="post" action="<?= url('partager/lots/' . (int) $unLot['id'] . '/desactiver') ?>"<?= $dansUneFenetre ? ' data-envoi-fenetre' : '' ?>
                data-confirmation="Désactiver ce lien ? L’adresse ne mènera plus à rien, même si elle a circulé. Vos documents, eux, restent.">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button class="bouton bouton--discret bouton--petit" type="submit">Désactiver ce lien</button>
          </form>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
<?php endif; ?>
