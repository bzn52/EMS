<?php
if (session_status() === PHP_SESSION_NONE) session_start();

define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();
Auth::requireRole('admin');

function e($s) { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$message = '';
$messageType = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security validation failed.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        $userId = (int)($_POST['user_id'] ?? 0);
        
        // Prevent admin from deleting themselves
        if ($userId === Auth::id() && $action === 'delete') {
            $message = 'You cannot delete your own account!';
            $messageType = 'error';
        } elseif ($userId > 0) {
            
            // Delete user
            if ($action === 'delete') {
                // Get user details first
                $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if ($user) {
                    // Delete user (cascade will delete related records)
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->bind_param('i', $userId);
                    
                    if ($stmt->execute()) {
                        $message = "User '{$user['name']}' has been deleted successfully.";
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to delete user.';
                        $messageType = 'error';
                    }
                    $stmt->close();
                }
            }
            
            // Approve/Unapprove user
            elseif ($action === 'approve' || $action === 'unapprove') {
                $approved = ($action === 'approve') ? 1 : 0;
                $adminId = Auth::id();
                
                $stmt = $conn->prepare("UPDATE users SET approved = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
                $stmt->bind_param('iii', $approved, $adminId, $userId);
                
                if ($stmt->execute()) {
                    $message = 'User status updated successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update user status.';
                    $messageType = 'error';
                }
                $stmt->close();
            }
            
            // Verify/Unverify email
            elseif ($action === 'verify' || $action === 'unverify') {
                $verified = ($action === 'verify') ? 1 : 0;
                
                $stmt = $conn->prepare("UPDATE users SET email_verified = ?, verified_at = NOW() WHERE id = ?");
                $stmt->bind_param('ii', $verified, $userId);
                
                if ($stmt->execute()) {
                    $message = 'Email verification status updated.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update email verification.';
                    $messageType = 'error';
                }
                $stmt->close();
            }
            
            // Change user role
            elseif ($action === 'change_role') {
                $newRole = $_POST['new_role'] ?? '';
                
                if (in_array($newRole, ['student', 'teacher', 'admin'])) {
                    // Prevent admin from changing their own role
                    if ($userId === Auth::id()) {
                        $message = 'You cannot change your own role!';
                        $messageType = 'error';
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                        $stmt->bind_param('si', $newRole, $userId);
                        
                        if ($stmt->execute()) {
                            $message = 'User role updated successfully.';
                            $messageType = 'success';
                        } else {
                            $message = 'Failed to update user role.';
                            $messageType = 'error';
                        }
                        $stmt->close();
                    }
                }
            }
            
            // Reset password
            elseif ($action === 'reset_password') {
                $newPassword = $_POST['new_password'] ?? '';
                
                if (strlen($newPassword) >= 8) {
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->bind_param('si', $newPassword, $userId);
                    
                    if ($stmt->execute()) {
                        $message = 'Password reset successfully.';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to reset password.';
                        $messageType = 'error';
                    }
                    $stmt->close();
                } else {
                    $message = 'Password must be at least 8 characters.';
                    $messageType = 'error';
                }
            }
        }
    }
}

// Search and filter
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Build query
$where = [];
$params = [];
$types = '';

if ($search) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}

if ($roleFilter && in_array($roleFilter, ['student', 'teacher', 'admin'])) {
    $where[] = "u.role = ?";
    $params[] = $roleFilter;
    $types .= 's';
}

if ($statusFilter === 'verified') {
    $where[] = "u.email_verified = 1";
} elseif ($statusFilter === 'unverified') {
    $where[] = "u.email_verified = 0";
} elseif ($statusFilter === 'approved') {
    $where[] = "u.approved = 1";
} elseif ($statusFilter === 'pending') {
    $where[] = "u.approved = 0";
}

$sql = "SELECT u.*, a.name as approved_by_name 
        FROM users u 
        LEFT JOIN users a ON u.approved_by = a.id";

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY u.created_at DESC";

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $users = $stmt->get_result();
    $stmt->close();
} else {
    $users = $conn->query($sql);
    if ($users === false) {
        die("Query failed: " . $conn->error);
    }
}
// Get statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'],
    'students' => $conn->query("SELECT COUNT(*) as c FROM users WHERE role='student'")->fetch_assoc()['c'],
    'teachers' => $conn->query("SELECT COUNT(*) as c FROM users WHERE role='teacher'")->fetch_assoc()['c'],
    'admins' => $conn->query("SELECT COUNT(*) as c FROM users WHERE role='admin'")->fetch_assoc()['c'],
    'pending' => $conn->query("SELECT COUNT(*) as c FROM users WHERE approved=0")->fetch_assoc()['c'],
    'unverified' => $conn->query("SELECT COUNT(*) as c FROM users WHERE email_verified=0")->fetch_assoc()['c'],
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    .filters-bar {
      background: var(--bg-primary);
      padding: 1.5rem;
      border-radius: var(--radius-lg);
      margin-bottom: 2rem;
      box-shadow: var(--shadow);
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      align-items: center;
    }
    .filters-bar input,
    .filters-bar select {
      margin-bottom: 0;
      flex: 1;
      min-width: 200px;
    }
    .user-actions {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }
    .user-actions button,
    .user-actions .btn {
      padding: 0.375rem 0.75rem;
      font-size: 0.813rem;
    }
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
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
    .modal-buttons {
      display: flex;
      gap: 1rem;
      margin-top: 1.5rem;
    }
  </style>
