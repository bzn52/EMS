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
  </style>
</head>
<body>
  <div class="page-wrapper">
    <header>
      <div class="header-content">
        <div class="header-left">
          <h1><i class="fas fa-calendar-alt"></i> Events</h1>
        </div>
        <div class="header-right">
          <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr(Auth::name(), 0, 1)) ?></div>
            <div>
              <div><?= e(Auth::name()) ?></div>
              <span class="user-role-badge badge-<?= e($role) ?>"><?= e($role) ?></span>
            </div>
          </div>
          <nav class="nav-links">
            <?php if ($role === 'teacher' || $role === 'admin'): ?>
              <a href="events/create.php"><i class="fas fa-plus"></i> Create Event</a>
            <?php endif; ?>
            <?php if ($role === 'admin'): ?>
              <a href="dashboard_admin.php"><i class="fas fa-shield-alt"></i> Admin Panel</a>
            <?php elseif ($role === 'teacher'): ?>
              <a href="dashboard_teacher.php"><i class="fas fa-chalkboard-teacher"></i> My Events</a>
            <?php endif; ?>
            <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
            <a href="logout.php" style="color: var(--error);"><i class="fas fa-sign-out-alt"></i> Logout</a>
          </nav>
        </div>
      </div>
    </header>

    <main>
      <div class="container">
        <div class="page-header">
          <h2 class="page-title">Upcoming Events</h2>
          <p class="page-subtitle">Discover and participate in campus events</p>
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
</body>
</html>