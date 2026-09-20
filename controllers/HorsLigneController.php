<?php
declare(strict_types=1);

/**
 * Ce qu'il faut pour que l'application tienne sans réseau.
 *
 * Trois choses, et elles vont ensemble : la page qui s'affiche quand on
 * demande quelque chose qui n'a jamais été ouvert, le manifeste qui permet
 * d'installer l'application sur un téléphone ou un bureau, et son icône.
 *
 * Aucune de ces trois-là ne demande de session : elles doivent répondre même
 * quand le serveur n'est pas joignable — c'est-à-dire depuis le cache.
 */
final class HorsLigneController
{
    /** La page servie quand on demande, sans réseau, une page jamais ouverte. */
    public function page(): void
    {
        header('Cache-Control: no-cache');
        Vue::afficherPublic('hors-ligne', [], 'Hors connexion');
    }

    /**
     * Le manifeste : de quoi installer « Mes Cours » comme une application.
     * Installée, elle s'ouvre sans barre d'adresse et garde son cache — c'est
     * là que le hors-ligne prend tout son sens.
     */
    public function manifeste(): void
    {
        $nom = (string) Config::get('app', 'nom');
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        echo (string) json_encode([
            'name' => $nom,
            'short_name' => $nom,
            'description' => 'Vos cours, vos fichiers et votre planning au même endroit.',
            'lang' => 'fr',
            'dir' => 'ltr',
            'start_url' => url(''),
            'scope' => url(''),
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#f5f6fa',
            'theme_color' => '#4f46e5',
            'icons' => [
                ['src' => url('icone-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => url('icone-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => url('icone-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Calendrier', 'url' => url('calendrier')],
                ['name' => 'Mes cours', 'url' => url('cours')],
                ['name' => 'Alternance', 'url' => url('alternance')],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * L'icône de l'application, dessinée ici plutôt que gardée en fichier :
     * un carré violet, un livre ouvert en blanc. Les emojis ne s'impriment
     * pas dans une image, et une icône installée doit être un vrai PNG.
     */
    public function icone(int $taille): void
    {
        $taille = max(48, min(512, $taille));
        $image = imagecreatetruecolor($taille, $taille);
        imagealphablending($image, true);

        $violet = (int) imagecolorallocate($image, 79, 70, 229);
        $blanc = (int) imagecolorallocate($image, 255, 255, 255);
        $ombre = (int) imagecolorallocate($image, 224, 226, 245);
        imagefilledrectangle($image, 0, 0, $taille, $taille, $violet);

        // Un livre ouvert : deux pages, et la reliure au milieu.
        $m = $taille / 16;              // la marge, pour que ça respire
        $hautPage = 4.5 * $m;
        $basPage = 11.5 * $m;
        $milieu = $taille / 2;

        imagefilledrectangle($image, (int) (2.2 * $m), (int) $hautPage, (int) ($milieu - 0.2 * $m), (int) $basPage, $blanc);
        imagefilledrectangle($image, (int) ($milieu + 0.2 * $m), (int) $hautPage, (int) ($taille - 2.2 * $m), (int) $basPage, $blanc);
        // Les lignes du texte, en gris très clair.
        for ($ligne = 1; $ligne <= 4; $ligne++) {
            $y = (int) ($hautPage + $ligne * ($basPage - $hautPage) / 5);
            $epaisseur = max(1, (int) ($taille / 90));
            imagefilledrectangle($image, (int) (3 * $m), $y, (int) ($milieu - 1 * $m), $y + $epaisseur, $ombre);
            imagefilledrectangle($image, (int) ($milieu + 1 * $m), $y, (int) ($taille - 3 * $m), $y + $epaisseur, $ombre);
        }

        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=604800');
        imagepng($image);
        imagedestroy($image);
        exit;
    }
}
