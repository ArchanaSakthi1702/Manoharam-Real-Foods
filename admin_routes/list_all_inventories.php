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
require_once "../auth/admin_middleware.php";

requireAdmin();

try {

    $cursor = $_GET['cursor'] ?? null;
    $limit = (int)($_GET['limit'] ?? 10);

    if ($limit <= 0) {
        $limit = 10;
    }

    if ($limit > 100) {
        $limit = 100;
    }

    $sql = "
        SELECT
            p.id,
            p.name,
            p.category,
            p.quantity AS product_quantity,
            p.unit AS product_unit,
            p.selling_price,
            p.status,

            COALESCE(SUM(ib.quantity), 0) AS total_quantity,
            COALESCE(SUM(ib.remaining_quantity), 0) AS remaining_quantity,
            COUNT(ib.id) AS batch_count

        FROM products p

        LEFT JOIN inventory_batches ib
            ON ib.product_id = p.id
    ";

    $params = [];

    if (!empty($cursor)) {

        $sql .= "
            WHERE p.id < ?
        ";

        $params[] = $cursor;
    }

    $sql .= "
        GROUP BY
            p.id,
            p.name,
            p.category,
            p.quantity,
            p.unit,
            p.selling_price,
            p.status

        ORDER BY p.id DESC
        LIMIT " . ($limit + 1);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $has_more = count($products) > $limit;

    if ($has_more) {
        array_pop($products);
    }

    $next_cursor = null;

    if ($has_more && !empty($products)) {
        $lastProduct = end($products);
        $next_cursor = $lastProduct['id'];
    }

    echo json_encode([
        "success" => true,
        "inventory" => $products,
        "pagination" => [
            "limit" => $limit,
            "has_more" => $has_more,
            "next_cursor" => $next_cursor
        ]
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}