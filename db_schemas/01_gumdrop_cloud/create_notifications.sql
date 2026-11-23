CREATE TABLE `notifications` (
  `notification_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  -- Things an admin must act on, counted beside the menu's Admin link (mg #851).
  -- Created empty on every site. Each site adds its own rows in a later migration.
  -- Pending migrations are never stored here and can never be completed.
  -- Times are UTC, written from PHP. The database clock is not UTC.
  -- Keep semicolons out of these comments. executeMultipleSQL() splits on them.
  `message` TEXT NOT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `show_from` DATETIME NOT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `completed_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  KEY `completed_show_from` (`completed_at`, `show_from`),
  CONSTRAINT `fk_notifications_completed_by` FOREIGN KEY (`completed_by`)
    REFERENCES `users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
