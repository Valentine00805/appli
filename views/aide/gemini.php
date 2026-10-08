<?php
/**
 * Mode d'emploi : obtenir une clé API Gemini, pas à pas, avec des schémas.
 *
 * Les schémas sont des dessins simplifiés de ce que Google affiche (le vrai écran change de temps en temps, et se traduit selon
 * la langue du compte Google) : ils montrent où regarder, pas une capture exacte. Les mots qu'on y lit sont ceux, anglais, du
 * bouton de Google ; les phrases autour sont dans la langue de l'application.
 *
 * @var bool $dansUneFenetre
 */
$dansUneFenetre = $dansUneFenetre ?? false;
$lienStudio = 'https://aistudio.google.com/apikey';
?>
<div class="entete-page"<?= $dansUneFenetre ? ' data-large' : '' ?>>
  <div>
    <h1 style="margin:0">🔑 <?= e(t('aide.gemini.titre')) ?></h1>
    <p class="discret" style="margin:.2rem 0 0"><?= e(t('aide.gemini.intro')) ?></p>
  </div>
</div>

<section class="carte guide">
  <h2 style="margin-top:0"><?= e(t('aide.gemini.avant')) ?></h2>
  <ul>
    <li><?= e(t('aide.gemini.avant_compte')) ?></li>
    <li><?= e(t('aide.gemini.avant_age')) ?></li>
    <li><?= e(t('aide.gemini.avant_gratuit')) ?></li>
  </ul>
  <div class="guide__alerte">
    <strong>⚠️ <?= e(t('aide.gemini.vie_privee_titre')) ?></strong>
    <?= e(t('aide.gemini.vie_privee')) ?>
  </div>
</section>

