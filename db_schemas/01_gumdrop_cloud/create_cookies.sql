CREATE TABLE `cookies` (
  `cookie_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  -- SHA-256 hex of the remember-me token (64 chars). The plaintext lives only
  -- in the browser cookie. The DB stores the hash, so a dump yields no usable
  -- session tokens. See \Auth\IsLoggedIn::setAutoLoginCookie().
  -- Keep semicolons out of these comments. executeMultipleSQL() splits on them.
  `cookie` CHAR(64) COLLATE utf8mb4_bin NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_access` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  -- When this token stops being accepted. The browser's own expiry is a request
  -- from us, not a rule -- a captured value would otherwise work forever. The
  -- lookup checks this column, so the 30 days is enforced here.
  `expires_at` DATETIME NOT NULL,
  `ip_address` VARBINARY(16) DEFAULT NULL,
  `user_agent_md5` CHAR(32) COLLATE utf8mb4_bin DEFAULT NULL,
  UNIQUE KEY `cookie` (`cookie`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `fk_cookies_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
