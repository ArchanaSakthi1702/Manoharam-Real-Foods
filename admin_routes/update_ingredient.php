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

$id = $data["id"] ?? $data["ingredient_id"] ?? null;

if (!$id || !is_numeric($id)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Valid ingredient id is required"
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM ingredients WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ingredient) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Ingredient not found"
        ]);
        exit;
    }

    $name     = isset($data["name"]) ? trim($data["name"]) : $ingredient["name"];
    $unit     = isset($data["unit"]) ? strtolower(trim($data["unit"])) : $ingredient["unit"];
    $calories = isset($data["calories_per_100g"]) ? (float)$data["calories_per_100g"] : (float)$ingredient["calories_per_100g"];
    $protein  = isset($data["protein_per_100g"]) ? (float)$data["protein_per_100g"] : (float)$ingredient["protein_per_100g"];
    $carbs    = isset($data["carbs_per_100g"]) ? (float)$data["carbs_per_100g"] : (float)$ingredient["carbs_per_100g"];
    $fat      = isset($data["fat_per_100g"]) ? (float)$data["fat_per_100g"] : (float)$ingredient["fat_per_100g"];
    $fiber    = isset($data["fiber_per_100g"]) ? (float)$data["fiber_per_100g"] : (float)$ingredient["fiber_per_100g"];

    if (empty($name)) {
        throw new Exception("Ingredient name cannot be empty");
    }

    $allowedUnits = ['g', 'kg', 'ml', 'l', 'piece'];
    if (!in_array($unit, $allowedUnits)) {
        throw new Exception("unit must be one of: " . implode(', ', $allowedUnits));
    }

    $stmt = $pdo->prepare("
        UPDATE ingredients
        SET
            name = ?,
            unit = ?,
            calories_per_100g = ?,
            protein_per_100g = ?,
            carbs_per_100g = ?,
            fat_per_100g = ?,
            fiber_per_100g = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $name,
        $unit,
        $calories,
        $protein,
        $carbs,
        $fat,
        $fiber,
        $id
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Ingredient updated successfully",
        "ingredient" => [
            "id" => (int)$id,
            "name" => $name,
            "unit" => $unit,
            "calories_per_100g" => $calories,
            "protein_per_100g" => $protein,
            "carbs_per_100g" => $carbs,
            "fat_per_100g" => $fat,
            "fiber_per_100g" => $fiber
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
