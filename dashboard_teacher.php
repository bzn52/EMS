<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();
Auth::requireRole(['teacher', 'admin']);

function e($s) { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$userId = Auth::id();
$role = Auth::role();

$successMessage = $_SESSION['delete_success'] ?? '';
unset($_SESSION['delete_success']);

// Get ALL approved events
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
        WHERE e.status = 'approved'
        ORDER BY e.created_at DESC";
$allEvents = $conn->query($sql);

// Get teacher's own events for statistics
$myEvents = null;
$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];

$statsQuery = $conn->prepare("SELECT status, COUNT(*) as count FROM events WHERE created_by_type = 'teacher' AND created_by_id = ? GROUP BY status");
$statsQuery->bind_param('i', $userId);
$statsQuery->execute();
$statsResult = $statsQuery->get_result();
while ($row = $statsResult->fetch_assoc()) {
    $stats[$row['status']] = $row['count'];
    $stats['total'] += $row['count'];
}

// Get my recent events
$myStmt = $conn->prepare("SELECT id, title, description, image, status, created_at 
                          FROM events 
                          WHERE created_by_type = 'teacher' AND created_by_id = ? 
                          ORDER BY created_at DESC 
                          LIMIT 5");
$myStmt->bind_param('i', $userId);
$myStmt->execute();
$myEvents = $myStmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Teacher Dashboard</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
      margin-bottom: 2rem;
    }
    .stat-card {
      background: var(--bg-primary);
      padding: 1.25rem;
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      border-left: 4px solid var(--primary);
      transition: var(--transition);
    }
    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }
    .stat-card.pending { border-left-color: var(--warning); }
    .stat-card.approved { border-left-color: var(--success); }
    .stat-card.rejected { border-left-color: var(--error); }
    .stat-number {
      font-size: 2rem;
      font-weight: 700;
      color: var(--primary);
      line-height: 1;
      margin-bottom: 0.5rem;
    }
    .stat-label {
      color: var(--text-muted);
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-weight: 600;
    }
    .section-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--primary);
      margin: 2rem 0 1rem 0;
    }
    .event-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 1.5rem;
    }
    .owner-badge {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      background: var(--primary);
      color: var(--bg-primary);
      border-radius: 999px;
      font-size: 0.7rem;
      font-weight: 700;
      margin-left: 0.5rem;
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
      <div class="container">
        <div class="page-header">
          <h2 class="page-title">Welcome, <?= e(Auth::name()) ?>!</h2>
          <p class="page-subtitle">View events and manage your creations</p>
        </div>

        <?php if ($successMessage): ?>
          <div class="message message-success">
            <?= e($successMessage) ?>
          </div>
        <?php endif; ?>

        <?php if ($stats['total'] > 0): ?>
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-number"><?= $stats['total'] ?></div>
            <div class="stat-label"><i class="fas fa-chart-bar"></i> My Total Events</div>
          </div>
          <div class="stat-card pending">
            <div class="stat-number"><?= $stats['pending'] ?></div>
            <div class="stat-label"><i class="fas fa-hourglass-half"></i> My Pending</div>
          </div>
          <div class="stat-card approved">
            <div class="stat-number"><?= $stats['approved'] ?></div>
            <div class="stat-label"><i class="fas fa-check-circle"></i> My Approved</div>
          </div>
          <div class="stat-card rejected">
            <div class="stat-number"><?= $stats['rejected'] ?></div>
            <div class="stat-label"><i class="fas fa-times-circle"></i> My Rejected</div>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($myEvents && $myEvents->num_rows > 0): ?>
        <h3 class="section-title"><i class="fas fa-clipboard-list"></i> My Recent Events</h3>
        <div class="event-grid">
          <?php while ($row = $myEvents->fetch_assoc()): ?>
            <article class="card">
              <?php if (!empty($row['image'])): ?>
                <img src="uploads/<?= e($row['image']) ?>" alt="<?= e($row['title']) ?>" class="card-image" style="margin: -2rem -2rem 1rem -2rem; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
              <?php endif; ?>
              
              <div class="card-header" style="border-bottom: none; padding-bottom: 0.5rem;">
                <h3 class="card-title"><?= e($row['title']) ?></h3>
                <span class="badge badge-<?= e($row['status']) ?>">
                  <?= e(ucfirst($row['status'])) ?>
                </span>
              </div>
              
              <div class="card-body">
                <p class="text-muted text-sm mb-2">
                  <i class="fas fa-calendar-alt"></i> <?= date('F j, Y', strtotime($row['created_at'])) ?>
                </p>
                <p><?= nl2br(e(strlen($row['description']) > 150 ? substr($row['description'], 0, 150) . '...' : $row['description'])) ?></p>
              </div>
              
              <div class="card-footer">
                <a href="events/view.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-eye"></i> View</a>
                <a href="events/edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm"><i class="fas fa-edit"></i> Edit</a>
                <a href="events/delete.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this event?')"><i class="fas fa-trash-alt"></i> Delete</a>
              </div>
            </article>
          <?php endwhile; ?>
        </div>
        <?php endif; ?>

        <h3 class="section-title"><i class="fas fa-calendar-check"></i> All Upcoming Events</h3>
        <?php if (!$allEvents || $allEvents->num_rows === 0): ?>
          <div class="card">
            <div class="empty-state">
              <div class="empty-state-icon"><i class="fas fa-inbox fa-4x"></i></div>
              <h3 class="empty-state-title">No events available</h3>
              <p class="empty-state-text">Check back later for new events or create one!</p>
              <a href="events/create.php" class="btn btn-sm" style="margin-top: 1rem; width: auto; display: inline-block;">Create Event</a>
            </div>
          </div>
        <?php else: ?>
          <div class="event-grid">
            <?php while ($row = $allEvents->fetch_assoc()): 
              $isOwner = ($row['created_by_type'] === 'teacher' && (int)$row['created_by_id'] === $userId);
            ?>
              <article class="card">
                <?php if (!empty($row['image'])): ?>
                  <img src="uploads/<?= e($row['image']) ?>" alt="<?= e($row['title']) ?>" class="card-image" style="margin: -2rem -2rem 1rem -2rem; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
                <?php endif; ?>
                
                <div class="card-header" style="border-bottom: none; padding-bottom: 0.5rem;">
                  <h3 class="card-title">
                    <?= e($row['title']) ?>
                    <?php if ($isOwner): ?>
                      <span class="owner-badge"><i class="fa-solid fa-user"></i> Mine</span>
                    <?php endif; ?>
                  </h3>
                  <span class="badge badge-approved">Approved</span>
                </div>
                
                <div class="card-body">
                  <p class="text-muted text-sm mb-2">
                    <i class="fas fa-calendar-alt"></i> <?= date('F j, Y', strtotime($row['created_at'])) ?>
                    <?php if (!empty($row['creator_name'])): ?>
                      <br><i class="fa-solid fa-user"></i> By <?= e($row['creator_name']) ?>
                    <?php endif; ?>
                  </p>
                  <p><?= nl2br(e(strlen($row['description']) > 150 ? substr($row['description'], 0, 150) . '...' : $row['description'])) ?></p>
                </div>
                
                <div class="card-footer">
                  <a href="events/view.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm" style="width: 100%;">View Details →</a>
                  <?php if ($isOwner): ?>
                    <a href="events/edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-edit"></i> Edit</a>
                    <a href="events/delete.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this event?')"><i class="fas fa-trash-alt"></i> Delete</a>
                  <?php endif; ?>
                </div>
              </article>
            <?php endwhile; ?>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</body>
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
</html>