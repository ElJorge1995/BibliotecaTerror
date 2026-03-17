ALTER TABLE users
  ADD COLUMN is_email_verified TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN email_verified_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS email_verification_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_email_verification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_email_verification_user ON email_verification_tokens (user_id);
CREATE INDEX idx_email_verification_expiry ON email_verification_tokens (expires_at);
