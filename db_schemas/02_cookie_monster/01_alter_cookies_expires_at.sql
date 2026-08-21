-- Adds cookies.expires_at to sites that ran 01_gumdrop_cloud before the column
-- existed. A fresh install already has it from create_cookies.sql, so every
-- statement here is written to be a no-op in that case rather than to fail.
-- Keep semicolons out of these comments. executeMultipleSQL() splits on them.

-- MySQL has no ADD COLUMN IF NOT EXISTS, so ask information_schema first and
-- build the DDL as a string. The string holds no semicolon, which matters
-- because executeMultipleSQL() splits this file on them.
SET @column_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'cookies'
     AND COLUMN_NAME = 'expires_at'
);

SET @ddl := IF(
  @column_exists = 0,
  'ALTER TABLE `cookies` ADD COLUMN `expires_at` DATETIME NULL DEFAULT NULL AFTER `last_access`, ADD KEY `expires_at` (`expires_at`)',
  'DO 0'
);

PREPARE add_expires_at FROM @ddl;
EXECUTE add_expires_at;
DEALLOCATE PREPARE add_expires_at;

-- Existing sessions get the lifetime they were always meant to have, measured
-- from when they were issued. Matches nothing on a fresh install.
UPDATE `cookies`
   SET `expires_at` = `created_at` + INTERVAL 30 DAY
 WHERE `expires_at` IS NULL;

-- Idempotent: re-running MODIFY on a column that is already NOT NULL is a no-op.
ALTER TABLE `cookies` MODIFY `expires_at` DATETIME NOT NULL;
