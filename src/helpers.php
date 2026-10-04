<?php
declare(strict_types=1);

/** Échappe une valeur pour l'affichage HTML. */
function e(?string $valeur): string
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Construit une URL absolue à partir d'un chemin interne. */
function url(string $chemin = '', array $params = []): string
{
    $chemin = ltrim($chemin, '/');
    $segments = $chemin === '' ? [] : array_map('rawurlencode', explode('/', $chemin));
    $url = BASE_URL . '/' . implode('/', $segments);
    if ($params !== []) {
        $url .= '?' . http_build_query($params);
    }
    return $url;
}

/** Redirige puis stoppe le script. */
function redirect(string $chemin, array $params = []): never
{
    /*
     * Un formulaire envoyé depuis une fenêtre le dit par « fenetre » : la
     * page où l'on repart garde la marque, et répond par un fragment que la
     * fenêtre pose à la place du formulaire. Seuls les formulaires faits pour
     * la fenêtre l'envoient.
     */
    if (($_POST['fenetre'] ?? '') === '1' && !array_key_exists('fenetre', $params)) {
        $params['fenetre'] = 1;
    }
    header('Location: ' . url($chemin, $params));
    exit;
}

/** Récupère une valeur POST (utile pour repeupler les formulaires). */
function post(string $cle, string $defaut = ''): string
{
    $valeur = $_POST[$cle] ?? $defaut;
    return is_string($valeur) ? trim($valeur) : $defaut;
}

/** Récupère un entier depuis POST/GET, ou null. */
function entier_ou_null(mixed $valeur): ?int
{
    if ($valeur === null || $valeur === '' || !is_numeric($valeur)) {
        return null;
    }
    return (int) $valeur;
}

/** Formate une taille en octets de façon lisible. */
function taille_lisible(int $octets): string
{
    $unites = ['o', 'ko', 'mo', 'go'];
    $i = 0;
    $taille = (float) $octets;
    while ($taille >= 1024 && $i < count($unites) - 1) {
        $taille /= 1024;
        $i++;
    }
    $nombre = $i === 0 ? (string) (int) $taille : number_format($taille, 1, t('fmt.decimal'), t('fmt.milliers'));

    return $nombre . ' ' . t('fmt.taille.' . $unites[$i]);
}

/**
 * Un mot de l'application, dans la langue choisie.
 *
 * C'est le seul chemin : partout ailleurs, on écrit « t('nav.accueil') » plutôt
 * que « Accueil ». Ce qu'écrit l'utilisateur, lui, n'est jamais traduit.
 */
function t(string $cle, array $valeurs = []): string
{
    return Langue::texte($cle, $valeurs);
}

/** Une phrase accordée au nombre : tn('taches.reste', 3). */
function tn(string $cle, int $n, array $valeurs = []): string
{
    return Langue::nombre($cle, $n, $valeurs);
}

/** Nom du mois, dans la langue choisie. */
function nom_mois(int $mois): string
{
    return Langue::liste('mois')[$mois - 1] ?? '';
}

/** Abréviation usuelle d'un mois : janv., févr., juil., sept.… */
function nom_mois_court(int $mois): string
{
    return Langue::liste('mois_courts')[$mois - 1] ?? '';
}

function jours_semaine(): array
{
    return Langue::liste('jours');
}

/**
 * Le nom d'un mois au milieu d'une phrase : « octobre 2026 » en français et en espagnol,
 * « October 2026 » en anglais, « Oktober 2026 » en allemand, où les mois gardent leur majuscule.
 * C'est la règle de « date.mois_minuscule » ; on n'écrit plus strtolower() à la main.
 */
function nom_mois_en_phrase(int $mois): string
{
    $nom = nom_mois($mois);

    return t('date.mois_minuscule') === '1' ? mb_strtolower($nom) : $nom;
}

/** Affiche une date/heure au format français. */
function date_fr(string $datetime, bool $avecHeure = true): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    $jour = date('j', $ts);
    // Les mois s'écrivent en minuscule en français et en espagnol, en majuscule ailleurs.
    $mois = nom_mois_en_phrase((int) date('n', $ts));
    $annee = date('Y', $ts);
    $texte = t('date.longue', ['jour' => $jour, 'mois' => $mois, 'annee' => $annee]);
    if ($avecHeure) {
        $texte .= ' ' . t('date.a') . ' ' . heure_courte($ts);
    }
    return $texte;
}

