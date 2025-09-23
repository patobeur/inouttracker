<?php

/**
 * Établit et retourne une connexion PDO à la base de données.
 *
 * @param array $config La configuration de la base de données.
 * @return PDO L'objet de connexion PDO.
 * @throws PDOException Si la connexion échoue.
 */
function get_db_connection(array $config): PDO
{
    $driver = $config['DB_DRIVER'] ?? 'sqlite';

    if ($driver === 'sqlite') {
        $db_path = $config['DB_PATH'] ?? __DIR__ . '/../storage/app.db';
        // Crée le répertoire s'il n'existe pas
        if (!file_exists(dirname($db_path))) {
            mkdir(dirname($db_path), 0755, true);
        }
        $dsn = "sqlite:" . $db_path;
        $pdo = new PDO($dsn);
    } elseif ($driver === 'mysql') {
        $host = $config['DB_HOST'];
        $port = $config['DB_PORT'];
        $dbname = $config['DB_NAME'];
        $charset = $config['DB_CHARSET'];
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";
        $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
    } else {
        throw new Exception("Driver de base de données non supporté: $driver");
    }

    // Configurer PDO pour lancer des exceptions en cas d'erreur
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Configurer le mode de récupération par défaut en tableau associatif
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $pdo;
}
