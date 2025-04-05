<header class="main-header">
    <nav class="navbar">
        <div class="logo">
            <a href="index.php">🏕️ CampNest</a>
        </div>
        <ul class="nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a href="index.php?page=campsites">Campsites</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                    <li><a href="index.php?page=admin">Admin Dashboard</a></li>
                <?php endif; ?>
                <li><a href="index.php?page=profile">My Profile</a></li>
                <li><a href="includes/logout.php">Logout</a></li>
            <?php else: ?>
                <li><a href="index.php?page=login">Login</a></li>
                <li><a href="index.php?page=register">Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header> 