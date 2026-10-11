<?php
declare(strict_types=1);

/**
 * Ce qu'il faut pour que l'application tienne sans réseau.
 *
 * Trois choses, et elles vont ensemble : la page qui s'affiche quand on
 * demande quelque chose qui n'a jamais été ouvert, le manifeste qui permet
 * d'installer l'application sur un téléphone ou un bureau, et son icône (le logo).
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
        Vue::afficherPublic('hors-ligne', [], t('titre.hors_ligne'));
    }

    /**
     * Le manifeste : de quoi installer « Mes Cours » comme une application.
     * Installée, elle s'ouvre sans barre d'adresse et garde son cache — c'est
     * là que le hors-ligne prend tout son sens.
     */
    public function manifeste(): void
    {
        $nom = (string) Config::get('app', 'nom');
        // Les icônes portent la date de leur fichier : un changement de logo change leur adresse, et ni le cache du navigateur (une semaine) ni
        // celui du service worker ne resservent l'ancienne image.
        $icone = static fn (string $route, string $fichier): string => url($route) . '?v=' . (int) @filemtime(dirname(__DIR__) . '/assets/img/' . $fichier);
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: private, max-age=3600');

        echo (string) json_encode([
            'name' => $nom,
            'short_name' => $nom,
            'description' => t('accueil.pitch'),
            'lang' => Langue::courante(),
            'dir' => 'ltr',
            'start_url' => url(''),
            'scope' => url(''),
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#f5f6fa',
            'theme_color' => '#4f46e5',
            'icons' => [
                ['src' => $icone('icone-192.png', 'icone-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $icone('icone-512.png', 'icone-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $icone('icone-masque-512.png', 'icone-masque-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => t('nav.calendrier'), 'url' => url('calendrier')],
                ['name' => t('nav.cours'), 'url' => url('cours')],
                ['name' => t('nav.alternance'), 'url' => url('alternance')],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * L'icône de l'application : le logo de Mes Cours, en PNG (une icône installée doit être un vrai PNG). « icone-192.png » et « icone-512.png »
     * ont les coins arrondis, transparents ; la demande se ramène à la taille la plus proche.
     */
    public function icone(int $taille): void
    {
        self::servir($taille <= 192 ? 'icone-192.png' : 'icone-512.png');
    }

    /**
     * La même, pour les systèmes qui découpent l'icône à leur façon (Android, iOS) : le fond est plein et blanc, le logo tient dans la zone
     * sûre — sans coins transparents qui deviendraient noirs.
     */
    public function iconeMasquable(int $taille): void
    {
        self::servir('icone-masque-512.png');
    }

    private static function servir(string $fichier): never
    {
        $chemin = dirname(__DIR__) . '/assets/img/' . $fichier;
        if (!is_file($chemin)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($chemin));
        header('Cache-Control: public, max-age=604800');
        readfile($chemin);
        exit;
    }
}
