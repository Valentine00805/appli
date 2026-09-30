<?php
/**
 * @var array $fichier, $paragraphes, $lignes
 * @var array $enrichis  les mêmes paragraphes, mise en forme comprise
 * @var int $sommaire    jusqu'à quel niveau de titre il descend, zéro s'il n'y en a pas
 * @var string $genre, $texte, $format
 * @var bool $tronque
 * @var bool $estTableur
 * @var int $total, $limite
 * @var ?string $erreur
 * @var bool $dansUneFenetre  rendu seul, pour être posé dans une fenêtre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
?>

<?php
/*
 * « data-document » demande à la fenêtre toute la place qu'elle peut prendre :
 * un document se lit, et une colonne étroite de quarante lignes ne se lit pas.
 */
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large data-document' : '' ?>>
  <div>
    <?php // Dans une fenêtre, le cours est juste derrière : la croix y ramène. ?>
    <?php if (!$dansUneFenetre): ?>
      <p class="discret" style="margin-bottom:.35rem">
        <a href="<?= url('cours/' . $fichier['cours_id']) ?>">← <?= e((string) $fichier['cours_titre']) ?></a>
      </p>
    <?php endif; ?>
    <h1><?= e(Fichiers::icone($fichier['mime'], $fichier['nom_origine'])) ?> <?= e((string) $fichier['nom_origine']) ?></h1>
    <p><?= e(ucfirst($format)) ?> · <?= e(taille_lisible((int) $fichier['taille'])) ?></p>
  </div>
  <div class="actions">
    <?php if (EditionDocument::modifiable((string) $fichier['nom_origine'])): ?>
      <?php // Dans la fenêtre, l'éditeur prend la place de l'aperçu. ?>
      <a class="bouton bouton--secondaire" href="<?= url('fichiers/' . $fichier['id'] . '/modifier') ?>"
         <?= $dansUneFenetre ? 'data-fenetre' : '' ?>>
        <?= e(t('ap.modifier_texte')) ?>
      </a>
    <?php endif; ?>
    <a class="bouton bouton--secondaire bouton-partage" href="<?= url('partager/fichiers/' . $fichier['id']) ?>" <?= $dansUneFenetre ? 'data-fenetre-dessus' : 'data-fenetre' ?>>
      <?= Partages::icone() ?> <?= e(t('evt.partager')) ?>
    </a>
    <?php // Télécharger : le fichier d'origine, ou le PDF. ?>
    <?= Vue::rendre('cours/_telecharger', ['fichier' => $fichier]) ?>
  </div>
</div>

<?php // Un PDF ou une image s'affichent tels quels : aucun avertissement à donner. ?>
<?php if (!in_array($genre, ['pdf', 'image'], true)): ?>
  <div class="flash flash--info" style="margin-bottom:1.25rem">
    <?php if ($genre === 'tableur'): ?>
      <?= t('ap.tableur_aide') ?>
    <?php elseif ($genre === 'brut'): ?>
      <?= t('ap.brut_aide') ?>
    <?php elseif ($enrichis !== []): ?>
      <?= t('ap.enrichi_aide', ['format' => e($format)]) ?>
    <?php else: ?>
      <?= t('ap.texte_aide', ['format' => e($format)]) ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($erreur !== null): ?>
  <div class="vide">
    <span class="vide__icone">⚠️</span>
    <p><?= e($erreur) ?></p>
    <a class="bouton bouton--secondaire" href="<?= url('fichiers/' . $fichier['id'], ['telecharger' => 1]) ?>">
      <?= e(t('commun.telecharger_fichier')) ?>
    </a>
  </div>
<?php elseif ($genre === 'pdf'): ?>
  <?php // Le document lui-même, dans la page, avec la visionneuse du navigateur. ?>
  <div class="carte apercu-cadre">
    <iframe src="<?= url('fichiers/' . $fichier['id']) ?>"
            title="<?= e((string) $fichier['nom_origine']) ?>"></iframe>
  </div>
  <p class="champ__aide" style="margin-top:.6rem">
    <?= e(t('ap.pdf_repli')) ?>
    <a href="<?= url('fichiers/' . $fichier['id']) ?>" target="_blank" rel="noopener"><?= e(t('ap.pdf_onglet')) ?></a>.
  </p>

<?php elseif ($genre === 'image'): ?>
  <div class="carte apercu-image">
    <a href="<?= url('fichiers/' . $fichier['id']) ?>" target="_blank" rel="noopener"
       title="<?= e(t('ap.image_grand')) ?>">
      <img src="<?= url('fichiers/' . $fichier['id']) ?>" alt="<?= e((string) $fichier['nom_origine']) ?>">
    </a>
  </div>

