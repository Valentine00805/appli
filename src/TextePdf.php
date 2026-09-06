<?php
declare(strict_types=1);

/**
 * Le texte d'un PDF, quand il y en a.
 *
 * Un PDF ne range pas son texte comme un document : il décrit une page à
 * dessiner, morceau par morceau, chacun placé à ses coordonnées. On relit donc
 * les instructions de dessin, on garde ce qui affiche du texte, et on recolle
 * les morceaux : même hauteur, même ligne ; hauteur différente, ligne suivante.
 *
 * Deux écueils, tous deux traités ici :
 *   — les octets d'une chaîne ne sont pas de l'Unicode. Une police moderne
 *     emporte sa table « ToUnicode » qui dit comment les lire ; sans elle, on
 *     suppose du Windows-1252, ce qui convient aux PDF simples.
 *   — depuis PDF 1.5, les objets sont souvent rangés dans des flux compressés.
 *     Il faut les ouvrir avant de trouver quoi que ce soit.
 *
 * Un PDF scanné, lui, ne contient que des images : il n'en sortira rien, et
 * c'est à l'appelant de le dire honnêtement plutôt que d'inventer.
 */
final class TextePdf
{
    /** Au-delà, on renonce : un tel document ne se lit pas en mémoire. */
    private const TAILLE_MAX = 40 * 1024 * 1024;

    /** Bornes de sécurité : un manuel entier n'a pas à passer par là. */
    private const PAGES_MAX = 300;
    private const CARACTERES_MAX = 400000;

    /** En deçà, il n'y avait rien à lire : le document est sans doute scanné. */
    private const PLANCHER = 20;

    /**
     * Le texte du document, ou null si on n'a rien pu en tirer.
     *
     * Le null ne dit pas « document vide » mais « je n'ai pas su lire » : les
     * deux se ressemblent de l'extérieur, et l'appelant doit le dire ainsi.
     */
    public static function extraire(string $chemin): ?string
    {
        if (!is_file($chemin) || filesize($chemin) > self::TAILLE_MAX) {
            return null;
        }
        $brut = @file_get_contents($chemin);
        if ($brut === false || !str_starts_with($brut, '%PDF')) {
            return null;
        }

        $objets = self::objets($brut);
        if ($objets === []) {
            return null;
        }

        $morceaux = [];
        $pages = 0;

        foreach ($objets as $corps) {
            if (!preg_match('#/Type\s*/Page(?![a-zA-Z])#', $corps)) {
                continue;
            }
            if (++$pages > self::PAGES_MAX) {
                break;
            }

            $polices = self::policesDeLaPage($corps, $objets);
            foreach (self::contenusDeLaPage($corps, $objets) as $contenu) {
                $morceaux[] = self::lireContenu($contenu, $polices);
            }
            // Un « formulaire » est un morceau de page rangé à part : beaucoup
            // de mises en page y logent leur texte.
            foreach (self::formulairesDeLaPage($corps, $objets) as [$contenu, $sesPolices]) {
                $morceaux[] = self::lireContenu($contenu, $sesPolices + $polices);
            }

            if (array_sum(array_map('strlen', $morceaux)) > self::CARACTERES_MAX) {
                break;
            }
        }

        $texte = trim(implode("\n", array_filter($morceaux, static fn (string $m): bool => trim($m) !== '')));

        return mb_strlen($texte) >= self::PLANCHER ? $texte : null;
    }

    // --- Les objets du document ---------------------------------------------

