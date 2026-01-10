# Wyandotte Football League - Setup Instructions

## Database Setup

### Option 1: Run Migration Script (Recommended)
```bash
php wyandotte/migrate.php
```

### Option 2: Manual SQL Import
Run the SQL file manually in your database:
```bash
mysql -u your_user -p your_database < wyandotte/wyandotte_tables.sql
```

Or copy and paste the contents of `wyandotte_tables.sql` into your database admin panel (phpMyAdmin, etc.)

## What Gets Created

The migration creates 3 tables:

1. **wyandotte_teams** - Stores up to 10 playoff teams with owner names
2. **wyandotte_rosters** - Links players to teams (9 roster spots per team)
3. **wyandotte_scores** - Ready for future scoring system

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

## Note
This is a temporary playoff league system separate from the main fantasy league functionality.
