ALTER TABLE projects
  ADD COLUMN reward_amount DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER daily_limit,
  ADD COLUMN reward_photos INT UNSIGNED NOT NULL DEFAULT 5 AFTER reward_amount,
  ADD COLUMN reward_symbol VARCHAR(8) NOT NULL DEFAULT '$' AFTER reward_photos;

ALTER TABLE projects ALTER photos_per_person SET DEFAULT 0;

UPDATE projects SET photos_per_person = 0, reward_amount = 1, reward_photos = 5, reward_symbol = '$'
