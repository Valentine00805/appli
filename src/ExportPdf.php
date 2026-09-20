<?php
declare(strict_types=1);

/**
 * Un document du cours, mis en pages en PDF.
 *
 * Il part de la même lecture que l'aperçu : le texte mis en forme paragraphe par
 * paragraphe — gras, italique, souligné, taille, couleur, surlignage —, les
 * titres, les listes, l'alignement et les images. Ce que l'aperçu ne rend pas
 * (tableaux, en-têtes, pagination d'origine) n'y est pas davantage : le PDF est
 * celui de l'aperçu, en pages A4, numérotées.
 *
 * Un fichier texte (« brut ») sort en caractères à chasse fixe, ligne à ligne.
 *
 * Le contenu d'un cours sort de la même mise en pages : son HTML nettoyé
 * (TexteRiche) est découpé en paragraphes du même genre, sous le titre du cours,
 * avec son sommaire et les pages où l'on trouve chaque titre.
 */
final class ExportPdf
{
    private const MARGE = 56.7;          // 2 cm
    private const PIED = 28.0;           // la bande du numéro de page
    private const TAILLE = 11.0;
    private const TITRES = [1 => 18.0, 2 => 15.0, 3 => 13.0];
    private const RETRAIT = 18.0;        // un niveau de liste
    private const PX_EN_PT = 0.75;       // 96 pixels par pouce, 72 points par pouce
    private const IMAGE_PX_MAX = 1800;

    private PdfSimple $pdf;
    private float $y = 0;

    /** @var array<int|string, ?array{0: int, 1: int, 2: int}> les images déjà posées, par rang ou par empreinte */
    private array $images = [];

    /** @var list<int> la page (à partir de 1) de chaque titre, dans l'ordre du texte */
    private array $pagesDesTitres = [];

    private function __construct(private string $chemin = '', private string $nom = '')
    {
        $this->pdf = new PdfSimple();
    }

    /** Ce fichier sait-il sortir en PDF ? */
    public static function possible(string $nomOrigine): bool
    {
        return in_array(ApercuDocument::genre($nomOrigine), ['document', 'brut'], true);
    }

    /** Le PDF, prêt à envoyer. */
    public static function fabriquer(string $chemin, string $nomOrigine): string
    {
        return (new self($chemin, $nomOrigine))->produire();
    }

    private function produire(): string
    {
        $this->nouvellePage();

        if (ApercuDocument::genre($this->nom) === 'brut') {
            $texte = ApercuDocument::texteBrut($this->chemin)['texte'];
            foreach (preg_split('/\r\n|\r|\n/', (string) $texte) ?: [] as $ligne) {
                $this->ligneBrute($ligne);
            }
        } else {
            foreach ($this->blocsDuDocument() as $bloc) {
                $this->bloc($bloc);
            }
        }

        $this->numeroterPages();

        return $this->pdf->sortie((string) pathinfo($this->nom, PATHINFO_FILENAME));
    }

    /** Les numéros de page, une fois le compte connu. */
    private function numeroterPages(): void
    {
        $total = $this->pdf->nombreDePages();
        for ($i = 0; $i < $total; $i++) {
            $this->pdf->allerALaPage($i);
            $mention = PdfSimple::encoder('Page ' . ($i + 1) . ' / ' . $total);
            $largeur = $this->pdf->largeur($mention, 'F1', 8.5);
            $this->pdf->texte((PdfSimple::LARGEUR - $largeur) / 2, self::MARGE / 2, $mention, 'F1', 8.5, [0.45, 0.45, 0.5]);
        }
    }

    /* --- Le contenu d'un cours ------------------------------------------------ */

    /**
     * Le contenu écrit d'un cours, en PDF : le titre du cours, sa matière, puis
     * le texte — et, si on l'a demandé dans l'éditeur, le sommaire avec la page
     * de chaque titre.
     *
     * La page d'un titre ne se connaît qu'une fois tout mis en pages, et le
     * sommaire prend lui-même de la place : on met donc en pages deux fois, la
     * première avec un sommaire aux numéros provisoires, de même hauteur.
     *
     * @param array{titre: string, contenu: ?string, matiere_nom?: ?string} $cours
     */
    public static function depuisCours(array $cours): string
    {
        [$blocs, $profondeur] = self::blocsDuTexteRiche($cours['contenu'] ?? null);

        return self::publier(
            ['titre' => (string) $cours['titre'], 'sous_titre' => trim((string) ($cours['matiere_nom'] ?? ''))],
            $blocs, $profondeur, 'Ce cours n’a pas encore de contenu écrit.', (string) $cours['titre']
        );
    }

