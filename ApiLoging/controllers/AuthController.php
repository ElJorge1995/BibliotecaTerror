<?php

class AuthController
{
    public static function register(): void
    {
        $data = self::getJsonInput();
        $username = trim((string) ($data['username'] ?? ''));
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $passwordConfirmation = (string) ($data['password_confirmation'] ?? '');
        RateLimiter::enforce('register', Security::getClientIp(), 10, 900);

        if (
            $username === '' ||
            $firstName === '' ||
            $lastName === '' ||
            $email === '' ||
            $password === '' ||
            $passwordConfirmation === ''
        ) {
            Response::json(['error' => 'username, first_name, last_name, email, password and password_confirmation are required'], 422);
        }

        if (!preg_match('/^[a-zA-Z0-9._-]{3,30}$/', $username)) {
            Response::json(['error' => 'invalid username format'], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'invalid email'], 422);
        }

        if ($phone !== '' && !preg_match('/^(?:\+34|0034)?[6789]\d{8}$/', $phone)) {
            Response::json(['error' => 'invalid spanish phone number format'], 422);
        }

        if ($password !== $passwordConfirmation) {
            Response::json(['error' => 'passwords do not match'], 422);
        }

        if (strlen($password) < 6) {
            Response::json(['error' => 'password must be at least 6 characters'], 422);
        }

        $name = trim($firstName . ' ' . $lastName);
        $hash = password_hash($password, PASSWORD_BCRYPT);
        [$plainToken, $tokenHash, $expiresAt] = self::buildVerificationToken();

