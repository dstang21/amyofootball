#!/bin/bash
# Wyandotte Playoff Scoring Diagnostic Script
# Run this to identify what's wrong with the scoring system

echo "========================================="
echo "Wyandotte Playoff Scoring Diagnostics"
echo "========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check 1: Table Existence
echo "1. Checking if cumulative stats table exists..."
TABLE_EXISTS=$(php -r "require dirname(__DIR__) . '/config.php'; \$stmt = \$pdo->query('SHOW TABLES LIKE \"wyandotte_player_playoff_stats\"'); echo \$stmt->rowCount();")

if [ "$TABLE_EXISTS" -gt 0 ]; then
    echo -e "${GREEN}✓ Table EXISTS${NC}"
    
    # Check record count
    echo ""
    echo "2. Checking record count..."
    RECORD_COUNT=$(php -r "require dirname(__DIR__) . '/config.php'; echo \$pdo->query('SELECT COUNT(*) FROM wyandotte_player_playoff_stats')->fetchColumn();")
    
    if [ "$RECORD_COUNT" -gt 0 ]; then
        echo -e "${GREEN}✓ Table has $RECORD_COUNT records${NC}"
        
        echo ""
        echo "3. Sample data from table:"
        php -r "
            require dirname(__DIR__) . '/config.php';
            \$stmt = \$pdo->query('
                SELECT p.full_name, ps.pass_yards, ps.rush_yards, ps.receptions, ps.tackles_total, ps.games_played
                FROM wyandotte_player_playoff_stats ps
                JOIN players p ON ps.player_id = p.id
                ORDER BY ps.pass_yards DESC, ps.rush_yards DESC
                LIMIT 10
            ');
            echo \"Player Name          | Pass Yds | Rush Yds | Rec | Tackles | Games\n\";
            echo \"─────────────────────┼──────────┼──────────┼─────┼─────────┼──────\n\";
            while(\$row = \$stmt->fetch(PDO::FETCH_ASSOC)) {
                printf(\"%-20s | %8s | %8s | %3s | %7s | %5s\n\",
                    substr(\$row['full_name'], 0, 20),
                    \$row['pass_yards'],
                    \$row['rush_yards'],
                    \$row['receptions'],
                    \$row['tackles_total'],
                    \$row['games_played']
                );
            }
        "
    else
        echo -e "${RED}✗ Table is EMPTY - No stats recorded yet${NC}"
        echo -e "${YELLOW}ACTION REQUIRED: Run 'php wyandotte/admin/update-cumulative-stats.php'${NC}"
    fi
else
    echo -e "${RED}✗ Table MISSING${NC}"
    echo -e "${YELLOW}ACTION REQUIRED: Run 'php wyandotte/migrations/run_playoff_stats_migration.php'${NC}"
fi

echo ""
echo "4. Checking API endpoint..."
curl -s "http://localhost/wyandotte/api/calculate-cumulative-scores.php" | php -r "
    \$json = json_decode(file_get_contents('php://stdin'), true);
    if (isset(\$json['success']) && \$json['success']) {
        echo \"✓ API is working\n\";
        if (isset(\$json['team_scores'])) {
            echo \"  Teams with scores: \" . count(\$json['team_scores']) . \"\n\";
            if (count(\$json['team_scores']) > 0) {
                echo \"  Top team: \" . \$json['team_scores'][0]['team_name'] . \" - \" . \$json['team_scores'][0]['total_points'] . \" pts\n\";
            }
        }
    } else {
        echo \"✗ API returned error\n\";
        if (isset(\$json['error'])) {
            echo \"  Error: \" . \$json['error'] . \"\n\";
        }
    }
"

echo ""
echo "5. Checking scoring settings..."
SCORING_COUNT=$(php -r "require dirname(__DIR__) . '/config.php'; echo \$pdo->query('SELECT COUNT(*) FROM wyandotte_scoring_settings')->fetchColumn();")
if [ "$SCORING_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓ Scoring settings configured ($SCORING_COUNT rules)${NC}"
else
    echo -e "${YELLOW}⚠ No scoring settings found${NC}"
fi

echo ""
echo "6. Checking rosters..."
ROSTER_COUNT=$(php -r "require dirname(__DIR__) . '/config.php'; echo \$pdo->query('SELECT COUNT(*) FROM wyandotte_rosters')->fetchColumn();")
TEAM_COUNT=$(php -r "require dirname(__DIR__) . '/config.php'; echo \$pdo->query('SELECT COUNT(*) FROM wyandotte_teams')->fetchColumn();")
echo "Teams: $TEAM_COUNT"
echo "Rostered players: $ROSTER_COUNT"

echo ""
echo "========================================="
echo "Diagnosis Complete"
echo "========================================="
echo ""
echo "Next Steps:"
echo "1. If table is missing: Run migration"
echo "2. If table is empty: Run update-cumulative-stats.php"
echo "3. Set up cron job for regular updates"
echo "4. Test on frontend at /wyandotte/rosters.php"
echo ""
