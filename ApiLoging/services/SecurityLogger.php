<?php

class SecurityLogger
{
    public static function log(string $eventType, ?int $userId = null, array $context = []): void
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare(
                'INSERT INTO security_events(event_type, user_id, ip_address, context_json, created_at) VALUES(?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $eventType,
                $userId,
                Security::getClientIp(),
                json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $e) {
            error_log('SECURITY_LOGGER_FALLBACK event=' . $eventType . ' message=' . $e->getMessage());
        }
    }
}
