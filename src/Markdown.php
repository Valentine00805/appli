<?php
declare(strict_types=1);

/**
 * Le Markdown que Gemini écrit, mis en HTML — et en texte à lire à voix haute.
 *
 * Volontairement petit : titres, listes, gras, italique, code, séparateurs, paragraphes. Le texte est
 * échappé AVANT toute mise en forme, et seules des balises écrites ici en sortent : ce que le modèle
 * (ou un document qu'il a lu) aurait glissé comme HTML n'est jamais interprété.
 */
final class Markdown
{
    public static function html(string $texte): string
    {
        $sortie = [];
        $liste = null;       // 'ul' | 'ol' | null
        $paragraphe = [];

        $fermerParagraphe = static function () use (&$paragraphe, &$sortie): void {
            if ($paragraphe !== []) {
                $sortie[] = '<p>' . implode('<br>', $paragraphe) . '</p>';
                $paragraphe = [];
            }
        };
        $fermerListe = static function () use (&$liste, &$sortie): void {
            if ($liste !== null) {
                $sortie[] = '</' . $liste . '>';
                $liste = null;
            }
        };

        foreach (preg_split('/\R/u', trim($texte)) ?: [] as $ligne) {
            $ligne = rtrim($ligne);

            if ($ligne === '') {
                $fermerParagraphe();
                $fermerListe();
                continue;
            }
            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*$/u', $ligne, $m) === 1) {
                $fermerParagraphe();
                $fermerListe();
                // La page porte déjà un h1 et un h2 : les titres du texte commencent à h3.
                $niveau = min(6, strlen($m[1]) + 2);
                $sortie[] = '<h' . $niveau . '>' . self::enLigne($m[2]) . '</h' . $niveau . '>';
                continue;
            }
            if (preg_match('/^\s*([-*_])(\s*\1){2,}\s*$/u', $ligne) === 1) {
                $fermerParagraphe();
                $fermerListe();
                $sortie[] = '<hr>';
                continue;
            }
            if (preg_match('/^\s*[-*•]\s+(.+)$/u', $ligne, $m) === 1) {
                $fermerParagraphe();
                if ($liste !== 'ul') {
                    $fermerListe();
                    $sortie[] = '<ul>';
                    $liste = 'ul';
                }
                $sortie[] = '<li>' . self::enLigne($m[1]) . '</li>';
                continue;
            }
            if (preg_match('/^\s*\d+[.)]\s+(.+)$/u', $ligne, $m) === 1) {
                $fermerParagraphe();
                if ($liste !== 'ol') {
                    $fermerListe();
                    $sortie[] = '<ol>';
                    $liste = 'ol';
                }
                $sortie[] = '<li>' . self::enLigne($m[1]) . '</li>';
                continue;
            }

            $fermerListe();
            $paragraphe[] = self::enLigne(trim($ligne));
        }
        $fermerParagraphe();
        $fermerListe();

        return implode("\n", $sortie);
    }

    /** Le texte sans aucune marque de mise en forme, tel qu'une voix doit le lire. */
    public static function brut(string $texte): string
    {
        $lignes = [];
        foreach (preg_split('/\R/u', trim($texte)) ?: [] as $ligne) {
            $ligne = preg_replace('/^\s*#{1,6}\s+/u', '', $ligne) ?? $ligne;
            $ligne = preg_replace('/^\s*([-*_])(\s*\1){2,}\s*$/u', '', $ligne) ?? $ligne;
            $ligne = preg_replace('/^\s*[-*•]\s+/u', '', $ligne) ?? $ligne;
            $ligne = preg_replace('/^\s*\d+[.)]\s+/u', '', $ligne) ?? $ligne;
            $ligne = preg_replace('/(\*\*|__|`)/u', '', $ligne) ?? $ligne;
            $ligne = preg_replace('/(?<![\p{L}\p{N}])[*_]|[*_](?![\p{L}\p{N}])/u', '', $ligne) ?? $ligne;
            $lignes[] = trim($ligne);
        }

        return trim((string) preg_replace('/\n{3,}/', "\n\n", implode("\n", $lignes)));
    }

    /** Gras, italique et code dans une ligne, après échappement. */
    private static function enLigne(string $ligne): string
    {
        $ligne = htmlspecialchars($ligne, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $ligne = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $ligne) ?? $ligne;
        $ligne = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $ligne) ?? $ligne;
        $ligne = preg_replace('/__(.+?)__/u', '<strong>$1</strong>', $ligne) ?? $ligne;
        $ligne = preg_replace('/(?<![\p{L}\p{N}*])\*(?!\s)(.+?)(?<!\s)\*(?![\p{L}\p{N}*])/u', '<em>$1</em>', $ligne) ?? $ligne;
        $ligne = preg_replace('/(?<![\p{L}\p{N}_])_(?!\s)(.+?)(?<!\s)_(?![\p{L}\p{N}_])/u', '<em>$1</em>', $ligne) ?? $ligne;

        return $ligne;
    }
}
