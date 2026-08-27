-- Run this once on an existing installation. The old numeric retest values will now be treated as months.
ALTER TABLE assets MODIFY asset_category ENUM('MINSUP','HPW','VAC','OTHER') NOT NULL;
ALTER TABLE assets MODIFY asset_retest_span INT UNSIGNED NOT NULL DEFAULT 12 COMMENT 'Retest interval in months';
CREATE TABLE IF NOT EXISTS asset_files (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, asset_id BIGINT UNSIGNED NOT NULL, uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 file_location VARCHAR(1024) NOT NULL, original_filename VARCHAR(255) NOT NULL, test_date DATE NULL,
 PRIMARY KEY(id), KEY idx_asset_files_asset_id(asset_id), CONSTRAINT fk_asset_files_asset FOREIGN KEY(asset_id) REFERENCES assets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE asset_files ADD COLUMN IF NOT EXISTS test_date DATE NULL AFTER original_filename;
INSERT INTO asset_files(asset_id,file_location,original_filename)
 SELECT id,file_location,original_filename FROM assets WHERE file_location IS NOT NULL AND file_location<>'' AND NOT EXISTS(SELECT 1 FROM asset_files f WHERE f.asset_id=assets.id);
UPDATE asset_files f JOIN assets a ON a.id=f.asset_id SET f.test_date=a.asset_test_date WHERE f.test_date IS NULL;
