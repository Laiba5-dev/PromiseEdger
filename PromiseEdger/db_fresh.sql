-- PromiseEdger FRESH database: purana saara data delete hoga (DROP DATABASE), naye sire se tables + naya data.
-- Admin: laibaboota786@gmail.com / password: Promise@2026 (login ke baad badal lein)
DROP DATABASE IF EXISTS promiseedger;
CREATE DATABASE promiseedger CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE promiseedger;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'user',
  profile_picture VARCHAR(255) DEFAULT NULL,
  lang VARCHAR(2) NOT NULL DEFAULT 'en',
  email_verified_at DATETIME NULL,
  verify_token CHAR(64) NULL,
  verify_sent_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_users_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(30) NOT NULL UNIQUE,
  name_en VARCHAR(50) NOT NULL,
  name_ur VARCHAR(50) NOT NULL,
  icon VARCHAR(40) NOT NULL DEFAULT 'bi-bookmark',
  sort_order TINYINT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS promises (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  category_id INT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  visibility ENUM('public','private') NOT NULL DEFAULT 'public',
  status ENUM('in_progress','done','missed') NOT NULL DEFAULT 'in_progress',
  progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
  due_date DATE NULL,
  completed_at DATETIME NULL,
  streak_count INT NOT NULL DEFAULT 0,
  best_streak INT NOT NULL DEFAULT 0,
  last_checkin DATE NULL,
  is_hidden TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX idx_promises_user_status (user_id, status),
  INDEX idx_promises_due (status, due_date),
  INDEX idx_promises_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS checkins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  promise_id INT NOT NULL,
  user_id INT NOT NULL,
  checkin_date DATE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_checkin (promise_id, checkin_date),
  FOREIGN KEY (promise_id) REFERENCES promises(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  promise_id INT NOT NULL,
  user_id INT NOT NULL,
  type ENUM('like','love','fire','clap','strong') NOT NULL DEFAULT 'like',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_reaction (promise_id, user_id),
  FOREIGN KEY (promise_id) REFERENCES promises(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  promise_id INT NOT NULL,
  user_id INT NOT NULL,
  comment TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (promise_id) REFERENCES promises(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS follows (
  follower_id INT NOT NULL,
  following_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (follower_id, following_id),
  INDEX idx_follows_following (following_id),
  FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  actor_id INT NULL,
  type VARCHAR(30) NOT NULL,
  promise_id INT NULL,
  message VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif_user (user_id, is_read, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (promise_id) REFERENCES promises(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  promise_id INT NOT NULL,
  reporter_id INT NOT NULL,
  reason ENUM('spam','offensive','misleading','other') NOT NULL DEFAULT 'other',
  details VARCHAR(500) NULL,
  status ENUM('pending','dismissed','actioned') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(255) NULL,
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_report (promise_id, reporter_id),
  INDEX idx_reports_status (status, created_at),
  FOREIGN KEY (promise_id) REFERENCES promises(id) ON DELETE CASCADE,
  FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reset_token (token_hash),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO categories (slug, name_en, name_ur, icon, sort_order) VALUES
 ('health',   'Health',   'صحت',     'bi-heart-pulse', 1),
 ('study',    'Study',    'پڑھائی',   'bi-mortarboard', 2),
 ('money',    'Money',    'پیسہ',     'bi-wallet2',     3),
 ('work',     'Work',     'کام',      'bi-briefcase',   4),
 ('family',   'Family',   'خاندان',   'bi-people',      5),
 ('personal', 'Personal', 'ذاتی',     'bi-stars',       6),
 ('other',    'Other',    'دیگر',     'bi-three-dots',  9);


-- ========== NEW DATA ==========
-- Sirf ek user: admin (email verified)
INSERT INTO users (name, email, password, role, email_verified_at) VALUES
('Laiba', 'laibaboota786@gmail.com', '$2y$12$UqzbG5jz4zwGal6fgMJZTe0tx/UG4hGnJ/bGqz451odyPjzMwQEMq', 'admin', NOW());

SET @admin := (SELECT id FROM users WHERE email = 'laibaboota786@gmail.com');

INSERT INTO promises (user_id, category_id, title, description, visibility, status, progress, due_date, completed_at, streak_count, best_streak, last_checkin) VALUES
(@admin, (SELECT id FROM categories WHERE slug='health'),   'Walk 30 minutes every day', 'Morning walk before work.', 'public', 'in_progress', 40, DATE_ADD(CURDATE(), INTERVAL 30 DAY), NULL, 0, 0, NULL),
(@admin, (SELECT id FROM categories WHERE slug='study'),    'Finish the PHP project',       'Testing, fixes and final submission.', 'public', 'in_progress', 75, DATE_ADD(CURDATE(), INTERVAL 10 DAY), NULL, 0, 0, NULL),
(@admin, (SELECT id FROM categories WHERE slug='personal'), 'Read the Quran daily',         'At least one page after Fajr.', 'public', 'in_progress', 55, DATE_ADD(CURDATE(), INTERVAL 60 DAY), NULL, 0, 0, NULL),
(@admin, (SELECT id FROM categories WHERE slug='money'),    'Save 5000 PKR monthly',        '', 'private', 'in_progress', 30, DATE_ADD(CURDATE(), INTERVAL 90 DAY), NULL, 0, 0, NULL),
(@admin, (SELECT id FROM categories WHERE slug='work'),     'Set up the PromiseEdger admin panel', 'Reports and users review.', 'public', 'done', 100, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), 0, 0, NULL);
