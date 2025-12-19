<?php
if (session_status() === PHP_SESSION_NONE)
  session_start();

define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();
Auth::requireRole('admin');

function e($s)
{
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$message = '';
$messageType = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    $message = 'Security validation failed.';
    $messageType = 'error';
  } else {
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);
    $userType = $_POST['user_type'] ?? '';

    if ($userId === Auth::id() && $userType === 'admin' && in_array($action, ['delete', 'change_role'])) {
      $message = 'You cannot modify your own account!';
      $messageType = 'error';
    } elseif ($userId > 0 && in_array($userType, ['admin', 'teacher', 'student'])) {
      $table = Role::getTableName($userType);

      if ($action === 'delete') {
        $stmt = $conn->prepare("SELECT name FROM $table WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
          $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
          $stmt->bind_param('i', $userId);
          if ($stmt->execute()) {
            $message = "User '{$user['name']}' has been deleted successfully.";
            $messageType = 'success';
          }
          $stmt->close();
        }
      } elseif ($action === 'approve' && $userType === 'teacher') {
        $adminId = Auth::id();
        $stmt = $conn->prepare("UPDATE teachers SET approved = 1, approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->bind_param('ii', $adminId, $userId);
        if ($stmt->execute()) {
          $message = 'Teacher approved successfully.';
          $messageType = 'success';
        }
        $stmt->close();
      } elseif ($action === 'change_role') {
        $newRole = $_POST['new_role'] ?? '';
        if (in_array($newRole, ['student', 'teacher', 'admin']) && $newRole !== $userType) {
          // Get user data
          $stmt = $conn->prepare("SELECT name, email, password FROM $table WHERE id = ?");
          $stmt->bind_param('i', $userId);
          $stmt->execute();
          $userData = $stmt->get_result()->fetch_assoc();
          $stmt->close();

          if ($userData) {
            $newTable = Role::getTableName($newRole);

            // Insert into new table
            if ($newRole === 'teacher') {
              $stmt = $conn->prepare("INSERT INTO $newTable (name, email, password, approved, created_at) VALUES (?, ?, ?, 1, NOW())");
            } else {
              $stmt = $conn->prepare("INSERT INTO $newTable (name, email, password, created_at) VALUES (?, ?, ?, NOW())");
            }
            $stmt->bind_param('sss', $userData['name'], $userData['email'], $userData['password']);

            if ($stmt->execute()) {
              $stmt->close();
              // Delete from old table
              $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
              $stmt->bind_param('i', $userId);
              $stmt->execute();
              $stmt->close();

              $message = "User role changed to " . ucfirst($newRole) . " successfully.";
              $messageType = 'success';
            } else {
              $stmt->close();
              $message = 'Failed to change role.';
              $messageType = 'error';
            }
          }
        }
      } elseif ($action === 'reset_password') {
        $newPassword = $_POST['new_password'] ?? '';
        if (strlen($newPassword) >= 8) {
          $stmt = $conn->prepare("UPDATE $table SET password = ? WHERE id = ?");
          $stmt->bind_param('si', $newPassword, $userId);
          if ($stmt->execute()) {
            $message = 'Password reset successfully.';
            $messageType = 'success';
          }
          $stmt->close();
        }
      }
    }
  }
}

// Get all users from all tables
$allUsers = [];

$admins = $conn->query("SELECT id, name, email, created_at, 'admin' as role, 1 as approved FROM admins");
while ($row = $admins->fetch_assoc()) {
  $allUsers[] = $row;
}

$teachers = $conn->query("SELECT id, name, email, created_at, 'teacher' as role, approved FROM teachers");
while ($row = $teachers->fetch_assoc()) {
  $allUsers[] = $row;
}

$students = $conn->query("SELECT id, name, email, created_at, 'student' as role, 1 as approved FROM students");
while ($row = $students->fetch_assoc()) {
  $allUsers[] = $row;
}

usort($allUsers, function ($a, $b) {
  return strtotime($b['created_at']) - strtotime($a['created_at']);
});

