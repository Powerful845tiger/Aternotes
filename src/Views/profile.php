<?php
session_start();

// Include bootstrap.php to load environment variables and other configurations
require_once __DIR__ . '/../../bootstrap.php';

// --- Authentication Check ---
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Database connection details from environment variables
$dbHost = $_ENV['DB_HOST'] ?? '';
$dbName = $_ENV['DB_DATABASE'] ?? '';
$dbUser = $_ENV['DB_USERNAME'] ?? '';
$dbPassword = $_ENV['DB_PASSWORD'] ?? '';

// Initialize variables for messages and current email
$emailUpdateErrors = [];
$emailUpdateSuccess = '';
$passwordChangeErrors = [];
$passwordChangeSuccess = '';
$currentEmail = '';

// PDO DSN and options
$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// --- Fetch Current Email ---
try {
    $pdo = new PDO($dsn, $dbUser, $dbPassword, $options);
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = :id");
    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch();
    if ($user) {
        $currentEmail = $user['email'];
    } else {
        // Should not happen if user is logged in
        $emailUpdateErrors[] = "Could not retrieve user data.";
    }
} catch (PDOException $e) {
    $emailUpdateErrors[] = "Database error fetching email: " . $e->getMessage();
}


// --- Handle Form Submissions ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pdo = new PDO($dsn, $dbUser, $dbPassword, $options); // Re-establish for POST operations

    // --- Handle Email Update ---
    if (isset($_POST['update_email'])) {
        $newEmail = trim($_POST['new_email'] ?? '');

        if (empty($newEmail)) {
            $emailUpdateErrors[] = "New email cannot be empty.";
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $emailUpdateErrors[] = "Invalid email format.";
        } elseif ($newEmail === $currentEmail) {
            $emailUpdateErrors[] = "New email is the same as the current email.";
        } else {
            try {
                // Check if new email is already in use by another user
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id != :id");
                $stmt->bindParam(':email', $newEmail, PDO::PARAM_STR);
                $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
                $stmt->execute();
                if ($stmt->fetchColumn() > 0) {
                    $emailUpdateErrors[] = "This email address is already in use by another account.";
                } else {
                    // Update email
                    $stmt = $pdo->prepare("UPDATE users SET email = :email WHERE id = :id");
                    $stmt->bindParam(':email', $newEmail, PDO::PARAM_STR);
                    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
                    if ($stmt->execute()) {
                        $emailUpdateSuccess = "Email updated successfully!";
                        $currentEmail = $newEmail; // Update displayed email
                    } else {
                        $emailUpdateErrors[] = "Failed to update email. Please try again.";
                    }
                }
            } catch (PDOException $e) {
                $emailUpdateErrors[] = "Database error updating email: " . $e->getMessage();
            }
        }
    }

    // --- Handle Password Change ---
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmNewPassword = $_POST['confirm_new_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmNewPassword)) {
            $passwordChangeErrors[] = "All password fields are required.";
        } elseif ($newPassword !== $confirmNewPassword) {
            $passwordChangeErrors[] = "New passwords do not match.";
        } else {
            try {
                // Fetch current password hash
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
                $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
                $stmt->execute();
                $user = $stmt->fetch();

                if ($user && password_verify($currentPassword, $user['password_hash'])) {
                    // Current password is correct, hash new password
                    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

                    // Update password
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :id");
                    $stmt->bindParam(':password_hash', $newPasswordHash, PDO::PARAM_STR);
                    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);

                    if ($stmt->execute()) {
                        $passwordChangeSuccess = "Password changed successfully!";
                    } else {
                        $passwordChangeErrors[] = "Failed to change password. Please try again.";
                    }
                } else {
                    $passwordChangeErrors[] = "Incorrect current password.";
                }
            } catch (PDOException $e) {
                $passwordChangeErrors[] = "Database error changing password: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f9f9f9; color: #333; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.05); max-width: 600px; margin: auto; }
        h2, h3 { color: #333; }
        .form-section { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .form-section:last-child { border-bottom: none; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="email"], input[type="password"] {
            width: calc(100% - 22px); padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;
        }
        button[type="submit"] {
            background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;
        }
        button[type="submit"]:hover { background-color: #0056b3; }
        .messages p { padding: 10px; border-radius: 4px; margin-bottom: 10px; }
        .error { color: #d9534f; background-color: #f2dede; border: 1px solid #ebccd1; }
        .success { color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; }
        .info { font-size: 0.9em; color: #555; }
        .nav-links { margin-top: 20px; text-align: center; }
        .nav-links a { margin: 0 10px; color: #007bff; text-decoration: none; }
        .nav-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <h2>User Profile</h2>
    <p class="info"><strong>Username:</strong> <?php echo htmlspecialchars($username); ?> (Username cannot be changed)</p>

    <!-- Email Update Section -->
    <div class="form-section">
        <h3>Update Email</h3>
        <div class="messages">
            <?php foreach ($emailUpdateErrors as $error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
            <?php if ($emailUpdateSuccess): ?>
                <p class="success"><?php echo htmlspecialchars($emailUpdateSuccess); ?></p>
            <?php endif; ?>
        </div>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="current_email">Current Email:</label>
                <input type="email" id="current_email" name="current_email" value="<?php echo htmlspecialchars($currentEmail); ?>" readonly disabled>
            </div>
            <div class="form-group">
                <label for="new_email">New Email:</label>
                <input type="email" id="new_email" name="new_email" required>
            </div>
            <button type="submit" name="update_email">Update Email</button>
        </form>
    </div>

    <!-- Password Change Section -->
    <div class="form-section">
        <h3>Change Password</h3>
        <div class="messages">
            <?php foreach ($passwordChangeErrors as $error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
            <?php if ($passwordChangeSuccess): ?>
                <p class="success"><?php echo htmlspecialchars($passwordChangeSuccess); ?></p>
            <?php endif; ?>
        </div>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="current_password">Current Password:</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            <div class="form-group">
                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_new_password">Confirm New Password:</label>
                <input type="password" id="confirm_new_password" name="confirm_new_password" required>
            </div>
            <button type="submit" name="change_password">Change Password</button>
        </form>
    </div>

    <div class="nav-links">
        <a href="dashboard.php">Back to Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

</body>
</html>
