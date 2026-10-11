<?php
/**
 * Un travail de groupe vu par son lien public : tout, en lecture seule.
 *
 * @var array $projet
 * @var string $jeton
 * @var list<array> $membres
 * @var list<array> $taches
 * @var list<array> $echeances
 * @var list<array> $fichiers
 * @var ?array $calendrier  le calendrier commun du projet (nom, couleur, évènements du mois), null s'il n'en a pas
 * @var DateTimeImmutable $moisCalendrier  le premier jour du mois affiché
 * @var array{id: int, prenom: string} $visiteur  qui est là (son prénom est vide tant que le lien ne demande pas de prénom)
 */
$maintenant = date('Y-m-d H:i:s');
$avenir = array_filter($echeances, static fn (array $e): bool => (string) $e['fin'] >= $maintenant);
$faites = count(array_filter($taches, static fn (array $t): bool => $t['statut'] === 'fait'));
$avancement = Travaux::avancement(count($taches), $faites);
?>
<?php if (($visiteur['prenom'] ?? '') !== ''): ?>
  <?php // Qui est là, et un moyen de dire autre chose : « Ce n'est pas vous ? » fait oublier le prénom à ce navigateur. ?>
  <form method="post" action="<?= url('g/' . $jeton . '/sortir') ?>" class="visiteur-bandeau">
    <input type="hidden" name="_csrf" value="<?= e(Session::jetonCsrf()) ?>">
    <span>👋 <?= e(t('tr.pu.bonjour', ['prenom' => (string) $visiteur['prenom']])) ?></span>
    <button class="bouton bouton--discret bouton--petit" type="submit"><?= e(t('tr.pu.pas_vous')) ?></button>
  </form>
<?php endif; ?>
<div class="entete-page">
  <div>
    <h1>👥 <?= e((string) $projet['nom']) ?></h1>
    <?php if ((string) ($projet['description'] ?? '') !== ''): ?>
      <p><?= nl2br(e((string) $projet['description'])) ?></p>
    <?php endif; ?>
    <p class="discret"><?= e(t('tr.pu.travail_groupe')) ?> <?= e(implode(', ', array_map(static fn (array $m): string => (string) $m['nom_affiche'], $membres))) ?></p>
  </div>
</div>

