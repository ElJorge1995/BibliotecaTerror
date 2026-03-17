<?php

class Security
{
    public static function bootstrapCors(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        $allowedOrigins = self::allowedOrigins();

        if (is_string($origin) && $origin !== '') {
            if (!in_array($origin, $allowedOrigins, true)) {
                Response::json(['error' => 'origin not allowed'], 403);
            }

            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    }

    public static function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
    }

    public static function enforceProductionTransport(): void
    {
        $appEnv = strtolower((string) (getenv('APP_ENV') ?: 'local'));
        if (!in_array($appEnv, ['production', 'prod'], true)) {
            return;
        }

        if (!self::isHttpsRequest()) {
            Response::json(['error' => 'https required'], 400);
        }
    }

    public static function ensureStrongJwtSecret(): void
    {
        $secret = (string) (getenv('JWT_SECRET') ?: '');
        $appEnv = strtolower((string) (getenv('APP_ENV') ?: 'local'));

        if ($secret === '' || strlen($secret) < 32 || $secret === 'change-this-secret') {
            if (in_array($appEnv, ['production', 'prod'], true)) {
                Response::json(['error' => 'jwt secret is not configured securely'], 500);
            }
        }
    }

    public static function getClientIp(): string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $parts = array_map('trim', explode(',', $candidate));
            foreach ($parts as $part) {
                if (filter_var($part, FILTER_VALIDATE_IP)) {
                    return $part;
                }
            }
        }

        return 'unknown';
    }

    public static function isAllowedAbsoluteUrl(string $url, string $envKey): bool
    {
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = isset($parts['port']) ? (string) $parts['port'] : null;

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        $allowed = self::parseCsv(getenv($envKey) ?: '');
        if ($allowed === []) {
            return true;
        }

        $origin = $scheme . '://' . $host . ($port !== null ? ':' . $port : '');
        return in_array($origin, $allowed, true);
    }

    private static function allowedOrigins(): array
    {
        $configured = self::parseCsv(getenv('CORS_ALLOWED_ORIGINS') ?: '');
        if ($configured !== []) {
            return $configured;
        }

        return [
            'http://localhost:5173',
            'http://localhost:5174',
            'http://127.0.0.1:5173',
            'http://127.0.0.1:5174',
        ];
    }

    private static function parseCsv(string $value): array
    {
        $parts = array_map('trim', explode(',', $value));
        $parts = array_values(array_filter($parts, static fn ($item) => $item !== ''));
        return array_values(array_unique($parts));
    }

    private static function isHttpsRequest(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https === 'on' || $https === '1') {
            return true;
        }

        $scheme = strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? ''));
        if ($scheme === 'https') {
            return true;
        }

        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $forwardedProto === 'https';
    }
}
