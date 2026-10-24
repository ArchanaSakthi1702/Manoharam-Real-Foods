<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/../cors.php";
require_once "../db.php";
require_once "../auth/admin_middleware.php";

requireAdmin();

try {

    $days = (int)($_GET['days'] ?? 3);

    if ($days < 0) {
        $days = 3;
    }

    $stmt = $pdo->prepare("
        SELECT
            ib.id,
            ib.product_id,
            p.name AS product_name,
            p.quantity AS product_quantity,
            p.unit AS product_unit,

            ib.batch_no,
            ib.manufactured_date,
            ib.expiry_date,
            ib.quantity,
            ib.remaining_quantity,

            DATEDIFF(ib.expiry_date, CURDATE()) AS days_remaining

        FROM inventory_batches ib

        JOIN products p
            ON p.id = ib.product_id

        WHERE ib.remaining_quantity > 0
        AND ib.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)

        ORDER BY ib.expiry_date ASC
    ");

    $stmt->execute([$days]);

    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "days" => $days,
        "batches" => $batches
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}