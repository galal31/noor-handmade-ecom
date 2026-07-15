ALTER TABLE users
    ADD COLUMN verification_expires_at DATETIME NULL AFTER verification_token,
    ADD COLUMN password_reset_token_hash CHAR(64) NULL AFTER verification_expires_at,
    ADD COLUMN password_reset_expires_at DATETIME NULL AFTER password_reset_token_hash,
    ADD INDEX idx_users_verification_token (verification_token);

UPDATE users
SET verification_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
WHERE verification_token IS NOT NULL AND verification_expires_at IS NULL;

CREATE TABLE login_attempts (
    scope VARCHAR(30) NOT NULL,
    client_key CHAR(64) NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    blocked_until DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (scope, client_key),
    KEY idx_login_attempts_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
