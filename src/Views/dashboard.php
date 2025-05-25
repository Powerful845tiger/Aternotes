<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // User is not logged in, redirect to login page
    header("Location: login.php");
    exit;
}

// Retrieve username from session for display
$username = $_SESSION['username'] ?? 'User'; // Default to 'User' if not set for some reason

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; height: 100vh; flex-direction: column; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); text-align: center; }
        h1 { color: #333; margin-bottom: 20px; }
        p { color: #555; font-size: 1.1em; }
        a { color: #007bff; text-decoration: none; font-size: 1.1em; }
        a:hover { text-decoration: underline; }
        .logout-link { margin-top: 25px; }
    </style>
</head>
<body>

<div class="container">
    <h1>Welcome to your Dashboard, <?php echo htmlspecialchars($username); ?>!</h1>
    <p>This is your private area. More features will be added soon.</p>

    <?php
    // Display role-specific content
    $role_id = $_SESSION['role_id'] ?? 0; // Default to 0 if not set

    if ($role_id == 1) { // Admin
        echo "<h3>Admin Panel</h3><p>You have access to administrative functionalities.</p>";
        echo "<p><a href='manage_moderators.php'>Manage Moderators</a></p>"; // Added link for admins
    } elseif ($role_id == 2) { // Moderator
        echo "<h3>Moderator Tools</h3><p>You have access to moderator functionalities.</p>";
    }
    // You could add an 'else' here for other roles or a default message
    ?>

    <div class="logout-link">
        <a href="profile.php" style="margin-right: 15px;">Manage Profile</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

</body>
</html>
