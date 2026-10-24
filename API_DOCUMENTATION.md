# Manoharam Real Foods — Frontend API Documentation

## 1. General Information

- **Base URL**: `http://localhost/Manoharam-Real-Foods-main` (or your domain/server URL)
- **Content-Type**: `application/json` (except image uploads which use `multipart/form-data`)
- **Authentication**: Bearer Token in `Authorization` header:
  ```http
  Authorization: Bearer <jwt_token>
  ```

### Standard Response Format

#### Success
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {}
}
```

#### Error
```json
{
  "success": false,
  "message": "Error description here"
}
```

---

## 2. Authentication & Account APIs

### 2.1 Customer Registration
- **Endpoint**: `POST /register.php`
- **Auth**: None
- **Body**:
```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "phone": "9876543210",
  "address": "123 Anna Nagar, Chennai",
  "password": "securepassword123"
}
```
- **Response**: `200 OK`
```json
{
  "success": true,
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "role": "CUSTOMER"
  }
}
```

### 2.2 Customer & Admin Login
- **Endpoint**: `POST /login.php`
- **Auth**: None
- **Body**:
```json
{
  "email": "jane@example.com",
  "password": "securepassword123"
}
```
- **Response**: `200 OK`
```json
{
  "success": true,
  "message": "Login successful",
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": {
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "role": "CUSTOMER"
  }
}
```

### 2.3 Forgot Password (Request OTP/Token)
- **Endpoint**: `POST /forgot_password.php`
- **Auth**: None
- **Body**:
```json
{
  "email": "jane@example.com"
}
```
- **Response**: `200 OK`
```json
{
  "success": true,
  "message": "Password reset token sent to your email"
}
```

### 2.4 Reset Password
- **Endpoint**: `POST /reset_password.php`
- **Auth**: None
- **Body**:
```json
{
  "email": "jane@example.com",
  "token": "482910",
  "new_password": "newsecurepassword123"
}
```
- **Response**: `200 OK`
```json
{
  "success": true,
  "message": "Password reset successfully. You can now log in with your new password."
}
```

### 2.5 Get User Profile
- **Endpoint**: `GET /user_routes/get_profile.php`
- **Auth**: Bearer Token
- **Response**: `200 OK`
```json
{
  "success": true,
  "profile": {
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "9876543210",
    "address": "123 Anna Nagar, Chennai",
    "role": "CUSTOMER",
    "status": "active",
    "created_at": "2026-07-28 12:00:16"
  }
}
```

### 2.6 Update User Profile
- **Endpoint**: `POST /user_routes/update_profile.php`
- **Auth**: Bearer Token
- **Body**:
```json
{
  "name": "Jane Doe",
  "phone": "9876543211",
  "address": "456 KK Nagar, Madurai"
}
```
- **Response**: `200 OK`

### 2.7 Change Password
- **Endpoint**: `POST /user_routes/change_password.php`
- **Auth**: Bearer Token
- **Body**:
```json
{
  "current_password": "oldpassword123",
  "new_password": "newpassword123"
}
```
- **Response**: `200 OK`

---

## 3. Product Catalog APIs

### 3.1 List Categories
- **Endpoint**: `GET /list_categories.php`
- **Auth**: None
- **Response**: `200 OK`
```json
{
  "success": true,
  "total_categories": 3,
  "categories": [
    { "category": "Pickles", "product_count": 7 },
    { "category": "Powders", "product_count": 4 },
    { "category": "Snacks", "product_count": 5 }
  ]
}
```

### 3.2 List All Products (Cursor Pagination)
- **Endpoint**: `GET /list_all_products.php?limit=10&cursor=8`
- **Auth**: None
- **Query Parameters**:
  - `limit` (default: 10, max: 100)
  - `cursor` (optional product id for pagination)
- **Response**: `200 OK`
```json
{
  "success": true,
  "has_more": true,
  "next_cursor": 5,
  "products": [
    {
      "id": 7,
      "name": "Amla Pickle",
      "category": "Pickles",
      "quantity": 500.0,
      "unit": "g",
      "selling_price": 230.0,
      "description": "Nutritious Indian gooseberry pickle...",
      "image_url": "https://example.com/uploads/foods_profiles/amla.jpg",
      "status": "ACTIVE",
      "total_weight_g": 1000.0,
      "calories": 180.0,
      "protein": 2.5,
      "carbs": 14.0,
      "fat": 12.0,
      "fiber": 4.0
    }
  ]
}
```

### 3.3 Product Details
- **Endpoint**: `GET /product_details.php?id=1`
- **Auth**: None
- **Response**: `200 OK` (Includes full nutritional details and ingredients list)

### 3.4 Search & Filter Products
- **Endpoint**: `GET /search_products.php?search=garlic&category=Pickles&min_price=100&max_price=300&limit=10`
- **Auth**: None
- **Query Parameters**:
  - `search`: Keyword for name and description
  - `category`: Category name
  - `min_price`: Minimum selling price
  - `max_price`: Maximum selling price

---

## 4. Cart Management APIs

### 4.1 Add to Cart (Increments quantity)
- **Endpoint**: `POST /user_routes/add_to_cart.php`
- **Auth**: Bearer Token
- **Body**:
```json
{
  "product_id": 1,
  "quantity": 1
}
```

### 4.2 Update Cart Quantity (Set exact quantity)
- **Endpoint**: `POST /user_routes/update_cart_quantity.php`
- **Auth**: Bearer Token
- **Note**: Passing `quantity: 0` removes the item from the cart.
- **Body**:
```json
{
  "product_id": 1,
  "quantity": 3
}
```
- **Response**: `200 OK`
```json
{
  "success": true,
  "message": "Cart updated successfully",
  "cart_id": 1,
  "total_amount": "660.00",
  "total_items": 1,
  "cart_items": [
    {
      "cart_item_id": 2,
      "product_id": 1,
      "name": "Garlic Pickle",
      "selling_price": "220.00",
      "quantity": "3.00",
      "item_total": "660.00"
    }
  ]
}
```

### 4.3 Get Cart
- **Endpoint**: `GET /user_routes/get_cart.php`
- **Auth**: Bearer Token
- **Response**: `200 OK`
```json
{
  "success": true,
  "cart_id": 1,
  "total_amount": "440.00",
  "cart_items": [
    {
      "cart_item_id": 1,
      "product_id": 1,
      "name": "Garlic Pickle",
      "category": "Pickles",
      "unit": "g",
      "selling_price": "220.00",
      "quantity": "2.00",
      "item_total": "440.00"
    }
  ]
}
```

### 4.4 Remove Item from Cart
- **Endpoint**: `POST /user_routes/remove_from_cart.php`
- **Auth**: Bearer Token
- **Body**:
```json
{
  "product_id": 1
}
```

### 4.5 Clear Cart
- **Endpoint**: `POST /user_routes/clear_cart.php`
- **Auth**: Bearer Token

---

## 5. Checkout & Payments (Razorpay & COD)

### 5.1 Checkout (Place Order)
- **Endpoint**: `POST /user_routes/checkout.php`
- **Auth**: Bearer Token
- **Body**:
```json
{
  "payment_method": "ONLINE",
  "delivery_address": "Flat 302, Green Meadows, Sivakasi"
}
```
*(Use `"payment_method": "COD"` for Cash on Delivery)*

- **Response (for ONLINE payment)**:
```json
{
  "success": true,
  "message": "Order placed successfully",
  "order_id": 14,
  "payment_method": "ONLINE",
  "status": "PENDING",
  "total_amount": "440.00",
  "delivery_address": "Flat 302, Green Meadows, Sivakasi",
  "razorpay": {
    "razorpay_order_id": "order_PXxxx123",
    "amount": 44000,
    "amount_in_rupees": 440.0,
    "currency": "INR",
    "key": "rzp_test_THLM79p8SJRLtA",
    "status": "created"
  }
}
```

### 5.2 Create Razorpay Order (For Existing Pending Order)
- **Endpoint**: `POST /user_routes/create_razorpay_order.php`
- **Auth**: Bearer Token
- **Body**:
```json
{
  "order_id": 14
}
```
*(Or pass `"subscription_id": 5` for subscription payment)*

### 5.3 Verify Razorpay Payment
- **Endpoint**: `POST /user_routes/verify_payment.php`
- **Auth**: Bearer Token
- **Body** (From Razorpay Checkout callback):
```json
{
  "order_id": 14,
  "razorpay_order_id": "order_PXxxx123",
  "razorpay_payment_id": "pay_PYyyy456",
  "razorpay_signature": "9d8e7f..."
}
```
- **Response**: `200 OK`
```json
{
  "success": true,
  "message": "Payment verified successfully. Order confirmed!",
  "order_id": 14,
  "status": "CONFIRMED",
  "payment_status": "PAID",
  "razorpay_payment_id": "pay_PYyyy456"
}
```

### 5.4 Get My Orders
- **Endpoint**: `GET /user_routes/get_my_orders.php`
- **Auth**: Bearer Token

### 5.5 Get Order Details
- **Endpoint**: `GET /user_routes/get_order_details.php?id=14`
- **Auth**: Bearer Token

### 5.6 Cancel Order
- **Endpoint**: `POST /user_routes/cancel_order.php`
- **Auth**: Bearer Token
- **Note**: Only `PENDING` orders can be cancelled. Deducted stock is automatically restored to batches.
- **Body**:
```json
{
  "order_id": 14,
  "reason": "Ordered wrong flavor by mistake"
}
```

---

## 6. Subscriptions APIs

### 6.1 List All Subscription Plans
- **Endpoint**: `GET /list_all_subscription_plans.php`
- **Auth**: None

### 6.2 Get Plan Details
- **Endpoint**: `GET /get_subscription_details.php?plan_id=1`
- **Auth**: None

### 6.3 List Subscribable Plans for Customer
- **Endpoint**: `GET /list_subscribable_salads.php`
- **Auth**: Bearer Token
- Returns plans the logged-in customer is not currently subscribed to.

### 6.4 Subscription Price Preview & Schedule Calculation
- **Endpoint**: `POST /subscription_preview.php`
- **Auth**: None
- **Body**:
```json
{
  "plan_id": 1,
  "weeks": 4,
  "start_date": "2026-11-01",
  "exclude_saturday": false,
  "exclude_sunday": true
}
```
- **Response**: Returns calculated end date, total price, and product delivery schedule.

### 6.5 Subscribe to Plan
- **Endpoint**: `POST /user_routes/subscribe_plan.php`
- **Auth**: Bearer Token
- **Body**:
```json
{
  "plan_id": 1,
  "weeks": 4,
  "start_date": "2026-11-01",
  "exclude_saturday": false,
  "exclude_sunday": true,
  "skip_dates": ["2026-11-12", "2026-11-19"]
}
```

### 6.6 List My Subscriptions
- **Endpoint**: `GET /user_routes/list_my_subscriptions.php`
- **Auth**: Bearer Token

### 6.7 Get My Subscription Details
- **Endpoint**: `GET /user_routes/get_my_subscription_details.php?subscription_id=5`
- **Auth**: Bearer Token

### 6.8 Skip a Delivery Date
- **Endpoint**: `POST /user_routes/skip_subscription.php`
- **Auth**: Bearer Token
- **Body**:
```json
{
  "subscription_id": 5,
  "skip_date": "2026-11-15",
  "reason": "Going out of station"
}
```

### 6.9 Unskip a Delivery Date (Resume skipped day)
- **Endpoint**: `POST /user_routes/unskip_subscription.php`
- **Auth**: Bearer Token
- **Body**:
```json
{
  "subscription_id": 5,
  "skip_date": "2026-11-15"
}
```

### 6.10 Pause Subscription
- **Endpoint**: `POST /user_routes/pause_subscription.php`
- **Auth**: Bearer Token
- **Body**:
```json
{
  "subscription_id": 5,
  "reason": "Vacation pause"
}
```

### 6.11 Resume Subscription
- **Endpoint**: `POST /user_routes/resume_subscription.php`
- **Auth**: Bearer Token
- **Body**:
```json
{
  "subscription_id": 5
}
```

### 6.12 Cancel Subscription
- **Endpoint**: `POST /user_routes/cancel_subscription.php`
- **Auth**: Bearer Token
- **Body**:
```json
{
  "subscription_id": 5,
  "reason": "Moving to another city"
}
```

---

## 7. Admin APIs

> [!NOTE]
> All `/admin_routes/*` endpoints require `Authorization: Bearer <token>` where the user's `role` is `ADMIN`.

### 7.1 Register Admin
- **Endpoint**: `POST /admin_register.php`
- **Auth**: None (Protected with `secret_key`)
- **Body**:
```json
{
  "name": "Super Admin",
  "email": "admin@manoharam.com",
  "phone": "9025988060",
  "password": "adminPassword123",
  "secret_key": "manoharam_admin_secret_2026"
}
```

### 7.2 Create Product
- **Endpoint**: `POST /admin_routes/create_product.php`
- **Content-Type**: `multipart/form-data`
- **Form Fields**: `name`, `category`, `quantity`, `unit` (`g`/`kg`), `selling_price`, `description`, `status` (`ACTIVE`/`INACTIVE`), and file `image`.

### 7.3 Update Product
- **Endpoint**: `POST /admin_routes/update_product.php`
- **Content-Type**: `multipart/form-data` or `application/json`
- **Body**:
```json
{
  "id": 1,
  "name": "Garlic Pickle (Special Edition)",
  "selling_price": 240.0,
  "status": "ACTIVE"
}
```

### 7.4 Delete / Deactivate Product
- **Endpoint**: `POST /admin_routes/delete_product.php`
- **Body**:
```json
{
  "product_id": 1,
  "force": false
}
```
*(By default, sets `status = 'INACTIVE'`).*

### 7.5 Ingredients & Recipes
- **Create Ingredient**: `POST /admin_routes/create_ingredient.php`
- **Update Ingredient**: `POST /admin_routes/update_ingredient.php`
- **Delete Ingredient**: `POST /admin_routes/delete_ingredient.php`
- **Create Recipe**: `POST /admin_routes/create_recipe_for_product.php`
- **Get Recipe Details**: `GET /admin_routes/get_recipe_details.php?recipe_id=2`
- **Update Recipe**: `POST /admin_routes/update_recipe.php`
- **Delete Recipe**: `POST /admin_routes/delete_recipe.php`
- **Calculate Scaled Recipe**: `POST /admin_routes/calculate_recipe.php`

### 7.6 Kitchen Daily Dispatch Sheet
- **Endpoint**: `GET /admin_routes/get_daily_dispatch.php?date=2026-11-02`
- **Query Parameters**: `date` (format: `YYYY-MM-DD`, defaults to today).
- **Response**:
```json
{
  "success": true,
  "dispatch_date": "2026-11-02",
  "day_of_week": "Monday",
  "total_deliveries": 20,
  "kitchen_production_summary": [
    {
      "product_id": 1,
      "product_name": "Garlic Pickle",
      "unit": "g",
      "portion_size": 500,
      "total_packs": 12
    },
    {
      "product_id": 2,
      "product_name": "Mango Pickle",
      "unit": "g",
      "portion_size": 500,
      "total_packs": 8
    }
  ],
  "deliveries": [
    {
      "subscription_id": 5,
      "customer_name": "Jane Doe",
      "customer_phone": "9876543210",
      "delivery_address": "123 Anna Nagar, Chennai",
      "plan_name": "Weekly Pickle Combo",
      "scheduled_product": {
        "product_id": 1,
        "name": "Garlic Pickle",
        "quantity": 500.0,
        "unit": "g"
      }
    }
  ]
}
```

### 7.7 Admin Order Status & Restocking
- **Endpoint**: `POST /admin_routes/update_order_status.php`
- **Body**:
```json
{
  "order_id": 14,
  "status": "CONFIRMED"
}
```
*(Allowed statuses: `CONFIRMED`, `OUT_FOR_DELIVERY`, `DELIVERED`, `CANCELLED`. Setting to `CANCELLED` restores batch inventory).*

### 7.8 Admin Subscription Approval & Rejection
- **Approve**: `POST /admin_routes/approve_subscription.php`
  - Body: `{ "subscription_id": 5 }`
- **Reject**: `POST /admin_routes/reject_subscription.php`
  - Body: `{ "subscription_id": 5, "reason": "Delivery address out of coverage area" }`

---

## 8. Razorpay Frontend Integration Workflow

```javascript
// 1. Customer places order
const res = await fetch('/user_routes/checkout.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    payment_method: 'ONLINE',
    delivery_address: 'Customer Address'
  })
});
const data = await res.json();

// 2. Open Razorpay Checkout modal with the returned payload
const options = {
  key: data.razorpay.key,
  amount: data.razorpay.amount,
  currency: data.razorpay.currency,
  name: 'Manoharam Real Foods',
  description: `Order #${data.order_id}`,
  order_id: data.razorpay.razorpay_order_id,
  handler: async function (response) {
    // 3. Verify payment on backend
    const verifyRes = await fetch('/user_routes/verify_payment.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      body: JSON.stringify({
        order_id: data.order_id,
        razorpay_order_id: response.razorpay_order_id,
        razorpay_payment_id: response.razorpay_payment_id,
        razorpay_signature: response.razorpay_signature
      })
    });
    const result = await verifyRes.json();
    if (result.success) {
      alert('Order Confirmed!');
    }
  }
};
const rzp = new window.Razorpay(options);
rzp.open();
```
