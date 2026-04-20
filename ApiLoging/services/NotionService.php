<?php

class NotionService
{
    private const API_BASE = 'https://api.notion.com/v1';
    private const API_VERSION = '2022-06-28';

    // --- Entidades ---

    public static function syncUserCreated(array $user): bool
    {
        return self::syncEntity('NOTION_DATABASE_ID', $user, [self::class, 'buildUserProperties']);
    }

    public static function syncUserUpdated(array $user): bool
    {
        return self::syncEntityUpdated('NOTION_DATABASE_ID', ['id', 'user id', 'usuario id'], (int)($user['id'] ?? 0), $user, [self::class, 'buildUserProperties']);
    }

    public static function syncBookCreated(array $book): bool
    {
        return self::syncEntity('NOTION_BOOKS_DATABASE_ID', $book, [self::class, 'buildBookProperties']);
    }

    public static function syncBookUpdated(array $book): bool
    {
        return self::syncEntityUpdated('NOTION_BOOKS_DATABASE_ID', ['id local', 'libro id'], (int)($book['id'] ?? 0), $book, [self::class, 'buildBookProperties']);
    }

    public static function syncLoanCreated(array $loan): bool
    {
        return self::syncEntity('NOTION_LOANS_DATABASE_ID', $loan, [self::class, 'buildLoanProperties']);
    }

    public static function syncLoanUpdated(array $loan): bool
    {
        return self::syncEntityUpdated('NOTION_LOANS_DATABASE_ID', ['id prestamo', 'loan id'], (int)($loan['id'] ?? $loan['prestamo_id'] ?? 0), $loan, [self::class, 'buildLoanProperties']);
    }

    // --- Core Sync Logic ---

    private static function syncEntity(string $dbEnvVar, array $data, callable $propertyBuilder): bool
    {
        if (!self::isEnabled($dbEnvVar)) return false;

        $databaseId = trim((string) getenv($dbEnvVar));
        $schema = self::getDatabaseSchema($databaseId);
        if ($schema === null) return false;

        $properties = call_user_func($propertyBuilder, $schema, $data);
        if ($properties === []) return false;

        $payload = [
            'parent' => ['database_id' => $databaseId],
            'properties' => $properties,
        ];

        return self::request('POST', '/pages', $payload) !== null;
    }

    private static function syncEntityUpdated(string $dbEnvVar, array $idNames, int $idValue, array $data, callable $propertyBuilder): bool
    {
        if (!self::isEnabled($dbEnvVar)) return false;

        $databaseId = trim((string) getenv($dbEnvVar));
        $pageId = self::findPageIdById($databaseId, $idNames, $idValue);
        
        if ($pageId === null) {
            return self::syncEntity($dbEnvVar, $data, $propertyBuilder);
        }

        $schema = self::getDatabaseSchema($databaseId);
        if ($schema === null) return false;

        $properties = call_user_func($propertyBuilder, $schema, $data);
        $payload = ['properties' => $properties];

        return self::request('PATCH', '/pages/' . $pageId, $payload) !== null;
    }

    private static function findPageIdById(string $databaseId, array $candidates, int $idValue): ?string
    {
        if ($idValue <= 0) return null;

        $schema = self::getDatabaseSchema($databaseId);
        if ($schema === null) return null;

        $idPropertyName = self::findPropertyByNames($schema, $candidates);
        if ($idPropertyName === null || ($schema[$idPropertyName]['type'] ?? '') !== 'number') return null;

        $payload = [
            'filter' => ['property' => $idPropertyName, 'number' => ['equals' => $idValue]],
            'page_size' => 1
        ];

        $response = self::request('POST', '/databases/' . $databaseId . '/query', $payload);
        return $response['results'][0]['id'] ?? null;
    }

    // --- Property Builders ---