    /**
     * Tous les objets du fichier, y compris ceux rangés dans des flux.
     *
     * @return array<int, string> numéro d'objet => son corps
     */
    private static function objets(string $brut): array
    {
        $objets = [];

        if (preg_match_all('/(\d+)\s+\d+\s+obj\b/', $brut, $trouves, PREG_OFFSET_CAPTURE)) {
            $nombre = count($trouves[0]);
            for ($i = 0; $i < $nombre; $i++) {
                $numero = (int) $trouves[1][$i][0];
                $debut = $trouves[0][$i][1] + strlen($trouves[0][$i][0]);
                // Jusqu'au prochain objet : « endobj » peut apparaître dans un flux binaire.
                $fin = $i + 1 < $nombre ? $trouves[0][$i + 1][1] : strlen($brut);
                $objets[$numero] = substr($brut, $debut, $fin - $debut);
            }
        }

        // Les objets rangés dans des flux d'objets rejoignent les autres.
        foreach ($objets as $corps) {
            if (str_contains($corps, '/ObjStm')) {
                foreach (self::ouvrirFluxDObjets($corps) as $numero => $contenu) {
                    $objets[$numero] ??= $contenu;
                }
            }
        }

        return $objets;
    }

    /**
     * Les objets contenus dans un « flux d'objets » (PDF 1.5 et suivants).
     *
     * @return array<int, string>
     */
    private static function ouvrirFluxDObjets(string $corps): array
    {
        $clair = self::flux($corps);
        if ($clair === null || !preg_match('#/N\s+(\d+)#', $corps, $n)
            || !preg_match('#/First\s+(\d+)#', $corps, $f)) {
            return [];
        }

        $entete = substr($clair, 0, (int) $f[1]);
        if (!preg_match_all('/(\d+)\s+(\d+)/', $entete, $paires, PREG_SET_ORDER)) {
            return [];
        }

        $objets = [];
        $paires = array_slice($paires, 0, (int) $n[1]);
        foreach ($paires as $rang => $paire) {
            $debut = (int) $f[1] + (int) $paire[2];
            $fin = isset($paires[$rang + 1])
                ? (int) $f[1] + (int) $paires[$rang + 1][2]
                : strlen($clair);
            $objets[(int) $paire[1]] = substr($clair, $debut, $fin - $debut);
        }

        return $objets;
    }

    /**
     * Le flux d'un objet, déballé de ses filtres, ou null.
     *
     * Un flux peut en traverser plusieurs, dans l'ordre où ils sont déclarés :
     * ReportLab écrit « /Filter [ /ASCII85Decode /FlateDecode ] », d'abord
     * encodé en texte imprimable, puis compressé. Un filtre qu'on ne sait pas
     * défaire — une image JPEG, par exemple — n'est pas du texte : on renonce.
     */
    private static function flux(string $corps): ?string
    {
        $debut = strpos($corps, 'stream');
        if ($debut === false) {
            return null;
        }
        $debut += strlen('stream');
        // Le flux commence après le saut de ligne qui suit le mot « stream ».
        if (str_starts_with(substr($corps, $debut, 2), "\r\n")) {
            $debut += 2;
        } elseif (in_array($corps[$debut] ?? '', ["\n", "\r"], true)) {
            $debut += 1;
        }

        $fin = strpos($corps, 'endstream', $debut);
        $donnees = substr($corps, $debut, ($fin === false ? strlen($corps) : $fin) - $debut);

        foreach (self::filtres($corps) as $filtre) {
            $donnees = match ($filtre) {
                'ASCII85Decode'  => self::ascii85($donnees),
                'ASCIIHexDecode' => self::asciiHexa($donnees),
                'FlateDecode'    => self::detasser($donnees),
                default          => null,
            };
            if ($donnees === null) {
                return null;
            }
        }

        return $donnees !== '' ? $donnees : null;
    }

    /**
     * Les filtres déclarés par un objet, dans l'ordre.
     *
     * @return list<string>
     */
    private static function filtres(string $corps): array
    {
        if (!preg_match('#/Filter\s*(\[[^\]]*\]|/[A-Za-z0-9]+)#', $corps, $m)) {
            return [];
        }

        return preg_match_all('#/([A-Za-z0-9]+)#', $m[1], $noms) ? $noms[1] : [];
    }

