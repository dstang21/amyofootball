<?php
// Wyandotte Football League
$pageTitle = "Wyandotte Football League";

// Get random hero image
$imageDir = __DIR__ . '/images/';
$images = glob($imageDir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
$heroImage = !empty($images) ? 'images/' . basename($images[array_rand($images)]) : 'images/kylefrench.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
        }

        .hero-banner {
            position: relative;
            width: 100%;
            height: 500px;
            overflow: hidden;
            background: #000;
        }

        .hero-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            opacity: 0.8;
            animation: fadeIn 1s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 0.8; }
        }

        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.7) 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
            padding: 20px;
        }

        .hero-title {
            font-size: 4rem;
            font-weight: bold;
            margin: 0 0 20px 0;
            text-shadow: 3px 3px 6px rgba(0,0,0,0.8);
            letter-spacing: 2px;
        }

        .hero-subtitle {
            font-size: 1.5rem;
            margin: 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
        }

        .content-section {
            max-width: 1200px;
            margin: 50px auto;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .coming-soon {
            text-align: center;
            padding: 60px 20px;
        }

        .coming-soon h2 {
            font-size: 3rem;
            color: #1e3c72;
            margin-bottom: 20px;
        }

        .coming-soon p {
            font-size: 1.5rem;
            color: #333;
            margin: 20px 0;
        }

        .emphasis {
            font-weight: bold;
            color: #2a5298;
        }

        .footer {
            text-align: center;
            padding: 30px;
            color: white;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.2rem;
            }
            
            .coming-soon h2 {
                font-size: 2rem;
            }
            
            .coming-soon p {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="hero-banner">
        <img src="<?php echo htmlspecialchars($heroImage); ?>" alt="Wyandotte Football League" class="hero-image">
        <div class="hero-overlay">
            <h1 class="hero-title">WYANDOTTE FOOTBALL LEAGUE</h1>
            <p class="hero-subtitle">Where Legends Are Made</p>
        </div>
    </div>

    <div class="content-section">
        <div class="coming-soon">
            <h2>🏈 Welcome, Bitches! 🏈</h2>
            <p>This is your home for the next few weeks of playoff action.</p>
            <p class="emphasis">Let's see who takes home the championship!</p>
        </div>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Wyandotte Football League. All rights reserved.</p>
    </div>
</body>
</html>
