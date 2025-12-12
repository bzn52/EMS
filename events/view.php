<?php
// View single event
require_once __DIR__ . '/events_common.php';
Auth::requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { 
    http_response_code(400); 
    die("Missing event ID"); 
}

$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$event = $res->fetch_assoc();
$stmt->close();

if (!$event) { 
    http_response_code(404); 
    die("Event not found"); 
}

// Students can't view unapproved events
$role = Auth::role();
if ($role === 'student' && ($event[EVENTS_STATUS_COL] ?? '') !== 'approved') {
    http_response_code(403); 
    die("This event is not available.");
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($event['title']) ?> - Event Details</title>
  <link rel="stylesheet" href="../styles.css">
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
            <a href="../dashboard_admin.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="../profile.php"><i class="fa-solid fa-user"></i> Profile</a>
            <a href="create.php"><i class="fas fa-plus"></i> Create Event</a>
            <a href="../admin_manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a>
            <a href="../dashboard_student.php"><i class="fas fa-eye"></i> Student View</a>
            <a href="../settings.php"><i class="fas fa-cog"></i> Settings</a>
            <a href="../logout.php" style="color: var(--error);"><i class="fas fa-sign-out-alt"></i> Logout</a>
          <?php elseif ($role === 'teacher'): ?>
            <a href="../dashboard_teacher.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="../profile.php"><i class="fa-solid fa-user"></i> Profile</a>            
            <a href="create.php"><i class="fas fa-plus"></i> Create Event</a>
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
      <div class="container container-sm">
        <div class="card">
          <div class="card-header">
            <h2 class="card-title"><?= e($event['title']) ?></h2>
            <span class="badge badge-<?= e($event[EVENTS_STATUS_COL]) ?>">
              <?= e(ucfirst($event[EVENTS_STATUS_COL])) ?>
            </span>
          </div>
          
          <?php if (!empty($event['image'])): ?>
            <img src="../uploads/<?= e($event['image']) ?>" alt="<?= e($event['title']) ?>" class="card-image">
          <?php endif; ?>
          
          <div class="card-body">
            <p class="text-muted text-sm mb-2">
              <i class="fas fa-calendar-alt"></i> Posted on <?= date('F j, Y \a\t g:i A', strtotime($event['created_at'])) ?>
            </p>
            
            <div style="margin-top: 1.5rem; line-height: 1.8;">
              <?= nl2br(e($event['description'])) ?>
            </div>
          </div>
          
          <div class="card-footer">
            <?php if (in_array($role, ['teacher', 'admin'])): ?>
              <a href="edit.php?id=<?= (int)$event['id'] ?>" class="btn btn-sm">Edit Event</a>
              <a href="delete.php?id=<?= (int)$event['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this event?');">Delete Event</a>
            <?php endif; ?>

            <?php if ($role === 'admin'): ?>
              <?php if ($event[EVENTS_STATUS_COL] !== 'approved'): ?>
                <a href="approve.php?id=<?= (int)$event['id'] ?>&action=approve" class="btn btn-sm btn-success">✓ Approve</a>
              <?php endif; ?>
              <?php if ($event[EVENTS_STATUS_COL] !== 'rejected'): ?>
                <a href="approve.php?id=<?= (int)$event['id'] ?>&action=reject" class="btn btn-sm btn-warning">✗ Reject</a>
              <?php endif; ?>
            <?php endif; ?>
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
  });
  </script>
</body>
</html>