    /** Un flux compressé, rendu à sa taille. */
    private static function detasser(string $donnees): ?string
    {
        $clair = @gzuncompress($donnees);
        if (!is_string($clair)) {
            // Certains producteurs écrivent un flux brut, sans en-tête zlib.
            $clair = @gzinflate($donnees);
        }

        return is_string($clair) && $clair !== '' ? $clair : null;
    }

    /**
     * Le codage ASCII85, défait.
     *
     * Cinq caractères imprimables valent quatre octets ; « z » abrège quatre
     * zéros, et « ~> » ferme le flux. Le dernier groupe peut être incomplet :
     * on le complète par des « u » avant de le décoder, puis on coupe ce qu'on
     * a ajouté.
     */
    private static function ascii85(string $donnees): ?string
    {
        $fin = strpos($donnees, '~>');
        if ($fin !== false) {
            $donnees = substr($donnees, 0, $fin);
        }
        $donnees = preg_replace('/\s/', '', $donnees) ?? $donnees;
        if (str_starts_with($donnees, '<~')) {
            $donnees = substr($donnees, 2);
        }

        $sortie = '';
        $groupe = [];
        $longueur = strlen($donnees);

        for ($i = 0; $i < $longueur; $i++) {
            $c = $donnees[$i];

            if ($c === 'z' && $groupe === []) {
                $sortie .= "\0\0\0\0";
                continue;
            }
            $valeur = ord($c) - 33;
            if ($valeur < 0 || $valeur > 84) {
                return null;
            }
            $groupe[] = $valeur;

            if (count($groupe) === 5) {
                $sortie .= self::quatreOctets($groupe);
                $groupe = [];
            }
        }

        if ($groupe !== []) {
            $manque = 5 - count($groupe);
            $sortie .= substr(self::quatreOctets(array_pad($groupe, 5, 84)), 0, 4 - $manque);
        }

        return $sortie;
    }

    /** Les quatre octets que valent cinq chiffres ASCII85. */
    private static function quatreOctets(array $groupe): string
    {
        $nombre = 0;
        foreach ($groupe as $valeur) {
            $nombre = $nombre * 85 + $valeur;
        }

        return pack('N', $nombre & 0xFFFFFFFF);
    }

    /** Le codage hexadécimal, défait. */
    private static function asciiHexa(string $donnees): ?string
    {
        $fin = strpos($donnees, '>');
        if ($fin !== false) {
            $donnees = substr($donnees, 0, $fin);
        }
        $hexa = preg_replace('/[^0-9A-Fa-f]/', '', $donnees) ?? '';
        if (strlen($hexa) % 2 === 1) {
            $hexa .= '0';
        }
        $octets = @hex2bin($hexa);

        return is_string($octets) ? $octets : null;
    }

    // --- Une page ------------------------------------------------------------

    /**
     * Les flux de contenu d'une page, décompressés.
     *
     * @param array<int, string> $objets
     * @return list<string>
     */
    private static function contenusDeLaPage(string $page, array $objets): array
    {
        if (!preg_match('#/Contents\s*(\d+)\s+\d+\s+R|/Contents\s*\[([^\]]*)\]#', $page, $m)) {
            return [];
        }

        $numeros = [];
        if (($m[1] ?? '') !== '') {
            $numeros[] = (int) $m[1];
        } elseif (preg_match_all('/(\d+)\s+\d+\s+R/', $m[2] ?? '', $refs)) {
            $numeros = array_map('intval', $refs[1]);
        }

        $flux = [];
        foreach ($numeros as $numero) {
            $clair = isset($objets[$numero]) ? self::flux($objets[$numero]) : null;
            if ($clair !== null) {
                $flux[] = $clair;
            }
        }

        return $flux;
    }

