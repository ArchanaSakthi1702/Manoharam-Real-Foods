<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
require_once __DIR__ . "/../cors.php";
require_once "../db.php";
require_once "../auth/admin_middleware.php";

requireAdmin();

$data = json_decode(
    file_get_contents("php://input"),
    true
);

$recipe_id = $data["recipe_id"] ?? null;
$required_quantity_kg =
    (float)($data["required_quantity_kg"] ?? 0);

if (
    !$recipe_id ||
    $required_quantity_kg <= 0
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" =>
            "recipe_id and required_quantity_kg are required"
    ]);

    exit;
}

try {

    // Get recipe
    $stmt = $pdo->prepare("
        SELECT
            id,
            product_id,
            name
        FROM recipes
        WHERE id = ?
    ");

    $stmt->execute([$recipe_id]);

    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recipe) {

        throw new Exception(
            "Recipe not found"
        );
    }

    // Get recipe ingredients
    $stmt = $pdo->prepare("
        SELECT
            i.id,
            i.name,
            i.unit,
            ri.quantity_g
        FROM recipe_ingredients ri
        INNER JOIN ingredients i
            ON i.id = ri.ingredient_id
        WHERE ri.recipe_id = ?
        ORDER BY i.name
    ");

    $stmt->execute([$recipe_id]);

    $ingredients =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];

    foreach ($ingredients as $ingredient) {

        $required_g =
            $ingredient["quantity_g"] *
            $required_quantity_kg;

        $required_kg =
            $required_g / 1000;

        $display_quantity =
            $required_g >= 1000
            ? round($required_kg, 3) . " kg"
            : round($required_g, 2) . " g";

        $result[] = [
            "ingredient_id" =>
                $ingredient["id"],

            "ingredient_name" =>
                $ingredient["name"],

            "recipe_quantity_g" =>
                (float)$ingredient["quantity_g"],

            "required_g" =>
                round($required_g, 2),

            "required_kg" =>
                round($required_kg, 3),

            "display_quantity" =>
                $display_quantity
        ];
    }

    echo json_encode([
        "success" => true,

        "recipe" => [
            "id" => $recipe["id"],
            "name" => $recipe["name"]
        ],

        "required_quantity_kg" =>
            $required_quantity_kg,

        "ingredients" => $result
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}