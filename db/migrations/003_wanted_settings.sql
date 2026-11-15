ALTER TABLE projects ADD COLUMN wanted TEXT NULL AFTER intro;

UPDATE projects SET wanted = 'Portraits and human activities\nOutdoor landscapes\nIndoor environments\nAnimals and pets\nFood and dining\nStill life\nMacro and texture photography' WHERE wanted IS NULL;

CREATE TABLE settings (
  name VARCHAR(80) NOT NULL PRIMARY KEY,
  value MEDIUMTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
