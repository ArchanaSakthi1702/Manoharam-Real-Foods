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

$name = trim($data["name"] ?? "");
$unit = $data["unit"] ?? "g";

$calories = $data["calories_per_100g"] ?? 0;
$protein = $data["protein_per_100g"] ?? 0;
$carbs = $data["carbs_per_100g"] ?? 0;
$fat = $data["fat_per_100g"] ?? 0;
$fiber = $data["fiber_per_100g"] ?? 0;

if (empty($name)) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Ingredient name is required"
    ]);

    exit;
}

$allowedUnits = [
    "g",
    "kg",
    "ml",
    "l",
    "piece"
];

if (!in_array($unit, $allowedUnits)) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid unit"
    ]);

    exit;
}

try {

    // Prevent duplicates
    $stmt = $pdo->prepare("
        SELECT id
        FROM ingredients
        WHERE name = ?
        LIMIT 1
    ");

    $stmt->execute([$name]);

    if ($stmt->fetch()) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Ingredient already exists"
        ]);

        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO ingredients (
            name,
            unit,
            calories_per_100g,
            protein_per_100g,
            carbs_per_100g,
            fat_per_100g,
            fiber_per_100g
        )
        VALUES (
            ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        $name,
        $unit,
        $calories,
        $protein,
        $carbs,
        $fat,
        $fiber
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Ingredient created successfully",
        "ingredient_id" => $pdo->lastInsertId()
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}