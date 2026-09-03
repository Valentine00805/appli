<?php
/**
 * Configuration de l'application.
 * Adapter l'utilisateur/mot de passe MySQL si besoin (WAMP : root sans mot de passe par défaut).
 */
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'mon_appli_cours',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'nom'              => 'Mes Cours',
        'inscription_ouverte' => true,          // passer à false pour bloquer les nouvelles inscriptions

        // Mot de passe à fournir pour créer un compte. Laissé vide, l'inscription
        // est libre — ce qui ne convient qu'en local. Dès que l'application est
        // accessible depuis Internet, il faut soit renseigner ce code, soit
        // fermer les inscriptions ci-dessus : sans cela l'application refuse
        // toute inscription et vous le signale.
        'code_inscription' => '',
        'dossier_uploads'  => __DIR__ . '/../storage/uploads',
        'taille_max_fichier' => 25 * 1024 * 1024, // 25 Mo
        'extensions_autorisees' => [
            'pdf', 'doc', 'docx', 'odt', 'ppt', 'pptx', 'odp', 'xls', 'xlsx', 'ods',
            'txt', 'md', 'csv', 'rtf',
            'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'heic',
            'zip', 'mp3', 'mp4', 'm4a',
        ],
    ],
];
