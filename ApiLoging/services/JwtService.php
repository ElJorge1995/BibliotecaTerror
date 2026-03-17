<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    public static function generate(array $user): string
    {
        $now = time();
        $ttl = (int) (getenv('JWT_TTL_SECONDS') ?: 86400);
        $secret = getenv('JWT_SECRET') ?: 'change-this-secret';
        $issuer = getenv('JWT_ISSUER') ?: 'reglado-auth';

        $payload = [
            'iss' => $issuer,
            'iat' => $now,
            'exp' => $now + $ttl,
            'sub' => (int) $user['id'],
            'email' => $user['email'],
            'username' => $user['username'] ?? null,
            'first_name' => $user['first_name'] ?? null,
            'last_name' => $user['last_name'] ?? null,
            'phone' => $user['phone'] ?? null,
            'name' => $user['name'],
            'role' => $user['role'],
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    public static function verify(string $token): array
    {
        $secret = getenv('JWT_SECRET') ?: 'change-this-secret';
        $decoded = JWT::decode($token, new Key($secret, 'HS256'));

        return (array) $decoded;
    }
}
