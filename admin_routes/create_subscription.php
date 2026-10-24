<?php

require_once __DIR__ . "/../cors.php";
require_once "../db.php";
require_once "../auth/admin_middleware.php";

header("Content-Type: application/json");

$admin = requireAdmin();

$data = json_decode(file_get_contents("php://input"), true);

// Accept either a single product_id or an array of product_ids.
$productsInput = $data["products"] ?? [];

$name = trim($data["name"] ?? "");
$pricePerWeek = (float)($data["price_per_week"] ?? 0);
$description = trim($data["description"] ?? "");
$status = strtoupper($data["status"] ?? "ACTIVE");

if (
    empty($productsInput) ||
    empty($name) ||
    $pricePerWeek <= 0
){
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "product_ids, name and price_per_week are required"
    ]);

    exit;
}

if (!in_array($status, ["ACTIVE", "INACTIVE"])) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid status"
    ]);

    exit;
}

    $productIds = [];
    $deliveryOrders = [];

    foreach ($productsInput as $item) {

        $productId = (int)($item["product_id"] ?? 0);
        $deliveryOrder = (int)($item["delivery_order"] ?? 0);

        if ($productId <= 0 || $deliveryOrder <= 0) {

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "message" => "Each product must contain product_id and delivery_order"
            ]);

            exit;
        }

        $productIds[] = $productId;
        $deliveryOrders[] = $deliveryOrder;
    }

try {

    $pdo->beginTransaction();

    // Validate all products exist.
    $placeholders = implode(",", array_fill(0, count($productIds), "?"));

    $stmt = $pdo->prepare("
        SELECT id, name
        FROM products
        WHERE id IN ($placeholders)
    ");

    $stmt->execute($productIds);

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($products) !== count($productIds)) {

        $foundIds = array_column($products, "id");
        $missingIds = array_diff($productIds, $foundIds);

        $pdo->rollBack();

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Product(s) not found: " . implode(", ", $missingIds)
        ]);

        exit;
    }

    // Create plan.
    $stmt = $pdo->prepare("
        INSERT INTO subscription_plans
        (
            name,
            price_per_week,
            description,
            status
        )
        VALUES
        (
            ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        $name,
        $pricePerWeek,
        $description,
        $status
    ]);

    $planId = $pdo->lastInsertId();

    // Link products to plan.
    $stmt = $pdo->prepare("
        INSERT INTO subscription_plan_products
        (
            plan_id,
            product_id,
            delivery_order
        )
        VALUES
        (
            ?, ?, ?
        )
    ");

    foreach ($productsInput as $item) {

        $stmt->execute([
            $planId,
            (int)$item["product_id"],
            (int)$item["delivery_order"]
        ]);
    }
    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Subscription plan created successfully",
        "data" => [
            "id" => (int)$planId,
            "products" => $productsInput,
            "name" => $name,
            "price_per_week" => (float)$pricePerWeek,
            "description" => $description,
            "status" => $status
        ]
    ]);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
