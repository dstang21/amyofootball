-- League settings table for Wyandotte Fantasy Football

DROP TABLE IF EXISTS wyandotte_league_settings;

CREATE TABLE wyandotte_league_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type VARCHAR(50),
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert league settings
INSERT INTO wyandotte_league_settings (setting_key, setting_value, setting_type, description) VALUES
('league_name', 'Wyandotte Fantasy Football', 'text', 'League name'),
('league_id', '530050', 'text', 'Yahoo League ID'),
('max_teams', '10', 'integer', 'Maximum teams in league'),
('scoring_type', 'Head-to-Head', 'text', 'Type of scoring'),
('max_acquisitions_season', '75', 'integer', 'Max acquisitions for entire season'),
('max_acquisitions_week', '0', 'integer', 'Max acquisitions per week (0 = unlimited)'),
('waiver_time', '2', 'integer', 'Waiver time in days'),
('waiver_type', 'Continual rolling list', 'text', 'Type of waiver system'),
('weekly_waivers', 'Game Time - Tuesday', 'text', 'When waivers process'),
('playoff_teams', '6', 'integer', 'Number of playoff teams'),
('playoff_weeks', '15,16,17', 'text', 'Playoff weeks'),
('trade_end_date', '2025-11-22', 'date', 'Last day trades can be made'),
('fractional_points', '1', 'boolean', 'Allow fractional points'),
('negative_points', '1', 'boolean', 'Allow negative points'),
('season_year', '2025', 'integer', 'Current season year'),
('roster_qb', '2', 'integer', 'Number of QB positions'),
('roster_wr', '3', 'integer', 'Number of WR positions'),
('roster_rb', '2', 'integer', 'Number of RB positions'),
('roster_te', '1', 'integer', 'Number of TE positions'),
('roster_flex', '2', 'integer', 'Number of FLEX (W/R/T) positions'),
('roster_k', '1', 'integer', 'Number of K positions'),
('roster_db', '2', 'integer', 'Number of DB positions'),
('roster_dl', '2', 'integer', 'Number of DL positions'),
('roster_lb', '3', 'integer', 'Number of LB positions'),
('roster_bench', '7', 'integer', 'Number of bench spots'),
('roster_ir', '2', 'integer', 'Number of IR spots');
