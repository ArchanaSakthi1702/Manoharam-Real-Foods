<?php

require_once "jwt_helper.php";

function authenticate()
{
    $headers = getallheaders();
    $authHeader = $headers["Authorization"] ?? $headers["authorization"] ?? null;

    if (!$authHeader) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Token missing"
        ]);
        exit;
    }

    $token = trim(str_replace("Bearer ", "", $authHeader));

    try {
        $decoded = JwtHelper::verifyToken($token);

        if (!$decoded || !isset($decoded->data)) {
            throw new Exception("Invalid token");
        }

        return $decoded->data;

    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Invalid token"
        ]);
        exit;
    }
}
