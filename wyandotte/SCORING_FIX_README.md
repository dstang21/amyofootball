# Wyandotte Playoff Scoring - Quick Fix Guide

## The Problem
The wyandotte playoff game is not accumulating stats across all playoff games. It should show cumulative totals from Wild Card through Super Bowl, but it's not.

## Quick Diagnosis

Run this command on the server:
```bash
php wyandotte/diagnose.php
```

This will tell you exactly what's wrong and what to do.

## Most Likely Fix

### Option 1: Table is Missing
If the diagnostic says the table is missing, run:
```bash
php wyandotte/migrations/run_playoff_stats_migration.php
```

Then proceed to Option 2.

### Option 2: Table is Empty
If the table exists but has no data, run:
```bash
php wyandotte/admin/update-cumulative-stats.php
```

This will fetch ALL playoff games since January 11, 2026 and populate cumulative stats.

**Expected output:**
- "Found X rostered players with ESPN IDs"
- "Checking games on 2026-01-11..."
- "Processing: Team A @ Team B..."
- "Saved X player records"

### Option 3: Set Up Automation
Once working, create a cron job to keep stats updated:

**Linux/Mac:**
```bash
crontab -e
```

Add this line:
```
0 * * * * cd /home/username/amyofootball/wyandotte/admin && php update-cumulative-stats.php >> /var/log/wyandotte-stats.log 2>&1
```

**Windows (Task Scheduler):**
- Create scheduled task to run hourly
- Program: `php.exe`
- Arguments: `C:\path\to\amyofootball\wyandotte\admin\update-cumulative-stats.php`

## Verify It's Working

1. **Check the API:**
   ```bash
   curl https://amyofootball.com/wyandotte/api/calculate-cumulative-scores.php
   ```
   
   Should return JSON with team scores and the note: `"Cumulative playoff stats - all games combined"`

2. **Check the Website:**
   - Visit: https://amyofootball.com/wyandotte/rosters.php
   - Player stats should show cumulative totals
   - Points should be visible next to each player
   - Standings should rank teams by total points

## Files Explained

- **`wyandotte_player_playoff_stats` table** - Stores cumulative stats
- **`update-cumulative-stats.php`** - Fetches games from ESPN and populates table
- **`calculate-cumulative-scores.php`** - Calculates fantasy points from cumulative data
- **`rosters.php`** - Frontend display (already configured correctly)

## How It Should Work

```
ESPN API (all playoff games)
    ↓
update-cumulative-stats.php (run via cron)
    ↓
wyandotte_player_playoff_stats table
    ↓
calculate-cumulative-scores.php
    ↓
rosters.php (displays cumulative totals)
```

## Troubleshooting

### "Access denied for user"
Check database credentials in `/config.php`

### "No games found"
- Verify playoff start date in update-cumulative-stats.php (line 29)
- Currently set to: `20260111` (January 11, 2026)

### "No ESPN IDs found"
- Some players may be missing ESPN ID mappings
- Check `sleeper_players` table for `espn_id` field
- Manually add missing IDs if needed

### "Stats not updating"
- Manually run: `php wyandotte/admin/update-cumulative-stats.php`
- Check for errors in output
- Verify cron job is running

## Support

For detailed documentation, see:
- `PLAYOFF_SCORING_ISSUE.md` - Full technical analysis
- `PROJECT_STATUS.md` - System architecture overview

Run `php wyandotte/diagnose.php` for automatic issue detection.