/**
 * L'heure seule, écrite comme on l'écrit dans la langue choisie.
 *
 * « 8h51 » en français, « 8:51 » ailleurs : c'est le fichier de langue qui
 * porte le format, et nulle part on ne le réécrit à la main.
 */
function heure_courte(int $ts): string
{
    return date(t('date.heure'), $ts);
}

/**
 * Types d'évènement proposés à la création d'un compte.
 * Ils sont ensuite entièrement modifiables depuis la page « Types ».
 */
function types_evenement_par_defaut(): array
{
    return [
        ['nom' => t('evt.type_defaut.cours'),    'icone' => '📘', 'couleur' => '#4f46e5', 'est_echeance' => 0, 'au_tableau' => 0],
        ['nom' => t('evt.type_defaut.examen'),   'icone' => '📝', 'couleur' => '#dc2626', 'est_echeance' => 1, 'au_tableau' => 1],
        ['nom' => t('evt.type_defaut.devoir'),   'icone' => '🗂️', 'couleur' => '#ea580c', 'est_echeance' => 1, 'au_tableau' => 1],
        ['nom' => t('evt.type_defaut.revision'), 'icone' => '🔁', 'couleur' => '#059669', 'est_echeance' => 0, 'au_tableau' => 1],
        ['nom' => t('evt.type_defaut.autre'),    'icone' => '📌', 'couleur' => '#64748b', 'est_echeance' => 0, 'au_tableau' => 0],
    ];
}

/**
 * Parmi des pièces jointes, celles dont on sait mesurer l'avancement.
 *
 * Un enregistrement, toujours. Un PDF dès qu'on connaît ses pages, même s'il
 * n'en a qu'une : on le déclare lu avec « Terminer ». Le reste — une image,
 * un tableur — n'a pas d'anneau et ne pèse donc dans aucune moyenne.
 *
 * @param array<int, array> $fichiers
 * @return list<array>
 */
function fichiers_suivis(array $fichiers): array
{
    $suivis = [];
    foreach ($fichiers as $fichier) {
        $mime = (string) ($fichier['mime'] ?? '');
        $nom = (string) ($fichier['nom_origine'] ?? '');
        $pdfSuivi = Fichiers::estPdf($mime, $nom) && (int) ($fichier['duree_lecture'] ?? 0) > 0;

        if (Fichiers::estMedia($mime, $nom) || $pdfSuivi) {
            $suivis[] = $fichier;
        }
    }
    return $suivis;
}
/**
 * L'avancement d'un ensemble de documents : la moyenne de leurs anneaux.
 *
 * Chacun pèse pareil, quelle que soit sa longueur : une heure de cours à
 * moitié écoutée vaut la même chose qu'un mémo de trois minutes à moitié
 * écouté, ou que des annales lues jusqu'à la moitié. Ce qui n'a jamais été
 * ouvert compte pour zéro : ne pas l'avoir commencé, c'est ne pas l'avoir révisé.
 *
 * @param array<int, array> $fichiers lignes de « fichiers » (audio, vidéo ou PDF)
 * @param list<int> $enPlus  d'autres anneaux déjà calculés, en pourcentage
 * @return array{pourcentage: int, total: int, finis: int, commences: int, a_faire: int}
 */
function avancement_anneaux(array $fichiers, array $enPlus = []): array
{
    $parts = [];
    foreach ($fichiers as $fichier) {
        $parts[] = avancement_lecture($fichier) ?? 0;
    }
    // Un paquet de cartes est un anneau comme un autre : il rejoint la moyenne.
    foreach ($enPlus as $part) {
        $parts[] = max(0, min(100, (int) $part));
    }

    $somme = 0;
    $finis = 0;
    $commences = 0;

    foreach ($parts as $part) {
        $somme += $part;
        if ($part >= 100) {
            $finis++;
        } elseif ($part > 0) {
            $commences++;
        }
    }

    $total = count($parts);

    return [
        'pourcentage' => $total === 0 ? 0 : (int) round($somme / $total),
        'total'       => $total,
        'finis'       => $finis,
        'commences'   => $commences,
        'a_faire'     => $total - $finis - $commences,
    ];
}

