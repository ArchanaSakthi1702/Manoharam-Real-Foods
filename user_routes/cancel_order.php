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
require_once "../auth/auth_middleware.php";

$user = authenticate();

$data = json_decode(file_get_contents("php://input"), true);
$orderId = isset($data["order_id"]) ? (int)$data["order_id"] : null;
$reason = trim($data["reason"] ?? "");

if (!$orderId) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "order_id is required"
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, customer_id, status
        FROM orders
        WHERE id = ? AND customer_id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$orderId, $user->id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found");
    }

    if ($order["status"] === "CANCELLED") {
        throw new Exception("Order is already cancelled");
    }

    if ($order["status"] !== "PENDING") {
        throw new Exception("Order cannot be cancelled because it is already " . strtolower(str_replace('_', ' ', $order["status"])));
    }

    // 1. Restore batch inventory
    $restoredCount = 0;
    try {
        $stmtDeductions = $pdo->prepare("
            SELECT batch_id, quantity
            FROM order_batch_deductions
            WHERE order_id = ?
        ");
        $stmtDeductions->execute([$orderId]);
        $deductions = $stmtDeductions->fetchAll(PDO::FETCH_ASSOC);

        foreach ($deductions as $d) {
            $stmtRestore = $pdo->prepare("
                UPDATE inventory_batches
                SET remaining_quantity = remaining_quantity + ?
                WHERE id = ?
            ");
            $stmtRestore->execute([$d["quantity"], $d["batch_id"]]);
            $restoredCount++;
        }
    } catch (PDOException $e) {
        $deductions = [];
    }

    // Fallback if no specific batch deductions logged
    if ($restoredCount === 0) {
        $stmtItems = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $stmtItems->execute([$orderId]);
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

    // 2. Update status to CANCELLED
    try {
        $stmtUpdate = $pdo->prepare("
            UPDATE orders
            SET status = 'CANCELLED', cancellation_reason = ?, cancelled_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([$reason ?: "Cancelled by customer", $orderId]);
    } catch (PDOException $e) {
        $stmtUpdate = $pdo->prepare("UPDATE orders SET status = 'CANCELLED' WHERE id = ?");
        $stmtUpdate->execute([$orderId]);
    }

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Order cancelled successfully and inventory restored",
        "order_id" => $orderId,
        "status" => "CANCELLED"
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
