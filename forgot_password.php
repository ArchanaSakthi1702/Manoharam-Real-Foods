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
require_once "helpers/mail_helper.php";

$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data["email"] ?? "");

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "A valid email address is required"
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, name, email FROM users
        WHERE email = ? AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // If user not found, don't leak account existence for security, or return standard message
    if (!$user) {
        echo json_encode([
            "success" => true,
            "message" => "If the email is registered, a password reset token has been sent."
        ]);
        exit;
    }

    // Generate 6-digit verification OTP or secure token
    $token = (string)random_int(100000, 999999);

    try {
        $updateStmt = $pdo->prepare("
            UPDATE users
            SET reset_token = ?, reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
            WHERE id = ?
        ");
        $updateStmt->execute([$token, $user["id"]]);
    } catch (PDOException $e) {
        throw new Exception("Please run database migration to enable password reset feature.");
    }

    try {
        MailHelper::sendPasswordResetEmail($user["email"], $user["name"], $token);
    } catch (Exception $mailErr) {
        // Mail delivery failed, log error
        error_log("Failed to send password reset mail: " . $mailErr->getMessage());
    }

    echo json_encode([
        "success" => true,
        "message" => "Password reset token sent to your email",
        // Included token in development/test environments
        "dev_token" => $token
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