/**
 * L'avancement d'un paquet de cartes, en pourcentage entier.
 *
 * La mesure est la boîte moyenne : une carte en boîte 1 n'est pas apprise, une
 * carte en boîte 5 l'est. Un paquet tout neuf vaut donc zéro, et un paquet dont
 * chaque carte est montée au bout vaut cent — comme un enregistrement écouté
 * jusqu'au silence.
 *
 * @param int $total    combien de cartes compte le paquet
 * @param float $moyenne la boîte moyenne, entre 1 et 5
 */
function avancement_cartes(int $total, float $moyenne): ?int
{
    if ($total === 0) {
        return null;
    }
    $part = (max(1.0, min(5.0, $moyenne)) - 1) / 4 * 100;

    return (int) round($part);
}

/**
 * L'avancement dans un document, quel qu'il soit.
 *
 * Un enregistrement se mesure en secondes, un PDF en pages : les deux tiennent
 * dans les mêmes colonnes, « où l'on s'est arrêté » et « la longueur totale ».
 */
function avancement_lecture(array $fichier): ?int
{
    $nom = (string) ($fichier['nom_origine'] ?? '');
    return Fichiers::estPdf((string) ($fichier['mime'] ?? ''), $nom)
        ? avancement_pages($fichier)
        : avancement_media($fichier);
}

/**
 * L'avancement dans un document paginé, en pourcentage entier.
 *
 * Sans nombre de pages, on ne sait rien. La page zéro veut dire « ouvert, mais
 * pas encore parcouru » : l'anneau reste vide tant qu'on n'a pas tourné de page.
 */
function avancement_pages(array $fichier): ?int
{
    $pages = (int) ($fichier['duree_lecture'] ?? 0);
    if ($pages <= 0) {
        return null;
    }
    $page = max(0, min((int) ($fichier['position_lecture'] ?? 0), $pages));

    return (int) round($page / $pages * 100);
}
/**
 * L'avancement dans un enregistrement, en pourcentage entier.
 *
 * Sans durée connue, on ne sait rien : le navigateur ne l'a pas encore dite.
 * Les dernières secondes comptent pour la fin : personne ne regarde le
 * générique, et un lecteur s'arrête rarement au centième près.
 */
function avancement_media(array $fichier): ?int
{
    $duree = (int) ($fichier['duree_lecture'] ?? 0);
    if ($duree <= 0) {
        return null;
    }
    $position = min((int) ($fichier['position_lecture'] ?? 0), $duree);
    $pourcentage = (int) round($position / $duree * 100);

    return $duree - $position <= 5 ? 100 : $pourcentage;
}

/** Une durée en secondes, écrite comme sur un lecteur : 4:07, 1:02:30. */
function duree_lisible(int $secondes): string
{
    $secondes = max(0, $secondes);
    $h = intdiv($secondes, 3600);
    $m = intdiv($secondes % 3600, 60);
    $s = $secondes % 60;

    return $h > 0
        ? sprintf('%d:%02d:%02d', $h, $m, $s)
        : sprintf('%d:%02d', $m, $s);
}

/** Icône d'un évènement, avec repli si son type a été supprimé. */
function icone_evenement(array $evt): string
{
    return (string) ($evt['type_icone'] ?? '') !== '' ? (string) $evt['type_icone'] : '📌';
}

/** Libellé du type d'un évènement, avec repli si le type a été supprimé. */
function libelle_type(array $evt): string
{
    return (string) ($evt['type_nom'] ?? '') !== '' ? (string) $evt['type_nom'] : t('evt.sans_type');
}

/**
 * Couleur d'un évènement : celle de sa matière en priorité,
 * sinon celle de son type, sinon un gris neutre.
 */
function couleur_evenement(array $evt): string
{
    // La matière et le type gardent la main ; l'agenda ne colore que ce qui
    // n'a ni l'une ni l'autre — c'est-à-dire tout ce qui vient d'Outlook.
    foreach (['matiere_couleur', 'type_couleur', 'agenda_couleur'] as $cle) {
        $couleur = (string) ($evt[$cle] ?? '');
        if ($couleur !== '') {
            return $couleur;
        }
    }
    return '#94a3b8';
}

