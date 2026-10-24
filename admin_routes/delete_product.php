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
require_once "../auth/admin_middleware.php";

requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);
$productId = $data["product_id"] ?? $data["id"] ?? null;
$force = (bool)($data["force"] ?? false);

if (!$productId || !is_numeric($productId)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Valid product_id is required"
    ]);
    exit;
}

try {
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

    if ($force) {
        // Attempt hard delete if no constraints
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            echo json_encode([
                "success" => true,
                "message" => "Product permanently deleted",
                "product_id" => (int)$productId
            ]);
            exit;
        } catch (PDOException $e) {
            // Cannot hard delete due to foreign keys, proceed to soft delete
        }
    }

    // Default: Soft delete by setting status to INACTIVE
    $stmt = $pdo->prepare("UPDATE products SET status = 'INACTIVE' WHERE id = ?");
    $stmt->execute([$productId]);

    echo json_encode([
        "success" => true,
        "message" => "Product status set to INACTIVE (soft-deleted)",
        "product_id" => (int)$productId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