    /**
     * Les tables de décodage des polices de la page, par nom de ressource.
     *
     * @param array<int, string> $objets
     * @return array<string, array<int, string>>
     */
    private static function policesDeLaPage(string $page, array $objets): array
    {
        $bloc = self::blocDesPolices($page, $objets);
        if ($bloc === null || !preg_match_all('#/([^\s/<>\[\]]+)\s+(\d+)\s+\d+\s+R#', $bloc, $refs, PREG_SET_ORDER)) {
            return [];
        }

        $polices = [];
        foreach ($refs as $ref) {
            $police = $objets[(int) $ref[2]] ?? '';
            if (preg_match('#/ToUnicode\s+(\d+)\s+\d+\s+R#', $police, $u)) {
                $table = self::lireCMap($objets[(int) $u[1]] ?? '');
                if ($table !== []) {
                    $polices[$ref[1]] = $table;
                }
            }
        }

        return $polices;
    }

    /**
     * Les morceaux de page rangés à part, avec leurs propres polices.
     *
     * On ne descend pas plus loin qu'un formulaire dans un formulaire : au-delà
     * c'est une mise en page très particulière, et le risque de tourner en rond
     * n'en vaut pas la peine.
     *
     * @return list<array{0: string, 1: array<string, array<int, string>>}>
     */
    private static function formulairesDeLaPage(string $page, array $objets): array
    {
        $bloc = self::blocDeRessource($page, $objets, '/XObject');
        if ($bloc === null || !preg_match_all('#/[^\s/<>\[\]]+\s+(\d+)\s+\d+\s+R#', $bloc, $refs)) {
            return [];
        }

        $formulaires = [];
        foreach (array_slice($refs[1], 0, 20) as $numero) {
            $objet = $objets[(int) $numero] ?? '';
            if (!preg_match('#/Subtype\s*/Form#', $objet)) {
                continue;
            }
            $contenu = self::flux($objet);
            if ($contenu !== null) {
                $formulaires[] = [$contenu, self::policesDeLaPage($objet, $objets)];
            }
        }

        return $formulaires;
    }

    /** Le dictionnaire /Font de la page, qu'il soit sur place ou plus loin. */
    private static function blocDesPolices(string $page, array $objets): ?string
    {
        return self::blocDeRessource($page, $objets, '/Font');
    }

    /** Un dictionnaire de ressources de la page, sur place ou dans un objet à part. */
    private static function blocDeRessource(string $page, array $objets, string $cle): ?string
    {
        $ressources = $page;
        if (preg_match('#/Resources\s+(\d+)\s+\d+\s+R#', $page, $r)) {
            $ressources = $objets[(int) $r[1]] ?? '';
        }

        $position = strpos($ressources, $cle);
        if ($position === false) {
            return null;
        }

        // Le dictionnaire peut être ici, ou dans un objet à part.
        if (preg_match('#' . preg_quote($cle, '#') . '\s+(\d+)\s+\d+\s+R#', $ressources, $f)) {
            return $objets[(int) $f[1]] ?? null;
        }

        $ouvrant = strpos($ressources, '<<', $position);
        if ($ouvrant === false) {
            return null;
        }

        $profondeur = 0;
        for ($i = $ouvrant, $n = strlen($ressources); $i < $n - 1; $i++) {
            if (substr($ressources, $i, 2) === '<<') {
                $profondeur++;
                $i++;
            } elseif (substr($ressources, $i, 2) === '>>') {
                $profondeur--;
                $i++;
                if ($profondeur === 0) {
                    return substr($ressources, $ouvrant, $i + 1 - $ouvrant);
                }
            }
        }

        return null;
    }

