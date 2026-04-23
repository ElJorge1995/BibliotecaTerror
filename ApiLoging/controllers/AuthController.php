<?php

class AuthController
{
    public static function register(): void
    {
        $data = self::getJsonInput();
        $username = trim((string) ($data['username'] ?? ''));
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $dni = trim((string) ($data['dni'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $passwordConfirmation = (string) ($data['password_confirmation'] ?? '');
        RateLimiter::enforce('register', Security::getClientIp(), 10, 900);

        if (
            $username === '' ||
            $firstName === '' ||
            $lastName === '' ||
            $dni === '' ||
            $email === '' ||
            $password === '' ||
            $passwordConfirmation === ''
        ) {
            Response::json(['error' => 'username, first_name, last_name, dni, email, password and password_confirmation are required'], 422);
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

            $pendingId = User::createPendingRegistration($username, $email, $hash, $name, $firstName, $lastName, $dni, $phone, $tokenHash, $expiresAt);

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
        $normalizedEmail = strtolower($email);

        // Throttling unificado: solo contamos FALLOS (5 en 30 min por email).
        // Los logins exitosos no consumen cuota — necesario para que kick-old
        // de single-session no bloquee al usuario legítimo reclamando su cuenta.
        RateLimiter::checkFailureLockout('login_lockout', $normalizedEmail, 5, 1800);

        if ($email === '' || $password === '') {
            Response::json(['error' => 'email and password are required'], 422);
        }

        $user = User::findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            RateLimiter::recordFailure('login_lockout', $normalizedEmail, 1800);
            SecurityLogger::log('login_failed', $user ? (int) $user['id'] : null, ['email' => $email]);
            Response::json(['error' => 'invalid credentials'], 401);
        }

        if ((int) ($user['is_email_verified'] ?? 0) !== 1) {
            SecurityLogger::log('login_blocked_unverified', (int) $user['id'], ['email' => $email]);
            Response::json(['error' => 'email not verified'], 403);
        }

        if (!empty($user['banned_at'])) {
            SecurityLogger::log('login_blocked_banned', (int) $user['id'], ['email' => $email]);
            Response::json(['error' => 'account banned'], 403);
        }

        if ((int) ($user['require_password_reset'] ?? 0) === 1) {
            // Emitimos un token de reset y lo enviamos por email; el usuario
            // sigue bloqueado hasta que complete /auth/reset-password.
            [$plainToken, $tokenHash, $expiresAt] = self::buildVerificationToken();
            User::createPasswordResetToken((int) $user['id'], $tokenHash, $expiresAt);
            $resetUrl = self::buildPasswordResetUrl($plainToken);
            $sent = MailService::sendPasswordResetEmail((string) $user['email'], (string) $user['name'], $resetUrl);
            SecurityLogger::log('login_blocked_reset_required', (int) $user['id']);
            if (!$sent) {
                Response::json(['error' => 'could not send password reset email'], 500);
            }
            Response::json(['error' => 'password reset required'], 403);
        }

        RateLimiter::resetFailure('login_lockout', $normalizedEmail);
        $sid = User::rotateSession((int) $user['id']);
        $token = JwtService::generate($user, $sid);
        SecurityLogger::log('login_success', (int) $user['id']);

        self::handleLoginLocation((int) $user['id'], Security::getClientIp(), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

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

            $sid = User::rotateSession((int) $freshUser['id']);
            $jwt = JwtService::generate($freshUser, $sid);
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
            User::setRequirePasswordReset((int) $user['id'], false);
            SecurityLogger::log('password_reset_completed', (int) $user['id']);
            self::respondWithRotatedSession((int) $user['id'], 'password updated');
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
        $currentPassword = (string) ($data['current_password'] ?? '');
        RateLimiter::enforce('request_email_change', Security::getClientIp() . '|' . $userId, 5, 900);

        // Exigimos re-auth con la contraseña para mitigar takeover con JWT robado:
        // sin esto, un JWT robado bastaría para cambiar email y luego password.
        if ($currentPassword === '') {
            Response::json(['error' => 'current_password is required'], 422);
        }

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'invalid email'], 422);
        }

        $currentUser = User::findById($userId);
        if (!$currentUser) {
            Response::json(['error' => 'user not found'], 404);
        }

        if (!password_verify($currentPassword, (string) $currentUser['password'])) {
            SecurityLogger::log('email_change_bad_password', $userId);
            Response::json(['error' => 'current password is incorrect'], 401);
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

            $sid = User::rotateSession((int) $freshUser['id']);
            $jwt = JwtService::generate($freshUser, $sid);
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
            self::respondWithRotatedSession($userId, 'password updated');
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
                    'dni' => $user['dni'] ?? null,
                    'phone' => $user['phone'] ?? null,
                    'name' => $user['name'] ?? null,
                    'role' => $user['role'] ?? null,
                    'email' => $user['email'] ?? null,
                    'is_email_verified' => (int) ($user['is_email_verified'] ?? 0),
                    'email_verified_at' => $user['email_verified_at'] ?? null,
                    'banned_at' => $user['banned_at'] ?? null,
                    'banned_by' => isset($user['banned_by']) ? (int) $user['banned_by'] : null,
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

    public static function adminRegister(): void
    {
        self::requireAdmin();

        $data = self::getJsonInput();
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $dni = trim((string) ($data['dni'] ?? ''));
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $role = trim((string) ($data['role'] ?? 'user'));

        if ($name === '' || $email === '' || $dni === '' || $password === '' || $username === '') {
            Response::json(['error' => 'name, username, email, dni and password are required'], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'invalid email'], 422);
        }

        if (strlen($password) < 6) {
            Response::json(['error' => 'password must be at least 6 characters'], 422);
        }

        if (User::findByEmail($email)) {
            Response::json(['error' => 'email already exists'], 409);
        }

        if (User::findByDni($dni)) {
            Response::json(['error' => 'DNI already exists'], 409);
        }

        if (User::findByUsername($username)) {
            Response::json(['error' => 'username already exists'], 409);
        }

        try {
            $userId = User::create(
                $username,
                $email,
                password_hash($password, PASSWORD_BCRYPT),
                $name,
                '', // firstName
                '', // lastName
                $dni,
                '', // phone
                $role,
                1   // is_email_verified
            );

            SecurityLogger::log('admin_registered_user', $userId);

            $newUser = User::findById($userId);
            Response::json([
                'message' => 'user created by admin',
                'user' => self::mapUser($newUser),
            ], 201);
        } catch (Throwable $e) {
            Response::json(['error' => 'could not create user: ' . $e->getMessage()], 500);
        }
    }

    public static function adminDeleteUser(): void
    {
        self::requireAdmin();

        $data = self::getJsonInput();
        $userId = (int) ($data['user_id'] ?? 0);

        if ($userId <= 0) {
            Response::json(['error' => 'user_id is required'], 422);
        }

        // Check active loans
        if (self::hasActiveLoans($userId)) {
            Response::json(['error' => 'No se puede eliminar al usuario porque tiene préstamos activos sin devolver.'], 403);
        }

        try {
            User::delete($userId);
            SecurityLogger::log('admin_deleted_user', $userId);
            Response::json(['message' => 'user deleted']);
        } catch (Throwable $e) {
            Response::json(['error' => 'could not delete user'], 500);
        }
    }

    public static function deleteMe(): void
    {
        $session = AuthMiddleware::handle();
        $userId = (int) ($session['sub'] ?? 0);

        // Check active loans
        if (self::hasActiveLoans($userId)) {
            Response::json(['error' => 'No puedes darte de baja mientras tengas préstamos activos sin devolver.'], 403);
        }

        try {
            User::delete($userId);
            SecurityLogger::log('user_deleted_self', $userId);
            Response::json(['message' => 'account deleted']);
        } catch (Throwable $e) {
            Response::json(['error' => 'could not delete account'], 500);
        }
    }

    private static function hasActiveLoans(int $userId): bool
    {
        $url = "http://localhost:8080/libros_api.php?action=count_active_loans&usuario_id=" . $userId;
        $response = @file_get_contents($url);
        if ($response === false) {
            return false; 
        }
        $data = json_decode($response, true);
        return isset($data['count']) && $data['count'] > 0;
    }

    public static function logout(): void
    {
        $token = AuthMiddleware::extractBearerToken();

        if ($token === null) {
            Response::json(['error' => 'unauthorized'], 401);
        }

        // Verificar firma ANTES de tocar la BBDD para no saturar revoked_tokens
        // con bearers basura (DoS lento que degrada cada request autenticada).
        try {
            $decoded = JwtService::verify($token);
        } catch (Throwable $e) {
            SecurityLogger::log('logout_invalid_token', null);
            Response::json(['error' => 'invalid token'], 401);
        }

        RateLimiter::enforce('logout', Security::getClientIp(), 30, 60);

        try {
            // Solo persistimos el hash (no el JWT en plano) + limpiamos el sid
            // para que el middleware rechace cualquier token del usuario.
            $tokenHash = hash('sha256', $token);
            $db = Database::connect();
            $stmt = $db->prepare('INSERT INTO revoked_tokens(token_hash) VALUES(?)');
            $stmt->execute([$tokenHash]);
            $userId = isset($decoded['sub']) ? (int) $decoded['sub'] : 0;
            if ($userId > 0) {
                User::clearSession($userId);
            }
            SecurityLogger::log('logout', $userId > 0 ? $userId : null);
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

    /**
     * Para updates de perfil (username/name/phone): conserva el sid actual, emite JWT fresh.
     * Si el admin hizo force-logout mientras el usuario editaba, current_session_id será NULL
     * y devolvemos 401 en vez de emitir un token sin sid.
     */
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

        $currentSid = $user['current_session_id'] ?? null;
        if (empty($currentSid)) {
            Response::json(['error' => 'session expired'], 401);
        }

        $token = JwtService::generate($user, (string) $currentSid);

        Response::json([
            'message' => $message,
            'token' => $token,
            'user' => self::mapUser($user),
        ]);
    }

    /**
     * Para flujos que inician sesión nueva (change-password, reset-password):
     * rota el sid y emite JWT con el sid nuevo. Los otros dispositivos caen
     * en el middleware al no coincidir su sid.
     */
    private static function respondWithRotatedSession(int $userId, string $message): void
    {
        $user = User::findById($userId);
        if (!$user) {
            Response::json(['error' => 'user not found'], 404);
        }

        $sid = User::rotateSession($userId);
        $token = JwtService::generate($user, $sid);

        Response::json([
            'message' => $message,
            'token' => $token,
            'user' => self::mapUser($user),
        ]);
    }

    private static function minMapUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'username' => $user['username'] ?? null,
            'email' => $user['email'] ?? null,
            'name' => $user['name'] ?? null,
            'role' => $user['role'] ?? null,
        ];
    }

    private static function mapUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'username' => $user['username'] ?? null,
            'first_name' => $user['first_name'] ?? null,
            'last_name' => $user['last_name'] ?? null,
            'dni' => $user['dni'] ?? null,
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

    /**
     * Helper compartido por force-logout y set-ban: valida admin + re-auth con
     * su password + user_id objetivo distinto del propio admin. Devuelve el
     * adminId, el targetUser y el payload JSON para que el caller lea campos
     * adicionales (ej. 'banned' en adminSetBan) sin releer php://input.
     *
     * @return array{adminId: int, targetUser: array, data: array}
     */
    private static function requireAdminForUserMutation(): array
    {
        $session = self::requireAdmin();
        $adminId = (int) ($session['sub'] ?? 0);
        RateLimiter::enforce('admin_mutate', (string) $adminId, 30, 60);

        $data = self::getJsonInput();
        $userId = (int) ($data['user_id'] ?? 0);
        $currentPassword = (string) ($data['current_password'] ?? '');

        if ($userId <= 0) {
            Response::json(['error' => 'user_id is required'], 422);
        }
        if ($currentPassword === '') {
            Response::json(['error' => 'current_password is required'], 422);
        }
        if ($userId === $adminId) {
            Response::json(['error' => 'cannot target self'], 422);
        }

        $adminUser = User::findById($adminId);
        if (!$adminUser || !password_verify($currentPassword, (string) $adminUser['password'])) {
            SecurityLogger::log('admin_mutation_bad_password', $adminId, ['target' => $userId]);
            Response::json(['error' => 'current password is incorrect'], 401);
        }

        $targetUser = User::findById($userId);
        if (!$targetUser) {
            Response::json(['error' => 'user not found'], 404);
        }

        return ['adminId' => $adminId, 'targetUser' => $targetUser, 'data' => $data];
    }

    public static function adminForceLogout(): void
    {
        ['adminId' => $adminId, 'targetUser' => $targetUser] = self::requireAdminForUserMutation();
        $userId = (int) $targetUser['id'];
        try {
            User::invalidateSessions($userId);
            User::clearSession($userId);
            SecurityLogger::log('admin_forced_logout', $userId, ['by_admin' => $adminId]);
            Response::json(['message' => 'sessions invalidated']);
        } catch (Throwable $e) {
            Response::json(['error' => 'could not force logout'], 500);
        }
    }

    public static function adminSetBan(): void
    {
        ['adminId' => $adminId, 'targetUser' => $targetUser, 'data' => $data] = self::requireAdminForUserMutation();
        $userId = (int) $targetUser['id'];

        if (!array_key_exists('banned', $data)) {
            Response::json(['error' => 'banned flag is required'], 422);
        }
        $banned = (bool) $data['banned'];

        try {
            if ($banned) {
                User::banUser($userId, $adminId);
                SecurityLogger::log('admin_banned_user', $userId, ['by_admin' => $adminId]);
                Response::json(['message' => 'user banned']);
            } else {
                User::unbanUser($userId);
                SecurityLogger::log('admin_unbanned_user', $userId, ['by_admin' => $adminId]);
                Response::json(['message' => 'user unbanned']);
            }
        } catch (Throwable $e) {
            Response::json(['error' => 'could not update ban state'], 500);
        }
    }

    /**
     * Evalúa si el login viene de un país distinto al último legítimo y, si
     * procede, dispara un email de alerta con dos botones. Todo envuelto en
     * try/catch: el JWT ya fue emitido al usuario, y cualquier Response::json
     * de error aquí mataría el script. La alerta es best-effort.
     */
    private static function handleLoginLocation(int $userId, string $ip, string $userAgent): void
    {
        try {
            $geo = GeoLocationService::lookup($ip);
            $countryCode = $geo['country_code'] ?? null;
            $countryName = $geo['country_name'] ?? null;

            $lastLegitCountry = User::getLastLegitLoginCountry($userId);

            $isNewCountry = $countryCode !== null
                         && $lastLegitCountry !== null
                         && $countryCode !== $lastLegitCountry;

            if (!$isNewCountry) {
                User::recordLoginLocation($userId, $ip, $countryCode, $countryName, $userAgent, 'neutral');
                return;
            }

            $alertUrlBase = self::resolveLoginAlertBaseUrl();
            if ($alertUrlBase === null) {
                error_log('LOGIN_ALERT_URL_BASE missing, falling back to neutral');
                User::recordLoginLocation($userId, $ip, $countryCode, $countryName, $userAgent, 'neutral');
                return;
            }

            [$plainToken, $tokenHash, $expiresAt] = self::buildLoginAlertToken();
            User::recordLoginLocation(
                $userId, $ip, $countryCode, $countryName, $userAgent,
                'pending', $tokenHash, $expiresAt
            );

            $user = User::findById($userId);
            if (!$user) return;
            $yesUrl = self::appendQuery($alertUrlBase, ['token' => $plainToken, 'decision' => 'me']);
            $noUrl  = self::appendQuery($alertUrlBase, ['token' => $plainToken, 'decision' => 'not-me']);
            MailService::sendLoginAlert($user, $countryName, $ip, $yesUrl, $noUrl);
            SecurityLogger::log('login_alert_sent', $userId, ['country' => $countryCode, 'ip' => $ip]);
        } catch (Throwable $e) {
            error_log('LOGIN_ALERT_FAIL user=' . $userId . ' message=' . $e->getMessage());
        }
    }

    private static function buildLoginAlertToken(): array
    {
        $ttlSeconds = 7 * 24 * 3600;
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
        return [$plainToken, $tokenHash, $expiresAt];
    }

    /**
     * URL base de las páginas de confirmación de alerta. Apunta al FRONTEND
     * (BibliotecaTerror), que recibe el clic del email y hace el POST al
     * backend. Así la UI es consistente con el resto del portal.
     */
    private static function resolveLoginAlertBaseUrl(): ?string
    {
        $base = (string) (getenv('LOGIN_ALERT_FRONTEND_URL_BASE') ?: '');
        if ($base !== '') return $base;
        $appEnv = strtolower((string) (getenv('APP_ENV') ?: 'local'));
        return $appEnv === 'local' ? 'http://localhost:5173/confirmar-acceso' : null;
    }

    private static function appendQuery(string $url, array $params): string
    {
        $sep = str_contains($url, '?') ? '&' : '?';
        return $url . $sep . http_build_query($params);
    }

    /**
     * Endpoint público JSON que procesa la decisión del usuario sobre una
     * alerta de login. La invoca la SPA de BibliotecaTerror tras abrir el link
     * del email. Respuesta siempre 200 con un campo `state`
     * (confirmed|rejected|expired|invalid).
     */
    public static function confirmLoginLocation(): void
    {
        $data = self::getJsonInput();
        $token = trim((string) ($data['token'] ?? ''));
        $decision = trim((string) ($data['decision'] ?? ''));

        if ($token === '' || !in_array($decision, ['me', 'not-me'], true)) {
            Response::json(['state' => 'invalid']);
        }

        $tokenHash = hash('sha256', $token);
        $location = User::findLoginLocationByTokenHash($tokenHash);

        $expired = $location
            && (
                $location['status'] !== 'pending'
                || $location['token_used_at'] !== null
                || strtotime((string) ($location['token_expires_at'] ?? '')) < time()
            );

        if (!$location || $expired) {
            Response::json(['state' => 'expired']);
        }

        $userId = (int) $location['user_id'];

        if ($decision === 'me') {
            User::updateLoginLocationStatus((int) $location['id'], 'confirmed');
            SecurityLogger::log('login_location_confirmed', $userId);
            Response::json(['state' => 'confirmed']);
        }

        User::updateLoginLocationStatus((int) $location['id'], 'rejected');
        User::clearSession($userId);
        User::setRequirePasswordReset($userId, true);
        SecurityLogger::log('login_location_rejected', $userId, [
            'ip' => $location['ip'], 'country' => $location['country_code'],
        ]);
        Response::json(['state' => 'rejected']);
    }

}
