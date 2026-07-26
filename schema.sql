-- D1 schema for Last Fall Back Act signups.
-- Apply:  wrangler d1 execute lastfallback-signups --file=schema.sql --remote
CREATE TABLE IF NOT EXISTS signups (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  created_at    TEXT    NOT NULL,
  first_name    TEXT    NOT NULL,
  last_name     TEXT    NOT NULL,
  email         TEXT    NOT NULL,
  city          TEXT,
  wa_voter      INTEGER NOT NULL DEFAULT 0,
  wants_updates INTEGER NOT NULL DEFAULT 0,
  volunteer     INTEGER NOT NULL DEFAULT 0,
  ip            TEXT
);
CREATE INDEX IF NOT EXISTS idx_signups_email      ON signups (email);
CREATE INDEX IF NOT EXISTS idx_signups_created_at ON signups (created_at);
