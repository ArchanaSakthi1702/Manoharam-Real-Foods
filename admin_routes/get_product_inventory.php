<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/../cors.php";
require_once "../db.php";
require_once "../auth/admin_middleware.php";

requireAdmin();

try {

    $productId = $_GET['product_id'] ?? null;

    if (!$productId || !is_numeric($productId)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Valid product_id is required"
        ]);

        exit;
    }

    // Product
    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            category,
            quantity,
            unit,
            selling_price,
            description,
            image_url,
            status
        FROM products
        WHERE id = ?
    ");

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

    // Batches
    $stmt = $pdo->prepare("
        SELECT
            id,
            batch_no,
            manufactured_date,
            expiry_date,
            quantity,
            remaining_quantity,
            created_at
        FROM inventory_batches
        WHERE product_id = ?
        ORDER BY expiry_date ASC, id ASC
    ");

    $stmt->execute([$productId]);

    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Summary
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(quantity), 0) AS total_quantity,
            COALESCE(SUM(remaining_quantity), 0) AS remaining_quantity,
            COUNT(*) AS batch_count
        FROM inventory_batches
        WHERE product_id = ?
    ");

    $stmt->execute([$productId]);

    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "product" => $product,
        "summary" => $summary,
        "batches" => $batches
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}