<?php elseif ($genre === 'brut'): ?>
  <div class="carte">
    <pre class="apercu-brut"><?= e($texte) ?></pre>
  </div>
  <?php if ($tronque): ?>
    <p class="champ__aide" style="margin-top:.6rem">
      <?= e(t('ap.tronque')) ?>
    </p>
  <?php endif; ?>

<?php elseif ($estTableur): ?>
  <?php if ($lignes === []): ?>
    <div class="vide">
      <span class="vide__icone">📊</span>
      <p><?= e(t('ap.classeur_vide')) ?></p>
      <a class="bouton bouton--secondaire" href="<?= url('fichiers/' . $fichier['id'], ['telecharger' => 1]) ?>">
        <?= e(t('commun.telecharger_fichier')) ?>
      </a>
    </div>
  <?php else: ?>
    <div class="carte" style="overflow-x:auto">
      <table class="tableau apercu-classeur">
        <tbody>
          <?php foreach ($lignes as $i => $ligne): ?>
            <tr>
              <th scope="row" class="apercu-classeur__num"><?= $i + 1 ?></th>
              <?php foreach ($ligne as $cellule): ?>
                <td><?= e($cellule) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="champ__aide" style="margin-top:.6rem">
      <?php
      $n = count($lignes);
      $suite = match (true) {
          $total > $limite => t('ap.sur_total', ['total' => $total]),
          $format === t('fmt.xlsx') => t('ap.premiere_feuille'),
          default => '.',
      };
      ?>
      <?= e(tn('ap.lignes', $n)) ?><?= e($suite) ?>
    </p>
  <?php endif; ?>
<?php elseif ($paragraphes === [] && $enrichis === []): ?>
  <div class="vide">
    <span class="vide__icone">📄</span>
    <p><?= e(t('ap.rien_a_montrer')) ?></p>
    <a class="bouton bouton--secondaire" href="<?= url('fichiers/' . $fichier['id'], ['telecharger' => 1]) ?>">
      <?= e(t('commun.telecharger_fichier')) ?>
    </a>
  </div>
