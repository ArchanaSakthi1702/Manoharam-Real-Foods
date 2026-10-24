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

$recipientName  = trim($data["recipient_name"] ?? "");
$recipientPhone = trim($data["recipient_phone"] ?? "");
$addressLine1   = trim($data["address_line1"] ?? "");
$addressLine2   = trim($data["address_line2"] ?? "");
$city           = trim($data["city"] ?? "");
$state          = trim($data["state"] ?? "Tamil Nadu");
$postalCode     = trim($data["postal_code"] ?? "");
$landmark       = trim($data["landmark"] ?? "");
$addressType    = strtoupper(trim($data["address_type"] ?? "HOME"));
$isDefault      = (bool)($data["is_default"] ?? false);

if (empty($addressLine1) || empty($city) || empty($postalCode)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "address_line1, city, and postal_code are required"
    ]);
    exit;
}

if (!in_array($addressType, ["HOME", "WORK", "OTHER"])) {
    $addressType = "HOME";
}

try {
    $pdo->beginTransaction();

    // Check if user has any existing addresses
    $stmt = $pdo->prepare("SELECT COUNT(id) FROM user_addresses WHERE user_id = ?");
    $stmt->execute([$user->id]);
    $count = (int)$stmt->fetchColumn();

    if ($count === 0) {
        $isDefault = true; // First address is automatically default
    }

    if ($isDefault) {
        // Unset previous defaults
        $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?");
        $stmt->execute([$user->id]);

        // Also update primary address on users table
        $fullAddress = trim("$addressLine1, $addressLine2, $city, $state - $postalCode", " ,");
        $stmt = $pdo->prepare("UPDATE users SET address = ? WHERE id = ?");
        $stmt->execute([$fullAddress, $user->id]);
    }

    $stmt = $pdo->prepare("
        INSERT INTO user_addresses (
            user_id,
            recipient_name,
            recipient_phone,
            address_line1,
            address_line2,
            city,
            state,
            postal_code,
            landmark,
            address_type,
            is_default
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $user->id,
        $recipientName ?: null,
        $recipientPhone ?: null,
        $addressLine1,
        $addressLine2 ?: null,
        $city,
        $state,
        $postalCode,
        $landmark ?: null,
        $addressType,
        $isDefault ? 1 : 0
    ]);

    $addressId = $pdo->lastInsertId();
    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Address added successfully",
        "address" => [
            "id" => (int)$addressId,
            "recipient_name" => $recipientName,
            "recipient_phone" => $recipientPhone,
            "address_line1" => $addressLine1,
            "address_line2" => $addressLine2,
            "city" => $city,
            "state" => $state,
            "postal_code" => $postalCode,
            "landmark" => $landmark,
            "address_type" => $addressType,
            "is_default" => $isDefault
        ]
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
