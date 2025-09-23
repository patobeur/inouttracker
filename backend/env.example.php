<?php

// Fichier de configuration d'exemple.
// Copier ce fichier en `env.php` et ajuster les valeurs.
// Le fichier `env.php` est ignoré par Git pour des raisons de sécurité.

return [
    // -- Configuration de la base de données --
    // Driver: 'sqlite' ou 'mysql'
    'DB_DRIVER' => 'sqlite',

    // Pour SQLite
    'DB_PATH' => __DIR__ . '/storage/app.db',

    // Pour MySQL
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_NAME' => 'votre_db_nom',
    'DB_USER' => 'votre_db_user',
    'DB_PASS' => 'votre_db_pass',
    'DB_CHARSET' => 'utf8mb4',

    // -- Configuration de l'envoi d'e-mails (PHPMailer) --
    'MAIL_HOST' => 'smtp.example.com',
    'MAIL_PORT' => 587,
    'MAIL_USERNAME' => 'user@example.com',
    'MAIL_PASSWORD' => 'secret',
    'MAIL_FROM_ADDRESS' => 'noreply@example.com',
    'MAIL_FROM_NAME' => 'InOutTracker',
    'MAIL_ENCRYPTION' => 'tls', // 'tls' ou 'ssl'

    // -- Paramètres de l'application --
    'APP_URL' => 'http://localhost:8000', // URL publique de l'application
    'DEBUG' => true, // Activer/désactiver les messages d'erreur détaillés
];
