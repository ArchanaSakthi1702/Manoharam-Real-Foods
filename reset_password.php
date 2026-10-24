<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/cors.php";
require_once "db.php";

$data = json_decode(file_get_contents("php://input"), true);

$email       = trim($data["email"] ?? "");
$token       = trim($data["token"] ?? "");
$newPassword = $data["new_password"] ?? "";

if (empty($email) || empty($token) || empty($newPassword)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "email, token, and new_password are required"
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
    $stmt = $pdo->prepare("
        SELECT id, reset_token, reset_token_expires_at
        FROM users
        WHERE email = ? AND reset_token = ?
        LIMIT 1
    ");
    $stmt->execute([$email, $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid reset token or email"
        ]);
        exit;
    }

    if (strtotime($user["reset_token_expires_at"]) < time()) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Password reset token has expired. Please request a new one."
        ]);
        exit;
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("
        UPDATE users
        SET password = ?, reset_token = NULL, reset_token_expires_at = NULL
        WHERE id = ?
    ");
    $stmt->execute([$hashedPassword, $user["id"]]);

    echo json_encode([
        "success" => true,
        "message" => "Password reset successfully. You can now log in with your new password."
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
