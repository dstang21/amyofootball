<?php
require_once dirname(__DIR__) . '/config.php';

$page_title = 'Wyandotte Football League - Chat';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
            color: white;
        }
        .navbar {
            background: rgba(15,23,42,0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(249,115,22,0.3);
            position: sticky;
            top: 0;
            z-index: 1000;
            margin-bottom: 15px;
        }
        .navbar-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
        }
        .navbar-logo {
            height: 32px;
            width: auto;
        }
        .navbar-home {
            color: #fbbf24;
            text-decoration: none;
            font-weight: bold;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 0.8rem;
        }
        .navbar-home:hover {
            background: rgba(251,191,36,0.2);
        }
        .hamburger {
            display: none;
            flex-direction: column;
            gap: 4px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
        }
        .hamburger span {
            width: 25px;
            height: 3px;
            background: #f97316;
            transition: all 0.3s;
            border-radius: 2px;
        }
        .hamburger.active span:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }
        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }
        .hamburger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }
        .nav-tabs {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .nav-tabs button, .nav-tabs a {
            background: transparent;
            border: none;
            color: rgba(255,255,255,0.7);
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        .nav-tabs button:hover, .nav-tabs a:hover {
            color: #fbbf24;
            border-bottom-color: #fbbf24;
        }
        .nav-tabs button.active {
            color: #f97316;
            border-bottom-color: #f97316;
            font-weight: bold;
        }
        @media (max-width: 768px) {
            .navbar-container {
                flex-wrap: wrap;
            }
            .hamburger {
                display: flex;
            }
            .nav-tabs {
                width: 100%;
                flex-direction: column;
                gap: 0;
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease-out;
            }
            .nav-tabs.mobile-open {
                max-height: 500px;
            }
            .nav-tabs button, .nav-tabs a {
                width: 100%;
                text-align: left;
                padding: 15px 20px;
                border-bottom: 1px solid rgba(249,115,22,0.2);
            }
            .navbar-logo {
                height: 32px;
            }
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        .avatar-option-large:hover {
            background: rgba(249,115,22,0.3);
            transform: scale(1.05);
            border-color: #f97316;
        }
        #avatarModal {
            display: none;
        }
        #avatarModal.active {
            display: flex !important;
        }
        #chatMessages::-webkit-scrollbar {
            width: 8px;
        }
        #chatMessages::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.3);
            border-radius: 10px;
        }
        #chatMessages::-webkit-scrollbar-thumb {
            background: #f97316;
            border-radius: 10px;
        }
        #chatMessages::-webkit-scrollbar-thumb:hover {
            background: #ea580c;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/live-ticker.php'; ?>
    
    <nav class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <img src="../amyofootball_logo.png" alt="Amyofootball" class="navbar-logo">
                <a href="index.php" class="navbar-home">Home</a>
            </div>
            <button class="hamburger" onclick="toggleMobileMenu()">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="nav-tabs" id="navTabs">
                <a href="rosters.php">Live Scores</a>
                <a href="rosters.php">Rosters</a>
                <a href="rosters.php">Player Stats</a>
                <a href="rosters.php">Latest Plays</a>
                <button class="active">Chat</button>
                <a href="rosters.php">Gallery</a>
                <a href="rosters.php">League Stats</a>
                <a href="rosters.php">Analytics</a>
                <a href="team-settings.php" style="padding: 12px 20px; background: rgba(249,115,22,0.3); border: 1px solid #f97316; border-radius: 8px; color: #fbbf24; text-decoration: none; display: inline-block; transition: all 0.3s; font-weight: 500; margin-left: 10px;">⚙️ Team Settings</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Avatar Selector Modal -->
        <div id="avatarModal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 10000; align-items: center; justify-content: center;">
            <div style="background: rgba(15,23,42,0.95); border-radius: 20px; padding: 30px; max-width: 600px; border: 2px solid #f97316; max-height: 80vh; overflow-y: auto;">
                <h3 style="color: #f97316; margin-bottom: 20px; text-align: center;">Choose Your Avatar</h3>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: center;">
                    <div class="avatar-option-large" data-avatar="football" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🏈</div>
                    <div class="avatar-option-large" data-avatar="helmet" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">⛑️</div>
                    <div class="avatar-option-large" data-avatar="trophy" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🏆</div>
                    <div class="avatar-option-large" data-avatar="fire" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🔥</div>
                    <div class="avatar-option-large" data-avatar="star" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">⭐</div>
                    <div class="avatar-option-large" data-avatar="lightning" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">⚡</div>
                    <div class="avatar-option-large" data-avatar="rocket" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🚀</div>
                    <div class="avatar-option-large" data-avatar="crown" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">👑</div>
                    <div class="avatar-option-large" data-avatar="skull" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">💀</div>
                    <div class="avatar-option-large" data-avatar="alien" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">👽</div>
                    <div class="avatar-option-large" data-avatar="robot" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🤖</div>
                    <div class="avatar-option-large" data-avatar="ghost" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">👻</div>
                    <div class="avatar-option-large" data-avatar="poop" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">💩</div>
                    <div class="avatar-option-large" data-avatar="clown" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🤡</div>
                    <div class="avatar-option-large" data-avatar="demon" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">😈</div>
                    <div class="avatar-option-large" data-avatar="devil" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">👿</div>
                    <div class="avatar-option-large" data-avatar="ninja" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🥷</div>
                    <div class="avatar-option-large" data-avatar="pirate" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🏴‍☠️</div>
                    <div class="avatar-option-large" data-avatar="muscle" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">💪</div>
                    <div class="avatar-option-large" data-avatar="punch" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">👊</div>
                    <div class="avatar-option-large" data-avatar="boom" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">💥</div>
                    <div class="avatar-option-large" data-avatar="bomb" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">💣</div>
                    <div class="avatar-option-large" data-avatar="dart" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🎯</div>
                    <div class="avatar-option-large" data-avatar="beer" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🍺</div>
                    <div class="avatar-option-large" data-avatar="pizza" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🍕</div>
                    <div class="avatar-option-large" data-avatar="burger" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🍔</div>
                    <div class="avatar-option-large" data-avatar="hotdog" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🌭</div>
                    <div class="avatar-option-large" data-avatar="taco" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🌮</div>
                    <div class="avatar-option-large" data-avatar="wolf" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🐺</div>
                    <div class="avatar-option-large" data-avatar="lion" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🦁</div>
                    <div class="avatar-option-large" data-avatar="tiger" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🐯</div>
                    <div class="avatar-option-large" data-avatar="bear" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🐻</div>
                    <div class="avatar-option-large" data-avatar="gorilla" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🦍</div>
                    <div class="avatar-option-large" data-avatar="eagle" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🦅</div>
                    <div class="avatar-option-large" data-avatar="shark" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🦈</div>
                    <div class="avatar-option-large" data-avatar="trex" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🦖</div>
                    <div class="avatar-option-large" data-avatar="dragon" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🐉</div>
                    <div class="avatar-option-large" data-avatar="unicorn" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🦄</div>
                    <div class="avatar-option-large" data-avatar="monkey" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🐵</div>
                    <div class="avatar-option-large" data-avatar="pig" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🐷</div>
                    <div class="avatar-option-large" data-avatar="dog" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🐶</div>
                    <div class="avatar-option-large" data-avatar="cat" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🐱</div>
                    <div class="avatar-option-large" data-avatar="money" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">💰</div>
                    <div class="avatar-option-large" data-avatar="diamond" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">💎</div>
                    <div class="avatar-option-large" data-avatar="puke" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🤮</div>
                    <div class="avatar-option-large" data-avatar="nerd" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">🤓</div>
                    <div class="avatar-option-large" data-avatar="cool" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">😎</div>
                    <div class="avatar-option-large" data-avatar="eyes" style="font-size: 2rem; cursor: pointer; padding: 8px; border: 3px solid transparent; border-radius: 10px; transition: all 0.3s;">👀</div>
                </div>
            </div>
        </div>

        <!-- Chat Form -->
        <div style="background: rgba(0,0,0,0.5); border-radius: 15px; padding: 20px; margin-bottom: 20px; border: 1px solid rgba(249,115,22,0.3);">
            <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span id="currentAvatar" style="font-size: 2rem;">🏈</span>
                    <button id="avatarSelectorBtn" onclick="openAvatarModal()" style="padding: 4px 8px; background: rgba(249,115,22,0.3); border: 1px solid #f97316; border-radius: 5px; color: #fbbf24; cursor: pointer; font-size: 0.75rem; transition: all 0.3s;">Change</button>
                </div>
                <input type="text" id="chatUsername" placeholder="Enter your name" style="flex: 1; padding: 12px; border-radius: 8px; border: 1px solid rgba(249,115,22,0.5); background: rgba(255,255,255,0.1); color: white; font-size: 1rem;">
            </div>
            <div style="display: flex; gap: 10px;">
                <textarea id="chatMessage" placeholder="Type your message..." style="flex: 1; padding: 12px; border-radius: 8px; border: 1px solid rgba(249,115,22,0.5); background: rgba(255,255,255,0.1); color: white; font-size: 1rem; resize: vertical; min-height: 60px;" onkeypress="if(event.key==='Enter' && !event.shiftKey) { event.preventDefault(); sendChatMessage(); }"></textarea>
                <button onclick="sendChatMessage()" style="padding: 12px 30px; background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 15px rgba(249,115,22,0.4);">Send</button>
            </div>
            <p style="color: #94a3b8; font-size: 0.85rem; margin-top: 8px;">Max 500 characters</p>
        </div>

        <!-- Chat Messages -->
        <div id="chatMessages" style="background: rgba(0,0,0,0.5); border-radius: 15px; padding: 20px; min-height: 300px; max-height: 500px; overflow-y: auto; overflow-x: hidden; border: 1px solid rgba(249,115,22,0.3);">
            <p style="text-align: center; color: #94a3b8;">Loading messages...</p>
        </div>
    </div>

    <script>
        function toggleMobileMenu() {
            const navTabs = document.getElementById('navTabs');
            const hamburger = document.querySelector('.hamburger');
            navTabs.classList.toggle('mobile-open');
            hamburger.classList.toggle('active');
        }

        let selectedAvatar = localStorage.getItem('chatAvatar') || 'football';
        let chatUsername = sessionStorage.getItem('chatUsername') || '';
        let chatUserId = localStorage.getItem('chatUserId') || '';
        let chatRefreshInterval;
        let avatarChosen = sessionStorage.getItem('avatarChosen') === 'true';

        // Default names list
        const defaultNames = [
            'Unwanted Stalker',
            'Retarded Voyeur',
            'Anonymous Dick',
            'Secret Loser',
            'Unknown Homo',
            'Unidentified Flying Penis',
            'Faceless Fred',
            'Incognito Eskimo',
            'One Dumb Fuck',
            'Nameless Moron',
            'Fart Knocker',
            'John Cena',
            'Someone That I Used To Know'
        ];

        // Generate user ID if not exists
        if (!chatUserId) {
            chatUserId = Math.random().toString(36).substring(2, 8);
            localStorage.setItem('chatUserId', chatUserId);
        }

        // Assign default name if user doesn't have one
        if (!chatUsername) {
            const randomIndex = Math.floor(Math.random() * defaultNames.length);
            chatUsername = defaultNames[randomIndex];
            sessionStorage.setItem('chatUsername', chatUsername);
        }

        const avatarMap = {
            'football': '🏈',
            'helmet': '⛑️',
            'trophy': '🏆',
            'fire': '🔥',
            'star': '⭐',
            'lightning': '⚡',
            'rocket': '🚀',
            'crown': '👑',
            'skull': '💀',
            'alien': '👽',
            'robot': '🤖',
            'ghost': '👻',
            'poop': '💩',
            'clown': '🤡',
            'demon': '😈',
            'devil': '👿',
            'ninja': '🥷',
            'pirate': '🏴‍☠️',
            'muscle': '💪',
            'punch': '👊',
            'boom': '💥',
            'bomb': '💣',
            'dart': '🎯',
            'beer': '🍺',
            'pizza': '🍕',
            'burger': '🍔',
            'hotdog': '🌭',
            'taco': '🌮',
            'wolf': '🐺',
            'lion': '🦁',
            'tiger': '🐯',
            'bear': '🐻',
            'gorilla': '🦍',
            'eagle': '🦅',
            'shark': '🦈',
            'trex': '🦖',
            'dragon': '🐉',
            'unicorn': '🦄',
            'monkey': '🐵',
            'pig': '🐷',
            'dog': '🐶',
            'cat': '🐱',
            'money': '💰',
            'diamond': '💎',
            'puke': '🤮',
            'nerd': '🤓',
            'cool': '😎',
            'eyes': '👀'
        };

        function updateAvatarDisplay() {
            document.getElementById('currentAvatar').textContent = avatarMap[selectedAvatar];
        }

        function openAvatarModal() {
            document.getElementById('avatarModal').classList.add('active');
        }

        function closeAvatarModal() {
            document.getElementById('avatarModal').classList.remove('active');
        }

        // Avatar selection from modal
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.avatar-option-large').forEach(option => {
                option.addEventListener('click', function() {
                    selectedAvatar = this.getAttribute('data-avatar');
                    localStorage.setItem('chatAvatar', selectedAvatar);
                    sessionStorage.setItem('avatarChosen', 'true');
                    avatarChosen = true;
                    updateAvatarDisplay();
                    document.getElementById('avatarSelectorBtn').style.display = 'none';
                    closeAvatarModal();
                });
            });

            updateAvatarDisplay();
            
            if (avatarChosen) {
                document.getElementById('avatarSelectorBtn').style.display = 'none';
            }

            // Load username
            if (chatUsername) {
                document.getElementById('chatUsername').value = chatUsername;
            }

            // Load messages
            loadChatMessages();

            // Auto-refresh every second
            chatRefreshInterval = setInterval(loadChatMessages, 1000);
        });

        function loadChatMessages() {
            fetch('api/chat.php?action=get&limit=50')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayChatMessages(data.messages);
                    }
                })
                .catch(error => console.error('Error loading chat:', error));
        }

        async function displayChatMessages(messages) {
            const container = document.getElementById('chatMessages');
            if (messages.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #94a3b8;">No messages yet. Be the first to chat!</p>';
                return;
            }

            messages.reverse();

            const formattedMessages = await Promise.all(messages.map(async msg => {
                const formattedMessage = await formatMessage(msg.message);
                return `
                    <div style="background: rgba(255,255,255,0.05); border-radius: 6px; padding: 8px 12px; margin-bottom: 6px; border-left: 2px solid #f97316;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                            <span style="color: #fbbf24; font-weight: bold; font-size: 0.85rem;">${escapeHtml(msg.username)}</span>
                            <span style="color: #64748b; font-size: 0.75rem; margin-left: auto;">${timeAgo(msg.created_at)}</span>
                        </div>
                        <div style="color: #cbd5e1; line-height: 1.4; font-size: 0.9rem;">${formattedMessage}</div>
                    </div>
                `;
            }));

            container.innerHTML = formattedMessages.join('');
            container.scrollTop = container.scrollHeight;
        }

        function sendChatMessage() {
            const username = document.getElementById('chatUsername').value.trim();
            const message = document.getElementById('chatMessage').value.trim();

            if (!username) {
                alert('Please enter your name');
                return;
            }

            if (!message) {
                alert('Please enter a message');
                return;
            }

            if (message.length > 500) {
                alert('Message is too long (max 500 characters)');
                return;
            }

            sessionStorage.setItem('chatUsername', username);
            chatUsername = username;

            const formData = new FormData();
            formData.append('action', 'post');
            formData.append('username', username);
            formData.append('message', message);
            formData.append('avatar', selectedAvatar);
            formData.append('user_id', chatUserId);

            fetch('api/chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('chatMessage').value = '';
                    loadChatMessages();
                } else {
                    alert('Error sending message: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error sending message:', error);
                alert('Error sending message');
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        async function formatMessage(message) {
            // Check for /pic/filename pattern
            const picPattern = /\/pic\/([a-zA-Z0-9_-]+)/g;
            
            let formattedMessage = escapeHtml(message);
            const matches = [...message.matchAll(picPattern)];
            
            // Check each image match
            for (const match of matches) {
                const filename = match[1];
                try {
                    const response = await fetch(`api/chat-image.php?name=${filename}`);
                    const data = await response.json();
                    
                    if (data.success) {
                        const imageTag = `<img src="${data.url}" alt="${filename}" style="max-width: 300px; max-height: 300px; border-radius: 8px; margin-top: 8px; display: block;">`;
                        formattedMessage = formattedMessage.replace(escapeHtml(match[0]), imageTag);
                    } else {
                        formattedMessage = formattedMessage.replace(escapeHtml(match[0]), '<span style="color: #ef4444;">[pic not found]</span>');
                    }
                } catch (error) {
                    formattedMessage = formattedMessage.replace(escapeHtml(match[0]), '<span style="color: #ef4444;">[pic not found]</span>');
                }
            }
            
            return formattedMessage;
        }

        function timeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);

            if (seconds < 60) return 'just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
            return Math.floor(seconds / 86400) + 'd ago';
        }
    </script>
    
    <!-- Orangecat Analytics Tracking Code -->
    <script>
      var ANALYTICS_SITE_ID = 'Amyofootball';
      var ANALYTICS_ENDPOINT = 'https://orangecatdigital.com/api/analytics/track';
    </script>
    <script src="https://orangecatdigital.com/orangecat-analytics.js"></script>
</body>
</html>
