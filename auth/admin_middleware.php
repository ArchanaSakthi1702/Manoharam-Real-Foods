<?php

require_once "auth_middleware.php";

function requireAdmin()
{
    $user = authenticate();

    if ($user->role !== "ADMIN") {

        http_response_code(403);

        echo json_encode([
            "success" => false,
            "message" => "Admin access required"
        ]);

        exit;
    }

    return $user;
}