<?php
session_start(); // Optional: for flash messages, though direct variable passing is used here

// Adjust the path to bootstrap.php based on the current file location
require_once __DIR__ . '/../../bootstrap.php';

// Database connection details from environment variables (loaded by bootstrap.php)
$dbHost = $_ENV['DB_HOST'] ?? '';
$dbName = $_ENV['DB_DATABASE'] ?? '';
$dbUser = $_ENV['DB_USERNAME'] ?? '';
$dbPassword = $_ENV['DB_PASSWORD'] ?? '';

// Initialize variables for messages and input
$errors = [];
$successMessage = '';
$username = '';
$email = '';

// --- Form Processing Logic ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize input
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // --- Validation ---
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    // Optional: Add password strength validation here
    // Example: if (strlen($password) < 8) { $errors[] = "Password must be at least 8 characters long."; }

    // --- If Validation Passes, Proceed with Database Operations ---
    if (empty($errors)) {
        // PDO DSN and options
        $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $dbUser, $dbPassword, $options);

            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Username or email already exists.";
            } else {
                // Hash the password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $default_role_id = 4; // Default role 'user'

                // Insert the new user
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role_id) VALUES (:username, :email, :password_hash, :role_id)");
                $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
                $stmt->bindParam(':role_id', $default_role_id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $successMessage = "Registration successful! You can now log in.";
                    // Clear form fields after successful registration
                    $username = '';
                    $email = '';
                } else {
                    $errors[] = "Registration failed. Please try again later.";
                }
            }
        } catch (PDOException $e) {
            // Log error for debugging: error_log("Database error: " . $e->getMessage());
            $errors[] = "Database error. Please try again later. " . $e->getMessage(); // Show detailed error for debugging only
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration</title>
    <!-- You might want to link a CSS file for styling -->
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 400px; margin: auto; }
        h2 { text-align: center; color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input[type="text"], input[type="email"], input[type="password"] {
            width: calc(100% - 20px); padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;
        }
        button[type="submit"] {
            background-color: #5cb85c; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%;
        }
        button[type="submit"]:hover { background-color: #4cae4c; }
        .messages { margin-top: 20px; }
        .error { color: #d9534f; background-color: #f2dede; border: 1px solid #ebccd1; padding: 10px; border-radius: 4px; margin-bottom: 10px;}
        .success { color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 10px; border-radius: 4px; margin-bottom: 10px;}
    </style>
</head>
<body>

<div class="container">
    <h2>Register</h2>

    <div class="messages">
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <p class="success"><?php echo htmlspecialchars($successMessage); ?></p>
        <?php endif; ?>
    </div>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
        </div>

        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
        </div>

        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>

        <button type="submit">Register</button>
    </form>
</div>

</body>
</html>
