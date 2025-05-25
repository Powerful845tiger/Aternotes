<?php

include_once __DIR__ . '/../../bootstrap.php'; // Ensures session_start() is called

use App\Models\Guide;
use Parsedown;

/**
 * Generates a URL-friendly slug from a title.
 *
 * @param string $title
 * @return string
 */
function generateUniqueSlug(string $title): string {
    $slug = strtolower($title);
    $slug = preg_replace('/\s+/', '-', $slug); // Replace spaces with hyphens
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug); // Remove non-alphanumeric except hyphens
    $slug = trim($slug, '-'); // Trim leading/trailing hyphens

    $originalSlug = $slug;
    $counter = 2;
    while (Guide::get(['slug' => $slug])) {
        $slug = $originalSlug . '-' . $counter++;
    }
    return $slug;
}

/**
 * Creates a new guide.
 * Expects JSON { "title": "...", "content_markdown": "..." }
 */
function createGuide(array $params) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'User not authenticated.']);
        exit;
    }
    $author_id = $_SESSION['user'];

    $title = $params['title'] ?? null;
    $content_markdown = $params['content_markdown'] ?? null;

    if (empty($title) || empty($content_markdown)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Title and content_markdown are required.']);
        exit;
    }

    $slug = generateUniqueSlug($title);
    
    $parsedown = new Parsedown();
    $content_html = $parsedown->text($content_markdown);

    $guide = new Guide();
    $guide->title = $title;
    $guide->slug = $slug;
    $guide->content_markdown = $content_markdown;
    $guide->content_html = $content_html;
    $guide->author_id = $author_id;
    $guide->status = 'draft'; // Default status
    // created_at and updated_at are handled by DB or model defaults if configured

    try {
        $guide->save();
        http_response_code(201); // Created
        echo json_encode(['status' => 'success', 'message' => 'Guide created successfully.', 'data' => $guide->getAttributes()]);
    } catch (Exception $e) {
        error_log("Error creating guide: " . $e->getMessage());
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Failed to create guide.']);
    }
    exit;
}

/**
 * Updates an existing guide.
 * Expects JSON { "guide_id": "...", "title": "...", "content_markdown": "..." }
 */
function updateGuide(array $params) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'User not authenticated.']);
        exit;
    }
    $current_user_id = $_SESSION['user'];

    $guide_id = $params['guide_id'] ?? null;
    if (!$guide_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Guide ID is required.']);
        exit;
    }

    $guide = Guide::get($guide_id);
    if (!$guide) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Guide not found.']);
        exit;
    }

    // Authorization: Only author can edit (add admin check later if needed)
    if ($guide->author_id != $current_user_id) {
        http_response_code(403); // Forbidden
        echo json_encode(['status' => 'error', 'message' => 'You are not authorized to update this guide.']);
        exit;
    }

    $title = $params['title'] ?? $guide->title;
    $content_markdown = $params['content_markdown'] ?? $guide->content_markdown;

    $guide->title = $title;
    $guide->content_markdown = $content_markdown;

    $parsedown = new Parsedown();
    $guide->content_html = $parsedown->text($content_markdown);
    // Slug is not updated on edit to maintain stable URLs

    try {
        $guide->save();
        echo json_encode(['status' => 'success', 'message' => 'Guide updated successfully.', 'data' => $guide->getAttributes()]);
    } catch (Exception $e) {
        error_log("Error updating guide: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update guide.']);
    }
    exit;
}

/**
 * Gets a single guide.
 * Expects JSON { "guide_id": "..." } or { "slug": "..." }
 */