$stats = [
  'total' => count($allUsers),
  'students' => $conn->query("SELECT COUNT(*) as c FROM students")->fetch_assoc()['c'],
  'teachers' => $conn->query("SELECT COUNT(*) as c FROM teachers")->fetch_assoc()['c'],
  'admins' => $conn->query("SELECT COUNT(*) as c FROM admins")->fetch_assoc()['c'],
  'pending' => $conn->query("SELECT COUNT(*) as c FROM teachers WHERE approved=0")->fetch_assoc()['c'],
];

$role = Auth::role();
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Manage Users</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
    integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      align-items: center;
      justify-content: center;
    }

    .modal.active {
      display: flex;
    }

    .modal-content {
      background: var(--bg-primary);
      padding: 2rem;
      border-radius: var(--radius-lg);
      max-width: 500px;
      width: 90%;
      box-shadow: var(--shadow-xl);
    }

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

    td .btn {
      padding: 0.5rem 0.875rem;
      font-size: 0.813rem;
      min-width: 100px;
      text-align: center;
      justify-content: center;
      margin: 0;
    }

    td form {
      display: inline-block;
      margin: 0;
    }

    td form button {
      margin: 0;
    }

    td>div {
      display: flex;
      gap: 0.5rem;
      justify-content: center;
      align-items: center;
      flex-wrap: wrap;
    }

    td .btn,
    td form button {
      height: 36px;
      line-height: 1.2;
      white-space: nowrap;
    }

    td .btn i,
    td form button i {
      margin-right: 0.25rem;
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
                <a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a>
                <a href="events/create.php"><i class="fas fa-plus"></i> Create Event</a>
                <a href="dashboard_student.php"><i class="fas fa-eye"></i> Student View</a>
                <a href="settings.php"><i class="fa-solid fa-key"></i> Change Password</a>
                <a href="logout.php" style="color: var(--error);"><i class="fas fa-sign-out-alt"></i> Logout</a>
              <?php elseif ($role === 'teacher'): ?>
                <a href="dashboard_teacher.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a>
                <a href="events/create.php"><i class="fas fa-plus"></i> Create Event</a>
                <a href="settings.php"><i class="fa-solid fa-key"></i> Change Password</a>
                <a href="logout.php" style="color: var(--error);"><i class="fas fa-sign-out-alt"></i> Logout</a>
              <?php else: ?>
                <a href="dashboard_student.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a>
                <a href="settings.php"><i class="fa-solid fa-key"></i> Change Password</a>
                <a href="logout.php" style="color: var(--error);"><i class="fas fa-sign-out-alt"></i> Logout</a>
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

      <div class="container container-lg">

        <?php if ($message): ?>
          <div class="message message-<?= e($messageType) ?>">
            <?= e($message) ?>
          </div>
        <?php endif; ?>

        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-number"><?= $stats['total'] ?></div>
            <div class="stat-label"><i class="fas fa-users"></i> Total Users</div>
          </div>
          <div class="stat-card">
            <div class="stat-number"><?= $stats['students'] ?></div>
            <div class="stat-label"><i class="fas fa-user-graduate"></i> Students</div>
          </div>
          <div class="stat-card">
            <div class="stat-number"><?= $stats['teachers'] ?></div>
            <div class="stat-label"><i class="fas fa-chalkboard-teacher"></i> Teachers</div>
          </div>
          <div class="stat-card">
            <div class="stat-number"><?= $stats['admins'] ?></div>
            <div class="stat-label"><i class="fas fa-user-shield"></i> Admins</div>
          </div>
          <div class="stat-card pending">
            <div class="stat-number"><?= $stats['pending'] ?></div>
            <div class="stat-label"><i class="fas fa-clock"></i> Pending Approval</div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h2 class="card-title">All Users</h2>
          </div>

          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th>Registered</th>
                  <th style="text-align: center;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($allUsers as $user):
                  $isCurrentUser = (int) $user['id'] === Auth::id() && $user['role'] === Auth::role();
                  ?>
                  <tr>
                    <td><strong><?= e($user['name']) ?></strong></td>
                    <td><?= e($user['email']) ?></td>
                    <td><span class="badge badge-<?= e($user['role']) ?>"><?= e(ucfirst($user['role'])) ?></span></td>
                    <td>
                      <?php if ($user['role'] === 'teacher' && !$user['approved']): ?>
                        <span class="badge badge-pending">Pending Approval</span>
                      <?php else: ?>
                        <span class="badge badge-approved">Active</span>
                      <?php endif; ?>
                    </td>
                    <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                    <td>
                      <div style="display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap;">
                        <?php if (!$isCurrentUser): ?>

                          <?php if ($user['role'] === 'teacher' && !$user['approved']): ?>
                            <form method="post" style="display: inline;">
                              <?= CSRF::field() ?>
                              <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                              <input type="hidden" name="user_type" value="<?= e($user['role']) ?>">
                              <input type="hidden" name="action" value="approve">
                              <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i>
                                Approve</button>
                            </form>
                          <?php endif; ?>

                          <button class="btn btn-sm"
                            onclick="openRoleModal(<?= (int) $user['id'] ?>, '<?= e($user['name']) ?>', '<?= e($user['role']) ?>')">
                            <i class="fas fa-exchange-alt"></i> Change Role
                          </button>

                          <button class="btn btn-sm btn-secondary"
                            onclick="openPasswordModal(<?= (int) $user['id'] ?>, '<?= e($user['name']) ?>', '<?= e($user['role']) ?>')">
                            <i class="fas fa-key"></i> Reset Password
                          </button>

                          <form method="post" style="display: inline;"
                            onsubmit="return confirm('Delete user <?= e($user['name']) ?>?')">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                            <input type="hidden" name="user_type" value="<?= e($user['role']) ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash-alt"></i>
                              Delete</button>
                          </form>

                        <?php else: ?>
                          <span class="badge" style="background: #6b7280; color: white;">You</span>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </main>
  </div>

  <div id="roleModal" class="modal">
    <div class="modal-content">
      <h3 style="margin-top: 0;">Change User Role</h3>
      <p id="roleModalText"></p>
      <form method="post">
        <?= CSRF::field() ?>
        <input type="hidden" name="user_id" id="roleUserId">
        <input type="hidden" name="user_type" id="roleUserType">
        <input type="hidden" name="action" value="change_role">

        <div class="form-group">
          <label class="form-label">New Role</label>
          <select name="new_role" id="roleSelect" required>
            <option value="student">Student</option>
            <option value="teacher">Teacher</option>
            <option value="admin">Admin</option>
          </select>
        </div>

        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
          <button type="submit" class="btn">Change Role</button>
          <button type="button" class="btn btn-outline" onclick="closeModal('roleModal')">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <div id="passwordModal" class="modal">
    <div class="modal-content">
      <h3 style="margin-top: 0;">Reset User Password</h3>
      <p id="passwordModalText"></p>
      <form method="post">
        <?= CSRF::field() ?>
        <input type="hidden" name="user_id" id="passwordUserId">
        <input type="hidden" name="user_type" id="passwordUserType">
        <input type="hidden" name="action" value="reset_password">

        <div class="form-group">
          <label class="form-label">New Password (min 8 characters)</label>
          <input type="password" name="new_password" required minlength="8">
        </div>

        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
          <button type="submit" class="btn">Reset Password</button>
          <button type="button" class="btn btn-outline" onclick="closeModal('passwordModal')">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openRoleModal(userId, userName, userType) {
      document.getElementById('roleUserId').value = userId;
      document.getElementById('roleUserType').value = userType;
      document.getElementById('roleSelect').value = userType === 'student' ? 'teacher' : 'student';
      document.getElementById('roleModalText').textContent = 'Change role for: ' + userName;
      document.getElementById('roleModal').classList.add('active');
    }

    function openPasswordModal(userId, userName, userType) {
      document.getElementById('passwordUserId').value = userId;
      document.getElementById('passwordUserType').value = userType;
      document.getElementById('passwordModalText').textContent = 'Reset password for: ' + userName;
      document.getElementById('passwordModal').classList.add('active');
    }

    function closeModal(modalId) {
      document.getElementById(modalId).classList.remove('active');
    }

    window.onclick = function (event) {
      if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
      }
    }

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