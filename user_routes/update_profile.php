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

$name = trim($data["name"] ?? "");
$phone = trim($data["phone"] ?? "");
$address = trim($data["address"] ?? "");

if (empty($name)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Name cannot be empty"
    ]);
    exit;
}

try {
    // Check if phone number is taken by another user
    if (!empty($phone)) {
        $stmt = $pdo->prepare("
            SELECT id FROM users
            WHERE phone = ? AND id != ?
            LIMIT 1
        ");
        $stmt->execute([$phone, $user->id]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode([
                "success" => false,
                "message" => "Phone number is already associated with another account"
            ]);
            exit;
        }
    }

    $stmt = $pdo->prepare("
        UPDATE users
        SET name = ?, phone = ?, address = ?
        WHERE id = ?
    ");
    $stmt->execute([$name, $phone ?: null, $address ?: null, $user->id]);

    echo json_encode([
        "success" => true,
        "message" => "Profile updated successfully",
        "profile" => [
            "id" => $user->id,
            "name" => $name,
            "phone" => $phone,
            "address" => $address
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
