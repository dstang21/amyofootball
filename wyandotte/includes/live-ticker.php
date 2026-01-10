<!-- Live NFL Scores Ticker -->
<style>
    .live-ticker {
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
        color: white;
        padding: 10px 0;
        overflow: hidden;
        position: relative;
        border-bottom: 3px solid #667eea;
    }
    .ticker-wrapper {
        display: flex;
        align-items: center;
        padding: 0 20px;
    }
    .ticker-label {
        background: #667eea;
        padding: 8px 15px;
        border-radius: 5px;
        font-weight: bold;
        margin-right: 20px;
        white-space: nowrap;
        font-size: 0.9rem;
    }
    .ticker-content {
        display: flex;
        gap: 30px;
        overflow-x: auto;
        flex: 1;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .ticker-content::-webkit-scrollbar {
        display: none;
    }
    .ticker-game {
        display: flex;
        align-items: center;
        gap: 10px;
        white-space: nowrap;
        padding: 5px 15px;
        background: rgba(255,255,255,0.05);
        border-radius: 8px;
        font-size: 0.9rem;
    }
    .ticker-game.live {
        background: rgba(220, 38, 38, 0.2);
        border: 1px solid #dc2626;
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    .ticker-team {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .ticker-logo {
        width: 24px;
        height: 24px;
    }
    .ticker-score {
        font-weight: bold;
        font-size: 1.1rem;
        margin: 0 5px;
    }
    .ticker-status {
        color: #fbbf24;
        font-size: 0.8rem;
        margin-left: 5px;
    }
    .ticker-live-dot {
        width: 8px;
        height: 8px;
        background: #ef4444;
        border-radius: 50%;
        display: inline-block;
        margin-right: 5px;
        animation: blink 1s infinite;
    }
    @keyframes blink {
        0%, 50%, 100% { opacity: 1; }
        25%, 75% { opacity: 0.3; }
    }
</style>

<div class="live-ticker" id="liveTicker">
    <div class="ticker-wrapper">
        <div class="ticker-label">🏈 NFL LIVE</div>
        <div class="ticker-content" id="tickerContent">
            <span style="color: #999;">Loading live scores...</span>
        </div>
    </div>
</div>

<script>
(function() {
    let tickerUpdateInterval;

    function updateTicker() {
        fetch('api/live-scores.php')
            .then(response => response.json())
            .then(data => {
                if (!data.success || !data.games || data.games.length === 0) {
                    document.getElementById('tickerContent').innerHTML = '<span style="color: #999;">No games today</span>';
                    return;
                }

                const tickerHtml = data.games.map(game => {
                    const liveClass = game.isLive ? 'live' : '';
                    const liveDot = game.isLive ? '<span class="ticker-live-dot"></span>' : '';
                    
                    let statusText = '';
                    if (game.isLive) {
                        statusText = `<span class="ticker-status">${game.clock} Q${game.period}</span>`;
                    } else if (game.isCompleted) {
                        statusText = '<span class="ticker-status">FINAL</span>';
                    } else if (game.isScheduled) {
                        const gameTime = new Date(game.date);
                        statusText = `<span class="ticker-status">${gameTime.toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit'})}</span>`;
                    }

                    return `
                        <div class="ticker-game ${liveClass}">
                            ${liveDot}
                            <div class="ticker-team">
                                <img src="${game.awayTeam.logo}" alt="${game.awayTeam.abbreviation}" class="ticker-logo" onerror="this.style.display='none'">
                                <strong>${game.awayTeam.abbreviation}</strong>
                            </div>
                            <span class="ticker-score">${game.awayTeam.score}</span>
                            <span>@</span>
                            <span class="ticker-score">${game.homeTeam.score}</span>
                            <div class="ticker-team">
                                <strong>${game.homeTeam.abbreviation}</strong>
                                <img src="${game.homeTeam.logo}" alt="${game.homeTeam.abbreviation}" class="ticker-logo" onerror="this.style.display='none'">
                            </div>
                            ${statusText}
                        </div>
                    `;
                }).join('');

                document.getElementById('tickerContent').innerHTML = tickerHtml;
            })
            .catch(error => {
                console.error('Ticker update failed:', error);
            });
    }

    // Initial update
    updateTicker();

    // Update every 60 seconds
    tickerUpdateInterval = setInterval(updateTicker, 60000);
})();
</script>