function getGuide(array $params) {
    header('Content-Type: application/json');
    $guide_id = $params['guide_id'] ?? null;
    $slug = $params['slug'] ?? null;

    if (!$guide_id && !$slug) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Guide ID or slug is required.']);
        exit;
    }

    $guide = null;
    if ($guide_id) {
        $guide = Guide::get($guide_id);
    } elseif ($slug) {
        $guide = Guide::get(['slug' => $slug]);
    }

    if (!$guide) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Guide not found.']);
        exit;
    }

    // Check permissions for non-published guides
    if ($guide->status !== 'published') {
        // If fetching by slug for a public user (not logged in), treat as not found.
        if ($slug && !isset($_SESSION['user'])) {
             http_response_code(404);
             echo json_encode(['status' => 'error', 'message' => 'Guide not found or not published.']);
             exit;
        }
        // If fetched by ID, or by slug by a logged-in user, check ownership for non-published guides.
        // This allows authors to view their own non-published guides.
        // TODO: Add admin/moderator role check here to allow them to view any guide.
        if (!isset($_SESSION['user']) || ($guide->author_id != $_SESSION['user'])) {
            http_response_code(403); // Forbidden
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission to view this guide.']);
            exit;
        }
    }
    
    $guideAttributes = $guide->getAttributes();
    $author = \App\Models\User::get($guide->author_id);
    $guideAttributes['author_username'] = $author ? $author->username : 'Unknown';

    // For public consumption of published guides, we might not want to expose all fields.
    // However, for now, returning all attributes for simplicity.
    // If this controller action is also used by internal/dashboard views, they might need all fields.
    // Consider creating a separate "publicGetGuide" action if field filtering becomes important.
    echo json_encode(['status' => 'success', 'data' => $guideAttributes]);
    exit;
}

/**
 * Lists guides for the current logged-in user.
 */
function listUserGuides(array $params = []) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'User not authenticated.']);
        exit;
    }
    $author_id = $_SESSION['user'];

    try {
        $guides = Guide::select(['author_id' => $author_id]);
        $guidesData = array_map(fn($guide) => $guide->getAttributes(), $guides);
        echo json_encode(['status' => 'success', 'data' => $guidesData]);
    } catch (Exception $e) {
        error_log("Error listing user guides: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to list user guides.']);
    }
    exit;
}

/**
 * Submits a guide for review.
 * Expects JSON { "guide_id": "..." }
 */
function submitForReview(array $params) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'User not authenticated.']);
        exit;
    }
    $current_user_id = $_SESSION['user'];

    $guide_id = $params['guide_id'] ?? null;
    if (!$guide_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Guide ID is required.']);
        exit;
    }

    $guide = Guide::get($guide_id);
    if (!$guide) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Guide not found.']);
        exit;
    }

    if ($guide->author_id != $current_user_id) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'You are not authorized to submit this guide for review.']);
        exit;
    }

    if ($guide->status !== 'draft') {
        http_response_code(400); // Bad Request or 409 Conflict
        echo json_encode(['status' => 'error', 'message' => 'Only guides in draft status can be submitted for review. Current status: ' . $guide->status]);
        exit;
    }

    $guide->status = 'pending_review';

    try {
        $guide->save();
        echo json_encode(['status' => 'success', 'message' => 'Guide submitted for review.', 'data' => $guide->getAttributes()]);
    } catch (Exception $e) {
        error_log("Error submitting guide for review: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to submit guide for review.']);
    }
    exit;
}

/**
 * Lists all published guides.
 * Includes author's username.
 */
