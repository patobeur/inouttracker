<?php

/**
 * Récupère tous les clients de la base de données.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @return array La liste des clients.
 */
function get_all_clients(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT * FROM clients WHERE deleted_at IS NULL ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère un client spécifique par son ID.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id L'ID du client.
 * @return array|false Les données du client ou false si non trouvé.
 */
function get_client(PDO $pdo, int $id)
{
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Crée un nouveau client.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param array $data Les données du client.
 * @return bool True si la création a réussi, false sinon.
 */
function create_client(PDO $pdo, array $data): bool
{
    $sql = "INSERT INTO clients (barcode, first_name, last_name, email, phone, promo_id, section_id, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['barcode'],
        $data['first_name'],
        $data['last_name'],
        $data['email'] ?? null,
        $data['phone'] ?? null,
        $data['promo_id'],
        $data['section_id']
    ]);
}

/**
 * Met à jour un client existant.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id L'ID du client à mettre à jour.
 * @param array $data Les nouvelles données du client.
 * @return bool True si la mise à jour a réussi, false sinon.
 */
function update_client(PDO $pdo, int $id, array $data): bool
{
    $sql = "UPDATE clients SET barcode = ?, first_name = ?, last_name = ?, email = ?, phone = ?, promo_id = ?, section_id = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['barcode'],
        $data['first_name'],
        $data['last_name'],
        $data['email'] ?? null,
        $data['phone'] ?? null,
        $data['promo_id'],
        $data['section_id'],
        $id
    ]);
}

/**
 * Supprime un client (soft delete).
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id L'ID du client à supprimer.
 * @return bool True si la suppression a réussi, false sinon.
 */
function delete_client(PDO $pdo, int $id): bool
{
    // Avant de supprimer, vérifier s'il y a des mouvements associés
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM movements WHERE client_id = ?");
    $stmt_check->execute([$id]);
    if ($stmt_check->fetchColumn() > 0) {
        // On ne peut pas supprimer un client qui a des mouvements
        return false;
    }

    $stmt = $pdo->prepare("UPDATE clients SET deleted_at = NOW() WHERE id = ?");
    return $stmt->execute([$id]);
}