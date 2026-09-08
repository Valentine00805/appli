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

 */
?>

<div class="entete-page">
  <div>
    <p class="discret" style="margin-bottom:.35rem">
      <a href="<?= url('cours/' . $fichier['cours_id']) ?>">← <?= e((string) $fichier['cours_titre']) ?></a>
    </p>
    <h1><?= e(Fichiers::icone($fichier['mime'], $fichier['nom_origine'])) ?> <?= e((string) $fichier['nom_origine']) ?></h1>
    <p><?= e(ucfirst($format)) ?> · <?= e(taille_lisible((int) $fichier['taille'])) ?></p>
  </div>
  <div class="actions">
    <?php if (EditionDocument::modifiable((string) $fichier['nom_origine'])): ?>
      <a class="bouton bouton--secondaire" href="<?= url('fichiers/' . $fichier['id'] . '/modifier') ?>">
        ✎ Modifier le texte
      </a>
    <?php endif; ?>
    <a class="bouton" href="<?= url('fichiers/' . $fichier['id'], ['telecharger' => 1]) ?>">
      ⬇ Télécharger le fichier
    </a>
  </div>
</div>

<?php // Un PDF ou une image s'affichent tels quels : aucun avertissement à donner. ?>
<?php if (!in_array($genre, ['pdf', 'image'], true)): ?>
  <div class="flash flash--info" style="margin-bottom:1.25rem">
    <?php if ($genre === 'tableur'): ?>
      <strong>Aperçu du contenu.</strong> Les formules, les couleurs et les graphiques
      ne sont pas reproduits : seules les valeurs sont affichées.
      Téléchargez le fichier pour l'ouvrir tel quel dans Excel ou LibreOffice.
    <?php elseif ($genre === 'brut'): ?>
      <strong>Contenu du fichier</strong>, tel qu'il est enregistré.
    <?php elseif ($enrichis !== []): ?>
      <strong>Aperçu du texte.</strong> Le gras, l'italique, le souligné, la taille,
      la couleur, le surlignage, l'alignement, les titres et les listes sont rendus ; les images,
      les tableaux et la pagination ne le sont pas — un navigateur ne sait pas afficher un
      <?= e($format) ?>. Téléchargez le fichier pour l'ouvrir tel quel dans Word
      ou LibreOffice.
    <?php else: ?>
      <strong>Aperçu du texte.</strong> La mise en forme, les images et la pagination
      ne sont pas reproduites — un navigateur ne sait pas afficher un <?= e($format) ?>.
      Téléchargez le fichier pour l'ouvrir tel quel dans Word ou LibreOffice.
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($erreur !== null): ?>
  <div class="vide">
    <span class="vide__icone">⚠️</span>
    <p><?= e($erreur) ?></p>
    <a class="bouton bouton--secondaire" href="<?= url('fichiers/' . $fichier['id'], ['telecharger' => 1]) ?>">
      Télécharger le fichier
    </a>
  </div>
<?php elseif ($genre === 'pdf'): ?>
  <?php // Le document lui-même, dans la page, avec la visionneuse du navigateur. ?>
  <div class="carte apercu-cadre">
    <iframe src="<?= url('fichiers/' . $fichier['id']) ?>"
            title="<?= e((string) $fichier['nom_origine']) ?>"></iframe>
  </div>
  <p class="champ__aide" style="margin-top:.6rem">
    Si le document ne s'affiche pas,
    <a href="<?= url('fichiers/' . $fichier['id']) ?>" target="_blank" rel="noopener">l'ouvrir dans un onglet</a>.
  </p>

<?php elseif ($genre === 'image'): ?>
  <div class="carte apercu-image">
    <a href="<?= url('fichiers/' . $fichier['id']) ?>" target="_blank" rel="noopener"
       title="Voir l'image en taille réelle">
      <img src="<?= url('fichiers/' . $fichier['id']) ?>" alt="<?= e((string) $fichier['nom_origine']) ?>">
    </a>
  </div>

