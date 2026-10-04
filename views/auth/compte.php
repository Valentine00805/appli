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
 * vignettes ne se déplient qu'en passant par « Modifier ». Choisir une vignette
 * montre le résultat à l'instant — le script pose le thème sur la page —, mais
 * rien n'est enregistré avant « Valider » ; « Annuler » rend à la page le thème
 * du compte.
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

  <form method="post" action="<?= url('compte/theme') ?>" data-choix-theme data-reglage-edition hidden>
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
    <p class="champ__aide"><?= e(t('apparence.apercu_aide')) ?></p>
    <p class="actions" style="margin:.4rem 0 0">
      <button class="bouton bouton--petit" type="submit"><?= e(t('apparence.valider')) ?></button>
      <button class="bouton bouton--discret bouton--petit" type="button" data-reglage-annuler><?= e(t('commun.annuler')) ?></button>
    </p>
  </form>
</section>

<?php
/*
 * La langue de l'application.
 *
 * Comme l'apparence : elle se lit, et les choix ne se déplient qu'en passant
 * par « Modifier ». Choisir une langue rouvre cette page dans cette langue
 * (« ?apercu_langue= ») : c'est un aperçu, le compte ne change qu'avec « Valider ».
 * Ce qu'on écrit soi-même n'est jamais traduit.
 */
