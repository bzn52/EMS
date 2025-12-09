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
$messages = [];
$errors = [];

// Load current user data
$stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userRow) {
    http_response_code(500);
    die('User record not found.');
}

// Handle POST (update actions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF validation
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security validation failed. Please try again.';
    } else {
        // Update basic profile
        if (isset($_POST['update_profile'])) {
            $newName = Input::text($_POST['name'] ?? '', 100);
            $newEmail = Input::email($_POST['email'] ?? '');

            if (strlen($newName) < 2) $errors[] = 'Name must be at least 2 characters.';
            if (!$newEmail) $errors[] = 'Please enter a valid email.';

            if (empty($errors)) {
                // Check email uniqueness
                $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check->bind_param('si', $newEmail, $uid);
                $check->execute();
                $check->store_result();
                if ($check->num_rows > 0) {
                    $errors[] = 'That email is already used by another account.';
                }
                $check->close();
            }

            if (empty($errors)) {
                $u = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
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

        // Change password
        if (isset($_POST['change_password'])) {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
            if (!preg_match('/[A-Z]/', $new)) $errors[] = 'Password must contain uppercase letter.';
            if (!preg_match('/[a-z]/', $new)) $errors[] = 'Password must contain lowercase letter.';
            if (!preg_match('/[0-9]/', $new)) $errors[] = 'Password must contain number.';
            if ($new !== $confirm) $errors[] = 'Passwords do not match.';

            if ($current !== $userRow['password']) {
                $errors[] = 'Current password is incorrect.';
            }

            if (empty($errors)) {
                $pstmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $pstmt->bind_param('si', $new, $uid);
                if ($pstmt->execute()) {
                    $messages[] = 'Password changed successfully.';
                    $userRow['password'] = $new;
                } else {
                    $errors[] = 'Failed to update password.';
                }
                $pstmt->close();
            }
        }
    }
}

$role = Auth::role();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Account Settings - Event Management</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
  <div class="page-wrapper">
    <header>
      <div class="header-content">
        <div class="header-left">
          <h1><i class="fas fa-cog"></i> Account Settings</h1>
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
            <a href="<?= Auth::getDashboardUrl() ?>">Dashboard</a>
            <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
            <a href="logout.php" style="color: var(--error);"><i class="fas fa-sign-out-alt"></i> Logout</a>
          </nav>
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
              <input type="hidden" name="update_profile" value="1">
              
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
                <input type="text" value="<?= e(ucfirst($role)) ?>" disabled style="background: var(--gray-100); cursor: not-allowed;">
                <small class="text-muted" style="display: block; margin-top: 0.5rem;">Your role cannot be changed</small>
              </div>

              <button type="submit" class="btn">Save Profile</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h2 class="card-title">Change Password</h2>
          </div>
          <div class="card-body">
            <form method="post">
              <?= CSRF::field() ?>
              <input type="hidden" name="change_password" value="1">
              
              <div class="form-group">
                <label class="form-label">Current Password</label>
                <div class="password-toggle">
                  <input type="password" name="current_password" id="current_password" required>
                  <button type="button" class="toggle-password" data-target="current_password" tabindex="-1"><i class="fas fa-eye"></i></button>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">New Password</label>
                <div class="password-toggle">
                  <input type="password" name="new_password" id="new_password" required minlength="8">
                  <button type="button" class="toggle-password" data-target="new_password" tabindex="-1"><i class="fas fa-eye"></i></button>
                </div>
              </div>

              <div class="password-requirements">
                <strong>Password must contain:</strong>
                <ul>
                  <li>At least 8 characters</li>
                  <li>One uppercase letter</li>
                  <li>One lowercase letter</li>
                  <li>One number</li>
                </ul>
              </div>

              <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <div class="password-toggle">
                  <input type="password" name="confirm_password" id="confirm_password" required>
                  <button type="button" class="toggle-password" data-target="confirm_password" tabindex="-1"><i class="fas fa-eye"></i></button>
                </div>
              </div>

              <button type="submit" class="btn">Change Password</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-body" style="text-align: center;">
            <h3 style="margin-bottom: 1rem; color: var(--gray-700);">Account Actions</h3>
            <form method="post" action="logout.php" style="display: inline-block;">
              <button type="submit" class="btn btn-secondary">Logout from Account</button>
            </form>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Toggle password visibility
      const toggleButtons = document.querySelectorAll('.toggle-password');
      toggleButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
          e.preventDefault();
          const targetId = button.getAttribute('data-target');
          const input = document.getElementById(targetId);
          if (input) {
            input.type = input.type === 'password' ? 'text' : 'password';
          }
        });
      });

      // Auto-hide success messages
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