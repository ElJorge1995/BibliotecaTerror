<?php

class User
{
    public static function findById(int $id): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function create(
        string $username,
        string $email,
        string $passwordHash,
        string $name,
        string $firstName,
        string $lastName,
        string $phone,
        string $role = 'user'
    ): int
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'INSERT INTO users(username, email, password, name, first_name, last_name, phone, role, is_email_verified)
             VALUES(?, ?, ?, ?, ?, ?, ?, ?, 0)'
        );
        $stmt->execute([$username, $email, $passwordHash, $name, $firstName, $lastName, $phone, $role]);

        return (int) $db->lastInsertId();
    }

    public static function createPendingRegistration(
        string $username,
        string $email,
        string $passwordHash,
        string $name,
        string $firstName,
        string $lastName,
        string $phone,
        string $tokenHash,
        string $expiresAt
    ): int {
        $db = Database::connect();
        $cleanup = $db->prepare('DELETE FROM pending_registrations WHERE email = ? OR username = ?');
        $cleanup->execute([$email, $username]);

        $stmt = $db->prepare(
            'INSERT INTO pending_registrations(username, email, password_hash, name, first_name, last_name, phone, token_hash, expires_at)
             VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$username, $email, $passwordHash, $name, $firstName, $lastName, $phone !== '' ? $phone : null, $tokenHash, $expiresAt]);

        return (int) $db->lastInsertId();
    }

    public static function deletePendingRegistration(int $pendingId): void
    {
        $db = Database::connect();
        $stmt = $db->prepare('DELETE FROM pending_registrations WHERE id = ?');
        $stmt->execute([$pendingId]);
    }

    public static function findPendingRegistrationConflict(string $email, string $username): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT id, email, username
             FROM pending_registrations
             WHERE used_at IS NULL
               AND expires_at > NOW()
               AND (email = ? OR username = ?)
             LIMIT 1'
        );
        $stmt->execute([$email, $username]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findPendingRegistrationByEmail(string $email): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT *
             FROM pending_registrations
             WHERE email = ?
               AND used_at IS NULL
               AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function refreshPendingRegistrationToken(int $pendingId, string $tokenHash, string $expiresAt): void
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'UPDATE pending_registrations
             SET token_hash = ?, expires_at = ?, used_at = NULL
             WHERE id = ?'
        );
        $stmt->execute([$tokenHash, $expiresAt, $pendingId]);
    }

    public static function findPendingRegistrationByTokenHash(string $tokenHash): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT *
             FROM pending_registrations
             WHERE token_hash = ?
               AND used_at IS NULL
               AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function createUserFromPendingRegistration(int $pendingId): array
    {
        $db = Database::connect();
        $db->beginTransaction();

        try {
            $select = $db->prepare('SELECT * FROM pending_registrations WHERE id = ? AND used_at IS NULL LIMIT 1');
            $select->execute([$pendingId]);
            $pending = $select->fetch();

            if (!$pending) {
                throw new RuntimeException('pending registration not found');
            }

            $insert = $db->prepare(
                'INSERT INTO users(username, email, password, name, first_name, last_name, phone, role, is_email_verified, email_verified_at)
                 VALUES(?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())'
            );
            $insert->execute([
                $pending['username'],
                $pending['email'],
                $pending['password_hash'],
                $pending['name'],
                $pending['first_name'],
                $pending['last_name'],
                $pending['phone'],
                'user',
            ]);

            $userId = (int) $db->lastInsertId();

            $markPending = $db->prepare('UPDATE pending_registrations SET used_at = NOW() WHERE id = ?');
            $markPending->execute([$pendingId]);

            $user = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $user->execute([$userId]);
            $freshUser = $user->fetch();

            $db->commit();

            if (!$freshUser) {
                throw new RuntimeException('created user not found');
            }

            return $freshUser;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function updateUsername(int $userId, string $username): void
    {
        $db = Database::connect();
        $stmt = $db->prepare('UPDATE users SET username = ? WHERE id = ?');
        $stmt->execute([$username, $userId]);
    }

    public static function updateRole(int $userId, string $role): void
    {
        $db = Database::connect();
        $stmt = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->execute([$role, $userId]);
    }

    public static function updateName(int $userId, string $firstName, string $lastName): void
    {
        $db = Database::connect();
        $fullName = trim($firstName . ' ' . $lastName);
        $stmt = $db->prepare('UPDATE users SET first_name = ?, last_name = ?, name = ? WHERE id = ?');
        $stmt->execute([$firstName, $lastName, $fullName, $userId]);
    }

    public static function updatePhone(int $userId, string $phone): void
    {
        $db = Database::connect();
        $stmt = $db->prepare('UPDATE users SET phone = ? WHERE id = ?');
        $stmt->execute([$phone, $userId]);
    }

    public static function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $db = Database::connect();
        $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$passwordHash, $userId]);
    }

    public static function createVerificationToken(int $userId, string $tokenHash, string $expiresAt): void
    {
        $db = Database::connect();
        $delete = $db->prepare('DELETE FROM email_verification_tokens WHERE user_id = ?');
        $delete->execute([$userId]);

        $stmt = $db->prepare(
            'INSERT INTO email_verification_tokens(user_id, token_hash, expires_at) VALUES(?, ?, ?)'
        );
        $stmt->execute([$userId, $tokenHash, $expiresAt]);
    }

    public static function createPasswordResetToken(int $userId, string $tokenHash, string $expiresAt): void
    {
        $db = Database::connect();
        $delete = $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
        $delete->execute([$userId]);

        $stmt = $db->prepare(
            'INSERT INTO password_reset_tokens(user_id, token_hash, expires_at) VALUES(?, ?, ?)'
        );
        $stmt->execute([$userId, $tokenHash, $expiresAt]);
    }

    public static function findByVerificationHash(string $tokenHash): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT u.*
             FROM email_verification_tokens evt
             INNER JOIN users u ON u.id = evt.user_id
             WHERE evt.token_hash = ?
               AND evt.used_at IS NULL
               AND evt.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findByPasswordResetHash(string $tokenHash): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT prt.id as reset_id, u.*
             FROM password_reset_tokens prt
             INNER JOIN users u ON u.id = prt.user_id
             WHERE prt.token_hash = ?
               AND prt.used_at IS NULL
               AND prt.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function createEmailChangeToken(int $userId, string $newEmail, string $tokenHash, string $expiresAt): void
    {
        $db = Database::connect();
        $delete = $db->prepare('DELETE FROM email_change_tokens WHERE user_id = ?');
        $delete->execute([$userId]);

        $stmt = $db->prepare(
            'INSERT INTO email_change_tokens(user_id, new_email, token_hash, expires_at) VALUES(?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $newEmail, $tokenHash, $expiresAt]);
    }

    public static function findPendingEmailChange(string $tokenHash): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT ect.id, ect.user_id, ect.new_email, u.name
             FROM email_change_tokens ect
             INNER JOIN users u ON u.id = ect.user_id
             WHERE ect.token_hash = ?
               AND ect.used_at IS NULL
               AND ect.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function applyEmailChange(int $tokenId, int $userId, string $newEmail): void
    {
        $db = Database::connect();
        $db->beginTransaction();

        try {
            $updateUser = $db->prepare(
                'UPDATE users SET email = ?, is_email_verified = 1, email_verified_at = NOW() WHERE id = ?'
            );
            $updateUser->execute([$newEmail, $userId]);

            $markToken = $db->prepare('UPDATE email_change_tokens SET used_at = NOW() WHERE id = ?');
            $markToken->execute([$tokenId]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function markPasswordResetAsUsed(int $resetId): void
    {
        $db = Database::connect();
        $stmt = $db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?');
        $stmt->execute([$resetId]);
    }

    public static function markEmailAsVerified(int $userId): void
    {
        $db = Database::connect();
        $db->beginTransaction();

        try {
            $updateUser = $db->prepare(
                'UPDATE users SET is_email_verified = 1, email_verified_at = NOW() WHERE id = ?'
            );
            $updateUser->execute([$userId]);

            $updateToken = $db->prepare(
                'UPDATE email_verification_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL'
            );
            $updateToken->execute([$userId]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function listAll(): array
    {
        $db = Database::connect();
        $stmt = $db->query(
            'SELECT id, username, email, name, first_name, last_name, phone, role, is_email_verified, email_verified_at, created_at
             FROM users
             ORDER BY created_at DESC, id DESC'
        );

        return $stmt->fetchAll() ?: [];
    }
}
