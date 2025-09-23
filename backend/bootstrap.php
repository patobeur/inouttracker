<?php

// Démarrer la temporisation de sortie pour capturer toute sortie précoce (espaces, erreurs)
ob_start();

// Affiche toutes les erreurs si le mode debug est activé
if (file_exists(__DIR__ . '/env.php') && (require __DIR__ . '/env.php')['DEBUG']) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    // TODO: Mettre en place un logging des erreurs dans /backend/storage/logs
}

// -- Gestion globale des erreurs et exceptions --
// Inclure response.php immédiatement pour qu'il soit disponible dans le gestionnaire d'exceptions.
require_once __DIR__ . '/lib/response.php';

set_exception_handler(function ($exception) {
    // On ne peut pas dépendre de la configuration globale ici, car une erreur peut
    // survenir avant son chargement. On la charge donc spécifiquement.
    $config = [];
    if (file_exists(__DIR__ . '/env.php')) {
        $config = require __DIR__ . '/env.php';
    } else {
        $config = require __DIR__ . '/env.example.php';
    }

    $isDebug = $config['DEBUG'] ?? false;
    $errorMessage = 'Une erreur serveur est survenue.';
    $errorDetails = null;

    // En mode debug, on affiche les détails de l'erreur
    if ($isDebug) {
        $errorMessage = $exception->getMessage();
        $errorDetails = [
            'type' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => explode("\n", $exception->getTraceAsString()),
        ];
    }

    // Logger l'erreur (peut être étendu pour logger dans un fichier)
    error_log($exception);

    // La fonction send_error_response est disponible car response.php est inclus avant.
    send_error_response($errorMessage, 500, $errorDetails);
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // Ce code d'erreur n'est pas inclus dans error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});


// -- Chargement de la configuration --
$config = [];
if (file_exists(__DIR__ . '/env.php')) {
    $config = require __DIR__ . '/env.php';
} else {
    // Si env.php n'existe pas, on utilise les valeurs par défaut de env.example.php
    // C'est utile pour la première installation et pour SQLite qui ne nécessite pas de mot de passe.
    $config = require __DIR__ . '/env.example.php';
}

// -- Configuration de la session --
// Définir les paramètres de cookie avant de démarrer la session
session_set_cookie_params([
    'lifetime' => 86400, // 24 heures
    'path' => '/',
    'domain' => '', // Mettre le domaine si nécessaire
    'secure' => isset($_SERVER['HTTPS']), // Vrai si en HTTPS
    'httponly' => true, // Empêche l'accès via JavaScript
    'samesite' => 'Lax' // Protection contre les attaques CSRF
]);

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -- Headers de sécurité --
// Ne pas envoyer l'en-tête X-Powered-By
header_remove('X-Powered-By');

// Empêcher le navigateur de deviner le type MIME
header('X-Content-Type-Options: nosniff');

// Empêcher le chargement de la page dans une frame/iframe
header('X-Frame-Options: DENY');

// Politique de sécurité de contenu (CSP) simple
// À adapter selon les besoins (par exemple, pour autoriser des polices ou des scripts externes)
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

// -- Inclusion des dépendances --
require_once __DIR__ . '/lib/security.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/profile.php';
require_once __DIR__ . '/lib/badges.php';
require_once __DIR__ . '/lib/admin.php';
require_once __DIR__ . '/lib/articles/articles.php';

// require_once __DIR__ . '/lib/mailer.php'; // Sera inclus plus tard quand nécessaire

// Initialiser la connexion à la base de données
try {
    $pdo = get_db_connection($config);
} catch (PDOException $e) {
    // En cas d'échec de connexion, on envoie une réponse d'erreur générique
    send_json_response(['error' => 'Service temporairement indisponible.'], 503);
    exit;
}

// Initialiser le token CSRF s'il n'existe pas
init_csrf_token();