/** Emoji proposés dans le sélecteur d'icône d'un type. */
function icones_proposees(): array
{
    return ['📘', '📝', '🗂️', '🔁', '📌', '🧪', '🎓', '✏️', '📚', '🧮', '🗣️', '🎨',
        '🎵', '⚽', '💻', '🔬', '🌍', '⏰', '⭐', '🚀', '📊', '🧠', '📅', '🏫'];
}

/** Contraste : renvoie du texte noir ou blanc selon la couleur de fond. */
function couleur_texte(string $hex): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) {
        return '#ffffff';
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    return $luminance > 0.6 ? '#111827' : '#ffffff';
}

/** Extrait un résumé texte d'un contenu de cours. */
function extrait(?string $texte, int $longueur = 160): string
{
    // Un texte mis en forme s'extrait sans ses balises.
    $texte = trim(preg_replace('/\s+/u', ' ', TexteRiche::versTexte($texte)) ?? '');
    if ($texte === '') {
        return '';
    }
    if (mb_strlen($texte) <= $longueur) {
        return $texte;
    }
    return mb_substr($texte, 0, $longueur) . '…';
}

/**
 * Un extrait centré sur le premier terme trouvé, plutôt que sur le début.
 *
 * Une fiche de plusieurs pages ne se reconnaît pas à ses trois premières
 * lignes : ce qu'on cherche est le plus souvent au milieu.
 *
 * @param string[] $termes
 */
function extrait_autour(?string $texte, array $termes, int $longueur = 240): string
{
    $texte = trim(preg_replace('/\s+/u', ' ', TexteRiche::versTexte($texte)) ?? '');
    if ($texte === '' || mb_strlen($texte) <= $longueur) {
        return $texte;
    }

    $position = null;
    foreach ($termes as $terme) {
        $terme = trim($terme);
        if (mb_strlen($terme) < 2) {
            continue;
        }
        $trouve = mb_stripos($texte, $terme);
        if ($trouve !== false && ($position === null || $trouve < $position)) {
            $position = $trouve;
        }
    }

    if ($position === null) {
        return extrait($texte, $longueur);
    }

    // On recule d'un tiers : un extrait qui commence pile sur le terme perd
    // ce qui l'amenait.
    $debut = max(0, $position - intdiv($longueur, 3));

    return ($debut > 0 ? '…' : '')
         . mb_substr($texte, $debut, $longueur)
         . ($debut + $longueur < mb_strlen($texte) ? '…' : '');
}

/** Surligne les termes recherchés dans un texte déjà échappé. */
function surligner(string $texteEchappe, array $termes): string
{
    foreach ($termes as $terme) {
        $terme = trim($terme);
        if (mb_strlen($terme) < 2) {
            continue;
        }
        $texteEchappe = preg_replace(
            '/(' . preg_quote(e($terme), '/') . ')/iu',
            '<mark>$1</mark>',
            $texteEchappe
        ) ?? $texteEchappe;
    }
    return $texteEchappe;
}

/** Un texte entre guillemets, à la façon de la langue : « x », “x”, «x» ou „x“. */
function guillemets(string $texte): string
{
    return t('fmt.guillemets', ['texte' => $texte]);
}

/**
 * Une date en chiffres, à la façon de la langue : 03/10/2026, ou 03.10.2026 en allemand.
 *
 * @param DateTimeInterface|int|string $quand une date, un horodatage, ou un texte que strtotime lit
 */
function date_numerique(DateTimeInterface|int|string $quand): string
{
    if ($quand instanceof DateTimeInterface) {
        return $quand->format(t('date.courte'));
    }

    return date(t('date.courte'), is_int($quand) ? $quand : (int) strtotime($quand));
}

/* --- Montants (budget) ------------------------------------------------- */

/**
 * Formate un montant pour l'affichage, à la façon de la langue en cours :
 * « 1 234,50 € » en français, « €1,234.50 » en anglais, « 1.234,50 € » en allemand.
 */
