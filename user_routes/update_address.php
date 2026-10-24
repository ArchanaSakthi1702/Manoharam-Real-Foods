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
    $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$addressId, $user->id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Address not found"
        ]);
        exit;
    }

    $recipientName  = isset($data["recipient_name"]) ? trim($data["recipient_name"]) : $existing["recipient_name"];
    $recipientPhone = isset($data["recipient_phone"]) ? trim($data["recipient_phone"]) : $existing["recipient_phone"];
    $addressLine1   = isset($data["address_line1"]) ? trim($data["address_line1"]) : $existing["address_line1"];
    $addressLine2   = isset($data["address_line2"]) ? trim($data["address_line2"]) : $existing["address_line2"];
    $city           = isset($data["city"]) ? trim($data["city"]) : $existing["city"];
    $state          = isset($data["state"]) ? trim($data["state"]) : $existing["state"];
    $postalCode     = isset($data["postal_code"]) ? trim($data["postal_code"]) : $existing["postal_code"];
    $landmark       = isset($data["landmark"]) ? trim($data["landmark"]) : $existing["landmark"];
    $addressType    = isset($data["address_type"]) ? strtoupper(trim($data["address_type"])) : $existing["address_type"];
    $isDefault      = isset($data["is_default"]) ? (bool)$data["is_default"] : (bool)$existing["is_default"];

    if (empty($addressLine1) || empty($city) || empty($postalCode)) {
        throw new Exception("address_line1, city, and postal_code cannot be empty");
    }

    $pdo->beginTransaction();

    if ($isDefault && !$existing["is_default"]) {
        $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
        $stmt->execute([$user->id]);

        $fullAddress = trim("$addressLine1, $addressLine2, $city, $state - $postalCode", " ,");
        $stmt = $pdo->prepare("UPDATE users SET address = ? WHERE id = ?");
        $stmt->execute([$fullAddress, $user->id]);
    }

    $stmt = $pdo->prepare("
        UPDATE user_addresses
        SET
            recipient_name = ?,
            recipient_phone = ?,
            address_line1 = ?,
            address_line2 = ?,
            city = ?,
            state = ?,
            postal_code = ?,
            landmark = ?,
            address_type = ?,
            is_default = ?
        WHERE id = ? AND user_id = ?
    ");

    $stmt->execute([
        $recipientName,
        $recipientPhone,
        $addressLine1,
        $addressLine2,
        $city,
        $state,
        $postalCode,
        $landmark,
        $addressType,
        $isDefault ? 1 : 0,
        $addressId,
        $user->id
    ]);

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Address updated successfully",
        "address_id" => (int)$addressId
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
