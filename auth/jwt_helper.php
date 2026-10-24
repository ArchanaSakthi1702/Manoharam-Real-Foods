<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtHelper
{
    private static string $secretKey =
    "6c4d0b9c9d4b1e8a5f2d7c3a8b1e4f9d6c2a7b5e1d8f3c9a4b7e2d1f6c8a5b3";

    public static function generateToken(array $user): string
    {
        $payload = [
            "iss" => "manoharam real foods",
            "iat" => time(),
            "exp" => time() + (60 * 60 * 24 * 30), // 30 days
            "data" => [
                "id" => $user["id"],
                "name" => $user["name"],
                "phone" => $user["phone"],
                "role" => $user["role"]
            ]
        ];

        return JWT::encode(
            $payload,
            self::$secretKey,
            'HS256'
        );
    }

    public static function verifyToken(string $token)
    {
        try {
            return JWT::decode(
                $token,
                new Key(self::$secretKey, 'HS256')
            );
        } catch (Exception $e) {
            return false;
        }
    }
}