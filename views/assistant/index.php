<?php
/**
 * L'assistant IA : mes discussions à gauche, la discussion ouverte à droite, le formulaire en bas.
 *
 * @var bool $cleConfiguree   le chiffrement des clés est-il en place sur cette installation ?
 * @var bool $cleEnregistree  la personne a-t-elle enregistré sa clé Gemini ?
 * @var list<array> $discussions
 * @var ?array $discussion   celle qu'on a ouverte, ou null (une page blanche : on en commence une)
 * @var list<array> $messages  ses tours : id, role (user|model), texte, html
 * @var list<array> $cours     mes cours, pour en choisir un dont parler
 * @var int $messageMax
 */
$csrf = Session::jetonCsrf();
$pret = $cleConfiguree && $cleEnregistree;
$idOuverte = $discussion === null ? null : (int) $discussion['id'];
$coursChoisi = $discussion === null || $discussion['cours_id'] === null ? null : (int) $discussion['cours_id'];
?>
<div class="entete-page">
  <div>
    <h1>🤖 <?= e(t('ia.titre')) ?></h1>
    <p><?= e(t('ia.sous_titre')) ?></p>
  </div>
</div>

<?php if (!$cleConfiguree): ?>
  <section class="carte"><p class="discret" style="margin:0"><?= e(t('ia.pas_configure')) ?></p></section>
<?php elseif (!$cleEnregistree): ?>
  <section class="carte">
    <p style="margin:0 0 .6rem"><?= e(t('ia.pas_de_cle')) ?></p>
    <a class="bouton" href="<?= url('compte') ?>#gemini"><?= e(t('ia.pas_de_cle_lien')) ?></a>
  </section>
<?php endif; ?>

<div class="ia"<?= $pret ? ' data-assistant data-envoyer="' . e(url('assistant/envoyer')) . '" data-jeton="' . e($csrf) . '"' . ($idOuverte !== null ? ' data-discussion="' . $idOuverte . '"' : '') : '' ?>>
  <aside class="carte ia__liste" aria-label="<?= e(t('ia.mes_discussions')) ?>">
    <a class="bouton ia__nouvelle" href="<?= url('assistant') ?>"><?= e(t('ia.nouvelle')) ?></a>
    <h2 class="ia__sous-titre"><?= e(t('ia.mes_discussions')) ?></h2>
    <?php if ($discussions === []): ?>
      <p class="discret" style="margin:0" data-ia-aucune><?= e(t('ia.aucune_discussion')) ?></p>
    <?php endif; ?>
    <ul class="ia__discussions" data-ia-liste>
      <?php foreach ($discussions as $d): ?>
        <li>
          <a href="<?= url('assistant/' . (int) $d['id']) ?>"<?= (int) $d['id'] === $idOuverte ? ' aria-current="page"' : '' ?>><?= e((string) $d['titre']) ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
  </aside>

  <section class="carte ia__fil" aria-live="polite">
    <?php if ($discussion !== null): ?>
      <header class="ia__entete">
        <h2 class="ia__titre" data-ia-titre><?= e((string) $discussion['titre']) ?></h2>
        <details class="ia__actions">
          <summary class="bouton bouton--discret bouton--petit" title="<?= e(t('ia.renommer')) ?>">⋯</summary>
          <div class="ia__menu">
            <form method="post" action="<?= url('assistant/' . $idOuverte . '/renommer') ?>" class="ia__renommer">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <label class="sr-only" for="ia-titre"><?= e(t('ia.renommer_titre')) ?></label>
              <input type="text" id="ia-titre" name="titre" maxlength="120" required value="<?= e((string) $discussion['titre']) ?>">
              <button class="bouton bouton--petit" type="submit"><?= e(t('ia.enregistrer')) ?></button>
            </form>
            <form method="post" action="<?= url('assistant/' . $idOuverte . '/supprimer') ?>"
                  data-confirmation="<?= e(t('ia.supprimer_confirmation')) ?>">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <button class="bouton bouton--danger bouton--petit" type="submit"><?= e(t('ia.supprimer')) ?></button>
            </form>
          </div>
        </details>
      </header>
    <?php endif; ?>

    <div class="ia__messages" data-ia-messages>
      <?php if ($messages === []): ?>
        <div class="ia__accueil" data-ia-accueil>
          <span class="ia__accueil-icone" aria-hidden="true">🤖</span>
          <h2><?= e(t('ia.bienvenue_titre')) ?></h2>
          <p class="discret"><?= e(t('ia.bienvenue_texte')) ?></p>
          <?php if ($pret): ?>
            <div class="ia__suggestions">
              <?php foreach (['ia.suggestion_1', 'ia.suggestion_2', 'ia.suggestion_3'] as $cle): ?>
                <button type="button" class="bouton bouton--secondaire bouton--petit" data-ia-suggestion="<?= e(t($cle)) ?>"><?= e(t($cle)) ?></button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php foreach ($messages as $m): ?>
        <article class="ia__tour ia__tour--<?= $m['role'] === 'model' ? 'ia' : 'moi' ?>">
          <span class="ia__auteur"><?= e(t($m['role'] === 'model' ? 'ia.assistant' : 'ia.toi')) ?></span>
          <div class="ia__bulle texte-riche-affiche"><?= $m['html'] ?></div>
        </article>
      <?php endforeach; ?>
    </div>

    <form class="ia__saisie" method="post" action="<?= url('assistant/envoyer') ?>" data-ia-formulaire>
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <?php if ($idOuverte !== null): ?><input type="hidden" name="discussion" value="<?= $idOuverte ?>"><?php endif; ?>
      <div class="ia__cours">
        <label for="ia-cours"><?= e(t('ia.cours_label')) ?></label>
        <select id="ia-cours" name="cours"<?= $pret ? '' : ' disabled' ?>>
          <option value=""><?= e(t('ia.cours_aucun')) ?></option>
          <?php foreach ($cours as $c): ?>
            <option value="<?= (int) $c['id'] ?>"<?= $coursChoisi === (int) $c['id'] ? ' selected' : '' ?>><?= e((string) $c['titre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ia__ligne">
        <label class="sr-only" for="ia-message"><?= e(t('ia.envoyer')) ?></label>
        <textarea id="ia-message" name="message" rows="2" maxlength="<?= (int) $messageMax ?>" required data-ia-message
                  placeholder="<?= e(t('ia.placeholder')) ?>"<?= $pret ? '' : ' disabled' ?>></textarea>
        <button class="bouton" type="submit" data-ia-envoyer<?= $pret ? '' : ' disabled' ?>><?= e(t('ia.envoyer')) ?></button>
      </div>
      <p class="champ__aide" style="margin:.35rem 0 0"><?= e(t('ia.confidentialite')) ?></p>
      <p class="chat__erreur" data-ia-erreur role="alert" hidden></p>
    </form>
  </section>
</div>
<?php if ($pret): ?><script src="<?= asset('assets/js/assistant.js') ?>" defer></script><?php endif; ?>