    /**
     * Le journal des missions de l'alternance, semaine après semaine : c'est
     * ce qu'on recopie dans le livret d'apprentissage, ou qu'on joint au
     * rapport. Le récapitulatif des compétences vient à la fin, du plus
     * souvent travaillé au moins souvent : il dit d'un coup d'œil ce qu'on a
     * vu — et ce qu'on n'a pas encore vu.
     *
     * @param list<array{titre: string, sous_titre?: string, missions: ?string, competences: list<string>}> $semaines
     */
    public static function depuisJournal(array $semaines, string $sousTitre): string
    {
        $e = static fn (string $texte): string => htmlspecialchars($texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $gris = static fn (string $texte): string => '<span data-couleur="6b7280">' . $texte . '</span>';

        $blocs = [];
        $combien = [];
        foreach ($semaines as $semaine) {
            $blocs[] = ['html' => $e($semaine['titre']), 'titre' => 2];
            if (trim((string) ($semaine['sous_titre'] ?? '')) !== '') {
                $blocs[] = ['html' => $gris($e((string) $semaine['sous_titre']))];
            }

            [$texte] = self::blocsDuTexteRiche($semaine['missions']);
            if ($texte === []) {
                $texte = [['html' => '<i>' . $gris('Aucune mission notée cette semaine.') . '</i>']];
            }
            array_push($blocs, ...$texte);

            if ($semaine['competences'] !== []) {
                $blocs[] = ['html' => $gris('Compétences : ') . $e(implode(', ', $semaine['competences']))];
            }
            foreach ($semaine['competences'] as $competence) {
                $cle = mb_strtolower($competence);
                $combien[$cle] = ['nom' => $combien[$cle]['nom'] ?? $competence, 'n' => ($combien[$cle]['n'] ?? 0) + 1];
            }
        }

        if (count($semaines) > 1 && $combien !== []) {
            uasort($combien, static fn (array $a, array $b): int => [$b['n'], mb_strtolower($a['nom'])] <=> [$a['n'], mb_strtolower($b['nom'])]);
            $blocs[] = ['html' => 'Compétences travaillées', 'titre' => 2];
            foreach ($combien as $c) {
                $blocs[] = ['html' => $e($c['nom']) . ' ' . $gris('— ' . $c['n'] . ' semaine' . ($c['n'] > 1 ? 's' : '')),
                            'liste' => 'puce'];
            }
        }

        return self::publier(
            ['titre' => 'Journal des missions', 'sous_titre' => $sousTitre],
            // Un sommaire dès que le journal compte assez de semaines pour
            // qu'on y cherche la sienne.
            $blocs, count($semaines) >= 3 ? 2 : 0, 'Le journal est vide.', 'Journal des missions'
        );
    }

    /**
     * La fiche de révision d'un cours, en PDF : ce qu'il faut retenir, puis ce
     * qui lui est rattaché — fichiers, liens, autres cours, évènements —, en
     * listes, comme sur la fiche.
     *
     * @param array{titre: string, fiche_revision: ?string, matiere_nom?: ?string} $cours
     * @param list<array> $fichiers les fichiers de la fiche
     * @param list<array> $elements les liens, cours et évènements rattachés
     */
    public static function depuisFiche(array $cours, array $fichiers, array $elements): string
    {
        [$blocs, $profondeur] = self::blocsDuTexteRiche($cours['fiche_revision'] ?? null);
        $e = static fn (string $texte): string => htmlspecialchars($texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $gris = static fn (string $texte): string => '<span data-couleur="6b7280">' . $texte . '</span>';

        $rayons = [];
        $rayon = static function (string $titre, array $lignes) use (&$rayons): void {
            if ($lignes === []) {
                return;
            }
            $rayons[] = ['html' => $titre, 'titre' => 3];
            foreach ($lignes as $ligne) {
                $rayons[] = ['html' => $ligne, 'liste' => 'puce'];
            }
        };

        $rayon('Fichiers et images', array_map(static fn (array $f): string =>
            $e((string) $f['nom_origine']) . ' ' . $gris('(' . $e(taille_lisible((int) $f['taille'])) . ')'), $fichiers));

        $parType = ['lien' => [], 'cours' => [], 'evenement' => []];
        foreach ($elements as $element) {
            $parType[$element['type']][] = $element;
        }
        $rayon('Liens', array_map(static fn (array $l): string =>
            '<b>' . $e((string) ($l['libelle'] ?: $l['url'])) . '</b> '
            . '<span data-couleur="2563eb">' . $e((string) $l['url']) . '</span>', $parType['lien']));
        $rayon('Autres cours', array_map(static fn (array $c): string =>
            $e((string) $c['cours_titre']) . ((string) ($c['libelle'] ?? '') !== '' ? ' ' . $gris('— ' . $e((string) $c['libelle'])) : ''),
            $parType['cours']));
        $rayon('Au calendrier', array_map(static fn (array $v): string =>
            $e((string) $v['evenement_titre']) . ' '
            . $gris('— ' . $e(date_fr((string) $v['evenement_debut'], (int) $v['journee_entiere'] === 0))
                . ((int) $v['termine'] === 1 ? ' · terminé' : '')),
            $parType['evenement']));

        if ($rayons !== []) {
            $blocs[] = ['html' => 'Éléments rattachés', 'titre' => 2];
            array_push($blocs, ...$rayons);
        }

        $matiere = trim((string) ($cours['matiere_nom'] ?? ''));

        return self::publier(
            ['titre' => (string) $cours['titre'], 'sous_titre' => 'Fiche de révision' . ($matiere === '' ? '' : ' · ' . $matiere)],
            $blocs, $profondeur, 'Cette fiche de révision est vide.', 'Fiche — ' . $cours['titre']
        );
    }

    /**
     * Mettre en pages un texte sous son en-tête, sommaire compris, et rendre le PDF.
     *
     * @param array{titre: string, sous_titre: string} $entete
     * @param list<array> $blocs
     */
    private static function publier(array $entete, array $blocs, int $profondeur, string $vide, string $nomDocument): string
    {
        $plan = [];
        foreach ($blocs as $rang => $bloc) {
            $niveau = (int) ($bloc['titre'] ?? 0);
            if ($niveau > 0) {
                $plan[] = ['rang' => $rang, 'niveau' => $niveau,
                           'texte' => trim(html_entity_decode(strip_tags((string) $bloc['html']), ENT_QUOTES | ENT_HTML5, 'UTF-8'))];
            }
        }

        $essai = new self();
        $essai->mettreEnPagesLeTexte($entete, $blocs, $plan, $profondeur, null, $vide);

        $final = new self();
        $final->mettreEnPagesLeTexte($entete, $blocs, $plan, $profondeur, $essai->pagesDesTitres, $vide);

        return $final->pdf->sortie($nomDocument);
    }

    /**
     * @param list<array> $blocs
     * @param list<array{rang: int, niveau: int, texte: string}> $plan
     * @param ?list<int> $pages la page de chaque titre, connue au second passage
     */
    private function mettreEnPagesLeTexte(array $entete, array $blocs, array $plan, int $profondeur, ?array $pages, string $vide): void
    {
        $this->nouvellePage();

        // L'en-tête : le titre du cours, et ce qui le situe.
        $this->bloc(['html' => htmlspecialchars($entete['titre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                     'titre' => 1, 'taille_titre' => 22.0]);
        if ($entete['sous_titre'] !== '') {
            $this->bloc(['html' => '<span data-couleur="6b7280">' . htmlspecialchars($entete['sous_titre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>']);
        }
        $this->y -= 6;

        // Le sommaire, s'il a été demandé et s'il y a des titres à y mettre.
        $entrees = [];
        foreach ($plan as $numero => $titre) {
            if ($titre['niveau'] <= $profondeur && $titre['texte'] !== '') {
                $entrees[] = $titre + ['page' => $pages[$numero] ?? null];
            }
        }
        if ($entrees !== []) {
            $this->sommaire($entrees);
        }

        if ($blocs === []) {
            $this->bloc(['html' => '<i><span data-couleur="6b7280">' . htmlspecialchars($vide, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span></i>']);
        }
        foreach ($blocs as $bloc) {
            $this->bloc($bloc);
        }

        $this->numeroterPages();
    }

    /**
     * Le sommaire : une ligne par titre, décalée selon son niveau, la page au
     * bout. Une ligne trop longue est raccourcie : elle doit tenir sur une
     * ligne, pour que les deux passages prennent la même place.
     *
     * @param list<array{niveau: int, texte: string, page: ?int}> $entrees
     */
    private function sommaire(array $entrees): void
    {
        // « taille_titre » : un titre de mise en page, qui n'entre pas lui-même au sommaire.
        $this->bloc(['html' => 'Sommaire', 'titre' => 3, 'taille_titre' => 13.0]);
        $taille = 10.5;
        $largeurTotale = PdfSimple::LARGEUR - 2 * self::MARGE;

        foreach ($entrees as $entree) {
            $retrait = ($entree['niveau'] - 1) * self::RETRAIT;
            $police = $entree['niveau'] === 1 ? 'F2' : 'F1';
            $page = PdfSimple::encoder((string) ($entree['page'] ?? '00'));
            $largeurPage = $this->pdf->largeur($page, 'F1', $taille);
            $place = $largeurTotale - $retrait - $largeurPage - 18;

            $texte = PdfSimple::encoder($entree['texte']);
            if ($this->pdf->largeur($texte, $police, $taille) > $place) {
                while ($texte !== '' && $this->pdf->largeur($texte . "\x85", $police, $taille) > $place) {
                    $texte = substr($texte, 0, -1);
                }
                $texte = rtrim($texte) . "\x85";
            }

            $this->place($taille * 1.55);
            $base = $this->y - $taille;
            $x = self::MARGE + $retrait;
            $this->pdf->texte($x, $base, $texte, $police, $taille);

            // Des points de conduite, jusqu'au numéro.
            $debut = $x + $this->pdf->largeur($texte, $police, $taille) + 4;
            $fin = self::MARGE + $largeurTotale - $largeurPage - 4;
            $point = $this->pdf->largeur('. ', 'F1', $taille);
            $points = (int) max(0, floor(($fin - $debut) / $point));
            if ($points > 0 && $entree['page'] !== null) {
                $this->pdf->texte($fin - $points * $point, $base, str_repeat('. ', $points), 'F1', $taille, [0.6, 0.6, 0.65]);
            }
            if ($entree['page'] !== null) {
                $this->pdf->texte(self::MARGE + $largeurTotale - $largeurPage, $base, $page, 'F1', $taille);
            }
            $this->y -= $taille * 1.55;
        }
        $this->y -= 10;
    }

    /**
     * Le contenu d'un cours découpé en paragraphes que la mise en pages connaît.
     *
     * Le HTML est celui que TexteRiche garde — nettoyé, aux balises connues :
     * les blocs (div, titres, retraits, listes) deviennent des paragraphes, le
     * reste (gras, couleurs, images, sauts de ligne) passe tel quel à l'intérieur.
     * Un texte d'avant, brut, donne un paragraphe par ligne.
     *
     * @return array{0: list<array>, 1: int} les paragraphes, et la profondeur du sommaire
     */
    private static function blocsDuTexteRiche(?string $contenu): array
    {
        $contenu = (string) $contenu;
        if (trim($contenu) === '') {
            return [[], 0];
        }
        if (!TexteRiche::estRiche($contenu)) {
            $blocs = [];
            foreach (preg_split('/\r\n|\r|\n/', trim($contenu)) ?: [] as $ligne) {
                $blocs[] = ['html' => htmlspecialchars($ligne, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')];
            }
            return [$blocs, 0];
        }

        $propre = substr(TexteRiche::pourEditeur($contenu), strlen(TexteRiche::MARQUE));
        $profondeur = 0;
        if (preg_match('/^<!--sommaire:([1-3])-->/', $propre, $m) === 1) {
            $profondeur = (int) $m[1];
            $propre = substr($propre, strlen($m[0]));
        }

        $doc = new DOMDocument();
        $avant = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $propre . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($avant);

        $blocs = [];
        foreach ($doc->childNodes as $racine) {
            if ($racine instanceof DOMElement) {
                self::blocsDe($doc, $racine, null, 0, $blocs);
            }
        }

        return [$blocs, $profondeur];
    }

    /** @param list<array> $blocs */
    private static function blocsDe(DOMDocument $doc, DOMNode $parent, ?string $alignement, int $retrait, array &$blocs): void
    {
        $enCours = '';
        $vider = static function () use (&$enCours, &$blocs, $alignement, $retrait): void {
            if (trim(strip_tags($enCours)) !== '' || str_contains($enCours, '<img')) {
                $blocs[] = ['html' => $enCours, 'alignement' => $alignement, 'retrait' => $retrait];
            }
            $enCours = '';
        };

        foreach ($parent->childNodes as $enfant) {
            if ($enfant instanceof DOMText) {
                $enCours .= htmlspecialchars((string) $enfant->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                continue;
            }
            if (!$enfant instanceof DOMElement) {
                continue;
            }
            $nom = strtolower($enfant->nodeName);
            $sien = self::alignementDe($enfant) ?? $alignement;

            if ($nom === 'div') {
                $vider();
                self::blocsDe($doc, $enfant, $sien, $retrait, $blocs);
            } elseif ($nom === 'blockquote') {
                $vider();
                self::blocsDe($doc, $enfant, $sien, $retrait + 1, $blocs);
            } elseif (in_array($nom, ['h2', 'h3', 'h4'], true)) {
                $vider();
                $blocs[] = ['html' => self::interieur($doc, $enfant), 'titre' => (int) substr($nom, 1) - 1,
                            'alignement' => $sien, 'retrait' => $retrait];
            } elseif ($nom === 'ul' || $nom === 'ol') {
                $vider();
                self::liste($doc, $enfant, 0, $sien, $retrait, $blocs);
            } else {
                $enCours .= $doc->saveHTML($enfant);
            }
        }
        $vider();
    }

    /** Une liste : un paragraphe par élément, les sous-listes un niveau plus bas. */
    private static function liste(DOMDocument $doc, DOMElement $liste, int $niveau, ?string $alignement, int $retrait, array &$blocs): void
    {
        $sorte = strtolower($liste->nodeName) === 'ol' ? 'numero' : 'puce';
        $numero = 0;
        foreach ($liste->childNodes as $element) {
            if (!$element instanceof DOMElement || strtolower($element->nodeName) !== 'li') {
                continue;
            }
            $numero++;
            $texte = '';
            $sousListes = [];
            foreach ($element->childNodes as $enfant) {
                if ($enfant instanceof DOMElement && in_array(strtolower($enfant->nodeName), ['ul', 'ol'], true)) {
                    $sousListes[] = $enfant;
                } else {
                    $texte .= $enfant instanceof DOMText
                        ? htmlspecialchars((string) $enfant->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                        : $doc->saveHTML($enfant);
                }
            }
            $blocs[] = ['html' => $texte, 'liste' => $sorte, 'numero' => $numero, 'niveau' => min($niveau, 4),
                        'alignement' => self::alignementDe($element) ?? $alignement, 'retrait' => $retrait];
            foreach ($sousListes as $sous) {
                self::liste($doc, $sous, $niveau + 1, $alignement, $retrait, $blocs);
            }
        }
    }

    private static function interieur(DOMDocument $doc, DOMElement $element): string
    {
        $html = '';
        foreach ($element->childNodes as $enfant) {
            $html .= $doc->saveHTML($enfant);
        }

        return $html;
    }

    private static function alignementDe(DOMElement $element): ?string
    {
        if (preg_match('/text-align:\s*(left|center|right|justify)/i', $element->getAttribute('style'), $m) !== 1) {
            return null;
        }

        return ['left' => 'gauche', 'center' => 'centre', 'right' => 'droite', 'justify' => 'justifie'][strtolower($m[1])];
    }

    /**
     * Les paragraphes, comme l'aperçu les lit : mis en forme quand la lecture
     * riche aboutit et tombe d'accord avec la lecture nue, en texte nu sinon.
     *
     * @return list<array>
     */
    private function blocsDuDocument(): array
    {
        $nus = ApercuDocument::paragraphes($this->chemin, $this->nom);
        $riches = [];
        if (EditionDocument::modifiable($this->nom)) {
            try {
                $riches = EditionDocument::apercuRiche($this->chemin, $this->nom,
                    static fn (int $rang): string => 'mescours-image:' . $rang);
            } catch (Throwable) {
                $riches = [];
            }
            $textuels = count(array_filter($riches,
                static fn (array $e): bool => (bool) ($e['textuel'] ?? (($e['html'] ?? '') !== ''))));
            if ($textuels !== count($nus)) {
                $riches = [];
            }
        }

        if ($riches !== []) {
            return array_values(array_filter($riches, static fn (array $b): bool => !($b['sommaire'] ?? false)));
        }

        return array_map(static fn (string $texte): array => [
            'html' => htmlspecialchars($texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'alignement' => null, 'liste' => '', 'numero' => null, 'niveau' => 0, 'titre' => 0, 'images' => [],
        ], $nus);
    }

    /* --- Un paragraphe ------------------------------------------------------ */

    private function bloc(array $bloc): void
    {
        $titre = min((int) ($bloc['titre'] ?? 0), 3);
        $liste = $titre > 0 ? '' : (string) ($bloc['liste'] ?? '');
        $niveau = (int) ($bloc['niveau'] ?? 0);
        // Un retrait (« blockquote » du contenu d'un cours) décale tout le paragraphe.
        $gauche = (int) ($bloc['retrait'] ?? 0) * self::RETRAIT * 1.5
            + ($liste === '' ? 0.0 : ($niveau + 1) * self::RETRAIT);
        $disponible = PdfSimple::LARGEUR - 2 * self::MARGE - $gauche;

        $jetons = $this->jetons((string) ($bloc['html'] ?? ''), $titre, $bloc['taille_titre'] ?? null);
        foreach ($bloc['images'] ?? [] as $image) {
            $jeton = $this->jetonImage((int) $image['rang'], isset($image['largeur']) ? (int) $image['largeur'] : null, 'centre');
            if ($jeton !== null) {
                $jetons[] = $jeton;
            }
        }
        if ($jetons === []) {
            // Un titre vide garde sa place dans le compte des titres du sommaire.
            if ($titre > 0 && !isset($bloc['taille_titre'])) {
                $this->pagesDesTitres[] = $this->pdf->nombreDePages();
            }
            $this->y -= self::TAILLE * 0.6;
            return;
        }

        if ($titre > 0) {
            $this->y -= 8;
        }

        $premiere = true;
        foreach ($this->lignes($jetons, $disponible) as $ligne) {
            if (isset($ligne['image'])) {
                $this->poserImage($ligne['image'], $gauche, $disponible, (string) ($bloc['alignement'] ?? ''));
                $premiere = false;
                continue;
            }

            // La ligne prend la hauteur de son plus grand caractère ; vide, celle du texte.
            $taille = 0.0;
            foreach ($ligne['jetons'] as $j) {
                $taille = max($taille, $j['taille']);
            }
            $taille = $taille > 0 ? $taille : self::TAILLE;
            $hauteur = $taille * 1.32;
            $this->place($hauteur);
            $base = $this->y - $taille * 1.0;

            // La page d'un titre, pour le sommaire : celle de sa première ligne.
            if ($premiere && $titre > 0 && !isset($bloc['taille_titre'])) {
                $this->pagesDesTitres[] = $this->pdf->nombreDePages();
            }
            if ($premiere && $liste !== '') {
                $this->marqueDeListe($liste, $niveau, $bloc['numero'] ?? null, $gauche, $base, $ligne['jetons'][0]['taille'] ?? self::TAILLE);
            }
            $this->ecrireLigne($ligne, $gauche, $disponible, (string) ($bloc['alignement'] ?? ''), $base);

            $this->y -= $hauteur;
            $premiere = false;
        }

        $this->y -= $titre > 0 ? 4 : ($liste !== '' ? 2 : 6);
    }

    /**
     * Le HTML d'un paragraphe — celui que l'application fabrique pour l'aperçu,
     * aux balises connues — découpé en mots portant leur style.
     *
     * @return list<array>
     */
    private function jetons(string $html, int $titre, ?float $tailleTitre = null): array
    {
        if (trim($html) === '') {
            return [];
        }
        $doc = new DOMDocument();
        $avant = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($avant);

        $jetons = [];
        $style = [
            'gras' => $titre > 0, 'italique' => false, 'souligne' => false,
            'taille' => $titre > 0 ? ($tailleTitre ?? self::TITRES[$titre]) : self::TAILLE,
            'couleur' => [0.0, 0.0, 0.0], 'fond' => null,
        ];
        foreach ($doc->childNodes as $racine) {
            if ($racine instanceof DOMElement) {
                $this->parcourir($racine, $style, $jetons);
            }
        }

        return $jetons;
    }

    private function parcourir(DOMNode $noeud, array $style, array &$jetons): void
    {
        foreach ($noeud->childNodes as $enfant) {
            if ($enfant instanceof DOMText) {
                $this->mots((string) $enfant->nodeValue, $style, $jetons);
                continue;
            }
            if (!$enfant instanceof DOMElement) {
                continue;
            }

            $sien = $style;
            switch (strtolower($enfant->nodeName)) {
                case 'br':
                    $jetons[] = ['sorte' => 'saut'];
                    continue 2;
                case 'img':
                    $source = $enfant->getAttribute('src');
                    if (preg_match('/^mescours-image:(\d+)$/', $source, $m) === 1) {
                        $largeur = $enfant->getAttribute('data-largeur');
                        $jeton = $this->jetonImage((int) $m[1], $largeur === '' ? null : (int) $largeur,
                            $enfant->getAttribute('data-habillage'));
                        if ($jeton !== null) {
                            $jetons[] = $jeton;
                        }
                    } elseif (preg_match('~^data:image/(?:png|jpeg|gif|webp);base64,([A-Za-z0-9+/]+={0,2})$~', $source, $m) === 1) {
                        // Une image du contenu d'un cours, embarquée dans le texte.
                        $jeton = $this->jetonImageEmbarquee($m[1], $enfant->getAttribute('style'));
                        if ($jeton !== null) {
                            $jetons[] = $jeton;
                        }
                    }
                    continue 2;
                case 'b': case 'strong':
                    $sien['gras'] = true;
                    break;
                case 'i': case 'em':
                    $sien['italique'] = true;
                    break;
                case 'u':
                    $sien['souligne'] = true;
                    break;
                case 'span':
                    if (preg_match('/^\d{1,3}(\.\d+)?$/', $enfant->getAttribute('data-taille')) === 1) {
                        $sien['taille'] = max(4.0, min(96.0, (float) $enfant->getAttribute('data-taille')));
                    }
                    $couleur = self::rgb($enfant->getAttribute('data-couleur'));
                    if ($couleur !== null) {
                        $sien['couleur'] = $couleur;
                    }
                    $fond = self::rgb($enfant->getAttribute('data-fond'));
                    if ($fond !== null) {
                        $sien['fond'] = $fond;
                    }
                    // Le contenu d'un cours écrit ses styles en CSS, déjà vérifiés par TexteRiche.
                    $css = $enfant->getAttribute('style');
                    if (preg_match('/font-size:\s*(\d{1,2})pt/i', $css, $m) === 1) {
                        $sien['taille'] = max(4.0, min(96.0, (float) $m[1]));
                    }
                    if (preg_match('/(?<![-\w])color:\s*([^;]+)/i', $css, $m) === 1 && ($couleur = self::rgb($m[1])) !== null) {
                        $sien['couleur'] = $couleur;
                    }
                    if (preg_match('/background-color:\s*([^;]+)/i', $css, $m) === 1) {
                        $sien['fond'] = self::rgb($m[1]);
                    }
                    break;
                // Les figures « impossibles » de l'aperçu ne portent que des explications.
                case 'figure': case 'figcaption':
                    continue 2;
            }
            $this->parcourir($enfant, $sien, $jetons);
        }
    }

    /** Un texte coupé en mots, chacun avec l'espace qui le suit. */
    private function mots(string $texte, array $style, array &$jetons): void
    {
        $police = $style['gras'] ? ($style['italique'] ? 'F4' : 'F2') : ($style['italique'] ? 'F3' : 'F1');
        preg_match_all('/[^\s]+|\n|[^\S\n]+/u', $texte, $morceaux);

        foreach ($morceaux[0] as $morceau) {
            if ($morceau === "\n") {
                $jetons[] = ['sorte' => 'saut'];
                continue;
            }
            if (trim($morceau) === '') {
                // L'espace appartient au mot d'avant, s'il y en a un.
                $dernier = count($jetons) - 1;
                if ($dernier >= 0 && $jetons[$dernier]['sorte'] === 'mot') {
                    $jetons[$dernier]['espace'] = true;
                }
                continue;
            }
            $code = PdfSimple::encoder($morceau);
            if ($code === '') {
                continue;
            }
            $jetons[] = [
                'sorte' => 'mot', 'code' => $code, 'police' => $police, 'taille' => $style['taille'],
                'couleur' => $style['couleur'], 'fond' => $style['fond'], 'souligne' => $style['souligne'],
                'espace' => false,
                'largeur' => $this->pdf->largeur($code, $police, $style['taille']),
                'largeurEspace' => $this->pdf->largeur(' ', $police, $style['taille']),
            ];
        }
    }

    /**
     * Les jetons rangés en lignes qui tiennent dans la largeur. Une image a sa
     * ligne à elle ; un mot plus large que la ligne se coupe.
     *
     * @return list<array>
     */
    private function lignes(array $jetons, float $largeur): array
    {
        $lignes = [];
        $ligne = [];
        $x = 0.0;
        $fermer = static function (bool $fin) use (&$lignes, &$ligne, &$x): void {
            $lignes[] = ['jetons' => $ligne, 'fin' => $fin];
            $ligne = [];
            $x = 0.0;
        };

        foreach ($jetons as $jeton) {
            if ($jeton['sorte'] === 'saut') {
                $fermer(true);
                continue;
            }
            if ($jeton['sorte'] === 'image') {
                if ($ligne !== []) {
                    $fermer(true);
                }
                $lignes[] = ['image' => $jeton];
                continue;
            }
            foreach ($this->couperSiTropLong($jeton, $largeur) as $mot) {
                $precedent = $ligne === [] ? null : $ligne[count($ligne) - 1];
                $ecart = $precedent !== null && $precedent['espace'] ? $precedent['largeurEspace'] : 0.0;
                if ($ligne !== [] && $x + $ecart + $mot['largeur'] > $largeur + 0.01) {
                    $fermer(false);
                    $ecart = 0.0;
                }
                $x += $ecart + $mot['largeur'];
                $ligne[] = $mot;
            }
        }
        if ($ligne !== []) {
            $fermer(true);
        }

        return $lignes;
    }

    /** @return list<array> */
    private function couperSiTropLong(array $mot, float $largeur): array
    {
        if ($mot['largeur'] <= $largeur) {
            return [$mot];
        }
        $morceaux = [];
        $courant = '';
        $n = strlen($mot['code']);
        for ($i = 0; $i < $n; $i++) {
            $essai = $courant . $mot['code'][$i];
            if ($courant !== '' && $this->pdf->largeur($essai, $mot['police'], $mot['taille']) > $largeur) {
                $morceaux[] = ['code' => $courant, 'espace' => false] + $mot;
                $courant = $mot['code'][$i];
            } else {
                $courant = $essai;
            }
        }
        $morceaux[] = ['code' => $courant] + $mot;

        return array_map(fn (array $m): array =>
            ['largeur' => $this->pdf->largeur($m['code'], $m['police'], $m['taille'])] + $m, $morceaux);
    }

    private function ecrireLigne(array $ligne, float $gauche, float $disponible, string $alignement, float $base): void
    {
        $jetons = $ligne['jetons'];
        $dernier = count($jetons) - 1;
        $contenu = 0.0;
        $ecarts = 0;
        foreach ($jetons as $i => $j) {
            $contenu += $j['largeur'];
            if ($i < $dernier && $j['espace']) {
                $contenu += $j['largeurEspace'];
                $ecarts++;
            }
        }

        $x = self::MARGE + $gauche;
        $supplement = 0.0;
        $reste = max(0.0, $disponible - $contenu);
        if ($alignement === 'centre') {
            $x += $reste / 2;
        } elseif ($alignement === 'droite') {
            $x += $reste;
        } elseif ($alignement === 'justifie' && !$ligne['fin'] && $ecarts > 0) {
            $supplement = $reste / $ecarts;
        }

        // Les fonds d'abord, pour que le texte passe par-dessus.
        $positions = [];
        $curseur = $x;
        foreach ($jetons as $i => $j) {
            $avance = $j['largeur'] + ($i < $dernier && $j['espace'] ? $j['largeurEspace'] + $supplement : 0.0);
            $positions[$i] = [$curseur, $avance];
            if ($j['fond'] !== null) {
                $this->pdf->rectangle($curseur, $base - $j['taille'] * 0.25, $avance, $j['taille'] * 1.18, $j['fond']);
            }
            $curseur += $avance;
        }
        foreach ($jetons as $i => $j) {
            [$xj, $avance] = $positions[$i];
            $this->pdf->texte($xj, $base, $j['code'], $j['police'], $j['taille'], $j['couleur']);
            if ($j['souligne']) {
                // Le soulignement court sous l'espace qui suit, si le mot d'après l'est aussi.
                $suite = $i < $dernier && $jetons[$i + 1]['souligne'] ? $avance : $j['largeur'];
                $this->pdf->rectangle($xj, $base - $j['taille'] * 0.14, $suite, max(0.5, $j['taille'] * 0.055), $j['couleur']);
            }
        }
    }

    private function marqueDeListe(string $liste, int $niveau, mixed $numero, float $gauche, float $base, float $taille): void
    {
        $marque = $liste === 'numero' && $numero !== null
            ? (string) $numero . '.'
            : ($niveau % 2 === 0 ? "\x95" : "\x96");
        $largeur = $this->pdf->largeur($marque, 'F1', $taille);
        $this->pdf->texte(self::MARGE + $gauche - 6 - $largeur, $base, $marque, 'F1', $taille);
    }

    /* --- Les images ---------------------------------------------------------- */

    /** Une image du document, prête à poser, ou rien si elle ne se lit pas. */
    private function jetonImage(int $rang, ?int $largeurPx, string $habillage): ?array
    {
        if (!array_key_exists($rang, $this->images)) {
            $this->images[$rang] = $this->preparerImage($rang);
        }
        $image = $this->images[$rang];
        if ($image === null) {
            return null;
        }
        [$numero, $px, $py] = $image;
        $largeur = ($largeurPx !== null && $largeurPx > 0 ? $largeurPx : $px) * self::PX_EN_PT;

        return [
            'sorte' => 'image', 'numero' => $numero,
            'largeur' => $largeur, 'hauteur' => $largeur * $py / max(1, $px),
            'habillage' => $habillage,
        ];
    }

    /**
     * Une image embarquée dans le contenu d'un cours : sa largeur en part de la
     * ligne, et sa place — à gauche, à droite, centrée ou dans la ligne.
     */
    private function jetonImageEmbarquee(string $base64, string $style): ?array
    {
        $cle = 'embarquee:' . md5($base64);
        if (!array_key_exists($cle, $this->images)) {
            $octets = base64_decode($base64, true);
            $this->images[$cle] = $octets === false ? null : $this->imageDepuisOctets($octets);
        }
        $image = $this->images[$cle];
        if ($image === null) {
            return null;
        }
        [$numero, $px, $py] = $image;
        $part = preg_match('/width:\s*(\d{1,3})%/', $style, $m) === 1 ? max(3, min(100, (int) $m[1])) : null;
        $habillage = match (true) {
            (bool) preg_match('/float:\s*left/', $style)    => 'gauche',
            (bool) preg_match('/float:\s*right/', $style)   => 'droite',
            (bool) preg_match('/display:\s*block/', $style) => 'centre',
            default                                         => '',
        };
        $largeur = $px * self::PX_EN_PT;

        return [
            'sorte' => 'image', 'numero' => $numero, 'part' => $part,
            'largeur' => $largeur, 'hauteur' => $largeur * $py / max(1, $px),
            'habillage' => $habillage,
        ];
    }

    /** @return ?array{0: int, 1: int, 2: int} le numéro dans le PDF, et la taille en pixels */
    private function preparerImage(int $rang): ?array
    {
        $trouvee = ImagesDocument::octets($this->chemin, $this->nom, $rang);

        return $trouvee === null ? null : $this->imageDepuisOctets($trouvee['octets']);
    }

    /** @return ?array{0: int, 1: int, 2: int} */
    private function imageDepuisOctets(string $octets): ?array
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $source = @imagecreatefromstring($octets);
        if ($source === false) {
            // Un format que GD ne lit pas (EMF, WMF…) : l'aperçu ne le montre pas non plus.
            return null;
        }

        $px = imagesx($source);
        $py = imagesy($source);
        $echelle = min(1, self::IMAGE_PX_MAX / max($px, $py));
        $lx = max(1, (int) round($px * $echelle));
        $ly = max(1, (int) round($py * $echelle));

        // Sur fond blanc : un JPEG n'a pas de transparence.
        $toile = imagecreatetruecolor($lx, $ly);
        imagefill($toile, 0, 0, (int) imagecolorallocate($toile, 255, 255, 255));
        imagecopyresampled($toile, $source, 0, 0, 0, 0, $lx, $ly, $px, $py);

        ob_start();
        imagejpeg($toile, null, 88);
        $jpeg = (string) ob_get_clean();

        return [$this->pdf->ajouterImage($jpeg, $lx, $ly), $px, $py];
    }

    private function poserImage(array $image, float $gauche, float $disponible, string $alignement): void
    {
        // Une largeur donnée en part de la ligne (contenu d'un cours) se rapporte à la place qu'on a.
        $largeur = isset($image['part']) && $image['part'] !== null
            ? $disponible * $image['part'] / 100
            : min($image['largeur'], $disponible);
        $hauteur = $image['hauteur'] * $largeur / max(0.01, $image['largeur']);
        // Plus haute qu'une page : réduite pour tenir.
        $hauteurMax = PdfSimple::HAUTEUR - 2 * self::MARGE - self::PIED;
        if ($hauteur > $hauteurMax) {
            $largeur *= $hauteurMax / $hauteur;
            $hauteur = $hauteurMax;
        }

        $this->place($hauteur + 6);
        $cote = match ($image['habillage']) {
            'gauche' => 'gauche',
            'droite' => 'droite',
            'centre' => 'centre',
            default  => $alignement,
        };
        $x = self::MARGE + $gauche + match ($cote) {
            'centre' => ($disponible - $largeur) / 2,
            'droite' => $disponible - $largeur,
            default  => 0.0,
        };
        $this->pdf->poserImage($image['numero'], $x, $this->y - 3 - $hauteur, $largeur, $hauteur);
        $this->y -= $hauteur + 6;
    }

    /* --- Le texte brut ------------------------------------------------------- */

    private function ligneBrute(string $ligne): void
    {
        $taille = 9.5;
        $code = PdfSimple::encoder(rtrim($ligne));
        $parLigne = max(1, (int) floor((PdfSimple::LARGEUR - 2 * self::MARGE) / ($taille * 0.6)));
        // Coupée aux espaces quand c'est possible, au caractère sinon.
        $morceaux = $code === '' ? [''] : explode("\n", wordwrap($code, $parLigne, "\n", true));
        foreach ($morceaux as $morceau) {
            $this->place($taille * 1.35);
            $this->pdf->texte(self::MARGE, $this->y - $taille, $morceau, 'F5', $taille);
            $this->y -= $taille * 1.35;
        }
    }

    /* --- Les pages ----------------------------------------------------------- */

    /** Une nouvelle page si ce qui vient ne tient plus dans celle-ci. */
    private function place(float $hauteur): void
    {
        if ($this->y - $hauteur < self::MARGE + self::PIED) {
            $this->nouvellePage();
        }
    }

    private function nouvellePage(): void
    {
        $this->pdf->nouvellePage();
        $this->y = PdfSimple::HAUTEUR - self::MARGE;
    }

    /** @return ?array{0: float, 1: float, 2: float} */
    private static function rgb(string $hexa): ?array
    {
        $hexa = strtolower(trim($hexa));
        if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})/', $hexa, $m) === 1) {
            return [min(255, (int) $m[1]) / 255, min(255, (int) $m[2]) / 255, min(255, (int) $m[3]) / 255];
        }
        // « transparent » : pas de fond.
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $hexa, $m) === 1) {
            $hexa = $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
        }
        if (preg_match('/^#?([0-9a-f]{6})$/', $hexa, $m) !== 1) {
            return null;
        }
        $v = hexdec($m[1]);

        return [(($v >> 16) & 255) / 255, (($v >> 8) & 255) / 255, ($v & 255) / 255];
    }
}
