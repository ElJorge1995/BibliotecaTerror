<?php

class AuthMiddleware
{
    public static function handle(): array
    {
        $token = self::extractBearerToken();

        if ($token === null) {
            Response::json(['error' => 'unauthorized'], 401);
        }

        try {
            $decoded = JwtService::verify($token);
            $db = Database::connect();
            $stmt = $db->prepare('SELECT id FROM revoked_tokens WHERE token = ? LIMIT 1');
            $stmt->execute([$token]);

            if ($stmt->fetch()) {
                SecurityLogger::log('token_revoked_attempt', isset($decoded['sub']) ? (int) $decoded['sub'] : null);
                Response::json(['error' => 'token revoked'], 401);
            }

            return $decoded;
        } catch (Throwable $e) {
            SecurityLogger::log('invalid_token_attempt', null, ['message' => 'invalid token']);
            Response::json(['error' => 'invalid token'], 401);
        }
    }

    public static function extractBearerToken(): ?string
    {
        $headers = self::getHeadersLower();
        $authorization = $headers['authorization'] ?? null;

        if ($authorization === null) {
            return null;
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    private static function getHeadersLower(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            $normalized = [];
            foreach ($headers as $key => $value) {
                $normalized[strtolower($key)] = $value;
            }
            return $normalized;
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