    private static function buildUserProperties(array $schema, array $user): array
    {
        $result = [];
        $titlePropertyName = self::findPropertyByType($schema, 'title');

        if ($titlePropertyName !== null) {
            $titleValue = (string) ($user['name'] ?? $user['username'] ?? $user['email'] ?? ('Usuario #' . (int) ($user['id'] ?? 0)));
            $result[$titlePropertyName] = ['title' => [['text' => ['content' => $titleValue]]]];
        }

        self::mapNumber($result, $schema, ['id', 'user id', 'usuario id'], (int) ($user['id'] ?? 0));
        self::mapEmail($result, $schema, ['email', 'correo'], (string) ($user['email'] ?? ''));
        self::mapRichText($result, $schema, ['username', 'usuario'], (string) ($user['username'] ?? ''));
        self::mapPhone($result, $schema, ['telefono', 'phone'], (string) ($user['phone'] ?? ''));
        self::mapSelectOrText($result, $schema, ['rol', 'role'], (string) ($user['role'] ?? 'user'));
        self::mapCheckbox($result, $schema, ['verificado'], ((int) ($user['is_email_verified'] ?? 0)) === 1);
        self::mapDate($result, $schema, ['creado en', 'fecha alta'], (string) ($user['created_at'] ?? ''));

        return $result;
    }

    private static function buildBookProperties(array $schema, array $book): array
    {
        $result = [];
        $titlePropertyName = self::findPropertyByType($schema, 'title');

        if ($titlePropertyName !== null) {
            $result[$titlePropertyName] = ['title' => [['text' => ['content' => (string)($book['titulo'] ?? 'Sin Titulo')]]]];
        }

        self::mapRichText($result, $schema, ['autor'], (string)($book['autor'] ?? ''));
        self::mapSelectOrText($result, $schema, ['categoria'], (string)($book['categoria'] ?? ''));
        self::mapNumber($result, $schema, ['stock'], (int)($book['stock'] ?? 0));
        self::mapRichText($result, $schema, ['google id', 'isbn'], (string)($book['google_id'] ?? ''));
        self::mapUrl($result, $schema, ['portada'], (string)($book['portada'] ?? ''));
        self::mapNumber($result, $schema, ['rating'], (float)($book['rating'] ?? 0));
        self::mapNumber($result, $schema, ['id local'], (int)($book['id'] ?? 0));

        return $result;
    }

    private static function buildLoanProperties(array $schema, array $loan): array
    {
        $result = [];
        $titlePropertyName = self::findPropertyByType($schema, 'title');

        if ($titlePropertyName !== null) {
            $result[$titlePropertyName] = ['title' => [['text' => ['content' => (string)($loan['titulo'] ?? 'Libro Desconocido')]]]];
        }

        self::mapRichText($result, $schema, ['usuario'], (string)($loan['nombre_usuario'] ?? ''));
        self::mapSelectOrText($result, $schema, ['estado'], (string)($loan['estado'] ?? 'pendiente'));
        self::mapDate($result, $schema, ['fecha prestamo'], (string)($loan['fecha_prestamo'] ?? ''));
        self::mapDate($result, $schema, ['fecha devolucion'], (string)($loan['fecha_devolucion'] ?? ''));
        self::mapDate($result, $schema, ['fecha entregado'], (string)($loan['fecha_entregado'] ?? ''));
        self::mapNumber($result, $schema, ['valoracion'], (int)($loan['rating'] ?? 0));
        self::mapNumber($result, $schema, ['id prestamo'], (int)($loan['id'] ?? $loan['prestamo_id'] ?? 0));
        self::mapRichText($result, $schema, ['dni usuario'], (string)($loan['dni'] ?? ''));

        return $result;
    }

    // --- Helpers Mapeo ---

    private static function mapNumber(array &$result, array $schema, array $candidates, $value): void
    {
        $name = self::findPropertyByNames($schema, $candidates);
        if ($name && ($schema[$name]['type'] ?? '') === 'number') {
            $result[$name] = ['number' => (float)$value];
        }
    }

    private static function mapEmail(array &$result, array $schema, array $candidates, string $value): void
    {
        if ($value === '') return;
        $name = self::findPropertyByNames($schema, $candidates);
        if ($name && ($schema[$name]['type'] ?? '') === 'email') {
            $result[$name] = ['email' => $value];
        }
    }

