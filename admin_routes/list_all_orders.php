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
            o.id,
            o.order_date,
            o.status,
            o.total_amount,

            u.id AS customer_id,
            u.name AS customer_name,
            u.phone AS customer_phone

        FROM orders o

        JOIN users u
            ON u.id = o.customer_id
    ";

    $params = [];

    if (!empty($cursor)) {

        $sql .= "
            WHERE o.id < ?
        ";

        $params[] = $cursor;
    }

    $fetchLimit = $limit + 1;

    $sql .= "
        ORDER BY o.id DESC
        LIMIT $fetchLimit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $has_more = count($orders) > $limit;

    if ($has_more) {
        array_pop($orders);
    }

    $next_cursor = null;

    if ($has_more && !empty($orders)) {

        $lastOrder = end($orders);

        $next_cursor = $lastOrder['id'];
    }

    echo json_encode([
        "success" => true,
        "orders" => $orders,
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