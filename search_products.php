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

    $search = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $status = trim($_GET['status'] ?? 'ACTIVE');

    $min_price = $_GET['min_price'] ?? null;
    $max_price = $_GET['max_price'] ?? null;

    $subscription_only = $_GET['subscription_only'] ?? $_GET['is_subscription_only'] ?? null;
    $for_sale_only = $_GET['for_sale_only'] ?? null;

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
        WHERE 1=1
    ";

    $params = [];

    if (!empty($search)) {

        $sql .= "
            AND (
                name LIKE ?
                OR description LIKE ?
            )
        ";

        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    if (!empty($category)) {

        $sql .= " AND category = ? ";

        $params[] = $category;
    }

    if (!empty($status)) {

        $sql .= " AND status = ? ";

        $params[] = $status;
    }

    if ($min_price !== null) {

        $sql .= " AND selling_price >= ? ";

        $params[] = $min_price;
    }

    if ($max_price !== null) {

        $sql .= " AND selling_price <= ? ";

        $params[] = $max_price;
    }

    if ($for_sale_only !== null && ($for_sale_only === '1' || strtolower((string)$for_sale_only) === 'true')) {
        $sql .= " AND is_subscription_only = 0 ";
    } elseif ($subscription_only !== null) {
        $isSub = ($subscription_only === '1' || $subscription_only === 1 || strtolower((string)$subscription_only) === 'true') ? 1 : 0;
        $sql .= " AND is_subscription_only = ? ";
        $params[] = $isSub;
    }

    // Cursor condition
    if (!empty($cursor)) {

        $sql .= " AND id < ? ";

        $params[] = $cursor;
    }

    $fetchLimit = $limit + 1;

    $sql .= "
        ORDER BY id DESC
        LIMIT $fetchLimit
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as &$product) {
        $product['is_subscription_only'] = (bool)($product['is_subscription_only'] ?? 0);
    }
    unset($product);

    $has_more = count($products) > $limit;

    if ($has_more) {
        array_pop($products);
    }

    $next_cursor = null;

    if (!empty($products)) {

        $lastProduct = end($products);

        $next_cursor = $lastProduct["id"];
    }

    echo json_encode([
        "success" => true,
        "products" => $products,
        "pagination" => [
            "limit" => $limit,
            "has_more" => $has_more,
            "next_cursor" => $has_more ? $next_cursor : null
        ]
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}