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

$name = trim($data["name"] ?? "");
$category = trim($data["category"] ?? "");

$quantity = $data["quantity"] ?? null;
$unit = strtolower(trim($data["unit"] ?? ""));

$selling_price = $data["selling_price"] ?? null;
$description = trim($data["description"] ?? "");
$status = strtoupper($data["status"] ?? "ACTIVE");

$isSubscriptionOnly = false;
if (isset($data["is_subscription_only"])) {
    $val = $data["is_subscription_only"];
    $isSubscriptionOnly = ($val === true || $val === 1 || $val === "1" || strtolower((string)$val) === "true");
} elseif (isset($data["subscription_only"])) {
    $val = $data["subscription_only"];
    $isSubscriptionOnly = ($val === true || $val === 1 || $val === "1" || strtolower((string)$val) === "true");
}
$isSubscriptionOnlyInt = $isSubscriptionOnly ? 1 : 0;

$image_url = null;

try {

    // Upload image if provided
    if (
        isset($_FILES["image"]) &&
        $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        $image_url = uploadImageToHostinger(
            $_FILES["image"],
            "foods_profiles"
        );
    } elseif (!empty($data["image_url"])) {
        $image_url = trim($data["image_url"]);
    } elseif (!empty($data["image"]) && is_string($data["image"])) {
        $image_url = trim($data["image"]);
    }

    // =========================
    // VALIDATION
    // =========================

    if (
        empty($name) ||
        $quantity === null ||
        empty($unit) ||
        (!$isSubscriptionOnly && $selling_price === null)
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

    if ($isSubscriptionOnly) {
        if ($selling_price === null || $selling_price === "") {
            $selling_price = 0.00;
        } elseif (!is_numeric($selling_price) || (float)$selling_price < 0) {
            throw new Exception(
                "selling_price must be a valid non-negative number"
            );
        }
    } else {
        if (
            !is_numeric($selling_price) ||
            $selling_price <= 0
        ) {
            throw new Exception(
                "selling_price must be greater than 0"
            );
        }
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
            status,
            is_subscription_only
        )
        VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?
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
        $status,
        $isSubscriptionOnlyInt
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Product created successfully",
        "product_id" => (int)$pdo->lastInsertId(),
        "is_subscription_only" => (bool)$isSubscriptionOnly,
        "image_url" => $image_url
    ]);

} catch (Exception $e) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}