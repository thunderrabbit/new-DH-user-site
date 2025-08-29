CREATE TABLE applied_DB_versions (
    applied_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    applied_version VARCHAR(128) NOT NULL,
    direction ENUM('up', 'down') NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
