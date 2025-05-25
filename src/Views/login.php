<?php
session_start();

// Adjust the path to bootstrap.php based on the current file location
require_once __DIR__ . '/../../bootstrap.php';

// Database connection details from environment variables (loaded by bootstrap.php)
$dbHost = $_ENV['DB_HOST'] ?? '';
$dbName = $_ENV['DB_DATABASE'] ?? '';
$dbUser = $_ENV['DB_USERNAME'] ?? '';
$dbPassword = $_ENV['DB_PASSWORD'] ?? '';

$errors = [];
$username_or_email = '';

// --- Form Processing Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_or_email = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';

    // --- Validation ---
    if (empty($username_or_email)) {
        $errors[] = "Username or Email is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    // --- If Validation Passes, Proceed with Database Operations ---
    if (empty($errors)) {
        $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $dbUser, $dbPassword, $options);

            // Prepare a statement to fetch the user by username or email
            $stmt = $pdo->prepare("SELECT id, username, email, password_hash, role_id FROM users WHERE username = :username_or_email OR email = :username_or_email LIMIT 1");
            $stmt->bindParam(':username_or_email', $username_or_email, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch();

            if ($user) {
                // Verify the password
                if (password_verify($password, $user['password_hash'])) {
                    // Password is correct, store user info in session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['logged_in'] = true;

                    // Redirect to a dashboard page (create this page next)
                    header("Location: dashboard.php");
                    exit;
                } else {
                    // Invalid password
                    $errors[] = "Invalid username/email or password.";
                }
            } else {
                // No user found with that username/email
                $errors[] = "Invalid username/email or password."; // Generic message for security
            }
        } catch (PDOException $e) {
            // Log error for debugging: error_log("Database error: " . $e->getMessage());
            $errors[] = "Database error. Please try again later."; // $e->getMessage(); // Show detailed error for debugging only
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 400px; margin: auto; }
        h2 { text-align: center; color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input[type="text"], input[type="password"] {
            width: calc(100% - 20px); padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;
        }
        button[type="submit"] {
            background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%;
        }
        button[type="submit"]:hover { background-color: #0056b3; }
        .messages { margin-top: 20px; }
        .error { color: #d9534f; background-color: #f2dede; border: 1px solid #ebccd1; padding: 10px; border-radius: 4px; margin-bottom: 10px;}
    </style>
</head>
<body>

<div class="container">
    <h2>Login</h2>

    <div class="messages">
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="form-group">
            <label for="username_or_email">Username or Email:</label>
            <input type="text" id="username_or_email" name="username_or_email" value="<?php echo htmlspecialchars($username_or_email); ?>" required>
        </div>

        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit">Login</button>
    </form>
    <p style="text-align: center; margin-top: 15px;">
        Don't have an account? <a href="register.php">Register here</a>
    </p>
</div>

</body>
</html>
