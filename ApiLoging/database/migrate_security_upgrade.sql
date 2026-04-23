-- Migración combinada: alinea el schema con la versión actual de ApiLoging
-- (a partir de abril 2026). Cubre seguridad v2, ban, single-session y geo alerts.
--
-- Cambios:
--   1. users.password_changed_at — invalida JWTs tras cambio de contraseña.
--   2. revoked_tokens.token_hash  — lookup O(1) por hash en vez del token en plano.
--   3. users.banned_at/banned_by   — feature de ban administrativo.
--   4. users.sessions_invalidated_at — force-logout admin.
--   5. users.current_session_id   — single-session enforcement (claim sid JWT).
--   6. users.require_password_reset — obliga cambio de password tras login_location rechazado.
--   7. login_locations             — registro de logins + tokens para alertas geo.
--
-- Todos los ALTER usan IF NOT EXISTS para ser idempotentes (MariaDB >=10.3).

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL AFTER password,
  ADD COLUMN IF NOT EXISTS banned_at DATETIME NULL AFTER is_email_verified,
  ADD COLUMN IF NOT EXISTS banned_by INT NULL AFTER banned_at,
  ADD COLUMN IF NOT EXISTS sessions_invalidated_at DATETIME NULL AFTER banned_by,
  ADD COLUMN IF NOT EXISTS current_session_id CHAR(64) NULL AFTER sessions_invalidated_at,
  ADD COLUMN IF NOT EXISTS require_password_reset TINYINT(1) NOT NULL DEFAULT 0 AFTER current_session_id;

-- FK banned_by -> users.id (se crea solo si no existe — nombre único descriptivo)
SET @fk_exists := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                   WHERE CONSTRAINT_SCHEMA = DATABASE()
                     AND TABLE_NAME = 'users'
                     AND CONSTRAINT_NAME = 'fk_users_banned_by');
SET @sql := IF(@fk_exists = 0,
               'ALTER TABLE users ADD CONSTRAINT fk_users_banned_by FOREIGN KEY (banned_by) REFERENCES users(id) ON DELETE SET NULL',
               'SELECT ''fk_users_banned_by ya existe'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE INDEX IF NOT EXISTS idx_users_banned_at ON users (banned_at);
CREATE INDEX IF NOT EXISTS idx_users_current_session_id ON users (current_session_id);

-- revoked_tokens: añade token_hash + relax token a NULL (nuevo logout solo guarda hash)
ALTER TABLE revoked_tokens
  ADD COLUMN IF NOT EXISTS token_hash CHAR(64) NULL AFTER token;

ALTER TABLE revoked_tokens
  MODIFY COLUMN token TEXT NULL;

-- Backfill de hashes para revocaciones antiguas (por si vuelven a ser consultadas).
UPDATE revoked_tokens
SET token_hash = SHA2(token, 256)
WHERE token_hash IS NULL AND token IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_revoked_token_hash ON revoked_tokens (token_hash);

-- Tabla login_locations: registro de cada login con país + tokens de confirmación.
CREATE TABLE IF NOT EXISTS login_locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  ip VARCHAR(45) NOT NULL,
  country_code CHAR(2) NULL,
  country_name VARCHAR(100) NULL,
  user_agent VARCHAR(512) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'neutral',
  token_hash CHAR(64) NULL,
  token_expires_at DATETIME NULL,
  token_used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_login_locations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_login_locations_user_created ON login_locations (user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_login_locations_token_hash ON login_locations (token_hash);
