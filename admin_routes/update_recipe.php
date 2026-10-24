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

$recipeId = $data["recipe_id"] ?? $data["id"] ?? null;
$name = isset($data["name"]) ? trim($data["name"]) : null;
$ingredients = $data["ingredients"] ?? null;
$totalWeightG = isset($data["total_weight_g"]) ? (float)$data["total_weight_g"] : null;

if (!$recipeId || !is_numeric($recipeId)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Valid recipe_id is required"
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM recipes WHERE id = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$recipeId]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recipe) {
        throw new Exception("Recipe not found");
    }

    $finalName = $name !== null ? $name : $recipe["name"];

    // If new ingredients provided
    if (is_array($ingredients) && !empty($ingredients)) {
        // Calculate total weight if not explicitly provided
        if ($totalWeightG === null || $totalWeightG <= 0) {
            $totalWeightG = 0;
            foreach ($ingredients as $ing) {
                $totalWeightG += (float)($ing["quantity_g"] ?? 0);
            }
        }

        // 1. Update recipe header
        $stmt = $pdo->prepare("
            UPDATE recipes
            SET name = ?, total_weight_g = ?
            WHERE id = ?
        ");
        $stmt->execute([$finalName, $totalWeightG, $recipeId]);

        // 2. Delete old recipe ingredients
        $stmt = $pdo->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?");
        $stmt->execute([$recipeId]);

        // 3. Re-insert and calculate nutrition
        $totalCalories = 0;
        $totalProtein  = 0;
        $totalCarbs    = 0;
        $totalFat      = 0;
        $totalFiber    = 0;

        $stmtInsert = $pdo->prepare("
            INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity_g)
            VALUES (?, ?, ?)
        ");

        $stmtNutri = $pdo->prepare("
            SELECT calories_per_100g, protein_per_100g, carbs_per_100g, fat_per_100g, fiber_per_100g
            FROM ingredients
            WHERE id = ?
        ");

        foreach ($ingredients as $item) {
            $ingId = $item["ingredient_id"] ?? null;
            $qtyG  = (float)($item["quantity_g"] ?? 0);

            if (!$ingId || $qtyG <= 0) {
                throw new Exception("Each ingredient must have a valid ingredient_id and quantity_g > 0");
            }

            $stmtNutri->execute([$ingId]);
            $nutri = $stmtNutri->fetch(PDO::FETCH_ASSOC);

            if (!$nutri) {
                throw new Exception("Ingredient ID {$ingId} not found");
            }

            $stmtInsert->execute([$recipeId, $ingId, $qtyG]);

            $factor = $qtyG / 100;
            $totalCalories += ((float)$nutri["calories_per_100g"] * $factor);
            $totalProtein  += ((float)$nutri["protein_per_100g"] * $factor);
            $totalCarbs    += ((float)$nutri["carbs_per_100g"] * $factor);
            $totalFat      += ((float)$nutri["fat_per_100g"] * $factor);
            $totalFiber    += ((float)$nutri["fiber_per_100g"] * $factor);
        }

        // 4. Upsert nutrition
        $stmt = $pdo->prepare("
            INSERT INTO recipe_nutrition (recipe_id, calories, protein, carbs, fat, fiber)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                calories = VALUES(calories),
                protein = VALUES(protein),
                carbs = VALUES(carbs),
                fat = VALUES(fat),
                fiber = VALUES(fiber)
        ");
        $stmt->execute([
            $recipeId,
            round($totalCalories, 2),
            round($totalProtein, 2),
            round($totalCarbs, 2),
            round($totalFat, 2),
            round($totalFiber, 2)
        ]);

    } else {
        // Just update name/total weight
        $finalWeight = $totalWeightG !== null ? $totalWeightG : (float)$recipe["total_weight_g"];
        $stmt = $pdo->prepare("
            UPDATE recipes
            SET name = ?, total_weight_g = ?
            WHERE id = ?
        ");
        $stmt->execute([$finalName, $finalWeight, $recipeId]);
    }

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Recipe updated successfully",
        "recipe_id" => (int)$recipeId
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
