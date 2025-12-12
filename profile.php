<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

Auth::requireLogin();

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$uid = Auth::id();
$role = Auth::role();
$messages = [];
$errors = [];

$table = Role::getTableName($role);

$stmt = $conn->prepare("SELECT id, name, email FROM $table WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userRow) {
    http_response_code(500);
    die('User record not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security validation failed. Please try again.';
    } else {
        $newName = Input::text($_POST['name'] ?? '', 100);
        $newEmail = Input::email($_POST['email'] ?? '');

        if (strlen($newName) < 2) $errors[] = 'Name must be at least 2 characters.';
        if (!$newEmail) $errors[] = 'Please enter a valid email.';

        if (empty($errors)) {
            $tables = ['admins', 'teachers', 'students'];
            foreach ($tables as $t) {
                $check = $conn->prepare("SELECT id FROM $t WHERE email = ? AND " . ($t === $table ? "id != ?" : "1=1"));
                if ($t === $table) {
                    $check->bind_param('si', $newEmail, $uid);
                } else {
                    $check->bind_param('s', $newEmail);
                }
                $check->execute();
                $check->store_result();
                if ($check->num_rows > 0) {
                    $errors[] = 'That email is already used by another account.';
                    $check->close();
                    break;
                }
                $check->close();
            }
        }

        if (empty($errors)) {
            $u = $conn->prepare("UPDATE $table SET name = ?, email = ? WHERE id = ?");
            $u->bind_param('ssi', $newName, $newEmail, $uid);
            if ($u->execute()) {
                $messages[] = 'Profile updated successfully.';
                $_SESSION['user_name'] = $newName;
                $_SESSION['email'] = $newEmail;
                $userRow['name'] = $newName;
                $userRow['email'] = $newEmail;
            } else {
                $errors[] = 'Failed to update profile.';
            }
            $u->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My Profile - Event Management</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
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
                <a href="dashboard_admin.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="events/create.php"><i class="fas fa-plus"></i> Create Event</a>
                <a href="admin_manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a>
                <a href="dashboard_student.php"><i class="fas fa-eye"></i> Student View</a>
                <a href="settings.php"><i class="fa-solid fa-key"></i> Change Password</a>
                <a href="logout.php" style="color: var(--error);"><i class="fas fa-sign-out-alt"></i> Logout</a>
              <?php elseif ($role === 'teacher'): ?>
                <a href="dashboard_teacher.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="events/create.php"><i class="fas fa-plus"></i> Create Event</a>
                <a href="settings.php"><i class="fa-solid fa-key"></i> Change Password</a>
                <a href="logout.php" style="color: var(--error);"><i class="fas fa-sign-out-alt"></i> Logout</a>
              <?php else: ?>
                <a href="dashboard_student.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="settings.php"><i class="fa-solid fa-key"></i> Change Password</a>
                <a href="logout.php" style="color: var(--error);"><i class="fas fa-sign-out-alt"></i> Logout</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main>
      <div class="container container-sm">
        <?php if ($errors): ?>
          <div class="message message-error">
            <?= e(implode(' | ', $errors)) ?>
          </div>
        <?php endif; ?>
        
        <?php if ($messages): ?>
          <div class="message message-success">
            <?= e(implode(' | ', $messages)) ?>
          </div>
        <?php endif; ?>

        <div class="card">
          <div class="card-header">
            <h2 class="card-title">Profile Information</h2>
          </div>
          <div class="card-body">
            <form method="post">
              <?= CSRF::field() ?>
              
              <div class="form-group">
                <label class="form-label">Name</label>
                <input type="text" name="name" value="<?= e($userRow['name']) ?>" required>
              </div>

              <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" value="<?= e($userRow['email']) ?>" required>
              </div>

              <div class="form-group">
                <label class="form-label">Role</label>
                <input type="text" value="<?= e(ucfirst($role)) ?>" disabled style="background: var(--bg-secondary); cursor: not-allowed;">
                <small class="text-muted" style="display: block; margin-top: 0.5rem;">Your role cannot be changed</small>
              </div>

              <button type="submit" class="btn">Save Changes</button>
            </form>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const userMenuWrapper = document.querySelector('.user-menu-wrapper');
      const userInfo = document.querySelector('.user-info');
      
      if (userMenuWrapper && userInfo) {
        userInfo.addEventListener('click', function(e) {
          e.stopPropagation();
          userMenuWrapper.classList.toggle('active');
        });
        
        document.addEventListener('click', function(e) {
          if (!userMenuWrapper.contains(e.target)) {
            userMenuWrapper.classList.remove('active');
          }
        });
      }

      setTimeout(function() {
        const messages = document.querySelectorAll('.message-success');
        messages.forEach(function(msg) {
          msg.style.transition = 'opacity 0.5s';
          msg.style.opacity = '0';
          setTimeout(function() { msg.remove(); }, 500);
        });
      }, 5000);
    });
  </script>
</body>
</html>