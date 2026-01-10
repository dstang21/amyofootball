# Sleeper API Integration for AmyoFootball

## Overview

This implementation provides a complete Sleeper.com fantasy football integration for the AmyoFootball website. It includes data synchronization, admin management interface, and export capabilities.

## Features

- **League Management**: Sync and manage multiple Sleeper leagues
- **Player Database**: Browse and search all NFL players from Sleeper
- **Statistics Tracking**: View weekly and seasonal player stats
- **Draft Analysis**: View draft results and analysis
- **Transaction History**: Track trades, waivers, and roster moves
- **Data Export**: Export all data to CSV/Excel formats
- **Admin Interface**: Complete admin panel integration

## Files Created

### Core Components
- `SleeperSync.php` - API synchronization service
- `admin/SleeperController.php` - Main controller class
- `admin/sleeper-api.php` - AJAX API endpoint

### Admin Pages
- `admin/sleeper-leagues.php` - Main leagues management
- `admin/sleeper-league.php` - Individual league details
- `admin/sleeper-players.php` - Players database browser
- `admin/sleeper-stats.php` - Player statistics viewer
- `admin/sleeper-draft.php` - Draft results viewer

### Utilities
- `sleeper-test.php` - Integration testing script

## Database Tables

The integration uses the following tables with `sleeper_` prefix:

- `sleeper_leagues` - League metadata
- `sleeper_league_users` - League members
- `sleeper_rosters` - Team rosters and records
- `sleeper_matchups` - Weekly matchup results
- `sleeper_transactions` - Trades and roster moves
- `sleeper_drafts` - Draft metadata
- `sleeper_draft_picks` - Individual draft picks
- `sleeper_players` - NFL player database
- `sleeper_player_stats` - Weekly/seasonal stats

## Usage Instructions

### 1. Access the Admin Panel

Navigate to: `admin/sleeper-leagues.php`

### 2. Sync a League

1. Find your Sleeper League ID from the URL: `sleeper.app/leagues/LEAGUE_ID/team`
2. Enter the League ID in the sync form
3. Click "Sync League Data"
4. Wait for the sync to complete (may take 2-3 minutes)

### 3. View League Data

- Click "View Details" on any synced league
- Browse different tabs: Users, Rosters, Matchups, Transactions, Drafts
- Export data using the CSV/Excel buttons

### 4. Browse Players and Stats

- Use `admin/sleeper-players.php` to browse the NFL player database
- Use `admin/sleeper-stats.php` to view player statistics
- Filter by position, team, season, week, etc.

## API Rate Limiting

The integration respects Sleeper's API rate limits:
- Maximum 1,000 requests per minute
- 1-second delay between requests during sync
- Error handling for API failures

## Data Sync Process

When syncing a league, the system fetches:

1. **League Information** - Basic league settings and metadata
2. **Users** - League members and commissioners
3. **Rosters** - Team rosters, records, and settings
4. **Matchups** - Weekly results for all 18 weeks
5. **Transactions** - All trades, waivers, and roster moves
6. **Drafts** - Draft metadata and individual picks
7. **Players** - Complete NFL player database (~5,000 players)
8. **Stats** - Weekly player statistics for the current season

## Error Handling

- API timeouts are handled gracefully
- Database errors are logged and reported
- Partial sync failures continue processing other data
- Rate limit compliance prevents API blocking

## Maintenance

### Regular Tasks
- Sync player data weekly (new players, status updates)
- Update player statistics after each NFL week
- Re-sync league data as needed during the season

### Monitoring
- Check error logs for API issues
- Monitor database table sizes
- Verify data freshness

## Security

- All admin pages require authentication
- SQL injection protection via prepared statements
- XSS protection via input sanitization
- CSRF protection on API endpoints

## Performance

- Database indexes on key lookup fields
- JSON data stored efficiently
- Pagination for large datasets
- Export functionality for offline analysis

## Troubleshooting

### Common Issues

1. **League Not Found**
   - Verify the League ID is correct
   - Ensure the league is public or you have access

2. **Sync Failures**
   - Check internet connection
   - Verify Sleeper API is accessible
   - Check error logs for specific issues

3. **Database Errors**
   - Verify database connection settings
   - Ensure all tables exist
   - Check database permissions

### Support

For issues or questions:
1. Check the error logs in the admin panel
2. Run `sleeper-test.php` to verify the integration
3. Review the Sleeper API documentation: https://docs.sleeper.app/

## Future Enhancements

Potential improvements:
- Automated cron job syncing
- Real-time webhook integration
- Advanced analytics and reporting
- Integration with existing fantasy scoring
- Mobile-responsive design improvements

## Changelog

### Version 1.0 (September 2025)
- Initial implementation
- Complete Sleeper API integration
- Admin interface
- Data export functionality
- Player and statistics management
