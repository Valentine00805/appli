<?php
declare(strict_types=1);

/**
 * Le récapitulatif d'un travail de groupe, en PDF : l'état du projet, les chiffres, les tâches faites par personne, le « burn down » — la
 * courbe des tâches qu'il restait à faire, du premier jour du projet au dernier — et, personne par personne, qui a fait quoi.
 *
 * Les calculs viennent de RecapProjet ; ici, que la mise en pages (A4, sur PdfSimple : du texte, des rectangles, des traits).
 */
final class RecapProjetPdf
{
    private const MARGE = 50.0;
    private const BAS = 56.0;

    private const ACCENT = [0.31, 0.27, 0.90];
    private const TEXTE = [0.10, 0.11, 0.16];
    private const DOUX = [0.42, 0.45, 0.52];
    private const TRAIT = [0.86, 0.87, 0.91];
    private const FOND = [0.95, 0.95, 0.98];
    private const VERT = [0.13, 0.64, 0.36];
    private const BLEU = [0.10, 0.27, 0.50];
    private const ORANGE = [0.93, 0.49, 0.13];
    private const JAUNE = [1.0, 0.75, 0.0];

    private PdfSimple $pdf;
    private float $y = 0.0;

    public static function generer(array $recap, string $nomProjet): string
    {
        return (new self())->composer($recap, $nomProjet);
    }

    private function composer(array $recap, string $nomProjet): string
    {
        $this->pdf = new PdfSimple();
        $this->page();
        $largeur = PdfSimple::LARGEUR - 2 * self::MARGE;

        // --- En-tête : le projet, son état, sa période
        $this->texte(self::MARGE, $this->y, t('tr.rc.pdf.titre'), 'F2', 9.5, self::ACCENT);
        $this->y -= 26;
        $this->paragraphe(self::MARGE, $largeur, $nomProjet, 'F2', 22, self::TEXTE, 26);
        $this->y -= 4;
        $statut = $recap['termine']
            ? t('tr.rc.pdf.termine', ['date' => date_fr($recap['fin'], false)])
            : t('tr.rc.pdf.en_cours', ['date' => date_fr($recap['fin'], false)]);
        $this->texte(self::MARGE, $this->y, $statut, 'F2', 11, $recap['termine'] ? self::VERT : self::ORANGE);
        $this->y -= 16;
        $this->texte(self::MARGE, $this->y, t('tr.rc.pdf.periode', ['debut' => date_fr($recap['debut'], false), 'fin' => date_fr($recap['fin'], false)]), 'F1', 10, self::DOUX);
        $this->y -= 26;

        // --- Les chiffres
        $this->chiffres([
            [$recap['faites'] . ' / ' . $recap['total'], t('tr.rc.pdf.chiffre_faites')],
            [$recap['part'] . ' %', t('tr.rc.pdf.chiffre_avancement')],
            [(string) $recap['jours'], tn('tr.rc.pdf.chiffre_duree', $recap['jours'])],
            [(string) count($recap['personnes']), t('tr.rc.pdf.chiffre_membres')],
        ]);

        // --- Les tâches faites par personne
        $this->titre(t('tr.rc.pdf.par_personne'));
        $lignes = array_map(static fn (array $p): array => [$p['nom'], $p['faites'], $p['total']], $recap['personnes']);
        if ($recap['sans_responsable'] !== null) {
            $lignes[] = [t('tr.rc.pdf.sans_responsable'), $recap['sans_responsable']['faites'], $recap['sans_responsable']['total']];
        }
        $this->barres($lignes);

        // --- Le burn down
        $this->placer(330);
        $this->titre(t('tr.rc.pdf.burn'));
        $this->paragraphe(self::MARGE, $largeur, t('tr.rc.pdf.burn_aide', ['debut' => date_fr($recap['debut'], false), 'fin' => date_fr($recap['fin'], false)]), 'F1', 9, self::DOUX, 12);
        $this->y -= 8;
        $this->burnDown($recap);

        // --- Qui a fait quoi (le titre ne reste pas seul en bas de page : il emmène le premier bloc avec lui)
        $this->placer(120);
        $this->titre(t('tr.rc.pdf.qui_a_fait_quoi'));
        if ($recap['total'] === 0) {
            $this->texte(self::MARGE, $this->y, t('tr.rc.pdf.aucune_tache'), 'F3', 10, self::DOUX);
            $this->y -= 16;
        }
        foreach ($recap['personnes'] as $p) {
            $this->personne($p['nom'], $p['faites'], $p['total'], $p['taches'], $p['points_coches']);
        }
        if ($recap['sans_responsable'] !== null) {
            $s = $recap['sans_responsable'];
            $this->personne(t('tr.rc.pdf.sans_responsable'), $s['faites'], $s['total'], $s['taches']);
        }

        $this->pieds($nomProjet);

        return $this->pdf->sortie(t('tr.rc.pdf.titre') . ' - ' . $nomProjet);
    }

