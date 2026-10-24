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
$reviewId = $data["id"] ?? $data["review_id"] ?? null;
$status   = strtoupper(trim($data["status"] ?? ""));

if (!$reviewId || !in_array($status, ["APPROVED", "REJECTED", "PENDING"])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "review_id and valid status (APPROVED, REJECTED, PENDING) are required"
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE product_reviews SET status = ? WHERE id = ?");
    $stmt->execute([$status, $reviewId]);

    echo json_encode([
        "success" => true,
        "message" => "Review status updated to $status",
        "review_id" => (int)$reviewId,
        "status" => $status
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
