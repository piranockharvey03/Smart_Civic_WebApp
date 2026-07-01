USE smart_civic_app;

CREATE TABLE IF NOT EXISTS app_cache (
    cache_key VARCHAR(190) NOT NULL PRIMARY KEY,
    cache_value LONGTEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_app_cache_expires_at (expires_at)
) ENGINE=InnoDB;
