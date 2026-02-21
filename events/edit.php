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

$role = Auth::role();
$userId = Auth::id();
$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
  header('Location: ../dashboard_' . $role . '.php');
  exit;
}

// Fetch event
$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
  header('Location: ../dashboard_' . $role . '.php');
  exit;
}

// Only owner or admin can edit
if ($role !== 'admin' && !($event['created_by_type'] === 'teacher' && (int) $event['created_by_id'] === $userId)) {
  header('Location: ../dashboard_' . $role . '.php');
  exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid request. Please try again.';
  } else {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $event_time = $_POST['event_time'] ?? '';
    $imageName = $event['image'];

    if (empty($title) || empty($description)) {
      $error = 'Title and description are required.';
    } else {
      if (!empty($_FILES['image']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed)) {
          $error = 'Invalid image type.';
        } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
          $error = 'Image must be under 5MB.';
        } else {
          $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
          $newName = bin2hex(random_bytes(8)) . '.' . $ext;
          $dest = UPLOADS_DIR . '/' . $newName;
          if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
            // Delete old image
            if ($imageName && file_exists(UPLOADS_DIR . '/' . $imageName)) {
              unlink(UPLOADS_DIR . '/' . $imageName);
            }
            $imageName = $newName;
          } else {
            $error = 'Failed to upload image.';
          }
        }
      }

      if (empty($error)) {
        $dateVal = $event_date ?: null;
        $timeVal = $event_time ?: null;
        $upd = $conn->prepare(
          "UPDATE events SET title=?, description=?, event_date=?, event_time=?, image=? WHERE id=?"
        );
        $upd->bind_param('sssssi', $title, $description, $dateVal, $timeVal, $imageName, $id);
        if ($upd->execute()) {
          $_SESSION['event_success'] = 'Event updated successfully!';
          header('Location: view.php?id=' . $id);
          exit;
        } else {
          $error = 'Failed to update event.';
        }
      }
    }
  }
}

// Pre-fill values
$title = $_POST['title'] ?? $event['title'];
$description = $_POST['description'] ?? $event['description'];
$event_date = $_POST['event_date'] ?? ($event['event_date'] ?? '');
$event_time = $_POST['event_time'] ?? ($event['event_time'] ? substr($event['event_time'], 0, 5) : '');
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Edit Event</title>
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
          <a href="view.php?id=<?= $id ?>" class="btn btn-outline btn-sm">
            <i class="fas fa-arrow-left"></i> Back
          </a>
        </div>
      </div>
    </header>

    <main>
      <div class="container" style="max-width: 680px;">
        <div class="page-header">
          <h2 class="page-title"><i class="fas fa-edit"></i> Edit Event</h2>
        </div>

        <?php if ($error): ?>
          <div class="message message-error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="card">
          <form method="post" enctype="multipart/form-data">
            <?= CSRF::field() ?>

            <div class="form-group">
              <label class="form-label" for="title">Event Title <span style="color:var(--error)">*</span></label>
              <input type="text" id="title" name="title" class="form-input" value="<?= e($title) ?>" required
                maxlength="255">
            </div>

            <div class="form-group">
              <label class="form-label" for="description">Description <span style="color:var(--error)">*</span></label>
              <textarea id="description" name="description" class="form-input" rows="5"
                required><?= e($description) ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
              <div class="form-group">
                <label class="form-label" for="event_date"><i class="fas fa-calendar-alt"></i> Event Date</label>
                <input type="date" id="event_date" name="event_date" class="form-input" value="<?= e($event_date) ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="event_time"><i class="fas fa-clock"></i> Event Time</label>
                <input type="time" id="event_time" name="event_time" class="form-input" value="<?= e($event_time) ?>">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="image">
                Event Image <span class="text-muted">(optional — leave blank to keep current)</span>
              </label>
              <?php if ($event['image']): ?>
                <div style="margin-bottom:0.5rem;">
                  <img src="../uploads/<?= e($event['image']) ?>" alt="Current image"
                    style="max-height:120px; border-radius:var(--radius); border:1px solid var(--border-light);">
                </div>
              <?php endif; ?>
              <input type="file" id="image" name="image" class="form-input"
                accept="image/jpeg,image/png,image/gif,image/webp">
            </div>

            <?php if ($role === 'admin'): ?>
              <div class="form-group">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-input">
                  <option value="pending" <?= $event['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                  <option value="approved" <?= $event['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                  <option value="rejected" <?= $event['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
              </div>
            <?php endif; ?>

            <button type="submit" class="btn" style="width:100%;">
              <i class="fas fa-save"></i> Save Changes
            </button>
          </form>
        </div>
      </div>
    </main>
  </div>
</body>

</html>