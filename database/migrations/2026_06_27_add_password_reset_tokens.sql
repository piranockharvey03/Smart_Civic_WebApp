<?php

declare(strict_types=1);

CREATE TABLE IF NOT EXISTS password_reset_tokens
(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_password_reset_tokens_user_id (user_id),
    KEY idx_password_reset_tokens_token_hash (token_hash),
    CONSTRAINT fk_password_reset_tokens_users
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB;
