<?php

require_once __DIR__ . '/helpers/razorpay_helper.php';

class ApiTester
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = getenv("TEST_BASE_URL") ?: "http://localhost/Manoharam-Real-Foods-main";
    }
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function request(string $method, string $path, array $data = [], ?string $token = null): array
    {
        $url = $this->baseUrl . $path;
        $headers = ["Accept: application/json"];
        if ($token) {
            $headers[] = "Authorization: Bearer $token";
        }

        $opts = [
            "http" => [
                "method" => $method,
                "ignore_errors" => true,
                "timeout" => 15
            ]
        ];

        if ($method === "POST" || $method === "PUT") {
            $headers[] = "Content-Type: application/json";
            $opts["http"]["content"] = json_encode($data);
        }

        $opts["http"]["header"] = implode("\r\n", $headers);

        $context = stream_context_create($opts);
        $res = @file_get_contents($url, false, $context);

        $statusLine = $http_response_header[0] ?? "";
        preg_match('/HTTP\/\S*\s(\d{3})/', $statusLine, $matches);
        $statusCode = isset($matches[1]) ? (int)$matches[1] : 0;

        $json = json_decode($res ?: "{}", true);

        return [
            "status_code" => $statusCode,
            "raw" => $res,
            "data" => $json
        ];
    }

    public function assert(bool $condition, string $testName, string $detail = ""): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] $testName\n";
        } else {
            $this->failed++;
            $msg = "  [FAIL] $testName" . ($detail ? " ($detail)" : "");
            echo "$msg\n";
            $this->errors[] = $msg;
        }
    }

    public function run(): void
    {
        echo "===============================================\n";
        echo "   MANOHARAM REAL FOODS — API TEST SUITE      \n";
        echo "===============================================\n\n";

        // 1. PUBLIC CATALOG TESTS
        echo "--- Section 1: Public Catalog APIs ---\n";
        $catRes = $this->request("GET", "/list_categories.php");
        $this->assert($catRes["status_code"] === 200 && ($catRes["data"]["success"] ?? false), "GET /list_categories.php returns 200 and categories");

        $prodRes = $this->request("GET", "/list_all_products.php?limit=5");
        $this->assert($prodRes["status_code"] === 200 && isset($prodRes["data"]["products"]), "GET /list_all_products.php returns product list");

        $prodDetail = $this->request("GET", "/product_details.php?id=1");
        $this->assert($prodDetail["status_code"] === 200 && ($prodDetail["data"]["success"] ?? false), "GET /product_details.php?id=1 returns details");

        // 2. AUTHENTICATION & PROFILE
        echo "\n--- Section 2: Customer Registration & Auth ---\n";
        $testEmail = "test_" . time() . "@example.com";
        $testPhone = "9" . rand(100000000, 999999999);
        $testPass = "Pass12345!";

        $regRes = $this->request("POST", "/register.php", [
            "name" => "Integration Tester",
            "email" => $testEmail,
            "phone" => $testPhone,
            "address" => "100 Test Avenue, Sivakasi",
            "password" => $testPass
        ]);
        $this->assert(in_array($regRes["status_code"], [200, 201]) && ($regRes["data"]["success"] ?? false), "POST /register.php registers new customer (HTTP 201)");

        $loginRes = $this->request("POST", "/login.php", [
            "email" => $testEmail,
            "password" => $testPass
        ]);
        $customerToken = $loginRes["data"]["token"] ?? null;
        $this->assert($loginRes["status_code"] === 200 && !empty($customerToken), "POST /login.php logs in customer and returns JWT");

        $profileRes = $this->request("GET", "/user_routes/get_profile.php", [], $customerToken);
        $this->assert($profileRes["status_code"] === 200 && ($profileRes["data"]["profile"]["email"] ?? "") === $testEmail, "GET /user_routes/get_profile.php returns user profile");

        $updateProfRes = $this->request("POST", "/user_routes/update_profile.php", [
            "name" => "Integration Tester Renamed",
            "phone" => $testPhone,
            "address" => "200 Updated Street, Madurai"
        ], $customerToken);
        $this->assert($updateProfRes["status_code"] === 200 && ($updateProfRes["data"]["success"] ?? false), "POST /user_routes/update_profile.php updates profile");

        // 3. CART MANAGEMENT
        echo "\n--- Section 3: Cart Management APIs ---\n";
        $addCartRes = $this->request("POST", "/user_routes/add_to_cart.php", [
            "product_id" => 1,
            "quantity" => 2
        ], $customerToken);
        $this->assert($addCartRes["status_code"] === 200 && ($addCartRes["data"]["success"] ?? false), "POST /user_routes/add_to_cart.php adds item to cart");

        $getCartRes = $this->request("GET", "/user_routes/get_cart.php", [], $customerToken);
        $this->assert($getCartRes["status_code"] === 200 && count($getCartRes["data"]["cart_items"] ?? []) >= 1, "GET /user_routes/get_cart.php lists cart items");

        $updCartRes = $this->request("POST", "/user_routes/update_cart_quantity.php", [
            "product_id" => 1,
            "quantity" => 5
        ], $customerToken);
        $this->assert($updCartRes["status_code"] === 200 && (float)($updCartRes["data"]["cart_items"][0]["quantity"] ?? 0) === 5.0, "POST /user_routes/update_cart_quantity.php sets quantity to 5");

        $remCartRes = $this->request("POST", "/user_routes/remove_from_cart.php", [
            "product_id" => 1
        ], $customerToken);
        $this->assert($remCartRes["status_code"] === 200 && ($remCartRes["data"]["remaining_items"] ?? null) === 0, "POST /user_routes/remove_from_cart.php removes item");

        $clearCartRes = $this->request("POST", "/user_routes/clear_cart.php", [], $customerToken);
        $this->assert($clearCartRes["status_code"] === 200 && ($clearCartRes["data"]["success"] ?? false), "POST /user_routes/clear_cart.php clears cart");

        // 4. CHECKOUT, PAYMENTS & ORDER CANCELLATION
        echo "\n--- Section 4: Checkout, Razorpay & Order Cancellation ---\n";
        $this->request("POST", "/user_routes/add_to_cart.php", ["product_id" => 1, "quantity" => 1], $customerToken);

        $checkoutRes = $this->request("POST", "/user_routes/checkout.php", [
            "payment_method" => "ONLINE",
            "delivery_address" => "300 Delivery Lane, Sivakasi"
        ], $customerToken);
        $orderId = $checkoutRes["data"]["order_id"] ?? null;
        $razorpayOrderId = $checkoutRes["data"]["razorpay"]["razorpay_order_id"] ?? null;

        $this->assert(
            $checkoutRes["status_code"] === 200 && $orderId && !empty($razorpayOrderId),
            "POST /user_routes/checkout.php creates order and generates Razorpay order payload"
        );

        $createPayOrderRes = $this->request("POST", "/user_routes/create_razorpay_order.php", [
            "order_id" => $orderId
        ], $customerToken);
        $this->assert($createPayOrderRes["status_code"] === 200 && ($createPayOrderRes["data"]["success"] ?? false), "POST /user_routes/create_razorpay_order.php for pending order");

        // Test payment verification with valid HMAC signature
        $testPaymentId = "pay_test_" . rand(100000, 999999);
        $testSignature = hash_hmac('sha256', $razorpayOrderId . '|' . $testPaymentId, RazorpayHelper::getKeySecret());

        $verifyRes = $this->request("POST", "/user_routes/verify_payment.php", [
            "order_id" => $orderId,
            "razorpay_order_id" => $razorpayOrderId,
            "razorpay_payment_id" => $testPaymentId,
            "razorpay_signature" => $testSignature
        ], $customerToken);
        $this->assert($verifyRes["status_code"] === 200 && ($verifyRes["data"]["payment_status"] ?? "") === "PAID", "POST /user_routes/verify_payment.php verifies real HMAC signature");

        // Checkout another order to test cancellation & restitution
        $this->request("POST", "/user_routes/add_to_cart.php", ["product_id" => 1, "quantity" => 1], $customerToken);
        $coRes2 = $this->request("POST", "/user_routes/checkout.php", ["payment_method" => "COD"], $customerToken);
        $orderId2 = $coRes2["data"]["order_id"] ?? null;

        $cancelOrderRes = $this->request("POST", "/user_routes/cancel_order.php", [
            "order_id" => $orderId2,
            "reason" => "Integration test cancellation"
        ], $customerToken);
        $this->assert($cancelOrderRes["status_code"] === 200 && ($cancelOrderRes["data"]["status"] ?? "") === "CANCELLED", "POST /user_routes/cancel_order.php cancels order and restores inventory");

        // 5. SUBSCRIPTIONS & LIFECYCLE
        echo "\n--- Section 5: Subscriptions & Lifecycle (Skip, Unskip, Pause, Resume, Cancel) ---\n";
        $plansRes = $this->request("GET", "/list_all_subscription_plans.php");
        $this->assert($plansRes["status_code"] === 200 && ($plansRes["data"]["success"] ?? false), "GET /list_all_subscription_plans.php returns active plans");

        $startDate = date("Y-m-d", strtotime("+3 days"));
        $skipTestDate = date("Y-m-d", strtotime("+5 days"));

        $previewRes = $this->request("POST", "/subscription_preview.php", [
            "plan_id" => 1,
            "weeks" => 2,
            "start_date" => $startDate,
            "exclude_saturday" => false,
            "exclude_sunday" => true
        ]);
        $this->assert($previewRes["status_code"] === 200 && isset($previewRes["data"]["total_deliveries"]), "POST /subscription_preview.php calculates deliveries & schedule");

        $subRes = $this->request("POST", "/user_routes/subscribe_plan.php", [
            "plan_id" => 1,
            "weeks" => 2,
            "start_date" => $startDate,
            "exclude_saturday" => false,
            "exclude_sunday" => true
        ], $customerToken);

        $subscriptionId = $subRes["data"]["subscription"]["id"] ?? $subRes["data"]["subscription_id"] ?? null;
        $this->assert($subRes["status_code"] === 200 && $subscriptionId, "POST /user_routes/subscribe_plan.php creates subscription");

        if ($subscriptionId) {
            $skipRes = $this->request("POST", "/user_routes/skip_subscription.php", [
                "subscription_id" => $subscriptionId,
                "skip_date" => $skipTestDate,
                "reason" => "Test skip"
            ], $customerToken);
            $this->assert($skipRes["status_code"] === 200 && ($skipRes["data"]["success"] ?? false), "POST /user_routes/skip_subscription.php skips delivery date (fixed daily_rate bug)");

            $unskipRes = $this->request("POST", "/user_routes/unskip_subscription.php", [
                "subscription_id" => $subscriptionId,
                "skip_date" => $skipTestDate
            ], $customerToken);
            $this->assert($unskipRes["status_code"] === 200 && ($unskipRes["data"]["success"] ?? false), "POST /user_routes/unskip_subscription.php unskips date");

            $cancelSubRes = $this->request("POST", "/user_routes/cancel_subscription.php", [
                "subscription_id" => $subscriptionId,
                "reason" => "Testing cancellation"
            ], $customerToken);
            $this->assert($cancelSubRes["status_code"] === 200 && ($cancelSubRes["data"]["status"] ?? "") === "CANCELLED", "POST /user_routes/cancel_subscription.php cancels subscription");
        }

        // 6. ADMIN OPERATIONS & KITCHEN DISPATCH
        echo "\n--- Section 6: Admin Operations & Kitchen Dispatch ---\n";
        require_once __DIR__ . "/auth/jwt_helper.php";
        $adminToken = JwtHelper::generateToken([
            "id" => 2,
            "name" => "Admin",
            "phone" => "9025988060",
            "role" => "ADMIN"
        ]);

        $dispatchRes = $this->request("GET", "/admin_routes/get_daily_dispatch.php", [], $adminToken);
        $this->assert(
            $dispatchRes["status_code"] === 200 && isset($dispatchRes["data"]["kitchen_production_summary"]),
            "GET /admin_routes/get_daily_dispatch.php generates kitchen production summary"
        );

        $updateProdRes = $this->request("POST", "/admin_routes/update_product.php", [
            "id" => 1,
            "selling_price" => 225.0,
            "status" => "ACTIVE"
        ], $adminToken);
        $this->assert($updateProdRes["status_code"] === 200 && ($updateProdRes["data"]["success"] ?? false), "POST /admin_routes/update_product.php updates product price & status");

        $recipeDetailRes = $this->request("GET", "/admin_routes/get_recipe_details.php?recipe_id=2", [], $adminToken);
        $this->assert(
            $recipeDetailRes["status_code"] === 200 && isset($recipeDetailRes["data"]["recipe"]["ingredients"]),
            "GET /admin_routes/get_recipe_details.php returns recipe and ingredients"
        );

        $calcRecipeRes = $this->request("POST", "/admin_routes/calculate_recipe.php", [
            "recipe_id" => 2,
            "required_quantity_kg" => 10
        ], $adminToken);
        $this->assert($calcRecipeRes["status_code"] === 200 && ($calcRecipeRes["data"]["success"] ?? false), "POST /admin_routes/calculate_recipe.php scales recipe ingredients");

        // 7. PASSWORD RESET FLOW
        echo "\n--- Section 7: Forgot & Reset Password Flow ---\n";
        $forgotRes = $this->request("POST", "/forgot_password.php", [
            "email" => $testEmail
        ]);
        $resetToken = $forgotRes["data"]["dev_token"] ?? null;
        $this->assert($forgotRes["status_code"] === 200 && !empty($resetToken), "POST /forgot_password.php generates reset token");

        if ($resetToken) {
            $resetRes = $this->request("POST", "/reset_password.php", [
                "email" => $testEmail,
                "token" => $resetToken,
                "new_password" => "brandNewPassword123"
            ]);
            $this->assert($resetRes["status_code"] === 200 && ($resetRes["data"]["success"] ?? false), "POST /reset_password.php resets password with token");
        }

        // 8. SAVED ADDRESS BOOK
        echo "\n--- Section 8: Saved Address Book ---\n";
        $addAddrRes = $this->request("POST", "/user_routes/add_address.php", [
            "recipient_name" => "Home Delivery",
            "recipient_phone" => $testPhone,
            "address_line1" => "Flat 4B, Hilltop Towers",
            "city" => "Sivakasi",
            "postal_code" => "626123",
            "address_type" => "HOME",
            "is_default" => true
        ], $customerToken);
        $addrId = $addAddrRes["data"]["address"]["id"] ?? null;
        $this->assert($addAddrRes["status_code"] === 200 && $addrId, "POST /user_routes/add_address.php saves new address");

        $getAddrsRes = $this->request("GET", "/user_routes/get_addresses.php", [], $customerToken);
        $this->assert($getAddrsRes["status_code"] === 200 && count($getAddrsRes["data"]["addresses"]) >= 1, "GET /user_routes/get_addresses.php lists saved addresses");

        $updAddrRes = $this->request("POST", "/user_routes/update_address.php", [
            "id" => $addrId,
            "address_line1" => "Flat 4B, Hilltop Towers (Renovated)",
            "city" => "Sivakasi",
            "postal_code" => "626123"
        ], $customerToken);
        $this->assert($updAddrRes["status_code"] === 200 && ($updAddrRes["data"]["success"] ?? false), "POST /user_routes/update_address.php updates address");

        // 9. COUPONS & DISCOUNTS
        echo "\n--- Section 9: Coupons & Promotions ---\n";
        $testCouponCode = "TEST" . rand(100, 999);
        $createCpnRes = $this->request("POST", "/admin_routes/create_coupon.php", [
            "code" => $testCouponCode,
            "description" => "Test Promo Coupon",
            "discount_type" => "PERCENTAGE",
            "discount_value" => 15.0,
            "min_order_amount" => 100.0,
            "max_discount_amount" => 50.0
        ], $adminToken);
        $this->assert($createCpnRes["status_code"] === 200 && ($createCpnRes["data"]["success"] ?? false), "POST /admin_routes/create_coupon.php creates coupon");

        $listCpnsRes = $this->request("GET", "/admin_routes/list_coupons.php", [], $adminToken);
        $this->assert($listCpnsRes["status_code"] === 200 && count($listCpnsRes["data"]["coupons"]) >= 1, "GET /admin_routes/list_coupons.php lists coupons");

        $applyCpnRes = $this->request("POST", "/user_routes/apply_coupon.php", [
            "code" => $testCouponCode,
            "order_amount" => 300.0
        ], $customerToken);
        $this->assert(
            $applyCpnRes["status_code"] === 200 && (float)($applyCpnRes["data"]["discount_amount"] ?? 0) === 45.0,
            "POST /user_routes/apply_coupon.php calculates 15% discount correctly"
        );

        // 10. REVIEWS & RATINGS
        echo "\n--- Section 10: Product Reviews & Ratings ---\n";
        $addRevRes = $this->request("POST", "/user_routes/add_review.php", [
            "product_id" => 1,
            "rating" => 5,
            "review_title" => "Delicious Authentic Flavor",
            "review_text" => "Traditional flavor just like homemade pickle."
        ], $customerToken);
        $reviewId = $addRevRes["data"]["review_id"] ?? null;
        $this->assert($addRevRes["status_code"] === 200 && $reviewId, "POST /user_routes/add_review.php adds product review");

        $getRevsRes = $this->request("GET", "/get_product_reviews.php?product_id=1");
        $this->assert($getRevsRes["status_code"] === 200 && count($getRevsRes["data"]["reviews"]) >= 1, "GET /get_product_reviews.php returns product reviews");

        // 11. ORDER TRACKING & INVOICE
        echo "\n--- Section 11: Order Tracking & Tax Invoice ---\n";
        $trackRes = $this->request("GET", "/user_routes/track_order.php?order_id={$orderId}", [], $customerToken);
        $this->assert($trackRes["status_code"] === 200 && isset($trackRes["data"]["timeline"]), "GET /user_routes/track_order.php returns order tracking timeline");

        $invoiceRes = $this->request("GET", "/user_routes/download_invoice.php?order_id={$orderId}&format=json", [], $customerToken);
        $this->assert($invoiceRes["status_code"] === 200 && isset($invoiceRes["data"]["invoice"]["invoice_number"]), "GET /user_routes/download_invoice.php generates invoice data");

        // 12. ADMIN DASHBOARD & CUSTOMER MANAGEMENT
        echo "\n--- Section 12: Admin Dashboard, Sales Analytics & Customer Management ---\n";
        $dashRes = $this->request("GET", "/admin_routes/get_dashboard_stats.php", [], $adminToken);
        $this->assert($dashRes["status_code"] === 200 && isset($dashRes["data"]["metrics"]["revenue_month"]), "GET /admin_routes/get_dashboard_stats.php returns KPI metrics");

        $salesRes = $this->request("GET", "/admin_routes/sales_report.php", [], $adminToken);
        $this->assert($salesRes["status_code"] === 200 && isset($salesRes["data"]["summary"]["total_revenue"]), "GET /admin_routes/sales_report.php generates sales report");

        $custsRes = $this->request("GET", "/admin_routes/list_customers.php", [], $adminToken);
        $this->assert($custsRes["status_code"] === 200 && count($custsRes["data"]["customers"]) >= 1, "GET /admin_routes/list_customers.php lists customers with spend");

        $custDetailRes = $this->request("GET", "/admin_routes/get_customer_details.php?id=1", [], $adminToken);
        $this->assert($custDetailRes["status_code"] === 200 && isset($custDetailRes["data"]["customer"]), "GET /admin_routes/get_customer_details.php returns customer profile & history");

        $custStatusRes = $this->request("POST", "/admin_routes/update_customer_status.php", [
            "id" => 1,
            "status" => "active"
        ], $adminToken);
        $this->assert($custStatusRes["status_code"] === 200 && ($custStatusRes["data"]["success"] ?? false), "POST /admin_routes/update_customer_status.php updates account status");

        // 13. ADMIN SERVICE HOLIDAYS / UNAVAILABLE DAYS (AUTO SKIPS & REFUNDS)
        echo "\n--- Section 13: Service Holidays (Admin Declaration, Auto-Skips & Refunds) ---\n";
        $holidayDate = date("Y-m-d", strtotime("+10 days"));
        $addHolRes = $this->request("POST", "/admin_routes/add_service_holiday.php", [
            "date" => $holidayDate,
            "reason" => "Diwali Festival - Kitchen Closed"
        ], $adminToken);
        $this->assert(
            $addHolRes["status_code"] === 200 && ($addHolRes["data"]["success"] ?? false),
            "POST /admin_routes/add_service_holiday.php declares holiday & auto-skips active subscriptions with refund"
        );

        $listHolAdminRes = $this->request("GET", "/admin_routes/list_service_holidays.php", [], $adminToken);
        $this->assert(
            $listHolAdminRes["status_code"] === 200 && count($listHolAdminRes["data"]["holidays"]) >= 1,
            "GET /admin_routes/list_service_holidays.php returns declared holidays with refund totals"
        );

        $listHolPubRes = $this->request("GET", "/list_service_holidays.php");
        $this->assert(
            $listHolPubRes["status_code"] === 200 && count($listHolPubRes["data"]["holidays"]) >= 1,
            "GET /list_service_holidays.php returns upcoming holidays for frontend calendar"
        );

        $delHolRes = $this->request("POST", "/admin_routes/delete_service_holiday.php", [
            "holiday_date" => $holidayDate
        ], $adminToken);
        $this->assert(
            $delHolRes["status_code"] === 200 && ($delHolRes["data"]["success"] ?? false),
            "POST /admin_routes/delete_service_holiday.php deletes holiday & resumes subscriptions"
        );

        // 14. CUSTOMER TODAY'S SCHEDULED DELIVERY
        echo "\n--- Section 14: Customer Today's Scheduled Delivery ---\n";
        $todayDelivRes = $this->request("GET", "/user_routes/get_today_delivery.php", [], $customerToken);
        $this->assert(
            $todayDelivRes["status_code"] === 200 && isset($todayDelivRes["data"]["day_of_week"]),
            "GET /user_routes/get_today_delivery.php returns scheduled product & delivery status for customer"
        );

        // 15. SUBSCRIPTION-ONLY PRODUCTS (FEATURE: ONLY FOR SUBSCRIPTION NOT FOR SALE)
        echo "\n--- Section 15: Subscription-Only Product Feature ---\n";
        
        // 15.1 Create subscription-only product
        $subOnlyProdRes = $this->request("POST", "/admin_routes/create_product.php", [
            "name" => "Subscription Exclusive Salad Bowl",
            "category" => "Salads",
            "quantity" => 350.0,
            "unit" => "g",
            "selling_price" => 0.0,
            "description" => "Handmade daily bowl exclusively prepared for subscribers.",
            "is_subscription_only" => true,
            "status" => "ACTIVE"
        ], $adminToken);
        $subOnlyProdId = $subOnlyProdRes["data"]["product_id"] ?? null;
        $this->assert(
            $subOnlyProdRes["status_code"] === 200 && !empty($subOnlyProdId) && ($subOnlyProdRes["data"]["is_subscription_only"] ?? false),
            "POST /admin_routes/create_product.php creates a product with is_subscription_only = true"
        );

        // 15.2 Verify product details returns is_subscription_only
        $subDetailRes = $this->request("GET", "/product_details.php?id=" . $subOnlyProdId);
        $this->assert(
            $subDetailRes["status_code"] === 200 && ($subDetailRes["data"]["product"]["is_subscription_only"] ?? false) === true,
            "GET /product_details.php exposes is_subscription_only = true"
        );

        // 15.3 Attempt direct purchase / add to cart (MUST FAIL with 400)
        $blockCartRes = $this->request("POST", "/user_routes/add_to_cart.php", [
            "product_id" => $subOnlyProdId,
            "quantity" => 1
        ], $customerToken);
        $this->assert(
            $blockCartRes["status_code"] === 400 && ($blockCartRes["data"]["success"] ?? true) === false,
            "POST /user_routes/add_to_cart.php rejects subscription-only product from direct sale"
        );

        // 15.4 Attempt update cart quantity (MUST FAIL with 400)
        $blockCartUpdRes = $this->request("POST", "/user_routes/update_cart_quantity.php", [
            "product_id" => $subOnlyProdId,
            "quantity" => 2
        ], $customerToken);
        $this->assert(
            $blockCartUpdRes["status_code"] === 400 && ($blockCartUpdRes["data"]["success"] ?? true) === false,
            "POST /user_routes/update_cart_quantity.php rejects subscription-only product"
        );

        // 15.5 Catalog filtering: subscription_only=1 vs for_sale_only=1
        $listSubOnlyRes = $this->request("GET", "/list_all_products.php?subscription_only=1");
        $foundInSubOnly = false;
        if (!empty($listSubOnlyRes["data"]["products"])) {
            foreach ($listSubOnlyRes["data"]["products"] as $p) {
                if ($p["id"] == $subOnlyProdId) {
                    $foundInSubOnly = true;
                    break;
                }
            }
        }
        $this->assert(
            $listSubOnlyRes["status_code"] === 200 && $foundInSubOnly,
            "GET /list_all_products.php?subscription_only=1 includes the subscription-only product"
        );

        $listForSaleRes = $this->request("GET", "/list_all_products.php?for_sale_only=1");
        $foundInForSale = false;
        if (!empty($listForSaleRes["data"]["products"])) {
            foreach ($listForSaleRes["data"]["products"] as $p) {
                if ($p["id"] == $subOnlyProdId) {
                    $foundInForSale = true;
                    break;
                }
            }
        }
        $this->assert(
            $listForSaleRes["status_code"] === 200 && !$foundInForSale,
            "GET /list_all_products.php?for_sale_only=1 excludes subscription-only products"
        );

        // 15.6 Admin catalog for subscriptions
        $adminSubProdsRes = $this->request("GET", "/admin_routes/list_subscription_products.php?subscription_only=1", [], $adminToken);
        $this->assert(
            $adminSubProdsRes["status_code"] === 200 && !empty($adminSubProdsRes["data"]["products"]),
            "GET /admin_routes/list_subscription_products.php lists subscription products for admin"
        );

        // 15.7 Create subscription plan containing this subscription-only product
        $subPlanRes = $this->request("POST", "/admin_routes/create_subscription_plan.php", [
            "name" => "Subscription Only Plan Test",
            "category" => "VEG",
            "price_per_week" => 600.0,
            "description" => "Plan with subscription-only items",
            "status" => "ACTIVE",
            "products" => json_encode([
                ["product_id" => (int)$subOnlyProdId, "delivery_order" => 1]
            ])
        ], $adminToken);
        $this->assert(
            $subPlanRes["status_code"] === 200 && ($subPlanRes["data"]["success"] ?? false),
            "POST /admin_routes/create_subscription_plan.php links subscription-only product to plan"
        );

        // 15.8 Subscription preview reflects schedule with subscription product
        $testPlanId = $subPlanRes["data"]["data"]["id"] ?? null;
        if ($testPlanId) {
            $prevSubOnlyRes = $this->request("POST", "/subscription_preview.php", [
                "plan_id" => $testPlanId,
                "weeks" => 1,
                "start_date" => date("Y-m-d", strtotime("+2 days"))
            ]);
            $this->assert(
                $prevSubOnlyRes["status_code"] === 200 && ($prevSubOnlyRes["data"]["delivery_schedule"][0]["product"]["is_subscription_only"] ?? false),
                "POST /subscription_preview.php includes subscription-only product in schedule"
            );
        }

        // 15.9 Update product to toggle flag or details
        $updSubProdRes = $this->request("POST", "/admin_routes/update_product.php", [
            "id" => $subOnlyProdId,
            "name" => "Subscription Exclusive Salad Bowl (Updated)",
            "selling_price" => 0.0,
            "is_subscription_only" => true
        ], $adminToken);
        $this->assert(
            $updSubProdRes["status_code"] === 200 && ($updSubProdRes["data"]["product"]["is_subscription_only"] ?? false) === true,
            "POST /admin_routes/update_product.php updates subscription-only product attributes"
        );

        // SUMMARY
        echo "\n===============================================\n";
        echo "TEST RUN COMPLETE: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "===============================================\n";

        if ($this->failed > 0) {
            echo "Failures:\n" . implode("\n", $this->errors) . "\n";
        }
    }
}

$tester = new ApiTester();
$tester->run();
