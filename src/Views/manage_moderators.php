<?php
session_start();

// Include bootstrap.php to load environment variables and other configurations
require_once __DIR__ . '/../../bootstrap.php';

// --- Authentication Check ---
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// --- Authorization Check (Admin Only) ---
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) { // Assuming Admin role_id is 1
    // You can either redirect or show an access denied message
    // Option 1: Redirect
    // $_SESSION['error_message'] = "Access Denied: You do not have permission to view this page.";
    // header("Location: dashboard.php");
    // exit;

    // Option 2: Show Access Denied message and stop script execution
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Access Denied</title>";
    echo "<style>body { font-family: Arial, sans-serif; padding: 20px; text-align: center; background-color: #f4f4f4; color: #333; } .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); display: inline-block; }</style></head><body>";
    echo "<div class='container'><h1>Access Denied</h1><p>You do not have permission to view this page.</p><p><a href='dashboard.php'>Go to Dashboard</a></p></div>";
    echo "</body></html>";
    exit;
}

// Database connection details from environment variables
$dbHost = $_ENV['DB_HOST'] ?? '';
$dbName = $_ENV['DB_DATABASE'] ?? '';
$dbUser = $_ENV['DB_USERNAME'] ?? '';
$dbPassword = $_ENV['DB_PASSWORD'] ?? '';

// Initialize variables
$messages = [];
$moderators = [];

