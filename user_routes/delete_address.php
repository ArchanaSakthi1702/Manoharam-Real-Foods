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
    $stmt = $pdo->prepare("SELECT is_default FROM user_addresses WHERE id = ? AND user_id = ? LIMIT 1");
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

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$addressId, $user->id]);

    // If deleted address was default, promote another address to default
    if ((bool)$existing["is_default"]) {
        $stmt = $pdo->prepare("
            SELECT id, address_line1, address_line2, city, state, postal_code
            FROM user_addresses
            WHERE user_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$user->id]);
        $next = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($next) {
            $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ?");
            $stmt->execute([$next["id"]]);

            $fullAddress = trim("{$next["address_line1"]}, {$next["address_line2"]}, {$next["city"]}, {$next["state"]} - {$next["postal_code"]}", " ,");
            $stmt = $pdo->prepare("UPDATE users SET address = ? WHERE id = ?");
            $stmt->execute([$fullAddress, $user->id]);
        }
    }

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Address deleted successfully"
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