        try {
            if (User::findByEmail($email) || User::findByUsername($username)) {
                SecurityLogger::log('register_conflict', null, ['email' => $email, 'username' => $username]);
                Response::json(['error' => 'email or username already exists'], 409);
            }

            if (User::findPendingRegistrationConflict($email, $username)) {
                Response::json(['error' => 'there is already a pending registration for this email or username'], 409);
            }

            $pendingId = User::createPendingRegistration($username, $email, $hash, $name, $firstName, $lastName, $phone, $tokenHash, $expiresAt);

            $verificationUrl = self::buildVerificationUrl($plainToken);
            $sent = MailService::sendVerificationEmail($email, $name, $verificationUrl);

            if (!$sent) {
                User::deletePendingRegistration($pendingId);
                Response::json(['error' => 'verification email could not be sent'], 500);
            }

            Response::json([
                'message' => 'verification email sent, confirm your email to complete registration',
            ], 201);
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                SecurityLogger::log('register_conflict', null, ['email' => $email, 'username' => $username]);
                Response::json(['error' => 'email or username already exists'], 409);
            }
            Response::json(['error' => 'could not create user'], 500);
        }
    }

    public static function login(): void
    {
        $data = self::getJsonInput();
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        RateLimiter::enforce('login', Security::getClientIp() . '|' . strtolower($email), 10, 900);

        if ($email === '' || $password === '') {
            Response::json(['error' => 'email and password are required'], 422);
        }

        $user = User::findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            SecurityLogger::log('login_failed', $user ? (int) $user['id'] : null, ['email' => $email]);
            Response::json(['error' => 'invalid credentials'], 401);
        }

        if ((int) ($user['is_email_verified'] ?? 0) !== 1) {
            SecurityLogger::log('login_blocked_unverified', (int) $user['id'], ['email' => $email]);
            Response::json(['error' => 'email not verified'], 403);
        }

        $token = JwtService::generate($user);
        SecurityLogger::log('login_success', (int) $user['id']);

        Response::json([
            'token' => $token,
            'user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'] ?? null,
                'first_name' => $user['first_name'] ?? null,
                'last_name' => $user['last_name'] ?? null,
                'phone' => $user['phone'] ?? null,
                'name' => $user['name'],
                'role' => $user['role'],
                'email' => $user['email'],
            ],
        ]);
    }

    public static function verifyEmail(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            Response::json(['error' => 'verification token is required'], 422);
        }

        $tokenHash = hash('sha256', $token);
        $user = User::findByVerificationHash($tokenHash);

        try {
            if ($user) {
                User::markEmailAsVerified((int) $user['id']);
                $freshUser = User::findByEmail((string) $user['email']);
                if (!$freshUser) {
                    Response::json(['error' => 'could not load verified user'], 500);
                }
            } else {
                $pending = User::findPendingRegistrationByTokenHash($tokenHash);
                if (!$pending) {
                    Response::json(['error' => 'invalid or expired verification token'], 400);
                }

                if (User::findByEmail((string) $pending['email']) || User::findByUsername((string) $pending['username'])) {
                    Response::json(['error' => 'email or username already exists'], 409);
                }

                $freshUser = User::createUserFromPendingRegistration((int) $pending['id']);

                // Notion es un espejo operativo: si falla, no se bloquea el alta local.
                try {
                    NotionService::syncUserCreated($freshUser);
                } catch (Throwable $syncError) {
                    error_log('[AuthController] Notion sync failed: ' . $syncError->getMessage());
                }
            }

            $jwt = JwtService::generate($freshUser);
            $redirectBase = getenv('EMAIL_VERIFY_REDIRECT_URL') ?: '';

            if ($redirectBase !== '' && Security::isAllowedAbsoluteUrl($redirectBase, 'REDIRECT_ALLOWED_ORIGINS')) {
                $separator = str_contains($redirectBase, '?') ? '&' : '?';
                $location = $redirectBase . $separator . 'token=' . urlencode($jwt);
                header('Location: ' . $location, true, 302);
                exit;
            }

            Response::json([
                'message' => 'email verified',
                'token' => $jwt,
                'user' => [
                    'id' => (int) $freshUser['id'],
                    'username' => $freshUser['username'] ?? null,
                    'first_name' => $freshUser['first_name'] ?? null,
                    'last_name' => $freshUser['last_name'] ?? null,
                    'phone' => $freshUser['phone'] ?? null,
                    'name' => $freshUser['name'],
                    'role' => $freshUser['role'],
                    'email' => $freshUser['email'],
                ],
            ]);
        } catch (Throwable $e) {
            Response::json(['error' => 'could not verify email'], 500);
        }
    }

    public static function resendVerification(): void
    {
        $data = self::getJsonInput();
        $email = trim((string) ($data['email'] ?? ''));
        RateLimiter::enforce('resend_verification', Security::getClientIp() . '|' . strtolower($email), 5, 900);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'valid email is required'], 422);
        }

        $user = User::findByEmail($email);
        if (!$user) {
            $pending = User::findPendingRegistrationByEmail($email);
            if ($pending) {
                [$plainToken, $tokenHash, $expiresAt] = self::buildVerificationToken();
                User::refreshPendingRegistrationToken((int) $pending['id'], $tokenHash, $expiresAt);
                $url = self::buildVerificationUrl($plainToken);
                $sent = MailService::sendVerificationEmail((string) $pending['email'], (string) $pending['name'], $url);

                if (!$sent) {
                    Response::json(['error' => 'could not send verification email'], 500);
                }

                Response::json(['message' => 'verification email sent']);
            }

            Response::json(['message' => 'if the account exists, a verification email was sent']);
        }

        if ((int) ($user['is_email_verified'] ?? 0) === 1) {
            Response::json(['message' => 'email is already verified']);
        }

        [$plainToken, $tokenHash, $expiresAt] = self::buildVerificationToken();
        User::createVerificationToken((int) $user['id'], $tokenHash, $expiresAt);
        $url = self::buildVerificationUrl($plainToken);
        $sent = MailService::sendVerificationEmail((string) $user['email'], (string) $user['name'], $url);

        if (!$sent) {
            Response::json(['error' => 'could not send verification email'], 500);
        }

        Response::json(['message' => 'verification email sent']);
    }

    public static function requestPasswordReset(): void
    {
        $data = self::getJsonInput();
        $email = trim((string) ($data['email'] ?? ''));
        RateLimiter::enforce('request_password_reset', Security::getClientIp() . '|' . strtolower($email), 5, 900);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'valid email is required'], 422);
        }

        $user = User::findByEmail($email);
        if (!$user) {
            SecurityLogger::log('password_reset_requested', null, ['email' => $email, 'result' => 'not_found']);
            Response::json(['message' => 'if the account exists, a recovery email was sent']);
        }

        [$plainToken, $tokenHash, $expiresAt] = self::buildVerificationToken();

        try {
            User::createPasswordResetToken((int) $user['id'], $tokenHash, $expiresAt);
            $resetUrl = self::buildPasswordResetUrl($plainToken);
            $sent = MailService::sendPasswordResetEmail((string) $user['email'], (string) $user['name'], $resetUrl);

            if (!$sent) {
                Response::json(['error' => 'could not send password reset email'], 500);
            }

            SecurityLogger::log('password_reset_requested', (int) $user['id']);
            Response::json(['message' => 'if the account exists, a recovery email was sent']);
        } catch (Throwable $e) {
            Response::json(['error' => 'could not request password reset'], 500);
        }
    }

    public static function resetPassword(): void
    {
        $data = self::getJsonInput();
        $token = trim((string) ($data['token'] ?? ''));
        $newPassword = (string) ($data['new_password'] ?? '');
        $newPasswordConfirmation = (string) ($data['new_password_confirmation'] ?? '');

        if ($token === '' || $newPassword === '' || $newPasswordConfirmation === '') {
            Response::json(['error' => 'token, new_password and new_password_confirmation are required'], 422);
        }

        if ($newPassword !== $newPasswordConfirmation) {
            Response::json(['error' => 'new passwords do not match'], 422);
        }

        if (strlen($newPassword) < 6) {
            Response::json(['error' => 'new password must be at least 6 characters'], 422);
        }

        $tokenHash = hash('sha256', $token);
        $user = User::findByPasswordResetHash($tokenHash);

        if (!$user) {
            SecurityLogger::log('password_reset_failed', null, ['reason' => 'invalid_token']);
            Response::json(['error' => 'invalid or expired reset token'], 400);
        }

        if (password_verify($newPassword, (string) $user['password'])) {
            Response::json(['error' => 'new password must be different from current password'], 422);
        }

        try {
            User::updatePasswordHash((int) $user['id'], password_hash($newPassword, PASSWORD_BCRYPT));
            User::markPasswordResetAsUsed((int) $user['reset_id']);
            SecurityLogger::log('password_reset_completed', (int) $user['id']);
            Response::json(['message' => 'password updated']);
        } catch (Throwable $e) {
            Response::json(['error' => 'could not reset password'], 500);
        }
    }

    public static function updateUsername(): void
    {
        $session = AuthMiddleware::handle();
        $userId = (int) ($session['sub'] ?? 0);
        $data = self::getJsonInput();
        $username = trim((string) ($data['username'] ?? ''));

        if (!preg_match('/^[a-zA-Z0-9._-]{3,30}$/', $username)) {
            Response::json(['error' => 'invalid username format'], 422);
        }

        try {
            User::updateUsername($userId, $username);
            self::respondWithFreshSession($userId, 'username updated');
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                Response::json(['error' => 'username already exists'], 409);
            }
            Response::json(['error' => 'could not update username'], 500);
        }
    }

    public static function updateName(): void
    {
        $session = AuthMiddleware::handle();
        $userId = (int) ($session['sub'] ?? 0);
        $data = self::getJsonInput();
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));

        if ($firstName === '' || $lastName === '') {
            Response::json(['error' => 'first_name and last_name are required'], 422);
        }

        try {
            User::updateName($userId, $firstName, $lastName);
            self::respondWithFreshSession($userId, 'name updated');
        } catch (Throwable $e) {
            Response::json(['error' => 'could not update name'], 500);
        }
    }

    public static function updatePhone(): void
    {
        $session = AuthMiddleware::handle();
        $userId = (int) ($session['sub'] ?? 0);
        $data = self::getJsonInput();
        $phone = trim((string) ($data['phone'] ?? ''));

        if ($phone !== '' && !preg_match('/^[0-9]{7,15}$/', $phone)) {
            Response::json(['error' => 'invalid phone'], 422);
        }

        try {
            User::updatePhone($userId, $phone);
            self::respondWithFreshSession($userId, 'phone updated');
        } catch (Throwable $e) {
            Response::json(['error' => 'could not update phone'], 500);
        }
    }

    public static function requestEmailChange(): void
    {
        $session = AuthMiddleware::handle();
        $userId = (int) ($session['sub'] ?? 0);
        $data = self::getJsonInput();
        $newEmail = trim((string) ($data['new_email'] ?? ''));
        RateLimiter::enforce('request_email_change', Security::getClientIp() . '|' . $userId, 5, 900);

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'invalid email'], 422);
        }

        $currentUser = User::findById($userId);
        if (!$currentUser) {
            Response::json(['error' => 'user not found'], 404);
        }

        if (strcasecmp((string) $currentUser['email'], $newEmail) === 0) {
            Response::json(['error' => 'new email must be different from current email'], 422);
        }

        if (User::findByEmail($newEmail)) {
            Response::json(['error' => 'email already exists'], 409);
        }

        [$plainToken, $tokenHash, $expiresAt] = self::buildVerificationToken();

        try {
            User::createEmailChangeToken($userId, $newEmail, $tokenHash, $expiresAt);
            $confirmationUrl = self::buildEmailChangeUrl($plainToken);
            $sent = MailService::sendEmailChangeConfirmation($newEmail, (string) $currentUser['name'], $confirmationUrl);

            if (!$sent) {
                Response::json(['error' => 'could not send email change confirmation'], 500);
            }

            SecurityLogger::log('email_change_requested', $userId, ['new_email' => $newEmail]);
            Response::json(['message' => 'email change confirmation sent']);
        } catch (Throwable $e) {
            Response::json(['error' => 'could not request email change'], 500);
        }
    }

    public static function confirmEmailChange(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            Response::json(['error' => 'confirmation token is required'], 422);
        }

        $tokenHash = hash('sha256', $token);
        $pending = User::findPendingEmailChange($tokenHash);

        if (!$pending) {
            Response::json(['error' => 'invalid or expired confirmation token'], 400);
        }

        if (User::findByEmail((string) $pending['new_email'])) {
            Response::json(['error' => 'email already exists'], 409);
        }

        try {
            User::applyEmailChange((int) $pending['id'], (int) $pending['user_id'], (string) $pending['new_email']);
            $freshUser = User::findById((int) $pending['user_id']);
            if (!$freshUser) {
                Response::json(['error' => 'could not load updated user'], 500);
            }

            try {
                NotionService::syncUserUpdated($freshUser);
            } catch (Throwable $syncError) {
                error_log('[AuthController] Notion sync on email change failed: ' . $syncError->getMessage());
            }

            $jwt = JwtService::generate($freshUser);
            $redirectBase = getenv('EMAIL_CHANGE_REDIRECT_URL') ?: '';

            if ($redirectBase !== '' && Security::isAllowedAbsoluteUrl($redirectBase, 'REDIRECT_ALLOWED_ORIGINS')) {
                $separator = str_contains($redirectBase, '?') ? '&' : '?';
                $location = $redirectBase . $separator . 'token=' . urlencode($jwt);
                header('Location: ' . $location, true, 302);
                exit;
            }

            Response::json([
                'message' => 'email updated',
                'token' => $jwt,
                'user' => self::mapUser($freshUser),
            ]);
        } catch (Throwable $e) {
            Response::json(['error' => 'could not confirm email change'], 500);
        }
    }

    public static function changePassword(): void
    {
        $session = AuthMiddleware::handle();
        $userId = (int) ($session['sub'] ?? 0);
        $data = self::getJsonInput();
        $currentPassword = (string) ($data['current_password'] ?? '');
        $newPassword = (string) ($data['new_password'] ?? '');
        $newPasswordConfirmation = (string) ($data['new_password_confirmation'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $newPasswordConfirmation === '') {
            Response::json(['error' => 'current_password, new_password and new_password_confirmation are required'], 422);
        }

        if ($newPassword !== $newPasswordConfirmation) {
            Response::json(['error' => 'new passwords do not match'], 422);
        }

        if (strlen($newPassword) < 6) {
            Response::json(['error' => 'new password must be at least 6 characters'], 422);
        }

        $user = User::findById($userId);
        if (!$user || !password_verify($currentPassword, (string) $user['password'])) {
            Response::json(['error' => 'current password is incorrect'], 401);
        }

        if (password_verify($newPassword, (string) $user['password'])) {
            Response::json(['error' => 'new password must be different from current password'], 422);
        }

        try {
            User::updatePasswordHash($userId, password_hash($newPassword, PASSWORD_BCRYPT));
            SecurityLogger::log('password_changed', $userId);
            self::respondWithFreshSession($userId, 'password updated');
        } catch (Throwable $e) {
            Response::json(['error' => 'could not update password'], 500);
        }
    }

    public static function me(): void
    {
        $session = AuthMiddleware::handle();
        $userId = (int) ($session['sub'] ?? 0);
        $user = User::findById($userId);

        if (!$user) {
            Response::json(['error' => 'user not found'], 404);
        }

        Response::json(['user' => self::mapUser($user)]);
    }

    public static function adminUsers(): void
    {
        self::requireAdmin();

        $users = array_map(
            static function (array $user): array {
                return [
                    'id' => (int) $user['id'],
                    'username' => $user['username'] ?? null,
                    'first_name' => $user['first_name'] ?? null,
                    'last_name' => $user['last_name'] ?? null,
                    'phone' => $user['phone'] ?? null,
                    'name' => $user['name'] ?? null,
                    'role' => $user['role'] ?? null,
                    'email' => $user['email'] ?? null,
                    'is_email_verified' => (int) ($user['is_email_verified'] ?? 0),
                    'email_verified_at' => $user['email_verified_at'] ?? null,
                    'created_at' => $user['created_at'] ?? null,
                ];
            },
            User::listAll()
        );

        Response::json(['users' => $users]);
    }

    public static function adminUpdateRole(): void
    {
        self::requireAdmin();

        $data = self::getJsonInput();
        $userId = (int) ($data['user_id'] ?? 0);
        $role = trim((string) ($data['role'] ?? ''));

        if ($userId <= 0 || $role === '') {
            Response::json(['error' => 'user_id and role are required'], 422);
        }

        if (!in_array($role, ['user', 'pro', 'admin'])) {
            Response::json(['error' => 'invalid role'], 422);
        }

        $targetUser = User::findById($userId);
        if (!$targetUser) {
            Response::json(['error' => 'user not found'], 404);
        }

        try {
            User::updateRole($userId, $role);
            SecurityLogger::log('admin_updated_user_role', $userId, ['new_role' => $role]);

            try {
                $updatedUser = User::findById($userId);
                if ($updatedUser) {
                    NotionService::syncUserUpdated($updatedUser);
                }
            } catch (Throwable $syncError) {
                error_log('[AuthController] Notion sync for admin role update failed: ' . $syncError->getMessage());
            }

            Response::json(['message' => 'role updated']);
        } catch (Throwable $e) {
            Response::json(['error' => 'could not update role'], 500);
        }
    }

    public static function logout(): void
    {
        $token = AuthMiddleware::extractBearerToken();

        if ($token === null) {
            Response::json(['error' => 'unauthorized'], 401);
        }

        try {
            $db = Database::connect();
            $stmt = $db->prepare('INSERT INTO revoked_tokens(token) VALUES(?)');
            $stmt->execute([$token]);
            $decoded = JwtService::verify($token);
            SecurityLogger::log('logout', isset($decoded['sub']) ? (int) $decoded['sub'] : null);
            Response::json(['message' => 'logged out']);
        } catch (Throwable $e) {
            Response::json(['error' => 'could not logout'], 500);
        }
    }

    private static function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '{}', true);

        return is_array($data) ? $data : [];
    }

    private static function buildVerificationToken(): array
    {
        $ttlSeconds = (int) (getenv('EMAIL_VERIFICATION_TTL_SECONDS') ?: 86400);
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

        return [$plainToken, $tokenHash, $expiresAt];
    }

    private static function buildVerificationUrl(string $plainToken): string
    {
        $base = getenv('EMAIL_VERIFY_URL_BASE') ?: 'http://localhost:8000/auth/verify-email';
        $separator = str_contains($base, '?') ? '&' : '?';

        return $base . $separator . 'token=' . urlencode($plainToken);
    }

    private static function buildEmailChangeUrl(string $plainToken): string
    {
        $base = getenv('EMAIL_CHANGE_VERIFY_URL_BASE') ?: 'http://localhost:8000/auth/confirm-email-change';
        $separator = str_contains($base, '?') ? '&' : '?';

        return $base . $separator . 'token=' . urlencode($plainToken);
    }

    private static function buildPasswordResetUrl(string $plainToken): string
    {
        $base = getenv('PASSWORD_RESET_URL_BASE') ?: 'http://localhost:5173/restablecer-contrasena';
        $separator = str_contains($base, '?') ? '&' : '?';

        return $base . $separator . 'token=' . urlencode($plainToken);
    }

    private static function respondWithFreshSession(int $userId, string $message): void
    {
        $user = User::findById($userId);
        if (!$user) {
            Response::json(['error' => 'user not found'], 404);
        }

        try {
            NotionService::syncUserUpdated($user);
        } catch (Throwable $syncError) {
            error_log('[AuthController] Notion sync on profile update failed: ' . $syncError->getMessage());
        }

        // Cada cambio de perfil devuelve un JWT nuevo para no dejar datos obsoletos en cliente.
        $token = JwtService::generate($user);

        Response::json([
            'message' => $message,
            'token' => $token,
            'user' => self::mapUser($user),
        ]);
    }

    private static function mapUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'username' => $user['username'] ?? null,
            'first_name' => $user['first_name'] ?? null,
            'last_name' => $user['last_name'] ?? null,
            'phone' => $user['phone'] ?? null,
            'name' => $user['name'] ?? null,
            'role' => $user['role'] ?? null,
            'email' => $user['email'] ?? null,
        ];
    }

    private static function requireAdmin(): array
    {
        $session = AuthMiddleware::handle();

        if (($session['role'] ?? null) !== 'admin') {
            Response::json(['error' => 'forbidden'], 403);
        }

        return $session;
    }
}