$langueEnregistree = (string) ($moi['langue'] ?? '');
if (!isset(Langue::LANGUES[$langueEnregistree])) { $langueEnregistree = Langue::PAR_DEFAUT; }
$apercuLangue = isset($apercuLangue) && isset(Langue::LANGUES[$apercuLangue]) ? $apercuLangue : null;
$langueChoisie = $apercuLangue ?? $langueEnregistree;
?>
<section class="carte" style="margin-bottom:1rem" id="langue" data-reglage>
  <h2 style="margin-top:0"><?= e(t('langue.titre')) ?></h2>

  <div class="reglage-lecture" data-reglage-lecture<?= $apercuLangue !== null ? ' hidden' : '' ?>>
    <p class="reglage-lecture__valeur">
      <?= Langue::LANGUES[$langueEnregistree]['drapeau'] ?> <?= e(Langue::LANGUES[$langueEnregistree]['nom']) ?>
    </p>
    <button class="bouton bouton--secondaire" type="button" data-reglage-modifier><?= e(t('commun.modifier')) ?></button>
  </div>

  <form method="post" action="<?= url('compte/langue') ?>" data-choix-langue data-apercu-url="<?= url('compte') ?>" data-reglage-edition<?= $apercuLangue === null ? ' hidden' : '' ?>>
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <div class="themes-choix">
      <?php foreach (Langue::LANGUES as $code => $langue): ?>
        <label class="themes-choix__option">
          <input type="radio" name="langue" value="<?= e($code) ?>"<?= $langueChoisie === $code ? ' checked' : '' ?>>
          <span class="themes-choix__nom"><?= $langue['drapeau'] ?> <?= e($langue['nom']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="champ__aide"><?= e(t('langue.aide')) ?></p>
    <p class="champ__aide"><?= e(t('langue.apercu_aide')) ?></p>
    <p class="actions" style="margin:.4rem 0 0">
      <button class="bouton bouton--petit" type="submit"><?= e(t('apparence.valider')) ?></button>
      <?php if ($apercuLangue !== null): ?>
        <a class="bouton bouton--discret bouton--petit" href="<?= url('compte') ?>#langue"><?= e(t('commun.annuler')) ?></a>
      <?php else: ?>
        <button class="bouton bouton--discret bouton--petit" type="button" data-reglage-annuler><?= e(t('commun.annuler')) ?></button>
      <?php endif; ?>
    </p>
  </form>
</section>

<section class="carte" style="margin-bottom:1rem" id="pseudo-carte" data-reglage>
  <h2 style="margin-top:0"><?= e(t('cpt.pseudo')) ?></h2>

  <div class="reglage-lecture" data-reglage-lecture<?= $enEdition ? ' hidden' : '' ?>>
    <?php if ($pseudoActuel === ''): ?>
      <p class="discret" style="margin:0"><?= e(t('cpt.pseudo_aucun')) ?></p>
    <?php else: ?>
      <p class="reglage-lecture__valeur"><?= e($pseudoActuel) ?></p>
    <?php endif; ?>
    <button class="bouton bouton--secondaire" type="button" data-reglage-modifier>
      <?= e($pseudoActuel === '' ? t('cpt.pseudo_creer') : t('commun.modifier')) ?>
    </button>
  </div>

  <form method="post" action="<?= url('compte/pseudo') ?>" data-reglage-edition<?= $enEdition ? '' : ' hidden' ?>>
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <label class="legende" for="pseudo"><?= e($pseudoActuel === '' ? t('cpt.pseudo_votre') : t('cpt.pseudo_nouveau')) ?></label>
    <div class="fuseau-choix">
      <input type="text" id="pseudo" name="pseudo" required autocomplete="nickname"
             minlength="<?= Auth::PSEUDO_MIN ?>" maxlength="<?= Auth::PSEUDO_MAX ?>"
             placeholder="<?= e(t('cpt.pseudo_votre')) ?>" data-valeur-actuelle="<?= e($pseudoActuel) ?>"
             value="<?= e($enEdition ? $pseudoSaisi : $pseudoActuel) ?>">
      <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
      <button class="bouton bouton--discret" type="button" data-reglage-annuler><?= e(t('commun.annuler')) ?></button>
    </div>
    <p class="champ__aide">
      <?= e(t('cpt.pseudo_regles', ['min' => Auth::PSEUDO_MIN, 'max' => Auth::PSEUDO_MAX])) ?>
    </p>
  </form>

  <p class="champ__aide" style="margin-bottom:0">
    <?= e(t('cpt.pseudo_aide')) ?>
  </p>
</section>

<?php // La photo de profil : essayée dans l'aperçu, envoyée avec « Enregistrer ». ?>
<section class="carte" style="margin-bottom:1rem" id="photo-profil" data-photo-carte>
  <h2 style="margin-top:0"><?= e(t('cpt.photo')) ?></h2>
  <div class="photo-groupe">
    <span class="photo-groupe__apercu" data-photo-apercu>
      <?= Amis::avatar((int) $moi['id'], Auth::nomAffiche($moi), 'avatar--apercu') ?>
    </span>
    <div class="fond-reglage__infos">
      <form method="post" action="<?= url('compte/photo') ?>" enctype="multipart/form-data" class="fond-reglage__choix" data-photo-formulaire>
        <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
        <input type="file" name="photo" id="photo-profil-fichier" class="sr-only" required
               accept="image/jpeg,image/png,image/gif,image/webp" data-photo-fichier>
        <label class="bouton bouton--secondaire" for="photo-profil-fichier">📷 <?= e(($moi['photo_nom'] ?? null) === null ? t('cpt.photo_choisir') : t('cpt.photo_changer')) ?></label>
        <span class="fond-reglage__nouveau" data-photo-nouveau hidden>
          <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
          <button class="bouton bouton--discret" type="button" data-photo-annuler><?= e(t('commun.annuler')) ?></button>
        </span>
      </form>
      <?php if (($moi['photo_nom'] ?? null) !== null): ?>
        <form method="post" action="<?= url('compte/photo/retirer') ?>" style="margin-top:.5rem">
          <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
          <button class="bouton bouton--discret" type="submit"><?= e(t('cpt.photo_retirer')) ?></button>
        </form>
      <?php endif; ?>
      <p class="champ__aide" style="margin-bottom:0">
        <?= e(t('cpt.photo_aide', ['mo' => intdiv(Amis::IMAGE_MAX_OCTETS, 1024 * 1024)])) ?>
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
  <h2 style="margin-top:0"><?= e(t('cpt.fuseau')) ?></h2>

  <?php // Comme le pseudo : il se lit, et ne se change qu'en passant par « Modifier ». ?>
  <div class="reglage-lecture" data-reglage-lecture>
    <p class="reglage-lecture__valeur"><?= e(str_replace('_', ' ', $fuseau)) ?></p>
    <button class="bouton bouton--secondaire" type="button" data-reglage-modifier><?= e(t('commun.modifier')) ?></button>
  </div>

  <form method="post" action="<?= url('compte/fuseau') ?>" data-reglage-edition hidden>
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <label class="legende" for="fuseau"><?= e(t('cpt.fuseau_nouveau')) ?></label>
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
      <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
      <button class="bouton bouton--discret" type="button" data-reglage-annuler><?= e(t('commun.annuler')) ?></button>
    </div>
    <p class="champ__aide">
      <?= e(t('cpt.fuseau_aide')) ?>
    </p>
  </form>

  <p class="champ__aide" style="margin-bottom:0">
    <?= t('cpt.fuseau_heure', ['heure' => e(heure_courte(time()))]) ?>
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
  <h2 style="margin-top:0"><?= str_replace(['width="14"', 'height="14"'], ['width="22"', 'height="22"'], Amis::micro()) ?> <?= e(t('cpt.transcription')) ?></h2>

  <div class="reglage-lecture" data-reglage-lecture>
    <p class="reglage-lecture__valeur"><?= e($transcription ? t('cpt.activee_puce') : t('cpt.coupee_puce')) ?></p>
    <button class="bouton bouton--secondaire" type="button" data-reglage-modifier><?= e(t('commun.modifier')) ?></button>
  </div>

  <form method="post" action="<?= url('compte/transcription') ?>" data-reglage-edition hidden>
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <fieldset class="reglage-choix">
      <legend class="legende"><?= e(t('cpt.transcription_legende')) ?></legend>
      <label><input type="radio" name="transcription" value="1"<?= $transcription ? ' checked' : '' ?>> <?= e(t('cpt.activee')) ?></label>
      <label><input type="radio" name="transcription" value="0"<?= $transcription ? '' : ' checked' ?>> <?= e(t('cpt.coupee')) ?></label>
    </fieldset>
    <div class="actions">
      <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
      <button class="bouton bouton--discret" type="button" data-reglage-annuler><?= e(t('commun.annuler')) ?></button>
    </div>
  </form>

  <p class="champ__aide" style="margin-bottom:0">
    <?= e(t('cpt.transcription_aide')) ?>
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
  <h2 style="margin-top:0"><?= e(t('cpt.partages')) ?></h2>

  <div class="reglage-lecture" data-reglage-lecture>
    <p class="reglage-lecture__valeur">
      <?= e($partagesDansDiscussion ? t('cpt.partages_discussion') : t('cpt.partages_seulement')) ?>
    </p>
    <button class="bouton bouton--secondaire" type="button" data-reglage-modifier><?= e(t('commun.modifier')) ?></button>
  </div>

  <form method="post" action="<?= url('partages/reception') ?>" data-reglage-edition hidden>
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <input type="hidden" name="retour" value="<?= e(url('compte') . '#reception-partages') ?>">
    <fieldset class="reglage-choix">
      <legend class="legende"><?= e(t('cpt.partages_legende')) ?></legend>
      <label><input type="radio" name="dans_discussion" value="1"<?= $partagesDansDiscussion ? ' checked' : '' ?>>
        <?= e(t('cpt.partages_choix_1')) ?></label>
      <label><input type="radio" name="dans_discussion" value="0"<?= $partagesDansDiscussion ? '' : ' checked' ?>>
        <?= e(t('cpt.partages_choix_2')) ?></label>
    </fieldset>
    <div class="actions">
      <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
      <button class="bouton bouton--discret" type="button" data-reglage-annuler><?= e(t('commun.annuler')) ?></button>
    </div>
  </form>

  <p class="champ__aide" style="margin-bottom:0">
    <?= e(t('cpt.partages_aide')) ?>
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
  <h2 style="margin-top:0"><?= e(t('cpt.mon_calendrier')) ?></h2>
  <?php if ($calendriersAmis === []): ?>
    <p class="discret" style="margin:0"><?= e(t('cpt.mon_calendrier_vide')) ?></p>
  <?php else: ?>
    <p class="champ__aide" style="margin-top:0">
      <?= e(t('cpt.mon_calendrier_aide')) ?>
    </p>
    <ul class="reglages-amis">
      <?php foreach ($calendriersAmis as $ami): ?>
        <li>
          <span class="reglages-amis__qui">
            <?= Amis::avatar($ami['id'], $ami['pseudo'], 'avatar--mini') ?>
            <strong><?= e($ami['pseudo']) ?></strong>
            <?php if ($ami['meMontreLeSien']): ?><span class="discret"><?= e(t('cpt.montre_le_sien')) ?></span><?php endif; ?>
          </span>
          <form method="post" action="<?= url('partages/mon-calendrier/' . $ami['id']) ?>" class="en-ligne"
                <?= $ami['voitLeMien'] ? 'data-confirmation="' . e(t('cpt.calendrier_retirer_sur', ['qui' => $ami['pseudo']])) . '"' : '' ?>>
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <input type="hidden" name="retour" value="<?= e(url('compte') . '#mon-calendrier') ?>">
            <input type="hidden" name="partager" value="<?= $ami['voitLeMien'] ? '0' : '1' ?>">
            <button class="interrupteur" type="submit" role="switch" aria-checked="<?= $ami['voitLeMien'] ? 'true' : 'false' ?>">
              <span class="interrupteur__texte"><?= e($ami['voitLeMien'] ? t('cpt.voit_mon_calendrier') : t('cpt.ne_le_voit_pas')) ?></span>
            </button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="carte" style="margin-bottom:1rem" id="calendrier-amis">
  <h2 style="margin-top:0"><?= e(t('cpt.calendrier_amis')) ?></h2>
  <?php if ($calendrierAmis === []): ?>
    <p class="discret" style="margin:0"><?= e(t('cpt.calendrier_amis_vide')) ?></p>
  <?php else: ?>
    <p class="champ__aide" style="margin-top:0">
      <?= e(t('cpt.calendrier_amis_aide')) ?>
    </p>
    <ul class="reglages-amis">
      <?php foreach ($calendrierAmis as $ami): ?>
        <li>
          <span class="reglages-amis__qui">
            <?= Amis::avatar($ami['id'], $ami['pseudo'], 'avatar--mini') ?>
            <strong><?= e($ami['pseudo']) ?></strong>
            <span class="discret">· <?= e($ami['partages'] === 0 ? t('cpt.aucun_partage') : tn('cpt.partages_nb', (int) $ami['partages'])) ?></span>
          </span>
          <form method="post" action="<?= url('partages/calendrier/' . $ami['id']) ?>" class="en-ligne">
            <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
            <input type="hidden" name="retour" value="<?= e(url('compte') . '#calendrier-amis') ?>">
            <input type="hidden" name="afficher" value="<?= $ami['affiche'] ? '0' : '1' ?>">
            <button class="interrupteur" type="submit" role="switch" aria-checked="<?= $ami['affiche'] ? 'true' : 'false' ?>"
                    title="<?= e($ami['affiche'] ? t('cpt.ne_plus_afficher') : t('cpt.afficher_office')) ?>">
              <span class="interrupteur__texte"><?= e($ami['affiche'] ? t('cpt.dans_mon_calendrier') : t('cpt.seulement_partages')) ?></span>
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
    <h2><?= e(t('cpt.mot_de_passe')) ?></h2>

    <div class="reglage-lecture" data-reglage-lecture<?= $motDePasseOuvert ? ' hidden' : '' ?>>
      <p class="reglage-lecture__valeur" aria-label="<?= e(t('cpt.mdp_masque')) ?>">••••••••</p>
      <button class="bouton bouton--secondaire" type="button" data-reglage-modifier><?= e(t('commun.modifier')) ?></button>
    </div>

    <form method="post" action="<?= url('compte/mot-de-passe') ?>" data-reglage-edition<?= $motDePasseOuvert ? '' : ' hidden' ?>>
      <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">

      <div class="champ">
        <label for="mot_de_passe_actuel"><?= e(t('cpt.mdp_actuel')) ?></label>
        <input type="password" id="mot_de_passe_actuel" name="mot_de_passe_actuel" required
               autocomplete="current-password">
      </div>

      <div class="ligne-champs">
        <div class="champ">
          <label for="nouveau_mot_de_passe"><?= e(t('cpt.mdp_nouveau')) ?></label>
          <input type="password" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" required minlength="8"
                 autocomplete="new-password">
        </div>
        <div class="champ">
          <label for="nouveau_mot_de_passe_confirmation"><?= e(t('cpt.mdp_confirmation')) ?></label>
          <input type="password" id="nouveau_mot_de_passe_confirmation" name="nouveau_mot_de_passe_confirmation"
                 required minlength="8" autocomplete="new-password">
        </div>
      </div>

      <div class="actions">
        <button class="bouton" type="submit"><?= e(t('commun.enregistrer')) ?></button>
        <button class="bouton bouton--discret" type="button" data-reglage-annuler><?= e(t('commun.annuler')) ?></button>
      </div>
    </form>

    <p class="champ__aide" style="margin-bottom:0"><?= e(t('cpt.mdp_aide')) ?></p>
  </div>

  <div class="pile">
    <div class="carte">
      <h2><?= e(t('cpt.notifications')) ?></h2>
      <p class="discret" style="margin-bottom:.8rem">
        <?= e(t('cpt.notifications_aide')) ?>
      </p>
      <a class="bouton bouton--secondaire bouton--bloc" href="<?= url('notifications') ?>" data-fenetre><?= e(t('cpt.regler_notifications')) ?></a>
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
      <h2><?= e(t('cpt.hors_ligne')) ?></h2>
      <p class="discret" style="margin-bottom:.8rem">
        <?= e(t('cpt.hors_ligne_aide')) ?>
      </p>
      <p class="champ__aide" data-hors-ligne-etat aria-live="polite" style="margin-top:0"><?= e(t('cpt.verification')) ?></p>
      <p class="actions" style="margin-bottom:0">
        <button class="bouton bouton--secondaire bouton--petit" type="button" data-hors-ligne-garder hidden>
          <?= e(t('cpt.preparer')) ?>
        </button>
        <button class="bouton bouton--discret bouton--petit" type="button" data-hors-ligne-oublier hidden>
          <?= e(t('cpt.vider')) ?>
        </button>
      </p>
    </div>

    <div class="carte" style="border-color:var(--accent)">
      <h2><?= e(t('cpt.sauvegarde')) ?></h2>
      <p class="discret" style="margin-bottom:.8rem">
        <?= e(t('cpt.sauvegarde_aide')) ?>
      </p>
      <a class="bouton bouton--bloc" href="<?= url('compte/sauvegarde') ?>">
        <?= e(t('cpt.sauvegarder')) ?>
      </a>
    </div>

    <div class="carte">
      <h2><?= e(t('cpt.ou_donnees')) ?></h2>
      <p class="discret">
        <?= t('cpt.ou_donnees_1') ?>
      </p>
      <p class="discret" style="margin:0">
        <?= e(t('cpt.ou_donnees_2')) ?>
      </p>
    </div>
  </div>
</div>
