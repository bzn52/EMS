<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

Auth::requireLogin();

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$sql = "SELECT id, title, description, image, created_at FROM events WHERE status = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$approved = 'approved';
$stmt->bind_param('s', $approved);
$stmt->execute();
$result = $stmt->get_result();

$role = Auth::role();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Student Dashboard - Events</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

  <style>
    .event-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 1.5rem;
    }
    @media (max-width: 768px) {
      .event-grid {
        grid-template-columns: 1fr;
      }
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
            <a href="admin_manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a>
            <a href="settings.php"><i class="fa-solid fa-key"></i> Change Password</a>
            <a href="logout.php" style="color: var(--error);"><i class="fas fa-sign-out-alt"></i> Logout</a>
          <?php elseif ($role === 'teacher'): ?>
            <a href="teacher.php"><i class="fas fa-home"></i> Dashboard</a>
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
          <p class="page-subtitle">Discover the events happening in our school.</p>
        </div>

        <?php if (!$result || $result->num_rows === 0): ?>
          <div class="card">
            <div class="empty-state">
              <div class="empty-state-icon"><i class="fas fa-inbox fa-4x"></i></div>
              <h3 class="empty-state-title">No events available</h3>
              <p class="empty-state-text">Check back later for new events!</p>
            </div>
          </div>
        <?php else: ?>
          <div class="event-grid">
            <?php while ($row = $result->fetch_assoc()): ?>
              <article class="card">
                <?php if (!empty($row['image'])): ?>
                  <img src="uploads/<?= e($row['image']) ?>" alt="<?= e($row['title']) ?>" class="card-image" style="margin: -1.5rem -1.5rem 1rem -1.5rem; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
                <?php endif; ?>
                
                <div class="card-header" style="border-bottom: none; padding-bottom: 0.5rem;">
                  <h3 class="card-title"><?= e($row['title']) ?></h3>
                  <span class="badge badge-approved">Approved</span>
                </div>
                
                <div class="card-body">
                  <p class="text-muted text-sm mb-2">
                    <i class="fas fa-calendar-alt"></i> <?= date('F j, Y', strtotime($row['created_at'])) ?>
                  </p>
                  <p><?= nl2br(e(strlen($row['description']) > 150 ? substr($row['description'], 0, 150) . '...' : $row['description'])) ?></p>
                </div>
                
                <div class="card-footer">
                  <a href="events/view.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm" style="width: 100%;">View Details →</a>
                </div>
              </article>
            <?php endwhile; ?>
          </div>
        <?php endif; ?>
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