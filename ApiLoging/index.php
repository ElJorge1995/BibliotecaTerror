<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/Env.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/Security.php';
require_once __DIR__ . '/services/JwtService.php';
require_once __DIR__ . '/services/MailService.php';
require_once __DIR__ . '/services/NotionService.php';
require_once __DIR__ . '/services/RateLimiter.php';
require_once __DIR__ . '/services/SecurityLogger.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/controllers/AuthController.php';

Env::load(__DIR__ . '/.env');

Security::sendSecurityHeaders();
Security::enforceProductionTransport();
Security::ensureStrongJwtSecret();
Security::bootstrapCors();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

// Router minimo centralizado: mantiene los endpoints visibles en un solo punto.
if ($uri === '/auth/register' && $method === 'POST') {
    AuthController::register();
}

if ($uri === '/auth/login' && $method === 'POST') {
    AuthController::login();
}

if ($uri === '/auth/verify-email' && $method === 'GET') {
    AuthController::verifyEmail();
}

if ($uri === '/auth/resend-verification' && $method === 'POST') {
    AuthController::resendVerification();
}

if ($uri === '/auth/request-password-reset' && $method === 'POST') {
    AuthController::requestPasswordReset();
}

if ($uri === '/auth/reset-password' && $method === 'POST') {
    AuthController::resetPassword();
}

if ($uri === '/auth/update-username' && $method === 'POST') {
    AuthController::updateUsername();
}

if ($uri === '/auth/update-name' && $method === 'POST') {
    AuthController::updateName();
}

if ($uri === '/auth/update-phone' && $method === 'POST') {
    AuthController::updatePhone();
}

if ($uri === '/auth/request-email-change' && $method === 'POST') {
    AuthController::requestEmailChange();
}

if ($uri === '/auth/confirm-email-change' && $method === 'GET') {
    AuthController::confirmEmailChange();
}

if ($uri === '/auth/change-password' && $method === 'POST') {
    AuthController::changePassword();
}

if ($uri === '/auth/me' && $method === 'GET') {
    AuthController::me();
}

if ($uri === '/auth/admin/users' && $method === 'GET') {
    AuthController::adminUsers();
}

if ($uri === '/auth/admin/update-role' && $method === 'POST') {
    AuthController::adminUpdateRole();
}

if ($uri === '/auth/logout' && $method === 'POST') {
    AuthController::logout();
}

if ($uri === '/api/profile' && $method === 'GET') {
    $user = AuthMiddleware::handle();
    Response::json([
        'message' => 'protected resource',
        'user' => $user,
    ]);
}

Response::json(['error' => 'not found'], 404);
