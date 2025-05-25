<?php

// Include bootstrap.php to load environment variables and other configurations
require_once __DIR__ . '/bootstrap.php';

// Database connection details from environment variables
$dbHost = $_ENV['DB_HOST'] ?? '';
$dbName = $_ENV['DB_DATABASE'] ?? '';
$dbUser = $_ENV['DB_USERNAME'] ?? '';
$dbPassword = $_ENV['DB_PASSWORD'] ?? '';

// Validate that essential database configuration is set
if (empty($dbHost)) {
    echo "Error: DB_HOST environment variable is not set. Please configure it in your .env file.\n";
    exit(1);
}
if (empty($dbName)) {
    echo "Error: DB_DATABASE environment variable is not set. Please configure it in your .env file.\n";
    exit(1);
}
if (empty($dbUser)) {
    echo "Error: DB_USERNAME environment variable is not set. Please configure it in your .env file.\n";
    exit(1);
}
// DB_PASSWORD can be empty for some local MySQL setups, so we don't strictly require it.

// PDO DSN (Data Source Name)
$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on error
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch results as associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements
];

try {
    // Attempt to connect to the database
    $pdo = new PDO($dsn, $dbUser, $dbPassword, $options);
    echo "Connected to database successfully.\n";

    // SQL statement to create the 'roles' table
    $sqlCreateRoles = "CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) UNIQUE NOT NULL
    );";

    try {
        $pdo->exec($sqlCreateRoles);
        echo "Table 'roles' created successfully (or already exists).\n";
    } catch (PDOException $e) {
        echo "Error creating 'roles' table: " . $e->getMessage() . "\n";
    }

    // SQL statement to create the 'users' table
    $sqlCreateUsers = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) UNIQUE NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role_id INT,
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
    );";

    try {
        $pdo->exec($sqlCreateUsers);
        echo "Table 'users' created successfully (or already exists).\n";
    } catch (PDOException $e) {
        echo "Error creating 'users' table: " . $e->getMessage() . "\n";
    }

    // SQL statement to create the 'moderators' table
    $sqlCreateModerators = "CREATE TABLE IF NOT EXISTS moderators (
        id INT AUTO_INCREMENT PRIMARY KEY,
        discord_id VARCHAR(255) UNIQUE NOT NULL,
        discord_username VARCHAR(255) NOT NULL,
        discord_avatar_url VARCHAR(255) NULL,
        last_fetched_at TIMESTAMP NULL DEFAULT NULL
    );";

    try {
        $pdo->exec($sqlCreateModerators);
        echo "Table 'moderators' created successfully (or already exists).\n";
    } catch (PDOException $e) {
        echo "Error creating 'moderators' table: " . $e->getMessage() . "\n";
    }

    // SQL statement to create the 'guides' table
    $sqlCreateGuides = "CREATE TABLE IF NOT EXISTS guides (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        author_id INT NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
    );";

    try {
        $pdo->exec($sqlCreateGuides);
        echo "Table 'guides' created successfully (or already exists).\n";
    } catch (PDOException $e) {
        echo "Error creating 'guides' table: " . $e->getMessage() . "\n";
    }

    // SQL statement to create the 'guide_revisions' table
    $sqlCreateGuideRevisions = "CREATE TABLE IF NOT EXISTS guide_revisions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        guide_id INT NOT NULL,
        content TEXT NOT NULL,
        editor_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (guide_id) REFERENCES guides(id) ON DELETE CASCADE,
        FOREIGN KEY (editor_id) REFERENCES users(id) ON DELETE CASCADE
    );";

    try {
        $pdo->exec($sqlCreateGuideRevisions);
        echo "Table 'guide_revisions' created successfully (or already exists).\n";
    } catch (PDOException $e) {
        echo "Error creating 'guide_revisions' table: " . $e->getMessage() . "\n";
    }

    // SQL statement to insert default roles
    $sqlInsertRoles = "INSERT IGNORE INTO roles (name) VALUES ('admin'), ('moderator'), ('editor'), ('user');";

    try {
        $pdo->exec($sqlInsertRoles);
        echo "Default roles inserted successfully (or already existed).\n";
    } catch (PDOException $e) {
        echo "Error inserting default roles: " . $e->getMessage() . "\n";
    }

} catch (PDOException $e) {
    // Catch connection errors
    echo "Database connection failed: " . $e->getMessage() . "\n";
    exit(1); // Exit if connection fails
}

?>
