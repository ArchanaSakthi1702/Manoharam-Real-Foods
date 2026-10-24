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

    $stmt = $pdo->prepare("SELECT id, name FROM recipes WHERE id = ? LIMIT 1");
    $stmt->execute([$recipeId]);
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recipe) {
        throw new Exception("Recipe not found");
    }

    // 1. Delete nutrition
    $stmt = $pdo->prepare("DELETE FROM recipe_nutrition WHERE recipe_id = ?");
    $stmt->execute([$recipeId]);

    // 2. Delete recipe ingredients
    $stmt = $pdo->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?");
    $stmt->execute([$recipeId]);

    // 3. Delete recipe
    $stmt = $pdo->prepare("DELETE FROM recipes WHERE id = ?");
    $stmt->execute([$recipeId]);

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Recipe deleted successfully",
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
