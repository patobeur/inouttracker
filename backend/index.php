<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/customers.php';

// Gérer les requêtes JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $json_data = file_get_contents('php://input');
    $decoded_data = json_decode($json_data, true);
    if (is_array($decoded_data)) {
        $_POST = array_merge($_POST, $decoded_data);
    }
}

// Le routeur principal de l'API
$action = $_REQUEST['action'] ?? '';

// On s'assure que pdo est bien défini après bootstrap.php
global $pdo, $config;

// Routes qui ne nécessitent pas d'authentification
switch ($action) {
    case 'status':
        // Cet endpoint vérifie si l'application est installée (si la table users existe)
        $is_installed = table_exists($pdo, 'users');
        send_json_response(['installed' => $is_installed]);
        break;

    case 'register':
        rate_limit('register', 5, 3600); // Limite à 5 inscriptions par heure par IP
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            send_error_response('Méthode non autorisée', 405);
        }

        $email = sanitize_input($_POST['email'] ?? '');
        $pseudo = sanitize_input($_POST['pseudo'] ?? '');
        $password = $_POST['password'] ?? ''; // Ne pas sanitizer le mot de passe

        $result = register_user($pdo, $email, $pseudo, $password);

        if ($result['success']) {
            send_json_response(['message' => $result['message']], 201);
        } else {
            send_error_response($result['message'], 400);
        }
        break;

    case 'login':
        rate_limit('login', 10, 60); // Limite à 10 tentatives par minute
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            send_error_response('Méthode non autorisée', 405);
        }

        $email = sanitize_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (login_user($pdo, $email, $password)) {
            send_json_response([
                'message' => 'Connexion réussie.',
                'user' => [
                    'pseudo' => $_SESSION['user_pseudo'],
                    'is_admin' => $_SESSION['is_admin']
                ],
                'csrf_token' => $_SESSION['csrf_token']
            ]);
        } else {
            send_error_response('Email ou mot de passe incorrect.', 401);
        }
        break;

    case 'logout':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            send_error_response('Méthode non autorisée', 405);
        }
        if (!verify_csrf_token()) {
             send_error_response('Token CSRF invalide.', 403);
        }
        logout_user();
        send_json_response(['message' => 'Déconnexion réussie.']);
        break;

    case 'get_csrf_token':
        // Endpoint pour récupérer le token CSRF initial
        send_json_response(['csrf_token' => $_SESSION['csrf_token']]);
        break;

    case 'request_reset':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            send_error_response('Méthode non autorisée', 405);
        }
        rate_limit('reset_request', 5, 3600);
        $email = sanitize_input($_POST['email'] ?? '');
        request_password_reset($pdo, $config, $email);
        // Toujours renvoyer un succès pour des raisons de sécurité
        send_json_response(['message' => 'Si un compte avec cet email existe, un lien de réinitialisation a été envoyé.']);
        break;

    case 'confirm_reset':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            send_error_response('Méthode non autorisée', 405);
        }
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';

        $result = confirm_password_reset($pdo, $token, $password);

        if ($result['success']) {
            send_json_response(['message' => $result['message']]);
        } else {
            send_error_response($result['message'], 400);
        }
        break;

    case 'install':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            send_error_response('Méthode non autorisée', 405);
        }

        $install_file = __DIR__ . '/install.php';
        if (file_exists($install_file)) {
            require_once $install_file;
            $result = run_installation($pdo);

            if ($result['success']) {
                // Renommer le fichier d'installation pour des raisons de sécurité
                $renamed_file = __DIR__ . '/install_a_effacer.php';
                if (rename($install_file, $renamed_file)) {
                    send_json_response([
                        'success' => true,
                        'message' => "L'installation a réussi ! Le fichier d'installation a été renommé en 'install_a_effacer.php'. Vous pouvez maintenant le supprimer."
                    ]);
                } else {
                    send_error_response("L'installation a réussi, mais le renommage du fichier install.php a échoué. Veuillez le faire manuellement.", 500);
                }
            } else {
                send_error_response("L'installation a échoué : " . ($result['error'] ?? 'Erreur inconnue'), 500);
            }
        } else {
            send_error_response("Le fichier d'installation n'a pas été trouvé ou l'application est déjà installée.", 404);
        }
        break;
}

