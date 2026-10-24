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

try {

    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid JSON"
        ]);

        exit;
    }

    $productId = $data['product_id'] ?? null;
    $batchNo = trim($data['batch_no'] ?? '');
    $manufacturedDate = $data['manufactured_date'] ?? null;
    $expiryDate = $data['expiry_date'] ?? null;
    $quantity = $data['quantity'] ?? null;

    if (!$productId || !is_numeric($productId)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "product_id is required"
        ]);

        exit;
    }

    if ($batchNo === '') {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "batch_no is required"
        ]);

        exit;
    }

    if (!$manufacturedDate || !$expiryDate) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "manufactured_date and expiry_date are required"
        ]);

        exit;
    }

    if (!is_numeric($quantity) || $quantity <= 0) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "quantity must be greater than 0"
        ]);

        exit;
    }

    if ($expiryDate < $manufacturedDate) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "expiry_date cannot be before manufactured_date"
        ]);

        exit;
    }

    // Check product
    $stmt = $pdo->prepare("
        SELECT id
        FROM products
        WHERE id = ?
    ");

    $stmt->execute([$productId]);

    if (!$stmt->fetch()) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Product not found"
        ]);

        exit;
    }

    // Check duplicate batch
    $stmt = $pdo->prepare("
        SELECT id
        FROM inventory_batches
        WHERE product_id = ?
        AND batch_no = ?
    ");

    $stmt->execute([
        $productId,
        $batchNo
    ]);

    if ($stmt->fetch()) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Batch already exists for this product"
        ]);

        exit;
    }

    // Insert
    $stmt = $pdo->prepare("
        INSERT INTO inventory_batches (
            product_id,
            batch_no,
            manufactured_date,
            expiry_date,
            quantity,
            remaining_quantity
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $productId,
        $batchNo,
        $manufacturedDate,
        $expiryDate,
        $quantity,
        $quantity
    ]);

    $batchId = $pdo->lastInsertId();

    echo json_encode([
        "success" => true,
        "message" => "Inventory batch added successfully",
        "batch_id" => $batchId
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}