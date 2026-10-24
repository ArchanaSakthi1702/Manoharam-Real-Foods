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
require_once "../helpers/upload_image.php";

requireAdmin();

$name = trim($_POST["name"] ?? "");
$category = trim($_POST["category"] ?? "");

$quantity = $_POST["quantity"] ?? null;
$unit = strtolower(trim($_POST["unit"] ?? ""));

$selling_price = $_POST["selling_price"] ?? null;
$description = trim($_POST["description"] ?? "");
$status = strtoupper($_POST["status"] ?? "ACTIVE");

$image_url = null;

try {

    // Upload image if provided
    if (
        isset($_FILES["image"]) &&
        $_FILES["image"]["error"] === 0
    ) {
        $image_url = uploadImageToHostinger(
            $_FILES["image"],
            "foods_profiles"
        );
    }

    // =========================
    // VALIDATION
    // =========================

    if (
        empty($name) ||
        $quantity === null ||
        empty($unit) ||
        $selling_price === null
    ) {
        throw new Exception(
            "name, quantity, unit and selling_price are required"
        );
    }

    if (
        !is_numeric($quantity) ||
        $quantity <= 0
    ) {
        throw new Exception(
            "quantity must be greater than 0"
        );
    }

    if (
        !in_array(
            $unit,
            ["g", "kg"]
        )
    ) {
        throw new Exception(
            "unit must be either g or kg"
        );
    }

    if (
        !is_numeric($selling_price) ||
        $selling_price <= 0
    ) {
        throw new Exception(
            "selling_price must be greater than 0"
        );
    }

    if (
        !in_array(
            $status,
            ["ACTIVE", "INACTIVE"]
        )
    ) {
        throw new Exception(
            "Invalid status"
        );
    }

    $stmt = $pdo->prepare("
        INSERT INTO products (
            name,
            category,
            quantity,
            unit,
            selling_price,
            description,
            image_url,
            status
        )
        VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        $name,
        $category,
        $quantity,
        $unit,
        $selling_price,
        $description,
        $image_url,
        $status
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Product created successfully",
        "product_id" => (int)$pdo->lastInsertId(),
        "image_url" => $image_url
    ]);

} catch (Exception $e) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}