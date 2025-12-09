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

// Check success message
$successMessage = $_SESSION['delete_success'] ?? '';
unset($_SESSION['delete_success']);

// Check if created_by column exists
$created_by_exists = false;
$colRes = $conn->query("SHOW COLUMNS FROM `events` LIKE 'created_by'");
if ($colRes && $colRes->num_rows > 0) $created_by_exists = true;

// Get ALL approved events (like students see)
$sql = "SELECT e.id, e.title, e.description, e.image, e.status, e.created_at, 
        u.name as creator_name, e.created_by
        FROM events e
        LEFT JOIN users u ON e.created_by = u.id
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

if ($created_by_exists) {
    $statsQuery = $conn->prepare("SELECT status, COUNT(*) as count FROM events WHERE created_by = ? GROUP BY status");
    $statsQuery->bind_param('i', $userId);
    $statsQuery->execute();
    $statsResult = $statsQuery->get_result();
    while ($row = $statsResult->fetch_assoc()) {
        $stats[$row['status']] = $row['count'];
        $stats['total'] += $row['count'];
    }
    
    // Get my events
    $myStmt = $conn->prepare("SELECT id, title, description, image, status, created_at 
                              FROM events 
                              WHERE created_by = ? 
                              ORDER BY created_at DESC 
                              LIMIT 5");
    $myStmt->bind_param('i', $userId);
    $myStmt->execute();
    $myEvents = $myStmt->get_result();
}
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
  </style>
</head>
<body>
  <div class="page-wrapper">
    <header>
      <div class="header-content">
        <div class="header-left">
          <h1><i class="fas fa-chalkboard-teacher"></i> Teacher Dashboard</h1>
        </div>
        <div class="header-right">
          <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr(Auth::name(), 0, 1)) ?></div>
            <div>
              <div><?= e(Auth::name()) ?></div>
              <span class="user-role-badge badge-teacher"><?= e($role) ?></span>
            </div>
          </div>
          <nav class="nav-links">
            <a href="events/create.php"><i class="fas fa-plus"></i> Create Event</a>
            <?php if ($role === 'admin'): ?>
              <a href="dashboard_admin.php"><i class="fas fa-shield-alt"></i> Admin Panel</a>
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
          <h2 class="page-title">Welcome, <?= e(Auth::name()) ?>!</h2>
          <p class="page-subtitle">View events and manage your creations</p>
        </div>

        <?php if ($successMessage): ?>
          <div class="message message-success">
            <?= e($successMessage) ?>
          </div>
        <?php endif; ?>

        <?php if ($created_by_exists && $stats['total'] > 0): ?>
        <!-- My Statistics -->
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

        <!-- My Recent Events -->
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

        <!-- All Approved Events -->
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
              $isOwner = $created_by_exists && (int)$row['created_by'] === $userId;
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
                    <!-- Only show edit/delete for own events -->
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
</html>