    private static function mapRichText(array &$result, array $schema, array $candidates, string $value): void
    {
        if ($value === '') return;
        $name = self::findPropertyByNames($schema, $candidates);
        if ($name && ($schema[$name]['type'] ?? '') === 'rich_text') {
            $result[$name] = ['rich_text' => [['text' => ['content' => $value]]]];
        }
    }

    private static function mapUrl(array &$result, array $schema, array $candidates, string $value): void
    {
        if ($value === '') return;
        $name = self::findPropertyByNames($schema, $candidates);
        if ($name && ($schema[$name]['type'] ?? '') === 'url') {
            $result[$name] = ['url' => $value];
        }
    }

    private static function mapPhone(array &$result, array $schema, array $candidates, string $value): void
    {
        if ($value === '') return;
        $name = self::findPropertyByNames($schema, $candidates);
        if ($name && ($schema[$name]['type'] ?? '') === 'phone_number') {
            $result[$name] = ['phone_number' => $value];
        }
    }

    private static function mapSelectOrText(array &$result, array $schema, array $candidates, string $value): void
    {
        if ($value === '') return;
        $name = self::findPropertyByNames($schema, $candidates);
        if (!$name) return;
        $type = $schema[$name]['type'] ?? '';
        if ($type === 'select') {
            $result[$name] = ['select' => ['name' => $value]];
        } elseif ($type === 'rich_text') {
            $result[$name] = ['rich_text' => [['text' => ['content' => $value]]]];
        }
    }

    private static function mapCheckbox(array &$result, array $schema, array $candidates, bool $value): void
    {
        $name = self::findPropertyByNames($schema, $candidates);
        if ($name && ($schema[$name]['type'] ?? '') === 'checkbox') {
            $result[$name] = ['checkbox' => $value];
        }
    }

    private static function mapDate(array &$result, array $schema, array $candidates, string $value): void
    {
        if ($value === '') return;
        $name = self::findPropertyByNames($schema, $candidates);
        if ($name && ($schema[$name]['type'] ?? '') === 'date') {
            $dt = date_create($value);
            if ($dt) $result[$name] = ['date' => ['start' => $dt->format(DateTimeInterface::ATOM)]];
        }
    }

    // --- Utils ---

    private static function findPropertyByType(array $schema, string $type): ?string
    {
        foreach ($schema as $name => $prop) if (($prop['type'] ?? '') === $type) return $name;
        return null;
    }

    private static function findPropertyByNames(array $schema, array $candidates): ?string
    {
        $indexed = [];
        foreach ($schema as $name => $_) $indexed[self::normalize($name)] = $name;
        foreach ($candidates as $candidate) {
            $norm = self::normalize($candidate);
            if (isset($indexed[$norm])) return $indexed[$norm];
        }
        return null;
    }

    private static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'u', 'n'], $value);
        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private static function getDatabaseSchema(string $databaseId): ?array
    {
        if ($databaseId === '') return null;
        $response = self::request('GET', '/databases/' . $databaseId);
        return $response['properties'] ?? null;
    }

    private static function request(string $method, string $path, ?array $payload = null): ?array
    {
        $apiKey = trim((string) getenv('NOTION_API_KEY'));
        if ($apiKey === '') return null;

        $ch = curl_init(self::API_BASE . $path);
        $headers = ['Authorization: Bearer ' . $apiKey, 'Notion-Version: ' . self::API_VERSION, 'Content-Type: application/json'];
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 15]);
        if ($payload) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decoded = json_decode((string)$raw, true);
        if ($status < 200 || $status >= 300) {
            self::log("HTTP $status: " . ($decoded['message'] ?? 'unknown error'));
            return null;
        }
        return $decoded;
    }

    private static function isEnabled(string $dbVar): bool
    {
        $enabled = strtolower(trim((string) getenv('NOTION_ENABLED')));
        return in_array($enabled, ['1', 'true', 'yes', 'on']) && trim((string) getenv('NOTION_API_KEY')) !== '' && trim((string) getenv($dbVar)) !== '';
    }

    private static function log(string $msg): void { error_log('[NotionService] ' . $msg); }
}

