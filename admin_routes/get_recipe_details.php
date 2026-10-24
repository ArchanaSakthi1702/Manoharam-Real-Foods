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

$recipeId  = $_GET["id"] ?? $_GET["recipe_id"] ?? null;
$productId = $_GET["product_id"] ?? null;

if (!$recipeId && !$productId) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "recipe_id or product_id is required"
    ]);
    exit;
}

try {
    if ($recipeId) {
        $stmt = $pdo->prepare("
            SELECT r.*, p.name AS product_name, p.selling_price
            FROM recipes r
            INNER JOIN products p ON p.id = r.product_id
            WHERE r.id = ?
            LIMIT 1
        ");
        $stmt->execute([$recipeId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT r.*, p.name AS product_name, p.selling_price
            FROM recipes r
            INNER JOIN products p ON p.id = r.product_id
            WHERE r.product_id = ?
            LIMIT 1
        ");
        $stmt->execute([$productId]);
    }

    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recipe) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Recipe not found"
        ]);
        exit;
    }

    // Get ingredients
    $stmt = $pdo->prepare("
        SELECT
            ri.id AS recipe_ingredient_id,
            i.id AS ingredient_id,
            i.name AS ingredient_name,
            i.unit,
            ri.quantity_g,
            i.calories_per_100g,
            i.protein_per_100g,
            i.carbs_per_100g,
            i.fat_per_100g,
            i.fiber_per_100g
        FROM recipe_ingredients ri
        INNER JOIN ingredients i ON i.id = ri.ingredient_id
        WHERE ri.recipe_id = ?
        ORDER BY ri.quantity_g DESC
    ");
    $stmt->execute([$recipe["id"]]);
    $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get nutrition
    $stmt = $pdo->prepare("
        SELECT calories, protein, carbs, fat, fiber
        FROM recipe_nutrition
        WHERE recipe_id = ?
        LIMIT 1
    ");
    $stmt->execute([$recipe["id"]]);
    $nutrition = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalWeight = (float)$recipe["total_weight_g"];
    $per100g = [];

    if ($totalWeight > 0 && $nutrition) {
        $multiplier = 100 / $totalWeight;
        $per100g = [
            "calories" => round((float)$nutrition["calories"] * $multiplier, 2),
            "protein"  => round((float)$nutrition["protein"] * $multiplier, 2),
            "carbs"    => round((float)$nutrition["carbs"] * $multiplier, 2),
            "fat"      => round((float)$nutrition["fat"] * $multiplier, 2),
            "fiber"    => round((float)$nutrition["fiber"] * $multiplier, 2)
        ];
    }

    echo json_encode([
        "success" => true,
        "recipe" => [
            "id" => (int)$recipe["id"],
            "product_id" => (int)$recipe["product_id"],
            "product_name" => $recipe["product_name"],
            "name" => $recipe["name"],
            "total_weight_g" => $totalWeight,
            "ingredients" => $ingredients,
            "total_nutrition" => $nutrition,
            "nutrition_per_100g" => $per100g,
            "created_at" => $recipe["created_at"]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
