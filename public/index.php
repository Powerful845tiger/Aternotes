<?php
session_start();
include 'bootstrap.php';

// Define $url early for use in head
$url = $_SERVER['REQUEST_URI'] ?? '/'; // Default to '/'
if (strpos($url, '?') !== false) { // Strip query parameters for routing
    $url = substr($url, 0, strpos($url, '?'));
}


// Dynamic Page Title Logic
$pageTitle = 'Aternotes'; // Default title
// Check for dynamic guide route first for title setting
if (preg_match('#^/guide/([a-zA-Z0-9-]+)$#', $url, $matches)) {
    // For a specific guide, the title might be set dynamically after fetching guide data in guide_detail.php
    // For now, a generic title or we can try to make it more specific if needed here.
    // For simplicity, we'll use a generic one here and let guide_detail.php override if possible.
    $pageTitle = 'Guide - Aternotes'; 
} else {
    switch ($url) {
        case '/':
        case '/home':
            $pageTitle = 'Home - Aternotes';
            break;
        case '/login':
            $pageTitle = 'Login - Aternotes';
            break;
        case '/dashboard':
            $pageTitle = 'Dashboard - Aternotes';
            break;
        case '/guides':
            $pageTitle = 'Guides - Aternotes';
            break;
        default:
            $pageTitle = 'Page Not Found - Aternotes';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo "<title>" . htmlspecialchars($pageTitle) . "</title>"; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/styles/root.css">
    <?php
    if ($url === '/dashboard') {
        echo '<link rel="stylesheet" href="/styles/dashboard.css">';
        echo '<link rel="stylesheet" href="https://unpkg.com/easymde/dist/easymde.min.css">';
        echo '<script src="https://unpkg.com/easymde/dist/easymde.min.js"></script>';
    }
    if ($url === '/guides') {
        echo '<link rel="stylesheet" href="/styles/guides.css">';
    }
    if (preg_match('#^/guide/([a-zA-Z0-9-]+)$#', $url)) {
        echo '<link rel="stylesheet" href="/styles/guide_detail.css">';
    }
    ?>
    <script src="/javascript/logoAnimation.js"></script>
</head>
<body>
    <header>
        <nav class="navbar" role="navigation" aria-label="main navigation">
            <div class="navbar__left">
                <button id="theme-toggle" class="theme-toggle__button" aria-pressed="false">
                    <span id="theme-icon">Light Mode</span>
                </button>
            </div>
            <div class="navbar__right">
                <?php if (isset($_SESSION['user'])): ?>
                    <a class="navbar__container" href="/dashboard">
                        <button class="navbar__button">
                            <span class="navbar_button--paddingEven">Dashboard</span>
                        </button>
                    </a>
                    <a class="navbar__container" href="/logout">
                        <button class="navbar__button">
                            <span class="navbar_button--paddingEven">Logout</span>
                        </button>
                    </a>
                <?php else: ?>
                    <a class="navbar__container" href="/login">
                        <button class="navbar__button">
                            <span class="navbar_button--paddingEven">Log in</span>
                        </button>
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </header>
    <?php
        // $url is already defined above
        switch ($url) {
            case '/':
            case '/home': // Added /home to match title logic
                include 'views/home.php';
                break;
            case '/login':
                include 'views/login.php';
                break;
            case '/dashboard':
                if (isset($_SESSION['user'])) {
                    include 'views/dashboard.php';
                } else {
                    header('Location: /login');
                    exit;
                }
                break;
            case '/logout':
                session_start(); // Ensure session is active to destroy
                session_unset();
                session_destroy();
                header('Location: /login');
                exit;
                break;
            case '/guides':
                include 'views/guides.php';
                break;
            default:
                if (preg_match('#^/guide/([a-zA-Z0-9-]+)$#', $url, $matches)) {
                    $_GET['slug'] = $matches[1]; // Make slug available
                    // The 'views/guide_detail.php' will use this $_GET['slug']
                    // to fetch and display the specific guide.
                    include 'views/guide_detail.php';
                } else {
                    include 'views/404.php'; // Original default 404 page
                }
                break;
        }
    ?>
</body>
</html>