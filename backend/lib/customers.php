<?php

/**
 * Récupère tous les clients de la base de données.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @return array La liste des clients.
 */
function get_all_customers(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT * FROM customers ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère un client spécifique par son ID.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id L'ID du client.
 * @return array|false Les données du client ou false si non trouvé.
 */
function get_customer(PDO $pdo, int $id)
{
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Crée un nouvel client.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param array $data Les données du client (name, email, phone, address).
 * @return bool True si la création a réussi, false sinon.
 */
function create_customer(PDO $pdo, array $data): bool
{
    $sql = "INSERT INTO customers (name, email, phone, address, updated_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['name'],
        $data['email'] ?? null,
        $data['phone'] ?? null,
        $data['address'] ?? null
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
function update_customer(PDO $pdo, int $id, array $data): bool
{
    $sql = "UPDATE customers SET name = ?, email = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['name'],
        $data['email'] ?? null,
        $data['phone'] ?? null,
        $data['address'] ?? null,
        $id
    ]);
}

/**
 * Supprime un client.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id L'ID du client à supprimer.
 * @return bool True si la suppression a réussi, false sinon.
 */
function delete_customer(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
    return $stmt->execute([$id]);
}