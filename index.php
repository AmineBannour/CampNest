<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampNest - Your Perfect Camping Destination</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php
    session_start();
    require_once 'config/database.php';
    require_once 'includes/header.php';
    
    $page = isset($_GET['page']) ? $_GET['page'] : 'home';
    $allowed_pages = ['home', 'campsites', 'campsite', 'booking', 'profile', 'login', 'register', 'admin', 'review'];
    
    if (in_array($page, $allowed_pages)) {
        include "pages/$page.php";
    } else {
        include "pages/404.php";
    }
    
    require_once 'includes/footer.php';
    ?>
    <script src="assets/js/main.js"></script>
</body>
</html> 