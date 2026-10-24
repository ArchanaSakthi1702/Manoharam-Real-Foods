<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

if (file_exists(dirname(__DIR__) . '/.env')) {
    $dotenv = Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
}

class JwtHelper
{
    private static function getSecretKey(): string
    {
        return $_ENV['JWT_SECRET'] ?? "6c4d0b9c9d4b1e8a5f2d7c3a8b1e4f9d6c2a7b5e1d8f3c9a4b7e2d1f6c8a5b3";
    }

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
            self::getSecretKey(),
            'HS256'
        );
    }

    public static function verifyToken(string $token)
    {
        try {
            return JWT::decode(
                $token,
                new Key(self::getSecretKey(), 'HS256')
            );
        } catch (Exception $e) {
            return false;
        }
    }
}