<div class="colonnes">
  <div class="pile">
    <section class="carte">
      <h2><?= e(t('tr.pu.qui_fait_quoi')) ?></h2>
      <?php if ($avancement !== null): ?>
        <div class="jauge" title="<?= e(t('tr.li.part_faite', ['n' => $avancement])) ?>">
          <span style="width:<?= $avancement ?>%;background:var(--accent)"></span>
        </div>
        <p class="discret" style="margin:0 0 .6rem"><?= e(t('tr.pu.faites_sur', [
            'faites' => $faites, 'total' => count($taches),
        ])) ?></p>
      <?php endif; ?>
      <?php if ($taches === []): ?>
        <p class="discret"><?= e(t('tr.pu.aucune_tache')) ?></p>
      <?php else: ?>
        <ul class="travaux-public-taches">
          <?php foreach ($taches as $t): ?>
            <?php $fait = $t['statut'] === 'fait'; ?>
            <li<?= $fait ? ' class="travaux-public-taches--faite"' : '' ?>>
              <span aria-hidden="true"><?= Travaux::STATUTS[$t['statut']]['icone'] ?></span>
              <span style="flex:1;min-width:0"><?= e((string) $t['titre']) ?>
                <span class="discret">· <?= e((string) ($t['membre_nom'] ?? t('tr.pu.personne'))) ?></span></span>
              <?php $texte = echeance_libelle($t['echeance'], $fait); ?>
              <?php if ($texte !== ''): ?>
                <span class="echeance echeance--<?= e(echeance_etat($t['echeance'], $fait)) ?>"><?= e($texte) ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <?php if (($calendrier ?? null) !== null): ?>
      <?php
      /*
       * Le calendrier commun du projet, en lecture seule : les évènements et les tâches datées du mois, jour par jour. Se parcourt de mois en
       * mois (« ?mois= ») ; quelqu'un qui n'a pas de compte y a le même accès que celui que donne ce lien au reste du projet.
       */
      $precedent = $moisCalendrier->modify('-1 month')->format('Y-m');
      $suivant = $moisCalendrier->modify('+1 month')->format('Y-m');
      $parJour = [];
      foreach ($calendrier['evenements'] as $ev) {
          $jour = new DateTimeImmutable(substr($ev['debut'], 0, 10));
          $dernier = new DateTimeImmutable(substr($ev['fin'], 0, 10));
          for (; $jour <= $dernier; $jour = $jour->modify('+1 day')) {
              if ($jour->format('Y-m') === $moisCalendrier->format('Y-m')) { $parJour[$jour->format('Y-m-d')][] = $ev; }
          }
      }
      ksort($parJour);
      $aujourdhui = date('Y-m-d');
      ?>
      <section class="carte" id="calendrier-public">
        <div class="calendrier-public__entete">
          <h2 style="margin:0">📅 <?= e(t('tr.pu.calendrier')) ?></h2>
          <nav class="calendrier-public__mois" aria-label="<?= e(t('tr.pu.calendrier_mois')) ?>">
            <a class="bouton bouton--discret bouton--petit" href="<?= url('g/' . $jeton, ['mois' => $precedent]) ?>#calendrier-public" rel="nofollow" aria-label="<?= e(t('tr.pu.mois_precedent')) ?>">‹</a>
            <strong><?= e(ucfirst(nom_mois_en_phrase((int) $moisCalendrier->format('n')))) ?> <?= e($moisCalendrier->format('Y')) ?></strong>
            <a class="bouton bouton--discret bouton--petit" href="<?= url('g/' . $jeton, ['mois' => $suivant]) ?>#calendrier-public" rel="nofollow" aria-label="<?= e(t('tr.pu.mois_suivant')) ?>">›</a>
          </nav>
        </div>
        <?php if ($parJour === []): ?>
          <p class="discret" style="margin:.8rem 0 0"><?= e(t('tr.pu.calendrier_vide')) ?></p>
        <?php else: ?>
          <ul class="calendrier-public__jours">
            <?php foreach ($parJour as $date => $evenementsDuJour): ?>
              <?php $ts = strtotime($date . ' 12:00:00'); ?>
              <li class="calendrier-public__jour<?= $date === $aujourdhui ? ' calendrier-public__jour--aujourdhui' : '' ?>">
                <span class="calendrier-public__date">
                  <span class="calendrier-public__numero"><?= (int) date('j', $ts) ?></span>
                  <span class="calendrier-public__semaine"><?= e(jours_semaine()[((int) date('N', $ts)) - 1] ?? '') ?></span>
                </span>
                <ul class="calendrier-public__evenements">
                  <?php foreach ($evenementsDuJour as $ev): ?>
                    <li class="calendrier-public__evenement<?= $ev['fait'] ? ' calendrier-public__evenement--fait' : '' ?>" style="--couleur-evt:<?= e($calendrier['couleur']) ?>">
                      <strong><?= $ev['tache'] ? '✅ ' : '' ?><?= e($ev['titre']) ?></strong>
                      <span class="discret">
                        <?= e($ev['journee_entiere'] ? t('tr.pu.toute_la_journee') : heure_courte((int) strtotime($ev['debut'])) . ' – ' . heure_courte((int) strtotime($ev['fin']))) ?>
                        <?php if ($ev['lieu'] !== ''): ?>· 📍 <?= e($ev['lieu']) ?><?php endif; ?>
                        <?php if ($ev['tache'] && $ev['qui'] !== ''): ?>· <?= e($ev['qui']) ?><?php endif; ?>
                      </span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ((string) ($projet['document'] ?? '') !== ''): ?>
      <section class="carte">
        <h2><?= e(t('tr.do.titre')) ?></h2>
        <div class="texte-riche-affiche"><?= TexteRiche::versHtml((string) $projet['document']) ?></div>
      </section>
    <?php endif; ?>
  </div>

  <div class="pile">
    <section class="carte">
      <h2><?= e(t('tr.pu.echeances')) ?></h2>
      <?php if ($avenir === []): ?>
        <p class="discret"><?= e(t('tr.pu.aucune_echeance')) ?></p>
      <?php else: ?>
        <ul class="travaux-echeances">
          <?php foreach ($avenir as $e): ?>
            <?php $journee = (int) $e['journee_entiere'] === 1; ?>
            <li class="travaux-echeance">
              <span class="travaux-echeance__icone" aria-hidden="true"><?= e((string) ($e['type_icone'] ?? Travaux::ICONE_SANS_TYPE)) ?></span>
              <span><strong><?= e((string) $e['titre']) ?></strong><br>
                <?= e(ucfirst(date_fr((string) $e['debut'], !$journee))) ?>
                <?php if ((string) ($e['lieu'] ?? '') !== ''): ?><span class="discret">· <?= e((string) $e['lieu']) ?></span><?php endif; ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <?php if ($fichiers !== []): ?>
      <section class="carte">
        <h2><?= e(t('tr.pu.fichiers')) ?></h2>
        <ul class="liste-fichiers">
          <?php foreach ($fichiers as $f): ?>
            <li class="fichier">
              <span class="fichier__icone" aria-hidden="true"><?= Fichiers::icone((string) $f['mime'], (string) $f['nom_origine']) ?></span>
              <span style="min-width:0">
                <a class="fichier__nom" href="<?= url('g/' . $jeton . '/fichiers/' . (int) $f['id']) ?>" target="_blank" rel="noopener">
                  <?= e((string) $f['nom_origine']) ?></a><br>
                <span class="fichier__meta"><?= e(taille_lisible((int) $f['taille'])) ?></span>
              </span>
              <span class="fichier__actions">
                <a class="bouton bouton--discret bouton--petit" href="<?= url('g/' . $jeton . '/fichiers/' . (int) $f['id'], ['telecharger' => 1]) ?>"
                   title="<?= e(t('tr.fi.telecharger')) ?>" aria-label="<?= e(t('tr.fi.telecharger_nom', ['nom' => (string) $f['nom_origine']])) ?>">⬇</a>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  </div>
</div>