function montant_lisible(int|float|string|null $montant, bool $avecSymbole = true, int $decimales = 2): string
{
    $valeur = round((float) ($montant ?? 0), $decimales);
    // Le signe se pose devant le tout : « -12,50 € » comme « -€12.50 ».
    $signe = $valeur < 0 ? '-' : '';
    $texte = number_format(abs($valeur), $decimales, t('fmt.decimal'), t('fmt.milliers'));

    return $signe . ($avecSymbole ? t('fmt.monnaie', ['montant' => $texte]) : $texte);
}
/**
 * Lit un montant saisi à la main : « 12,50 », « 12.50 », « 1 234,50 », « 12 € ».
 * Renvoie null si la saisie n'est pas un nombre exploitable.
 */
function montant_depuis_saisie(string $saisie, ?string $convention = null): ?float
{
    $saisie = trim($saisie);
    if ($saisie === '') {
        return null;
    }
    // Espaces (y compris insécables), symbole monétaire : on retire.
    $saisie = str_replace(["\u{00A0}", "\u{202F}", ' ', '€', 'EUR'], '', $saisie);

    $virgules = substr_count($saisie, ',');
    $points = substr_count($saisie, '.');
    if ($virgules > 0 && $points > 0) {
        // Les deux à la fois : le dernier est la décimale, l'autre sépare les milliers.
        $decimale = (int) strrpos($saisie, ',') > (int) strrpos($saisie, '.') ? ',' : '.';
        $milliers = $decimale === ',' ? '.' : ',';
        $saisie = str_replace($decimale, '.', str_replace($milliers, '', $saisie));
    } elseif ($virgules + $points > 0) {
        /*
         * Un seul signe. « 12,50 » et « 12.50 » sont des décimales dans toutes les
         * langues ; « 1,234 » ou « 1.234 » est un millier si c'est le signe des
         * milliers de la langue (la virgule en anglais, le point en allemand), et
         * une décimale sinon — c'est le cas du français, qui n'a que l'espace.
         * Un fichier de banque lit la langue du fichier, pas celle de la page :
         * l'appelant le dit par « $convention ».
         */
        $signe = $virgules > 0 ? ',' : '.';
        $miens = Langue::texteEn($convention ?? Langue::courante(), 'fmt.milliers');
        $forme = '/^[-+]?\d{1,3}(?:' . preg_quote($signe, '/') . '\d{3})+$/';
        $estMilliers = $signe === $miens && preg_match($forme, $saisie) === 1;
        $saisie = $estMilliers ? str_replace($signe, '', $saisie) : str_replace(',', '.', $saisie);
    }
    if (!is_numeric($saisie)) {
        return null;
    }
    return round((float) $saisie, 2);
}
/** Couleur d'une opération : celle de sa catégorie, sinon un gris neutre. */
function couleur_operation(array $operation): string
{
    $couleur = (string) ($operation['categorie_couleur'] ?? '');
    return $couleur !== '' ? $couleur : '#94a3b8';
}

/** Libellé de la catégorie d'une opération, avec repli. */
function libelle_categorie(array $operation): string
{
    $nom = (string) ($operation['categorie_nom'] ?? '');
    return $nom !== '' ? $nom : t('bud.sans_categorie');
}

/** Icône de la catégorie d'une opération, avec repli. */
function icone_categorie(array $operation): string
{
    $icone = (string) ($operation['categorie_icone'] ?? '');
    return $icone !== '' ? $icone : '💶';
}

/* --- Tâches ------------------------------------------------------------ */

/**
 * Situation d'une échéance par rapport à aujourd'hui.
 * Renvoie 'aucune', 'retard', 'aujourdhui', 'demain', 'proche' (moins d'une
 * semaine) ou 'lointain'. Une tâche faite n'est jamais en retard.
 */
function echeance_etat(?string $echeance, bool $faite = false): string
{
    if ($echeance === null || $echeance === '') {
        return 'aucune';
    }
    if ($faite) {
        return 'lointain';
    }
    $jour = date_create($echeance);
    if ($jour === false) {
        return 'aucune';
    }
    $jours = (int) date_create('today')->diff($jour->setTime(0, 0))->format('%r%a');

    return match (true) {
        $jours < 0  => 'retard',
        $jours === 0 => 'aujourdhui',
        $jours === 1 => 'demain',
        $jours <= 7 => 'proche',
        default     => 'lointain',
    };
}

