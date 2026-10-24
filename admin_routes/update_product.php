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

// Handle both multipart/form-data and JSON payloads
$data = $_POST;
if (empty($data)) {
    $json = json_decode(file_get_contents("php://input"), true);
    if (is_array($json)) {
        $data = $json;
    }
}

$productId = $data["id"] ?? $data["product_id"] ?? null;

if (!$productId || !is_numeric($productId)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Valid product id is required"
    ]);
    exit;
}

try {
    // Check product exists
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Product not found"
        ]);
        exit;
    }

    $name         = isset($data["name"]) ? trim($data["name"]) : $product["name"];
    $category     = isset($data["category"]) ? trim($data["category"]) : $product["category"];
    $quantity     = isset($data["quantity"]) ? (float)$data["quantity"] : (float)$product["quantity"];
    $unit         = isset($data["unit"]) ? strtolower(trim($data["unit"])) : $product["unit"];
    $sellingPrice = isset($data["selling_price"]) ? (float)$data["selling_price"] : (float)$product["selling_price"];
    $description  = isset($data["description"]) ? trim($data["description"]) : $product["description"];
    $status       = isset($data["status"]) ? strtoupper(trim($data["status"])) : $product["status"];
    $imageUrl     = $product["image_url"];

    // Handle image file upload if provided
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] === 0) {
        $imageUrl = uploadImageToHostinger($_FILES["image"], "foods_profiles");
    } elseif (!empty($data["image_url"])) {
        $imageUrl = trim($data["image_url"]);
    }

    // Validation
    if ($quantity <= 0) {
        throw new Exception("quantity must be greater than 0");
    }

    if (!in_array($unit, ["g", "kg"])) {
        throw new Exception("unit must be either g or kg");
    }

    if ($sellingPrice <= 0) {
        throw new Exception("selling_price must be greater than 0");
    }

    if (!in_array($status, ["ACTIVE", "INACTIVE"])) {
        throw new Exception("status must be ACTIVE or INACTIVE");
    }

    $stmt = $pdo->prepare("
        UPDATE products
        SET
            name = ?,
            category = ?,
            quantity = ?,
            unit = ?,
            selling_price = ?,
            description = ?,
            image_url = ?,
            status = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $name,
        $category,
        $quantity,
        $unit,
        $sellingPrice,
        $description,
        $imageUrl,
        $status,
        $productId
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Product updated successfully",
        "product" => [
            "id" => (int)$productId,
            "name" => $name,
            "category" => $category,
            "quantity" => $quantity,
            "unit" => $unit,
            "selling_price" => $sellingPrice,
            "description" => $description,
            "image_url" => $imageUrl,
            "status" => $status
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