// Routes qui nécessitent une authentification
if (!is_user_logged_in()) {
    // Si l'action n'est pas une action publique, et que l'utilisateur n'est pas connecté,
    // on renvoie une erreur 401, sauf si une réponse a déjà été envoyée.
    $public_actions = ['register', 'login', 'get_csrf_token', 'request_reset', 'confirm_reset', 'install', 'status'];
    if ($action !== '' && !in_array($action, $public_actions)) {
         send_error_response('Authentification requise.', 401);
    } else if ($action === '') {
        // Cas où aucune action n'est spécifiée
        send_error_response('Action non spécifiée.', 400);
    }
    exit;
}

// -- A PARTIR D'ICI, L'UTILISATEUR EST AUTHENTIFIÉ --
// Vérification du token CSRF pour toutes les requêtes POST authentifiées
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token()) {
    send_error_response('Token CSRF invalide.', 403);
}

// Le switch pour les actions authentifiées
switch ($action) {
    case 'me':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            send_error_response('Méthode non autorisée', 405);
        }
        $profile = get_user_profile($pdo, get_current_user_id());
        if ($profile) {
            // On renvoie le token CSRF avec les données du profil
            // pour que le client puisse l'utiliser pour les requêtes suivantes.
            $profile['csrf_token'] = $_SESSION['csrf_token'];
            send_json_response($profile);
        } else {
            send_error_response('Profil non trouvé.', 404);
        }
        break;

    case 'profile_update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            send_error_response('Méthode non autorisée', 405);
        }

        $data = [
            'pseudo' => sanitize_input($_POST['pseudo'] ?? ''),
            'first_name' => sanitize_input($_POST['first_name'] ?? ''),
            'last_name' => sanitize_input($_POST['last_name'] ?? ''),
        ];

        $result = update_user_profile($pdo, get_current_user_id(), $data);

        if ($result['success']) {
            send_json_response($result);
        } else {
            send_error_response($result['message'], 400);
        }
        break;

    case 'badges':
         if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            send_error_response('Méthode non autorisée', 405);
        }
        $badges = get_user_badges($pdo, get_current_user_id());
        send_json_response($badges);
        break;

    // --- Actions Administrateur ---
    case 'admin/dashboard':
        if (!is_user_admin()) { send_error_response('Accès non autorisé.', 403); }
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { send_error_response('Méthode non autorisée', 405); }

        $dashboard_data = get_admin_dashboard_data($pdo);
        send_json_response($dashboard_data);
        break;

    case 'admin/articles':
        if (!is_user_admin()) { send_error_response('Accès non autorisé.', 403); }
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { send_error_response('Méthode non autorisée', 405); }

        $articles = get_all_articles($pdo);
        send_json_response($articles);
        break;

    case 'admin/articles/create':
        if (!is_user_admin()) { send_error_response('Accès non autorisé.', 403); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { send_error_response('Méthode non autorisée', 405); }

        $data = [
            'barcode' => sanitize_input($_POST['barcode'] ?? ''),
            'name' => sanitize_input($_POST['name'] ?? ''),
            'category' => sanitize_input($_POST['category'] ?? ''),
            'condition' => sanitize_input($_POST['condition'] ?? ''),
        ];

        if (empty($data['barcode']) || empty($data['name'])) {
            send_error_response('Le code-barres et le nom sont requis.', 400);
        }

        if (create_article($pdo, $data)) {
            send_json_response(['success' => true, 'message' => 'Article créé avec succès.']);
        } else {
            send_error_response('Erreur lors de la création de l\'article.', 500);
        }
        break;

    case 'admin/articles/update':
        if (!is_user_admin()) { send_error_response('Accès non autorisé.', 403); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { send_error_response('Méthode non autorisée', 405); }

        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'barcode' => sanitize_input($_POST['barcode'] ?? ''),
            'name' => sanitize_input($_POST['name'] ?? ''),
            'category' => sanitize_input($_POST['category'] ?? ''),
            'condition' => sanitize_input($_POST['condition'] ?? ''),
        ];

        if ($id <= 0 || empty($data['barcode']) || empty($data['name'])) {
            send_error_response('ID, code-barres et nom sont requis.', 400);
        }

        if (update_article($pdo, $id, $data)) {
            send_json_response(['success' => true, 'message' => 'Article mis à jour avec succès.']);
        } else {
            send_error_response('Erreur lors de la mise à jour de l\'article.', 500);
        }
        break;

    case 'admin/articles/delete':
        if (!is_user_admin()) { send_error_response('Accès non autorisé.', 403); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { send_error_response('Méthode non autorisée', 405); }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            send_error_response('ID d\'article invalide.', 400);
        }

        if (delete_article($pdo, $id)) {
            send_json_response(['success' => true, 'message' => 'Article supprimé avec succès.']);
        } else {
            send_error_response('Impossible de supprimer l\'article. Il peut être lié à des mouvements.', 500);
        }
        break;

    case 'admin/customers':
        if (!is_user_admin()) { send_error_response('Accès non autorisé.', 403); }
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { send_error_response('Méthode non autorisée', 405); }

        $customers = get_all_customers($pdo);
        send_json_response($customers);
        break;

    case 'admin/customers/create':
        if (!is_user_admin()) { send_error_response('Accès non autorisé.', 403); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { send_error_response('Méthode non autorisée', 405); }

        $data = [
            'name' => sanitize_input($_POST['name'] ?? ''),
            'email' => sanitize_input($_POST['email'] ?? ''),
            'phone' => sanitize_input($_POST['phone'] ?? ''),
            'address' => sanitize_input($_POST['address'] ?? ''),
        ];

        if (empty($data['name'])) {
            send_error_response('Le nom est requis.', 400);
        }

        if (create_customer($pdo, $data)) {
            send_json_response(['success' => true, 'message' => 'Client créé avec succès.']);
        } else {
            send_error_response('Erreur lors de la création du client.', 500);
        }
        break;

    case 'admin/customers/update':
        if (!is_user_admin()) { send_error_response('Accès non autorisé.', 403); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { send_error_response('Méthode non autorisée', 405); }

        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'name' => sanitize_input($_POST['name'] ?? ''),
            'email' => sanitize_input($_POST['email'] ?? ''),
            'phone' => sanitize_input($_POST['phone'] ?? ''),
            'address' => sanitize_input($_POST['address'] ?? ''),
        ];

        if ($id <= 0 || empty($data['name'])) {
            send_error_response('ID et nom sont requis.', 400);
        }

        if (update_customer($pdo, $id, $data)) {
            send_json_response(['success' => true, 'message' => 'Client mis à jour avec succès.']);
        } else {
            send_error_response('Erreur lors de la mise à jour du client.', 500);
        }
        break;

    case 'admin/customers/delete':
        if (!is_user_admin()) { send_error_response('Accès non autorisé.', 403); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { send_error_response('Méthode non autorisée', 405); }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            send_error_response('ID de client invalide.', 400);
        }

        if (delete_customer($pdo, $id)) {
            send_json_response(['success' => true, 'message' => 'Client supprimé avec succès.']);
        } else {
            send_error_response('Impossible de supprimer le client.', 500);
        }
        break;

    case 'admin_get_users':
        if (!is_user_admin()) { send_error_response('Accès non autorisé.', 403); }
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { send_error_response('Méthode non autorisée', 405); }

        $users = get_all_users($pdo);
        send_json_response($users);
        break;

    case 'admin_promote_user':
        if (!is_user_admin()) { send_error_response('Accès non autorisé.', 403); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { send_error_response('Méthode non autorisée', 405); }

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0 && promote_user_to_admin($pdo, $userId)) {
            send_json_response(['success' => true, 'message' => 'Utilisateur promu administrateur.']);
        } else {
            send_error_response('Impossible de promouvoir l\'utilisateur.', 400);
        }
        break;

    case 'admin_demote_user':
        if (!is_user_admin()) { send_error_response('Accès non autorisé.', 403); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { send_error_response('Méthode non autorisée', 405); }

        $userId = (int)($_POST['user_id'] ?? 0);
        // Empêcher un admin de se rétrograder lui-même
        if ($userId === get_current_user_id()) {
            send_error_response('Vous ne pouvez pas vous rétrograder vous-même.', 403);
        }

        if ($userId > 0 && demote_user_from_admin($pdo, $userId)) {
            send_json_response(['success' => true, 'message' => 'Administrateur rétrogradé.']);
        } else {
            send_error_response('Impossible de rétrograder l\'administrateur.', 400);
        }
        break;

    default:
        // Si l'action n'est reconnue ni dans les routes publiques, ni ici, c'est une erreur.
        send_error_response('Action non reconnue.', 404);
        break;
}
