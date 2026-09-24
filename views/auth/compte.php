<?php
/** @var array $stats */
$moi = Auth::utilisateur();
?>

<div class="entete-page">
  <div>
    <h1><?= e(t('compte.titre')) ?></h1>
    <p>
      <?php if ((string) ($moi['pseudo'] ?? '') !== ''): ?><strong><?= e((string) $moi['pseudo']) ?></strong> · <?php endif; ?>
      <?= e((string) $moi['email']) ?> — <?= e(t('compte.inscrit_le', ['date' => date_fr((string) $moi['created_at'], false)])) ?>
    </p>
  </div>
</div>

<div class="grille grille--4" style="margin-bottom:1.5rem">
  <div class="carte stat"><div class="stat__valeur"><?= (int) $stats['cours'] ?></div><div class="stat__libelle"><?= e(t('compte.stat_cours')) ?></div></div>
  <div class="carte stat"><div class="stat__valeur"><?= (int) $stats['matieres'] ?></div><div class="stat__libelle"><?= e(t('compte.stat_matieres')) ?></div></div>
  <div class="carte stat"><div class="stat__valeur"><?= (int) $stats['evenements'] ?></div><div class="stat__libelle"><?= e(t('compte.stat_evenements')) ?></div></div>
  <div class="carte stat">
    <div class="stat__valeur"><?= (int) $stats['fichiers'] ?></div>
    <div class="stat__libelle"><?= e(t('compte.stat_fichiers')) ?> · <?= e(taille_lisible((int) $stats['octets'])) ?></div>
  </div>
</div>

<?php
/*
 * Le pseudo : choisi à l'inscription, il se change ici. Un compte d'avant
 * n'en a pas encore — la carte l'invite à en choisir un.
 *
 * Le pseudo se lit d'abord, sans champ : on ne le change qu'en passant par
 * « Modifier », et « Annuler » le remet tel qu'il était. Après un refus, le
 * champ reste ouvert avec ce qu'on avait tapé.
 */
$pseudoActuel = (string) ($moi['pseudo'] ?? '');
$pseudoSaisi = Session::reprendre('pseudo_saisi');
$enEdition = is_string($pseudoSaisi);
?>
<?php
/*
 * L'apparence : claire, sombre, ou celle de l'appareil.
 *
 * Comme le pseudo et le fuseau : l'apparence choisie se lit, et les trois
 * vignettes ne se déplient qu'en passant par « Modifier ». Le choix s'applique
 * à l'instant où on le fait — le script pose le thème sur la page avant même
 * l'enregistrement —, puis le formulaire part tout seul.
 */
$themeActuel = Auth::theme($moi);
?>
<section class="carte" style="margin-bottom:1rem" id="apparence" data-reglage>
  <h2 style="margin-top:0"><?= e(t('apparence.titre')) ?></h2>

  <div class="reglage-lecture" data-reglage-lecture>
    <p class="reglage-lecture__valeur">
      <?= Auth::THEMES[$themeActuel]['icone'] ?> <?= e(t('apparence.' . $themeActuel)) ?>
    </p>
    <button class="bouton bouton--secondaire" type="button" data-reglage-modifier><?= e(t('commun.modifier')) ?></button>
  </div>

  <form method="post" action="<?= url('compte/theme') ?>" data-auto-envoi data-choix-theme data-reglage-edition hidden>
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <div class="themes-choix">
      <?php foreach (Auth::THEMES as $cle => $theme): ?>
        <label class="themes-choix__option">
          <input type="radio" name="theme" value="<?= e($cle) ?>"<?= $themeActuel === $cle ? ' checked' : '' ?>>
          <span class="themes-choix__apercu themes-choix__apercu--<?= e($cle) ?>" aria-hidden="true">
            <span class="themes-choix__barre"></span>
            <span class="themes-choix__ligne"></span>
            <span class="themes-choix__ligne themes-choix__ligne--courte"></span>
          </span>
          <span class="themes-choix__nom"><?= $theme['icone'] ?> <?= e(t('apparence.' . $cle)) ?></span>
          <span class="discret themes-choix__aide"><?= e(t('apparence.' . $cle . '_aide')) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="actions" style="margin:.8rem 0 0">
      <noscript><button class="bouton bouton--petit" type="submit">Enregistrer</button></noscript>
      <button class="bouton bouton--discret bouton--petit" type="button" data-reglage-annuler><?= e(t('commun.fermer')) ?></button>
    </p>
  </form>
