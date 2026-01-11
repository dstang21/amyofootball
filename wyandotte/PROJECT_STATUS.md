# Wyandotte Football League - Project Status & Developer Guide

**Last Updated:** January 10, 2026  
**Current Status:** Production-ready with chat system, live scores, and responsive navbar

---

## 🎯 Project Overview

The Wyandotte Football League is a **fantasy football playoff league** web application featuring:
- Live scoring system with real-time updates
- 10 custom teams with 9-player rosters (QB, RB, WR, TE, DB, LB, DL positions)
- Real-time chat system with avatar selection
- Live NFL game integration
- Player statistics tracking
- Responsive design with mobile hamburger menu

**Main File:** `wyandotte/rosters.php` (2176 lines)

---

## 🗂️ File Structure

```
wyandotte/
├── rosters.php                    # Main application (all features)
├── api/
│   ├── chat.php                   # Chat API (post/get/latest)
│   └── plays.php                  # Plays API (IN PROGRESS - not integrated yet)
├── includes/
│   ├── live-ticker.php            # NFL live ticker at top
│   └── [other includes]
├── manage/
│   └── [team/roster management]
├── chat_table.sql                 # Chat database schema
├── plays_table.sql                # Plays database schema (NOT RUN YET)
├── wyandotte_tables.sql           # Main tables schema
├── scoring_settings.sql           # Fantasy scoring rules
└── PROJECT_STATUS.md              # This file
```

---

## 🗄️ Database Tables

### Core Tables (Already Created)
- **wyandotte_teams** - 10 playoff teams with owner names
- **wyandotte_rosters** - Player assignments (9 slots per team)
- **wyandotte_scores** - Weekly scoring
- **wyandotte_chat** - Chat messages with avatars and user IDs
  - `id`, `username`, `user_ip` (stores 6-char unique ID), `avatar`, `message`, `created_at`

### Pending Tables (SQL Created, NOT Migrated)
- **wyandotte_plays** - Individual plays tracking (file: plays_table.sql)
  - Not yet integrated into database
  - API endpoint created but untested

---

## 🎨 Features Implemented

### 1. Navigation System
**Location:** Lines 842-864 in rosters.php

- **Desktop:** Horizontal navbar with logo, home button, and tabs
- **Mobile:** Hamburger menu (< 768px) with animated icon
- **Logo:** amyofootball_logo.png (50px desktop, 40px mobile)
- **Tabs:** Live Scores, Rosters, Player Stats, Latest Plays, Chat, Gallery, League Stats, Analytics
- **Sticky positioning** with rgba(15,23,42,0.95) background

**Key Functions:**
- `toggleMobileMenu()` - Opens/closes mobile menu
- `showTab(tabName)` - Switches tabs, auto-closes mobile menu

### 2. Chat System
**Location:** Lines 1110-1220 (chat tab), Lines 883-903 (preview)

