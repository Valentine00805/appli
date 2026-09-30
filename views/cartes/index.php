<?php
/**
 * Les paquets de cartes, un par cours, et ce qui est dû aujourd'hui.
 *
 * @var array $paquets  un cours par ligne, avec ses compteurs
 * @var int $aRevoir    cartes dues, tous cours confondus
 * @var int $total      cartes existantes
 * @var array $cours     tous les cours, pour choisir où puiser
 * @var array $propositions   ce que le générateur vient de trouver, à valider,
 *                            chacune sachant de quel cours elle vient
 * @var array $cartesParCours  les cartes de chaque paquet, par cours
 * @var array $documentsParCours  les fichiers de chaque cours, par cours
 */
?>

<div class="entete-page">
  <div>
    <h1><?= e(t('crt.titre')) ?></h1>
    <p>
      <?php if ($total === 0): ?>
        <?= e(t('crt.pitch')) ?>
      <?php elseif ($aRevoir === 0): ?>
        <?= e(t('crt.rien_aujourdhui', ['n' => tn('crt.nb', $total)])) ?>
      <?php else: ?>
        <?= e(t('crt.dues_sur', ['dues' => tn('crt.nb', $aRevoir), 'total' => $total])) ?>
      <?php endif; ?>
    </p>
  </div>

  <?php if ($aRevoir > 0): ?>
    <a class="bouton" href="<?= url('cartes/seance') ?>" data-fenetre><?= e(tn('crt.reviser_n', $aRevoir)) ?></a>
  <?php endif; ?>
</div>

<?php if ($propositions !== []): ?>
  <?php
  /*
   * Les propositions ne sont pas encore des cartes : rien n'est enregistré tant
   * que l'utilisateur n'a pas coché. Chaque question et chaque réponse reste
   * modifiable ici, parce qu'une règle se trompe parfois de découpe.
   */
  ?>
  <?php
  /*
   * Chaque proposition porte son cours : une même fournée peut venir de
   * plusieurs, et chaque carte doit rejoindre le bon paquet.
   */
  $titres = array_values(array_unique(array_column($propositions, 'cours_titre')));
  ?>
  <form class="carte propositions" method="post" action="<?= url('cartes/retenir') ?>">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

    <div class="propositions__entete">
      <h2><?= e(tn('crt.propositions', count($propositions))) ?>
        <span class="discret">
          <?= e(count($titres) === 1
              ? t('crt.pour_cours', ['nom' => $titres[0]])
              : t('crt.pour_n_cours', ['n' => count($titres)])) ?>
        </span></h2>
      <span class="discret"><?= e(t('crt.decochez')) ?></span>
    </div>

    <ul class="propositions__liste">
      <?php $venuDe = null; ?>
      <?php foreach ($propositions as $rang => $p): ?>
        <?php if (count($titres) > 1 && $p['cours_titre'] !== $venuDe): ?>
          <?php $venuDe = $p['cours_titre']; ?>
          <li class="propositions__cours"><?= e($venuDe) ?></li>
        <?php endif; ?>
        <li class="proposition">
          <label class="proposition__garder">
            <input type="checkbox" name="carte[<?= $rang ?>][garder]" value="1" checked>
            <span class="proposition__source">
              <?= e(match ($p['origine']) {
                  'cours'   => t('crt.venue_cours'),
                  'fiche'   => t('crt.venue_fiche'),
                  'fichier' => $p['source'] !== ''
                      ? t('crt.venue_fichier', ['nom' => $p['source']])
                      : t('crt.venue_document'),
                  default   => '',
              }) ?>
            </span>
            <?php $genre = $p['genre'] ?? 'definition'; ?>
            <?php if ($genre !== 'definition'): ?>
              <span class="proposition__genre proposition__genre--<?= e($genre) ?>">
                <?= e(t($genre === 'devoir' ? 'crt.genre_devoir' : 'crt.genre_trous')) ?>
              </span>
            <?php endif; ?>
          </label>
          <div class="proposition__couple">
            <input type="text" name="carte[<?= $rang ?>][question]" value="<?= e($p['question']) ?>"
                   aria-label="<?= e(t('crt.question')) ?>" maxlength="500">
            <input type="text" name="carte[<?= $rang ?>][reponse]" value="<?= e($p['reponse']) ?>"
                   aria-label="<?= e(t('crt.reponse')) ?>" placeholder="<?= $p['reponse'] === '' ? e(t('crt.reponse_vide')) : '' ?>">
          </div>
          <input type="hidden" name="carte[<?= $rang ?>][cours]" value="<?= (int) $p['cours_id'] ?>">
          <input type="hidden" name="carte[<?= $rang ?>][origine]" value="<?= e($p['origine']) ?>">
          <input type="hidden" name="carte[<?= $rang ?>][source]" value="<?= e($p['source']) ?>">
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="actions">
      <button class="bouton" type="submit"><?= e(t('crt.ajouter_cochees')) ?></button>
      <a class="bouton bouton--discret" href="<?= url('cartes') ?>"><?= e(t('crt.abandonner')) ?></a>
    </div>
  </form>
