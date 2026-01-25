# Wyandotte Playoff Game - Scoring Issue Documentation

**Last Updated:** January 25, 2026  
**Status:** Issue Identified - Awaiting Fix

---

## 🎯 The Problem

The wyandotte playoff game at https://amyofootball.com/wyandotte/rosters.php is **NOT scoring the entire playoffs as a total**. 

### Expected Behavior
Each player should receive points for **ALL stats they've accumulated in ANY playoff game** from Wild Card Round through the Super Bowl, and this cumulative score should be displayed in the standings.

### Current Behavior
The system appears to be tracking only **live game stats** or **per-game stats** instead of cumulative playoff totals.

---

## 📊 How It Should Work

The wyandotte league is a **fantasy football playoff league** where:

1. **10 teams** each draft **9 NFL players** (1 QB, 2 RB, 2 WR, 1 TE, 1 DB, 1 LB, 1 DL)
2. Players accumulate stats throughout the **entire NFL playoff period** (Wild Card → Divisional → Conference Championships → Super Bowl)
3. Each player's fantasy points should be the **SUM of ALL their playoff games**
4. Team standings should reflect the **total cumulative points** from all rostered players

**Example:**
- If Patrick Mahomes throws for 250 yards and 2 TDs in Wild Card round, he gets X points
- If he then throws for 300 yards and 3 TDs in Divisional round, he should now have X + Y points total
- This continues accumulating through Super Bowl
- The team that drafted Mahomes gets all these cumulative points

---

## 🔍 System Architecture

### Database Tables

#### Core Tables (Existing)
- `wyandotte_teams` - 10 playoff teams with owner names
- `wyandotte_rosters` - Player assignments (9 slots per team)
- `wyandotte_scores` - Weekly scoring (unclear usage)
- `wyandotte_chat` - Chat system

#### Cumulative Stats Table (Should exist)
- `wyandotte_player_playoff_stats` - **THIS IS THE KEY TABLE**
  - Stores cumulative stats across ALL playoff games
  - Fields: `pass_yards`, `pass_tds`, `rush_yards`, `rec_yards`, `receptions`, `tackles_total`, `sacks`, `interceptions`, etc.
  - Should be populated by fetching ESPN data for ALL playoff games

### API Endpoints

1. **`api/live-player-stats.php`**
   - Fetches CURRENT/LIVE game stats from ESPN
   - Only shows stats from games happening RIGHT NOW
   - **Problem:** This is live data, not cumulative

2. **`api/calculate-cumulative-scores.php`** ✅ CORRECT
   - Reads from `wyandotte_player_playoff_stats` table
   - Calculates fantasy points based on cumulative stats
   - This is what rosters.php is TRYING to use
   - **Status:** This file appears correctly implemented

3. **`admin/update-cumulative-stats.php`** ⚙️ UPDATE SCRIPT
   - Fetches ALL playoff games from ESPN (from Jan 11, 2026 onwards)
   - Accumulates stats across all games
   - Saves to `wyandotte_player_playoff_stats` table
   - **This needs to be run regularly!**

### Frontend Display (rosters.php)

**Line 1773:** Fetches cumulative scores
```javascript
fetch('api/calculate-cumulative-scores.php')
```

This is CORRECT - the frontend is trying to display cumulative scores.

---

## 🐛 Root Cause Analysis

### The Issue

The system has two separate data flows:

#### Flow A: Live Stats (Current Implementation - WRONG for playoffs)
```
ESPN API (current games only)
    ↓
api/live-player-stats.php
    ↓
Display in rosters.php
```
**Problem:** Only shows stats from games happening RIGHT NOW. Stats disappear after game ends.

#### Flow B: Cumulative Stats (Correct Implementation - NEEDS DATA)
```
ESPN API (ALL playoff games)
    ↓
admin/update-cumulative-stats.php
    ↓
wyandotte_player_playoff_stats table
    ↓
api/calculate-cumulative-scores.php
    ↓
Display in rosters.php
```
**Status:** Code exists but the table may be empty or not updating.

### Likely Causes

1. **Table doesn't exist**
   - Migration at `migrations/run_playoff_stats_migration.php` may not have been run
   
2. **Table exists but is empty**
   - `admin/update-cumulative-stats.php` hasn't been executed
   - No cron job set up to run updates
   
3. **Update script not running regularly**
   - Should run after every playoff game completes
   - Should accumulate ALL games since Jan 11, 2026

---

## ✅ Solution Checklist

### Step 1: Verify Table Exists
```sql
SHOW TABLES LIKE 'wyandotte_player_playoff_stats';
```

If missing, run migration:
```bash
php wyandotte/migrations/run_playoff_stats_migration.php
```

Or manually run:
```bash
mysql -u username -p database < wyandotte/migrations/create_playoff_cumulative_stats.sql
```

### Step 2: Populate Initial Data
Run the update script to fetch ALL playoff games since Jan 11, 2026:
```bash
php wyandotte/admin/update-cumulative-stats.php
```

**Expected Output:**
- "Found X rostered players with ESPN IDs"
- "Checking games on YYYY-MM-DD..."
- "Processing: Team A @ Team B..."
- "Saving to Database..."
- "Saved X player records"

### Step 3: Set Up Automated Updates
Create a cron job to run every hour during playoffs:
```bash
0 * * * * cd /path/to/amyofootball/wyandotte/admin && php update-cumulative-stats.php >> /var/log/wyandotte-stats.log 2>&1
```

### Step 4: Verify Data
Check that cumulative stats are being calculated:
```bash
curl https://amyofootball.com/wyandotte/api/calculate-cumulative-scores.php
```

