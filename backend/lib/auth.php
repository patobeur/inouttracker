<?php

/**
 * Enregistre un nouvel utilisateur.
 *
 * @param PDO $pdo
 * @param string $email
 * @param string $pseudo
 * @param string $password
 * @return array Résultat de l'opération.
 */
function register_user(PDO $pdo, string $email, string $pseudo, string $password): array {
    // Validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Format d\'email invalide.'];
    }
    if (strlen($pseudo) < 3 || strlen($pseudo) > 50) {
        return ['success' => false, 'message' => 'Le pseudo doit contenir entre 3 et 50 caractères.'];
    }
    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.'];
    }

    // Vérifier l'unicité de l'email et du pseudo
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR pseudo = ?");
    $stmt->execute([$email, $pseudo]);
    if ($stmt->fetchColumn() > 0) {
        return ['success' => false, 'message' => 'L\'email ou le pseudo est déjà utilisé.'];
    }

    // Hachage du mot de passe
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insertion dans la base de données
    $stmt = $pdo->prepare(
        "INSERT INTO users (email, pseudo, password_hash) VALUES (?, ?, ?)"
    );
    if ($stmt->execute([$email, $pseudo, $password_hash])) {
        return ['success' => true, 'message' => 'Inscription réussie.'];
    }

    return ['success' => false, 'message' => 'Une erreur est survenue lors de l\'inscription.'];
}

/**
 * Connecte un utilisateur.
 *
 * @param PDO $pdo
 * @param string $email
 * @param string $password
 * @return bool Vrai si la connexion est réussie, faux sinon.
 */
function login_user(PDO $pdo, string $email, string $password): bool {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Régénérer l'ID de session pour éviter la fixation de session
        session_regenerate_id(true);

        // Stocker les informations de l'utilisateur dans la session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_pseudo'] = $user['pseudo'];
        $_SESSION['is_admin'] = (bool)($user['is_admin'] ?? 0);
        return true;
    }

    return false;
}

/**
 * Déconnecte l'utilisateur actuel.
 */
function logout_user() {
    // Vider toutes les variables de session
    $_SESSION = [];

    // Détruire le cookie de session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Finalement, détruire la session.
    session_destroy();
}

/**
 * Vérifie si un utilisateur est connecté.
 *
 * @return bool
 */
function is_user_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Vérifie si un utilisateur est administrateur.
 *
 * @return bool
 */
function is_user_admin(): bool {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Récupère l'ID de l'utilisateur connecté.
 *
 * @return int|null
 */
function get_current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Gère une demande de réinitialisation de mot de passe.
 *
 * @param PDO $pdo
 * @param array $config
 * @param string $email
 * @return bool
 */
function request_password_reset(PDO $pdo, array $config, string $email): bool {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Générer un token sécurisé
        $token = bin2hex(random_bytes(32));
        $expires = new DateTime('+1 hour');

        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE id = ?");
        $stmt->execute([$token, $expires->format('Y-m-d H:i:s'), $user['id']]);

        // Inclure et utiliser le mailer
        require_once __DIR__ . '/mailer.php';
        send_password_reset_email($config, $email, $token);
    }

    // On retourne toujours vrai pour ne pas révéler si un email existe dans le système.
    return true;
}

/**
 * Confirme la réinitialisation du mot de passe avec un token.
 *
 * @param PDO $pdo
 * @param string $token
 * @param string $newPassword
 * @return array
 */
function confirm_password_reset(PDO $pdo, string $token, string $newPassword): array {
    // Validation
    if (empty($token)) {
        return ['success' => false, 'message' => 'Token manquant.'];
    }
     if (strlen($newPassword) < 8) {
        return ['success' => false, 'message' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.'];
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Token invalide.'];
    }

    // Vérifier l'expiration du token
    $expires = new DateTime($user['reset_expires_at']);
    if (new DateTime() > $expires) {
        return ['success' => false, 'message' => 'Le token a expiré.'];
    }

    // Tout est bon, on met à jour le mot de passe et on supprime le token
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        "UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires_at = NULL WHERE id = ?"
    );

    if ($stmt->execute([$newPasswordHash, $user['id']])) {
        return ['success' => true, 'message' => 'Mot de passe réinitialisé avec succès.'];
    }

    return ['success' => false, 'message' => 'Erreur lors de la mise à jour du mot de passe.'];
}