<?php endif; ?>

<section class="carte fabrique">
  <h2><?= e(t('crt.fabriquer')) ?></h2>
  <?php if ($cours === []): ?>
    <p class="discret"><?= e(t('crt.aucun_cours')) ?>
      <a href="<?= url('cours/nouveau') ?>" data-fenetre><?= e(t('crt.en_creer_un')) ?></a>.</p>
  <?php else: ?>
    <p class="champ__aide">
      <?= e(t('crt.fabrique_aide')) ?>
    </p>
    <form method="post" action="<?= url('cartes/proposer') ?>" class="fabrique__form">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <?php
      /*
       * Des cases plutôt qu'une liste déroulante : on peut puiser dans
       * plusieurs cours d'un coup, et les voir tous sans dérouler quoi que ce
       * soit. Le premier est coché, pour que le formulaire serve tel quel.
       */
      ?>
      <fieldset class="sources choix-cours">
        <legend><?= e(t('crt.cours')) ?></legend>
        <?php $matiere = false; ?>
        <?php foreach ($cours as $rang => $c): ?>
          <?php if ($c['matiere_nom'] !== $matiere): ?>
            <?php $matiere = $c['matiere_nom']; ?>
            <p class="documents__place"><?= e($matiere ?? t('focus.sans_matiere')) ?></p>
          <?php endif; ?>
          <label class="sources__choix">
            <input type="checkbox" name="cours[]" value="<?= (int) $c['id'] ?>"
                   data-choix-cours <?= $rang === 0 ? 'checked' : '' ?>>
            <span>
              <?= e($c['titre']) ?>
              <?php if (!$c['a_fiche']): ?><span class="discret"><?= e(t('crt.sans_fiche')) ?></span><?php endif; ?>
            </span>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <?= Vue::rendre('cartes/_sources', ['cours' => null]) ?>

      <?php
      /*
       * Les documents de chaque cours attendent déjà dans la page : cocher un
       * cours fait apparaître les siens, sans rien redemander au serveur. Ils
       * arrivent tous cochés — décocher sert à écarter un document précis.
       */
      ?>
      <?php foreach ($cours as $c): ?>
        <?= Vue::rendre('cartes/_documents', [
            'coursId'    => (int) $c['id'],
            'coursTitre' => (string) $c['titre'],
            'documents'  => $documentsParCours[(int) $c['id']] ?? [],
        ]) ?>
      <?php endforeach; ?>

      <button class="bouton" type="submit"><?= e(t('crt.proposer')) ?></button>
    </form>

    <hr class="separateur">

    <h3><?= e(t('crt.ou_ecrire')) ?></h3>
    <form method="post" action="<?= url('cartes/carte') ?>" class="fabrique__form">
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
      <div class="champ">
        <label for="cours-carte"><?= e(t('crt.cours')) ?></label>
        <select id="cours-carte" name="cours" required>
          <?php $matiere = false; ?>
          <?php foreach ($cours as $c): ?>
            <?php if ($c['matiere_nom'] !== $matiere): ?>
              <?php if ($matiere !== false): ?></optgroup><?php endif; ?>
              <?php $matiere = $c['matiere_nom']; ?>
              <optgroup label="<?= e($matiere ?? t('focus.sans_matiere')) ?>">
            <?php endif; ?>
            <option value="<?= (int) $c['id'] ?>">
              <?= e($c['titre']) ?>
              <?php if (!$c['a_fiche']): ?><?= e(t('crt.sans_fiche_tiret')) ?><?php endif; ?>
            </option>
          <?php endforeach; ?>
          <?php if ($matiere !== false): ?></optgroup><?php endif; ?>
        </select>
      </div>
      <div class="champ">
        <label for="question"><?= e(t('crt.question')) ?></label>
        <input type="text" id="question" name="question" maxlength="500" required>
      </div>
      <div class="champ">
        <label for="reponse"><?= e(t('crt.reponse')) ?></label>
        <textarea id="reponse" name="reponse" rows="3" required></textarea>
      </div>
      <button class="bouton bouton--secondaire" type="submit"><?= e(t('crt.ajouter_carte')) ?></button>
    </form>
  <?php endif; ?>
