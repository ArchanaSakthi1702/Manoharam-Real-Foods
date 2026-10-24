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
$addressId = $data["id"] ?? $data["address_id"] ?? null;

if (!$addressId || !is_numeric($addressId)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Valid address_id is required"
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT * FROM user_addresses
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$addressId, $user->id]);
    $address = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$address) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Address not found"
        ]);
        exit;
    }

    $pdo->beginTransaction();

    // 1. Unset all defaults
    $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
    $stmt->execute([$user->id]);

    // 2. Set this address default
    $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ?");
    $stmt->execute([$addressId]);

    // 3. Sync to users table
    $fullAddress = trim("{$address["address_line1"]}, {$address["address_line2"]}, {$address["city"]}, {$address["state"]} - {$address["postal_code"]}", " ,");
    $stmt = $pdo->prepare("UPDATE users SET address = ? WHERE id = ?");
    $stmt->execute([$fullAddress, $user->id]);

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Default delivery address updated successfully",
        "default_address_id" => (int)$addressId
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
