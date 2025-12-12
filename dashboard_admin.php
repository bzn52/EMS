<?php
if (session_status() === PHP_SESSION_NONE) session_start();

define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();
Auth::requireRole('admin');

function e($s) { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Fetch all events with creator info
$sql = "SELECT e.id, e.title, e.description, e.image, e.status, e.created_at, 
        e.created_by_type, e.created_by_id,
        CASE 
            WHEN e.created_by_type = 'admin' THEN a.name
            WHEN e.created_by_type = 'teacher' THEN t.name
            ELSE 'Unknown'
        END as creator_name
        FROM events e 
        LEFT JOIN admins a ON e.created_by_type = 'admin' AND e.created_by_id = a.id
        LEFT JOIN teachers t ON e.created_by_type = 'teacher' AND e.created_by_id = t.id
        ORDER BY e.created_at DESC";
$result = $conn->query($sql);

// Get statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as count FROM events")->fetch_assoc()['count'],
    'pending' => $conn->query("SELECT COUNT(*) as count FROM events WHERE status='pending'")->fetch_assoc()['count'],
    'approved' => $conn->query("SELECT COUNT(*) as count FROM events WHERE status='approved'")->fetch_assoc()['count'],
    'rejected' => $conn->query("SELECT COUNT(*) as count FROM events WHERE status='rejected'")->fetch_assoc()['count'],
    'users' => $conn->query("SELECT 
        (SELECT COUNT(*) FROM admins) + 
        (SELECT COUNT(*) FROM teachers) + 
        (SELECT COUNT(*) FROM students) as count")->fetch_assoc()['count'],
];

$role = Auth::role();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard - Events Management</title>
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
  /* Add this to the <style> section in dashboard_admin.php */

/* Fix action buttons in table */
.card-footer .btn,
td .btn {
  width: auto;
  margin: 0;
  padding: 0.375rem 0.75rem;
  font-size: 0.813rem;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}

/* Ensure form buttons don't stretch */
td form {
  display: inline-block;
  margin: 0;
}

td form button {
  width: auto;
  margin: 0;
}

/* Action buttons container */
td > div {
  display: flex;
  gap: 0.5rem;
  justify-content: center;
  flex-wrap: wrap;
  align-items: center;
}
td .btn {
  background: #e0e7ff;
  color: #4338ca;
  border: 1px solid #c7d2fe;
}
td .btn:hover {
  background: #c7d2fe;
  color: #3730a3;
}
td .btn-success {
  background: #d1fae5;
  color: #065f46;
  border: 1px solid #6ee7b7;
}
td .btn-success:hover {
  background: #a7f3d0;
  color: #047857;
}
td .btn-warning {
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fde047;
}
td .btn-warning:hover {
  background: #fde68a;
  color: #78350f;
}
td .btn-danger {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fca5a5;
}
td .btn-danger:hover {
  background: #fecaca;
  color: #7f1d1d;
}
.stat-card {
  background: var(--bg-primary);
  border: 1px solid var(--border-light);
  border-left: 4px solid var(--primary);
}
.stat-card.pending {
  border-left-color: #f59e0b;
  background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
}
.stat-card.approved {
  border-left-color: #10b981;
  background: linear-gradient(135deg, #f0fdf4 0%, #d1fae5 100%);
}
.stat-card.rejected {
  border-left-color: #ef4444;
  background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
}
.stat-card.users {
  border-left-color: #3b82f6;
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
}
.stat-card .stat-number {
  color: var(--text-primary);
  font-weight: 800;
}
.stat-card .stat-label {
  color: var(--text-secondary);
  font-weight: 600;
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
            <a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a>
            <a href="events/create.php"><i class="fas fa-plus"></i> Create Event</a>
            <a href="admin_manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a>
            <a href="dashboard_student.php"><i class="fas fa-eye"></i> Student View</a>
            <a href="settings.php"><i class="fa-solid fa-key"></i> Change Password</a>
            <a href="logout.php" style="color: var(--error);"><i class="fas fa-sign-out-alt"></i> Logout</a>
          <?php elseif ($role === 'teacher'): ?>
            <a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a>
            <a href="events/create.php"><i class="fas fa-plus"></i> Create Event</a>
            <a href="settings.php"><i class="fa-solid fa-key"></i> Change Password</a>
            <a href="logout.php" style="color: var(--error);"><i class="fas fa-sign-out-alt"></i> Logout</a>
          <?php else: ?>
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
      <div class="container container-lg">
        <div class="page-header">
          <h2 class="page-title">Admin Dashboard</h2>
        </div>

        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-number"><?= $stats['total'] ?></div>
            <div class="stat-label"><i class="fas fa-chart-bar"></i> Total Events</div>
          </div>
          <div class="stat-card pending">
            <div class="stat-number"><?= $stats['pending'] ?></div>
            <div class="stat-label"><i class="fas fa-hourglass-half"></i> Pending Review</div>
          </div>
          <div class="stat-card approved">
            <div class="stat-number"><?= $stats['approved'] ?></div>
            <div class="stat-label"><i class="fas fa-check-circle"></i> Approved</div>
          </div>
          <div class="stat-card rejected">
            <div class="stat-number"><?= $stats['rejected'] ?></div>
            <div class="stat-label"><i class="fas fa-times-circle"></i> Rejected</div>
          </div>
          <div class="stat-card users">
            <div class="stat-number"><?= $stats['users'] ?></div>
            <div class="stat-label"><i class="fas fa-users"></i> Total Users</div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="card-title">All Events</h3>
            <div style="display: flex; gap: 0.5rem;">
              <a href="events/create.php" class="btn btn-sm"><i class="fas fa-plus"></i> New Event</a>
            </div>
          </div>
          
          <?php if (!$result || $result->num_rows === 0): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><i class="fas fa-inbox fa-4x"></i></div>
              <h3 class="empty-state-title">No events found</h3>
              <p class="empty-state-text">Events will appear here once created</p>
              <a href="events/create.php" class="btn btn-sm" style="margin-top: 1rem; width: auto; display: inline-block;">Create First Event</a>
            </div>
          <?php else: ?>
            <div class="table-container">
              <table>
                <thead>
                  <tr>
                    <th>Title</th>
                    <th>Creator</th>
                    <th>Created</th>
                    <th>Status</th>
                    <th style="text-align: center;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($row = $result->fetch_assoc()): ?>
                  <tr>
                    <td>
                      <strong><?= e($row['title']) ?></strong>
                      <?php if ($row['image']): ?>
                        <br><small class="text-muted"><i class="fa-solid fa-camera"></i> Has image</small>
                      <?php endif; ?>
                    </td>
                    <td><?= e($row['creator_name']) ?></td>
                    <td><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
                    <td>
                      <span class="badge badge-<?= e($row['status']) ?>">
                        <?= e(ucfirst($row['status'])) ?>
                      </span>
                    </td>
                    <td style="text-align: center;">
                      <div style="display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap;">
                        <a href="events/view.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm" style="background: #e0e7ff; color: #4338ca; width: auto; padding: 0.375rem 0.75rem; font-size: 0.813rem;"><i class="fas fa-eye"></i> View</a>
                        <a href="events/edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm" style="background: #dbeafe; color: #1e40af; width: auto; padding: 0.375rem 0.75rem; font-size: 0.813rem;"><i class="fas fa-edit"></i> Edit</a>
                        <?php if ($row['status'] !== 'approved'): ?>
                          <form method="post" action="events/approve.php" style="display: inline; margin: 0;">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-sm btn-success" style="width: auto; padding: 0.375rem 0.75rem; font-size: 0.813rem;"><i class="fas fa-check"></i> Approve</button>
                          </form>
                        <?php endif; ?>
                        <?php if ($row['status'] !== 'rejected'): ?>
                          <form method="post" action="events/approve.php" style="display: inline; margin: 0;">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-sm btn-warning" style="width: auto; padding: 0.375rem 0.75rem; font-size: 0.813rem;"><i class="fas fa-times"></i> Reject</button>
                          </form>
                        <?php endif; ?>
                        <a href="events/delete.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-danger" style="width: auto; padding: 0.375rem 0.75rem; font-size: 0.813rem;" onclick="return confirm('Delete this event?')"><i class="fas fa-trash-alt"></i> Delete</a>
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
    });
  </script>
</body>
</html>