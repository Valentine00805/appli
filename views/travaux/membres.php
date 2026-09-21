<?php
/**
 * Les membres du projet, la discussion du groupe, le lien public, et les
 * réglages du projet.
 *
 * @var array $projet
 * @var list<array> $membres
 * @var list<array> $amisAInviter
 * @var list<array> $discussions  les discussions de groupe dont je fais partie
 * @var string $onglet
 */
$dansUneFenetre = $dansUneFenetre ?? false;
// Dans la fenêtre, on y reste : les formulaires s'y enregistrent.
$envoi = $dansUneFenetre ? ' data-envoi-fenetre' : '';
$csrf = Session::jetonCsrf();
$admin = $projet['role'] === 'admin';
$jeton = $projet['jeton'] === null ? null : (string) $projet['jeton'];
?>
<?= Vue::rendre('travaux/_onglets', ['projet' => $projet, 'onglet' => $onglet, 'dansUneFenetre' => $dansUneFenetre]) ?>

<div class="colonnes">
  <div class="pile">
    <section class="carte">
      <h2>Membres <span class="discret">(<?= count(array_filter($membres, static fn (array $m): bool => $m['statut'] === 'membre')) ?>)</span></h2>
      <ul class="travaux-membres">
        <?php foreach ($membres as $m): ?>
          <?php $moi = (int) ($m['user_id'] ?? 0) === Auth::id(); ?>
          <li class="travaux-membre">
            <?php if ($m['user_id'] !== null): ?>
              <?= Amis::avatar((int) $m['user_id'], (string) $m['nom_affiche'], 'avatar--mini') ?>
            <?php else: ?>
              <span class="avatar avatar--mini" aria-hidden="true">👤</span>
            <?php endif; ?>
            <span style="flex:1;min-width:0">
              <strong><?= e((string) $m['nom_affiche']) ?></strong><?= $moi ? ' <span class="discret">(moi)</span>' : '' ?>
              <?php if ($m['role'] === 'admin'): ?><span class="pastille">Administrateur</span><?php endif; ?>
              <?php if ($m['statut'] === 'invite'): ?><span class="pastille pastille--muette">Invitation envoyée</span><?php endif; ?>
              <?php if ($m['user_id'] === null): ?><span class="pastille pastille--muette">Sans compte</span><?php endif; ?>
              <?php if ($m['statut'] === 'membre'): ?>
                <br><span class="discret"><?= (int) $m['a_faire'] ?> tâche<?= (int) $m['a_faire'] > 1 ? 's' : '' ?> à faire
                  · <?= (int) $m['faites'] ?> faite<?= (int) $m['faites'] > 1 ? 's' : '' ?></span>
              <?php endif; ?>
            </span>
            <?php if ($admin && !$moi): ?>
              <span class="en-ligne">
                <?php if ($m['user_id'] !== null && $m['statut'] === 'membre'): ?>
                  <form method="post"<?= $envoi ?> action="<?= url('travaux/membres/' . (int) $m['id'] . ($m['role'] === 'admin' ? '/membre' : '/admin')) ?>" class="en-ligne">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button class="bouton bouton--discret bouton--petit" type="submit">
                      <?= $m['role'] === 'admin' ? 'Retirer l’administration' : 'Nommer administrateur' ?></button>
                  </form>
                <?php endif; ?>
                <form method="post"<?= $envoi ?> action="<?= url('travaux/membres/' . (int) $m['id'] . '/retirer') ?>" class="en-ligne"
                      data-confirmation="Retirer <?= e((string) $m['nom_affiche']) ?> du projet ? Ses tâches resteront, sans personne pour les faire.">
                  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                  <button class="bouton bouton--discret bouton--petit" type="submit"><?= $m['statut'] === 'invite' ? 'Annuler l’invitation' : 'Retirer' ?></button>
                </form>
              </span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <?php if ($admin): ?>
      <section class="carte">
        <h2>Inviter des amis</h2>
        <?php if ($amisAInviter === []): ?>
          <p class="discret">Tous vos amis sont déjà dans le projet — ou vous n’en avez pas encore : <a href="<?= url('amis') ?>">en ajouter</a>.</p>
        <?php else: ?>
          <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/inviter') ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <label class="discussions-recherche">
              <span class="sr-only">Rechercher un ami</span>
              <input type="search" placeholder="Rechercher un ami" autocomplete="off" data-filtre-liste="[data-liste-amis-inviter]">
            </label>
            <ul class="groupe-choix__liste partage-liste" data-liste-amis-inviter>
              <?php foreach ($amisAInviter as $a): ?>
                <li data-nom="<?= e(mb_strtolower((string) $a['pseudo'])) ?>">
                  <label class="groupe-choix__ami">
                    <input type="checkbox" name="amis[]" value="<?= (int) $a['id'] ?>">
                    <?= Amis::avatar((int) $a['id'], (string) $a['pseudo'], 'avatar--mini') ?>
                    <span class="partage-liste__nom"><?= e((string) $a['pseudo']) ?></span>
                  </label>
                </li>
              <?php endforeach; ?>
            </ul>
            <p class="discret" data-filtre-vide hidden style="margin:.4rem 0 0">Aucun ami ne porte ce nom.</p>
            <button class="bouton bouton--petit" type="submit" style="margin-top:.6rem">Inviter</button>
          </form>
        <?php endif; ?>
      </section>

      <section class="carte">
        <h2>Ajouter une personne sans compte</h2>
        <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/sans-compte') ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <div class="champ">
            <label for="nom-sans-compte">Son nom</label>
            <input type="text" id="nom-sans-compte" name="nom" required maxlength="60" placeholder="Camille">
            <span class="champ__aide">On peut lui confier des tâches ; elle suit le projet par le lien public, sans rien modifier.</span>
          </div>
          <button class="bouton bouton--petit" type="submit">Ajouter</button>
        </form>
      </section>
    <?php endif; ?>
  </div>

  <div class="pile">
    <section class="carte">
      <h2>💬 Discussion du groupe</h2>
      <?php if ($projet['conversation_id'] !== null): ?>
        <p><a class="bouton bouton--bloc" href="<?= url('groupes/' . (int) $projet['conversation_id']) ?>">Ouvrir la discussion</a></p>
        <p class="champ__aide">Qui rejoint le projet y entre aussi.</p>
        <?php if ($admin): ?>
          <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/discussion/delier') ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button class="bouton bouton--discret bouton--petit" type="submit">Délier la discussion</button>
          </form>
        <?php endif; ?>
      <?php else: ?>
        <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/discussion') ?>">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--bloc" type="submit">Créer la discussion du groupe</button>
          <span class="champ__aide">Avec tous les membres qui ont un compte.</span>
        </form>
        <?php if ($discussions !== []): ?>
          <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/discussion') ?>" style="margin-top:.8rem">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="champ">
              <label for="discussion-existante">Ou relier une discussion existante</label>
              <select id="discussion-existante" name="conversation_id">
                <?php foreach ($discussions as $d): ?>
                  <option value="<?= (int) $d['id'] ?>"><?= e((string) $d['nom']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="bouton bouton--secondaire bouton--petit" type="submit">Relier</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <section class="carte">
      <h2>🔗 Lien public</h2>
      <?php if ($jeton === null): ?>
        <p class="discret">Pour qui n’a pas de compte : il voit les tâches, les échéances, les fichiers et le document, sans rien modifier.</p>
        <?php if ($admin): ?>
          <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/lien') ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button class="bouton bouton--secondaire bouton--bloc" type="submit">Créer le lien</button>
          </form>
        <?php endif; ?>
      <?php else: ?>
        <div class="partage-lien" data-partage-lien>
          <label class="sr-only" for="lien-travaux">Lien public du projet</label>
          <input type="text" id="lien-travaux" readonly value="<?= e(Travaux::adresseLien($jeton)) ?>" data-partage-adresse>
          <button class="bouton" type="button" data-partage-copier>Copier</button>
        </div>
        <p class="champ__aide" data-partage-etat aria-live="polite">Qui a ce lien voit le projet, en lecture seule.</p>
        <?php if ($admin): ?>
          <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/lien/fermer') ?>"
                data-confirmation="Désactiver le lien ? Il ne mènera plus nulle part.">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button class="bouton bouton--discret bouton--petit" type="submit">Désactiver le lien</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <?php if ($admin): ?>
      <details class="carte">
        <summary><strong>Réglages du projet</strong></summary>
        <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/modifier') ?>" style="margin-top:.8rem">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <div class="champ">
            <label for="nom-projet">Nom</label>
            <input type="text" id="nom-projet" name="nom" required maxlength="<?= Travaux::NOM_MAX ?>" value="<?= e((string) $projet['nom']) ?>">
          </div>
          <div class="champ">
            <label for="description-projet">Le sujet, les consignes</label>
            <textarea id="description-projet" name="description" rows="3" maxlength="2000"><?= e((string) ($projet['description'] ?? '')) ?></textarea>
          </div>
          <button class="bouton bouton--petit" type="submit">Enregistrer</button>
        </form>
        <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/supprimer') ?>" style="margin-top:1rem"
              data-confirmation="Supprimer « <?= e((string) $projet['nom']) ?> » pour tout le groupe ? Tâches, fichiers, document et échéances seront effacés.">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button class="bouton bouton--danger bouton--petit" type="submit">Supprimer le projet</button>
        </form>
      </details>
    <?php endif; ?>

    <form method="post"<?= $envoi ?> action="<?= url('travaux/' . (int) $projet['id'] . '/quitter') ?>"
          data-confirmation="Quitter « <?= e((string) $projet['nom']) ?> » ? Ses échéances quitteront votre calendrier.">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <button class="bouton bouton--discret bouton--petit" type="submit">🚪 Quitter le projet</button>
    </form>
  </div>
</div>
