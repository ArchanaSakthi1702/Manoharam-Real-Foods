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
            id,
            name,
            unit,
            calories_per_100g,
            protein_per_100g,
            carbs_per_100g,
            fat_per_100g,
            fiber_per_100g
        FROM ingredients
        WHERE 1=1
    ";

    $params = [];

    if (!empty($cursor)) {

        $sql .= "
            AND id < ?
        ";

        $params[] = $cursor;
    }
    $limitPlusOne = $limit + 1;

    $sql .= "
        ORDER BY id DESC
        LIMIT {$limitPlusOne}
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $has_more = count($ingredients) > $limit;

    if ($has_more) {
        array_pop($ingredients);
    }

    $next_cursor = null;

    if ($has_more && !empty($ingredients)) {

        $lastIngredient = end($ingredients);

        $next_cursor = $lastIngredient['id'];
    }

    echo json_encode([
        "success" => true,
        "ingredients" => $ingredients,
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