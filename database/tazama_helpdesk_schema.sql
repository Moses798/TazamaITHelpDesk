-- ============================================================================
-- TazamaDesk (TazamaITHelpDesk) — schema
-- Source: https://github.com/Moses798/TazamaITHelpDesk
--   data/seed.json / data/store.json were a flat JSON document (accounts,
--   agents, departments, categories, priorities, statuses, kb_articles,
--   tickets[].history[]). This normalizes that into relational tables.
-- Target: MySQL 8 / MariaDB 10.5+ (InnoDB, utf8mb4). Swap AUTO_INCREMENT ->
--   SERIAL / GENERATED ALWAYS AS IDENTITY and ENUM -> CHECK constraints for
--   PostgreSQL if needed.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS ticket_events;
DROP TABLE IF EXISTS tickets;
DROP TABLE IF EXISTS kb_articles;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS statuses;
DROP TABLE IF EXISTS priorities;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS departments;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- Lookup tables (seed.json: departments[], categories[], priorities{}, statuses[])
-- ----------------------------------------------------------------------------

CREATE TABLE departments (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name  VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name  VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE priorities (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(20) NOT NULL UNIQUE,       -- Critical / High / Medium / Low
  sla_hours   INT UNSIGNED NOT NULL,             -- SLA window, e.g. Critical = 2h
  sort_order  INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE statuses (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(20) NOT NULL UNIQUE,       -- New/Assigned/In Progress/On Hold/Resolved/Closed
  sort_order  INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- People: seed.json accounts[] (login) + agents[] (assignable names) + every
-- ticket requester/assignee/closed_by/history.who collapse into one table.
-- Columns line up with the existing Tazai employee registry
-- (Tazai/database/tazai.db `employees` table: phone_e164, role, authorized)
-- so the two can be reconciled/merged later if TazamaDesk and Tazai share a
-- user base.
-- ----------------------------------------------------------------------------

CREATE TABLE users (
  username       VARCHAR(100) NOT NULL PRIMARY KEY,
  full_name      VARCHAR(150) NOT NULL,
  password_hash  VARCHAR(255) NULL,              -- bcrypt/argon2; NULL for agents w/o login
  role           ENUM('employee','agent','admin') NOT NULL DEFAULT 'employee',
  title          VARCHAR(100) NULL,               -- e.g. "Finance Dept.", "IT Administrator"
  department_id  INT UNSIGNED NULL,
  phone_e164     VARCHAR(20) NULL,
  authorized     TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Knowledge base (seed.json kb_articles[])
-- ----------------------------------------------------------------------------

CREATE TABLE kb_articles (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(255) NOT NULL,
  category_id  INT UNSIGNED NOT NULL,
  views        INT UNSIGNED NOT NULL DEFAULT 0,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_kb_category FOREIGN KEY (category_id) REFERENCES categories(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Tickets (seed.json tickets[], minus history[] and created_offset_h/
-- closed_offset_h which become real timestamps here instead of "hours ago").
-- id is NOT auto-increment-only-from-1: the seed data uses real-looking IDs
-- (4821, 4820, ...), so AUTO_INCREMENT is seeded to continue after the max.
-- ----------------------------------------------------------------------------

CREATE TABLE tickets (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject           VARCHAR(255) NOT NULL,
  department_id     INT UNSIGNED NULL,
  category_id       INT UNSIGNED NOT NULL,
  priority_id       INT UNSIGNED NOT NULL,
  status_id         INT UNSIGNED NOT NULL,
  requester_username VARCHAR(100) NOT NULL,
  assignee_username VARCHAR(100) NULL,
  description       TEXT NOT NULL,
  created_at        DATETIME NOT NULL,
  closed_at         DATETIME NULL,
  closed_by_username VARCHAR(100) NULL,
  resolution_note   TEXT NULL,
  source            VARCHAR(50) NULL,
  phone             VARCHAR(30) NULL,
  internal_notes    JSON NULL,
  CONSTRAINT fk_tickets_department FOREIGN KEY (department_id) REFERENCES departments(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_tickets_category   FOREIGN KEY (category_id)   REFERENCES categories(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_tickets_priority   FOREIGN KEY (priority_id)   REFERENCES priorities(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_tickets_status     FOREIGN KEY (status_id)     REFERENCES statuses(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_tickets_requester  FOREIGN KEY (requester_username)  REFERENCES users(username)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_tickets_assignee   FOREIGN KEY (assignee_username)   REFERENCES users(username)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_tickets_closed_by  FOREIGN KEY (closed_by_username)  REFERENCES users(username)
    ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_tickets_status (status_id),
  INDEX idx_tickets_priority (priority_id),
  INDEX idx_tickets_assignee (assignee_username),
  INDEX idx_tickets_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Ticket activity feed (seed.json tickets[].history[]). Unifies system events
-- ("picked up this ticket", "closed this ticket") and employee chat messages
-- (is_message=1, seen_by_admin) into one append-only timeline per ticket.
-- ----------------------------------------------------------------------------

CREATE TABLE ticket_events (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id      INT UNSIGNED NOT NULL,
  username       VARCHAR(100) NOT NULL,
  actor_role     ENUM('employee','agent','admin','system') NOT NULL,
  action         VARCHAR(255) NOT NULL,           -- e.g. "created this ticket", "sent a message"
  note           TEXT NULL,                       -- history[].note or history[].text
  is_message     TINYINT(1) NOT NULL DEFAULT 0,
  seen_by_admin  TINYINT(1) NULL,                 -- only meaningful when is_message = 1
  seen_by_requester TINYINT(1) NULL,              -- only meaningful when is_message = 1
  created_at     DATETIME NOT NULL,
  CONSTRAINT fk_events_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_events_user   FOREIGN KEY (username)   REFERENCES users(username )
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_events_ticket (ticket_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- Seed data (converted 1:1 from data/seed.json)
-- ============================================================================

INSERT INTO departments (id, name) VALUES
  (1, 'Administration'),
  (2, 'Finance'),
  (3, 'Human Resources'),
  (4, 'Security'),
  (5, 'Operations'),
  (6, 'Maintenance'),
  (7, 'Boomgate'),
  (8, 'Commercial'),
  (9, 'Dispatch');

INSERT INTO categories (id, name) VALUES
  (1, 'Hardware'),
  (2, 'Software'),
  (3, 'Network'),
  (4, 'Account Access'),
  (5, 'Email'),
  (6, 'Printer'),
  (7, 'Security');

INSERT INTO priorities (id, name, sla_hours, sort_order) VALUES
  (1, 'Critical', 2, 1),
  (2, 'High', 8, 2),
  (3, 'Medium', 24, 3),
  (4, 'Low', 72, 4);

INSERT INTO statuses (id, name, sort_order) VALUES
  (1, 'New', 1),
  (2, 'Assigned', 2),
  (3, 'In Progress', 3),
  (4, 'On Hold', 4),
  (5, 'Resolved', 5),
  (6, 'Closed', 6);

-- NOTE: seed passwords are the demo app's plaintext values (employee123/admin123).
-- password_hash below wraps them with a placeholder marker — replace with a real
-- bcrypt/argon2 hash (e.g. PHP password_hash()) before using this schema anywhere real.
INSERT INTO users (username, full_name, password_hash, role, title) VALUES
  ('Namakau', 'Namakau Mulenga', 'Tazama123', 'employee', 'Finance Dept.'),
  ('Shadrick', 'Shadrick Bilali', 'Tazama123', 'admin', 'IT administrator'),
  ('Hendrix', 'Hendrix Kulumba', 'Tazama123', 'employee', 'operations'),
  ('Chileshe', 'Chileshe Chileshe', 'Tazama123', 'admin', 'IT Administrator'),
  ('Moses', 'Moses Chola', 'Tazama123', 'admin123', 'IT Administrator'),
  ('Kumwenda', 'Kumwenda Musonda', 'Tazama123', 'employee', 'Dispatch Coordinator');

INSERT INTO kb_articles (username, title, category_id, views) VALUES
  ('angela.cruz', 'How to reset your network password', 4, 1204),
  ('brian.tembo', 'Connecting to the office VPN', 3, 982),
  ('chanda.mwape', 'Setting up email on your phone', 5, 875),
  ('Chileshe', 'Requesting new hardware', 1, 640),
  ('chris.chipopola', 'Reporting a phishing email', 7, 1310),
  ('david.mwale', 'Fixing common printer jams', 6, 512);

INSERT INTO tickets (username, subject, department_id, category_id, priority_id, status_id,
                     requester_id, assignee_id, description, created_at, closed_at,
                     closed_by_id, resolution_note) VALUES
ALTER TABLE tickets AUTO_INCREMENT = 4822;
