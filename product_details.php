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

    $product_id = $_GET['id'] ?? null;

    if (!$product_id) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Product ID is required"
        ]);

        exit;
    }

    // =========================
    // PRODUCT + RECIPE + NUTRITION
    // =========================
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
            p.is_subscription_only,

            r.id AS recipe_id,
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

        WHERE p.id = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id]);

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Product not found"
        ]);

        exit;
    }

    // =========================
    // INGREDIENT NAMES ONLY
    // =========================
    $ingredients = [];

    if (!empty($product['recipe_id'])) {

        $stmt2 = $pdo->prepare("
            SELECT i.name
            FROM recipe_ingredients ri
            JOIN ingredients i
                ON i.id = ri.ingredient_id
            WHERE ri.recipe_id = ?
        ");

        $stmt2->execute([
            $product['recipe_id']
        ]);

        $ingredients = $stmt2->fetchAll(
            PDO::FETCH_COLUMN
        );
    }

    // =========================
    // PRODUCT WEIGHT IN GRAMS
    // =========================
    $product_weight_g = 0;

    if (
        strtolower($product['unit']) === 'kg'
    ) {

        $product_weight_g =
            $product['quantity'] * 1000;

    } else {

        $product_weight_g =
    (float)$product['quantity'];
    }

    // =========================
    // SCALE NUTRITION
    // =========================
    $nutrition = [
        "calories" => 0,
        "protein" => 0,
        "carbs" => 0,
        "fat" => 0,
        "fiber" => 0
    ];

    $recipe_weight_g =
    (float)($product['total_weight_g'] ?? 0);

    if (
        $recipe_weight_g > 0 &&
        !empty($product['recipe_id'])
    ) {

        $factor =
            $product_weight_g /
            $recipe_weight_g;

        $nutrition = [

            "calories" => round(
                ($product['calories'] ?? 0)
                * $factor,
                2
            ),

            "protein" => round(
                ($product['protein'] ?? 0)
                * $factor,
                2
            ),

            "carbs" => round(
                ($product['carbs'] ?? 0)
                * $factor,
                2
            ),

            "fat" => round(
                ($product['fat'] ?? 0)
                * $factor,
                2
            ),

            "fiber" => round(
                ($product['fiber'] ?? 0)
                * $factor,
                2
            )
        ];
    }

    // =========================
    // RESPONSE
    // =========================
    echo json_encode([
        "success" => true,
        "product" => [

            "id" => $product['id'],
            "name" => $product['name'],
            "category" => $product['category'],

            "quantity" => $product['quantity'],
            "unit" => $product['unit'],

            "selling_price" => $product['selling_price'],
            "description" => $product['description'],
            "image_url" => $product['image_url'],
            "status" => $product['status'],
            "is_subscription_only" => (bool)($product['is_subscription_only'] ?? 0),

            "nutrition" => $nutrition,

            "ingredients" => $ingredients
        ]
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}