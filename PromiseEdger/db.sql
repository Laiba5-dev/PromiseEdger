-- PromiseEdger database (fresh install). Existing install? Koi zaroorat nahi: app khud upgrade kar leti hai (config/schema.php).
CREATE DATABASE IF NOT EXISTS promiseedger CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE promiseedger;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'user',
  profile_picture VARCHAR(255) DEFAULT NULL,
  email_verified_at DATETIME NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_users_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS promises (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  visibility ENUM('public','private') NOT NULL DEFAULT 'public',
  category VARCHAR(20) NOT NULL DEFAULT 'other',
  status ENUM('in_progress','done','missed') NOT NULL DEFAULT 'in_progress',
  progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
  due_date DATE NULL DEFAULT NULL,
  completed_at DATETIME NULL DEFAULT NULL,
  streak_count INT UNSIGNED NOT NULL DEFAULT 0,
  best_streak INT UNSIGNED NOT NULL DEFAULT 0,
  last_checkin DATE NULL DEFAULT NULL,
  reminder_sent_on DATE NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_p_user_created (user_id, created_at),
  KEY idx_p_vis_created (visibility, created_at),
  KEY idx_p_status_due (status, due_date),
  KEY idx_p_done (status, completed_at),
  KEY idx_p_category (category),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Emoji reactions (purane "likes" ki jagah). Ek user = ek promise par ek reaction.
CREATE TABLE IF NOT EXISTS reactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  promise_id INT NOT NULL,
  user_id INT NOT NULL,
  type VARCHAR(16) NOT NULL DEFAULT 'like',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_reaction (promise_id, user_id),
  KEY idx_r_promise_type (promise_id, type),
  KEY idx_r_user (user_id),
  FOREIGN KEY (promise_id) REFERENCES promises(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  promise_id INT NOT NULL,
  user_id INT NOT NULL,
  comment TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_c_promise_created (promise_id, created_at),
  KEY idx_c_user (user_id),
  FOREIGN KEY (promise_id) REFERENCES promises(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Roz ka check-in (streaks)
CREATE TABLE IF NOT EXISTS checkins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  promise_id INT NOT NULL,
  user_id INT NOT NULL,
  checkin_date DATE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_checkin (promise_id, checkin_date),
  KEY idx_ch_user_date (user_id, checkin_date),
  FOREIGN KEY (promise_id) REFERENCES promises(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS follows (
  follower_id INT NOT NULL,
  following_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (follower_id, following_id),
  KEY idx_f_following (following_id),
  FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  actor_id INT NULL,
  type VARCHAR(24) NOT NULL,
  promise_id INT NULL,
  title VARCHAR(255) NULL,
  data VARCHAR(100) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_n_user (user_id, is_read, id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (promise_id) REFERENCES promises(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  promise_id INT NOT NULL,
  reporter_id INT NOT NULL,
  reason VARCHAR(20) NOT NULL,
  details VARCHAR(500) NULL,
  status ENUM('pending','resolved','dismissed') NOT NULL DEFAULT 'pending',
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_report (promise_id, reporter_id),
  KEY idx_rep_status (status, created_at),
  FOREIGN KEY (promise_id) REFERENCES promises(id) ON DELETE CASCADE,
  FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Password reset / email verification (token ka sirf hash store hota hai)
CREATE TABLE IF NOT EXISTS user_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type ENUM('verify','reset') NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_token (token_hash),
  KEY idx_t_user_type (user_id, type),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS app_meta (k VARCHAR(50) PRIMARY KEY, v VARCHAR(255) NOT NULL) ENGINE=InnoDB;
REPLACE INTO app_meta (k, v) VALUES ('schema_version', '2');
