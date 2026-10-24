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
        p.quantity,
        p.unit,
        p.selling_price,
        p.description,
        p.image_url,
        p.status,

        r.total_weight_g,

        rn.calories,
        rn.protein,
        rn.carbs,
        rn.fat,
        rn.fiber

    FROM products p

    LEFT JOIN recipes r
        ON r.product_id = p.id

    LEFT JOIN recipe_nutrition rn
        ON rn.recipe_id = r.id

    WHERE p.status = 'ACTIVE'
";

    $params = [];

    if (!empty($cursor)) {

        $sql .= "
            AND id < ?
        ";

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

    if (
        strtolower($product['unit']) === 'kg'
    ) {
        $productWeight =
            (float)$product['quantity'] * 1000;
    } else {
        $productWeight =
            (float)$product['quantity'];
    }

    $recipeWeight =
        (float)($product['total_weight_g'] ?? 0);

    $nutrition = [
        "calories" => 0,
        "protein" => 0,
        "carbs" => 0,
        "fat" => 0,
        "fiber" => 0
    ];

    if ($recipeWeight > 0) {

        $factor =
            $productWeight / $recipeWeight;

        $nutrition = [
            "calories" => round(
                ((float)$product['calories']) * $factor,
                2
            ),
            "protein" => round(
                ((float)$product['protein']) * $factor,
                2
            ),
            "carbs" => round(
                ((float)$product['carbs']) * $factor,
                2
            ),
            "fat" => round(
                ((float)$product['fat']) * $factor,
                2
            ),
            "fiber" => round(
                ((float)$product['fiber']) * $factor,
                2
            )
        ];
    }

    $product['nutrition'] = $nutrition;

    unset(
        $product['total_weight_g'],
        $product['calories'],
        $product['protein'],
        $product['carbs'],
        $product['fat'],
        $product['fiber']
    );
}

unset($product);

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
        "products" => $products,
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