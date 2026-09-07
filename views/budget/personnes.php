<?php
/**
 * Le carnet des personnes qui vous remboursent.
 *
 * @var array $personnes  chacune avec ce qu'elle vous doit encore
 * @var list<string> $oublies  noms portés par des opérations, mais pas au carnet
 * @var array $groupes  chacun avec les noms de ses membres
 */
$csrf = Session::jetonCsrf();
?>

<?= Vue::rendre('budget/_onglets', ['onglet' => 'personnes']) ?>

<div class="entete-page">
  <div>
    <h1>👥 Personnes</h1>
    <p>Celles qui vous remboursent. Les renommer ou les fusionner ici change
      aussi vos opérations. Un groupe les rassemble pour lire plusieurs comptes
      d'un coup.</p>
  </div>
</div>

<div class="colonnes">
  <div>
    <?php if ($personnes === []): ?>
      <div class="vide">
        <span class="vide__icone">👥</span>
        <p>Le carnet est vide. Ajoutez une personne, ou cochez
          « à me faire rembourser » sur une dépense : son nom viendra ici tout seul.</p>
      </div>
    <?php endif; ?>

    <?php foreach ($personnes as $p): ?>
      <?php
      $nb = (int) $p['nb_operations'];
      $reste = (float) $p['reste'];
      ?>
      <section class="carte">
        <div class="matiere-carte">
          <div style="flex:1;min-width:0">
            <h3 style="margin-bottom:.15rem;font-size:1.05rem"><?= e($p['nom']) ?></h3>
            <p class="discret" style="margin:0">
              <?php if ($nb === 0): ?>
                Aucune opération à son nom
              <?php else: ?>
                <?= $nb ?> opération<?= $nb > 1 ? 's' : '' ?>
                <?php if ($reste > 0): ?>
                  · <strong><?= e(montant_fr($reste)) ?></strong> encore à réclamer
                <?php else: ?>
                  · rien à réclamer
                <?php endif; ?>
              <?php endif; ?>
            </p>
          </div>

          <div class="actions">
            <?php if ($nb > 0): ?>
              <a class="bouton bouton--discret bouton--petit"
                 href="<?= url('budget/remboursements', ['personne' => $p['nom']]) ?>">Voir</a>
            <?php endif; ?>
            <button class="bouton bouton--secondaire bouton--petit" type="button"
                    data-bascule="edition-pers-<?= (int) $p['id'] ?>">Modifier</button>
          </div>
        </div>

        <div id="edition-pers-<?= (int) $p['id'] ?>" hidden style="margin-top:1rem">
          <hr class="separateur" style="margin:.75rem 0">

          <form method="post" action="<?= url('budget/personnes/' . $p['id'] . '/renommer') ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="champ">
              <label for="nom-p-<?= (int) $p['id'] ?>">Nom</label>
              <input type="text" id="nom-p-<?= (int) $p['id'] ?>" name="nom" required maxlength="80"
                     value="<?= e($p['nom']) ?>">
              <?php if ($nb > 0): ?>
                <span class="champ__aide">
                  Le nouveau nom remplacera l'ancien sur
                  <?= $nb ?> opération<?= $nb > 1 ? 's' : '' ?>.
                </span>
              <?php endif; ?>
            </div>
            <button class="bouton bouton--petit" type="submit">Renommer</button>
          </form>

          <?php if (count($personnes) > 1): ?>
            <hr class="separateur" style="margin:.9rem 0">

            <?php
            /*
             * Fusionner, pour la même personne écrite de deux façons. Tout ce
             * qui porte ce nom-ci passe à l'autre, et celle-ci quitte le
             * carnet : c'est ce que « renommer » ne peut pas faire, un nom déjà
             * pris ne pouvant pas l'être deux fois.
             */
            ?>
            <form method="post" action="<?= url('budget/personnes/' . $p['id'] . '/fusionner') ?>"
                  data-confirmation="Fusionner « <?= e($p['nom']) ?> » dans une autre personne ?<?= $nb > 0
                      ? ' Ses ' . $nb . ' opération' . ($nb > 1 ? 's changeront' : ' changera') . ' de nom.'
                      : '' ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <div class="champ">
                <label for="cible-<?= (int) $p['id'] ?>">Fusionner dans</label>
                <select id="cible-<?= (int) $p['id'] ?>" name="cible" required>
                  <option value="">— Choisir une personne —</option>
                  <?php foreach ($personnes as $autre): ?>
                    <?php if ((int) $autre['id'] === (int) $p['id']) { continue; } ?>
                    <option value="<?= (int) $autre['id'] ?>"><?= e($autre['nom']) ?></option>
                  <?php endforeach; ?>
                </select>
                <span class="champ__aide">
                  « <?= e($p['nom']) ?> » disparaîtra du carnet, et tout ce qui la nomme
                  passera à l'autre.
                </span>
              </div>
              <button class="bouton bouton--secondaire bouton--petit" type="submit">Fusionner</button>
            </form>
          <?php endif; ?>

          <hr class="separateur" style="margin:.9rem 0">

          <?php
          /*
           * Retirer du carnet n'efface rien des dépenses : le nom qu'elles
           * portent était vrai le jour où on les a payées, et l'effacer
           * fausserait les relevés passés.
           */
          ?>
          <form method="post" action="<?= url('budget/personnes/' . $p['id'] . '/supprimer') ?>"
                data-confirmation="Retirer « <?= e($p['nom']) ?> » du carnet ?<?= $nb > 0
                    ? ' Les ' . $nb . ' opération' . ($nb > 1 ? 's' : '') . ' à son nom le garderont.'
                    : '' ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button class="bouton bouton--danger bouton--petit" type="submit">
              Retirer du carnet
            </button>
          </form>
        </div>
      </section>
    <?php endforeach; ?>
  </div>

  <div>
    <div class="carte">
      <h2>Ajouter une personne</h2>
      <p class="champ__aide">
        Utile pour préparer un nom avant la première dépense. Sinon, il s'inscrit
        tout seul dès que vous enregistrez une dépense à se faire rembourser.
      </p>
      <form method="post" action="<?= url('budget/personnes') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="champ">
          <label for="nom">Nom</label>
          <input type="text" id="nom" name="nom" required maxlength="80" placeholder="Parents">
        </div>
        <button class="bouton bouton--bloc" type="submit">Ajouter</button>
      </form>
    </div>

    <?php
    /*
     * Les groupes. Ils ne se choisissent pas sur une dépense : celle-ci reste
     * due par une personne, et une seule. Un groupe sert à regarder — « combien
     * me doivent mes colocataires, tous ensemble » — depuis la page des
     * remboursements.
     */
    ?>
    <div class="carte">
      <h2>Groupes</h2>

      <?php if ($groupes === []): ?>
        <p class="champ__aide">
          Aucun groupe. Un groupe rassemble plusieurs personnes du carnet pour
          voir d'un coup ce qu'elles vous doivent — vos colocataires, votre
          famille.
        </p>
      <?php endif; ?>

      <?php foreach ($groupes as $g): ?>
        <div style="margin-bottom:.9rem">
          <div class="matiere-carte" style="gap:.6rem">
            <div style="flex:1;min-width:0">
              <strong><?= e($g['nom']) ?></strong><br>
              <span class="discret">
                <?= $g['membres'] === []
                    ? 'personne dedans'
                    : e(implode(', ', $g['membres'])) ?>
              </span>
            </div>
            <div class="actions">
              <?php if ($g['membres'] !== []): ?>
                <a class="bouton bouton--discret bouton--petit"
                   href="<?= url('budget/remboursements', ['qui' => 'g:' . $g['id']]) ?>">Voir</a>
              <?php endif; ?>
              <button class="bouton bouton--secondaire bouton--petit" type="button"
                      data-bascule="edition-grp-<?= (int) $g['id'] ?>">Modifier</button>
            </div>
          </div>

          <div id="edition-grp-<?= (int) $g['id'] ?>" hidden style="margin-top:.7rem">
            <form method="post" action="<?= url('budget/groupes/' . $g['id'] . '/modifier') ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <div class="champ">
                <label for="nom-g-<?= (int) $g['id'] ?>">Nom du groupe</label>
                <input type="text" id="nom-g-<?= (int) $g['id'] ?>" name="nom" required maxlength="80"
                       value="<?= e($g['nom']) ?>">
              </div>

              <?php if ($personnes === []): ?>
                <p class="champ__aide">Le carnet est vide : ajoutez d'abord des personnes.</p>
              <?php else: ?>
                <fieldset class="sources">
                  <legend>Qui en fait partie</legend>
                  <?php foreach ($personnes as $membre): ?>
                    <label class="sources__choix">
                      <input type="checkbox" name="membres[]" value="<?= (int) $membre['id'] ?>"
                             <?= in_array($membre['nom'], $g['membres'], true) ? 'checked' : '' ?>>
                      <span><?= e($membre['nom']) ?></span>
                    </label>
                  <?php endforeach; ?>
                </fieldset>
              <?php endif; ?>

              <p class="actions" style="margin-top:.8rem">
                <button class="bouton bouton--petit" type="submit">Enregistrer</button>
              </p>
            </form>

            <form method="post" action="<?= url('budget/groupes/' . $g['id'] . '/supprimer') ?>"
                  data-confirmation="Supprimer le groupe « <?= e($g['nom']) ?> » ? Ses membres restent au carnet.">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--danger bouton--petit" type="submit">Supprimer le groupe</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>

      <hr class="separateur" style="margin:.9rem 0">

      <form method="post" action="<?= url('budget/groupes') ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="champ">
          <label for="nom-groupe">Nouveau groupe</label>
          <input type="text" id="nom-groupe" name="nom" required maxlength="80" placeholder="Colocataires">
        </div>
        <button class="bouton bouton--secondaire bouton--bloc" type="submit">Créer le groupe</button>
      </form>
    </div>

    <?php if ($oublies !== []): ?>
      <?php
      /*
       * Des noms écrits sur des opérations d'avant le carnet, ou revenus d'une
       * sauvegarde. Ils ne sont plus proposés à la saisie tant qu'ils n'y sont
       * pas entrés : mieux vaut le dire que de les laisser disparaître.
       */
      ?>
      <div class="carte">
        <h2>Noms hors carnet</h2>
        <p class="champ__aide">
          Portés par des opérations, mais absents du carnet : ils ne sont plus
          proposés quand vous saisissez une dépense. Ajoutez-les ici ; pour les
          rattacher à quelqu'un du carnet, fusionnez-les ensuite.
        </p>
        <?php foreach ($oublies as $nom): ?>
          <form method="post" action="<?= url('budget/personnes') ?>" class="en-ligne"
                style="margin-bottom:.5rem">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="nom" value="<?= e($nom) ?>">
            <button class="bouton bouton--secondaire bouton--petit" type="submit">
              ➕ <?= e($nom) ?>
            </button>
          </form>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
