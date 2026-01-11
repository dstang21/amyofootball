# Wyandotte Chat System

## Overview
A complete chat system integrated into the Wyandotte Football League rosters page, allowing league members to communicate during games.

## Features

### 1. **Avatar Selection**
- 8 selectable avatars: 🏈 🏆 ⛑️ 🔥 ⭐ ⚡ 🚀 👑
- Visual selection with hover effects and active state highlighting
- Selected avatar is saved and used for all messages

### 2. **User Identity**
- User enters their own name (saved to localStorage for convenience)
- Short IP address displayed automatically (last segment only for privacy)
- Format: `Username (IP: xxx)`

### 3. **Message Display**
- **Latest Chat Preview**: Shows the most recent message below the navigation tabs
  - Includes "View All →" link to open the Chat tab
  - Auto-updates every 60 seconds
  - Hidden when no messages exist
  
- **Full Chat Tab**: Displays up to 50 messages in chronological order
  - Shows avatar, username, IP, timestamp, and message
  - Auto-scrolls to bottom on new messages
  - Custom scrollbar with orange theme
  - Messages refresh every 60 seconds

### 4. **Message Input**
- Name field with localStorage persistence
- Large textarea for message composition
- 500 character limit (validated server-side)
- Orange "Send" button with gradient effect
- Clear error messages for validation issues

### 5. **Real-time Updates**
- Latest chat preview updates every 60 seconds
- Full chat list refreshes when Chat tab is active
- Stops refreshing when navigating away from Chat tab

## Database

### Table: `wyandotte_chat`
```sql
CREATE TABLE wyandotte_chat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    user_ip VARCHAR(45) NOT NULL,
    avatar VARCHAR(50) DEFAULT 'football',
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at DESC)
);
```

## API Endpoints

### POST `/wyandotte/api/chat.php`
**Action: `post`**
- Parameters: `username`, `message`, `avatar`
- Validates message length (max 500 chars)
- Stores short IP automatically
- Returns: `{success: true}` or `{success: false, error: "..."}`

### GET `/wyandotte/api/chat.php?action=get&limit=50`
**Action: `get`**
- Parameters: `limit` (default 50)
- Returns all messages ordered by newest first
- Returns: `{success: true, messages: [...]}`

### GET `/wyandotte/api/chat.php?action=latest`
**Action: `latest`**
- Returns the single most recent message
- Used for the latest chat preview section
- Returns: `{success: true, message: {...}}` or `{success: false}`

## Styling

### Color Scheme
- Dark backgrounds: `rgba(0,0,0,0.5)`
- Orange accents: `#f97316`
- Gold text: `#fbbf24`
- Light gray text: `#cbd5e1`
- Hover effects with transform and shadow

### Responsive Design
- Flexbox layout for message display
- Scrollable message container (max-height: 600px)
- Mobile-friendly input fields
- Touch-friendly avatar selection

## Usage

1. **Send a Message**
   - Navigate to the Chat tab
   - Select your avatar
   - Enter your name (auto-saved for next time)
   - Type your message
   - Click "Send"

2. **View Messages**
   - See latest message below navigation tabs
   - Click "View All →" to open full chat
   - Messages auto-refresh every 60 seconds
   - Scroll through message history

3. **Privacy**
   - Only last segment of IP shown (e.g., "123" from "192.168.1.123")
   - No email or registration required
   - Messages stored with timestamp for history

## Files Modified

- `wyandotte/rosters.php`: Added Chat tab, latest preview, JavaScript functions
- `wyandotte/api/chat.php`: Chat API endpoints (created)
- `wyandotte/chat_table.sql`: Database schema (created)
- `wyandotte/setup-chat.php`: Database setup script (created)

## Future Enhancements

Possible additions:
- Delete your own messages
- Edit messages within 5 minutes
- Emoji reactions
- File/image uploads
- @mentions for other users
- Message search/filter
- Admin moderation tools
- WebSocket for real-time updates (instead of 60-second polling)