</section>

<?php if ($paquets === []): ?>
  <div class="vide">
    <span class="vide__icone">🃏</span>
    <p><?= e(t('crt.aucune_index')) ?></p>
  </div>
<?php else: ?>
  <div class="pile pile--paquets">
    <?php foreach ($paquets as $p): ?>
      <?php
      $dues = (int) $p['a_revoir'];
      $sues = (int) $p['sues'];
      $nb = (int) $p['total'];
      ?>
      <?php
      /*
       * Le paquet s'ouvre ici même : « details » le fait sans une ligne de
       * script, et sans quitter l'onglet où l'on est en train de travailler.
       */
      ?>
      <details class="carte paquet-bloc">
        <summary class="paquet-bloc__entete">
          <span class="paquet-bloc__titre">
            <?php if ($p['matiere_nom'] !== null): ?>
              <span class="pastille" style="background:<?= e($p['matiere_couleur']) ?>;color:<?= e(couleur_texte($p['matiere_couleur'])) ?>">
                <?= e($p['matiere_nom']) ?>
              </span>
            <?php else: ?>
              <span class="discret"><?= e(t('focus.sans_matiere')) ?></span>
            <?php endif; ?>
            <strong><?= e($p['titre']) ?></strong>
            <?php if ($dues > 0): ?>
              <span class="carte-du"><?= e(t('crt.dues_puce', ['n' => $dues])) ?></span>
            <?php endif; ?>
          </span>

          <span class="paquet-bloc__mesure">
            <?= Vue::rendre('cours/_anneau', [
                'pourcentage' => avancement_cartes($nb, (float) $p['boite_moyenne']),
                'titre'       => t('fiche.cartes_avancement'),
            ]) ?>
            <span class="discret">
              <?= e(tn('crt.nb', $nb)) ?>
              <?php if ($sues > 0): ?><?= e(tn('crt.sues', $sues)) ?><?php endif; ?>
            </span>
          </span>
        </summary>

        <p class="actions paquet-bloc__actions">
          <?php if ($dues > 0): ?>
            <a class="bouton bouton--petit" href="<?= url('cartes/seance', ['cours' => $p['id']]) ?>" data-fenetre><?= e(t('crt.reviser')) ?></a>
          <?php endif; ?>
          <a class="bouton bouton--secondaire bouton--petit" href="<?= url('cours/' . $p['id']) ?>"><?= e(t('crt.voir_cours')) ?></a>
        </p>

        <?= Vue::rendre('cartes/_paquet', [
            'cours'  => $p,
            'cartes' => $cartesParCours[(int) $p['id']] ?? [],
            'retour' => 'onglet',
        ]) ?>
      </details>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