</section>

<?php
/*
 * La langue de l'application.
 *
 * Comme l'apparence : elle se lit, et les choix ne se déplient qu'en passant
 * par « Modifier ». Ce qu'on écrit soi-même n'est jamais traduit.
 */
$langueActuelle = Langue::courante();
?>
<section class="carte" style="margin-bottom:1rem" id="langue" data-reglage>
  <h2 style="margin-top:0"><?= e(t('langue.titre')) ?></h2>

  <div class="reglage-lecture" data-reglage-lecture>
    <p class="reglage-lecture__valeur">
      <?= Langue::LANGUES[$langueActuelle]['drapeau'] ?> <?= e(Langue::LANGUES[$langueActuelle]['nom']) ?>
    </p>
    <button class="bouton bouton--secondaire" type="button" data-reglage-modifier><?= e(t('commun.modifier')) ?></button>
  </div>

  <form method="post" action="<?= url('compte/langue') ?>" data-auto-envoi data-reglage-edition hidden>
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <div class="themes-choix">
      <?php foreach (Langue::LANGUES as $code => $langue): ?>
        <label class="themes-choix__option">
          <input type="radio" name="langue" value="<?= e($code) ?>"<?= $langueActuelle === $code ? ' checked' : '' ?>>
          <span class="themes-choix__nom"><?= $langue['drapeau'] ?> <?= e($langue['nom']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="champ__aide"><?= e(t('langue.aide')) ?> <?= e(t('langue.partielle')) ?></p>
    <p class="actions" style="margin:.4rem 0 0">
      <noscript><button class="bouton bouton--petit" type="submit"><?= e(t('commun.enregistrer')) ?></button></noscript>
      <button class="bouton bouton--discret bouton--petit" type="button" data-reglage-annuler><?= e(t('commun.fermer')) ?></button>
    </p>
  </form>
</section>

<section class="carte" style="margin-bottom:1rem" id="pseudo-carte" data-reglage>
  <h2 style="margin-top:0">🏷️ Mon pseudo</h2>

  <div class="reglage-lecture" data-reglage-lecture<?= $enEdition ? ' hidden' : '' ?>>
    <?php if ($pseudoActuel === ''): ?>
      <p class="discret" style="margin:0">Vous n’avez pas encore de pseudo.</p>
    <?php else: ?>
      <p class="reglage-lecture__valeur"><?= e($pseudoActuel) ?></p>
    <?php endif; ?>
    <button class="bouton bouton--secondaire" type="button" data-reglage-modifier>
      <?= $pseudoActuel === '' ? 'Créer mon pseudo' : '✎ Modifier' ?>
    </button>
  </div>

  <form method="post" action="<?= url('compte/pseudo') ?>" data-reglage-edition<?= $enEdition ? '' : ' hidden' ?>>
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <label class="legende" for="pseudo"><?= $pseudoActuel === '' ? 'Votre pseudo' : 'Nouveau pseudo' ?></label>
    <div class="fuseau-choix">
      <input type="text" id="pseudo" name="pseudo" required autocomplete="nickname"
             minlength="<?= Auth::PSEUDO_MIN ?>" maxlength="<?= Auth::PSEUDO_MAX ?>"
             placeholder="Votre pseudo" data-valeur-actuelle="<?= e($pseudoActuel) ?>"
             value="<?= e($enEdition ? $pseudoSaisi : $pseudoActuel) ?>">
      <button class="bouton" type="submit">Enregistrer</button>
      <button class="bouton bouton--discret" type="button" data-reglage-annuler>Annuler</button>
    </div>
    <p class="champ__aide">
      De <?= Auth::PSEUDO_MIN ?> à <?= Auth::PSEUDO_MAX ?> caractères : lettres, chiffres, point, tiret et tiret bas, sans espace.
      Unique : deux comptes ne peuvent pas porter le même.
    </p>
  </form>

  <p class="champ__aide" style="margin-bottom:0">
    Le nom sous lequel l’application vous appelle. Il sert aussi à se connecter, à la place de l’adresse e-mail.
  </p>
</section>

<?php // La photo de profil : essayée dans l'aperçu, envoyée avec « Enregistrer ». ?>
<section class="carte" style="margin-bottom:1rem" id="photo-profil" data-photo-carte>
  <h2 style="margin-top:0">📷 Photo de profil</h2>
  <div class="photo-groupe">
    <span class="photo-groupe__apercu" data-photo-apercu>
      <?= Amis::avatar((int) $moi['id'], Auth::nomAffiche($moi), 'avatar--apercu') ?>
    </span>
    <div class="fond-reglage__infos">
      <form method="post" action="<?= url('compte/photo') ?>" enctype="multipart/form-data" class="fond-reglage__choix" data-photo-formulaire>
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
        <input type="file" name="photo" id="photo-profil-fichier" class="sr-only" required
               accept="image/jpeg,image/png,image/gif,image/webp" data-photo-fichier>
        <label class="bouton bouton--secondaire" for="photo-profil-fichier">📷 <?= ($moi['photo_nom'] ?? null) === null ? 'Choisir une photo' : 'Changer de photo' ?></label>
        <span class="fond-reglage__nouveau" data-photo-nouveau hidden>
          <button class="bouton" type="submit">Enregistrer</button>
          <button class="bouton bouton--discret" type="button" data-photo-annuler>Annuler</button>
        </span>
      </form>
      <?php if (($moi['photo_nom'] ?? null) !== null): ?>
        <form method="post" action="<?= url('compte/photo/retirer') ?>" style="margin-top:.5rem">
          <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
          <button class="bouton bouton--discret" type="submit">Retirer la photo</button>
        </form>
      <?php endif; ?>
      <p class="champ__aide" style="margin-bottom:0">
        Elle remplace votre initiale : en haut de l’application, et là où les autres comptes voient votre pseudo.
        JPEG, PNG, GIF ou WebP, <?= intdiv(Amis::IMAGE_MAX_OCTETS, 1024 * 1024) ?> Mo au plus.
      </p>
    </div>
  </div>
</section>

<?php
/*
 * Le fuseau horaire.
 *
 * Il ne sert pas qu'à l'affichage : c'est lui qui décide de l'heure à laquelle
 * un cours part dans Outlook et de celle à laquelle un rendez-vous en revient.
 * Mal réglé, il décale tout d'un bloc sans rien signaler.
 */
?>
<section class="carte" style="margin-bottom:1rem" id="fuseau-carte" data-reglage>
  <h2 style="margin-top:0">🕑 Fuseau horaire</h2>

  <?php // Comme le pseudo : il se lit, et ne se change qu'en passant par « Modifier ». ?>
  <div class="reglage-lecture" data-reglage-lecture>
    <p class="reglage-lecture__valeur"><?= e(str_replace('_', ' ', $fuseau)) ?></p>
    <button class="bouton bouton--secondaire" type="button" data-reglage-modifier>✎ Modifier</button>
  </div>

  <form method="post" action="<?= url('compte/fuseau') ?>" data-reglage-edition hidden>
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <label class="legende" for="fuseau">Nouveau fuseau horaire</label>
    <div class="fuseau-choix">
      <select id="fuseau" name="fuseau">
        <?php foreach ($fuseaux as $region => $liste): ?>
          <optgroup label="<?= e($region) ?>">
            <?php foreach ($liste as $f): ?>
              <option value="<?= e($f) ?>"<?= $f === $fuseau ? ' selected' : '' ?>>
                <?= e(str_replace('_', ' ', $f)) ?>
              </option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
      <button class="bouton" type="submit">Enregistrer</button>
      <button class="bouton bouton--discret" type="button" data-reglage-annuler>Annuler</button>
    </div>
    <p class="champ__aide">
      Changer de fuseau ne déplace pas ce qui est déjà noté : un cours à 8 h
      reste à 8 h, simplement lu dans la nouvelle heure. C'est ce qu'on veut en
      déménageant — moins en corrigeant un mauvais réglage, où il faudra
      reprendre les horaires à la main.
    </p>
  </form>

  <p class="champ__aide" style="margin-bottom:0">
    Il est <strong><?= e(date('H:i')) ?></strong> pour l'application.
    Vos horaires d'évènements, ici comme dans Outlook, sont lus et écrits dans
    ce fuseau.
  </p>
</section>

<?php
/*
 * La transcription des messages vocaux, comme le pseudo et le fuseau : elle
 * se lit, et ne se change qu'en passant par « Modifier ».
 */
$transcription = (int) ($moi['transcription_vocale'] ?? 1) === 1;
?>
<section class="carte" style="margin-bottom:1rem" id="transcription-carte" data-reglage>
  <h2 style="margin-top:0"><?= str_replace(['width="14"', 'height="14"'], ['width="22"', 'height="22"'], Amis::micro()) ?> Transcription des messages vocaux</h2>

  <div class="reglage-lecture" data-reglage-lecture>
    <p class="reglage-lecture__valeur"><?= $transcription ? '✅ Activée' : '⛔ Coupée' ?></p>
    <button class="bouton bouton--secondaire" type="button" data-reglage-modifier>✎ Modifier</button>
  </div>

  <form method="post" action="<?= url('compte/transcription') ?>" data-reglage-edition hidden>
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <fieldset class="reglage-choix">
      <legend class="legende">Transcrire mes messages vocaux</legend>
      <label><input type="radio" name="transcription" value="1"<?= $transcription ? ' checked' : '' ?>> Activée</label>
      <label><input type="radio" name="transcription" value="0"<?= $transcription ? '' : ' checked' ?>> Coupée</label>
    </fieldset>
    <div class="actions">
      <button class="bouton" type="submit">Enregistrer</button>
      <button class="bouton bouton--discret" type="button" data-reglage-annuler>Annuler</button>
    </div>
  </form>

  <p class="champ__aide" style="margin-bottom:0">
    Activée, votre navigateur écrit ce que vous dites pendant l’enregistrement, et vos amis lisent le texte sous le vocal.
    Chrome et Edge envoient pour cela le son à Google ou Microsoft ; Firefox ne sait pas transcrire.
    Coupée, vos vocaux partent sans texte. Les vocaux déjà envoyés ne changent pas.
  </p>
</section>

<?php
/*
 * Les évènements que mes amis me partagent : pour chacun, s'ils paraissent
 * d'office dans mon calendrier, ou restent dans « Partagés ».
 */
$calendrierAmis = $calendrierAmis ?? [];
?>
<?php $partagesDansDiscussion = $partagesDansDiscussion ?? true; ?>
<section class="carte" style="margin-bottom:1rem" id="reception-partages" data-reglage>
  <h2 style="margin-top:0">🔗 Ce qu’on me partage</h2>

  <div class="reglage-lecture" data-reglage-lecture>
    <p class="reglage-lecture__valeur">
      <?= $partagesDansDiscussion ? '💬 Dans la discussion et dans « Partagés »' : '📥 Seulement dans « Partagés », avec une notification' ?>
    </p>
    <button class="bouton bouton--secondaire" type="button" data-reglage-modifier>✎ Modifier</button>
  </div>

  <form method="post" action="<?= url('partages/reception') ?>" data-reglage-edition hidden>
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <input type="hidden" name="retour" value="<?= e(url('compte') . '#reception-partages') ?>">
    <fieldset class="reglage-choix">
      <legend class="legende">Quand un ami me partage un document</legend>
      <label><input type="radio" name="dans_discussion" value="1"<?= $partagesDansDiscussion ? ' checked' : '' ?>>
        Il arrive en carte dans notre discussion, et dans « Partagés »</label>
      <label><input type="radio" name="dans_discussion" value="0"<?= $partagesDansDiscussion ? '' : ' checked' ?>>
        Il n’arrive que dans « Partagés », avec une notification</label>
    </fieldset>
    <div class="actions">
      <button class="bouton" type="submit">Enregistrer</button>
      <button class="bouton bouton--discret" type="button" data-reglage-annuler>Annuler</button>
    </div>
  </form>

  <p class="champ__aide" style="margin-bottom:0">
    Dans les deux cas, l’onglet « Partagés » compte ce que vous n’avez pas encore vu, et le mot qui accompagne un partage s’y lit.
    Un partage fait à un groupe arrive toujours dans le groupe : la discussion est commune à tous ses membres.
  </p>
</section>

<?php
/*
 * Mon calendrier entier, ami par ami : il voit tous mes évènements, en
 * lecture — ceux d'aujourd'hui comme ceux que j'ajouterai.
 */
$calendriersAmis = $calendriersAmis ?? [];
?>
<section class="carte" style="margin-bottom:1rem" id="mon-calendrier">
  <h2 style="margin-top:0">📅 Partager mon calendrier</h2>
  <?php if ($calendriersAmis === []): ?>
    <p class="discret" style="margin:0">Vous n’avez pas encore d’amis à qui ouvrir votre calendrier.</p>
  <?php else: ?>
    <p class="champ__aide" style="margin-top:0">
      Ouvert à un ami, votre calendrier paraît dans le sien, en lecture : tous vos évènements, ceux d’aujourd’hui comme ceux que vous ajouterez.
      Seulement « Mes évènements » — ni vos agendas Outlook et Google, ni ce que d’autres vous ont partagé.
    </p>
    <ul class="reglages-amis">
      <?php foreach ($calendriersAmis as $ami): ?>
        <li>
          <span class="reglages-amis__qui">
            <?= Amis::avatar($ami['id'], $ami['pseudo'], 'avatar--mini') ?>
            <strong><?= e($ami['pseudo']) ?></strong>
            <?php if ($ami['meMontreLeSien']): ?><span class="discret">· vous montre le sien</span><?php endif; ?>
          </span>
          <form method="post" action="<?= url('partages/mon-calendrier/' . $ami['id']) ?>" class="en-ligne"
                <?= $ami['voitLeMien'] ? 'data-confirmation="' . e($ami['pseudo']) . ' ne verra plus votre calendrier. Continuer ?"' : '' ?>>
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <input type="hidden" name="retour" value="<?= e(url('compte') . '#mon-calendrier') ?>">
            <input type="hidden" name="partager" value="<?= $ami['voitLeMien'] ? '0' : '1' ?>">
            <button class="interrupteur" type="submit" role="switch" aria-checked="<?= $ami['voitLeMien'] ? 'true' : 'false' ?>">
              <span class="interrupteur__texte"><?= $ami['voitLeMien'] ? 'Voit mon calendrier' : 'Ne le voit pas' ?></span>
            </button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="carte" style="margin-bottom:1rem" id="calendrier-amis">
  <h2 style="margin-top:0">📅 Évènements partagés par mes amis</h2>
  <?php if ($calendrierAmis === []): ?>
    <p class="discret" style="margin:0">Vous n’avez pas encore d’amis. Quand vous en aurez, vous choisirez ici qui s’affiche dans votre calendrier.</p>
  <?php else: ?>
    <p class="champ__aide" style="margin-top:0">
      Choisissez, ami par ami, si les évènements qu’il vous partage — un par un, ou tout son calendrier — paraissent d’office dans votre calendrier et sur votre accueil.
      Ils y restent à jour, et disparaissent si le partage est retiré. Sinon, ils vous attendent dans « Partagés ».
    </p>
    <ul class="reglages-amis">
      <?php foreach ($calendrierAmis as $ami): ?>
        <li>
          <span class="reglages-amis__qui">
            <?= Amis::avatar($ami['id'], $ami['pseudo'], 'avatar--mini') ?>
            <strong><?= e($ami['pseudo']) ?></strong>
            <span class="discret">· <?= $ami['partages'] === 0 ? 'aucun évènement partagé' : $ami['partages'] . ' évènement' . ($ami['partages'] > 1 ? 's' : '') . ' partagé' . ($ami['partages'] > 1 ? 's' : '') ?></span>
          </span>
          <form method="post" action="<?= url('partages/calendrier/' . $ami['id']) ?>" class="en-ligne">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <input type="hidden" name="retour" value="<?= e(url('compte') . '#calendrier-amis') ?>">
            <input type="hidden" name="afficher" value="<?= $ami['affiche'] ? '0' : '1' ?>">
            <button class="interrupteur" type="submit" role="switch" aria-checked="<?= $ami['affiche'] ? 'true' : 'false' ?>"
                    title="<?= $ami['affiche'] ? 'Ne plus afficher d’office' : 'Afficher d’office' ?>">
              <span class="interrupteur__texte"><?= $ami['affiche'] ? 'Dans mon calendrier' : 'Seulement dans « Partagés »' ?></span>
            </button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<div class="colonnes">
  <?php
  /*
   * Le mot de passe, comme le pseudo et le fuseau : rien à remplir tant
   * qu'on n'a pas cliqué sur « Modifier ». Après un refus, les champs se
   * rouvrent d'eux-mêmes — vides, un mot de passe ne se garde pas.
   */
  $motDePasseOuvert = Session::reprendre('mot_de_passe_ouvert') === true;
  ?>
  <div class="carte" id="mot-de-passe-carte" data-reglage>
    <h2>🔒 Mot de passe</h2>

    <div class="reglage-lecture" data-reglage-lecture<?= $motDePasseOuvert ? ' hidden' : '' ?>>
      <p class="reglage-lecture__valeur" aria-label="Mot de passe masqué">••••••••</p>
      <button class="bouton bouton--secondaire" type="button" data-reglage-modifier>✎ Modifier</button>
    </div>

    <form method="post" action="<?= url('compte/mot-de-passe') ?>" data-reglage-edition<?= $motDePasseOuvert ? '' : ' hidden' ?>>
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

      <div class="champ">
        <label for="mot_de_passe_actuel">Mot de passe actuel</label>
        <input type="password" id="mot_de_passe_actuel" name="mot_de_passe_actuel" required
               autocomplete="current-password">
      </div>

      <div class="ligne-champs">
        <div class="champ">
          <label for="nouveau_mot_de_passe">Nouveau mot de passe</label>
          <input type="password" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" required minlength="8"
                 autocomplete="new-password">
        </div>
        <div class="champ">
          <label for="nouveau_mot_de_passe_confirmation">Confirmation</label>
          <input type="password" id="nouveau_mot_de_passe_confirmation" name="nouveau_mot_de_passe_confirmation"
                 required minlength="8" autocomplete="new-password">
        </div>
      </div>

      <div class="actions">
        <button class="bouton" type="submit">Enregistrer</button>
        <button class="bouton bouton--discret" type="button" data-reglage-annuler>Annuler</button>
      </div>
    </form>

    <p class="champ__aide" style="margin-bottom:0">Huit caractères au moins. Le mot de passe actuel est demandé pour le changer.</p>
  </div>

  <div class="pile">
    <div class="carte">
      <h2>🔔 Notifications</h2>
      <p class="discret" style="margin-bottom:.8rem">
        Des rappels avant vos évènements et le matin de vos échéances, sur cet
        ordinateur ou votre téléphone — même application fermée.
      </p>
      <a class="bouton bouton--secondaire bouton--bloc" href="<?= url('notifications') ?>" data-fenetre>Régler les notifications</a>
    </div>

    <?php
    /*
     * Le hors-ligne : l'application garde d'elle-même les pages qu'on ouvre,
     * mais on peut lui demander de prendre d'avance celles dont on sait
     * qu'on en aura besoin — avant de partir en atelier, par exemple.
     */
    $aGarder = [url(''), url('calendrier'), url('cours'), url('taches'), url('tableau'),
        url('revision'), url('cartes'), url('alternance'), url('alternance/entreprise'),
        url('alternance/rythme'), url('alternance/journal'), url('alternance/documents'),
        url('budget'), url('amis'), url('compte'), url('hors-ligne')];
    ?>
    <div class="carte" data-hors-ligne data-pages="<?= e((string) json_encode($aGarder, JSON_UNESCAPED_SLASHES)) ?>">
      <h2>📴 Hors connexion</h2>
      <p class="discret" style="margin-bottom:.8rem">
        Les pages que vous ouvrez sont gardées sur cet appareil : sans réseau,
        vous les relisez, et ce que vous écrivez repart tout seul au retour de
        la connexion.
      </p>
      <p class="champ__aide" data-hors-ligne-etat aria-live="polite" style="margin-top:0">Vérification…</p>
      <p class="actions" style="margin-bottom:0">
        <button class="bouton bouton--secondaire bouton--petit" type="button" data-hors-ligne-garder hidden>
          Préparer mes pages
        </button>
        <button class="bouton bouton--discret bouton--petit" type="button" data-hors-ligne-oublier hidden>
          Vider ce qui est gardé
        </button>
      </p>
    </div>

    <div class="carte" style="border-color:var(--accent)">
      <h2>💾 Sauvegarde</h2>
      <p class="discret" style="margin-bottom:.8rem">
        Vos données n'existent qu'à un seul endroit. Téléchargez-en une copie et
        rangez-la ailleurs : c'est la seule chose qui vous protège d'une panne.
      </p>
      <a class="bouton bouton--bloc" href="<?= url('compte/sauvegarde') ?>">
        Sauvegarder mes données
      </a>
    </div>

    <div class="carte">
      <h2>Où sont mes données ?</h2>
      <p class="discret">
        Les textes de cours, votre calendrier et vos comptes sont dans la base
        MySQL <code>mon_appli_cours</code> ; les fichiers joints dans le dossier
        <code>storage/uploads</code> de l'application.
      </p>
      <p class="discret" style="margin:0">
        Le code est sur GitHub, mais pas vos données : elles en sont exclues
        volontairement.
      </p>
    </div>
  </div>
</div>
