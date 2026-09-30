<?php
/**
 * Le minimum pour parler à la base depuis un essai, sans toucher au projet.
 *
 * Aucun fichier du projet n'est chargé ici : l'antivirus met parfois en
 * quarantaine un PHP du dossier quand un script en ligne de commande l'ouvre.
 * On se contente de PDO, et tout le reste passe par HTTP.
 */
declare(strict_types=1);

function bd(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=mon_appli_cours;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

/** Exécute une requête, et rend le nombre de lignes touchées. */
function bd_run(string $sql, array $valeurs = []): int
{
    $q = bd()->prepare($sql);
    $q->execute($valeurs);
    return $q->rowCount();
}

/** La première colonne de la première ligne, ou null. */
function bd_valeur(string $sql, array $valeurs = []): mixed
{
    $q = bd()->prepare($sql);
    $q->execute($valeurs);
    $ligne = $q->fetch(PDO::FETCH_NUM);
    return $ligne === false ? null : $ligne[0];
}

/** Toutes les lignes. */
function bd_all(string $sql, array $valeurs = []): array
{
    $q = bd()->prepare($sql);
    $q->execute($valeurs);
    return $q->fetchAll();
}
