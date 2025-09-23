<?php

// Désactiver la temporisation de sortie de bootstrap.php pour voir les messages en temps réel.
ob_end_clean();

echo "Démarrage du script d'installation...\n";

// Ce script ne doit pas être accessible en production via le web.
// Une vérification simple, mais qui peut être renforcée.
if (php_sapi_name() !== 'cli' && !isset($_GET['confirm'])) {
    header('HTTP/1.1 403 Forbidden');
    die("ERREUR : Ce script doit être exécuté en ligne de commande (CLI) ou avec le paramètre de confirmation '?confirm=true'.\n");
}

// Inclure le fichier de bootstrap pour accéder à la configuration et aux fonctions de base.
// Note : bootstrap.php initialise déjà la connexion PDO.
require_once __DIR__ . '/bootstrap.php';

/**
 * Crée les tables de la base de données si elles n'existent pas.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 */
function install_database_schema(PDO $pdo)
{
    // Détecter le type de driver pour adapter les types de données
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    echo "Détection du driver de base de données : $driver\n";

    // Définitions de schémas SQL
    $users_sql = "
    CREATE TABLE IF NOT EXISTS users (
        id " . ($driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY') . ",
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
        id " . ($driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY') . ",
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
        id " . ($driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY') . ",
        title VARCHAR(255) NOT NULL,
        description TEXT,
        status VARCHAR(10) NOT NULL DEFAULT 'draft',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );";

    $clients_sql = "
    CREATE TABLE IF NOT EXISTS clients (
        id " . ($driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY') . ",
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE,
        phone VARCHAR(50),
        address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );";

    $articles_sql = "
    CREATE TABLE IF NOT EXISTS articles (
        id " . ($driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY') . ",
        name VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10, 2),
        quantity INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );";

    $movements_sql = "
    CREATE TABLE IF NOT EXISTS movements (
        id " . ($driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY') . ",
        article_id INT NOT NULL,
        client_id INT,
        type VARCHAR(10) NOT NULL,
        quantity INT NOT NULL,
        date DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
    );";

    try {
        echo "Création de la table 'users'...\n";
        $pdo->exec($users_sql);
        echo "Création de la table 'badges'...\n";
        $pdo->exec($badges_sql);
        echo "Création de la table 'user_badges'...\n";
        $pdo->exec($user_badges_sql);
        echo "Création de la table 'sondages'...\n";
        $pdo->exec($sondages_sql);
        echo "Création de la table 'clients'...\n";
        $pdo->exec($clients_sql);
        echo "Création de la table 'articles'...\n";
        $pdo->exec($articles_sql);
        echo "Création de la table 'movements'...\n";
        $pdo->exec($movements_sql);
        echo "Toutes les tables ont été créées avec succès.\n";

        // Vérifier si la table des utilisateurs est vide pour insérer l'admin
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $user_count = $stmt->fetchColumn();

        if ($user_count == 0) {
            echo "La table 'users' est vide. Création de l'utilisateur admin...\n";
            $admin_email = 'inouttracker@inouttracker.fr';
            $admin_pseudo = 'admin';
            $admin_first_name = 'admin';
            $admin_last_name = 'istrateur';
            $is_admin = 1;
            $admin_password_hash = '$2y$10$CYsB/e/mroa4UJPmp7Y5Me4eKadWUGbAOHpbCDbUzrjfom.HDI5uy';

            $insert_admin_sql = "INSERT INTO users (email, pseudo, first_name, last_name, password_hash, is_admin) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($insert_admin_sql);
            $stmt->execute([$admin_email, $admin_pseudo, $admin_first_name, $admin_last_name, $admin_password_hash, $is_admin]);
            echo "Utilisateur admin créé avec succès.\n";
        } else {
            echo "La table 'users' contient déjà des utilisateurs. L'utilisateur admin n'a pas été créé.\n";
        }
    } catch (PDOException $e) {
        throw new Exception("Erreur lors de la création du schéma de la base de données : " . $e->getMessage());
    }
}

try {
    // La variable $pdo est initialisée dans bootstrap.php
    if (!isset($pdo)) {
        throw new Exception("La connexion PDO n'a pas pu être établie. Vérifiez la configuration dans 'env.php'.");
    }

    echo "Connexion à la base de données réussie. Installation du schéma...\n";
    install_database_schema($pdo);

    echo "\n---------------------------------------------------\n";
    echo "Installation terminée avec succès !\n";
    echo "IMPORTANT : Pour des raisons de sécurité, veuillez supprimer ce fichier (install.php) maintenant.\n";
    echo "---------------------------------------------------\n";

} catch (Exception $e) {
    // Renvoyer un code d'erreur HTTP si ce n'est pas en CLI
    if (php_sapi_name() !== 'cli') {
        header('HTTP/1.1 500 Internal Server Error');
    }
    die("ERREUR LORS DE L'INSTALLATION : " . $e->getMessage() . "\n");
}
