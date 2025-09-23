<?php

/**
 * Récupère tous les articles de la base de données.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @return array La liste des articles.
 */
function get_all_articles(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT * FROM articles ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère un article spécifique par son ID.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id L'ID de l'article.
 * @return array|false Les données de l'article ou false si non trouvé.
 */
function get_article(PDO $pdo, int $id)
{
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Crée un nouvel article.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param array $data Les données de l'article (barcode, name, category, condition).
 * @return bool True si la création a réussi, false sinon.
 */
function create_article(PDO $pdo, array $data): bool
{
    $sql = "INSERT INTO articles (barcode, name, category, `condition`, updated_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['barcode'],
        $data['name'],
        $data['category'] ?? null,
        $data['condition'] ?? null
    ]);
}

/**
 * Met à jour un article existant.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id L'ID de l'article à mettre à jour.
 * @param array $data Les nouvelles données de l'article.
 * @return bool True si la mise à jour a réussi, false sinon.
 */
function update_article(PDO $pdo, int $id, array $data): bool
{
    $sql = "UPDATE articles SET barcode = ?, name = ?, category = ?, `condition` = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['barcode'],
        $data['name'],
        $data['category'] ?? null,
        $data['condition'] ?? null,
        $id
    ]);
}

/**
 * Supprime un article.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id L'ID de l'article à supprimer.
 * @return bool True si la suppression a réussi, false sinon.
 */
function delete_article(PDO $pdo, int $id): bool
{
    // Avant de supprimer, vérifier s'il y a des mouvements associés
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM movements WHERE article_id = ?");
    $stmt_check->execute([$id]);
    if ($stmt_check->fetchColumn() > 0) {
        // On ne peut pas supprimer un article qui a des mouvements
        // On pourrait aussi envisager une suppression logique (soft delete)
        return false;
    }

    $stmt = $pdo->prepare("DELETE FROM articles WHERE id = ?");
    return $stmt->execute([$id]);
}