    /* --- Les blocs ---------------------------------------------------------- */

    /** @param list<array{0: string, 1: string}> $cases un grand chiffre, et ce qu'il dit */
    private function chiffres(array $cases): void
    {
        $ecart = 10.0;
        $largeur = (PdfSimple::LARGEUR - 2 * self::MARGE - ($ecart * (count($cases) - 1))) / count($cases);
        $hauteur = 56.0;
        $this->placer($hauteur + 10);
        foreach ($cases as $i => [$chiffre, $legende]) {
            $x = self::MARGE + $i * ($largeur + $ecart);
            $this->pdf->rectangle($x, $this->y - $hauteur, $largeur, $hauteur, self::FOND);
            $this->pdf->rectangle($x, $this->y - $hauteur, 3, $hauteur, self::ACCENT);
            $this->texte($x + 14, $this->y - 26, $chiffre, 'F2', 18, self::TEXTE);
            $this->texte($x + 14, $this->y - 44, $legende, 'F1', 8.5, self::DOUX);
        }
        $this->y -= $hauteur + 26;
    }

    private function titre(string $texte): void
    {
        $this->placer(40);
        $this->texte(self::MARGE, $this->y, $texte, 'F2', 13, self::TEXTE);
        $this->y -= 6;
        $this->pdf->rectangle(self::MARGE, $this->y, PdfSimple::LARGEUR - 2 * self::MARGE, 0.8, self::TRAIT);
        $this->y -= 18;
    }

    /** @param list<array{0: string, 1: int, 2: int}> $lignes nom, faites, total */
    private function barres(array $lignes): void
    {
        $plusGrand = max(1, ...array_map(static fn (array $l): int => $l[2], $lignes ?: [['', 0, 1]]));
        $xBarre = self::MARGE + 150;
        $longueur = PdfSimple::LARGEUR - self::MARGE - 70 - $xBarre;
        foreach ($lignes as [$nom, $faites, $total]) {
            $this->placer(24);
            $this->texte(self::MARGE, $this->y, $this->couper($nom, 'F1', 10, 140), 'F1', 10, self::TEXTE);
            $this->pdf->rectangle($xBarre, $this->y - 2, $longueur, 10, self::FOND);
            if ($total > 0) {
                $this->pdf->rectangle($xBarre, $this->y - 2, $longueur * $total / $plusGrand, 10, [0.80, 0.82, 0.96]);
            }
            if ($faites > 0) {
                $this->pdf->rectangle($xBarre, $this->y - 2, $longueur * $faites / $plusGrand, 10, self::ACCENT);
            }
            $this->texte($xBarre + $longueur + 10, $this->y, $faites . ' / ' . $total, 'F2', 10, self::TEXTE);
            $this->y -= 22;
        }
        $this->y -= 14;
    }