function listPublishedGuides(array $params = []) {
    header('Content-Type: application/json');
    try {
        // Assuming 'published_at' is the correct field for ordering.
        // The model library might not support direct ordering in select().
        // If so, sorting would need to be done in PHP after fetching, or with a raw query.
        // For now, we fetch all published and then can sort if necessary.
        $publishedGuides = Guide::select(['status' => 'published']);
        
        // Manually sort by published_at DESC if ORM doesn't support it directly
        // This is not super efficient for large datasets but works for moderate numbers.
        usort($publishedGuides, function($a, $b) {
            $timeA = $a->published_at ? strtotime($a->published_at) : 0;
            $timeB = $b->published_at ? strtotime($b->published_at) : 0;
            return $timeB <=> $timeA; // Sort descending
        });

        $guidesData = [];
        foreach ($publishedGuides as $guide) {
            $guideAttributes = $guide->getAttributes();
            $author = \App\Models\User::get($guide->author_id);
            $guideAttributes['author_username'] = $author ? $author->username : 'Unknown';
            // Select only necessary fields for public listing
            $guidesData[] = [
                'title' => $guideAttributes['title'],
                'slug' => $guideAttributes['slug'],
                'author_username' => $guideAttributes['author_username'],
                'published_at' => $guideAttributes['published_at'],
                // Optionally, a short snippet of content_html (e.g., first 100 chars, stripped of HTML)
                // 'snippet' => substr(strip_tags($guideAttributes['content_html']), 0, 150) . '...'
            ];
        }
        echo json_encode(['status' => 'success', 'data' => $guidesData]);
    } catch (Exception $e) {
        error_log("Error listing published guides: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to list published guides.']);
    }
    exit;
}


/**
 * Lists guides pending review.
 * Includes author's username.
 */
function listGuidesForReview(array $params = []) {
    header('Content-Type: application/json');
    // TODO: Add permission check for moderators/admins

    try {
        $guidesPending = Guide::select(['status' => 'pending_review']);
        $guidesData = [];
        foreach ($guidesPending as $guide) {
            $guideAttributes = $guide->getAttributes();
            $author = \App\Models\User::get($guide->author_id);
            $guideAttributes['author_username'] = $author ? $author->username : 'Unknown';
            $guidesData[] = $guideAttributes;
        }
        echo json_encode(['status' => 'success', 'data' => $guidesData]);
    } catch (Exception $e) {
        error_log("Error listing guides for review: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to list guides for review.']);
    }
    exit;
}

/**
 * Approves a guide.
 * Expects JSON { "guide_id": "..." }
 */
function approveGuide(array $params) {
    header('Content-Type: application/json');
    // TODO: Add permission check for moderators/admins

    $guide_id = $params['guide_id'] ?? null;
    if (!$guide_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Guide ID is required.']);
        exit;
    }

    $guide = Guide::get($guide_id);
    if (!$guide) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Guide not found.']);
        exit;
    }

    if ($guide->status !== 'pending_review') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Guide is not pending review. Current status: ' . $guide->status]);
        exit;
    }

    $guide->status = 'published';
    $guide->published_at = date('Y-m-d H:i:s'); // Current timestamp

    try {
        $guide->save();
        echo json_encode(['status' => 'success', 'message' => 'Guide approved and published.', 'data' => $guide->getAttributes()]);
    } catch (Exception $e) {
        error_log("Error approving guide: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to approve guide.']);
    }
    exit;
}

/**
 * Rejects a guide.
 * Expects JSON { "guide_id": "..." }
 */
function rejectGuide(array $params) {
    header('Content-Type: application/json');
    // TODO: Add permission check for moderators/admins

    $guide_id = $params['guide_id'] ?? null;
    if (!$guide_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Guide ID is required.']);
        exit;
    }

    $guide = Guide::get($guide_id);
    if (!$guide) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Guide not found.']);
        exit;
    }

    if ($guide->status !== 'pending_review') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Guide is not pending review. Current status: ' . $guide->status]);
        exit;
    }

    $guide->status = 'rejected';
    // published_at remains null or its previous value

    try {
        $guide->save();
        echo json_encode(['status' => 'success', 'message' => 'Guide rejected.', 'data' => $guide->getAttributes()]);
    } catch (Exception $e) {
        error_log("Error rejecting guide: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to reject guide.']);
    }
    exit;
}


// Basic dispatcher logic (ensure session is started via bootstrap.php)
$requestData = json_decode(file_get_contents("php://input"), true);
$action = $requestData['action'] ?? $_GET['action'] ?? null;
$params = $requestData['data'] ?? $_POST['data'] ?? $_GET['data'] ?? [];

if (!$action && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['action'])) {
    if (basename($_SERVER['PHP_SELF']) === 'GuideController.php') {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(400);
        }
        echo json_encode(['status' => 'error', 'message' => 'Action required for GuideController.']);
        exit;
    }
}

if ($action) {
    if (function_exists($action)) {
        call_user_func($action, $params);
    } else {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(404);
        }
        echo json_encode(['status' => 'error', 'message' => "Action '{$action}' not found."]);
        exit;
    }
} else {
    if (basename($_SERVER['PHP_SELF']) === 'GuideController.php' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
       if (!headers_sent()) {
           header('Content-Type: application/json');
           http_response_code(400);
       }
       echo json_encode(['status' => 'error', 'message' => 'No action specified for GuideController.']);
       exit;
    }
}

?>
