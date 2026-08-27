-- Omit this statement if role2 already exists.
ALTER TABLE users ADD COLUMN role2 VARCHAR(50) NULL DEFAULT NULL;
CREATE TABLE IF NOT EXISTS assets (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 asset_number VARCHAR(100) NOT NULL,
 asset_category VARCHAR(100) NOT NULL,
 asset_description TEXT NOT NULL,
 asset_test_date DATE NOT NULL,
 asset_retest_span INT UNSIGNED NOT NULL COMMENT 'Retest interval in days',
 file_location VARCHAR(1024) NOT NULL,
 original_filename VARCHAR(255) NOT NULL,
 uploaded_by BIGINT NOT NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_assets_asset_number (asset_number),
 KEY idx_assets_category (asset_category), KEY idx_assets_test_date (asset_test_date),
 KEY idx_assets_uploaded_by (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
