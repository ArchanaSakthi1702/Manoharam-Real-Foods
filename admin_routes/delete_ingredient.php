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
$ingredientId = $data["id"] ?? $data["ingredient_id"] ?? null;

if (!$ingredientId || !is_numeric($ingredientId)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Valid ingredient id is required"
    ]);
    exit;
}

try {
    // Check if ingredient exists
    $stmt = $pdo->prepare("SELECT id, name FROM ingredients WHERE id = ? LIMIT 1");
    $stmt->execute([$ingredientId]);
    $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ingredient) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Ingredient not found"
        ]);
        exit;
    }

    // Check if used in any recipe
    $stmt = $pdo->prepare("
        SELECT r.name AS recipe_name
        FROM recipe_ingredients ri
        INNER JOIN recipes r ON r.id = ri.recipe_id
        WHERE ri.ingredient_id = ?
        LIMIT 5
    ");
    $stmt->execute([$ingredientId]);
    $usedIn = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($usedIn)) {
        $names = array_column($usedIn, "recipe_name");
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Cannot delete ingredient '{$ingredient["name"]}' because it is in use by recipes: " . implode(", ", $names)
        ]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM ingredients WHERE id = ?");
    $stmt->execute([$ingredientId]);

    echo json_encode([
        "success" => true,
        "message" => "Ingredient deleted successfully",
        "ingredient_id" => (int)$ingredientId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
