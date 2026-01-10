<?php

class SleeperSync
{
    private $baseUrl = 'https://api.sleeper.app/v1';
    private $leagueId;
    private $pdo;

    public function __construct($leagueId, $pdo)
    {
        $this->leagueId = $leagueId;
        $this->pdo = $pdo;
    }

    public function syncAll()
    {
        try {
            $this->log("Starting sync for league: " . $this->leagueId);
            
            $this->syncLeague();
            sleep(1);
            $this->syncUsers();
            sleep(1);
            $this->syncRosters();
            sleep(1);
            $this->syncMatchups();
            sleep(1);
            $this->syncTransactions();
            sleep(1);
            $this->syncDrafts();
            sleep(1);
            $this->syncPlayers();
            sleep(1);
            $this->syncStats();
            
            $this->log("Sync completed successfully for league: " . $this->leagueId);
            return ['success' => true, 'message' => 'Sync completed for league ' . $this->leagueId];
        } catch (Exception $e) {
            $this->log("Sync error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function syncLeague()
    {
        $data = $this->apiCall("/league/{$this->leagueId}");
        
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
            $data['league_id'],
            $data['name'],
            $data['season'],
            $data['sport'] ?? 'nfl',
            $data['status'],
            $data['season_type'] ?? 'regular',
            $data['previous_league_id'] ?? null,
            $data['draft_id'] ?? null,
            $data['avatar'] ?? null,
            $data['total_rosters'],
            json_encode($data['roster_positions'] ?? []),
            json_encode($data['settings'] ?? []),
            json_encode($data['scoring_settings'] ?? [])
        ]);
    }

    private function syncUsers()
    {
        $users = $this->apiCall("/league/{$this->leagueId}/users");
        
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
                $this->leagueId,
                $user['user_id'],
                $user['username'],
                $user['display_name'],
                $user['avatar'] ?? null,
                $user['metadata']['team_name'] ?? null,
                $user['is_owner'] ?? 0,
                json_encode($user['metadata'] ?? [])
            ]);
        }
    }

    private function syncRosters()
    {
        $rosters = $this->apiCall("/league/{$this->leagueId}/rosters");
        
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
                $this->leagueId,
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

    private function syncMatchups()
    {
        for ($week = 1; $week <= 18; $week++) {
            try {
                $matchups = $this->apiCall("/league/{$this->leagueId}/matchups/{$week}");
                
                foreach ($matchups as $matchup) {
                    $sql = "INSERT INTO sleeper_matchups 
                            (league_id, week, roster_id, matchup_id, points, custom_points, players, starters)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                            matchup_id = VALUES(matchup_id),
                            points = VALUES(points),
                            custom_points = VALUES(custom_points),
                            players = VALUES(players),
                            starters = VALUES(starters)";

                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([
                        $this->leagueId,
                        $week,
                        $matchup['roster_id'],
                        $matchup['matchup_id'],
                        $matchup['points'],
                        $matchup['custom_points'] ?? null,
                        json_encode($matchup['players'] ?? []),
                        json_encode($matchup['starters'] ?? [])
                    ]);
                }
                sleep(1);
            } catch (Exception $e) {
                $this->log("Failed to sync matchups for week {$week}: " . $e->getMessage());
            }
        }
    }

    private function syncTransactions()
    {
        $transactions = $this->apiCall("/league/{$this->leagueId}/transactions");
        
        foreach ($transactions as $transaction) {
            $sql = "INSERT INTO sleeper_transactions 
                    (league_id, transaction_id, type, status, status_updated, created, creator, leg, adds, drops, draft_picks, waiver_budget, consenter_ids, roster_ids, settings, metadata)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    type = VALUES(type),
                    status = VALUES(status),
                    status_updated = VALUES(status_updated),
                    leg = VALUES(leg),
                    adds = VALUES(adds),
                    drops = VALUES(drops),
                    draft_picks = VALUES(draft_picks),
                    waiver_budget = VALUES(waiver_budget),
                    consenter_ids = VALUES(consenter_ids),
                    roster_ids = VALUES(roster_ids),
                    settings = VALUES(settings),
                    metadata = VALUES(metadata)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $this->leagueId,
                $transaction['transaction_id'],
                $transaction['type'],
                $transaction['status'],
                $transaction['status_updated'],
                $transaction['created'],
                $transaction['creator'],
                $transaction['leg'],
                json_encode($transaction['adds'] ?? []),
                json_encode($transaction['drops'] ?? []),
                json_encode($transaction['draft_picks'] ?? []),
                json_encode($transaction['waiver_budget'] ?? []),
                json_encode($transaction['consenter_ids'] ?? []),
                json_encode($transaction['roster_ids'] ?? []),
                json_encode($transaction['settings'] ?? []),
                json_encode($transaction['metadata'] ?? [])
            ]);
        }
    }

    private function syncDrafts()
    {
        $drafts = $this->apiCall("/league/{$this->leagueId}/drafts");
        
        foreach ($drafts as $draft) {
            // Sync draft metadata
            $sql = "INSERT INTO sleeper_drafts 
                    (draft_id, league_id, type, status, start_time, sport, season_type, season, settings, metadata, draft_order, creators, created, last_picked, last_message_time, last_message_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    type = VALUES(type),
                    status = VALUES(status),
                    start_time = VALUES(start_time),
                    sport = VALUES(sport),
                    season_type = VALUES(season_type),
                    season = VALUES(season),
                    settings = VALUES(settings),
                    metadata = VALUES(metadata),
                    draft_order = VALUES(draft_order),
                    creators = VALUES(creators),
                    last_picked = VALUES(last_picked),
                    last_message_time = VALUES(last_message_time),
                    last_message_id = VALUES(last_message_id)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $draft['draft_id'],
                $this->leagueId,
                $draft['type'],
                $draft['status'],
                $draft['start_time'] ?? null,
                $draft['sport'] ?? 'nfl',
                $draft['season_type'] ?? 'regular',
                $draft['season'],
                json_encode($draft['settings'] ?? []),
                json_encode($draft['metadata'] ?? []),
                json_encode($draft['draft_order'] ?? []),
                json_encode($draft['creators'] ?? []),
                $draft['created'],
                $draft['last_picked'] ?? null,
                $draft['last_message_time'] ?? null,
                $draft['last_message_id'] ?? null
            ]);

            // Sync draft picks
            $picks = $this->apiCall("/draft/{$draft['draft_id']}/picks");
            foreach ($picks as $pick) {
                $sql = "INSERT INTO sleeper_draft_picks 
                        (draft_id, player_id, picked_by, roster_id, round, draft_slot, pick_no, is_keeper, metadata)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        player_id = VALUES(player_id),
                        picked_by = VALUES(picked_by),
                        roster_id = VALUES(roster_id),
                        round = VALUES(round),
                        draft_slot = VALUES(draft_slot),
                        is_keeper = VALUES(is_keeper),
                        metadata = VALUES(metadata)";

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $pick['draft_id'],
                    $pick['player_id'],
                    $pick['picked_by'],
                    $pick['roster_id'],
                    $pick['round'],
                    $pick['draft_slot'],
                    $pick['pick_no'],
                    $pick['is_keeper'] ?? null,
                    json_encode($pick['metadata'] ?? [])
                ]);
            }
            sleep(1);
        }
    }

    private function syncPlayers()
    {
        $players = $this->apiCall("/players/nfl");
        
        foreach ($players as $player_id => $player) {
            $sql = "INSERT INTO sleeper_players 
                    (player_id, first_name, last_name, age, team, number, position, fantasy_positions, height, weight, college, years_exp, rotoworld_id, rotowire_id, sportradar_id, fantasy_data_id, yahoo_id, espn_id, status, injury_status, injury_start_date, practice_participation, search_rank, depth_chart_position, depth_chart_order, hashtag, birth_country, stats_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    first_name = VALUES(first_name),
                    last_name = VALUES(last_name),
                    age = VALUES(age),
                    team = VALUES(team),
                    number = VALUES(number),
                    position = VALUES(position),
                    fantasy_positions = VALUES(fantasy_positions),
                    height = VALUES(height),
                    weight = VALUES(weight),
                    college = VALUES(college),
                    years_exp = VALUES(years_exp),
                    rotoworld_id = VALUES(rotoworld_id),
                    rotowire_id = VALUES(rotowire_id),
                    sportradar_id = VALUES(sportradar_id),
                    fantasy_data_id = VALUES(fantasy_data_id),
                    yahoo_id = VALUES(yahoo_id),
                    espn_id = VALUES(espn_id),
                    status = VALUES(status),
                    injury_status = VALUES(injury_status),
                    injury_start_date = VALUES(injury_start_date),
                    practice_participation = VALUES(practice_participation),
                    search_rank = VALUES(search_rank),
                    depth_chart_position = VALUES(depth_chart_position),
                    depth_chart_order = VALUES(depth_chart_order),
                    hashtag = VALUES(hashtag),
                    birth_country = VALUES(birth_country),
                    stats_id = VALUES(stats_id),
                    updated_at = CURRENT_TIMESTAMP";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $player_id,
                $player['first_name'] ?? '',
                $player['last_name'] ?? '',
                $player['age'] ?? null,
                $player['team'] ?? null,
                $player['number'] ?? null,
                $player['position'] ?? null,
                json_encode($player['fantasy_positions'] ?? []),
                $player['height'] ?? null,
                $player['weight'] ?? null,
                $player['college'] ?? null,
                $player['years_exp'] ?? null,
                $player['rotoworld_id'] ?? null,
                $player['rotowire_id'] ?? null,
                $player['sportradar_id'] ?? null,
                $player['fantasy_data_id'] ?? null,
                $player['yahoo_id'] ?? null,
                $player['espn_id'] ?? null,
                $player['status'] ?? null,
                $player['injury_status'] ?? null,
                $player['injury_start_date'] ?? null,
                $player['practice_participation'] ?? null,
                $player['search_rank'] ?? null,
                $player['depth_chart_position'] ?? null,
                $player['depth_chart_order'] ?? null,
                $player['hashtag'] ?? null,
                $player['birth_country'] ?? null,
                $player['stats_id'] ?? null
            ]);
        }
    }

    private function syncStats()
    {
        $season = date('Y');
        for ($week = 1; $week <= 18; $week++) {
            try {
                $stats = $this->apiCall("/stats/nfl/{$season}/{$week}");
                
                foreach ($stats as $player_id => $stat) {
                    $sql = "INSERT INTO sleeper_player_stats 
                            (player_id, season, week, stats)
                            VALUES (?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                            stats = VALUES(stats)";

                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([
                        $player_id,
                        $season,
                        $week,
                        json_encode($stat)
                    ]);
                }
                sleep(1);
            } catch (Exception $e) {
                $this->log("Failed to sync stats for week {$week}: " . $e->getMessage());
            }
        }
    }

    private function apiCall($endpoint)
    {
        $url = $this->baseUrl . $endpoint;
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'AmyoFootball/1.0'
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            throw new Exception("Failed to fetch data from: $url");
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response from: $url");
        }
        
        return $data;
    }

    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        error_log("[$timestamp] SleeperSync: $message");
    }
}
