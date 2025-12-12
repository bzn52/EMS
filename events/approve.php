<?php
require_once __DIR__ . '/events_common.php';

Auth::requireLogin();
Auth::requireRole('admin');

function e($s) { 
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); 
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method not allowed. Use POST request.");
}

if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die("CSRF validation failed");
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$action = $_POST['action'] ?? '';

if (!$id || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    die("Invalid request parameters");
}

$stmt = $conn->prepare("SELECT id, title, status FROM events WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$event = $result->fetch_assoc();
$stmt->close();

if (!$event) {
    http_response_code(404);
    die("Event not found");
}

$newStatus = $action === 'approve' ? 'approved' : 'rejected';

$stmt = $conn->prepare("UPDATE events SET status = ?, approved_at = NOW(), approved_by = ? WHERE id = ?");
$approvedBy = Auth::id();
$stmt->bind_param('sii', $newStatus, $approvedBy, $id);

if ($stmt->execute()) {
    $stmt->close();
    
    if (isset($_POST['return']) && $_POST['return'] === 'view') {
        header('Location: view.php?id=' . $id);
    } else {
        header('Location: ../dashboard_admin.php');
    }
    exit;
} else {
    http_response_code(500);
    die("Failed to update status: " . $conn->error);
}