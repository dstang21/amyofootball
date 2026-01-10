-- Wyandotte Fantasy Football Scoring Settings
-- Based on Yahoo league settings

-- Drop existing tables if they exist
DROP TABLE IF EXISTS wyandotte_scoring_settings;

-- Create scoring settings table
CREATE TABLE wyandotte_scoring_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL,
    stat_name VARCHAR(100) NOT NULL,
    stat_key VARCHAR(50) NOT NULL,
    points_value DECIMAL(10,2) NOT NULL,
    per_unit INT DEFAULT 1,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_stat (stat_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Offensive Scoring Settings
INSERT INTO wyandotte_scoring_settings (category, stat_name, stat_key, points_value, per_unit, description) VALUES
-- Passing
('offense', 'Passing Yards', 'pass_yards', 0.05, 1, '20 yards per point'),
('offense', 'Passing Touchdowns', 'pass_td', 6.00, 1, '6 points per passing TD'),
('offense', 'Interceptions', 'pass_int', -1.00, 1, '-1 point per interception'),
('offense', '2-Point Conversions', 'two_point_pass', 2.00, 1, '2 points for 2-pt conversion pass'),

-- Rushing
('offense', 'Rushing Yards', 'rush_yards', 0.10, 1, '10 yards per point'),
('offense', 'Rushing Touchdowns', 'rush_td', 6.00, 1, '6 points per rushing TD'),
('offense', '2-Point Conversions', 'two_point_rush', 2.00, 1, '2 points for 2-pt conversion rush'),

-- Receiving
('offense', 'Receptions', 'receptions', 1.00, 1, '1 point per reception (PPR)'),
('offense', 'Receiving Yards', 'rec_yards', 0.10, 1, '10 yards per point'),
('offense', 'Receiving Touchdowns', 'rec_td', 6.00, 1, '6 points per receiving TD'),
('offense', '2-Point Conversions', 'two_point_rec', 2.00, 1, '2 points for 2-pt conversion reception'),

-- Other Offense
('offense', 'Fumbles Lost', 'fumbles_lost', -1.00, 1, '-1 point per fumble lost'),
('offense', 'Return Touchdowns', 'return_td', 6.00, 1, '6 points per return TD'),
('offense', 'Offensive Fumble Return TD', 'fumble_return_td', 6.00, 1, '6 points per fumble return TD');

-- Insert Kicker Scoring Settings
INSERT INTO wyandotte_scoring_settings (category, stat_name, stat_key, points_value, per_unit, description) VALUES
('kicker', 'Field Goals 0-19 Yards', 'fg_0_19', 3.00, 1, '3 points for FG 0-19 yards'),
('kicker', 'Field Goals 20-29 Yards', 'fg_20_29', 3.00, 1, '3 points for FG 20-29 yards'),
('kicker', 'Field Goals 30-39 Yards', 'fg_30_39', 3.00, 1, '3 points for FG 30-39 yards'),
('kicker', 'Field Goals 40-49 Yards', 'fg_40_49', 4.00, 1, '4 points for FG 40-49 yards'),
('kicker', 'Field Goals 50+ Yards', 'fg_50_plus', 5.00, 1, '5 points for FG 50+ yards'),
('kicker', 'Point After Attempt Made', 'pat_made', 1.00, 1, '1 point per PAT made'),
('kicker', 'Point After Attempt Missed', 'pat_missed', -1.00, 1, '-1 point per PAT missed');

-- Insert Defensive Player Scoring Settings
INSERT INTO wyandotte_scoring_settings (category, stat_name, stat_key, points_value, per_unit, description) VALUES
('defense', 'Solo Tackle', 'tackle_solo', 2.00, 1, '2 points per solo tackle'),
('defense', 'Assisted Tackle', 'tackle_assist', 1.00, 1, '1 point per assisted tackle'),
('defense', 'Sack', 'sack', 4.00, 1, '4 points per sack'),
('defense', 'Interception', 'interception', 4.00, 1, '4 points per interception'),
('defense', 'Fumble Forced', 'fumble_force', 4.00, 1, '4 points per forced fumble'),
('defense', 'Fumble Recovery', 'fumble_recovery', 3.00, 1, '3 points per fumble recovery'),
('defense', 'Defensive Touchdown', 'def_td', 6.00, 1, '6 points per defensive TD'),
('defense', 'Safety', 'safety', 2.00, 1, '2 points per safety'),
('defense', 'Pass Defended', 'pass_defended', 2.00, 1, '2 points per pass defended'),
('defense', 'Blocked Kick', 'blocked_kick', 2.00, 1, '2 points per blocked kick'),
('defense', 'Extra Point Returned', 'extra_point_return', 2.00, 1, '2 points per extra point returned');

-- Create a view for easy querying
CREATE OR REPLACE VIEW wyandotte_scoring_view AS
SELECT 
    id,
    category,
    stat_name,
    stat_key,
    points_value,
    per_unit,
    description,
    CASE 
        WHEN per_unit > 1 THEN CONCAT(per_unit, ' ', stat_name, ' = ', points_value, ' points')
        ELSE CONCAT(stat_name, ' = ', points_value, ' points')
    END as display_text
FROM wyandotte_scoring_settings
ORDER BY 
    CASE category
        WHEN 'offense' THEN 1
        WHEN 'kicker' THEN 2
        WHEN 'defense' THEN 3
    END,
    stat_name;
