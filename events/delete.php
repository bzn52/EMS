<?php
require_once __DIR__ . '/events_common.php';

Auth::requireLogin();
Auth::requireRole(['teacher', 'admin']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    http_response_code(400);
    die("Missing event ID");
}

// Fetch event details
$stmt = $conn->prepare("SELECT title, image, created_by FROM events WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    die("Event not found");
}

$userId = Auth::id();
$role = Auth::role();

if ($role === 'teacher') {
    // Check if this teacher created this event
    if (!isset($row['created_by']) || (int)$row['created_by'] !== $userId) {
        // NOT the creator - show error page
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>Access Denied</title>
            <link rel="stylesheet" href="../styles.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        </head>
        <body>
            <div class="page-wrapper">
                <main>
                    <div class="container container-sm">
                        <div class="card">
                            <div class="empty-state">
                                <div class="empty-state-icon"><i class="fas fa-ban fa-4x"></i></div>
                                <h2 class="empty-state-title">Access Denied</h2>
                                <p class="empty-state-text">You can only delete events that you created.</p>
                                <a href="../dashboard_teacher.php" class="btn btn-sm" style="margin-top: 1rem; width: auto; display: inline-block;">Go to Dashboard</a>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// If we reach here, user has permission to delete
$eventTitle = $row['title'];

// Delete from database
$stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    // Delete image file if exists
    if (!empty($row['image'])) {
        $imagePath = EVENTS_UPLOADS_DIR . '/' . $row['image'];
        if (file_exists($imagePath)) {
            @unlink($imagePath);
        }
    }
    
    $stmt->close();
    
    // Set success message
    $_SESSION['delete_success'] = "Event '{$eventTitle}' has been deleted successfully.";
    
    // Redirect based on role
    if ($role === 'admin') {
        header('Location: ../dashboard_admin.php');
    } else {
        header('Location: ../dashboard_teacher.php');
    }
    exit;
} else {
    http_response_code(500);
    die("Failed to delete event: " . $conn->error);
}