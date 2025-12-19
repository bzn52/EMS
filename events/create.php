<?php
if (session_status() === PHP_SESSION_NONE)
  session_start();

require_once __DIR__ . '/events_common.php';
Auth::requireLogin();
Auth::requireRole(['teacher', 'admin']);

$title = '';
$description = '';
$errors = [];
$success = '';

$uploadsDir = EVENTS_UPLOADS_DIR;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Security validation failed. Please try again.';
  } else {
    $title = Input::text($_POST['title'] ?? '', 255);
    $description = Input::text($_POST['description'] ?? '', 5000);

    if (strlen($title) < 3) {
      $errors[] = 'Title must be at least 3 characters.';
    }

    $imageName = null;
    if (!empty($_FILES['image']['name'])) {
      $uploadErrors = FileUpload::validate($_FILES['image']);

      if (!empty($uploadErrors)) {
        $errors = array_merge($errors, $uploadErrors);
      } else {
        $imageName = FileUpload::generateSecureName($_FILES['image']['name']);
        $dest = rtrim($uploadsDir, '/') . '/' . $imageName;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
          $errors[] = 'Failed to upload file.';
          $imageName = null;
        }
      }
    }

    if (empty($errors)) {
      $userId = Auth::id();
      $userRole = Auth::role();

      $sql = "INSERT INTO events (title, description, image, status, created_by_type, created_by_id, created_at)
                    VALUES (?, ?, ?, 'pending', ?, ?, NOW())";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("ssssi", $title, $description, $imageName, $userRole, $userId);

      if ($stmt->execute()) {
        $stmt->close();

        $success = 'Event created successfully! It is pending admin approval.';

        $title = '';
        $description = '';

        header("refresh:2;url=" . (Auth::role() === 'admin' ? '../dashboard_admin.php' : '../dashboard_teacher.php'));
      } else {
        $errors[] = 'Failed to create event: ' . $stmt->error;
        $stmt->close();
      }
    }
  }
}

function e($s)
{
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$role = Auth::role();
$backLink = ($role === "admin") ? "../dashboard_admin.php" : "../dashboard_teacher.php";
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
  <style>
    .user-menu-wrapper {
      position: relative;
    }

    .user-info {
      cursor: pointer;
      user-select: none;
    }

    .user-info::after {
      content: "▼";
      margin-left: 0.5rem;
      font-size: 0.75rem;
      opacity: 0.6;
      transition: var(--transition);
    }

    .user-menu-wrapper.active .user-info::after {
      transform: rotate(180deg);
    }

    .dropdown-menu {
      position: absolute;
      top: calc(100% + 0.75rem);
      right: 0;
      background: var(--bg-primary);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-xl);
      min-width: 220px;
      display: none;
      z-index: 1000;
      overflow: hidden;
    }

    .user-menu-wrapper.active .dropdown-menu {
      display: block;
    }

    .dropdown-menu a {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem 1rem;
      color: var(--text-secondary);
      text-decoration: none;
      transition: var(--transition);
      border-bottom: 1px solid var(--border-light);
      font-size: 0.875rem;
      font-weight: 500;
      white-space: nowrap;
    }

    .dropdown-menu a:last-child {
      border-bottom: none;
    }

    .dropdown-menu a:hover {
      background: var(--bg-secondary);
      color: var(--primary);
    }

    .dropdown-menu a i {
      width: 1.25rem;
      text-align: center;
      opacity: 0.7;
    }

    .header-right .nav-links {
      display: none;
    }
  </style>
</head>

<body>
  <div class="page-wrapper">
    <header>
      <div class="header-content">
        <div class="header-left">
          <h1>Event Management System</h1>
        </div>
        <div class="header-right">
          <div class="user-menu-wrapper">
            <div class="user-info">
              <div class="user-avatar"><?= strtoupper(substr(Auth::name(), 0, 1)) ?></div>
              <div>
                <div><?= e(Auth::name()) ?></div>
                <span class="user-role-badge badge-<?= e($role) ?>"><?= e($role) ?></span>
              </div>
            </div>
            <div class="dropdown-menu">
              <?php if ($role === 'admin'): ?>
                <a href="../dashboard_admin.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="../profile.php"><i class="fa-solid fa-user"></i> Profile</a>
                <a href="../admin_manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a>
                <a href="../dashboard_student.php"><i class="fas fa-eye"></i> Student View</a>
                <a href="../settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="../logout.php" style="color: var(--error);"><i class="fas fa-sign-out-alt"></i> Logout</a>
              <?php elseif ($role === 'teacher'): ?>
                <a href="../dashboard_teacher.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="../profile.php"><i class="fa-solid fa-user"></i> Profile</a>
                <a href="../settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="../logout.php" style="color: var(--error);"><i class="fas fa-sign-out-alt"></i> Logout</a>
              <?php else: ?>
                <a href="../dashboard_student.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="../profile.php"><i class="fa-solid fa-user"></i> Profile</a>
                <a href="../settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="../logout.php" style="color: var(--error);"><i class="fas fa-sign-out-alt"></i> Logout</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main>
      <div class="back-button-container">
        <a href="dashboard_admin.php" class="back-button">
          <i class="fas fa-arrow-left"></i> Back
        </a>
      </div>
      <div class="container container-sm">
        <?php if (!empty($errors)): ?>
          <div class="message message-error">
            <?= implode('<br>', array_map('e', $errors)) ?>
          </div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="message message-success">
            <?= e($success) ?>
            <br><small>Redirecting to dashboard...</small>
          </div>
        <?php endif; ?>

        <div class="card">
          <div class="card-header">
            <h2 class="card-title">Event Information</h2>
          </div>
          <div class="card-body">
            <form method="post" enctype="multipart/form-data">
              <?= CSRF::field() ?>

              <div class="form-group">
                <label class="form-label">Event Title *</label>
                <input type="text" name="title" value="<?= e($title) ?>" placeholder="Enter event title" required
                  minlength="3" maxlength="255">
              </div>

              <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" rows="8"
                  placeholder="Describe your event..."><?= e($description) ?></textarea>
              </div>

              <div class="form-group">
                <label class="form-label">Event Image (optional)</label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"
                  onchange="previewImage(this, 'image-preview')">
                <small class="text-muted" style="display: block; margin-top: 0.5rem;">
                  Max size: 5MB. Formats: JPG, PNG, GIF, WebP
                </small>
                <img id="image-preview" style="display: none; max-width: 300px; margin-top: 1rem; border-radius: 8px;">
              </div>

              <div class="form-group">
                <button type="submit" class="btn">
                  <i class="fas fa-plus-circle"></i> Create Event
                </button>
                <a href="<?= $backLink ?>" class="btn btn-outline">
                  <i class="fas fa-times"></i> Cancel
                </a>
              </div>

              <p class="text-muted text-sm text-center">
                <i class="fas fa-info-circle"></i> Your event will be pending until approved by an administrator.
              </p>
            </form>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="../script.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const userMenuWrapper = document.querySelector('.user-menu-wrapper');
      const userInfo = document.querySelector('.user-info');

      if (userMenuWrapper && userInfo) {
        userInfo.addEventListener('click', function (e) {
          e.stopPropagation();
          userMenuWrapper.classList.toggle('active');
        });

        document.addEventListener('click', function (e) {
          if (!userMenuWrapper.contains(e.target)) {
            userMenuWrapper.classList.remove('active');
          }
        });
      }
    });

    document.querySelector('.back-button')?.addEventListener('click', function (e) {
      e.preventDefault();
      window.history.back();
    });
  </script>
</body>

</html>