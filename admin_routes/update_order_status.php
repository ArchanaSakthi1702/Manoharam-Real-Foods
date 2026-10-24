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

$order_id = $data["order_id"] ?? null;
$status = $data["status"] ?? null;
$cancellation_reason = trim($data["cancellation_reason"] ?? $data["reason"] ?? "");

$allowedStatuses = [
    "CONFIRMED",
    "OUT_FOR_DELIVERY",
    "DELIVERED",
    "CANCELLED"
];

if (
    !$order_id ||
    !$status
) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "order_id and status are required"
    ]);

    exit;
}

if (!in_array($status, $allowedStatuses)) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid status"
    ]);

    exit;
}

try {
    $pdo->beginTransaction();

    // Check order
    $stmt = $pdo->prepare("
        SELECT
            id,
            status
        FROM orders
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([$order_id]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found");
    }

    if (
        $order["status"] === "DELIVERED" ||
        $order["status"] === "CANCELLED"
    ) {
        throw new Exception(
            "Order can no longer be modified"
        );
    }

    // If changing to CANCELLED, restore inventory
    if ($status === 'CANCELLED') {
        $restored = false;
        try {
            $stmtDeductions = $pdo->prepare("
                SELECT batch_id, quantity FROM order_batch_deductions WHERE order_id = ?
            ");
            $stmtDeductions->execute([$order_id]);
            $deductions = $stmtDeductions->fetchAll(PDO::FETCH_ASSOC);

            foreach ($deductions as $d) {
                $stmtRestore = $pdo->prepare("
                    UPDATE inventory_batches
                    SET remaining_quantity = remaining_quantity + ?
                    WHERE id = ?
                ");
                $stmtRestore->execute([$d["quantity"], $d["batch_id"]]);
                $restored = true;
            }
        } catch (PDOException $e) {
            $deductions = [];
        }

        // Fallback restoration
        if (!$restored) {
            $stmtItems = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $stmtItems->execute([$order_id]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                $stmtBatch = $pdo->prepare("
                    SELECT id FROM inventory_batches
                    WHERE product_id = ?
                    ORDER BY expiry_date DESC, id DESC
                    LIMIT 1
                ");
                $stmtBatch->execute([$item["product_id"]]);
                $batch = $stmtBatch->fetch(PDO::FETCH_ASSOC);

                if ($batch) {
                    $stmtRestore = $pdo->prepare("
                        UPDATE inventory_batches
                        SET remaining_quantity = remaining_quantity + ?
                        WHERE id = ?
                    ");
                    $stmtRestore->execute([$item["quantity"], $batch["id"]]);
                }
            }
        }
    }

    // Update status
    try {
        $stmt = $pdo->prepare("
            UPDATE orders
            SET status = ?, cancellation_reason = ?, cancelled_at = IF(? = 'CANCELLED', NOW(), cancelled_at)
            WHERE id = ?
        ");
        $stmt->execute([
            $status,
            ($status === 'CANCELLED' ? ($cancellation_reason ?: "Cancelled by administrator") : null),
            $status,
            $order_id
        ]);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare("
            UPDATE orders
            SET status = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $order_id]);
    }

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Order status updated successfully",
        "order" => [
            "id" => $order_id,
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
