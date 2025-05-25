<?php

include __DIR__ . '/../../bootstrap.php';

use App\Models\User;

// Read and decode JSON input
$requestData = json_decode(file_get_contents("php://input"), true);

// Check if JSON is valid and 'function' key is present
if (!$requestData || !isset($requestData['function'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON or missing function key', 'status' => 400]);
    exit;
}

$functionName = $requestData['function'];

// Verify that the function exists
if (!function_exists($functionName)) {
    http_response_code(404);
    echo json_encode(['error' => 'Function not found', 'status' => 404]);
    exit;
}

// Prepare arguments
$params = [];
if ($functionName === 'userHandler') {
    if (!isset($requestData['username']) || !isset($requestData['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing username or password for userHandler', 'status' => 400]);
        exit;
    }
    $params[] = $requestData['username'];
    $params[] = $requestData['password'];
} else {
    // For other functions, pass all parameters except 'function'
    $params = $requestData;
    unset($params['function']);
    $params = array_values($params); // Ensure numeric array for call_user_func_array
}

// Call the function
call_user_func_array($functionName, $params);

function userHandler(string $username, string $password)
{
    $user = User::select(['username' => $username]);

    $firstUser = $user[0] ?? null;

    if (!$firstUser) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found', 'status' => 404]);
        exit;
    }

    if (password_verify($password, $firstUser->password)) {
        $_SESSION['user'] = $firstUser->id;
        echo json_encode(['success' => 'User logged in', 'status' => 200]);
        exit;
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Password incorrect', 'status' => 401]);
        exit;
    }
}