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
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name      VARCHAR(150) NOT NULL,
  email          VARCHAR(190) NULL UNIQUE,
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
  requester_id      INT UNSIGNED NOT NULL,
  assignee_id       INT UNSIGNED NULL,
  description       TEXT NOT NULL,
  created_at        DATETIME NOT NULL,
  closed_at         DATETIME NULL,
  closed_by_id      INT UNSIGNED NULL,
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
  CONSTRAINT fk_tickets_requester  FOREIGN KEY (requester_id)  REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_tickets_assignee   FOREIGN KEY (assignee_id)   REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_tickets_closed_by  FOREIGN KEY (closed_by_id)  REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_tickets_status (status_id),
  INDEX idx_tickets_priority (priority_id),
  INDEX idx_tickets_assignee (assignee_id),
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
  user_id        INT UNSIGNED NOT NULL,
  actor_role     ENUM('employee','agent','admin','system') NOT NULL,
  action         VARCHAR(255) NOT NULL,           -- e.g. "created this ticket", "sent a message"
  note           TEXT NULL,                       -- history[].note or history[].text
  is_message     TINYINT(1) NOT NULL DEFAULT 0,
  seen_by_admin  TINYINT(1) NULL,                 -- only meaningful when is_message = 1
  seen_by_requester TINYINT(1) NULL,              -- only meaningful when is_message = 1
  created_at     DATETIME NOT NULL,
  CONSTRAINT fk_events_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_events_user   FOREIGN KEY (user_id)   REFERENCES users(id)
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
INSERT INTO users (id, full_name, email, password_hash, role, title) VALUES
  (1, 'Angela Cruz', 'Chileshe.Chileshe@tazamadesk.com', 'PLAINTEXT-DEMO:employee123', 'employee', 'Finance Dept.'),
  (2, 'Brian Tembo', NULL, NULL, 'employee', NULL),
  (3, 'Chanda Mwape', NULL, NULL, 'employee', NULL),
  (4, 'Chileshe Chileshe', NULL, NULL, 'agent', NULL),
  (5, 'Chris Chipopola', NULL, NULL, 'agent', NULL),
  (6, 'Daniel Kim', NULL, NULL, 'admin', NULL),
  (7, 'Esther Mwila', NULL, NULL, 'employee', NULL),
  (8, 'Fatima Osei', NULL, NULL, 'admin', NULL),
  (9, 'Given Mulenga', NULL, NULL, 'employee', NULL),
  (10, 'Grace Mumba', NULL, NULL, 'employee', NULL),
  (11, 'James Banda', NULL, NULL, 'employee', NULL),
  (12, 'Kondwani Phiri', NULL, NULL, 'employee', NULL),
  (13, 'Marcus Webb', 'moses.Chola@tazamadesk.com', 'PLAINTEXT-DEMO:admin123', 'admin', 'IT Administrator'),
  (14, 'Moses Chola', NULL, NULL, 'agent', NULL),
  (15, 'Mwansa Chola', NULL, NULL, 'employee', NULL),
  (16, 'Natasha kunda', NULL, NULL, 'agent', NULL),
  (17, 'Natasha Zulu', NULL, NULL, 'employee', NULL),
  (18, 'Peter Sinkala', NULL, NULL, 'employee', NULL),
  (19, 'Priya Nandy', NULL, NULL, 'admin', NULL),
  (20, 'Ruth Chanda', NULL, NULL, 'employee', NULL),
  (21, 'Shadrick Bilali', NULL, NULL, 'agent', NULL);

INSERT INTO kb_articles (id, title, category_id, views) VALUES
  (1, 'How to reset your network password', 4, 1204),
  (2, 'Connecting to the office VPN', 3, 982),
  (3, 'Setting up email on your phone', 5, 875),
  (4, 'Requesting new hardware', 1, 640),
  (5, 'Reporting a phishing email', 7, 1310),
  (6, 'Fixing common printer jams', 6, 512);