</head>
<body>
  <div class="page-wrapper">
    <header>
      <div class="header-content">
        <div class="header-left">
          <h1><i class="fas fa-users-cog"></i> Manage Users</h1>
        </div>
        <div class="header-right">
          <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr(Auth::name(), 0, 1)) ?></div>
            <div>
              <div><?= e(Auth::name()) ?></div>
              <span class="user-role-badge badge-admin"><?= e($role) ?></span>
            </div>
          </div>
          <nav class="nav-links">
            <a href="dashboard_admin.php">Back to Dashboard</a>
            <a href="logout.php" style="color: var(--error);"><i class="fas fa-sign-out-alt"></i> Logout</a>
          </nav>
        </div>
      </div>
    </header>

    <main>
      <div class="container container-lg">
        
        <?php if ($message): ?>
          <div class="message message-<?= e($messageType) ?>">
            <?= e($message) ?>
          </div>
        <?php endif; ?>

        <!-- Statistics -->
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
          <div class="stat-card rejected">
            <div class="stat-number"><?= $stats['unverified'] ?></div>
            <div class="stat-label"><i class="fas fa-envelope"></i> Unverified Email</div>
          </div>
        </div>

        <!-- Quick Access Buttons -->
        <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
          <a href="?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? '' : 'btn-outline' ?>" style="<?= $statusFilter === 'pending' ? 'background: var(--warning);' : '' ?>">
            <i class="fas fa-clock"></i> Pending Approvals (<?= $stats['pending'] ?>)
          </a>
          <a href="?status=unverified" class="btn btn-sm <?= $statusFilter === 'unverified' ? '' : 'btn-outline' ?>" style="<?= $statusFilter === 'unverified' ? 'background: var(--error);' : '' ?>">
            <i class="fas fa-envelope"></i> Unverified Emails (<?= $stats['unverified'] ?>)
          </a>
          <a href="?role=teacher" class="btn btn-sm <?= $roleFilter === 'teacher' ? '' : 'btn-outline' ?>">
            <i class="fas fa-chalkboard-teacher"></i> Teachers (<?= $stats['teachers'] ?>)
          </a>
          <a href="?role=student" class="btn btn-sm <?= $roleFilter === 'student' ? '' : 'btn-outline' ?>">
            <i class="fas fa-user-graduate"></i> Students (<?= $stats['students'] ?>)
          </a>
        </div>

        <!-- Filters -->
        <form method="get" class="filters-bar">
          <input 
            type="text" 
            name="search" 
            placeholder="Search by name or email..." 
            value="<?= e($search) ?>"
          >
          
          <select name="role">
            <option value="">All Roles</option>
            <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Students</option>
            <option value="teacher" <?= $roleFilter === 'teacher' ? 'selected' : '' ?>>Teachers</option>
            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admins</option>
          </select>
          
          <select name="status">
            <option value="">All Status</option>
            <option value="verified" <?= $statusFilter === 'verified' ? 'selected' : '' ?>>Email Verified</option>
            <option value="unverified" <?= $statusFilter === 'unverified' ? 'selected' : '' ?>>Email Unverified</option>
            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Approval</option>
          </select>
          
          <button type="submit" class="btn btn-sm">
            <i class="fas fa-filter"></i> Filter
          </button>
          
          <a href="admin_manage_users.php" class="btn btn-sm btn-outline">
            <i class="fas fa-redo"></i> Reset
          </a>
        </form>

        <!-- Users Table -->
        <div class="card">
          <div class="card-header">
            <h2 class="card-title">All Users</h2>
          </div>

          <?php if (!$users || $users->num_rows === 0): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><i class="fas fa-users"></i></div>
              <h3 class="empty-state-title">No users found</h3>
              <p class="empty-state-text">Try adjusting your search filters</p>
            </div>
          <?php else: ?>
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
                  <?php while ($user = $users->fetch_assoc()): ?>
                  <tr>
                    <td><strong><?= e($user['name']) ?></strong></td>
                    <td><?= e($user['email']) ?></td>
                    <td><span class="badge badge-<?= e($user['role']) ?>"><?= e(ucfirst($user['role'])) ?></span></td>
                    <td>
                      <?php if (!$user['email_verified']): ?>
                        <span class="badge badge-rejected">Email Not Verified</span>
                      <?php elseif (!$user['approved'] && $user['role'] === 'teacher'): ?>
                        <span class="badge badge-pending">Awaiting Approval</span>
                      <?php elseif (!$user['approved']): ?>
                        <span class="badge badge-warning">Not Approved</span>
                      <?php else: ?>
                        <span class="badge badge-approved">Active</span>
                      <?php endif; ?>
                    </td>
                    <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                    <td>
                      <div class="user-actions" style="justify-content: center;">
                        <?php if ((int)$user['id'] !== Auth::id()): ?>
                          
                          <!-- Approve/Unapprove -->
                          <?php if (!$user['approved']): ?>
                            <form method="post" style="display: inline;">
                              <?= CSRF::field() ?>
                              <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                              <input type="hidden" name="action" value="approve">
                              <button type="submit" class="btn btn-sm btn-success" title="Approve User">
                                <i class="fas fa-check"></i> Approve
                              </button>
                            </form>
                          <?php else: ?>
                            <form method="post" style="display: inline;">
                              <?= CSRF::field() ?>
                              <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                              <input type="hidden" name="action" value="unapprove">
                              <button type="submit" class="btn btn-sm btn-warning" title="Unapprove User">
                                <i class="fas fa-ban"></i> Unapprove
                              </button>
                            </form>
                          <?php endif; ?>
                          
                          <!-- Verify Email -->
                          <?php if (!$user['email_verified']): ?>
                            <form method="post" style="display: inline;">
                              <?= CSRF::field() ?>
                              <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                              <input type="hidden" name="action" value="verify">
                              <button type="submit" class="btn btn-sm" style="background: #3b82f6; color: white;" title="Verify Email">
                                <i class="fas fa-envelope-circle-check"></i> Verify Email
                              </button>
                            </form>
                          <?php endif; ?>
                          
                          <!-- Change Role -->
                          <button class="btn btn-sm btn-outline" onclick="openRoleModal(<?= (int)$user['id'] ?>, '<?= e($user['name']) ?>', '<?= e($user['role']) ?>')">
                            <i class="fas fa-exchange-alt"></i> Change Role
                          </button>
                          
                          <!-- Reset Password -->
                          <button class="btn btn-sm btn-secondary" onclick="openPasswordModal(<?= (int)$user['id'] ?>, '<?= e($user['name']) ?>')">
                            <i class="fas fa-key"></i> Reset Password
                          </button>
                          
                          <!-- Delete -->
                          <form method="post" style="display: inline;" onsubmit="return confirm('Delete user <?= e($user['name']) ?>? This cannot be undone!')">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-sm btn-danger" title="Delete User">
                              <i class="fas fa-trash-alt"></i> Delete
                            </button>
                          </form>
                          
                        <?php else: ?>
                          <span class="badge" style="background: #6b7280; color: white;">You</span>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </main>
  </div>

  <!-- Change Role Modal -->
  <div id="roleModal" class="modal">
    <div class="modal-content">
      <h3 style="margin-top: 0;">Change User Role</h3>
      <p id="roleModalText"></p>
      <form method="post">
        <?= CSRF::field() ?>
        <input type="hidden" name="user_id" id="roleUserId">
        <input type="hidden" name="action" value="change_role">
        
        <div class="form-group">
          <label class="form-label">New Role</label>
          <select name="new_role" id="roleSelect" required>
            <option value="student">Student</option>
            <option value="teacher">Teacher</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        
        <div class="modal-buttons">
          <button type="submit" class="btn">Change Role</button>
          <button type="button" class="btn btn-outline" onclick="closeModal('roleModal')">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Reset Password Modal -->
  <div id="passwordModal" class="modal">
    <div class="modal-content">
      <h3 style="margin-top: 0;">Reset User Password</h3>
      <p id="passwordModalText"></p>
      <form method="post">
        <?= CSRF::field() ?>
        <input type="hidden" name="user_id" id="passwordUserId">
        <input type="hidden" name="action" value="reset_password">
        
        <div class="form-group">
          <label class="form-label">New Password (min 8 characters)</label>
          <input type="password" name="new_password" required minlength="8" placeholder="Enter new password">
        </div>
        
        <div class="modal-buttons">
          <button type="submit" class="btn">Reset Password</button>
          <button type="button" class="btn btn-outline" onclick="closeModal('passwordModal')">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openRoleModal(userId, userName, currentRole) {
      document.getElementById('roleUserId').value = userId;
      document.getElementById('roleSelect').value = currentRole;
      document.getElementById('roleModalText').textContent = 'Change role for: ' + userName;
      document.getElementById('roleModal').classList.add('active');
    }

    function openPasswordModal(userId, userName) {
      document.getElementById('passwordUserId').value = userId;
      document.getElementById('passwordModalText').textContent = 'Reset password for: ' + userName;
      document.getElementById('passwordModal').classList.add('active');
    }

    function closeModal(modalId) {
      document.getElementById(modalId).classList.remove('active');
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
      if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
      }
    }
  </script>
</body>
</html>