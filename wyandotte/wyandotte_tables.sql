-- Wyandotte Football League Tables
-- Playoff league using existing sleeper_league_users

-- Participants table - tracks which users are in the playoff league
CREATE TABLE IF NOT EXISTS wyandotte_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(20) NOT NULL,
    league_id VARCHAR(20) NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user (user_id),
    FOREIGN KEY (user_id) REFERENCES sleeper_league_users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (league_id) REFERENCES sleeper_leagues(league_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roster table - playoff rosters for participants
CREATE TABLE IF NOT EXISTS wyandotte_rosters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(20) NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    position ENUM('QB', 'RB', 'WR', 'TE', 'DB', 'LB', 'DL') NOT NULL,
    slot_number INT NOT NULL COMMENT '1-9 for the 9 roster spots',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_slot (user_id, slot_number),
    FOREIGN KEY (user_id) REFERENCES sleeper_league_users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scoring table (for future use)
CREATE TABLE IF NOT EXISTS wyandotte_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(20) NOT NULL,
    week INT NOT NULL,
    points DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_week (user_id, week),
    FOREIGN KEY (user_id) REFERENCES sleeper_league_users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
