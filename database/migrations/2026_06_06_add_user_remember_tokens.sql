CREATE TABLE IF NOT EXISTS user_remember_tokens
(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    selector CHAR(32) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_remember_tokens_selector (selector),
    KEY idx_user_remember_tokens_user_id (user_id),
    KEY idx_user_remember_tokens_expires_at (expires_at),
    KEY idx_user_remember_tokens_revoked_at (revoked_at),
    CONSTRAINT fk_user_remember_tokens_users
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;