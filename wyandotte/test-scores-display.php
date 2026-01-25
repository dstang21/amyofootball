<!DOCTYPE html>
<html>
<head>
    <title>Test Cumulative Scores</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e293b; color: #fff; }
        .team { background: #334155; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .player { background: #475569; padding: 10px; margin: 5px 0; border-left: 3px solid #f97316; }
        .stats { color: #94a3b8; font-size: 0.9em; }
        .points { color: #fbbf24; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Wyandotte Cumulative Scores Test</h1>
    <p>Testing what the API actually returns...</p>

    <div id="output"></div>

    <script>
        fetch('api/calculate-cumulative-scores.php')
            .then(r => r.json())
            .then(data => {
                console.log('Full API response:', data);
                
                if (!data.success) {
                    document.getElementById('output').innerHTML = '<p style="color: red;">API Error: ' + (data.error || 'Unknown') + '</p>';
                    return;
                }

                let html = '<h2>Team Standings (Cumulative)</h2>';
                html += '<p style="color: #10b981;">Note: ' + data.note + '</p>';
                
                data.team_scores.forEach((team, index) => {
                    html += `
                        <div class="team">
                            <h3>${index + 1}. ${team.team_name} (${team.owner_name})</h3>
                            <p class="points">Total: ${team.total_points.toFixed(1)} pts (${team.player_count} active players)</p>
                            <div style="margin-left: 20px;">
                    `;
                    
                    team.players.forEach(player => {
                        html += `
                            <div class="player">
                                <strong>${player.name}</strong> (${player.position} - ${player.team})
                                <span class="points">${player.total_points.toFixed(1)} pts</span>
                                <div class="stats">
                        `;
                        
                        if (player.breakdown) {
                            if (player.breakdown.passing) {
                                const p = player.breakdown.passing;
                                html += `<br>📊 PASSING: ${p.yards.value} yds (${p.yards.points.toFixed(1)} pts), ${p.tds.value} TD (${p.tds.points.toFixed(1)} pts), ${p.ints.value} INT (${p.ints.points.toFixed(1)} pts)`;
                            }
                            if (player.breakdown.rushing) {
                                const r = player.breakdown.rushing;
                                html += `<br>🏃 RUSHING: ${r.yards.value} yds (${r.yards.points.toFixed(1)} pts), ${r.tds.value} TD (${r.tds.points.toFixed(1)} pts)`;
                            }
                            if (player.breakdown.receiving) {
                                const rec = player.breakdown.receiving;
                                html += `<br>🙌 RECEIVING: ${rec.receptions.value} rec (${rec.receptions.points.toFixed(1)} pts), ${rec.yards.value} yds (${rec.yards.points.toFixed(1)} pts), ${rec.tds.value} TD (${rec.tds.points.toFixed(1)} pts)`;
                            }
                            if (player.breakdown.defensive) {
                                const d = player.breakdown.defensive;
                                html += `<br>🛡️ DEFENSE: ${d.tackles.value} tkl (${d.tackles.points.toFixed(1)} pts), ${d.sacks.value} sck (${d.sacks.points.toFixed(1)} pts), ${d.ints.value} int (${d.ints.points.toFixed(1)} pts)`;
                            }
                        }
                        
                        if (!player.breakdown || Object.keys(player.breakdown).length === 0) {
                            html += '<br>❌ No stats recorded for this player';
                        }
                        
                        html += `
                                </div>
                            </div>
                        `;
                    });
                    
                    html += `
                            </div>
                        </div>
                    `;
                });
                
                document.getElementById('output').innerHTML = html;
            })
            .catch(err => {
                console.error('Error:', err);
                document.getElementById('output').innerHTML = '<p style="color: red;">Failed to load: ' + err.message + '</p>';
            });
    </script>
</body>
</html>