// PDO DSN and options
$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPassword, $options);

    // --- Handle Form Submission (Add Moderator) ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_moderator'])) {
        $discordId = trim($_POST['discord_id'] ?? '');

        if (empty($discordId)) {
            $messages[] = ['type' => 'error', 'text' => 'Discord User ID cannot be empty.'];
        } elseif (!ctype_digit($discordId)) { // Basic check if it's numeric
            $messages[] = ['type' => 'error', 'text' => 'Discord User ID should be numeric.'];
        } else {
            $botToken = $_ENV['DISCORD_BOT_TOKEN'] ?? null;
            if (empty($botToken)) {
                $messages[] = ['type' => 'error', 'text' => 'Discord Bot Token is not configured in the .env file.'];
            } else {
                // Cooldown logic
                $stmt = $pdo->prepare("SELECT discord_username, discord_avatar_url, last_fetched_at FROM moderators WHERE discord_id = :discord_id");
                $stmt->bindParam(':discord_id', $discordId, PDO::PARAM_STR);
                $stmt->execute();
                $existingModerator = $stmt->fetch();

                $fetchData = true;
                if ($existingModerator && !empty($existingModerator['last_fetched_at'])) {
                    $lastFetchedTime = new DateTime($existingModerator['last_fetched_at']);
                    $currentTime = new DateTime();
                    $interval = $currentTime->getTimestamp() - $lastFetchedTime->getTimestamp();
                    if ($interval < 6 * 3600) { // Less than 6 hours
                        $messages[] = ['type' => 'info', 'text' => "Moderator data for ID " . htmlspecialchars($discordId) . " is fresh (fetched less than 6 hours ago). Using cached data: " . htmlspecialchars($existingModerator['discord_username'])];
                        $fetchData = false;
                    }
                }

                if ($fetchData) {
                    $apiUrl = "https://discord.com/api/v9/users/{$discordId}";
                    $headers = [
                        "Authorization: Bot {$botToken}",
                        "Content-Type: application/json"
                    ];

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $apiUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'MyDiscordBot (https://mydomain.com, 1.0)'); // Recommended by Discord

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);

                    if ($curlError) {
                        $messages[] = ['type' => 'error', 'text' => "Curl Error fetching Discord user: " . htmlspecialchars($curlError)];
                    } elseif ($httpCode == 200) {
                        $userData = json_decode($response, true);
                        if ($userData && isset($userData['id'])) {
                            $discordUsername = $userData['username'] . (isset($userData['discriminator']) && $userData['discriminator'] !== '0' ? '#' . $userData['discriminator'] : '');
                            $avatarHash = $userData['avatar'] ?? null;
                            $discordAvatarUrl = $avatarHash ? "https://cdn.discordapp.com/avatars/{$userData['id']}/{$avatarHash}.png" : null;

                            $sql = "INSERT INTO moderators (discord_id, discord_username, discord_avatar_url, last_fetched_at) 
                                    VALUES (:discord_id, :discord_username, :discord_avatar_url, NOW())
                                    ON DUPLICATE KEY UPDATE 
                                    discord_username = VALUES(discord_username), 
                                    discord_avatar_url = VALUES(discord_avatar_url), 
                                    last_fetched_at = NOW()";
                            $updateStmt = $pdo->prepare($sql);
                            $updateStmt->bindParam(':discord_id', $discordId, PDO::PARAM_STR);
                            $updateStmt->bindParam(':discord_username', $discordUsername, PDO::PARAM_STR);
                            $updateStmt->bindParam(':discord_avatar_url', $discordAvatarUrl, PDO::PARAM_STR);
                            
                            if ($updateStmt->execute()) {
                                $messages[] = ['type' => 'success', 'text' => "Successfully fetched and saved/updated moderator: " . htmlspecialchars($discordUsername)];
                            } else {
                                $messages[] = ['type' => 'error', 'text' => "Database error saving moderator data."];
                            }
                        } else {
                            $messages[] = ['type' => 'error', 'text' => "Invalid data received from Discord API."];
                        }
                    } elseif ($httpCode == 404) {
                        $messages[] = ['type' => 'error', 'text' => "Discord User ID " . htmlspecialchars($discordId) . " not found (Error 404). Please verify the ID."];
                    } elseif ($httpCode == 401) {
                        $messages[] = ['type' => 'error', 'text' => "Discord API Authorization failed (Error 401). Check your Bot Token."];
                    } else {
                        $messages[] = ['type' => 'error', 'text' => "Discord API Error: HTTP Code " . htmlspecialchars($httpCode) . ". Response: " . htmlspecialchars(substr($response, 0, 200))];
                    }
                }
            }
        }
    }

    // --- Fetch Existing Moderators (Refresh list) ---
    $stmt = $pdo->query("SELECT id, discord_id, discord_username, discord_avatar_url, last_fetched_at FROM moderators ORDER BY discord_username ASC");
    $moderators = $stmt->fetchAll();

} catch (PDOException $e) {
    $messages[] = ['type' => 'error', 'text' => "Database error: " . $e->getMessage()];
    // For critical errors, you might want to log them and stop further execution or show a generic error page.
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Moderators</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f9f9f9; color: #333; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.05); max-width: 900px; margin: auto; }
        h2, h3 { color: #333; }
        .form-section { margin-bottom: 30px; padding: 20px; border: 1px solid #eee; border-radius: 5px; background-color: #fdfdfd;}
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"] {
            width: calc(100% - 22px); padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;
        }
        button[type="submit"] {
            background-color: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;
        }
        button[type="submit"]:hover { background-color: #218838; }
        .messages p { padding: 10px; border-radius: 4px; margin-bottom: 10px; }
        .error { color: #d9534f; background-color: #f2dede; border: 1px solid #ebccd1; }
        .success { color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; }
        .info { color: #00529B; background-color: #BDE5F8; border: 1px solid #00529B; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .nav-links { margin-top: 20px; text-align: center; }
        .nav-links a { margin: 0 10px; color: #007bff; text-decoration: none; }
        .nav-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <h2>Manage Moderators</h2>

    <div class="messages">
        <?php foreach ($messages as $message): ?>
            <p class="<?php echo htmlspecialchars($message['type']); ?>"><?php echo htmlspecialchars($message['text']); ?></p>
        <?php endforeach; ?>
    </div>

    <div class="form-section">
        <h3>Add New Moderator</h3>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="discord_id">Discord User ID:</label>
                <input type="text" id="discord_id" name="discord_id" required pattern="\d+">
                <small>Enter the numeric Discord User ID.</small>
            </div>
            <button type="submit" name="add_moderator">Add Moderator (Placeholder)</button>
        </form>
    </div>

    <h3>Current Moderators</h3>
    <?php if (!empty($moderators)): ?>
        <table>
            <thead>
                <tr>
                    <th>Discord ID</th>
                    <th>Username</th>
                    <th>Avatar URL</th>
                    <th>Last Fetched</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($moderators as $mod): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($mod['discord_id']); ?></td>
                        <td><?php echo htmlspecialchars($mod['discord_username']); ?></td>
                        <td>
                            <?php if (!empty($mod['discord_avatar_url'])): ?>
                                <img src="<?php echo htmlspecialchars($mod['discord_avatar_url']); ?>" alt="Avatar" style="width:50px; height:50px; border-radius: 50%;">
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($mod['last_fetched_at'] ?? 'Never'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No moderators added yet.</p>
    <?php endif; ?>

    <div class="nav-links">
        <a href="dashboard.php">Back to Dashboard</a>
    </div>
</div>

</body>
</html>
