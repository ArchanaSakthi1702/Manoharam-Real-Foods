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
    $today = date("Y-m-d");
    $monthStart = date("Y-m-01 00:00:00");

    // 1. Revenue Today
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) FROM orders
        WHERE DATE(order_date) = ? AND status != 'CANCELLED'
    ");
    $stmt->execute([$today]);
    $revenueToday = (float)$stmt->fetchColumn();

    // 2. Revenue This Month
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) FROM orders
        WHERE order_date >= ? AND status != 'CANCELLED'
    ");
    $stmt->execute([$monthStart]);
    $revenueMonth = (float)$stmt->fetchColumn();

    // 3. Orders Today & Pending Orders
    $stmt = $pdo->prepare("SELECT COUNT(id) FROM orders WHERE DATE(order_date) = ?");
    $stmt->execute([$today]);
    $ordersTodayCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(id) FROM orders WHERE status = 'PENDING'");
    $pendingOrdersCount = (int)$stmt->fetchColumn();

    // 4. Active Subscriptions
    $stmt = $pdo->query("SELECT COUNT(id) FROM subscriptions WHERE status IN ('ACTIVE', 'APPROVED')");
    $activeSubscriptionsCount = (int)$stmt->fetchColumn();

    // 5. Total Customers
    $stmt = $pdo->query("SELECT COUNT(id) FROM users WHERE role = 'CUSTOMER'");
    $totalCustomersCount = (int)$stmt->fetchColumn();

    // 6. Low stock products (less than 10 units in unexpired batches)
    $stmt = $pdo->query("
        SELECT COUNT(p.id) FROM products p
        LEFT JOIN inventory_batches ib ON ib.product_id = p.id AND ib.expiry_date >= CURDATE()
        WHERE p.status = 'ACTIVE'
        GROUP BY p.id
        HAVING COALESCE(SUM(ib.remaining_quantity), 0) < 10
    ");
    $lowStockProductsCount = $stmt->rowCount();

    // 7. Recent 5 Orders
    $stmt = $pdo->query("
        SELECT
            o.id,
            o.order_date,
            o.status,
            o.total_amount,
            o.payment_method,
            u.name AS customer_name
        FROM orders o
        INNER JOIN users u ON u.id = o.customer_id
        ORDER BY o.id DESC
        LIMIT 5
    ");
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. Recent 5 Subscriptions
    $stmt = $pdo->query("
        SELECT
            s.id,
            s.start_date,
            s.end_date,
            s.status,
            s.total_amount,
            u.name AS customer_name,
            sp.name AS plan_name
        FROM subscriptions s
        INNER JOIN users u ON u.id = s.customer_id
        INNER JOIN subscription_plans sp ON sp.id = s.plan_id
        ORDER BY s.id DESC
        LIMIT 5
    ");
    $recentSubscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "metrics" => [
            "revenue_today" => $revenueToday,
            "revenue_month" => $revenueMonth,
            "orders_today" => $ordersTodayCount,
            "pending_orders" => $pendingOrdersCount,
            "active_subscriptions" => $activeSubscriptionsCount,
            "total_customers" => $totalCustomersCount,
            "low_stock_products_count" => $lowStockProductsCount
        ],
        "recent_orders" => $recentOrders,
        "recent_subscriptions" => $recentSubscriptions
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