**Features:**
- 48 emoji avatars with modal selector
- Username input with default name assignment
- Quick-reply input in latest chat preview
- 6-character unique user IDs (#abc123)
- localStorage persistence for username, avatar, userId
- 60-second auto-refresh

**Default Names (13 options):**
1. Unwanted Stalker
2. Retarded Voyeur
3. Anonymous Dick
4. Secret Loser
5. Unknown Homo
6. Unidentified Flying Penis
7. Faceless Fred
8. Incognito Eskimo
9. One Dumb Fuck
10. Nameless Moron
11. Fart Knocker
12. John Cena
13. Someone That I Used To Know

**Assignment Logic:**
- Uses first 6 chars of user ID as seed
- Modulo operation to select from 13 names
- Consistent name per user across sessions
- User can change to custom name anytime

**API Endpoints (api/chat.php):**
- `POST action=post` - Send message (requires: username, message, avatar, user_id)
- `GET action=get&limit=50` - Fetch messages (max 100)
- `GET action=latest` - Get most recent message

**Chat Preview Box:**
- Displays below navbar on all tabs EXCEPT Chat tab
- Shows: Avatar + Username + Message + Timestamp
- Inline quick-reply input with Send button
- Minimal header (0.7rem, uppercase, 80% opacity)

### 3. Live Scoring System
**Location:** Lines 920-950 (leaderboard), scattered functions

**Features:**
- Real-time team standings with colored rank badges
- Expandable team modals with full roster details
- Player-by-player point breakdown
- Team comparison mode (up to 2 teams)
- 60-second auto-refresh

**Functions:**
- `loadLiveScores()` - Fetches team scores
- `loadLivePlayerStats()` - Gets detailed player stats
- `openTeamModal(teamId, teamName, ownerName)` - Shows team details
- `toggleCompareTeam(teamId)` - Comparison mode

### 4. Avatar System
**Location:** Lines 1115-1165 (modal), Lines 1280-1330 (logic)

**48 Avatars:**
- Sports: football, helmet, trophy
- Energy: fire, star, lightning, rocket
- Royalty: crown, diamond, money
- Creatures: skull, alien, robot, ghost, demon, devil, ninja
- Animals: wolf, lion, tiger, bear, gorilla, eagle, shark, dragon
- Food: beer, pizza, burger, hotdog, taco
- Emojis: poop, clown, puke, nerd, cool, eyes, muscle, punch, bomb
- Default: football (🏈)

**Modal Behavior:**
- Opens via "Change" button next to avatar
- Grid layout with hover effects
- 3px border on selected avatar
- Closes on selection or outside click
- Selection persists in localStorage

### 5. Latest Chat Preview
**Location:** Lines 883-903

**Design:**
- Thin box below navbar (max-width: 800px)
- Dark background rgba(0,0,0,0.5) with orange border
- "Latest Chat" header (0.7rem, uppercase, low opacity)
- "View All →" link to Chat tab
- Single line display: Avatar + Name + Message + Time
- Quick-reply input below message
- Hidden when Chat tab is active

**JavaScript:**
- `updateLatestChat()` - Fetches latest message
- `sendQuickChat()` - Posts from inline input
- Updates every 60 seconds

### 6. Latest Play Preview (ADDED BUT NOT FUNCTIONAL YET)
**Location:** Lines 905-910

**Design:**
- Very thin box below latest chat preview
- Even more minimal than chat preview
- "(expand)" link to navigate to Latest Plays tab
- **NOT YET CONNECTED TO DATA**

---

## 🎯 In-Progress Feature: Latest Plays

### What Was Created (Not Yet Integrated):
1. ✅ Database table schema (plays_table.sql)
2. ✅ API endpoint (api/plays.php)
3. ✅ Latest Plays tab button in navbar
4. ✅ Latest play preview box in HTML

### What's Missing:
- [ ] Run plays_table.sql to create database table
- [ ] Create Latest Plays tab content section (like Chat tab)
- [ ] JavaScript functions: `loadPlays()`, `updateLatestPlay()`
- [ ] Display formatting for play descriptions
- [ ] Integration with existing player/team data
- [ ] Auto-refresh functionality
- [ ] Test data population

### To Resume Development:
1. Run migration: `wyandotte/plays_table.sql`
2. Add tab content section after Chat tab (around line 1220)
3. Add JavaScript functions for plays (after chat functions)
4. Update `updateLatestPlay()` to populate preview box
5. Add plays to 60-second refresh cycle
6. Test with sample play data

---

## 🔧 Key Technical Details

### JavaScript State Management
**Location:** Lines 1222-1260

**Global Variables:**
```javascript
let compareMode = false;
let selectedTeams = [];
let liveScoresInterval;
let livePlayerStatsInterval;
let rosterStatsInterval;
let chatRefreshInterval;
let selectedAvatar = localStorage.getItem('chatAvatar') || 'football';
let chatUsername = localStorage.getItem('chatUsername') || '';
let chatUserId = localStorage.getItem('chatUserId') || '';
let avatarChosen = sessionStorage.getItem('avatarChosen') === 'true';
```

**Avatar Mapping:**
- `avatarMap` object (48 entries) maps keys to emoji characters
- Used for display in chat messages

### Auto-Refresh System
**Location:** Lines 1946-1950

**Intervals:**
- Live scores: 60 seconds
- Player stats: 60 seconds
- Roster stats: 60 seconds
- Chat: 60 seconds
- Ticker: 60 seconds

**Initial Load:**
```javascript
loadLiveScores();
loadLivePlayerStats();
updateLatestChat();
setInterval(updateLatestChat, 60000);
```

### Tab Management
**Function:** `showTab(tabName)` - Lines 1380-1405

**Behavior:**
- Deactivates all tabs and hides all content
- Activates selected tab and shows content
- Hides latest chat preview if on Chat tab
- Manages refresh intervals (starts chat refresh only on chat tab)
- Auto-closes mobile menu when tab selected

### Modal Systems

**Team Modal:**
- Displays full roster with player stats
- Shows total team score
- Position-based organization
- Scrollable content area

**Avatar Modal:**
- Grid of 48 selectable avatars
- 2rem font size, 8px gap
- Selection indication with border
- Closes on selection with callback

### Responsive Design
**Breakpoint:** 768px

**Mobile Adaptations:**
- Hamburger menu replaces horizontal tabs
- Logo shrinks to 40px
- Tabs become vertical column
- Max-height transition (0 → 500px)
- Touch-optimized spacing

---

## 🎨 Color Scheme

**Primary Colors:**
- Background: #0f172a (dark slate)
- Primary Accent: #f97316 (orange)
- Secondary Accent: #fbbf24 (gold)
- Text Primary: #ffffff (white)
- Text Secondary: #cbd5e1 (light gray)
- Text Muted: #94a3b8 (medium gray)
- Text Dark: #64748b (dark gray)

**Gradients:**
- Orange gradient: `linear-gradient(135deg, #f97316 0%, #ea580c 100%)`
- Used for buttons and highlights

**Backgrounds:**
- Containers: rgba(0,0,0,0.5) with blur
- Navbar: rgba(15,23,42,0.95)
- Inputs: rgba(255,255,255,0.1)

---

## 📦 Dependencies

**Backend:**
- PHP 7.4+
- MySQL/MariaDB
- PDO for database connections
- config.php for database credentials

**Frontend:**
- Vanilla JavaScript (no frameworks)
- CSS3 with Flexbox/Grid
- localStorage & sessionStorage APIs
- Fetch API for AJAX

**External:**
- NFL API integration (live games/ticker)
- Sleeper API integration (not in this file)

---

## 🚀 How to Continue Development

### 1. Latest Plays Feature (Next Priority)

**Step 1: Database Migration**
```bash
mysql -u username -p database_name < wyandotte/plays_table.sql
```

**Step 2: Add Tab Content** (after line 1220)
```html
<!-- Latest Plays Tab -->
<div id="plays" class="tab-content">
    <div style="max-width: 900px; margin: 0 auto;">
        <!-- Play list goes here -->
    </div>
</div>
```

**Step 3: Add JavaScript Functions** (around line 1540)
```javascript
function loadPlays() {
    fetch('api/plays.php?action=get&limit=20')
        .then(response => response.json())
        .then(data => {
            // Populate plays tab
        });
}

function updateLatestPlay() {
    fetch('api/plays.php?action=latest')
        .then(response => response.json())
        .then(data => {
            // Update latest play preview box
        });
}
```

**Step 4: Update Preview Box**
- Modify lines 905-910 to populate with real data
- Add "(expand)" click handler to show Latest Plays tab
- Format: "TD - [Player Name] 45 yd pass +6.0 pts"

### 2. Adding New Features

**Pattern to Follow:**
1. Create database table (SQL file in wyandotte/)
2. Create API endpoint (wyandotte/api/feature.php)
3. Add tab button in navbar (line ~854-861)
4. Add tab content section (after line 1220)
5. Add JavaScript functions (after line 1540)
6. Add to auto-refresh if needed (line ~1946-1950)
7. Update latest preview boxes if applicable

### 3. Git Workflow

**Recent Commits:**
- ff3a340: Move latest chat message inline with username
- 8c89541: Redesign latest chat preview with minimal header
- 743d966: Remove old header and duplicate nav-tabs
- 6be5a09: Replace header with navbar including logo

**To Commit Changes:**
```bash
git add wyandotte/rosters.php
git commit -m "Brief description of changes"
git push
```

---

## 🐛 Known Issues & Considerations

### Current Issues:
- None reported - system stable

### Future Enhancements:
- Latest Plays feature (in progress)
- Live score sorting/filtering
- Chat message editing/deletion
- Avatar upload capability
- User profiles/stats
- Play-by-play notifications
- Mobile swipe gestures

### Performance Notes:
- 60-second refresh is aggressive - consider increasing to 120s for production
- Chat message limit (50) keeps payload small
- No pagination yet on chat - may need for long-term use
- Consider indexing on created_at for better query performance

---

## 📞 Quick Reference

### Important Line Numbers (rosters.php)
- **Navbar:** 842-864
- **Latest Chat Preview:** 883-903
- **Latest Play Preview:** 905-910
- **Live Scores Tab:** 920-1000
- **Rosters Tab:** 1002-1050
- **Player Stats Tab:** 1052-1080
- **Chat Tab:** 1110-1220
- **JavaScript Start:** 1222
- **Global Variables:** 1222-1260
- **Avatar Map:** 1267-1316
- **showTab Function:** 1380-1405
- **Chat Functions:** 1406-1530
- **Update Functions:** 1540+
- **Initial Load:** 1946-1950

### Database Connection
Located in root: `config.php`

### API Endpoints
- Chat: `wyandotte/api/chat.php`
- Plays: `wyandotte/api/plays.php` (untested)

### Styling
Inline CSS throughout rosters.php - consider extracting to separate file if expanding further.

---

## 🎓 Code Patterns Used

### Tab Content Structure
```html
<div id="tabname" class="tab-content">
    <div style="max-width: 900px; margin: 0 auto;">
        <!-- Content here -->
    </div>
</div>
```

### API Fetch Pattern
```javascript
fetch('api/endpoint.php?action=get')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Handle data
        }
    })
    .catch(error => console.error('Error:', error));
```

### Modal Pattern
```html
<div id="modalId" style="display: none; position: fixed; ...">
    <div onclick="event.stopPropagation()">
        <!-- Modal content -->
    </div>
</div>
```

### LocalStorage Pattern
```javascript
localStorage.setItem('key', value);
let value = localStorage.getItem('key') || 'default';
```

---

## 📝 Notes for New Developers

1. **Single Page Application:** Everything is in rosters.php - no routing needed
2. **No Build Process:** Pure vanilla JS/CSS - just edit and refresh
3. **Database First:** Create tables before API endpoints
4. **Mobile First:** Test mobile menu on every navbar change
5. **60-Second Updates:** Any live data should use setInterval pattern
6. **Error Handling:** Use try-catch in PHP, .catch() in JavaScript
7. **User ID System:** 6-char alphanumeric in localStorage, never expose IPs
8. **Avatar System:** Always use avatarMap for emoji display consistency
9. **Modals:** Use event.stopPropagation() on content div to prevent close on content click
10. **Git Often:** Small commits with clear messages

---

## 🎯 Project Goals

**Completed:**
✅ Live scoring system  
✅ Team roster management  
✅ Player statistics display  
✅ Real-time chat with avatars  
✅ Responsive navbar with mobile menu  
✅ NFL live ticker integration  
✅ Team comparison mode  
✅ Default username system  

**In Progress:**
⏳ Latest plays tracking and display  

**Future:**
🔮 Historical statistics  
🔮 Draft system  
🔮 Trade proposals  
🔮 Championship tracking  
🔮 User authentication  

---

**Remember:** This is a fantasy football app meant to be fun! Keep the UX simple, fast, and engaging. The chat default names are intentionally humorous - maintain that tone throughout.
