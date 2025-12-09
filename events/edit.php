<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/events_common.php';
Auth::requireLogin();
Auth::requireRole(['teacher', 'admin']);

$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$eventId) {
    http_response_code(400);
    die('Invalid event ID.');
}

// Load event
$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param('i', $eventId);
if (!$stmt->execute()) die('DB error: ' . $stmt->error);
$result = $stmt->get_result();
$event = $result->fetch_assoc();
$stmt->close();

if (!$event) {
    http_response_code(404);
    die('Event not found.');
}

$userId = Auth::id();
$role = Auth::role();

if ($role === 'teacher') {
    // Check if this teacher created this event
    if (!isset($event['created_by']) || (int)$event['created_by'] !== $userId) {
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
                                <p class="empty-state-text">You can only edit events that you created.</p>
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

// If we reach here, user has permission to edit
$title = $event['title'];
$description = $event['description'];
$currentImage = $event['image'] ?? '';
$status = $event['status'] ?? 'pending';
$showStatus = ($role === 'admin'); // Only admin can change status

$uploadsDir = EVENTS_UPLOADS_DIR;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security validation failed. Please try again.';
    } else {
        $title = Input::text($_POST['title'] ?? '', 255);
        $description = Input::text($_POST['description'] ?? '', 5000);
        $newStatus = $showStatus ? ($_POST['status'] ?? 'pending') : $status;

        if (strlen($title) < 3) {
            $errors[] = 'Title must be at least 3 characters.';
        }

        $imageName = $currentImage;
        if (!empty($_FILES['image']['name'])) {
            $uploadErrors = FileUpload::validate($_FILES['image']);
            
            if (!empty($uploadErrors)) {
                $errors = array_merge($errors, $uploadErrors);
            } else {
                $imageName = FileUpload::generateSecureName($_FILES['image']['name']);
                $dest = rtrim($uploadsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $imageName;
                
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                    $errors[] = 'Failed to move uploaded file.';
                    $imageName = $currentImage;
                } else {
                    // Delete old image
                    if (!empty($currentImage) && $currentImage !== $imageName) {
                        $oldPath = rtrim($uploadsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $currentImage;
                        if (file_exists($oldPath)) @unlink($oldPath);
                    }
                }
            }
        }

        if (empty($errors)) {
            if ($showStatus) {
                $sql = "UPDATE events SET title=?, description=?, image=?, status=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssssi', $title, $description, $imageName, $newStatus, $eventId);
            } else {
                $sql = "UPDATE events SET title=?, description=?, image=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sssi', $title, $description, $imageName, $eventId);
            }

            if ($stmt->execute()) {
                $stmt->close();
                
                header('Location: ' . ($role === 'admin' ? '../dashboard_admin.php' : '../dashboard_teacher.php'));
                exit;
            } else {
                $errors[] = 'Update failed: ' . $stmt->error;
                $stmt->close();
            }
        }
    }
}

function e($s) { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Edit Event</title>
  <link rel="stylesheet" href="../styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
  <div class="page-wrapper">
    <header>
      <div class="header-content">
        <div class="header-left">
          <h1><i class="fas fa-edit"></i> Edit Event</h1>
        </div>
        <div class="header-right">
          <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr(Auth::name(), 0, 1)) ?></div>
            <div>
              <div><?= e(Auth::name()) ?></div>
              <span class="user-role-badge"><?= e($role) ?></span>
            </div>
          </div>
          <nav class="nav-links">
            <a href="<?= $role === 'admin' ? '../dashboard_admin.php' : '../dashboard_teacher.php' ?>">Back</a>
            <a href="../logout.php">Logout</a>
          </nav>
        </div>
      </div>
    </header>

    <main>
      <div class="container container-sm">
        <?php if ($errors): ?>
          <div class="message message-error">
            <?= implode('<br>', array_map('e', $errors)) ?>
          </div>
        <?php endif; ?>

        <div class="card">
          <div class="card-header">
            <h2 class="card-title">Edit Event Information</h2>
          </div>
          <div class="card-body">
            <form method="post" enctype="multipart/form-data">
              <?= CSRF::field() ?>
              
              <div class="form-group">
                <label class="form-label">Event Title *</label>
                <input 
                  type="text" 
                  name="title" 
                  value="<?= e($title) ?>" 
                  required
                  minlength="3"
                  maxlength="255"
                >
              </div>

              <div class="form-group">
                <label class="form-label">Description</label>
                <textarea 
                  name="description" 
                  rows="8"
                ><?= e($description) ?></textarea>
              </div>

              <div class="form-group">
                <label class="form-label">Event Image</label>
                <?php if ($currentImage): ?>
                  <div style="margin-bottom: 1rem;">
                    <img src="../uploads/<?= e($currentImage) ?>" style="max-width: 300px; border-radius: 8px;">
                    <p class="text-muted text-sm" style="margin-top: 0.5rem;">Current image</p>
                  </div>
                <?php endif; ?>
                <input 
                  type="file" 
                  name="image" 
                  accept="image/jpeg,image/png,image/gif,image/webp"
                  onchange="previewImage(this, 'image-preview')"
                >
                <small class="text-muted" style="display: block; margin-top: 0.5rem;">
                  Upload new image to replace current one. Max size: 5MB
                </small>
                <img id="image-preview" style="display: none; max-width: 300px; margin-top: 1rem; border-radius: 8px;">
              </div>

              <?php if ($showStatus): ?>
                <div class="form-group">
                  <label class="form-label">Status (Admin Only)</label>
                  <select name="status" required>
                    <option value="pending" <?= $status==='pending' ? 'selected':'' ?>>Pending</option>
                    <option value="approved" <?= $status==='approved' ? 'selected':'' ?>>Approved</option>
                    <option value="rejected" <?= $status==='rejected' ? 'selected':'' ?>>Rejected</option>
                  </select>
                </div>
              <?php else: ?>
                <div class="message message-info">
                  <i class="fas fa-info-circle"></i> Current status: <strong><?= e(ucfirst($status)) ?></strong> (only admins can change status)
                </div>
              <?php endif; ?>

              <div class="form-group">
                <button type="submit" class="btn">Save Changes</button>
                <a href="view.php?id=<?= $eventId ?>" class="btn btn-outline">Cancel</a>
              </div>
            </form>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="../script.js"></script>
</body>
</html>