<?php

require_once __DIR__ . "/cors.php";
require_once "db.php";
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

$secretKey = trim($data["secret_key"] ?? $data["admin_secret_key"] ?? "");
$expectedSecret = $_ENV['ADMIN_SECRET_KEY'] ?? "manoharam_admin_secret_2026";

// Verify secret key for admin creation
if ($secretKey !== $expectedSecret) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized: Valid admin secret_key is required to register an administrator"
    ]);
    exit;
}

$name = trim($data["name"] ?? "");
$email = trim($data["email"] ?? "");
$phone = trim($data["phone"] ?? "");
$address = trim($data["address"] ?? "");
$password = $data["password"] ?? "";

if (
    empty($name) ||
    empty($email) ||
    empty($password)
) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Name, email and password are required"
    ]);

    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid email format"
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Check Existing Email
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT id FROM users WHERE email = ? LIMIT 1"
);

$stmt->execute([$email]);

if ($stmt->fetch()) {
    http_response_code(409);

    echo json_encode([
        "success" => false,
        "message" => "Email already exists"
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Hash Password
|--------------------------------------------------------------------------
*/

$hashedPassword = password_hash(
    $password,
    PASSWORD_BCRYPT
);

/*
|--------------------------------------------------------------------------
| Insert Admin User
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT INTO users (
        name,
        email,
        phone,
        address,
        password,
        role,
        status
    )
    VALUES (
        ?, ?, ?, ?, ?, 'ADMIN', 'active'
    )
");

$stmt->execute([
    $name,
    $email,
    $phone,
    $address,
    $hashedPassword
]);

$userId = $pdo->lastInsertId();

echo json_encode([
    "success" => true,
    "message" => "Admin registered successfully",
    "user" => [
        "id" => $userId,
        "name" => $name,
        "email" => $email,
        "role" => "ADMIN"
    ]
]);
