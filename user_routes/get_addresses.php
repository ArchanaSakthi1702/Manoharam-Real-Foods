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
require_once "../auth/auth_middleware.php";

$user = authenticate();

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            recipient_name,
            recipient_phone,
            address_line1,
            address_line2,
            city,
            state,
            postal_code,
            landmark,
            address_type,
            is_default,
            created_at
        FROM user_addresses
        WHERE user_id = ?
        ORDER BY is_default DESC, id DESC
    ");
    $stmt->execute([$user->id]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast boolean
    foreach ($addresses as &$addr) {
        $addr["id"] = (int)$addr["id"];
        $addr["is_default"] = (bool)$addr["is_default"];
    }

    echo json_encode([
        "success" => true,
        "total_addresses" => count($addresses),
        "addresses" => $addresses
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
