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
require_once "../helpers/mail_helper.php";

requireAdmin();

try {

    $threshold = (float)($_GET['threshold'] ?? 10);

    if ($threshold < 0) {
        $threshold = 10;
    }

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.name,
            p.quantity AS product_quantity,
            p.unit AS product_unit,

            COALESCE(SUM(ib.remaining_quantity), 0) AS remaining_quantity

        FROM products p

        LEFT JOIN inventory_batches ib
            ON ib.product_id = p.id

        WHERE p.status = 'ACTIVE'

        GROUP BY
            p.id,
            p.name,
            p.quantity,
            p.unit

        HAVING remaining_quantity <= ?

        ORDER BY remaining_quantity ASC
    ");

    $stmt->execute([$threshold]);

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
     * Send admin email if low-stock products exist.
     *
     * Mail failure should NOT make the inventory API fail.
     */
    $mailSent = false;

    if (!empty($products)) {

        try {

            MailHelper::sendAdminLowStockNotification(
                $products,
                $threshold
            );

            $mailSent = true;

        } catch (Exception $mailException) {

            error_log(
                "Low stock notification failed: " .
                $mailException->getMessage()
            );
        }
    }

    echo json_encode([
        "success" => true,
        "threshold" => $threshold,
        "products" => $products,
        "mail_sent" => $mailSent
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}