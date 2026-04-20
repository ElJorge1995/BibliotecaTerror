<?php
require_once __DIR__ . '/../../ApiLoging/config/Env.php';
require_once __DIR__ . '/../../ApiLoging/services/NotionService.php';

Env::load(__DIR__ . '/../../ApiLoging/.env');

echo "--- Debug Notion Books ---\n";
echo "NOTION_ENABLED: " . (getenv('NOTION_ENABLED') ?: 'false') . "\n";
echo "NOTION_BOOKS_DATABASE_ID: " . (getenv('NOTION_BOOKS_DATABASE_ID') ?: 'NOT SET') . "\n";

$testBook = [
    'id' => 999,
    'titulo' => 'Libro de Prueba Debug',
    'autor' => 'Antigravity AI',
    'categoria' => 'Terror',
    'stock' => 5,
    'google_id' => 'DEBUG-123',
    'portada' => 'https://via.placeholder.com/150',
    'rating' => 4.5
];

echo "Intentando sincronizar libro...\n";
$result = NotionService::syncBookCreated($testBook);

if ($result) {
    echo "¡ÉXITO! El libro debería aparecer en Notion.\n";
} else {
    echo "ERROR: La sincronización falló. Revisa los logs de error de PHP.\n";
}
