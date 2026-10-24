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
    $limit = (int)($_GET['limit'] ?? 20);

    if ($limit <= 0) {
        $limit = 20;
    }

    if ($limit > 100) {
        $limit = 100;
    }

    $sql = "
        SELECT
            r.id,
            r.product_id,
            r.name,
            r.created_at,
            p.name AS product_name
        FROM recipes r
        INNER JOIN products p
            ON p.id = r.product_id
        WHERE 1=1
    ";

    $params = [];

    if (!empty($cursor)) {

        $sql .= "
            AND r.id < ?
        ";

        $params[] = $cursor;
    }

    $limitPlusOne = $limit + 1;

    $sql .= "
        ORDER BY r.id DESC
        LIMIT {$limitPlusOne}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $has_more = count($recipes) > $limit;

    if ($has_more) {
        array_pop($recipes);
    }

    $next_cursor = null;

    if ($has_more && !empty($recipes)) {

        $lastRecipe = end($recipes);

        $next_cursor = $lastRecipe['id'];
    }

    echo json_encode([
        "success" => true,
        "recipes" => $recipes,
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