/** Libellé court d'une échéance : « En retard », « Aujourd'hui », « lun. 8 sept. ». */
function echeance_libelle(?string $echeance, bool $faite = false): string
{
    $etat = echeance_etat($echeance, $faite);
    if ($etat === 'aucune') {
        return '';
    }
    $ts = strtotime((string) $echeance);
    $date = t('date.jour_mois_court', ['jour' => (int) date('j', $ts), 'mois' => nom_mois_court((int) date('n', $ts))]);

    if ($etat === 'retard') {
        $jours = (int) date_create('today')->diff(date_create((string) $echeance)->setTime(0, 0))->format('%a');
        return $jours === 1 ? t('date.hier') : t('ech.en_retard', ['date' => $date]);
    }
    if ($etat === 'aujourdhui') {
        return t('date.aujourdhui');
    }
    if ($etat === 'demain') {
        return t('date.demain');
    }
    if ($etat === 'proche') {
        return t('alt.jour_court', [
            'jour' => t('alt.jour.' . date('N', $ts)),
            'n' => (int) date('j', $ts),
            'mois' => nom_mois_court((int) date('n', $ts)),
        ]);
    }
    // Au-delà d'un an, l'année lève l'ambiguïté.
    return $date . (date('Y', $ts) !== date('Y') ? ' ' . date('Y', $ts) : '');
}

/**
 * Une liste de tâches que l'application tient pour elle-même (« Révisions », « Alternance »),
 * créée au premier besoin et retrouvée par son RÔLE — jamais par son nom.
 *
 * Le nom appartient à celui qui la voit : écrit dans sa langue à la création, il peut être
 * changé à la main. Retrouvée par son nom, la liste se perdait au premier changement de langue
 * ou de nom, et une seconde naissait, la première restant orpheline (colonne listes_taches.role).
 *
 * @param string $role          « revisions » ou « alternance »
 * @param string $cleNom        la clé de langue du nom de départ
 * @param string $nomHistorique le nom français d'avant le rôle, que portent les listes d'une archive
 *                              ancienne : une telle liste est reconnue, non dupliquée
 * @param bool   $creer         faux pour seulement lire : null si la liste n'existe pas
 */
function liste_systeme(int $userId, string $role, string $cleNom, string $nomHistorique,
                       string $couleur, string $icone, bool $creer = true): ?int
{
    $id = Database::valeur('SELECT id FROM listes_taches WHERE user_id = ? AND role = ? LIMIT 1', [$userId, $role]);
    if ($id !== null && $id !== false) {
        return (int) $id;
    }

    // Une liste du même nom, faite à la main ou venue d'une archive d'avant le rôle : on la
    // reconnaît plutôt que d'en fabriquer une jumelle, ou d'échouer sur le nom unique.
    $nom = t($cleNom);
    $connue = Database::valeur(
        'SELECT id FROM listes_taches WHERE user_id = ? AND role IS NULL AND nom IN (?, ?) ORDER BY id LIMIT 1',
        [$userId, $nom, $nomHistorique]);
    if ($connue !== null && $connue !== false) {
        Database::run('UPDATE listes_taches SET role = ? WHERE id = ? AND user_id = ?', [$role, $connue, $userId]);

        return (int) $connue;
    }
    if (!$creer) {
        return null;
    }

    // Le nom est pris par une autre liste — l'autre liste d'application, renommée comme celle-ci :
    // « Repaso (2) » plutôt qu'un refus.
    $essai = $nom;
    for ($i = 2; Database::valeur('SELECT id FROM listes_taches WHERE user_id = ? AND nom = ?', [$userId, $essai]) !== null; $i++) {
        $essai = $nom . ' (' . $i . ')';
    }
    $rang = (int) Database::valeur('SELECT COALESCE(MAX(position), 0) + 1 FROM listes_taches WHERE user_id = ?', [$userId]);
    // IGNORE : deux envois simultanés se disputent la clé (compte, rôle) ; le perdant relit celle du gagnant.
    Database::run(
        'INSERT IGNORE INTO listes_taches (user_id, nom, couleur, icone, role, position, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
        [$userId, $essai, $couleur, $icone, $role, $rang]);

    $id = Database::valeur('SELECT id FROM listes_taches WHERE user_id = ? AND role = ? LIMIT 1', [$userId, $role]);
    if ($id === null || $id === false) {
        throw new RuntimeException('La liste « ' . $role . ' » n\'a pas pu être créée.');
    }

    return (int) $id;
}

