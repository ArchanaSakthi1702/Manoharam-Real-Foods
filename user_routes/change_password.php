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

$currentPassword = $data["current_password"] ?? "";
$newPassword     = $data["new_password"] ?? "";

if (empty($currentPassword) || empty($newPassword)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "current_password and new_password are required"
    ]);
    exit;
}

if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "New password must be at least 6 characters long"
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user->id]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userRow || !password_verify($currentPassword, $userRow["password"])) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Incorrect current password"
        ]);
        exit;
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashedPassword, $user->id]);

    echo json_encode([
        "success" => true,
        "message" => "Password changed successfully"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
