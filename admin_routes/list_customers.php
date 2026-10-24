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

$search = trim($_GET["search"] ?? "");
$page   = max(1, (int)($_GET["page"] ?? 1));
$limit  = min(100, max(1, (int)($_GET["limit"] ?? 10)));
$offset = ($page - 1) * $limit;

try {
    $whereClause = "WHERE u.role = 'CUSTOMER'";
    $params = [];

    if (!empty($search)) {
        $whereClause .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Total count
    $countStmt = $pdo->prepare("SELECT COUNT(u.id) FROM users u $whereClause");
    $countStmt->execute($params);
    $totalCustomers = (int)$countStmt->fetchColumn();

    // Customers with aggregate spending
    $sql = "
        SELECT
            u.id,
            u.name,
            u.email,
            u.phone,
            u.address,
            u.status,
            u.created_at,
            COUNT(DISTINCT o.id) AS total_orders,
            COALESCE(SUM(CASE WHEN o.status != 'CANCELLED' THEN o.total_amount ELSE 0 END), 0) AS total_spent,
            COUNT(DISTINCT s.id) AS total_subscriptions
        FROM users u
        LEFT JOIN orders o ON o.customer_id = u.id
        LEFT JOIN subscriptions s ON s.customer_id = u.id
        $whereClause
        GROUP BY u.id
        ORDER BY u.id DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "page" => $page,
        "limit" => $limit,
        "total_customers" => $totalCustomers,
        "total_pages" => ceil($totalCustomers / $limit),
        "customers" => $customers
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