<?php elseif ($genre === 'brut'): ?>
  <div class="carte">
    <pre class="apercu-brut"><?= e($texte) ?></pre>
  </div>
  <?php if ($tronque): ?>
    <p class="champ__aide" style="margin-top:.6rem">
      Fichier volumineux : seul le début est affiché. Téléchargez-le pour tout voir.
    </p>
  <?php endif; ?>

<?php elseif ($estTableur): ?>
  <?php if ($lignes === []): ?>
    <div class="vide">
      <span class="vide__icone">📊</span>
      <p>Ce classeur ne contient aucune donnée sur sa première feuille.</p>
      <a class="bouton bouton--secondaire" href="<?= url('fichiers/' . $fichier['id'], ['telecharger' => 1]) ?>">
        Télécharger le fichier
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
          $total > $limite => ' sur ' . $total . ' — les suivantes ne sont pas montrées ici.'
                            . ' Téléchargez le fichier pour tout voir.',
          $format === 'classeur Excel' => ' · première feuille du classeur.',
          default => '.',
      };
      ?>
      <?= $n ?> ligne<?= $n > 1 ? 's' : '' ?> affichée<?= $n > 1 ? 's' : '' ?><?= $suite ?>
    </p>
  <?php endif; ?>
<?php elseif ($paragraphes === []): ?>
  <div class="vide">
    <span class="vide__icone">📄</span>
    <p>Ce document ne contient aucun texte — il est peut-être vide, ou composé
       uniquement d'images.</p>
    <a class="bouton bouton--secondaire" href="<?= url('fichiers/' . $fichier['id'], ['telecharger' => 1]) ?>">
      Télécharger le fichier
    </a>
  </div>
<?php else: ?>
  <?php
  /*
   * Le sommaire est refait ici à partir des titres lus, et non repris du
   * document : il est ainsi toujours d'accord avec ce qu'on voit dessous,
   * même si le fichier n'a pas encore été rouvert dans Word.
   */
  $plan = [];
  foreach ($paragraphes as $rang => $paragraphe) {
      if ($enrichis[$rang]['sommaire'] ?? false) {
          continue;
      }
      $niveau = (int) ($enrichis[$rang]['titre'] ?? 0);
      $intitule = trim((string) preg_replace('/\s+/u', ' ', (string) $paragraphe));
      // Le sommaire s'arrête au niveau choisi : ce qui est plus profond
      // reste dans le texte, mais n'y figure pas.
      if ($niveau > 0 && $niveau <= $sommaire && $intitule !== '') {
          $plan[$rang] = ['niveau' => $niveau, 'texte' => $intitule];
      }
  }
  ?>
  <?php if ($sommaire > 0 && $plan !== []): ?>
    <nav class="carte apercu-sommaire" aria-labelledby="apercu-sommaire-titre">
      <h2 id="apercu-sommaire-titre" class="apercu-sommaire__titre">Sommaire</h2>
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

    foreach ($paragraphes as $rang => $paragraphe):
        $riche = $enrichis[$rang] ?? null;
        // Le sommaire du document est refait plus haut, à partir des titres :
        // le montrer ici le donnerait deux fois, dont une périmée.
        if ($riche['sommaire'] ?? false) {
            continue;
        }
        $aligne = ['gauche' => 'left', 'centre' => 'center',
                   'droite' => 'right', 'justifie' => 'justify'][$riche['alignement'] ?? ''] ?? null;
        $style = $aligne === null ? '' : ' style="text-align:' . $aligne . '"';
        $corps = $riche === null ? e($paragraphe) : $riche['html'];
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
            echo '<' . $balise . $classe . $style . '>' . $corps . '</' . $balise . '>';
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
        echo '<li' . ($numero > 0 ? ' value="' . $numero . '"' : '') . $style . '>' . $corps;
        $porte[$dernier] = true;
    endforeach;

    while ($pile !== []) {
        $fermerUn();
    }
    ?>
  </article>
  <p class="champ__aide" style="margin-top:.6rem">
    <?= count($paragraphes) ?> paragraphe<?= count($paragraphes) > 1 ? 's' : '' ?> lu<?= count($paragraphes) > 1 ? 's' : '' ?>.
  </p>
<?php endif; ?>