INSERT INTO tickets (id, subject, department_id, category_id, priority_id, status_id,
                     requester_id, assignee_id, description, created_at, closed_at,
                     closed_by_id, resolution_note) VALUES
  (4821, 'Outlook not syncing since this morning', 2, 5, 2, 3, 1, 13, 'Emails sent after 8am are not appearing in Sent folder. Rebooted twice, still failing to sync across devices.', '2026-09-22 04:46:57', NULL, NULL, NULL),
  (4820, 'Laptop won''t power on after update', NULL, 1, 1, 1, 2, NULL, 'Dell Latitude froze during a Windows update overnight and now shows a black screen with no response to power button.', '2026-09-22 07:28:57', NULL, NULL, NULL),
  (4819, 'Cannot access shared Finance drive', 2, 4, 2, 2, 10, 13, 'Getting ''access denied'' on \\fileserver\finance since permissions were updated yesterday.', '2026-09-22 02:16:57', NULL, NULL, NULL),
  (4818, 'VPN drops every 10 minutes remotely', 5, 3, 3, 3, 12, 6, 'Working from home, VPN client disconnects repeatedly, forcing re-authentication throughout the day.', '2026-09-21 18:16:57', NULL, NULL, NULL),
  (4817, 'New starter needs full account setup', 3, 4, 3, 1, 17, NULL, 'New hire starting Monday needs AD account, email, Slack, and Salesforce access provisioned.', '2026-09-22 06:16:57', NULL, NULL, NULL),
  (4816, 'Printer on 3rd floor jamming constantly', 5, 6, 4, 4, 11, 8, 'HP LaserJet on the 3rd floor jams roughly every 5th print job, mostly on double-sided jobs. Awaiting replacement toner.', '2026-09-21 02:16:57', NULL, NULL, NULL),
  (4815, 'Suspicious phishing email reported', NULL, 7, 1, 2, 15, 13, 'Received an email impersonating IT asking to reset password via external link. Reported before clicking.', '2026-09-22 07:04:57', NULL, NULL, NULL),
  (4814, 'Salesforce dashboard loading blank', NULL, 2, 3, 5, 20, 19, 'Pipeline dashboard has been blank since the last release. Cleared cache did not help.', '2026-09-21 12:16:57', NULL, NULL, NULL),
  (4813, 'Wi-Fi weak signal in meeting room B', NULL, 3, 4, 5, 3, 6, 'Signal frequently drops to one bar during client calls in meeting room B, second floor.', '2026-09-20 08:16:57', NULL, NULL, NULL),
  (4812, 'Password reset for finance portal', 2, 4, 4, 6, 9, 8, 'Locked out of the finance reporting portal after three failed attempts.', '2026-09-19 10:16:57', '2026-09-19 15:16:57', 8, 'Reset the account password and unlocked the profile after verifying identity over the phone. Confirmed the user could log back in successfully.'),
  (4811, 'Monitor flickering intermittently', NULL, 1, 4, 6, 7, 13, 'Secondary monitor flickers when laptop is on battery power only.', '2026-09-18 14:16:57', '2026-09-19 00:16:57', 13, 'Updated the display driver and disabled power-saving dimming on battery mode. Flickering has not recurred after two days of use.'),
  (4810, 'Teams calls dropping mid-meeting', NULL, 2, 2, 1, 18, NULL, 'Teams calls disconnect roughly 15 minutes in, affecting client meetings twice this week.', '2026-09-22 03:46:57', NULL, NULL, NULL);

INSERT INTO ticket_events (ticket_id, user_id, actor_role, action, note, is_message, seen_by_admin, created_at) VALUES
  (4821, 1, 'employee', 'created this ticket', NULL, 0, NULL, '2026-09-22 04:46:57'),
  (4821, 13, 'admin', 'picked up this ticket', NULL, 0, NULL, '2026-09-22 05:10:57'),
  (4821, 1, 'employee', 'sent a message', 'Hi, still not syncing — could someone take another look today? It''s affecting a few people on my team too.', 1, 0, '2026-09-22 07:52:57'),
  (4820, 2, 'employee', 'created this ticket', NULL, 0, NULL, '2026-09-22 07:28:57'),
  (4819, 10, 'employee', 'created this ticket', NULL, 0, NULL, '2026-09-22 02:16:57'),
  (4819, 13, 'admin', 'picked up this ticket', NULL, 0, NULL, '2026-09-22 02:52:57'),
  (4818, 12, 'employee', 'created this ticket', NULL, 0, NULL, '2026-09-21 18:16:57'),
  (4818, 6, 'admin', 'picked up this ticket', NULL, 0, NULL, '2026-09-21 19:40:57'),
  (4817, 17, 'employee', 'created this ticket', NULL, 0, NULL, '2026-09-22 06:16:57'),
  (4816, 11, 'employee', 'created this ticket', NULL, 0, NULL, '2026-09-21 02:16:57'),
  (4816, 8, 'admin', 'picked up this ticket', NULL, 0, NULL, '2026-09-21 05:16:57'),
  (4815, 15, 'employee', 'created this ticket', NULL, 0, NULL, '2026-09-22 07:04:57'),
  (4815, 13, 'admin', 'picked up this ticket', NULL, 0, NULL, '2026-09-22 07:16:57'),
  (4814, 20, 'employee', 'created this ticket', NULL, 0, NULL, '2026-09-21 12:16:57'),
  (4814, 19, 'admin', 'picked up this ticket', NULL, 0, NULL, '2026-09-21 14:16:57'),
  (4813, 3, 'employee', 'created this ticket', NULL, 0, NULL, '2026-09-20 08:16:57'),
  (4813, 6, 'admin', 'picked up this ticket', NULL, 0, NULL, '2026-09-20 12:16:57'),
  (4812, 9, 'employee', 'created this ticket', NULL, 0, NULL, '2026-09-19 10:16:57'),
  (4812, 8, 'admin', 'picked up this ticket', NULL, 0, NULL, '2026-09-19 12:16:57'),
  (4812, 8, 'admin', 'closed this ticket', 'Reset the account password and unlocked the profile after verifying identity over the phone. Confirmed the user could log back in successfully.', 0, NULL, '2026-09-19 15:16:57'),
  (4811, 7, 'employee', 'created this ticket', NULL, 0, NULL, '2026-09-18 14:16:57'),
  (4811, 13, 'admin', 'picked up this ticket', NULL, 0, NULL, '2026-09-18 16:16:57'),
  (4811, 13, 'admin', 'closed this ticket', 'Updated the display driver and disabled power-saving dimming on battery mode. Flickering has not recurred after two days of use.', 0, NULL, '2026-09-19 00:16:57'),
  (4810, 18, 'employee', 'created this ticket', NULL, 0, NULL, '2026-09-22 03:46:57');

ALTER TABLE tickets AUTO_INCREMENT = 4822;
