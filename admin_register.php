<?php

require_once "db.php";

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

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
| Check Existing Phone
|--------------------------------------------------------------------------
*/

if (!empty($phone)) {

    $stmt = $pdo->prepare(
        "SELECT id FROM users WHERE phone = ? LIMIT 1"
    );

    $stmt->execute([$phone]);

    if ($stmt->fetch()) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Phone already exists"
        ]);

        exit;
    }
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
| Create Admin
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT INTO users
    (
        name,
        email,
        phone,
        address,
        password,
        role,
        status
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        ?,
        'ADMIN',
        'active'
    )
");

$stmt->execute([
    $name,
    $email,
    $phone,
    $address,
    $hashedPassword
]);

$adminId = $pdo->lastInsertId();

echo json_encode([
    "success" => true,
    "message" => "Admin created successfully",
    "admin" => [
        "id" => $adminId,
        "name" => $name,
        "email" => $email,
        "role" => "ADMIN"
    ]
]);