<?php else: ?>
  <?php
  /*
   * Une seule liste à parcourir, et non deux à faire correspondre.
   *
   * La lecture riche fait foi quand elle a abouti : elle porte le texte mis en
   * forme et les images. Sinon on retombe sur le texte nu, présenté de la même
   * façon — la page n'a plus à savoir laquelle des deux elle affiche.
   */
  $blocs = $enrichis !== [] ? $enrichis : array_map(
      static fn (string $texte): array => [
          'html' => e($texte), 'alignement' => null, 'liste' => '', 'numero' => null,
          'niveau' => 0, 'titre' => 0, 'sommaire' => false, 'images' => [],
      ],
      $paragraphes
  );

  /** Le texte d'un bloc, sans ses balises : pour le sommaire. */
  $nu = static fn (array $bloc): string => trim((string) preg_replace('/\s+/u', ' ',
      html_entity_decode(strip_tags((string) $bloc['html']), ENT_QUOTES, 'UTF-8')));

  /*
   * Les images du document, rendues à leur place — par le même partiel que
   * l'éditeur, pour que les deux pages les montrent de la même façon.
   */
  $figures = static fn (array $images): string => $images === [] ? ''
      : Vue::rendre('cours/_images', ['images' => $images, 'fichier' => $fichier]);
  ?>
  <?php
  /*
   * Le sommaire est refait ici à partir des titres lus, et non repris du
   * document : il est ainsi toujours d'accord avec ce qu'on voit dessous,
   * même si le fichier n'a pas encore été rouvert dans Word.
   */
  $plan = [];
  foreach ($blocs as $rang => $bloc) {
      if ($bloc['sommaire'] ?? false) {
          continue;
      }
      $niveau = (int) ($bloc['titre'] ?? 0);
      $intitule = $nu($bloc);
      // Le sommaire s'arrête au niveau choisi : ce qui est plus profond
      // reste dans le texte, mais n'y figure pas.
      if ($niveau > 0 && $niveau <= $sommaire && $intitule !== '') {
          $plan[$rang] = ['niveau' => $niveau, 'texte' => $intitule];
      }
  }
  ?>
  <?php if ($sommaire > 0 && $plan !== []): ?>
    <nav class="carte apercu-sommaire" aria-labelledby="apercu-sommaire-titre"
         data-apercu-sommaire>
      <h2 id="apercu-sommaire-titre" class="apercu-sommaire__titre"><?= e(t('ap.sommaire')) ?></h2>
      <ul class="apercu-sommaire__liste">
        <?php foreach ($plan as $rang => $entree): ?>
          <li class="apercu-sommaire__ligne apercu-sommaire__ligne--n<?= $entree['niveau'] ?>">
            <a href="#titre-<?= (int) $rang ?>"><?= e($entree['texte']) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="champ__aide" style="margin:.7rem 0 0">
        Les numéros de page apparaîtront à l'ouverture du document dans Word
        ou LibreOffice.
      </p>
    </nav>
  <?php endif; ?>

  <article class="carte apercu-document">
    <?php
    /*
     * Le HTML n'est pas échappé ici, et c'est voulu : il ne vient pas du
     * document mais de l'application, qui le rebâtit balise par balise à partir
     * des seules marques qu'elle connaît. Tout ce que le fichier contient
     * d'autre n'en ressort que sous forme de texte, déjà échappé.
     */
    ?>
    <?php
    /*
     * Les paragraphes d'une même liste se suivent dans une seule balise :
     * sinon une numérotation reprendrait à un à chaque ligne. Une sous-liste
     * se range dans l'élément qui la porte, comme le veut le HTML.
     */
    $pile = [];       // les listes ouvertes, avec leur balise
    $porte = [];      // à chaque étage, un <li> reste-t-il à fermer ?

    $fermerUn = static function () use (&$pile, &$porte): void {
        if (array_pop($porte)) {
            echo '</li>';
        }
        echo '</' . array_pop($pile) . '>';
    };

    foreach ($blocs as $rang => $riche):
        // Le sommaire du document est refait plus haut, à partir des titres :
        // le montrer ici le donnerait deux fois, dont une périmée.
        if ($riche['sommaire'] ?? false) {
            continue;
        }
        $aligne = ['gauche' => 'left', 'centre' => 'center',
                   'droite' => 'right', 'justifie' => 'justify'][$riche['alignement'] ?? ''] ?? null;
        $style = $aligne === null ? '' : ' style="text-align:' . $aligne . '"';
        $corps = (string) $riche['html'];
        $dessins = $figures($riche['images'] ?? []);
        $liste = (string) ($riche['liste'] ?? '');
        $titre = (int) ($riche['titre'] ?? 0);
        // Un titre annonce une section : il referme les listes ouvertes et ne
        // se range pas dedans.
        $balise = $titre > 0 ? '' : (['puce' => 'ul', 'numero' => 'ol'][$liste] ?? '');
        $vise = $balise === '' ? 0 : (int) ($riche['niveau'] ?? 0) + 1;

        // Une liste d'une autre sorte n'en continue pas une : on referme tout
        // avant d'ouvrir la sienne.
        if ($pile !== [] && $pile[0] !== $balise) {
            while ($pile !== []) {
                $fermerUn();
            }
        }
        while (count($pile) > $vise) {
            $fermerUn();
        }
        while (count($pile) < $vise) {
            // La sous-liste se glisse dans le dernier élément, qu'on laisse
            // ouvert ; s'il n'y en a pas, on en pose un vide pour rester
            // dans un HTML valide.
            if ($pile !== [] && !$porte[count($porte) - 1]) {
                echo '<li class="apercu-liste__porteur">';
                $porte[count($porte) - 1] = true;
            }
            $classe = 'apercu-liste apercu-liste--n' . count($pile);
            echo '<' . $balise . ' class="' . $classe . '">';
            $pile[] = $balise;
            $porte[] = false;
        }

        if ($balise === '') {
            // La page porte déjà son <h1> : les titres du document se rangent
            // dessous, pour que le plan de la page reste lisible.
            $balise = $titre > 0 ? 'h' . ($titre + 1) : 'p';
            // L'ancre où mène le sommaire.
            $classe = $titre > 0
                ? ' id="titre-' . (int) $rang . '" class="apercu-titre apercu-titre--' . $titre . '"'
                : '';
            // Un paragraphe qui n'est qu'une image : la figure suffit, un
            // paragraphe vide au-dessus ne ferait qu'un blanc de plus.
            if ($corps !== '') {
                echo '<' . $balise . $classe . $style . '>' . $corps . '</' . $balise . '>';
            }
            echo $dessins;
            continue;
        }

        // « value » dit le numéro plutôt que de le faire compter au navigateur :
        // une liste que le document poursuit après un paragraphe ordinaire
        // repart alors au bon rang, et non à un.
        $numero = $liste === 'numero' ? (int) ($riche['numero'] ?? 0) : 0;
        $dernier = count($porte) - 1;
        if ($porte[$dernier]) {
            echo '</li>';
        }
        echo '<li' . ($numero > 0 ? ' value="' . $numero . '"' : '') . $style . '>'
            . $corps . $dessins;
        $porte[$dernier] = true;
    endforeach;

    while ($pile !== []) {
        $fermerUn();
    }
    ?>
  </article>
  <p class="champ__aide" style="margin-top:.6rem">
    <?php $nbImages = array_sum(array_map(
        static fn (array $b): int => count($b['images'] ?? []) + (int) ($b['images_texte'] ?? 0),
        $blocs)); ?>
    <?= e(tn('ap.paragraphes', count($paragraphes))) ?><?php
    ?><?= $nbImages > 0 ? e(tn('ap.images', $nbImages)) : '' ?>.
  </p>
<?php endif; ?>
