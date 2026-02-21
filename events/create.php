<?php
if (session_status() === PHP_SESSION_NONE)
  session_start();
if (!defined('APP_INIT'))
  define('APP_INIT', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

Auth::requireLogin();
Auth::requireRole(['teacher', 'admin']);

function e($s)
{
  return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$error = '';
$success = '';
$role = Auth::role();
$userId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid request. Please try again.';
  } else {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $event_time = $_POST['event_time'] ?? '';
    $imageName = null;

    if (empty($title) || empty($description)) {
      $error = 'Title and description are required.';
    } else {
      // Handle image upload
      if (!empty($_FILES['image']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed)) {
          $error = 'Invalid image type. Only JPG, PNG, GIF, WEBP allowed.';
        } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
          $error = 'Image must be under 5MB.';
        } else {
          $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
          $imageName = bin2hex(random_bytes(8)) . '.' . $ext;
          $dest = UPLOADS_DIR . '/' . $imageName;
          if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
            $error = 'Failed to upload image.';
            $imageName = null;
          }
        }
      }

      if (empty($error)) {
        $status = ($role === 'admin') ? 'approved' : 'pending';
        $stmt = $conn->prepare(
          "INSERT INTO events (title, description, event_date, event_time, image, status, created_by_type, created_by_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $dateVal = $event_date ?: null;
        $timeVal = $event_time ?: null;
        $stmt->bind_param('sssssssi', $title, $description, $dateVal, $timeVal, $imageName, $status, $role, $userId);
        if ($stmt->execute()) {
          $newId = $conn->insert_id;
          $_SESSION['event_success'] = 'Event created successfully!' . ($status === 'pending' ? ' Awaiting admin approval.' : '');
          header('Location: view.php?id=' . $newId);
          exit;
        } else {
          $error = 'Failed to create event. Please try again.';
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Create Event</title>
  <link rel="stylesheet" href="../styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
    integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body>
  <div class="page-wrapper">
    <header>
      <div class="header-content">
        <div class="header-left">
          <h1>Event Management System</h1>
        </div>
        <div class="header-right">
          <a href="<?= $role === 'admin' ? '../dashboard_admin.php' : '../dashboard_teacher.php' ?>"
            class="btn btn-outline btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
          </a>
        </div>
      </div>
    </header>

    <main>
      <div class="container" style="max-width: 680px;">
        <div class="page-header">
          <h2 class="page-title"><i class="fas fa-plus-circle"></i> Create New Event</h2>
        </div>

        <?php if ($error): ?>
          <div class="message message-error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="card">
          <form method="post" enctype="multipart/form-data">
            <?= CSRF::field() ?>

            <div class="form-group">
              <label class="form-label" for="title">Event Title <span style="color:var(--error)">*</span></label>
              <input type="text" id="title" name="title" class="form-input" value="<?= e($_POST['title'] ?? '') ?>"
                required maxlength="255">
            </div>

            <div class="form-group">
              <label class="form-label" for="description">Description <span style="color:var(--error)">*</span></label>
              <textarea id="description" name="description" class="form-input" rows="5"
                required><?= e($_POST['description'] ?? '') ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label" for="event_date"><i class="fas fa-calendar-alt"></i> Event Date</label>
                <input type="date" id="event_date" name="event_date" class="form-input"
                  value="<?= e($_POST['event_date'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="event_time"><i class="fas fa-clock"></i> Event Time</label>
                <input type="time" id="event_time" name="event_time" class="form-input"
                  value="<?= e($_POST['event_time'] ?? '') ?>">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="image">Event Image <span class="text-muted">(optional, max
                  5MB)</span></label>
              <input type="file" id="image" name="image" class="form-input"
                accept="image/jpeg,image/png,image/gif,image/webp">
            </div>

            <?php if ($role === 'teacher'): ?>
              <p class="text-muted text-sm" style="margin-bottom:1rem;">
                <i class="fas fa-info-circle"></i> Your event will be submitted for admin approval.
              </p>
            <?php endif; ?>

            <button type="submit" class="btn" style="width:100%;">
              <i class="fas fa-plus"></i> Create Event
            </button>
          </form>
        </div>
      </div>
    </main>
  </div>
</body>

</html>