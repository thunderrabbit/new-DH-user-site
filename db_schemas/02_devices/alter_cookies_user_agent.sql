-- The readable User-Agent, so /profile/devices/ can say "iPhone, Safari"
-- instead of a hash. user_agent_md5 stays: it is what the lookup matches on.
-- Rows issued before this column existed stay NULL and show as an unknown device.
-- Keep semicolons out of these comments. executeMultipleSQL() splits on them.
ALTER TABLE `cookies`
  ADD COLUMN `user_agent` VARCHAR(255) COLLATE utf8mb4_bin DEFAULT NULL AFTER `user_agent_md5`;
