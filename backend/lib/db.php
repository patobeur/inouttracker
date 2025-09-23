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

/**
 * Crée les tables de la base de données si elles n'existent pas.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 */
function create_tables_if_not_exists(PDO $pdo)
{
    // Détecter le type de driver pour adapter les types de données
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $id = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    // Définitions de schémas SQL
    $users_sql = "
    CREATE TABLE IF NOT EXISTS users (
        id " . $id . ",
        email VARCHAR(255) NOT NULL UNIQUE,
        pseudo VARCHAR(50) NOT NULL UNIQUE,
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        password_hash VARCHAR(255) NOT NULL,
        total_points INT DEFAULT 0,
        is_admin INTEGER NOT NULL DEFAULT 0,
        reset_token VARCHAR(255),
        reset_expires_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );";

    $badges_sql = "
    CREATE TABLE IF NOT EXISTS badges (
        id " . $id . ",
        slug VARCHAR(50) NOT NULL UNIQUE,
        label VARCHAR(100) NOT NULL,
        color VARCHAR(7) NOT NULL,
        description TEXT
    );";

    $user_badges_sql = "
    CREATE TABLE IF NOT EXISTS user_badges (
        user_id INT NOT NULL,
        badge_id INT NOT NULL,
        level INT DEFAULT 1,
        awarded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, badge_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
    );";

    $sondages_sql = "
    CREATE TABLE IF NOT EXISTS sondages (
        id " . $id . ",
        title VARCHAR(255) NOT NULL,
        description TEXT,
        status VARCHAR(10) NOT NULL DEFAULT 'draft',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );";

    // Exécuter les créations de table
    $pdo->exec($users_sql);
    $pdo->exec($badges_sql);
    $pdo->exec($user_badges_sql);
    $pdo->exec($sondages_sql);

    // Vérifier si la table des utilisateurs est vide
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $user_count = $stmt->fetchColumn();

    if ($user_count == 0) {
        // La table est vide, insérer l'utilisateur admin
        $admin_email = 'inouttracker@inouttracker.pat';
        $admin_pseudo = 'admin';
        $admin_first_name = 'admin';
        $admin_last_name = 'istrateur';
        // Le mot de passe est 'admin' hashé avec PASSWORD_BCRYPT
        $admin_password_hash = '$2y$10$CYsB/e/mroa4UJPmp7Y5Me4eKadWUGbAOHpbCDbUzrjfom.HDI5uy';
        $is_admin = 1; // Mettre à 1 pour un administrateur

        $insert_admin_sql = "INSERT INTO users (email, pseudo, first_name, last_name, password_hash, is_admin) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($insert_admin_sql);
        $stmt->execute([$admin_email, $admin_pseudo, $admin_first_name, $admin_last_name, $admin_password_hash, $is_admin]);
    }
}
