    </main>
    
    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            <p>Your ultimate fantasy football rankings and statistics resource.</p>
        </div>
    </footer>

    <script>
        // Simple JavaScript for enhanced UX
        document.addEventListener('DOMContentLoaded', function() {
            // Add fade-in animation to main content
            document.querySelector('main').classList.add('fade-in');
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
            
            // Confirm delete actions
            const deleteButtons = document.querySelectorAll('.btn-danger[onclick*="delete"]');
            deleteButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to delete this item?')) {
                        e.preventDefault();
                    }
                });
            });
            
            // Real-time search functionality
            const searchInput = document.querySelector('input[type="search"], input[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    // Simple client-side filtering could be added here
                    console.log('Searching for:', this.value);
                });
            }
        });
    </script>    
    <!-- Orangecat Analytics Tracking Code -->
    <script>
      var ANALYTICS_SITE_ID = 'Amyofootball';
      var ANALYTICS_ENDPOINT = 'https://orangecatdigital.com/api/analytics/track';
    </script>
    <script src="https://orangecatdigital.com/orangecat-analytics.js"></script></body>
</html>
