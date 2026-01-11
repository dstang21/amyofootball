<?php
// Handle different include paths depending on where this file is called from
if (file_exists('../config.php')) {
    require_once '../config.php';
    require_once '../SleeperSync.php';
} else {
    require_once 'config.php';
    require_once 'SleeperSync.php';
}

class SleeperController
{
    public $pdo;

    public function __construct()
    {
        // Use existing database connection from config.php
        global $pdo;
        if (isset($pdo)) {
            $this->pdo = $pdo;
        } else {
            // Fallback: create new connection if global doesn't exist
            try {
                $this->pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                die("Database connection failed: " . $e->getMessage());
            }
        }
    }

    public function getLeagues()
    {
        $stmt = $this->pdo->query("SELECT * FROM sleeper_leagues ORDER BY season DESC, name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeague($leagueId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sleeper_leagues WHERE league_id = ?");
        $stmt->execute([$leagueId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getLeagueUsers($leagueId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sleeper_league_users WHERE league_id = ? ORDER BY display_name");
        $stmt->execute([$leagueId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeagueRosters($leagueId)
    {
        $sql = "SELECT r.*, u.display_name, u.team_name, u.username 
                FROM sleeper_rosters r
                LEFT JOIN sleeper_league_users u ON r.owner_id = u.user_id AND r.league_id = u.league_id
                WHERE r.league_id = ?
                ORDER BY r.fpts DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$leagueId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeagueMatchups($leagueId, $week = null)
    {
        $sql = "SELECT m.*, r.owner_id, u.display_name, u.team_name
                FROM sleeper_matchups m
                LEFT JOIN sleeper_rosters r ON m.roster_id = r.roster_id AND m.league_id = r.league_id
                LEFT JOIN sleeper_league_users u ON r.owner_id = u.user_id AND r.league_id = u.league_id
                WHERE m.league_id = ?";
        $params = [$leagueId];
        
        if ($week) {
            $sql .= " AND m.week = ?";
            $params[] = $week;
        }
        
        $sql .= " ORDER BY m.week DESC, m.matchup_id, m.points DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeagueTransactions($leagueId, $limit = 50)
    {
        // Ensure limit is an integer and safe
        $limit = (int) $limit;
        if ($limit <= 0 || $limit > 1000) {
            $limit = 50; // Default safe value
        }
        
        $sql = "SELECT t.*, u.display_name as creator_name
                FROM sleeper_transactions t
                LEFT JOIN sleeper_league_users u ON t.creator = u.user_id AND t.league_id = u.league_id
                WHERE t.league_id = ?
                ORDER BY t.created DESC
                LIMIT " . $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$leagueId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeagueDrafts($leagueId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sleeper_drafts WHERE league_id = ? ORDER BY created DESC");
        $stmt->execute([$leagueId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDraftPicks($draftId)
    {
        $sql = "SELECT dp.*, p.first_name, p.last_name, p.position, p.team, u.display_name as picked_by_name
                FROM sleeper_draft_picks dp
                LEFT JOIN sleeper_players p ON dp.player_id = p.player_id
                LEFT JOIN sleeper_league_users u ON dp.picked_by = u.user_id
                WHERE dp.draft_id = ?
                ORDER BY dp.pick_no";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$draftId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPlayers($search = '', $position = '', $team = '', $limit = 50, $offset = 0)
    {
        $sql = "SELECT * FROM sleeper_players WHERE 1=1";
        $params = [];
        
        if ($search) {
            $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($position) {
            $sql .= " AND position = ?";
            $params[] = $position;
        }
        
        if ($team) {
            $sql .= " AND team = ?";
            $params[] = $team;
        }
        
        // Cast limit and offset to integers and add them directly to SQL
        $limit = (int)$limit;
        $offset = (int)$offset;
        $sql .= " ORDER BY search_rank ASC, last_name, first_name LIMIT $limit OFFSET $offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPlayerStats($playerId = '', $season = '', $week = '', $limit = 50, $offset = 0)
    {
        $sql = "SELECT ps.*, p.first_name, p.last_name, p.position, p.team
                FROM sleeper_player_stats ps
                LEFT JOIN sleeper_players p ON ps.player_id = p.player_id
                WHERE 1=1";
        $params = [];
        
        if ($playerId) {
            $sql .= " AND ps.player_id = ?";
            $params[] = $playerId;
        }
        
        if ($season) {
            $sql .= " AND ps.season = ?";
            $params[] = $season;
        }
        
        if ($week) {
            $sql .= " AND ps.week = ?";
            $params[] = $week;
        }
        
        // Cast limit and offset to integers and add them directly to SQL
        $limit = (int)$limit;
        $offset = (int)$offset;
        $sql .= " ORDER BY ps.season DESC, ps.week DESC, p.last_name, p.first_name LIMIT $limit OFFSET $offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function syncLeague($leagueId)
    {
        try {
            $sync = new SleeperSync($leagueId, $this->pdo);
            return $sync->syncAll();
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getTeams()
    {
        $teams = [
            'ARI' => 'Arizona Cardinals', 'ATL' => 'Atlanta Falcons', 'BAL' => 'Baltimore Ravens',
            'BUF' => 'Buffalo Bills', 'CAR' => 'Carolina Panthers', 'CHI' => 'Chicago Bears',
            'CIN' => 'Cincinnati Bengals', 'CLE' => 'Cleveland Browns', 'DAL' => 'Dallas Cowboys',
            'DEN' => 'Denver Broncos', 'DET' => 'Detroit Lions', 'GB' => 'Green Bay Packers',
            'HOU' => 'Houston Texans', 'IND' => 'Indianapolis Colts', 'JAX' => 'Jacksonville Jaguars',
            'KC' => 'Kansas City Chiefs', 'LV' => 'Las Vegas Raiders', 'LAC' => 'Los Angeles Chargers',
            'LAR' => 'Los Angeles Rams', 'MIA' => 'Miami Dolphins', 'MIN' => 'Minnesota Vikings',
            'NE' => 'New England Patriots', 'NO' => 'New Orleans Saints', 'NYG' => 'New York Giants',
            'NYJ' => 'New York Jets', 'PHI' => 'Philadelphia Eagles', 'PIT' => 'Pittsburgh Steelers',
            'SF' => 'San Francisco 49ers', 'SEA' => 'Seattle Seahawks', 'TB' => 'Tampa Bay Buccaneers',
            'TEN' => 'Tennessee Titans', 'WAS' => 'Washington Commanders'
        ];
        return $teams;
    }

    public function getPositions()
    {
        return ['QB', 'RB', 'WR', 'TE', 'K', 'DEF'];
    }

    public function getLeagueStandings($leagueId, $season = null)
    {
        // If no season specified, get the most recent season with data
        if (!$season) {
            $seasonSql = "SELECT season FROM sleeper_leagues 
                         WHERE league_id = ? OR previous_league_id = ? 
                         ORDER BY season DESC LIMIT 1";
            $seasonStmt = $this->pdo->prepare($seasonSql);
            $seasonStmt->execute([$leagueId, $leagueId]);
            $seasonResult = $seasonStmt->fetch();
            $season = $seasonResult ? $seasonResult['season'] : date('Y');
        }
        
        // Get the league ID for the specific season
        $leagueForSeason = $this->getLeagueIdForSeason($leagueId, $season);
        
        $sql = "SELECT r.*, u.display_name, u.team_name, sl.season, sl.name as league_name
                FROM sleeper_rosters r
                LEFT JOIN sleeper_league_users u ON r.owner_id = u.user_id AND r.league_id = u.league_id
                LEFT JOIN sleeper_leagues sl ON r.league_id = sl.league_id
                WHERE r.league_id = ?
                ORDER BY r.wins DESC, r.fpts DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$leagueForSeason]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeagueSeasons($leagueId)
    {
        // Get all seasons for leagues with the same name (related leagues)
        $sql = "SELECT sl1.season, sl1.league_id, sl1.name,
                       CASE WHEN sr.league_id IS NOT NULL THEN 1 ELSE 0 END as has_roster_data
                FROM sleeper_leagues sl1
                LEFT JOIN sleeper_rosters sr ON sl1.league_id = sr.league_id
                WHERE sl1.name IN (
                    SELECT DISTINCT name FROM sleeper_leagues WHERE league_id = ?
                )
                GROUP BY sl1.season, sl1.league_id, sl1.name
                ORDER BY sl1.season DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$leagueId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getLeagueIdForSeason($baseLeagueId, $season)
    {
        // Find the league ID for the specific season by matching league name
        $sql = "SELECT sl2.league_id 
                FROM sleeper_leagues sl2
                WHERE sl2.name IN (
                    SELECT name FROM sleeper_leagues WHERE league_id = ?
                ) AND sl2.season = ?
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$baseLeagueId, $season]);
        $result = $stmt->fetch();
        
        return $result ? $result['league_id'] : $baseLeagueId;
    }

    // Lighter sync method for historical data to prevent timeouts
    public function syncLeagueBasics($leagueId)
    {
        try {
            $sync = new SleeperSync($leagueId, $this->pdo);
            
            // Use the new lighter syncBasics method
            $result = $sync->syncBasics();
            
            return $result;
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getRecentMatchups($leagueId, $limit = 10)
    {
        // Ensure limit is an integer and safe
        $limit = (int) $limit;
        if ($limit <= 0 || $limit > 100) {
            $limit = 10; // Default safe value
        }
        
        $sql = "SELECT m.*, u.display_name as team_name 
                FROM sleeper_matchups m
                LEFT JOIN sleeper_rosters r ON m.roster_id = r.roster_id AND m.league_id = r.league_id
                LEFT JOIN sleeper_league_users u ON r.owner_id = u.user_id AND r.league_id = u.league_id
                WHERE m.league_id = ?
                ORDER BY m.week DESC, m.points DESC
                LIMIT " . $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$leagueId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeagueChampions($leagueId)
    {
        $sql = "SELECT 
                    lc.season,
                    lc.display_name as champion_name,
                    lc.team_name,
                    lc.wins,
                    lc.losses,
                    lc.ties,
                    lc.fpts,
                    sl.name as league_name
                FROM league_champions lc
                JOIN sleeper_leagues sl ON lc.league_id = sl.league_id
                WHERE lc.league_id = ? 
                   OR lc.league_id IN (
                       SELECT league_id FROM sleeper_leagues 
                       WHERE previous_league_id = ? 
                       OR league_id IN (
                           SELECT previous_league_id FROM sleeper_leagues WHERE league_id = ?
                       )
                   )
                ORDER BY lc.season DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$leagueId, $leagueId, $leagueId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeaguePlayerRankings($leagueId)
    {
        // Get players owned in this league - simplified for MariaDB compatibility
        $sql = "SELECT DISTINCT 
                    CONCAT(p.first_name, ' ', p.last_name) as player_name,
                    p.position,
                    p.team as nfl_team,
                    u.display_name as owner_name,
                    0 as fantasy_points
                FROM sleeper_rosters r
                LEFT JOIN sleeper_league_users u ON r.owner_id = u.user_id AND r.league_id = u.league_id
                CROSS JOIN sleeper_players p
                WHERE r.league_id = ? 
                AND r.players LIKE CONCAT('%\"', p.player_id, '\"%')
                AND p.player_id IS NOT NULL
                ORDER BY p.position, p.first_name, p.last_name
                LIMIT 50";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$leagueId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeagueHistory($leagueId)
    {
        // Get historical seasons for this league
        $sql = "SELECT season, total_rosters as total_teams FROM sleeper_leagues 
                WHERE league_id = ? OR previous_league_id = ?
                ORDER BY season DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$leagueId, $leagueId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMostActiveOwner($leagueId)
    {
        $sql = "SELECT u.display_name as name, SUM(r.total_moves) as total_moves
                FROM sleeper_league_users u
                LEFT JOIN sleeper_rosters r ON u.user_id = r.owner_id AND u.league_id = r.league_id
                WHERE u.league_id = ?
                GROUP BY u.user_id, u.display_name
                ORDER BY total_moves DESC
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$leagueId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getHighestScoringSeasonTeam($leagueId)
    {
        $sql = "SELECT MAX(fpts) as points FROM sleeper_rosters WHERE league_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$leagueId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function scrapeLeagueHistory($leagueId)
    {
        $scrapedSeasons = 0;
        $scrapedChampions = 0;
        $currentLeagueId = $leagueId;
        $visitedLeagues = [];
        
        try {
            // Follow the chain of previous_league_id to get all historical seasons
            while ($currentLeagueId && !in_array($currentLeagueId, $visitedLeagues)) {
                $visitedLeagues[] = $currentLeagueId;
                
                // Get league data from Sleeper API
                $leagueData = $this->apiCall("/league/{$currentLeagueId}");
                
                if (!$leagueData) {
                    break;
                }
                
                // Store/update league data
                $this->storeLeagueData($leagueData);
                $scrapedSeasons++;
                
                // Get users and rosters for champion tracking
                $users = $this->apiCall("/league/{$currentLeagueId}/users");
                $rosters = $this->apiCall("/league/{$currentLeagueId}/rosters");
                
                if ($users && $rosters) {
                    $this->storeLeagueUsers($currentLeagueId, $users);
                    $this->storeLeagueRosters($currentLeagueId, $rosters);
                    
                    // Determine champion if league is complete
                    if ($leagueData['status'] === 'complete') {
                        $champion = $this->determineLeagueChampion($currentLeagueId, $rosters, $users);
                        if ($champion) {
                            $this->storeLeagueChampion($currentLeagueId, $leagueData['season'], $champion);
                            $scrapedChampions++;
                        }
                    }
                }
                
                // Move to previous league
                $currentLeagueId = $leagueData['previous_league_id'] ?? null;
                
                // Add delay to respect API limits
                sleep(1);
            }
            
            return [
                'success' => true,
                'message' => "Successfully scraped {$scrapedSeasons} seasons and {$scrapedChampions} champions for league history"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Error scraping league history: " . $e->getMessage()
            ];
        }
    }

    public function scrapeAllLeagueHistories()
    {
        $leagues = $this->getLeagues();
        $totalScraped = 0;
        $errors = 0;
        
        foreach ($leagues as $league) {
            try {
                $result = $this->scrapeLeagueHistory($league['league_id']);
                if ($result['success']) {
                    $totalScraped++;
                }
            } catch (Exception $e) {
                $errors++;
            }
            
            // Add delay between leagues
            sleep(2);
        }
        
        return [
            'success' => true,
            'message' => "Scraped histories for {$totalScraped} leagues" . ($errors > 0 ? " ({$errors} errors)" : "")
        ];
    }

    public function getLeagueHistorySummary()
    {
        $sql = "SELECT 
                    l.league_id,
                    l.name as league_name,
                    COUNT(DISTINCT l2.season) as season_count,
                    COUNT(DISTINCT lc.season) as champions_tracked,
                    MAX(l2.updated_at) as last_updated
                FROM sleeper_leagues l
                LEFT JOIN sleeper_leagues l2 ON (l2.league_id = l.league_id OR l2.previous_league_id = l.league_id)
                LEFT JOIN league_champions lc ON lc.league_id = l.league_id
                GROUP BY l.league_id, l.name
                ORDER BY l.name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function storeLeagueData($leagueData)
    {
        $sql = "INSERT INTO sleeper_leagues 
                (league_id, name, season, sport, status, season_type, previous_league_id, draft_id, avatar, total_rosters, roster_positions, settings, scoring_settings) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                season = VALUES(season),
                sport = VALUES(sport),
                status = VALUES(status),
                season_type = VALUES(season_type),
                previous_league_id = VALUES(previous_league_id),
                draft_id = VALUES(draft_id),
                avatar = VALUES(avatar),
                total_rosters = VALUES(total_rosters),
                roster_positions = VALUES(roster_positions),
                settings = VALUES(settings),
                scoring_settings = VALUES(scoring_settings),
                updated_at = CURRENT_TIMESTAMP";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $leagueData['league_id'],
            $leagueData['name'],
            $leagueData['season'],
            $leagueData['sport'] ?? 'nfl',
            $leagueData['status'],
            $leagueData['season_type'] ?? 'regular',
            $leagueData['previous_league_id'] ?? null,
            $leagueData['draft_id'] ?? null,
            $leagueData['avatar'] ?? null,
            $leagueData['total_rosters'],
            json_encode($leagueData['roster_positions'] ?? []),
            json_encode($leagueData['settings'] ?? []),
            json_encode($leagueData['scoring_settings'] ?? [])
        ]);
    }

    private function storeLeagueUsers($leagueId, $users)
    {
        foreach ($users as $user) {
            $sql = "INSERT INTO sleeper_league_users 
                    (league_id, user_id, username, display_name, avatar, team_name, is_owner, metadata)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    username = VALUES(username),
                    display_name = VALUES(display_name),
                    avatar = VALUES(avatar),
                    team_name = VALUES(team_name),
                    is_owner = VALUES(is_owner),
                    metadata = VALUES(metadata)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $leagueId,
                $user['user_id'],
                $user['username'] ?? $user['display_name'] ?? 'Unknown User',
                $user['display_name'] ?? 'Unknown User',
                $user['avatar'] ?? null,
                $user['metadata']['team_name'] ?? null,
                $user['is_owner'] ?? 0,
                json_encode($user['metadata'] ?? [])
            ]);
        }
    }

    private function storeLeagueRosters($leagueId, $rosters)
    {
        foreach ($rosters as $roster) {
            $sql = "INSERT INTO sleeper_rosters 
                    (league_id, roster_id, owner_id, wins, losses, ties, fpts, fpts_decimal, fpts_against, fpts_against_decimal, total_moves, waiver_position, waiver_budget_used, players, starters, reserve, settings)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    owner_id = VALUES(owner_id),
                    wins = VALUES(wins),
                    losses = VALUES(losses),
                    ties = VALUES(ties),
                    fpts = VALUES(fpts),
                    fpts_decimal = VALUES(fpts_decimal),
                    fpts_against = VALUES(fpts_against),
                    fpts_against_decimal = VALUES(fpts_against_decimal),
                    total_moves = VALUES(total_moves),
                    waiver_position = VALUES(waiver_position),
                    waiver_budget_used = VALUES(waiver_budget_used),
                    players = VALUES(players),
                    starters = VALUES(starters),
                    reserve = VALUES(reserve),
                    settings = VALUES(settings)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $leagueId,
                $roster['roster_id'],
                $roster['owner_id'],
                $roster['settings']['wins'] ?? 0,
                $roster['settings']['losses'] ?? 0,
                $roster['settings']['ties'] ?? 0,
                $roster['settings']['fpts'] ?? 0.00,
                $roster['settings']['fpts_decimal'] ?? 0.00,
                $roster['settings']['fpts_against'] ?? 0.00,
                $roster['settings']['fpts_against_decimal'] ?? 0.00,
                $roster['settings']['total_moves'] ?? 0,
                $roster['settings']['waiver_position'] ?? 0,
                $roster['settings']['waiver_budget_used'] ?? 0,
                json_encode($roster['players'] ?? []),
                json_encode($roster['starters'] ?? []),
                json_encode($roster['reserve'] ?? []),
                json_encode($roster['settings'] ?? [])
            ]);
        }
    }

    private function determineLeagueChampion($leagueId, $rosters, $users)
    {
        // Find the roster with the most wins (and highest points as tiebreaker)
        $champion = null;
        $bestRecord = ['wins' => -1, 'fpts' => 0];
        
        foreach ($rosters as $roster) {
            $wins = $roster['settings']['wins'] ?? 0;
            $points = $roster['settings']['fpts'] ?? 0;
            
            if ($wins > $bestRecord['wins'] || 
                ($wins === $bestRecord['wins'] && $points > $bestRecord['fpts'])) {
                $bestRecord = ['wins' => $wins, 'fpts' => $points];
                $champion = $roster;
            }
        }
        
        if ($champion) {
            // Find the user for this roster
            foreach ($users as $user) {
                if ($user['user_id'] === $champion['owner_id']) {
                    return [
                        'user_id' => $user['user_id'],
                        'display_name' => $user['display_name'],
                        'team_name' => $user['metadata']['team_name'] ?? null,
                        'wins' => $champion['settings']['wins'] ?? 0,
                        'losses' => $champion['settings']['losses'] ?? 0,
                        'ties' => $champion['settings']['ties'] ?? 0,
                        'fpts' => $champion['settings']['fpts'] ?? 0
                    ];
                }
            }
        }
        
        return null;
    }

    private function storeLeagueChampion($leagueId, $season, $champion)
    {
        $sql = "INSERT INTO league_champions 
                (league_id, season, user_id, display_name, team_name, wins, losses, ties, fpts)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                display_name = VALUES(display_name),
                team_name = VALUES(team_name),
                wins = VALUES(wins),
                losses = VALUES(losses),
                ties = VALUES(ties),
                fpts = VALUES(fpts)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $leagueId,
            $season,
            $champion['user_id'],
            $champion['display_name'],
            $champion['team_name'],
            $champion['wins'],
            $champion['losses'],
            $champion['ties'],
            $champion['fpts']
        ]);
    }

    public function searchPlayers($query)
    {
        $sql = "SELECT player_id, first_name, last_name, position, team, age, college
                FROM sleeper_players 
                WHERE first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?
                ORDER BY search_rank ASC, last_name, first_name 
                LIMIT 20";
        
        $searchTerm = "%$query%";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function apiCall($endpoint)
    {
        $url = "https://api.sleeper.app/v1" . $endpoint;
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'AmyoFootball/1.0'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new Exception("Failed to fetch data from: $url");
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response from: $url");
        }
        
        return $data;
    }
}
