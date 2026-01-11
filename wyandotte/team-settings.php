<?php
require_once '../config.php';

// Get all teams
$stmt = $pdo->query("SELECT * FROM wyandotte_teams ORDER BY team_name");
$teams = $stmt->fetchAll();

// Get gallery images
$imageDir = __DIR__ . '/images/';
$galleryImages = [];
if (is_dir($imageDir)) {
    $images = glob($imageDir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
    foreach ($images as $imagePath) {
        $galleryImages[] = 'images/' . basename($imagePath);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Settings - Wyandotte Football</title>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #f97316 0%, #fbbf24 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .back-link {
            display: inline-block;
            color: #fbbf24;
            text-decoration: none;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: rgba(249,115,22,0.2);
            border-radius: 8px;
            border: 1px solid #f97316;
            transition: all 0.3s;
        }
        .back-link:hover {
            background: rgba(249,115,22,0.3);
        }
        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        .team-card {
            background: rgba(15,23,42,0.9);
            border-radius: 20px;
            padding: 25px;
            border: 2px solid rgba(249,115,22,0.3);
            transition: all 0.3s;
        }
        .team-card:hover {
            border-color: #f97316;
            box-shadow: 0 8px 30px rgba(249,115,22,0.3);
        }
        .team-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(249,115,22,0.2);
        }
        .team-logo-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(249,115,22,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: #f97316;
            border: 2px solid #f97316;
            overflow: hidden;
        }
        .team-logo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .team-info h3 {
            color: #fbbf24;
            font-size: 1.3rem;
            margin-bottom: 5px;
        }
        .team-info p {
            color: #94a3b8;
            font-size: 0.9rem;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #fbbf24;
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        .form-group input[type="text"] {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid rgba(249,115,22,0.5);
            background: rgba(255,255,255,0.1);
            color: white;
            font-size: 1rem;
        }
        .form-group input[type="text"]:focus {
            outline: none;
            border-color: #f97316;
            background: rgba(255,255,255,0.15);
        }
        .logo-options {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .logo-option-btn {
            flex: 1;
            padding: 10px;
            background: rgba(249,115,22,0.2);
            border: 1px solid #f97316;
            border-radius: 8px;
            color: #fbbf24;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        .logo-option-btn:hover,
        .logo-option-btn.active {
            background: rgba(249,115,22,0.4);
        }
        .gallery-selector {
            display: none;
            max-height: 300px;
            overflow-y: auto;
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }
        .gallery-selector.active {
            display: block;
        }
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 10px;
        }
        .gallery-image {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        .gallery-image:hover {
            border-color: #fbbf24;
            transform: scale(1.05);
        }
        .gallery-image.selected {
            border-color: #f97316;
            box-shadow: 0 0 15px rgba(249,115,22,0.5);
        }
        .upload-area {
            display: none;
            margin-top: 10px;
        }
        .upload-area.active {
            display: block;
        }
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        .file-input-label {
            display: block;
            padding: 12px;
            background: rgba(249,115,22,0.2);
            border: 2px dashed #f97316;
            border-radius: 8px;
            color: #fbbf24;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .file-input-label:hover {
            background: rgba(249,115,22,0.3);
        }
        .selected-file {
            color: #94a3b8;
            font-size: 0.85rem;
            margin-top: 8px;
        }
        .save-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(249,115,22,0.4);
        }
        .save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(249,115,22,0.5);
        }
        .success-message,
        .error-message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            display: none;
        }
        .success-message {
            background: rgba(16,185,129,0.2);
            border: 1px solid #10b981;
            color: #10b981;
        }
        .error-message {
            background: rgba(239,68,68,0.2);
            border: 1px solid #ef4444;
            color: #ef4444;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="rosters.php" class="back-link">← Back to Rosters</a>
        
        <div class="header">
            <h1>⚙️ Team Settings</h1>
            <p style="color: #94a3b8;">Customize your team name and logo</p>
        </div>

        <div class="teams-grid">
            <?php foreach ($teams as $team): 
                $team_initials = strtoupper(substr($team['team_name'], 0, 1) . substr($team['owner_name'], 0, 1));
            ?>
                <div class="team-card">
                    <div id="success-<?php echo $team['id']; ?>" class="success-message">Settings saved successfully!</div>
                    <div id="error-<?php echo $team['id']; ?>" class="error-message">Error saving settings</div>
                    
                    <div class="team-header">
                        <div class="team-logo-preview" id="logo-preview-<?php echo $team['id']; ?>">
                            <?php if ($team['logo']): ?>
                                <img src="<?php echo htmlspecialchars($team['logo']); ?>" alt="<?php echo htmlspecialchars($team['team_name']); ?>">
                            <?php else: ?>
                                <?php echo $team_initials; ?>
                            <?php endif; ?>
                        </div>
                        <div class="team-info">
                            <h3><?php echo htmlspecialchars($team['team_name']); ?></h3>
                            <p>Owner: <?php echo htmlspecialchars($team['owner_name']); ?></p>
                        </div>
                    </div>

                    <form id="team-form-<?php echo $team['id']; ?>" onsubmit="saveTeam(event, <?php echo $team['id']; ?>)">
                        <div class="form-group">
                            <label>Team Name</label>
                            <input type="text" name="team_name" value="<?php echo htmlspecialchars($team['team_name']); ?>" required maxlength="100">
                        </div>

                        <div class="form-group">
                            <label>Team Logo</label>
                            <div class="logo-options">
                                <button type="button" class="logo-option-btn active" onclick="selectLogoOption(<?php echo $team['id']; ?>, 'gallery')">
                                    Choose from Gallery
                                </button>
                                <button type="button" class="logo-option-btn" onclick="selectLogoOption(<?php echo $team['id']; ?>, 'upload')">
                                    Upload New
                                </button>
                            </div>

                            <!-- Gallery Selector -->
                            <div id="gallery-<?php echo $team['id']; ?>" class="gallery-selector active">
                                <div class="gallery-grid">
                                    <?php foreach ($galleryImages as $image): ?>
                                        <img src="<?php echo htmlspecialchars($image); ?>" 
                                             class="gallery-image" 
                                             onclick="selectGalleryImage(<?php echo $team['id']; ?>, '<?php echo htmlspecialchars($image); ?>')"
                                             data-team="<?php echo $team['id']; ?>"
                                             <?php if ($team['logo'] === $image): ?>class="gallery-image selected"<?php endif; ?>>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Upload Area -->
                            <div id="upload-<?php echo $team['id']; ?>" class="upload-area">
                                <div class="file-input-wrapper">
                                    <input type="file" id="file-<?php echo $team['id']; ?>" name="logo_file" accept="image/*" onchange="handleFileSelect(<?php echo $team['id']; ?>)">
                                    <label for="file-<?php echo $team['id']; ?>" class="file-input-label">
                                        📁 Click to upload an image
                                    </label>
                                </div>
                                <div id="file-name-<?php echo $team['id']; ?>" class="selected-file"></div>
                            </div>

                            <input type="hidden" name="logo" id="logo-input-<?php echo $team['id']; ?>" value="<?php echo htmlspecialchars($team['logo'] ?? ''); ?>">
                        </div>

                        <button type="submit" class="save-btn">Save Changes</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function selectLogoOption(teamId, option) {
            // Update button states
            const buttons = document.querySelectorAll(`#team-form-${teamId} .logo-option-btn`);
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            // Show/hide appropriate section
            const gallery = document.getElementById(`gallery-${teamId}`);
            const upload = document.getElementById(`upload-${teamId}`);
            
            if (option === 'gallery') {
                gallery.classList.add('active');
                upload.classList.remove('active');
            } else {
                gallery.classList.remove('active');
                upload.classList.add('active');
            }
        }

        function selectGalleryImage(teamId, imagePath) {
            // Update hidden input
            document.getElementById(`logo-input-${teamId}`).value = imagePath;
            
            // Update visual selection
            const images = document.querySelectorAll(`#gallery-${teamId} .gallery-image`);
            images.forEach(img => img.classList.remove('selected'));
            event.target.classList.add('selected');
            
            // Update preview
            updateLogoPreview(teamId, imagePath);
        }

        function handleFileSelect(teamId) {
            const fileInput = document.getElementById(`file-${teamId}`);
            const fileName = document.getElementById(`file-name-${teamId}`);
            
            if (fileInput.files.length > 0) {
                fileName.textContent = `Selected: ${fileInput.files[0].name}`;
                // Clear gallery selection
                document.getElementById(`logo-input-${teamId}`).value = '';
            }
        }

        function updateLogoPreview(teamId, imagePath) {
            const preview = document.getElementById(`logo-preview-${teamId}`);
            preview.innerHTML = `<img src="${imagePath}" alt="Team Logo">`;
        }

        function saveTeam(event, teamId) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            formData.append('team_id', teamId);
            
            // Hide previous messages
            document.getElementById(`success-${teamId}`).style.display = 'none';
            document.getElementById(`error-${teamId}`).style.display = 'none';
            
            fetch('api/update-team.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(`success-${teamId}`).style.display = 'block';
                    if (data.logo) {
                        updateLogoPreview(teamId, data.logo);
                    }
                    // Update header
                    const headerInfo = form.closest('.team-card').querySelector('.team-info h3');
                    headerInfo.textContent = formData.get('team_name');
                    
                    setTimeout(() => {
                        document.getElementById(`success-${teamId}`).style.display = 'none';
                    }, 3000);
                } else {
                    document.getElementById(`error-${teamId}`).style.display = 'block';
                    document.getElementById(`error-${teamId}`).textContent = data.error || 'Error saving settings';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById(`error-${teamId}`).style.display = 'block';
            });
        }
    </script>
</body>
</html>
