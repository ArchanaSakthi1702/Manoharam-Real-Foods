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
    $subscriptionOnly = $_GET['subscription_only'] ?? null;
    $search = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');

    $sql = "
        SELECT
            id,
            name,
            category,
            quantity,
            unit,
            selling_price,
            description,
            image_url,
            status,
            is_subscription_only
        FROM products
        WHERE status = 'ACTIVE'
    ";

    $params = [];

    if ($subscriptionOnly !== null) {
        $isSub = ($subscriptionOnly === '1' || $subscriptionOnly === 1 || strtolower((string)$subscriptionOnly) === 'true') ? 1 : 0;
        $sql .= " AND is_subscription_only = ? ";
        $params[] = $isSub;
    }

    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR description LIKE ?) ";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    if (!empty($category)) {
        $sql .= " AND category = ? ";
        $params[] = $category;
    }

    $sql .= " ORDER BY name ASC ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as &$prod) {
        $prod["id"] = (int)$prod["id"];
        $prod["quantity"] = (float)$prod["quantity"];
        $prod["selling_price"] = (float)$prod["selling_price"];
        $prod["is_subscription_only"] = (bool)$prod["is_subscription_only"];
    }
    unset($prod);

    echo json_encode([
        "success" => true,
        "count" => count($products),
        "products" => $products
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
