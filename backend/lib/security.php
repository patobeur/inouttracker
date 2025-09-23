<?php

/**
 * Initialise le token CSRF dans la session s'il n'existe pas.
 */
function init_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Vérifie le token CSRF soumis via une requête POST.
 *
 * @param string|null $token Le token reçu du client. Si null, essaie de le lire depuis $_POST.
 * @return bool Vrai si le token est valide, faux sinon.
 */
function verify_csrf_token($token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }

    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }

    return true;
}

/**
 * Applique une limitation de débit simple basée sur l'IP.
 *
 * @param string $action L'action pour laquelle on limite le débit.
 * @param int $maxRequests Le nombre maximum de requêtes autorisées.
 * @param int $period La période en secondes.
 */
function rate_limit($action, $maxRequests = 10, $period = 60) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = "rate_limit_{$action}_{$ip}";

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }

    $now = time();
    // Nettoyer les timestamps expirés
    $_SESSION[$key] = array_filter($_SESSION[$key], function ($timestamp) use ($now, $period) {
        return ($now - $timestamp) < $period;
    });

    if (count($_SESSION[$key]) >= $maxRequests) {
        send_error_response('Trop de tentatives. Veuillez réessayer plus tard.', 429);
    }

    // Enregistrer la tentative actuelle
    $_SESSION[$key][] = $now;
}

/**
 * Valide et nettoie une entrée utilisateur.
 *
 * @param string $data La donnée à nettoyer.
 * @return string La donnée nettoyée.
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
