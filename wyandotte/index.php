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
            background: #000;
            min-height: 100vh;
        }

        .hero-banner {
            position: relative;
            width: 100%;
            min-height: 100vh;
            overflow: hidden;
            background: #000;
        }

        .hero-image {
            width: 100%;
            height: auto;
            display: block;
        }

        .hero-overlay {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 10;
        }

        .cta-button {
            display: inline-block;
            padding: 20px 50px;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-size: 1.5rem;
            font-weight: bold;
            box-shadow: 0 8px 25px rgba(249,115,22,0.5);
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .cta-button:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(249,115,22,0.7);
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/live-ticker.php'; ?>
    
    <div class="hero-banner">
        <img src="<?php echo htmlspecialchars($heroImage); ?>" alt="Wyandotte Football League" class="hero-image">
        <div class="hero-overlay">
            <a href="rosters.php" class="cta-button">View Playoff Side Game</a>
        </div>
    </div>
    
    <!-- Orangecat Analytics Tracking Code -->
    <script>
      var ANALYTICS_SITE_ID = 'Amyofootball';
      var ANALYTICS_ENDPOINT = 'https://orangecatdigital.com/api/analytics/track';
    </script>
    <script src="https://orangecatdigital.com/orangecat-analytics.js"></script>
</body>
</html>