    /** La courbe des tâches restantes, le rythme idéal, et les tâches ajoutées chaque jour (les barres). */
    private function burnDown(array $recap): void
    {
        $largeurTotale = PdfSimple::LARGEUR - 2 * self::MARGE;
        $hauteur = 200.0;
        $x0 = self::MARGE + 32;
        $largeur = $largeurTotale - 40;
        $this->placer($hauteur + 70);
        $haut = $this->y;
        $bas = $haut - $hauteur;

        $maximum = (int) $recap['maximum'];
        $pasY = max(1, (int) ceil($maximum / 5));
        $axeY = $pasY * (int) ceil($maximum / $pasY);
        $axe = (int) $recap['axe'];
        $x = static fn (int|float $d): float => $x0 + $largeur * $d / $axe;
        $yPour = static fn (int|float $v): float => $bas + $hauteur * $v / $axeY;

        // La grille et l'échelle des tâches
        for ($v = 0; $v <= $axeY; $v += $pasY) {
            $this->pdf->ligne($x0, $yPour($v), $x0 + $largeur, $yPour($v), $v === 0 ? 0.9 : 0.4, $v === 0 ? self::DOUX : self::TRAIT);
            $mot = (string) $v;
            $this->texte($x0 - 6 - $this->largeurTexte($mot, 'F1', 8), $yPour($v) - 3, $mot, 'F1', 8, self::DOUX);
        }

        // Les dates, une sur n pour qu'elles tiennent
        $pasX = max(1, (int) ceil($axe / 10));
        foreach ($recap['courbe'] as $point) {
            if ($point['indice'] % $pasX !== 0 && $point['indice'] !== $axe) {
                continue;
            }
            if ($point['indice'] !== $axe && $axe - $point['indice'] < $pasX / 2) {
                continue;   // trop près de la dernière date
            }
            $etiquette = date('j/n', (int) strtotime($point['jour']));
            $this->texte($x($point['indice']) - $this->largeurTexte($etiquette, 'F1', 8) / 2, $bas - 13, $etiquette, 'F1', 8, self::DOUX);
        }

        // Les tâches ajoutées chaque jour
        $largeurBarre = max(1.5, min(14.0, $largeur / ($axe + 1) * 0.6));
        foreach ($recap['courbe'] as $point) {
            if ($point['ajoutees'] > 0) {
                $this->pdf->rectangle($x($point['indice']) - $largeurBarre / 2, $bas + 0.45, $largeurBarre, $hauteur * $point['ajoutees'] / $axeY, self::JAUNE);
            }
        }

        // Le rythme idéal, puis ce qu'il restait vraiment
        $this->pdf->ligne($x(0), $yPour($recap['total']), $x($axe), $yPour(0), 1.4, self::ORANGE);
        $trace = array_map(static fn (array $p): array => [$x($p['indice']), $yPour($p['restant'])], $recap['courbe']);
        if (count($trace) === 1) {
            $trace[] = [$x($axe), $trace[0][1]];
        }
        $this->pdf->trace($trace, 2.0, self::BLEU);
        if (count($trace) <= 60) {
            foreach ($trace as [$px, $py]) {
                $this->pdf->rectangle($px - 2, $py - 2, 4, 4, self::BLEU);
            }
        }

        // La légende
        $this->y = $bas - 34;
        $cx = $x0;
        foreach ([[self::BLEU, t('tr.rc.pdf.legende_restant'), 'trait'], [self::ORANGE, t('tr.rc.pdf.legende_ideal'), 'trait'], [self::JAUNE, t('tr.rc.pdf.legende_ajoutees'), 'barre']] as [$couleur, $mot, $forme]) {
            if ($forme === 'trait') {
                $this->pdf->ligne($cx, $this->y + 3, $cx + 16, $this->y + 3, 2.0, $couleur);
            } else {
                $this->pdf->rectangle($cx + 3, $this->y - 1, 10, 9, $couleur);
            }
            $this->texte($cx + 22, $this->y, $mot, 'F1', 8.5, self::DOUX);
            $cx += 22 + $this->largeurTexte($mot, 'F1', 8.5) + 22;
        }
        $this->y -= 34;
    }

    /** @param list<array{titre: string, statut: string, fait_le: ?string}> $taches */
    private function personne(string $nom, int $faites, int $total, array $taches, int $pointsCoches = 0): void
    {
        $this->placer(60);
        $this->texte(self::MARGE, $this->y, $nom, 'F2', 11, self::TEXTE);
        if ($pointsCoches > 0) {
            $this->texte(self::MARGE + $this->largeurTexte($nom, 'F2', 11) + 10, $this->y, tn('tr.rc.pdf.points_coches', $pointsCoches), 'F1', 8.5, self::DOUX);
        }
        $compte = $faites . ' / ' . $total;
        $this->texte(PdfSimple::LARGEUR - self::MARGE - $this->largeurTexte($compte, 'F2', 10), $this->y, $compte, 'F2', 10, self::ACCENT);
        $this->y -= 16;
        if ($taches === []) {
            $this->texte(self::MARGE + 14, $this->y, t('tr.rc.pdf.aucune_pour_personne'), 'F3', 9.5, self::DOUX);
            $this->y -= 16;
        }
        foreach ($taches as $t) {
            $fait = $t['statut'] === 'fait';
            $droite = $fait && $t['fait_le'] !== null ? t('tr.rc.pdf.fait_le', ['date' => date_fr($t['fait_le'], false)]) : ($t['phase'] ?? Travaux::statutNom($t['statut']));
            if ($t['points'] !== null) {
                $droite = t('tr.rc.pdf.points', ['faits' => $t['points'][0], 'total' => $t['points'][1]]) . ' · ' . $droite;
            }
            $largeurDroite = $this->largeurTexte($droite, 'F1', 8.5);
            $lignes = $this->decouper($t['titre'], 'F1', 10, PdfSimple::LARGEUR - 2 * self::MARGE - 14 - $largeurDroite - 14);
            $this->placer(14 * count($lignes) + 4);
            if ($fait) {
                $this->pdf->rectangle(self::MARGE + 1, $this->y - 1, 8, 8, self::VERT);
            } else {
                $this->pdf->rectangle(self::MARGE + 1, $this->y - 1, 8, 8, self::DOUX);
                $this->pdf->rectangle(self::MARGE + 2, $this->y, 6, 6, [1, 1, 1]);
            }
            foreach ($lignes as $k => $ligne) {
                $this->texte(self::MARGE + 16, $this->y, $ligne, 'F1', 10, $fait ? self::TEXTE : self::DOUX);
                if ($k === 0) {
                    $this->texte(PdfSimple::LARGEUR - self::MARGE - $largeurDroite, $this->y, $droite, 'F1', 8.5, $fait ? self::VERT : self::DOUX);
                }
                $this->y -= 14;
            }
        }
        $this->y -= 10;
    }

