CREATE TABLE IF NOT EXISTS client_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  client_user_id INT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NULL,
  action VARCHAR(20) NOT NULL,
  photos INT UNSIGNED NOT NULL DEFAULT 0,
  detail VARCHAR(255) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  country CHAR(2) NOT NULL DEFAULT '',
  agent VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  KEY (client_user_id, created_at),
  KEY (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS client_photo_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  client_user_id INT UNSIGNED NOT NULL,
  photo_id INT UNSIGNED NOT NULL,
  kind VARCHAR(10) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY (client_user_id, kind, photo_id),
  KEY (photo_id, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS download_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  client_user_id INT UNSIGNED NOT NULL,
  photos INT UNSIGNED NOT NULL,
  bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  note VARCHAR(500) NOT NULL DEFAULT '',
  status VARCHAR(10) NOT NULL DEFAULT 'pending',
  reply VARCHAR(500) NOT NULL DEFAULT '',
  decided_by INT UNSIGNED NULL,
  decided_at DATETIME NULL,
  expires_at DATETIME NULL,
  downloads INT UNSIGNED NOT NULL DEFAULT 0,
  last_download_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY (status),
  KEY (client_user_id),
  KEY (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS download_request_photos (
  request_id INT UNSIGNED NOT NULL,
  photo_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (request_id, photo_id),
  KEY (photo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE projects
  ADD COLUMN client_approval TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN client_bulk TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN bulk_upload TINYINT(1) NOT NULL DEFAULT 1
