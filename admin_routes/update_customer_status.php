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
require_once "../auth/admin_middleware.php";

requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);
$customerId = $data["id"] ?? $data["customer_id"] ?? null;
$status     = strtolower(trim($data["status"] ?? ""));

if (!$customerId || !in_array($status, ["active", "inactive", "blocked"])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "customer_id and valid status (active, inactive, blocked) are required"
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$status, $customerId]);

    echo json_encode([
        "success" => true,
        "message" => "Customer account status updated to $status",
        "customer_id" => (int)$customerId,
        "status" => $status
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