/**
 * Le nom que porte cette liste chez cet utilisateur : le sien, si elle existe et qu'il l'a peut-être
 * renommée ; sinon le nom de départ, dans sa langue. C'est lui qu'on cite dans les messages.
 */
function nom_liste_systeme(int $userId, string $role, string $cleNom): string
{
    $nom = Database::valeur('SELECT nom FROM listes_taches WHERE user_id = ? AND role = ? LIMIT 1', [$userId, $role]);

    return ($nom === null || $nom === false) ? t($cleNom) : (string) $nom;
}

/** Emoji proposés pour une liste de tâches. */
function icones_listes(): array
{
    return ['📋', '🎓', '🏠', '🛒', '💼', '💡', '✈️', '🎯', '🧾', '📦',
        '🩺', '🚗', '🎉', '💪', '📞', '🔖'];
}

/**
 * Adresse d'un fichier statique, suivie de sa date de modification.
 * Le navigateur récupère ainsi la nouvelle version dès que le fichier change,
 * sans qu'il faille penser à incrémenter un numéro à la main.
 */
function asset(string $chemin): string
{
    $absolu = dirname(__DIR__) . '/' . ltrim($chemin, '/');
    return url($chemin) . '?v=' . (is_file($absolu) ? (string) filemtime($absolu) : '0');
}

/**
 * « d'Outlook », mais « de Google Agenda ».
 *
 * Le nom d'un agenda vient d'ailleurs et ne se plie pas d'avance à la
 * phrase qui l'accueille. Une élision faite à la main donnerait « de
 * Outlook », qu'on ne lit nulle part ailleurs dans l'application.
 */
function de_agenda(string $nom): string
{
    /*
     * L'élision ne regarde que le français : « d’Outlook », mais « de
     * Google ». Ailleurs, la préposition ne bouge pas, et c'est le fichier
     * de langue qui la porte.
     */
    if (Langue::courante() !== 'fr') {
        return t('agenda.de', ['nom' => $nom]);
    }
    $premiere = mb_strtolower(mb_substr($nom, 0, 1));

    return in_array($premiere, ['a', 'e', 'i', 'o', 'u', 'y', 'é', 'è', 'h'], true)
        ? 'd’' . $nom
        : 'de ' . $nom;
}

/**
 * Répond en JSON et s'arrête là.
 *
 * Pour les appels que le navigateur passe seul, en arrière-plan : ils
 * n'attendent pas une page, et une page les ferait échouer sans le dire.
 */
function repondre_json(array $donnees): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * L'appel vient-il du script de la page plutôt que d'un formulaire ?
 *
 * Le formulaire reste la voie principale : sans JavaScript, tout continue
 * de fonctionner, en moins commode.
 */
function veut_du_json(): bool
{
    return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
}

/**
 * Repart vers la page d'où venait l'envoi, ou vers une page par défaut.
 *
 * L'adresse de retour vient du formulaire, donc de l'extérieur : on n'accepte
 * qu'un chemin de cette application — pas d'adresse absolue, pas de double
 * barre qui mènerait ailleurs, pas de saut de ligne qui glisserait un en-tête.
 */
function repartir_vers(string $defaut): never
{
    $retour = $_POST['retour'] ?? '';

    if (is_string($retour) && $retour !== ''
        && $retour[0] === '/'
        && !str_starts_with($retour, '//')
        && !preg_match('/[\r\n]/', $retour)
        && (BASE_URL === '' || str_starts_with($retour, BASE_URL . '/'))
    ) {
        header('Location: ' . $retour);
        exit;
    }

    redirect($defaut);
}

/** Emoji proposés pour un dossier de cours. */
function icones_dossiers(): array
{
    return ['📁', '📂', '🗂️', '📚', '🎓', '🔬', '🧮', '🗓️', '📦', '⭐',
        '🧪', '💼', '🎨', '🌍', '💻', '🏛️'];
}

/** Décalage visuel d'un dossier selon sa profondeur, dans une liste déroulante. */
function retrait_dossier(array $dossier): string
{
    return str_repeat("\u{00A0}\u{00A0}\u{00A0}", (int) ($dossier['profondeur'] ?? 0));
}
