<?php

require_once "jwt_helper.php";

function authenticate()
{
    $headers = getallheaders();

    if (!isset($headers["Authorization"])) {

        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Token missing"
        ]);

        exit;
    }

    $token = str_replace(
        "Bearer ",
        "",
        $headers["Authorization"]
    );

    try {

        $decoded = JwtHelper::verifyToken($token);

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