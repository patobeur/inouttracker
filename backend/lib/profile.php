<?php

/**
 * Récupère les informations de profil d'un utilisateur.
 *
 * @param PDO $pdo
 * @param int $userId
 * @return array|null Les données du profil ou null si non trouvé.
 */
function get_user_profile(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("SELECT id, email, pseudo, first_name, last_name, is_admin, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();

    return $profile ?: null;
}

/**
 * Met à jour le profil d'un utilisateur.
 *
 * @param PDO $pdo
 * @param int $userId
 * @param array $data Les données à mettre à jour (pseudo, first_name, last_name)
 * @return array Résultat de l'opération.
 */
function update_user_profile(PDO $pdo, int $userId, array $data): array {
    // 1. Récupérer le profil existant pour obtenir les valeurs actuelles
    $existingProfile = get_user_profile($pdo, $userId);
    if (!$existingProfile) {
        return ['success' => false, 'message' => 'Utilisateur non trouvé.'];
    }

    // 2. Fusionner les nouvelles données avec les données existantes
    // Les valeurs dans $data écraseront celles dans $existingProfile
    $updatedData = array_merge($existingProfile, $data);

    // 3. Valider les données fusionnées
    $pseudo = $updatedData['pseudo'];
    if (empty($pseudo)) {
        return ['success' => false, 'message' => 'Le pseudo ne peut pas être vide.'];
    }
    if (strlen($pseudo) < 3 || strlen($pseudo) > 50) {
        return ['success' => false, 'message' => 'Le pseudo doit contenir entre 3 et 50 caractères.'];
    }

    // 4. Vérifier si le nouveau pseudo est déjà pris par un autre utilisateur
    // On vérifie seulement si le pseudo a réellement changé
    if (isset($data['pseudo']) && $data['pseudo'] !== $existingProfile['pseudo']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE pseudo = ? AND id != ?");
        $stmt->execute([$data['pseudo'], $userId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Ce pseudo est déjà utilisé.'];
        }
    }

    // 5. Construire la requête de mise à jour uniquement avec les champs qui ont changé
    $fieldsToUpdate = [];
    $params = [];
    foreach ($data as $key => $value) {
        // On ne met à jour que les champs autorisés et qui ont réellement une nouvelle valeur
        if (in_array($key, ['pseudo', 'first_name', 'last_name']) && $value !== $existingProfile[$key]) {
            $fieldsToUpdate[] = "$key = ?";
            $params[] = $value;
        }
    }

    // S'il n'y a rien à mettre à jour, on retourne un succès sans rien faire
    if (count($fieldsToUpdate) === 0) {
        return ['success' => true, 'message' => 'Aucune modification détectée.'];
    }

    $fieldsToUpdate[] = "updated_at = CURRENT_TIMESTAMP";

    $query = "UPDATE users SET " . implode(', ', $fieldsToUpdate) . " WHERE id = ?";
    $params[] = $userId;

    $stmt = $pdo->prepare($query);

    if ($stmt->execute($params)) {
        // Mettre à jour le pseudo dans la session également, s'il a été changé
        if (isset($data['pseudo'])) {
            $_SESSION['user_pseudo'] = $data['pseudo'];
        }
        return ['success' => true, 'message' => 'Profil mis à jour avec succès.'];
    }

    return ['success' => false, 'message' => 'Une erreur est survenue lors de la mise à jour.'];
}
