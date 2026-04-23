<?php

/**
 * Traduce una IP a (country_code, country_name) usando el archivo local
 * GeoLite2-Country.mmdb de MaxMind. Sin red, sin terceros.
 *
 * Degradación grácil:
 *   - Si el .mmdb no existe o es ilegible, devuelve null (se loguea una vez).
 *   - Si la IP es privada/localhost/reservada/inválida, devuelve null.
 *
 * El caller debe tratar null como "país desconocido" y no disparar alerta.
 */
class GeoLocationService
{
    /** @var \GeoIp2\Database\Reader|null */
    private static $reader = null;
    private static bool $initialized = false;

    /**
     * @return array{country_code: ?string, country_name: ?string}|null
     */
    public static function lookup(string $ip): ?array
    {
        if (!self::ensureReader()) {
            return null;
        }

        try {
            $record = self::$reader->country($ip);
            return [
                'country_code' => $record->country->isoCode,
                'country_name' => $record->country->name,
            ];
        } catch (Throwable $e) {
            // IPs privadas/no geolocalizables caen aquí. Es normal en dev.
            return null;
        }
    }

    private static function ensureReader(): bool
    {
        if (self::$initialized) {
            return self::$reader !== null;
        }
        self::$initialized = true;

        $path = __DIR__ . '/../data/GeoLite2-Country.mmdb';
        if (!is_readable($path)) {
            error_log('GEOIP_DB_MISSING path=' . $path);
            return false;
        }

        try {
            self::$reader = new \GeoIp2\Database\Reader($path);
            return true;
        } catch (Throwable $e) {
            error_log('GEOIP_DB_OPEN_FAIL message=' . $e->getMessage());
            self::$reader = null;
            return false;
        }
    }
}
