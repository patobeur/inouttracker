<?php

/**
 * Récupère tous les utilisateurs (pour les admins).
 *
 * @param PDO $pdo
 * @return array
 */
function get_all_users(PDO $pdo): array {
    $stmt = $pdo->query("SELECT id, email, pseudo, first_name, last_name, is_admin, created_at, updated_at FROM users ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

/**
 * Promeut un utilisateur au rang d'administrateur.
 *
 * @param PDO $pdo
 * @param int $userId
 * @return bool
 */
function promote_user_to_admin(PDO $pdo, int $userId): bool {
    $stmt = $pdo->prepare("UPDATE users SET is_admin = 1 WHERE id = ?");
    return $stmt->execute([$userId]);
}

/**
 * Rétrograde un administrateur au rang d'utilisateur standard.
 *
 * @param PDO $pdo
 * @param int $userId
 * @return bool
 */
function demote_user_from_admin(PDO $pdo, int $userId): bool {
    $stmt = $pdo->prepare("UPDATE users SET is_admin = 0 WHERE id = ?");
    return $stmt->execute([$userId]);
}

/**
 * Récupère les données agrégées pour le tableau de bord de l'administration.
 *
 * @param PDO $pdo
 * @return array
 */
function get_admin_dashboard_data(PDO $pdo): array {
    $data = [];

    // Nombre total d'utilisateurs
    $stmt_users = $pdo->query("SELECT COUNT(*) FROM users");
    $data['total_users'] = $stmt_users->fetchColumn();

    // Nombre total de sondages (supposant une table 'sondages')
    try {
        $stmt_polls = $pdo->query("SELECT COUNT(*) FROM sondages");
        $data['total_polls'] = $stmt_polls->fetchColumn();
    } catch (PDOException $e) {
        // Si la table n'existe pas, on met 0
        $data['total_polls'] = 0;
    }

    // Nombre de sondages terminés (supposant une colonne 'status' dans 'sondages')
    try {
        $stmt_finished_polls = $pdo->query("SELECT COUNT(*) FROM sondages WHERE status = 'finished'");
        $data['finished_polls'] = $stmt_finished_polls->fetchColumn();
    } catch (PDOException $e) {
        $data['finished_polls'] = 0;
    }

    // Nombre total de badges décernés (supposant une table 'user_badges')
    try {
        $stmt_badges = $pdo->query("SELECT COUNT(*) FROM user_badges");
        $data['total_badges_awarded'] = $stmt_badges->fetchColumn();
    } catch (PDOException $e) {
        $data['total_badges_awarded'] = 0;
    }

    // Top 10 des utilisateurs par points (supposant une colonne 'total_points' dans 'users')
    try {
        $stmt_leaderboard = $pdo->query("SELECT pseudo, total_points FROM users ORDER BY total_points DESC, pseudo ASC LIMIT 10");
        $data['top_10_users_by_points'] = $stmt_leaderboard->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $data['top_10_users_by_points'] = [];
    }

    return $data;
}
