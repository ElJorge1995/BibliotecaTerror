<?php

/**
 * Controla la frecuencia de peticiones sensibles y el bloqueo temporal de cuentas
 * tras intentos fallidos consecutivos. Usa la tabla `rate_limits` con clave
 * compuesta (scope_name + hash SHA-256 del sujeto) y ventana deslizante.
 *
 * Política de fallos: si la BBDD no responde, la clase falla en "cerrado"
 * (devuelve 503) para evitar que un fallo de infraestructura deje el login
 * sin protección contra fuerza bruta. Solo `resetFailure` es tolerante a fallos
 * (un reseteo perdido nunca reduce la seguridad).
 */
class RateLimiter
{
    /**
     * Throttling clásico: cuenta cada intento (éxito o fallo) y bloquea al
     * superar `maxAttempts` dentro de `windowSeconds`. Úsalo para limitar la
     * tasa de llamadas a endpoints (login, register, reset, etc.).
     */
    public static function enforce(string $scope, string $subject, int $maxAttempts, int $windowSeconds): void
    {
        $subject = self::normalizeSubject($subject);

        try {
            $db = Database::connect();
            $keyHash = self::buildKeyHash($scope, $subject);
            $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);

            $db->prepare('DELETE FROM rate_limits WHERE updated_at < ?')->execute([$windowStart]);

            $stmt = $db->prepare('SELECT id, attempts, updated_at FROM rate_limits WHERE key_hash = ? AND scope_name = ? LIMIT 1');
            $stmt->execute([$keyHash, $scope]);
            $row = $stmt->fetch();

            if (!$row) {
                $insert = $db->prepare(
                    'INSERT INTO rate_limits(key_hash, scope_name, attempts, updated_at, created_at) VALUES(?, ?, 1, NOW(), NOW())'
                );
                $insert->execute([$keyHash, $scope]);
                return;
            }

            $updatedAt = strtotime((string) $row['updated_at']) ?: 0;
            if ($updatedAt < time() - $windowSeconds) {
                $reset = $db->prepare('UPDATE rate_limits SET attempts = 1, updated_at = NOW() WHERE id = ?');
                $reset->execute([(int) $row['id']]);
                return;
            }

            $attempts = (int) $row['attempts'];
            if ($attempts >= $maxAttempts) {
                Response::json(['error' => 'too many requests, try again later'], 429);
            }

            $update = $db->prepare('UPDATE rate_limits SET attempts = attempts + 1, updated_at = NOW() WHERE id = ?');
            $update->execute([(int) $row['id']]);
        } catch (Throwable $e) {
            self::failClosed('enforce', $scope, $e);
        }
    }

    /**
     * Verifica si el sujeto está actualmente bloqueado por acumular demasiados
     * fallos dentro de la ventana. No incrementa el contador: se llama al inicio
     * de la operación sensible (por ejemplo, antes de validar contraseña).
     */
    public static function checkFailureLockout(string $scope, string $subject, int $maxFailures, int $windowSeconds): void
    {
        $subject = self::normalizeSubject($subject);

        try {
            $db = Database::connect();
            $keyHash = self::buildKeyHash($scope, $subject);

            $stmt = $db->prepare('SELECT attempts, updated_at FROM rate_limits WHERE key_hash = ? AND scope_name = ? LIMIT 1');
            $stmt->execute([$keyHash, $scope]);
            $row = $stmt->fetch();

            if (!$row) {
                return;
            }

            $updatedAt = strtotime((string) $row['updated_at']) ?: 0;
            if ($updatedAt < time() - $windowSeconds) {
                // La ventana ha expirado: el lockout ya no aplica, el siguiente fallo
                // empezará un nuevo ciclo via recordFailure().
                return;
            }

            if ((int) $row['attempts'] >= $maxFailures) {
                Response::json(['error' => 'account temporarily locked, try again later'], 429);
            }
        } catch (Throwable $e) {
            self::failClosed('checkFailureLockout', $scope, $e);
        }
    }

    /**
     * Registra un fallo para el sujeto. Si no hay registro previo o la ventana
     * expiró, arranca el contador en 1. Se llama solo cuando la operación
     * sensible falla (credenciales inválidas, token malo, etc.).
     */
    public static function recordFailure(string $scope, string $subject, int $windowSeconds): void
    {
        $subject = self::normalizeSubject($subject);

        try {
            $db = Database::connect();
            $keyHash = self::buildKeyHash($scope, $subject);

            $stmt = $db->prepare('SELECT id, updated_at FROM rate_limits WHERE key_hash = ? AND scope_name = ? LIMIT 1');
            $stmt->execute([$keyHash, $scope]);
            $row = $stmt->fetch();

            if (!$row) {
                $insert = $db->prepare(
                    'INSERT INTO rate_limits(key_hash, scope_name, attempts, updated_at, created_at) VALUES(?, ?, 1, NOW(), NOW())'
                );
                $insert->execute([$keyHash, $scope]);
                return;
            }

            $updatedAt = strtotime((string) $row['updated_at']) ?: 0;
            if ($updatedAt < time() - $windowSeconds) {
                $reset = $db->prepare('UPDATE rate_limits SET attempts = 1, updated_at = NOW() WHERE id = ?');
                $reset->execute([(int) $row['id']]);
                return;
            }

            $update = $db->prepare('UPDATE rate_limits SET attempts = attempts + 1, updated_at = NOW() WHERE id = ?');
            $update->execute([(int) $row['id']]);
        } catch (Throwable $e) {
            self::failClosed('recordFailure', $scope, $e);
        }
    }

    /**
     * Borra el contador de fallos tras una operación exitosa (p. ej., login OK
     * desbloquea la cuenta). Si la BBDD falla aquí, solo hacemos log: un reset
     * perdido implica que el siguiente fallo seguirá sumando sobre el contador
     * previo, lo cual es más seguro, no menos.
     */
    public static function resetFailure(string $scope, string $subject): void
    {
        $subject = self::normalizeSubject($subject);

        try {
            $db = Database::connect();
            $keyHash = self::buildKeyHash($scope, $subject);
            $stmt = $db->prepare('DELETE FROM rate_limits WHERE key_hash = ? AND scope_name = ?');
            $stmt->execute([$keyHash, $scope]);
        } catch (Throwable $e) {
            error_log('RATE_LIMITER_RESET_FAILED scope=' . $scope . ' message=' . $e->getMessage());
        }
    }

    private static function normalizeSubject(string $subject): string
    {
        $subject = trim($subject);
        return $subject === '' ? 'anonymous' : $subject;
    }

    private static function buildKeyHash(string $scope, string $subject): string
    {
        return hash('sha256', $scope . '|' . strtolower($subject));
    }

    /**
     * Respuesta fail-closed: cuando la BBDD del rate limiter no responde,
     * devolvemos 503 en lugar de dejar pasar la petición. Así un atacante no
     * puede desactivar la protección tirando la tabla de rate limits.
     */
    private static function failClosed(string $method, string $scope, Throwable $e): void
    {
        error_log('RATE_LIMITER_FAIL_CLOSED method=' . $method . ' scope=' . $scope . ' message=' . $e->getMessage());
        Response::json(['error' => 'service temporarily unavailable, try again later'], 503);
    }
}
