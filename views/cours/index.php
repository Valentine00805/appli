<?php
/**
 * @var array $cours, $matieres, $tags
 * @var string $recherche, $tri
 * @var ?int $matiereId, $tagId
 * @var bool $favoris
 */
?>

<div class="entete-page">
  <div>
    <h1>Mes cours</h1>
    <p><?= count($cours) ?> cours affiché<?= count($cours) > 1 ? 's' : '' ?></p>
  </div>
  <div class="actions">
    <a class="bouton" href="<?= url('cours/nouveau') ?>">+ Nouveau cours</a>
  </div>
</div>

<form class="filtres" method="get" action="<?= url('cours') ?>" data-auto-envoi>
  <div class="champ">
    <label for="f-q">Rechercher</label>
    <input type="search" id="f-q" name="q" value="<?= e($recherche) ?>" placeholder="titre, contenu…">
  </div>

  <div class="champ">
    <label for="f-matiere">Matière</label>
    <select id="f-matiere" name="matiere">
      <option value="">Toutes</option>
      <?php foreach ($matieres as $m): ?>
        <option value="<?= (int) $m['id'] ?>"<?= $matiereId === (int) $m['id'] ? ' selected' : '' ?>>
          <?= e($m['nom']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <?php if ($tags !== []): ?>
    <div class="champ">
      <label for="f-tag">Tag</label>
      <select id="f-tag" name="tag">
        <option value="">Tous</option>
        <?php foreach ($tags as $t): ?>
          <option value="<?= (int) $t['id'] ?>"<?= $tagId === (int) $t['id'] ? ' selected' : '' ?>>
            <?= e($t['nom']) ?> (<?= (int) $t['nb'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <?php if ($dossiers !== []): ?>
    <div class="champ">
      <label for="f-dossier">Dossier</label>
      <select id="f-dossier" name="dossier">
        <option value="">Tous</option>
        <?php foreach ($dossiers as $d): ?>
          <option value="<?= (int) $d['id'] ?>"<?= $dossierId === (int) $d['id'] ? ' selected' : '' ?>>
            <?= e(retrait_dossier($d) . $d['icone'] . ' ' . $d['nom']) ?> (<?= (int) $d['nb_cours'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <div class="champ">
    <label for="f-tri">Trier par</label>
    <select id="f-tri" name="tri">
      <option value="recent"<?= $tri === 'recent' ? ' selected' : '' ?>>Modifié récemment</option>
      <option value="titre"<?= $tri === 'titre' ? ' selected' : '' ?>>Titre (A→Z)</option>
      <option value="ancien"<?= $tri === 'ancien' ? ' selected' : '' ?>>Plus ancien d'abord</option>
    </select>
  </div>

  <label class="case" style="padding-bottom:.55rem">
    <input type="checkbox" name="favoris" value="1"<?= $favoris ? ' checked' : '' ?>
           onchange="this.form.submit()"> Favoris
  </label>

  <button class="bouton bouton--secondaire" type="submit">Filtrer</button>
  <?php if ($recherche !== '' || $matiereId !== null || $tagId !== null || $dossierId !== null || $favoris): ?>
    <a class="bouton bouton--discret" href="<?= url('cours') ?>">Réinitialiser</a>
  <?php endif; ?>
</form>

<?php if ($cours === []): ?>
  <div class="vide">
    <span class="vide__icone">📄</span>
    <?php if ($recherche !== '' || $matiereId !== null || $tagId !== null || $dossierId !== null || $favoris): ?>
      <p>Aucun cours ne correspond à ces critères.</p>
      <a class="bouton bouton--secondaire" href="<?= url('cours') ?>">Voir tous les cours</a>
    <?php else: ?>
      <p>Vous n'avez pas encore de cours enregistré.</p>
      <a class="bouton" href="<?= url('cours/nouveau') ?>">Créer mon premier cours</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="grille grille--3">
    <?php foreach ($cours as $c): ?>
      <a class="carte cours-carte" href="<?= url('cours/' . $c['id']) ?>">
        <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap">
          <?php if ($c['matiere_nom'] !== null): ?>
            <span class="pastille" style="background:<?= e($c['matiere_couleur']) ?>;color:<?= e(couleur_texte($c['matiere_couleur'])) ?>">
              <?= e($c['matiere_nom']) ?>
            </span>
          <?php else: ?>
            <span class="pastille">Sans matière</span>
          <?php endif; ?>
          <?php if ($c['dossier_nom'] !== null): ?>
            <span class="pastille" title="Dossier"><?= e($c['dossier_icone'] . ' ' . $c['dossier_nom']) ?></span>
          <?php endif; ?>
          <?php if ((int) $c['favori'] === 1): ?><span title="Favori">⭐</span><?php endif; ?>
        </div>

        <div class="cours-carte__titre"><?= e($c['titre']) ?></div>
        <p class="cours-carte__extrait"><?= e(extrait($c['contenu'])) ?></p>

        <div class="cours-carte__bas">
          <span>Modifié le <?= e(date_fr($c['updated_at'], false)) ?></span>
          <?php if ((int) $c['nb_fichiers'] > 0): ?>
            <span>· 📎 <?= (int) $c['nb_fichiers'] ?></span>
          <?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
