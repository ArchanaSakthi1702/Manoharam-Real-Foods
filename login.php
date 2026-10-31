<?php
require_once __DIR__ . "/cors.php";
require_once "db.php";
require_once "auth/jwt_helper.php";

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    $data = $_POST;
}

$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if (
    empty($email) ||
    empty($password)
) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Email and password are required"
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Find User
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        email,
        phone,
        address,
        password,
        role,
        status
    FROM users
    WHERE email = ?
    LIMIT 1
");

$stmt->execute([$email]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Invalid email or password"
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Check Status
|--------------------------------------------------------------------------
*/

if ($user["status"] !== "active") {

    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Account is inactive"
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Verify Password
|--------------------------------------------------------------------------
*/

if (!password_verify($password, $user["password"])) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Invalid email or password"
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Generate JWT
|--------------------------------------------------------------------------
*/

$token = JwtHelper::generateToken([
    "id" => $user["id"],
    "name" => $user["name"],
    "email" => $user["email"],
    "phone" => $user["phone"],
    "role" => $user["role"]
]);

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "message" => "Login successful",
    "token" => $token,
    "user" => [
        "id" => $user["id"],
        "name" => $user["name"],
        "email" => $user["email"],
        "phone" => $user["phone"],
        "address" => $user["address"],
        "role" => $user["role"]
    ]
]);