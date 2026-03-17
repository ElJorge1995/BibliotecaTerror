<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/Env.php';
Env::load(__DIR__ . '/.env');
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/services/JwtService.php';
require_once __DIR__ . '/models/User.php';

$users = User::listAll();
$user = User::findById((int)$users[0]['id']);
$token = JwtService::generate($user);

$ch = curl_init('http://localhost:8000/auth/update-username');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username' => 'testuser' . time()]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

echo "Update Code: $code\n";
echo "Update Response: $resp\n";
