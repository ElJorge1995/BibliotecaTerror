<?php

class NotionService
{
    private const API_BASE = 'https://api.notion.com/v1';
    private const API_VERSION = '2022-06-28';

    public static function syncUserCreated(array $user): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        $schema = self::getDatabaseSchema();
        if ($schema === null) {
            self::log('notion schema unavailable');
            return false;
        }

        $properties = self::buildProperties($schema, $user);
        if ($properties === []) {
            self::log('notion properties could not be mapped');
            return false;
        }

        $databaseId = trim((string) getenv('NOTION_DATABASE_ID'));
        $payload = [
            'parent' => ['database_id' => $databaseId],
            'properties' => $properties,
        ];

        $response = self::request('POST', '/pages', $payload);
        if ($response === null) {
            self::log('notion page insert failed');
            return false;
        }

        return true;
    }

    public static function syncUserUpdated(array $user): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        $pageId = self::findPageIdByUserId((int) ($user['id'] ?? 0));
        if ($pageId === null) {
            self::log('notion page not found for user update, creating instead');
            return self::syncUserCreated($user);
        }

        $schema = self::getDatabaseSchema();
        if ($schema === null) {
            self::log('notion schema unavailable for update');
            return false;
        }

        $properties = self::buildProperties($schema, $user);
        if ($properties === []) {
            self::log('notion properties could not be mapped for update');
            return false;
        }

        $payload = [
            'properties' => $properties,
        ];

        $response = self::request('PATCH', '/pages/' . $pageId, $payload);
        if ($response === null) {
            self::log('notion page update failed');
            return false;
        }

        return true;
    }

    private static function findPageIdByUserId(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }

        $schema = self::getDatabaseSchema();
        if ($schema === null) {
            return null;
        }

        $idPropertyName = self::findPropertyByNames($schema, ['id', 'user id', 'auth id', 'usuario id']);
        if ($idPropertyName === null || ($schema[$idPropertyName]['type'] ?? '') !== 'number') {
            return null;
        }

        $databaseId = trim((string) getenv('NOTION_DATABASE_ID'));
        $payload = [
            'filter' => [
                'property' => $idPropertyName,
                'number' => [
                    'equals' => $userId
                ]
            ],
            'page_size' => 1
        ];

        $response = self::request('POST', '/databases/' . $databaseId . '/query', $payload);
        if (!is_array($response) || !isset($response['results']) || count($response['results']) === 0) {
            return null;
        }

        return $response['results'][0]['id'] ?? null;
    }

    private static function buildProperties(array $schema, array $user): array
    {
        $result = [];
        $titlePropertyName = self::findPropertyByType($schema, 'title');

        if ($titlePropertyName !== null) {
            $titleValue = (string) ($user['name'] ?? $user['username'] ?? $user['email'] ?? ('Usuario #' . (int) ($user['id'] ?? 0)));
            $result[$titlePropertyName] = [
                'title' => [[
                    'text' => ['content' => $titleValue],
                ]],
            ];
        }

        self::mapNumber($result, $schema, ['id', 'user id', 'auth id', 'usuario id'], (int) ($user['id'] ?? 0));
        self::mapEmail($result, $schema, ['email', 'correo', 'correo electronico'], (string) ($user['email'] ?? ''));
        self::mapRichText($result, $schema, ['username', 'usuario', 'nombre de usuario'], (string) ($user['username'] ?? ''));
        self::mapRichText($result, $schema, ['nombre', 'name'], (string) ($user['first_name'] ?? $user['name'] ?? ''));
        self::mapRichText($result, $schema, ['apellido', 'last name', 'last_name'], (string) ($user['last_name'] ?? ''));
        self::mapPhone($result, $schema, ['telefono', 'telefono movil', 'phone'], (string) ($user['phone'] ?? ''));
        self::mapSelectOrText($result, $schema, ['rol', 'role'], (string) ($user['role'] ?? 'user'));
        self::mapCheckbox($result, $schema, ['verificado', 'email verificado', 'is email verified'], ((int) ($user['is_email_verified'] ?? 0)) === 1);
        self::mapDate($result, $schema, ['creado en', 'created at', 'fecha alta'], (string) ($user['created_at'] ?? ''));

        return $result;
    }

    private static function mapNumber(array &$result, array $schema, array $candidates, int $value): void
    {
        $name = self::findPropertyByNames($schema, $candidates);
        if ($name === null || ($schema[$name]['type'] ?? '') !== 'number') {
            return;
        }

        $result[$name] = ['number' => $value];
    }

    private static function mapEmail(array &$result, array $schema, array $candidates, string $value): void
    {
        if ($value === '') {
            return;
        }

        $name = self::findPropertyByNames($schema, $candidates);
        if ($name === null || ($schema[$name]['type'] ?? '') !== 'email') {
            return;
        }

        $result[$name] = ['email' => $value];
    }

    private static function mapRichText(array &$result, array $schema, array $candidates, string $value): void
    {
        if ($value === '') {
            return;
        }

        $name = self::findPropertyByNames($schema, $candidates);
        if ($name === null) {
            return;
        }

        $type = (string) ($schema[$name]['type'] ?? '');
        if ($type !== 'rich_text') {
            return;
        }

        $result[$name] = [
            'rich_text' => [[
                'text' => ['content' => $value],
            ]],
        ];
    }

    private static function mapPhone(array &$result, array $schema, array $candidates, string $value): void
    {
        if ($value === '') {
            return;
        }

        $name = self::findPropertyByNames($schema, $candidates);
        if ($name === null || ($schema[$name]['type'] ?? '') !== 'phone_number') {
            return;
        }

        $result[$name] = ['phone_number' => $value];
    }

    private static function mapSelectOrText(array &$result, array $schema, array $candidates, string $value): void
    {
        if ($value === '') {
            return;
        }

        $name = self::findPropertyByNames($schema, $candidates);
        if ($name === null) {
            return;
        }

        $type = (string) ($schema[$name]['type'] ?? '');
        if ($type === 'select') {
            $result[$name] = ['select' => ['name' => $value]];
            return;
        }

        if ($type === 'rich_text') {
            $result[$name] = [
                'rich_text' => [[
                    'text' => ['content' => $value],
                ]],
            ];
        }
    }

    private static function mapCheckbox(array &$result, array $schema, array $candidates, bool $value): void
    {
        $name = self::findPropertyByNames($schema, $candidates);
        if ($name === null || ($schema[$name]['type'] ?? '') !== 'checkbox') {
            return;
        }

        $result[$name] = ['checkbox' => $value];
    }

    private static function mapDate(array &$result, array $schema, array $candidates, string $value): void
    {
        if ($value === '') {
            return;
        }

        $name = self::findPropertyByNames($schema, $candidates);
        if ($name === null || ($schema[$name]['type'] ?? '') !== 'date') {
            return;
        }

        $dt = date_create($value);
        if (!$dt) {
            return;
        }

        $result[$name] = ['date' => ['start' => $dt->format(DateTimeInterface::ATOM)]];
    }

    private static function findPropertyByType(array $schema, string $type): ?string
    {
        foreach ($schema as $name => $property) {
            if (($property['type'] ?? '') === $type) {
                return $name;
            }
        }

        return null;
    }

    private static function findPropertyByNames(array $schema, array $candidates): ?string
    {
        $indexed = [];
        foreach ($schema as $name => $_property) {
            $indexed[self::normalize($name)] = $name;
        }

        foreach ($candidates as $candidate) {
            $normalized = self::normalize($candidate);
            if (isset($indexed[$normalized])) {
                return $indexed[$normalized];
            }
        }

        return null;
    }

    private static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $value
        );

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private static function getDatabaseSchema(): ?array
    {
        $databaseId = trim((string) getenv('NOTION_DATABASE_ID'));
        if ($databaseId === '') {
            return null;
        }

        $response = self::request('GET', '/databases/' . $databaseId);
        if (!is_array($response) || !isset($response['properties']) || !is_array($response['properties'])) {
            return null;
        }

        return $response['properties'];
    }

    private static function request(string $method, string $path, ?array $payload = null): ?array
    {
        if (!function_exists('curl_init')) {
            self::log('curl extension unavailable');
            return null;
        }

        $apiKey = trim((string) getenv('NOTION_API_KEY'));
        if ($apiKey === '') {
            return null;
        }

        $ch = curl_init();
        if ($ch === false) {
            return null;
        }

        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Notion-Version: ' . self::API_VERSION,
            'Content-Type: application/json',
        ];

        $options = [
            CURLOPT_URL => self::API_BASE . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 15,
        ];

        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!is_string($raw) || $raw === '') {
            if ($curlError !== '') {
                self::log('notion curl error: ' . $curlError);
            }
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            self::log('notion invalid json response');
            return null;
        }

        if ($status < 200 || $status >= 300) {
            $message = (string) ($decoded['message'] ?? 'unknown notion error');
            self::log('notion http error ' . $status . ': ' . $message);
            return null;
        }

        return $decoded;
    }

    private static function isEnabled(): bool
    {
        $enabled = strtolower(trim((string) getenv('NOTION_ENABLED')));
        if (!in_array($enabled, ['1', 'true', 'yes', 'on'], true)) {
            return false;
        }

        return trim((string) getenv('NOTION_API_KEY')) !== '' && trim((string) getenv('NOTION_DATABASE_ID')) !== '';
    }

    private static function log(string $message): void
    {
        error_log('[NotionService] ' . $message);
    }
}

