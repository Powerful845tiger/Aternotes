<?php

include_once __DIR__ . '/../../bootstrap.php';

use App\Models\Moderator;

/**
 * Helper function to fetch user profile from Discord API.
 *
 * @param string $discordId The Discord User ID.
 * @return array|null The user data array or null on failure.
 */
function fetchDiscordUserProfile(string $discordId): ?array
{
    $botToken = $_ENV['DISCORD_BOT_TOKEN'] ?? getenv('DISCORD_BOT_TOKEN');
    if (!$botToken) {
        // Log error or handle missing token
        error_log("Discord Bot Token not configured.");
        return null;
    }

    $url = "https://discord.com/api/v10/users/{$discordId}";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bot ' . $botToken],
        CURLOPT_USERAGENT => 'AternotesModeratorManager/1.0 (+https://aternotes.org)' // Recommended to set a User-Agent
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("cURL Error fetching Discord profile for {$discordId}: " . $curlError);
        return null;
    }

    if ($httpCode === 200) {
        return json_decode($response, true);
    } else {
        error_log("Discord API error for {$discordId}: HTTP {$httpCode} - Response: {$response}");
        return null;
    }
}

/**
 * Adds a new moderator.
 * Expects JSON: { "discord_id": "..." }
 */
function addModerator(array $params) {
    header('Content-Type: application/json');
    $discordId = $params['discord_id'] ?? null;

    if (!$discordId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Discord ID is required.']);
        exit;
    }
    
    // Check if moderator already exists
    $existingModerator = Moderator::get(['discord_id' => $discordId]);
    if ($existingModerator) {
        http_response_code(409); // Conflict
        echo json_encode(['status' => 'error', 'message' => 'Moderator with this Discord ID already exists.']);
        exit;
    }

    $userData = fetchDiscordUserProfile($discordId);

    if (!$userData) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch user profile from Discord or user not found.']);
        exit;
    }

    $moderator = new Moderator();
    $moderator->discord_id = $userData['id'];
    $moderator->discord_username = $userData['username'] . '#' . $userData['discriminator'];
    if (isset($userData['avatar']) && $userData['avatar']) {
        $moderator->discord_avatar_url = "https://cdn.discordapp.com/avatars/" . $userData['id'] . "/" . $userData['avatar'] . ".png";
    } else {
        $moderator->discord_avatar_url = null;
    }
    $moderator->last_fetched_at = date('Y-m-d H:i:s'); // Current timestamp

    try {
        $moderator->save();
        http_response_code(201); // Created
        echo json_encode(['status' => 'success', 'message' => 'Moderator added successfully.', 'data' => $moderator->getAttributes()]);
    } catch (Exception $e) {
        error_log("Error saving moderator: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save moderator.']);
    }
    exit;
}

/**
 * Removes an existing moderator.
 * Expects JSON: { "id": "..." } (database ID) or { "discord_id": "..." }
 */
function removeModerator(array $params) {
    header('Content-Type: application/json');
    $id = $params['id'] ?? null;
    $discordId = $params['discord_id'] ?? null;

    if (!$id && !$discordId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Moderator ID or Discord ID is required.']);
        exit;
    }

    $moderator = null;
    if ($id) {
        $moderator = Moderator::get($id);
    } elseif ($discordId) {
        $moderator = Moderator::get(['discord_id' => $discordId]);
    }

    if (!$moderator) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Moderator not found.']);
        exit;
    }

    try {
        $moderator->delete();
        echo json_encode(['status' => 'success', 'message' => 'Moderator removed successfully.']);
    } catch (Exception $e) {
        error_log("Error deleting moderator: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to remove moderator.']);
    }
    exit;
}

/**
 * Lists all moderators.
 */
function listModerators() {
    header('Content-Type: application/json');
    try {
        $moderators = Moderator::select();
        $moderatorData = array_map(fn($mod) => $mod->getAttributes(), $moderators);
        echo json_encode(['status' => 'success', 'data' => $moderatorData]);
    } catch (Exception $e) {
        error_log("Error listing moderators: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to list moderators.']);
    }
    exit;
}

/**
 * Refreshes a moderator's profile from Discord API.
 * Expects JSON: { "id": "..." } (database ID) or { "discord_id": "..." }
 */
function refreshModeratorProfile(array $params) {
    header('Content-Type: application/json');
    $id = $params['id'] ?? null;
    $discordId = $params['discord_id'] ?? null;

    if (!$id && !$discordId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Moderator ID or Discord ID is required for refresh.']);
        exit;
    }
    
    $moderator = null;
    if ($id) {
        $moderator = Moderator::get($id);
    } elseif ($discordId) {
        $moderator = Moderator::get(['discord_id' => $discordId]);
    }

    if (!$moderator) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Moderator not found for refresh.']);
        exit;
    }

    $userData = fetchDiscordUserProfile($moderator->discord_id);

    if (!$userData) {
        http_response_code(404); // Or 502 if Discord API fails
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch updated user profile from Discord.']);
        exit;
    }

    $moderator->discord_username = $userData['username'] . '#' . $userData['discriminator'];
    if (isset($userData['avatar']) && $userData['avatar']) {
        $moderator->discord_avatar_url = "https://cdn.discordapp.com/avatars/" . $userData['id'] . "/" . $userData['avatar'] . ".png";
    } else {
        $moderator->discord_avatar_url = null;
    }
    $moderator->last_fetched_at = date('Y-m-d H:i:s');

    try {
        $moderator->save();
        echo json_encode(['status' => 'success', 'message' => 'Moderator profile refreshed successfully.', 'data' => $moderator->getAttributes()]);
    } catch (Exception $e) {
        error_log("Error saving refreshed moderator profile: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save refreshed moderator profile.']);
    }
    exit;
}

// Basic dispatcher logic
$requestData = json_decode(file_get_contents("php://input"), true);
$action = $requestData['action'] ?? $_GET['action'] ?? null; // Allow action via GET for simpler testing if needed
$params = $requestData['data'] ?? $_POST['data'] ?? $_GET['data'] ?? []; // Allow data via POST/GET for simpler testing

if (!$action && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['action'])) {
    // Default to listModerators if it's a GET request with no action specified (e.g. direct access to this script)
    // This is a convention, adjust if a different default behavior is needed.
    if (basename($_SERVER['PHP_SELF']) === 'ModeratorController.php') { // Check if this script is directly accessed
         $action = 'listModerators';
    }
}


if ($action) {
    if (function_exists($action)) {
        // Pass params to functions that expect them
        if (in_array($action, ['addModerator', 'removeModerator', 'refreshModeratorProfile'])) {
            call_user_func($action, $params);
        } else {
            call_user_func($action);
        }
    } else {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(404);
        }
        echo json_encode(['status' => 'error', 'message' => "Action '{$action}' not found."]);
        exit;
    }
} else {
    // Only output this if no action was called, and it wasn't the default list action
     if (basename($_SERVER['PHP_SELF']) === 'ModeratorController.php' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(400);
        }
        echo json_encode(['status' => 'error', 'message' => 'No action specified.']);
        exit;
    }
}
?>
