ALTER TABLE photos ADD COLUMN notified_at DATETIME NULL AFTER reviewed_at;

UPDATE photos SET notified_at = COALESCE(reviewed_at, created_at) WHERE status = 'approved'
