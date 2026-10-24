<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/cors.php";
require_once "db.php";

try {
    $stmt = $pdo->query("
        SELECT
            category,
            COUNT(id) AS product_count
        FROM products
        WHERE status = 'ACTIVE'
          AND category IS NOT NULL
          AND category != ''
        GROUP BY category
        ORDER BY category ASC
    ");

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "total_categories" => count($categories),
        "categories" => $categories
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