<ol class="guide__etapes">

  <li class="carte guide">
    <h2><?= e(t('aide.gemini.e1_titre')) ?></h2>
    <p><?= e(t('aide.gemini.e1')) ?></p>
    <p><a class="bouton" href="<?= e($lienStudio) ?>" target="_blank" rel="noopener noreferrer">🌐 <?= e(t('aide.gemini.ouvrir')) ?></a></p>
    <svg class="guide__fig" viewBox="0 0 640 190" role="img" aria-label="<?= e(t('aide.gemini.e1_fig')) ?>">
      <rect class="g-fenetre" x="20" y="14" width="600" height="162" rx="12"/>
      <rect class="g-barre" x="20" y="14" width="600" height="38" rx="12"/>
      <circle cx="42" cy="33" r="5" fill="#ef4444"/><circle cx="60" cy="33" r="5" fill="#f59e0b"/><circle cx="78" cy="33" r="5" fill="#22c55e"/>
      <rect class="g-champ" x="104" y="22" width="400" height="22" rx="11"/>
      <text class="g-texte" x="122" y="38">aistudio.google.com/apikey</text>
      <rect class="g-accent" x="100" y="19" width="408" height="28" rx="14"/>
      <circle cx="586" cy="33" r="13" fill="#4f46e5"/><text x="586" y="38" text-anchor="middle" fill="#fff" font-size="14" font-weight="700" font-family="system-ui">A</text>
      <rect class="g-ligne" x="56" y="82" width="210" height="14" rx="7"/>
      <rect class="g-ligne" x="56" y="108" width="330" height="10" rx="5"/>
      <rect class="g-ligne" x="56" y="128" width="280" height="10" rx="5"/>
      <g><circle class="g-pastille" cx="522" cy="108" r="13"/><text x="522" y="113" text-anchor="middle" class="g-pastille-texte">1</text></g>
    </svg>
  </li>

  <li class="carte guide">
    <h2><?= e(t('aide.gemini.e2_titre')) ?></h2>
    <p><?= e(t('aide.gemini.e2')) ?></p>
    <svg class="guide__fig" viewBox="0 0 640 200" role="img" aria-label="<?= e(t('aide.gemini.e2_fig')) ?>">
      <rect class="g-fenetre" x="110" y="14" width="420" height="172" rx="14"/>
      <text class="g-texte" x="136" y="46">Terms of Service</text>
      <rect class="g-ligne" x="136" y="62" width="368" height="9" rx="4"/>
      <rect class="g-ligne" x="136" y="80" width="330" height="9" rx="4"/>
      <rect x="136" y="108" width="20" height="20" rx="4" fill="#fff" stroke="#1a73e8" stroke-width="2"/>
      <path d="M141 118 l5 5 l9 -11" fill="none" stroke="#1a73e8" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
      <rect class="g-ligne" x="166" y="114" width="250" height="9" rx="4"/>
      <rect class="g-bouton" x="396" y="148" width="108" height="30" rx="15"/>
      <text class="g-bouton-texte" x="450" y="168" text-anchor="middle">Continue</text>
      <rect class="g-accent" x="128" y="100" width="36" height="36" rx="8"/>
      <rect class="g-accent" x="390" y="143" width="120" height="40" rx="20"/>
      <g><circle class="g-pastille" cx="110" cy="118" r="13"/><text x="110" y="123" text-anchor="middle" class="g-pastille-texte">2</text></g>
    </svg>
  </li>

  <li class="carte guide">
    <h2><?= e(t('aide.gemini.e3_titre')) ?></h2>
    <p><?= e(t('aide.gemini.e3')) ?></p>
    <svg class="guide__fig" viewBox="0 0 640 200" role="img" aria-label="<?= e(t('aide.gemini.e3_fig')) ?>">
      <rect class="g-fenetre" x="20" y="14" width="600" height="172" rx="12"/>
      <rect class="g-lateral" x="20" y="14" width="130" height="172" rx="12"/>
      <rect class="g-ligne" x="38" y="48" width="92" height="10" rx="5"/>
      <rect x="32" y="76" width="106" height="26" rx="13" fill="#d8e6ff"/><text x="46" y="94" class="g-texte" style="font-size:12px">API keys</text>
      <rect class="g-ligne" x="38" y="122" width="80" height="10" rx="5"/>
      <text class="g-titre" x="176" y="52">API keys</text>
      <rect class="g-bouton" x="176" y="76" width="150" height="34" rx="17"/>
      <text class="g-bouton-texte" x="251" y="98" text-anchor="middle">Create API key</text>
      <rect class="g-accent" x="170" y="70" width="162" height="46" rx="23"/>
      <rect class="g-ligne" x="176" y="136" width="400" height="10" rx="5"/>
      <rect class="g-ligne" x="176" y="154" width="300" height="10" rx="5"/>
      <g><circle class="g-pastille" cx="344" cy="93" r="13"/><text x="344" y="98" text-anchor="middle" class="g-pastille-texte">3</text></g>
    </svg>
    <p class="champ__aide"><?= e(t('aide.gemini.e3_aide')) ?></p>
  </li>

  <li class="carte guide">
    <h2><?= e(t('aide.gemini.e4_titre')) ?></h2>
    <p><?= e(t('aide.gemini.e4')) ?></p>
    <svg class="guide__fig" viewBox="0 0 640 210" role="img" aria-label="<?= e(t('aide.gemini.e4_fig')) ?>">
      <rect class="g-fenetre" x="130" y="12" width="380" height="188" rx="14"/>
      <text class="g-texte" x="156" y="42">Create API key</text>
      <text class="g-doux" x="156" y="66">Choose an imported project</text>
      <rect class="g-champ" x="156" y="76" width="328" height="34" rx="6" stroke="#9aa3bd" stroke-width="1.5"/>
      <text class="g-doux" x="170" y="98">Search Google Cloud projects</text>
      <path d="M458 90 l8 8 l8 -8" fill="none" stroke="#6b7390" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
      <rect class="g-accent" x="150" y="70" width="340" height="46" rx="8"/>
      <rect class="g-ligne" x="156" y="124" width="250" height="9" rx="4"/>
      <rect class="g-bouton" x="326" y="152" width="158" height="30" rx="15"/>
      <text class="g-bouton-texte" x="405" y="172" text-anchor="middle">Create API key</text>
      <g><circle class="g-pastille" cx="130" cy="93" r="13"/><text x="130" y="98" text-anchor="middle" class="g-pastille-texte">4</text></g>
    </svg>
    <p class="champ__aide"><?= e(t('aide.gemini.e4_aide')) ?></p>
  </li>

  <li class="carte guide">
    <h2><?= e(t('aide.gemini.e5_titre')) ?></h2>
    <p><?= e(t('aide.gemini.e5')) ?></p>
    <svg class="guide__fig" viewBox="0 0 640 170" role="img" aria-label="<?= e(t('aide.gemini.e5_fig')) ?>">
      <rect class="g-fenetre" x="20" y="14" width="600" height="142" rx="12"/>
      <rect class="g-barre" x="20" y="14" width="600" height="34" rx="12"/>
      <text class="g-doux" x="40" y="36">Name</text><text class="g-doux" x="200" y="36">API key</text><text class="g-doux" x="450" y="36">Created</text>
      <text class="g-texte" x="40" y="86" style="font-size:13px">Gemini API Key</text>
      <text class="g-texte" x="200" y="86" style="font-size:13px;font-family:Consolas,monospace">AIza••••••••••••••••••••</text>
      <rect x="500" y="66" width="34" height="30" rx="8" fill="#fff" stroke="#1a73e8" stroke-width="2"/>
      <rect x="509" y="72" width="14" height="16" rx="2" fill="none" stroke="#1a73e8" stroke-width="2"/>
      <rect x="513" y="75" width="14" height="16" rx="2" fill="#fff" stroke="#1a73e8" stroke-width="2"/>
      <rect class="g-accent" x="494" y="60" width="46" height="42" rx="10"/>
      <rect class="g-ligne" x="40" y="118" width="520" height="8" rx="4"/>
      <g><circle class="g-pastille" cx="560" cy="81" r="13"/><text x="560" y="86" text-anchor="middle" class="g-pastille-texte">5</text></g>
    </svg>
    <div class="guide__alerte guide__alerte--danger">
      <strong>🔒 <?= e(t('aide.gemini.secret_titre')) ?></strong>
      <?= e(t('aide.gemini.secret')) ?>
    </div>
  </li>

  <li class="carte guide">
    <h2><?= e(t('aide.gemini.e6_titre')) ?></h2>
    <p><?= e(t('aide.gemini.e6')) ?></p>
    <svg class="guide__fig" viewBox="0 0 640 190" role="img" aria-label="<?= e(t('aide.gemini.e6_fig')) ?>">
      <rect class="g-fenetre" x="60" y="12" width="520" height="166" rx="14"/>
      <text class="g-texte" x="86" y="42"><?= e(t('gemini.titre')) ?></text>
      <rect class="g-champ" x="86" y="58" width="468" height="36" rx="8" stroke="#9aa3bd" stroke-width="1.5"/>
      <text class="g-texte" x="100" y="82" style="letter-spacing:2px">••••••••••••••••••••••••••••</text>
      <rect class="g-accent" x="80" y="52" width="480" height="48" rx="10"/>
      <rect x="86" y="118" width="110" height="34" rx="9" fill="#4f46e5"/>
      <text x="141" y="140" text-anchor="middle" fill="#fff" font-size="13" font-weight="700" font-family="system-ui"><?= e(t('commun.enregistrer')) ?></text>
      <rect class="g-accent" x="80" y="112" width="122" height="46" rx="12"/>
      <g><circle class="g-pastille" cx="60" cy="76" r="13"/><text x="60" y="81" text-anchor="middle" class="g-pastille-texte">6</text></g>
    </svg>
    <ol class="guide__sous">
      <li><?= e(t('aide.gemini.e6_a')) ?></li>
      <li><?= e(t('aide.gemini.e6_b')) ?></li>
      <li><?= e(t('aide.gemini.e6_c')) ?></li>
    </ol>
    <p><a class="bouton bouton--secondaire" href="<?= url('compte') ?>#gemini"><?= e(t('aide.gemini.vers_compte')) ?></a></p>
  </li>
