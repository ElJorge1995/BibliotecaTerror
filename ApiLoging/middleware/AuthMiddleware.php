<?php

/**
 * Middleware de autenticación: extrae el Bearer, verifica la firma del JWT,
 * comprueba que no esté revocado y que sea posterior al último cambio de
 * contraseña del usuario. Si cualquiera de estas comprobaciones falla,
 * responde 401 sin que la petición alcance al controlador.
 */
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
        } catch (Throwable $e) {
            SecurityLogger::log('invalid_token_attempt', null, ['message' => 'invalid token']);
            Response::json(['error' => 'invalid token'], 401);
        }

        try {
            // Lookup O(1) por hash: revoked_tokens.token (TEXT) ya no se consulta.
            $tokenHash = hash('sha256', $token);
            $db = Database::connect();
            $stmt = $db->prepare('SELECT id FROM revoked_tokens WHERE token_hash = ? LIMIT 1');
            $stmt->execute([$tokenHash]);

            if ($stmt->fetch()) {
                SecurityLogger::log('token_revoked_attempt', isset($decoded['sub']) ? (int) $decoded['sub'] : null);
                Response::json(['error' => 'token revoked'], 401);
            }

            // Validación centralizada del estado de seguridad del usuario:
            // 1. Cambio de contraseña -> invalida JWTs anteriores.
            // 2. Ban activo -> rechaza cualquier JWT (independiente del iat).
            // 3. Sessions invalidated -> rechaza JWTs emitidos antes del
            //    timestamp (usado por force-logout y también por el ban).
            $userId = isset($decoded['sub']) ? (int) $decoded['sub'] : 0;
            $iat = isset($decoded['iat']) ? (int) $decoded['iat'] : 0;
            if ($userId > 0 && $iat > 0) {
                $state = User::getSecurityState($userId);

                if ($state['password_changed_at'] !== null && $state['password_changed_at'] > $iat) {
                    SecurityLogger::log('token_invalidated_by_password_change', $userId);
                    Response::json(['error' => 'session expired'], 401);
                }

                if ($state['banned_at'] !== null) {
                    SecurityLogger::log('token_banned_account', $userId);
                    Response::json(['error' => 'account banned'], 401);
                }

                if ($state['sessions_invalidated_at'] !== null && $state['sessions_invalidated_at'] > $iat) {
                    SecurityLogger::log('token_session_invalidated', $userId);
                    Response::json(['error' => 'session expired'], 401);
                }

                // Single-session enforcement: el sid del token debe coincidir
                // con users.current_session_id. Si no coincide (o el usuario
                // no tiene sesión activa) rechazamos sin considerar el iat.
                $tokenSid = isset($decoded['sid']) ? (string) $decoded['sid'] : '';
                if ($state['current_session_id'] === null || $tokenSid === '' || !hash_equals((string) $state['current_session_id'], $tokenSid)) {
                    SecurityLogger::log('token_session_mismatch', $userId);
                    Response::json(['error' => 'session expired'], 401);
                }
            }

            return $decoded;
        } catch (Throwable $e) {
            // Cualquier fallo inesperado (BBDD caída, etc.) durante las
            // comprobaciones post-firma: fail-closed para no servir contenido
            // protegido bajo condiciones desconocidas.
            error_log('AUTH_MIDDLEWARE_ERROR message=' . $e->getMessage());
            Response::json(['error' => 'unauthorized'], 401);
        }

        return [];
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
