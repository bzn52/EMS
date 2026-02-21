<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
if (!defined('APP_INIT'))
    define('APP_INIT', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

Auth::requireLogin();
Auth::requireRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    header('Location: ../dashboard_student.php');
    exit;
}

$eventId = (int) ($_POST['event_id'] ?? 0);
$action = $_POST['action'] ?? '';
$studentId = Auth::id();

if (!$eventId || !in_array($action, ['join', 'leave'])) {
    header('Location: ../dashboard_student.php');
    exit;
}

// Verify event is approved
$chk = $conn->prepare("SELECT id FROM events WHERE id=? AND status='approved'");
$chk->bind_param('i', $eventId);
$chk->execute();
if ($chk->get_result()->num_rows === 0) {
    header('Location: ../dashboard_student.php');
    exit;
}

if ($action === 'join') {
    $stmt = $conn->prepare("INSERT IGNORE INTO event_registrations (event_id, student_id) VALUES (?, ?)");
    $stmt->bind_param('ii', $eventId, $studentId);
    $stmt->execute();
    $_SESSION['event_success'] = 'You have joined the event!';
} else {
    $stmt = $conn->prepare("DELETE FROM event_registrations WHERE event_id=? AND student_id=?");
    $stmt->bind_param('ii', $eventId, $studentId);
    $stmt->execute();
    $_SESSION['event_success'] = 'You have left the event.';
}

header('Location: view.php?id=' . $eventId);
exit;