    /**
     * La table « code du caractère → texte » d'une police.
     *
     * @return array<int, string>
     */
    private static function lireCMap(string $objet): array
    {
        $cmap = self::flux($objet);
        if ($cmap === null) {
            return [];
        }

        $table = [];

        // « <0041> <0042> » : un code, sa traduction.
        if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $cmap, $blocs)) {
            foreach ($blocs[1] as $bloc) {
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]*)>/', $bloc, $paires, PREG_SET_ORDER)) {
                    foreach ($paires as $paire) {
                        $table[hexdec($paire[1])] = self::depuisUtf16($paire[2]);
                    }
                }
            }
        }

        // « <0041> <005A> <0061> » : une plage de codes, traduite d'un bloc.
        if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmap, $blocs)) {
            foreach ($blocs[1] as $bloc) {
                self::lirePlages($bloc, $table);
            }
        }

        return $table;
    }

    /** Les plages d'une CMap, y compris celles qui listent leurs valeurs. */
    private static function lirePlages(string $bloc, array &$table): void
    {
        // Forme « <début> <fin> [<a> <b> …] »
        if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*\[(.*?)\]/s', $bloc, $listes, PREG_SET_ORDER)) {
            foreach ($listes as $liste) {
                $code = hexdec($liste[1]);
                if (preg_match_all('/<([0-9A-Fa-f]*)>/', $liste[3], $valeurs)) {
                    foreach ($valeurs[1] as $valeur) {
                        $table[$code++] = self::depuisUtf16($valeur);
                    }
                }
            }
            $bloc = preg_replace('/<[0-9A-Fa-f]+>\s*<[0-9A-Fa-f]+>\s*\[.*?\]/s', '', $bloc) ?? $bloc;
        }

        // Forme « <début> <fin> <première valeur> »
        if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $bloc, $plages, PREG_SET_ORDER)) {
            foreach ($plages as $plage) {
                $debut = hexdec($plage[1]);
                $fin = hexdec($plage[2]);
                $valeur = hexdec($plage[3]);
                // Une plage démesurée trahit une lecture de travers : on passe.
                if ($fin < $debut || $fin - $debut > 65535) {
                    continue;
                }
                for ($code = $debut; $code <= $fin; $code++) {
                    $table[$code] = self::caractere($valeur + ($code - $debut));
                }
            }
        }
    }

    /** Une valeur UTF-16BE écrite en hexadécimal, rendue en UTF-8. */
    private static function depuisUtf16(string $hexa): string
    {
        if ($hexa === '' || strlen($hexa) % 2 !== 0) {
            return '';
        }
        $octets = @hex2bin($hexa);
        if ($octets === false) {
            return '';
        }
        $texte = @mb_convert_encoding($octets, 'UTF-8', 'UTF-16BE');

        return is_string($texte) ? $texte : '';
    }

    /** Un point de code Unicode, en UTF-8. */
    private static function caractere(int $point): string
    {
        if ($point <= 0 || $point > 0x10FFFF) {
            return '';
        }
        $texte = @mb_chr($point, 'UTF-8');

        return is_string($texte) ? $texte : '';
    }

    // --- Les instructions de dessin -----------------------------------------

    /**
     * Le texte d'un flux de contenu.
     *
     * Une chaîne n'est du texte que si un opérateur d'affichage la réclame :
     * Tj, TJ, ' ou ". Toutes les autres sont des paramètres — l'étiquette de
     * langue « (fr-FR) » d'un balisage, par exemple — et n'ont rien à faire
     * dans le résultat. On les met donc en attente, et on ne les retient que
     * lorsqu'un tel opérateur arrive.
     *
     * Le reste suit les positions : deux morceaux à la même hauteur
     * appartiennent à la même ligne et se recollent sans rien entre eux —
     * l'espace, quand il existe, est dans le texte lui-même. Une hauteur qui
     * change, ou un retour vers la gauche, commence une ligne.
     *
     * @param array<string, array<int, string>> $polices
     */
    private static function lireContenu(string $contenu, array $polices): string
    {
        $sortie = '';
        $attente = '';           // les chaînes lues, tant qu'aucun opérateur ne les réclame
        $police = [];
        $pile = [];              // les nombres en attente d'un opérateur
        $nom = '';               // le dernier /Nom rencontré
        $tmX = 0.0; $tmY = 0.0;  // la matrice de texte
        $dX = 0.0; $dY = 0.0;    // les déplacements cumulés depuis
        $posX = null; $posY = null;  // là où le dernier texte a été écrit
        $forcerLigne = false;
        $dansTableau = false;

        $ecrire = static function (string $texte) use (
            &$sortie, &$posX, &$posY, &$forcerLigne, &$tmX, &$tmY, &$dX, &$dY
        ): void {
            if ($texte === '') {
                return;
            }
            $x = $tmX + $dX;
            $y = $tmY + $dY;

            if ($posY !== null) {
                // Un retour vers la gauche vaut un retour à la ligne, quelle
                // que soit l'échelle du document.
                $sortie .= ($forcerLigne || abs($y - $posY) > 0.6 || $x < $posX - 1) ? "\n" : '';
            }
            $sortie .= $texte;
            $posX = $x;
            $posY = $y;
            $forcerLigne = false;
        };

        $longueur = strlen($contenu);
        $i = 0;

        while ($i < $longueur) {
            $c = $contenu[$i];

            if ($c === ' ' || $c === "\n" || $c === "\r" || $c === "\t") {
                $i++;
                continue;
            }

            // Un dictionnaire en ligne ne contient que des paramètres : on l'enjambe.
            if ($c === '<' && ($contenu[$i + 1] ?? '') === '<') {
                $i = self::finDuDictionnaire($contenu, $i);
                continue;
            }

            // Une chaîne littérale : « (bonjour) ».
            if ($c === '(') {
                [$chaine, $i] = self::lireChaine($contenu, $i);
                $attente .= self::decoder($chaine, $police);
                $i++;
                continue;
            }

            // Une chaîne hexadécimale : « <0042004F> ».
            if ($c === '<') {
                $ferme = strpos($contenu, '>', $i);
                if ($ferme === false) {
                    break;
                }
                $hexa = preg_replace('/[^0-9A-Fa-f]/', '', substr($contenu, $i + 1, $ferme - $i - 1)) ?? '';
                if (strlen($hexa) % 2 === 1) {
                    $hexa .= '0';
                }
                $attente .= self::decoder((string) @hex2bin($hexa), $police);
                $i = $ferme + 1;
                continue;
            }

            if ($c === '[') { $dansTableau = true; $i++; continue; }
            if ($c === ']') { $dansTableau = false; $i++; continue; }

            // Un nom de ressource : « /F6 ».
            if ($c === '/') {
                preg_match('#\G/([^\s/<>\[\]()]*)#', $contenu, $m, 0, $i);
                $nom = $m[1] ?? '';
                $i += strlen($m[0] ?? '/');
                continue;
            }

            // Un nombre : opérande, ou crénage dans un tableau.
            if ($c === '-' || $c === '+' || $c === '.' || ctype_digit($c)) {
                preg_match('/\G[-+]?\d*\.?\d+/', $contenu, $m, 0, $i);
                if (($m[0] ?? '') === '') {
                    $i++;
                    continue;
                }
                $valeur = (float) $m[0];
                // Dans « [(Mai) -250 (lior)] TJ », un grand recul est une espace.
                if ($dansTableau) {
                    if ($valeur <= -120 && !str_ends_with($attente, ' ')) {
                        $attente .= ' ';
                    }
                } else {
                    $pile[] = $valeur;
                }
                $i += strlen($m[0]);
                continue;
            }

            // Un opérateur.
            preg_match('/\G[A-Za-z\'"*]+/', $contenu, $m, 0, $i);
            $operateur = $m[0] ?? '';
            if ($operateur === '') {
                $i++;
                continue;
            }
            $i += strlen($operateur);

            switch ($operateur) {
                case 'Tj':
                case 'TJ':
                    $ecrire($attente);
                    break;
                case "'":
                case '"':
                    $forcerLigne = true;
                    $ecrire($attente);
                    break;
                case 'Tf':
                    $police = $polices[$nom] ?? [];
                    break;
                case 'BT':
                    $dX = 0.0;
                    $dY = 0.0;
                    break;
                case 'Tm':
                    if (count($pile) >= 6) {
                        $tmX = $pile[count($pile) - 2];
                        $tmY = $pile[count($pile) - 1];
                        $dX = 0.0;
                        $dY = 0.0;
                    }
                    break;
                case 'Td':
                case 'TD':
                    if (count($pile) >= 2) {
                        $dX += $pile[count($pile) - 2];
                        $dY += $pile[count($pile) - 1];
                    }
                    break;
                case 'T*':
                    $forcerLigne = true;
                    break;
            }

            // Une chaîne qu'aucun opérateur d'affichage n'a réclamée était un
            // paramètre : elle ne fait pas partie du texte de la page.
            $attente = '';
            $pile = [];
        }

        return self::nettoyer($sortie);
    }

    /** La position juste après le dictionnaire « << … >> » qui commence ici. */
    private static function finDuDictionnaire(string $contenu, int $depart): int
    {
        $profondeur = 0;
        $longueur = strlen($contenu);

        for ($i = $depart; $i < $longueur - 1; $i++) {
            $paire = substr($contenu, $i, 2);
            if ($paire === '<<') {
                $profondeur++;
                $i++;
            } elseif ($paire === '>>') {
                $profondeur--;
                $i++;
                if ($profondeur === 0) {
                    return $i + 1;
                }
            }
        }

        return $longueur;
    }

    /** Une chaîne littérale, parenthèses imbriquées et échappements compris. */
    private static function lireChaine(string $contenu, int $depart): array
    {
        $chaine = '';
        $profondeur = 1;
        $i = $depart + 1;
        $longueur = strlen($contenu);

        while ($i < $longueur && $profondeur > 0) {
            $c = $contenu[$i];

            if ($c === '\\') {
                $suivant = $contenu[$i + 1] ?? '';
                if (preg_match('/[0-7]/', $suivant)) {
                    preg_match('/\G[0-7]{1,3}/', $contenu, $m, 0, $i + 1);
                    $chaine .= chr(octdec($m[0]) & 0xFF);
                    $i += 1 + strlen($m[0]);
                    continue;
                }
                $chaine .= match ($suivant) {
                    'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C",
                    "\n" => '', "\r" => '',
                    default => $suivant,
                };
                $i += 2;
                continue;
            }

            if ($c === '(') {
                $profondeur++;
            } elseif ($c === ')') {
                $profondeur--;
                if ($profondeur === 0) {
                    break;
                }
            }

            $chaine .= $c;
            $i++;
        }

        return [$chaine, $i];
    }

    /**
     * Les octets d'une chaîne, rendus lisibles.
     *
     * Avec une table, les codes se lisent deux octets à la fois quand elle est
     * faite ainsi. Sans table, on suppose du Windows-1252 : c'est ce
     * qu'emploient les PDF sans police embarquée, et l'accent y tombe juste.
     */
    private static function decoder(string $octets, array $police): string
    {
        if ($octets === '') {
            return '';
        }

        if ($police === []) {
            $texte = @mb_convert_encoding($octets, 'UTF-8', 'Windows-1252');
            return is_string($texte) ? $texte : '';
        }

        $surDeuxOctets = max(array_keys($police)) > 255;
        $sortie = '';
        $pas = $surDeuxOctets ? 2 : 1;

        for ($i = 0, $n = strlen($octets); $i < $n; $i += $pas) {
            $code = $pas === 2
                ? ((ord($octets[$i]) << 8) | ord($octets[$i + 1] ?? "\0"))
                : ord($octets[$i]);
            $sortie .= $police[$code] ?? '';
        }

        return $sortie;
    }

    /** Espaces en trop, lignes vides et caractères de contrôle écartés. */
    private static function nettoyer(string $texte): string
    {
        $texte = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $texte) ?? $texte;
        $texte = preg_replace('/[ \t]+/u', ' ', $texte) ?? $texte;
        $texte = preg_replace('/ *\n */u', "\n", $texte) ?? $texte;
        $texte = preg_replace('/\n{2,}/u', "\n", $texte) ?? $texte;

        return trim($texte);
    }
}
