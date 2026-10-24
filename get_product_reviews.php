<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/cors.php";
require_once "db.php";

$productId = isset($_GET["id"]) ? (int)$_GET["id"] : (isset($_GET["product_id"]) ? (int)$_GET["product_id"] : null);

if (!$productId) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "product_id is required"
    ]);
    exit;
}

try {
    // 1. Calculate average rating and count
    $stmt = $pdo->prepare("
        SELECT
            COUNT(id) AS total_reviews,
            COALESCE(AVG(rating), 0) AS average_rating
        FROM product_reviews
        WHERE product_id = ? AND status = 'APPROVED'
    ");
    $stmt->execute([$productId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Fetch approved reviews with reviewer names
    $stmt = $pdo->prepare("
        SELECT
            r.id,
            r.rating,
            r.review_title,
            r.review_text,
            r.created_at,
            u.name AS reviewer_name
        FROM product_reviews r
        INNER JOIN users u ON u.id = r.customer_id
        WHERE r.product_id = ? AND r.status = 'APPROVED'
        ORDER BY r.id DESC
    ");
    $stmt->execute([$productId]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "product_id" => $productId,
        "average_rating" => round((float)$stats["average_rating"], 1),
        "total_reviews" => (int)$stats["total_reviews"],
        "reviews" => $reviews
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
