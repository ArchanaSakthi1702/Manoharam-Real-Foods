<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: PUT, OPTIONS");

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

    $id = $data['id'] ?? null;
    $batchNo = trim($data['batch_no'] ?? '');
    $manufacturedDate = $data['manufactured_date'] ?? null;
    $expiryDate = $data['expiry_date'] ?? null;

    if (!$id || !is_numeric($id)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Valid batch id is required"
        ]);

        exit;
    }

    if ($batchNo === '' || !$manufacturedDate || !$expiryDate) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "batch_no, manufactured_date and expiry_date are required"
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

    // Get batch
    $stmt = $pdo->prepare("
        SELECT product_id
        FROM inventory_batches
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Inventory batch not found"
        ]);

        exit;
    }

    // Check duplicate batch number
    $stmt = $pdo->prepare("
        SELECT id
        FROM inventory_batches
        WHERE product_id = ?
        AND batch_no = ?
        AND id != ?
    ");

    $stmt->execute([
        $batch['product_id'],
        $batchNo,
        $id
    ]);

    if ($stmt->fetch()) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Another batch with this batch number already exists"
        ]);

        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE inventory_batches
        SET
            batch_no = ?,
            manufactured_date = ?,
            expiry_date = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $batchNo,
        $manufacturedDate,
        $expiryDate,
        $id
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Inventory batch updated successfully"
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}