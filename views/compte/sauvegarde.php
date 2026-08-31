<?php
/** @var array $resume, $libelles */
$csrf = Session::jetonCsrf();
$aDesDonnees = $resume['lignes'] > 0;
?>

<div class="entete-page">
  <div>
    <p class="discret" style="margin-bottom:.35rem">
      <a href="<?= url('compte') ?>">← Mon compte</a>
    </p>
    <h1>Sauvegarde</h1>
    <p>Une copie de tout ce que contient votre compte, dans un seul fichier.</p>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <section class="carte" style="border-color:var(--accent)">
      <h2>💾 Télécharger ma sauvegarde</h2>
      <p class="discret" style="margin:.3rem 0 1rem">
        Un fichier <code>.zip</code> contenant vos données <strong>et</strong> vos pièces
        jointes. Rangez-le ailleurs que sur cette machine : un espace de stockage
        en ligne, une clé USB, un disque externe. Une copie posée à côté de
        l'original ne protège de rien.
      </p>

      <?php if (!$aDesDonnees): ?>
        <p class="discret">Votre compte est vide : il n'y a rien à sauvegarder pour l'instant.</p>
      <?php else: ?>
        <a class="bouton bouton--bloc" href="<?= url('compte/sauvegarde/export') ?>">
          Télécharger la sauvegarde
        </a>
        <p class="champ__aide" style="margin-top:.6rem">
          <?= (int) $resume['lignes'] ?> lignes de données,
          <?= (int) $resume['fichiers'] ?> pièce<?= (int) $resume['fichiers'] > 1 ? 's' : '' ?> jointe<?= (int) $resume['fichiers'] > 1 ? 's' : '' ?>
          <?php if ((int) $resume['octets'] > 0): ?>
            (<?= e(taille_lisible((int) $resume['octets'])) ?>)
          <?php endif; ?>.
          La préparation peut prendre quelques secondes.
        </p>
      <?php endif; ?>
    </section>

    <section class="carte">
      <h2>Restaurer une sauvegarde</h2>
      <p class="discret" style="margin:.3rem 0 .9rem">
        À n'utiliser que pour repartir d'une copie : après une réinstallation, un
        changement d'ordinateur, ou une fausse manœuvre.
      </p>

      <div class="flash flash--erreur" style="margin-bottom:1rem">
        <strong>La restauration remplace tout.</strong> Les données actuelles de
        votre compte sont effacées et remplacées par celles de l'archive. Si vous
        avez le moindre doute, téléchargez d'abord une sauvegarde de l'état actuel.
      </div>

      <form method="post" action="<?= url('compte/sauvegarde/restaurer') ?>" enctype="multipart/form-data"
            data-confirmation="Remplacer toutes les données de votre compte par le contenu de cette archive ?">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

        <div class="champ">
          <label for="archive">Fichier de sauvegarde</label>
          <input type="file" id="archive" name="archive" accept=".zip,application/zip" required>
          <span class="champ__aide">L'archive <code>.zip</code> téléchargée depuis cette page.</span>
        </div>

        <label class="case" style="margin-bottom:1rem">
          <input type="checkbox" name="confirmation" value="1" required>
          Je comprends que mes données actuelles seront remplacées.
        </label>

        <button class="bouton bouton--danger" type="submit">Restaurer</button>
      </form>
    </section>
  </div>

  <div class="pile">
    <?php if ($aDesDonnees): ?>
      <div class="carte">
        <h2>Ce qui est sauvegardé</h2>
        <table class="tableau">
          <tbody>
            <?php foreach ($resume['detail'] as $table => $n): ?>
              <?php if ($n === 0) { continue; } ?>
              <tr>
                <th scope="row" style="font-weight:500"><?= e($libelles[$table] ?? $table) ?></th>
                <td class="nombre"><?= (int) $n ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="champ__aide" style="margin-top:.6rem">
          Vos identifiants de connexion ne sont pas dans l'archive : le mot de
          passe reste attaché au compte, pas à la sauvegarde.
        </p>
      </div>
    <?php endif; ?>

    <div class="carte">
      <h2>Pourquoi c'est nécessaire</h2>
      <p class="discret" style="margin-bottom:.6rem">
        Le code de l'application est sur GitHub, mais <strong>vos données n'y sont
        pas</strong> — et c'est voulu. En cas de panne, vous récupéreriez une
        application parfaitement fonctionnelle et parfaitement vide.
      </p>
      <p class="discret" style="margin:0">
        Cette archive est la seule chose qui contienne vos cours, votre calendrier
        et vos comptes.
      </p>
    </div>

    <div class="carte">
      <h2>À quel rythme ?</h2>
      <p class="discret" style="margin:0">
        Une fois par mois suffit, ou après une grosse saisie. Le geste prend
        trente secondes, et vous n'y penserez qu'une fois — le jour où vous en
        aurez besoin.
      </p>
    </div>
  </div>
</div>
