CREATE TABLE IF NOT EXISTS `#__oidc_identities` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `provider_key` VARCHAR(64) NOT NULL,
    `issuer` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `idx_issuer_subject` (`issuer`, `subject`),
    UNIQUE KEY `idx_provider_user` (`provider_key`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
