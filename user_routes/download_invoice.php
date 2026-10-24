<?php

require_once __DIR__ . "/../cors.php";
require_once "../db.php";
require_once "../auth/auth_middleware.php";

$user = authenticate();

$orderId = isset($_GET["id"]) ? (int)$_GET["id"] : (isset($_GET["order_id"]) ? (int)$_GET["order_id"] : null);
$format  = strtolower(trim($_GET["format"] ?? "html"));

if (!$orderId) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "order_id is required"]);
    exit;
}

try {
    // 1. Fetch order & customer
    $stmt = $pdo->prepare("
        SELECT
            o.*,
            u.name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone
        FROM orders o
        INNER JOIN users u ON u.id = o.customer_id
        WHERE o.id = ? AND o.customer_id = ?
        LIMIT 1
    ");
    $stmt->execute([$orderId, $user->id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Order not found"]);
        exit;
    }

    // 2. Fetch order items
    $stmt = $pdo->prepare("
        SELECT
            oi.quantity,
            oi.price,
            (oi.quantity * oi.price) AS total_price,
            p.name,
            p.category,
            p.unit
        FROM order_items oi
        INNER JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($format === "json") {
        header("Content-Type: application/json");
        echo json_encode([
            "success" => true,
            "invoice" => [
                "invoice_number" => "INV-" . str_pad($order["id"], 6, "0", STR_PAD_LEFT),
                "order_id" => (int)$order["id"],
                "order_date" => $order["order_date"],
                "status" => $order["status"],
                "payment_method" => $order["payment_method"],
                "payment_status" => $order["payment_status"],
                "customer" => [
                    "name" => $order["customer_name"],
                    "email" => $order["customer_email"],
                    "phone" => $order["customer_phone"],
                    "delivery_address" => $order["delivery_address"]
                ],
                "items" => $items,
                "total_amount" => (float)$order["total_amount"]
            ]
        ]);
        exit;
    }

    // Render HTML Printable Invoice
    header("Content-Type: text/html; charset=UTF-8");
    $invoiceNo = "INV-" . str_pad($order["id"], 6, "0", STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Tax Invoice - <?= $invoiceNo ?></title>
  <style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 30px; background: #fafafa; color: #333; }
    .invoice-card { max-width: 800px; margin: auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
    .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #C1272D; padding-bottom: 20px; }
    .brand-title { color: #C1272D; margin: 0; font-size: 26px; }
    .invoice-meta { text-align: right; }
    .details { display: flex; justify-content: space-between; margin: 30px 0; }
    .table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
    .table th { background: #fdf2f2; color: #C1272D; }
    .total-section { text-align: right; margin-top: 25px; font-size: 18px; }
    .btn-print { background: #C1272D; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; float: right; margin-bottom: 20px; }
    @media print { .btn-print { display: none; } body { padding: 0; background: #fff; } .invoice-card { box-shadow: none; padding: 0; } }
  </style>
</head>
<body>
  <div class="invoice-card">
    <button class="btn-print" onclick="window.print()">🖨️ Print Invoice</button>
    <div style="clear:both;"></div>
    <div class="header">
      <div>
        <h1 class="brand-title">Manoharam Real Foods</h1>
        <p style="margin:5px 0;color:#666;">Authentic Homemade Traditional Foods & Pickles</p>
      </div>
      <div class="invoice-meta">
        <h3 style="margin:0;color:#333;">TAX INVOICE</h3>
        <p style="margin:5px 0;"><b>Invoice:</b> <?= $invoiceNo ?></p>
        <p style="margin:5px 0;"><b>Date:</b> <?= date("d M Y, h:i A", strtotime($order["order_date"])) ?></p>
      </div>
    </div>

    <div class="details">
      <div>
        <p style="margin:0;font-weight:bold;color:#C1272D;">BILLED TO:</p>
        <p style="margin:5px 0;font-weight:600;"><?= htmlspecialchars($order["customer_name"]) ?></p>
        <p style="margin:5px 0;color:#555;"><?= htmlspecialchars($order["customer_phone"]) ?> | <?= htmlspecialchars($order["customer_email"]) ?></p>
        <p style="margin:5px 0;color:#555;max-width:300px;"><?= htmlspecialchars($order["delivery_address"] ?: "Standard address") ?></p>
      </div>
      <div style="text-align:right;">
        <p style="margin:5px 0;"><b>Payment Method:</b> <?= $order["payment_method"] ?></p>
        <p style="margin:5px 0;"><b>Payment Status:</b> <span style="color:<?= $order["payment_status"] === 'PAID' ? 'green' : 'orange' ?>;font-weight:bold;"><?= $order["payment_status"] ?></span></p>
        <p style="margin:5px 0;"><b>Order Status:</b> <?= $order["status"] ?></p>
      </div>
    </div>

    <table class="table">
      <thead>
        <tr>
          <th>Item</th>
          <th style="text-align:center;">Quantity</th>
          <th style="text-align:right;">Price</th>
          <th style="text-align:right;">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
          <td><?= htmlspecialchars($item["name"]) ?> <span style="color:#888;font-size:12px;">(<?= $item["category"] ?>)</span></td>
          <td style="text-align:center;"><?= (float)$item["quantity"] ?></td>
          <td style="text-align:right;">₹<?= number_format((float)$item["price"], 2) ?></td>
          <td style="text-align:right;">₹<?= number_format((float)$item["total_price"], 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="total-section">
      <p style="margin:5px 0;color:#666;font-size:15px;">Subtotal: ₹<?= number_format((float)$order["total_amount"], 2) ?></p>
      <p style="margin:5px 0;color:#666;font-size:15px;">Delivery: <span style="color:green;font-weight:bold;">FREE</span></p>
      <h2 style="margin:10px 0;color:#C1272D;">Grand Total: ₹<?= number_format((float)$order["total_amount"], 2) ?></h2>
    </div>

    <div style="margin-top:40px;border-top:1px solid #eee;padding-top:15px;color:#888;font-size:12px;text-align:center;">
      Thank you for shopping with Manoharam Real Foods! For queries, contact us at support@manoharam.com.
    </div>
  </div>
</body>
</html>
<?php
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
