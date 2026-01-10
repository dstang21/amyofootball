<?php
require_once 'config.php';

if (isLoggedIn()) {
    unset($_SESSION[ADMIN_SESSION_NAME]);
    session_destroy();
}

redirect('index.php');
?>
