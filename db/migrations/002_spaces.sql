ALTER TABLE photos
  ADD COLUMN store ENUM('local','spaces') NOT NULL DEFAULT 'local' AFTER token,
  ADD COLUMN store_key VARCHAR(255) NOT NULL DEFAULT '' AFTER store