</ol>

<section class="carte guide">
  <h2 style="margin-top:0">✅ <?= e(t('aide.gemini.apres_titre')) ?></h2>
  <p><?= e(t('aide.gemini.apres')) ?></p>
  <p><a class="bouton bouton--secondaire" href="<?= url('assistant') ?>">🤖 <?= e(t('nav.assistant')) ?></a></p>
</section>

<section class="carte guide">
  <h2 style="margin-top:0">🛠️ <?= e(t('aide.gemini.depannage')) ?></h2>
  <dl class="guide__faq">
    <dt><?= e(t('aide.gemini.q1')) ?></dt><dd><?= e(t('aide.gemini.r1')) ?></dd>
    <dt><?= e(t('aide.gemini.q2')) ?></dt><dd><?= e(t('aide.gemini.r2')) ?></dd>
    <dt><?= e(t('aide.gemini.q3')) ?></dt><dd><?= e(t('aide.gemini.r3')) ?></dd>
    <dt><?= e(t('aide.gemini.q4')) ?></dt><dd><?= e(t('aide.gemini.r4')) ?></dd>
    <dt><?= e(t('aide.gemini.q5')) ?></dt><dd><?= e(t('aide.gemini.r5')) ?></dd>
  </dl>
  <p class="champ__aide"><?= e(t('aide.gemini.schemas')) ?></p>
</section>
