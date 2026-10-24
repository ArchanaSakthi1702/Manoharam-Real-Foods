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

$data = json_decode(file_get_contents("php://input"), true);

$product_id = $data["product_id"] ?? null;
$name = trim($data["name"] ?? "");
$ingredients = $data["ingredients"] ?? [];
$total_weight_g =
    $data["total_weight_g"] ?? null;

    
if (
    !$product_id ||
    empty($name) ||
    !$total_weight_g ||
    empty($ingredients)
) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "product_id, name, total_weight_g and ingredients are required"
    ]);
    exit;
}

try {

    $pdo->beginTransaction();

    // =========================
    // 1. CREATE RECIPE
    // =========================
    $stmt = $pdo->prepare("
        INSERT INTO recipes (
            product_id,
            name,
            total_weight_g
        )
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $product_id,
        $name,
        $total_weight_g
    ]);

    $recipe_id = $pdo->lastInsertId();

    // =========================
    // 2. INIT TOTAL NUTRITION
    // =========================
    $total_calories = 0;
    $total_protein = 0;
    $total_carbs = 0;
    $total_fat = 0;
    $total_fiber = 0;

    // =========================
    // 3. INSERT INGREDIENTS
    // =========================
    $stmtInsert = $pdo->prepare("
        INSERT INTO recipe_ingredients (
            recipe_id,
            ingredient_id,
            quantity_g
        )
        VALUES (?, ?, ?)
    ");

    // Pre-prepare ingredient fetch (optimization)
    $stmtNutri = $pdo->prepare("
        SELECT
            calories_per_100g,
            protein_per_100g,
            carbs_per_100g,
            fat_per_100g,
            fiber_per_100g
        FROM ingredients
        WHERE id = ?
    ");

    foreach ($ingredients as $ingredient) {

        $ingredient_id = $ingredient["ingredient_id"] ?? null;
        $quantity_g = $ingredient["quantity_g"] ?? null;

        if (!$ingredient_id || !$quantity_g) {
            throw new Exception("Invalid ingredient data");
        }

        // Insert mapping
        $stmtInsert->execute([
            $recipe_id,
            $ingredient_id,
            $quantity_g
        ]);

        // Fetch nutrition
        $stmtNutri->execute([$ingredient_id]);
        $nutri = $stmtNutri->fetch(PDO::FETCH_ASSOC);

        if ($nutri) {

            $factor = $quantity_g / 100;

            $total_calories += $nutri["calories_per_100g"] * $factor;
            $total_protein  += $nutri["protein_per_100g"] * $factor;
            $total_carbs    += $nutri["carbs_per_100g"] * $factor;
            $total_fat      += $nutri["fat_per_100g"] * $factor;
            $total_fiber    += $nutri["fiber_per_100g"] * $factor;
        }
    }

    // =========================
    // 4. INSERT RECIPE NUTRITION
    // =========================
    $stmt = $pdo->prepare("
        INSERT INTO recipe_nutrition (
            recipe_id,
            calories,
            protein,
            carbs,
            fat,
            fiber
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $recipe_id,
        $total_calories,
        $total_protein,
        $total_carbs,
        $total_fat,
        $total_fiber
    ]);

    $pdo->commit();

    // =========================
    // 5. RESPONSE
    // =========================
    echo json_encode([
        "success" => true,
        "message" => "Recipe created successfully with nutrition",
        "data" => [
            "recipe_id" => $recipe_id,
            "nutrition" => [
                "calories" => $total_calories,
                "protein" => $total_protein,
                "carbs" => $total_carbs,
                "fat" => $total_fat,
                "fiber" => $total_fiber
            ]
        ]
    ]);

} catch (Exception $e) {

    $pdo->rollBack();

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}