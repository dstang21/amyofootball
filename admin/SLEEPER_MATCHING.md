# Sleeper Player Matching System

## Overview
This system links your AmyoFootball `players` table with Sleeper's comprehensive NFL player database for better stat matching.

## Files Created

1. **admin/migrations/add_sleeper_id_to_players.sql** - SQL migration to add sleeper_id column
2. **admin/run-sleeper-migration.php** - One-click migration runner
3. **admin/match-sleeper-players.php** - Main matching interface with auto-match and manual tools

## How to Use

### Step 1: Run Migration
1. Visit: `http://amyofootball.local/admin/run-sleeper-migration.php`
2. This adds the `sleeper_id` column to your `players` table

### Step 2: Match Players
1. Visit: `http://amyofootball.local/admin/match-sleeper-players.php`
2. Click "Run Auto-Match" to automatically match players by name
3. Manually match any remaining players using the search interface

### Features

#### Auto-Matching
- Matches players by comparing full names (first + last)
- Case-insensitive matching
- Shows results: matched vs skipped

#### Manual Matching
- Search Sleeper's database with live suggestions
- Shows player details: position, team, birth year
- One-click selection from search results
- Clean, easy-to-use interface

#### Statistics Dashboard
- Shows matched vs unmatched counts
- Completion percentage
- Visual progress tracking

## Benefits

Once matched, your players will:
- Link to Sleeper's up-to-date player database
- Get accurate ESPN IDs for stat matching
- Have consistent player identification across systems
- Improve accuracy for Wyandotte league stats

## Accessing the Tool

The "Match Sleeper Players" button appears in:
- `admin/manage-players.php` (top right, yellow button)

## Technical Details

### Database Changes
```sql
ALTER TABLE players 
ADD COLUMN sleeper_id VARCHAR(10) NULL,
ADD INDEX idx_sleeper_id (sleeper_id);
```

### Matching Logic
1. Exact full name match (case-insensitive)
2. First name + Last name comparison
3. Manual override available for edge cases

### API Integration
- Uses existing SleeperController
- New `searchPlayers()` method for live search
- GET endpoint: `sleeper-api.php?action=search_players&query=<name>`