Should return JSON with:
- `success: true`
- `team_scores` array with cumulative points
- `note: "Cumulative playoff stats - all games combined"`

### Step 5: Monitor the Frontend
Visit https://amyofootball.com/wyandotte/rosters.php and verify:
- Player stats show cumulative totals
- Points increase after each playoff game
- Eliminated players (teams out of playoffs) stop accumulating
- Standings reflect total playoff performance

---

## 🔧 Quick Diagnostic Commands

### Check if table exists:
```php
php -r "require 'config.php'; \$stmt = \$pdo->query('SHOW TABLES LIKE \"wyandotte_player_playoff_stats\"'); echo (\$stmt->rowCount() > 0) ? 'EXISTS' : 'MISSING';"
```

### Check record count:
```php
php -r "require 'config.php'; echo \$pdo->query('SELECT COUNT(*) FROM wyandotte_player_playoff_stats')->fetchColumn();"
```

### View sample data:
```php
php -r "require 'config.php'; \$stmt = \$pdo->query('SELECT p.full_name, ps.pass_yards, ps.rush_yards, ps.receptions FROM wyandotte_player_playoff_stats ps JOIN players p ON ps.player_id = p.id LIMIT 5'); while(\$row = \$stmt->fetch()) { echo \$row['full_name'] . ': Pass=' . \$row['pass_yards'] . ', Rush=' . \$row['rush_yards'] . ', Rec=' . \$row['receptions'] . PHP_EOL; }"
```

---

## 📝 Files Involved

### Database Migrations
- `wyandotte/migrations/create_playoff_cumulative_stats.sql` - Table schema
- `wyandotte/migrations/run_playoff_stats_migration.php` - Migration runner

### Data Population
- `wyandotte/admin/update-cumulative-stats.php` - Fetches and stores cumulative stats

### API Endpoints
- `wyandotte/api/calculate-cumulative-scores.php` - Calculates fantasy points from cumulative data ✅
- `wyandotte/api/live-player-stats.php` - Live game data (not used for totals)

### Frontend
- `wyandotte/rosters.php` - Main display (line 1773 calls cumulative API) ✅

### Scoring Configuration
- `wyandotte/scoring_settings.sql` - Fantasy point values per stat

---

## 🎯 Expected Data Flow (Correct Implementation)

```
┌─────────────────────────────────────────────────────────────┐
│                     ESPN API                                 │
│  (All NFL playoff games: Wild Card → Super Bowl)           │
└────────────────────┬────────────────────────────────────────┘
                     │
                     │ Fetched by cron job
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│          admin/update-cumulative-stats.php                   │
│  - Fetches ALL games since Jan 11, 2026                    │
│  - Accumulates stats by player across games                │
│  - Handles passing, rushing, receiving, defense            │
└────────────────────┬────────────────────────────────────────┘
                     │
                     │ Saves to database
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│         wyandotte_player_playoff_stats                       │
│  player_id | pass_yards | rush_yards | receptions | ...    │
│  ──────────┼────────────┼────────────┼────────────┼─────   │
│      1234  │    456     │     89     │     12     │  ...   │
│      5678  │      0     │    234     │      8     │  ...   │
└────────────────────┬────────────────────────────────────────┘
                     │
                     │ Read by API
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│        api/calculate-cumulative-scores.php                   │
│  - Reads cumulative stats from database                    │
│  - Applies scoring rules (0.04 per pass yd, etc.)          │
│  - Calculates total fantasy points per player              │
│  - Groups by team and ranks                                │
└────────────────────┬────────────────────────────────────────┘
                     │
                     │ JSON response
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│              rosters.php (Frontend)                          │
│  - Displays cumulative points per player                   │
│  - Shows team standings based on total                     │
│  - Updates every 15 seconds                                │
└─────────────────────────────────────────────────────────────┘
```

---

## ⚠️ Important Notes

1. **Playoff Start Date:** January 11, 2026
   - All cumulative stats should start from this date
   - Defined in `update-cumulative-stats.php` line 29

2. **Eliminated Players**
   - Players whose teams are out of playoffs should stop accumulating
   - System tracks `playoff_status` in `teams` table
   - Eliminated players marked with gray overlay on frontend

3. **Scoring Settings**
   - Stored in `wyandotte_scoring_settings` table
   - Pass yards: 0.04 pts/yd typically
   - Pass TD: 4 pts typically
   - Rush/Rec TD: 6 pts typically
   - Check actual values in database

4. **Games Counted**
   - Wild Card Round
   - Divisional Round  
   - Conference Championships
   - Super Bowl
   - All games from Jan 11, 2026 onwards

5. **Refresh Rate**
   - Frontend updates every 15 seconds (line 1846)
   - Cache expires every 60 seconds
   - Cron should run at least hourly during active games

---

## 🚀 Quick Fix (Emergency)

If you need to get this working RIGHT NOW:

1. **SSH into server**
2. **Run migration:** `php wyandotte/migrations/run_playoff_stats_migration.php`
3. **Populate data:** `php wyandotte/admin/update-cumulative-stats.php`
4. **Set up cron:** Add hourly job for update script
5. **Verify:** Visit rosters page and check standings

The cumulative stats should appear immediately after step 3.

---

## 📞 Support

For issues:
1. Check `/var/log/wyandotte-stats.log` for cron errors
2. Run update script manually to see errors
3. Check database connection in `config.php`
4. Verify ESPN API is accessible (curl test)

---

**Remember:** This is a CUMULATIVE playoff league - stats must persist and accumulate across all playoff games, not reset after each game!
