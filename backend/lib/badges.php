<?php

/**
 * Récupère la liste de tous les badges disponibles (placeholder).
 *
 * @param PDO $pdo
 * @return array
 */
function get_all_badges(PDO $pdo): array {
    // Pour la phase 1, on retourne des données statiques.
    // Pour la phase 2, ceci ferait une requête `SELECT * FROM badges`.
    return [
        ['id' => 1, 'slug' => 'pionnier', 'label' => 'Pionnier', 'color' => '#4a90e2', 'description' => 'A rejoint la plateforme durant la première semaine.'],
        ['id' => 2, 'slug' => 'beta-testeur', 'label' => 'Bêta-Testeur', 'color' => '#7ed321', 'description' => 'A participé au programme de bêta-test.'],
        ['id' => 3, 'slug' => 'curieux', 'label' => 'Curieux', 'color' => '#f5a623', 'description' => 'A exploré toutes les sections du site.'],
    ];
}

/**
 * Récupère les badges d'un utilisateur spécifique (placeholder).
 *
 * @param PDO $pdo
 * @param int $userId
 * @return array
 */
function get_user_badges(PDO $pdo, int $userId): array {
    // Pour la phase 1, on retourne des données statiques.
    // Pour la phase 2, ceci ferait une requête complexe avec jointure.
    // On simule que l'utilisateur a le badge "Pionnier".
    return [
        ['slug' => 'pionnier', 'label' => 'Pionnier', 'color' => '#4a90e2', 'level' => 1, 'awarded_at' => '2024-01-15 10:00:00'],
    ];
}
