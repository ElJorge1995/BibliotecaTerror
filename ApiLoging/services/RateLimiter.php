<?php

class RateLimiter
{
    public static function enforce(string $scope, string $subject, int $maxAttempts, int $windowSeconds): void
    {
        $subject = trim($subject);
        if ($subject === '') {
            $subject = 'anonymous';
        }

        try {
            $db = Database::connect();
            $keyHash = hash('sha256', $scope . '|' . strtolower($subject));
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
            error_log('RATE_LIMITER_DISABLED message=' . $e->getMessage());
        }
    }
}
