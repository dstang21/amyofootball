# Wyandotte Football League - Setup Instructions

## Database Setup

Run the following SQL to create the necessary tables:

```sql
-- Wyandotte Football League Tables

-- Teams table
CREATE TABLE IF NOT EXISTS wyandotte_teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(100) NOT NULL,
    owner_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_team_name (team_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roster table
CREATE TABLE IF NOT EXISTS wyandotte_rosters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    position ENUM('QB', 'RB', 'WR', 'TE', 'DB', 'LB', 'DL') NOT NULL,
    slot_number INT NOT NULL COMMENT '1-9 for the 9 roster spots',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_team_slot (team_id, slot_number),
    FOREIGN KEY (team_id) REFERENCES wyandotte_teams(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scoring table (for future use)
CREATE TABLE IF NOT EXISTS wyandotte_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    roster_id INT NOT NULL,
    week INT NOT NULL,
    points DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_roster_week (roster_id, week),
    FOREIGN KEY (roster_id) REFERENCES wyandotte_rosters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Admin Access

Access the Wyandotte admin at: `/wyandotte/admin/teams.php`

### Features:
1. **Team Management** - Create up to 10 teams with owner names
2. **Roster Builder** - Build 9-player rosters for each team:
   - 1 QB
   - 2 RB
   - 2 WR
   - 1 TE
   - 1 DB
   - 1 LB
   - 1 DL

## Roster Structure
Each team has 9 roster slots populated from the existing players table with position data from player_teams.
