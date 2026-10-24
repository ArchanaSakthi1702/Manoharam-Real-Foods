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

$startDate = trim($_GET["start_date"] ?? date("Y-m-01"));
$endDate   = trim($_GET["end_date"] ?? date("Y-m-d"));

try {
    // 1. Overall Sales Summary
    $stmt = $pdo->prepare("
        SELECT
            COUNT(id) AS total_orders,
            COALESCE(SUM(total_amount), 0) AS total_revenue,
            COALESCE(AVG(total_amount), 0) AS average_order_value
        FROM orders
        WHERE DATE(order_date) >= ? AND DATE(order_date) <= ?
          AND status != 'CANCELLED'
    ");
    $stmt->execute([$startDate, $endDate]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Daily Sales Trend
    $stmt = $pdo->prepare("
        SELECT
            DATE(order_date) AS date,
            COUNT(id) AS orders_count,
            SUM(total_amount) AS revenue
        FROM orders
        WHERE DATE(order_date) >= ? AND DATE(order_date) <= ?
          AND status != 'CANCELLED'
        GROUP BY DATE(order_date)
        ORDER BY DATE(order_date) ASC
    ");
    $stmt->execute([$startDate, $endDate]);
    $dailyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Top-Selling Products
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.name,
            p.category,
            SUM(oi.quantity) AS total_quantity_sold,
            SUM(oi.quantity * oi.price) AS total_product_revenue
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        INNER JOIN products p ON p.id = oi.product_id
        WHERE DATE(o.order_date) >= ? AND DATE(o.order_date) <= ?
          AND o.status != 'CANCELLED'
        GROUP BY p.id
        ORDER BY total_quantity_sold DESC
        LIMIT 5
    ");
    $stmt->execute([$startDate, $endDate]);
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "date_range" => [
            "start_date" => $startDate,
            "end_date" => $endDate
        ],
        "summary" => [
            "total_orders" => (int)$summary["total_orders"],
            "total_revenue" => round((float)$summary["total_revenue"], 2),
            "average_order_value" => round((float)$summary["average_order_value"], 2)
        ],
        "daily_trend" => $dailyTrend,
        "top_selling_products" => $topProducts
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
