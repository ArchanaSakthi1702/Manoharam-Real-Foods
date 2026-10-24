<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../cors.php";
require_once "../db.php";
require_once "../auth/admin_middleware.php";
require_once "../helpers/upload_image.php";

$admin = requireAdmin();

$name = trim($_POST["name"] ?? "");
$pricePerWeek = (float)($_POST["price_per_week"] ?? 0);
$description = trim($_POST["description"] ?? "");
$category = strtoupper(
    trim($_POST["category"] ?? "")
);
$status = strtoupper($_POST["status"] ?? "ACTIVE");

$productsInput = json_decode(
    $_POST["products"] ?? "[]",
    true
);

$imageUrl = null;

if (
    isset($_FILES["image"]) &&
    $_FILES["image"]["error"] === 0
) {
    $imageUrl = uploadImageToHostinger(
        $_FILES["image"],
        "subscription_plans"
    );
}

if (
    empty($productsInput) ||
    empty($name) ||
    $pricePerWeek <= 0
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "products, name and price_per_week are required"
    ]);

    exit;
}

if (
    !in_array(
        $category,
        ["VEG", "NON_VEG"]
    )
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "category must be VEG or NON_VEG"
    ]);

    exit;
}

if (
    !in_array(
        $status,
        ["ACTIVE", "INACTIVE"]
    )
) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid status"
    ]);

    exit;
}

$productIds = [];

foreach ($productsInput as $item) {

    $productId = (int)($item["product_id"] ?? 0);
    $deliveryOrder = (int)($item["delivery_order"] ?? 0);

    if (
        $productId <= 0 ||
        $deliveryOrder <= 0
    ) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Each product must contain product_id and delivery_order"
        ]);

        exit;
    }

    $productIds[] = $productId;
}

try {

    $pdo->beginTransaction();

    $placeholders = implode(
        ",",
        array_fill(
            0,
            count($productIds),
            "?"
        )
    );

    $stmt = $pdo->prepare("
        SELECT id, name
        FROM products
        WHERE id IN ($placeholders)
    ");

    $stmt->execute($productIds);

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (
        count($products) !== count($productIds)
    ) {

        $foundIds = array_column(
            $products,
            "id"
        );

        $missingIds = array_diff(
            $productIds,
            $foundIds
        );

        $pdo->rollBack();

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Product(s) not found: " .
                implode(", ", $missingIds)
        ]);

        exit;
    }

    $stmt = $pdo->prepare("
    INSERT INTO subscription_plans
    (
        name,
        category,
        price_per_week,
        description,
        image_url,
        status
    )
    VALUES
    (
        ?, ?, ?, ?, ?, ?
    )
");

    $stmt->execute([
        $name,
        $category,
        $pricePerWeek,
        $description,
        $imageUrl,
        $status
    ]);

    $planId = (int)$pdo->lastInsertId();

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
            "id" => $planId,
            "name" => $name,
            "category" => $category,
            "price_per_week" => (float)$pricePerWeek,
            "description" => $description,
            "image_url" => $imageUrl,
            "status" => $status,
            "products" => $productsInput
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