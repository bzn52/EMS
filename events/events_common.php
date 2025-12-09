<?php
// Common include for all event pages

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load dependencies
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Sanity check: $conn should exist
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo "Database connection (\$conn) not found. Check config.php.";
    exit;
}

// Uploads directory
$UPLOADS_DIR = realpath(__DIR__ . '/../uploads') ?: __DIR__ . '/../uploads';

// Create uploads directory if needed
if (!is_dir($UPLOADS_DIR)) {
    if (!mkdir($UPLOADS_DIR, 0755, true) && !is_dir($UPLOADS_DIR)) {
        http_response_code(500);
        echo "Failed to create uploads directory: " . htmlspecialchars($UPLOADS_DIR);
        exit;
    }
}

// Ensure uploads directory is writable
if (!is_writable($UPLOADS_DIR)) {
    @chmod($UPLOADS_DIR, 0755);
    if (!is_writable($UPLOADS_DIR)) {
        http_response_code(500);
        echo "Uploads directory is not writable: " . htmlspecialchars($UPLOADS_DIR);
        exit;
    }
}

// Status column name
$EVENT_STATUS_COL = 'status';

/**
 * Verify the events table contains the status column
 */
function get_event_status_column(mysqli $conn, string $col = 'status') {
    $res = $conn->query("SHOW COLUMNS FROM `events` LIKE '" . $conn->real_escape_string($col) . "'");
    if ($res && $res->num_rows > 0) return $col;
    return null;
}

// Helper: safe escape for output
if (!function_exists('e')) {
    function e($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Helper: allowed image types
if (!function_exists('allowed_image_types')) {
    function allowed_image_types() {
        return ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    }
}

// Define constants
define('EVENTS_UPLOADS_DIR', $UPLOADS_DIR);
define('EVENTS_STATUS_COL', get_event_status_column($conn) ?? $EVENT_STATUS_COL);