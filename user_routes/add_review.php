<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/../cors.php";
require_once "../db.php";
require_once "../auth/auth_middleware.php";

$user = authenticate();

$data = json_decode(file_get_contents("php://input"), true);

$productId   = isset($data["product_id"]) ? (int)$data["product_id"] : null;
$rating      = isset($data["rating"]) ? (int)$data["rating"] : null;
$reviewTitle = trim($data["review_title"] ?? "");
$reviewText  = trim($data["review_text"] ?? "");

if (!$productId || $rating === null) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "product_id and rating (1-5) are required"
    ]);
    exit;
}

if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "rating must be an integer between 1 and 5"
    ]);
    exit;
}

try {
    // Check product exists
    $stmt = $pdo->prepare("SELECT id, name FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Product not found"
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO product_reviews (
            product_id,
            customer_id,
            rating,
            review_title,
            review_text,
            status
        )
        VALUES (?, ?, ?, ?, ?, 'APPROVED')
    ");

    $stmt->execute([
        $productId,
        $user->id,
        $rating,
        $reviewTitle ?: null,
        $reviewText ?: null
    ]);

    $reviewId = $pdo->lastInsertId();

    echo json_encode([
        "success" => true,
        "message" => "Review submitted successfully",
        "review_id" => (int)$reviewId,
        "product_name" => $product["name"],
        "rating" => $rating
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
