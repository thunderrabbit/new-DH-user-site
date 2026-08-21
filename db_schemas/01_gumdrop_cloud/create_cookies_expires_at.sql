-- Server-side expiry for remember-me cookies (mg #450). Until this column the
-- 30-day lifetime lived only in the browser, so a captured token worked forever.
-- Named create_* because Database\SchemaPath::resolve() admits only that prefix
-- (it is a traversal guard on a JSON body), and kept in 01 so it is automatic.
-- Existing rows are backfilled from their last activity plus the 30-day default.
-- Keep semicolons out of these comments. executeMultipleSQL() splits on them.
ALTER TABLE `cookies` ADD COLUMN `expires_at` DATETIME NULL AFTER `last_access`;
UPDATE `cookies` SET `expires_at` = DATE_ADD(COALESCE(`last_access`, `created_at`), INTERVAL 30 DAY) WHERE `expires_at` IS NULL;
ALTER TABLE `cookies` MODIFY `expires_at` DATETIME NOT NULL;
ALTER TABLE `cookies` ADD KEY `expires_at` (`expires_at`);
