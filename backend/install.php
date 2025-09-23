<?php

/**
 * Crée les tables de la base de données si elles n'existent pas.
 * ce fichier devra etre effacer par l'utilisateur apres installation
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

    // les sections = [ "BTS COM", "BTS SIO", "BACHELOR A", "BACHELOR B", "BACHELOR C" ]
    // les description sont en fonction des sections 'ex pour BTS COM : BTS Communication'
    $sections_sql = "
    CREATE TABLE IF NOT EXISTS sections (
        id " . $id . ",
        title VARCHAR(255) NOT NULL,
        description TEXT,
    );";

    // les promos = [ "2022-2024", "2023-2025", "2024-2026", "2025-2027", "2026-2028" ]
    // les description sont en fonction des promos 'ex pour 2022-2024 : 2022-2024 (2 ans)'
    $promos_sql = "
    CREATE TABLE IF NOT EXISTS promos (
        id " . $id . ",
        title VARCHAR(255) NOT NULL,
        description TEXT,
    );";

    $clients_sql = "
    CREATE TABLE IF NOT EXISTS clients (
        id " . $id . ",
        barcode VARCHAR(255) NOT NULL UNIQUE,
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        email VARCHAR(255),
        phone VARCHAR(50),
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        invalidated_at DATETIME,
        invalidated_reason TEXT,
        deleted_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        promo_id INT NOT NULL,
        section_id INT NOT NULL,
    );";

    $articles_sql = "
    CREATE TABLE IF NOT EXISTS articles (
        id " . $id . ",
        barcode VARCHAR(255) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100),
        `condition` VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );";

    $movements_sql = "
    CREATE TABLE IF NOT EXISTS movements (
        id " . $id . ",
        article_id INT NOT NULL,
        client_id INT,
        type VARCHAR(4) NOT NULL,
        occurred_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        note TEXT,
        FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT ON UPDATE RESTRICT
    );";


    // Exécuter les créations de table
    $pdo->exec($users_sql);
    $pdo->exec($badges_sql);
    $pdo->exec($user_badges_sql);
    $pdo->exec($sondages_sql);
    $pdo->exec($sections_sql);
    $pdo->exec($promos_sql);
    $pdo->exec($clients_sql);
    $pdo->exec($articles_sql);
    $pdo->exec($movements_sql);

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

    // Ajout des index pour les nouvelles tables
    // $pdo->exec("CREATE INDEX IF NOT EXISTS idx_clients_search ON clients (last_name, first_name, email, phone);");
    // $pdo->exec("CREATE INDEX IF NOT EXISTS idx_movements_article_time ON movements (article_id, occurred_at);");
    // $pdo->exec("CREATE INDEX IF NOT EXISTS idx_movements_client_time ON movements (client_id, occurred_at);");
    // $pdo->exec("CREATE INDEX IF NOT EXISTS idx_movements_type_time ON movements (type, occurred_at);");


    if ($driver === 'sqlite') {
        // Ajout des index (SQLite supporte IF NOT EXISTS)
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_clients_search ON clients (last_name, first_name, email, phone);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_movements_article_time ON movements (article_id, occurred_at);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_movements_client_time ON movements (client_id, occurred_at);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_movements_type_time ON movements (type, occurred_at);");
    } else { // MySQL (pas de IF NOT EXISTS sur CREATE INDEX)
        /**
         * Crée un index s'il n'existe pas déjà (MySQL).
         * @param PDO    $pdo
         * @param string $table       Nom de la table
         * @param string $indexName   Nom de l'index
         * @param string $columnsList Liste des colonnes de l'index, telle que "col1, col2"
         */
        $createIndexIfMissing = function (PDO $pdo, string $table, string $indexName, string $columnsList) {
            // Vérifier l'existence de l'index dans le schéma courant
            $sql = "SELECT 1
                    FROM information_schema.statistics
                    WHERE table_schema = DATABASE()
                    AND table_name = :table
                    AND index_name = :idx
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':table' => $table, ':idx' => $indexName]);
            $exists = (bool) $stmt->fetchColumn();

            if (!$exists) {
                // Créer l'index
                // ⚠️ suppose que $table, $indexName et $columnsList sont sûrs (noms constants du code)
                $pdo->exec("CREATE INDEX `$indexName` ON `$table` ($columnsList);");
            }
        };

        // Ajout des index (MySQL)
        $createIndexIfMissing($pdo, 'clients',   'idx_clients_search',           'last_name, first_name, email, phone');
        $createIndexIfMissing($pdo, 'movements', 'idx_movements_article_time',   'article_id, occurred_at');
        $createIndexIfMissing($pdo, 'movements', 'idx_movements_client_time',    'client_id, occurred_at');
        $createIndexIfMissing($pdo, 'movements', 'idx_movements_type_time',      '`type`, occurred_at'); // `type` est un mot réservé, mieux l’échapper
    }
}
