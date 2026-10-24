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
        SELECT id, name, email, phone, address, role, status, created_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$user->id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "User profile not found"
        ]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "profile" => $profile
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