    /** « Page n / N », et la date, au pied de chaque page. */
    private function pieds(string $nomProjet): void
    {
        $total = $this->pdf->nombreDePages();
        $genere = t('tr.rc.pdf.genere', ['date' => date_fr(date('Y-m-d'), false)]);
        for ($i = 0; $i < $total; $i++) {
            $this->pdf->allerALaPage($i);
            $this->pdf->rectangle(self::MARGE, 38, PdfSimple::LARGEUR - 2 * self::MARGE, 0.6, self::TRAIT);
            $gauche = $this->couper($nomProjet, 'F1', 8.5, 260) . ' · ' . $genere;
            $this->texte(self::MARGE, 26, $gauche, 'F1', 8.5, self::DOUX);
            $page = t('tr.rc.pdf.page', ['n' => $i + 1, 'total' => $total]);
            $this->texte(PdfSimple::LARGEUR - self::MARGE - $this->largeurTexte($page, 'F1', 8.5), 26, $page, 'F1', 8.5, self::DOUX);
        }
    }

    /* --- La page et le texte -------------------------------------------------- */

    private function page(): void
    {
        $this->pdf->nouvellePage();
        $this->pdf->rectangle(0, PdfSimple::HAUTEUR - 8, PdfSimple::LARGEUR, 8, self::ACCENT);
        $this->y = PdfSimple::HAUTEUR - self::MARGE - 12;
    }

    /** Assez de place pour ce qui vient ? Sinon, page suivante. */
    private function placer(float $hauteur): void
    {
        if ($this->y - $hauteur < self::BAS) {
            $this->page();
        }
    }

    private function texte(float $x, float $y, string $texte, string $police, float $taille, array $couleur): void
    {
        $this->pdf->texte($x, $y, PdfSimple::encoder($texte), $police, $taille, $couleur);
    }

    private function largeurTexte(string $texte, string $police, float $taille): float
    {
        return $this->pdf->largeur(PdfSimple::encoder($texte), $police, $taille);
    }

    /** Un texte trop long est coupé, avec des points de suspension. */
    private function couper(string $texte, string $police, float $taille, float $largeur): string
    {
        if ($this->largeurTexte($texte, $police, $taille) <= $largeur) {
            return $texte;
        }
        while ($texte !== '' && $this->largeurTexte($texte . '…', $police, $taille) > $largeur) {
            $texte = mb_substr($texte, 0, -1);
        }

        return rtrim($texte) . '…';
    }

    /** @return list<string> le texte, coupé aux espaces pour tenir dans la largeur (un mot trop long est coupé où il faut) */
    private function decouper(string $texte, string $police, float $taille, float $largeur): array
    {
        $lignes = [];
        $courante = '';
        foreach (preg_split('/\s+/u', trim($texte)) ?: [] as $mot) {
            while ($this->largeurTexte($mot, $police, $taille) > $largeur && mb_strlen($mot) > 1) {
                $morceau = $mot;
                while (mb_strlen($morceau) > 1 && $this->largeurTexte($morceau, $police, $taille) > $largeur) {
                    $morceau = mb_substr($morceau, 0, -1);
                }
                if ($courante !== '') {
                    $lignes[] = $courante;
                    $courante = '';
                }
                $lignes[] = $morceau;
                $mot = mb_substr($mot, mb_strlen($morceau));
            }
            $essai = $courante === '' ? $mot : $courante . ' ' . $mot;
            if ($courante !== '' && $this->largeurTexte($essai, $police, $taille) > $largeur) {
                $lignes[] = $courante;
                $courante = $mot;
            } else {
                $courante = $essai;
            }
        }
        if ($courante !== '' || $lignes === []) {
            $lignes[] = $courante;
        }

        return $lignes;
    }

    /** Un paragraphe, ligne à ligne, depuis le curseur. */
    private function paragraphe(float $x, float $largeur, string $texte, string $police, float $taille, array $couleur, float $interligne): void
    {
        foreach ($this->decouper($texte, $police, $taille, $largeur) as $ligne) {
            $this->placer($interligne);
            $this->texte($x, $this->y, $ligne, $police, $taille, $couleur);
            $this->y -= $interligne;
        }
    }
}
