<?php
require_once __DIR__ . '/events_common.php';
Auth::requireLogin();

$role = Auth::role();
$status_col = EVENTS_STATUS_COL;

// Fetch events: students see only approved; others see all
if ($role === 'student') {
    $sql = "SELECT id, title, description, image, {$status_col} AS status, created_at FROM events WHERE {$status_col} = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $approved = 'approved';
    $stmt->bind_param('s', $approved);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query("SELECT id, title, description, image, {$status_col} AS status, created_at FROM events ORDER BY created_at DESC");
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Events List</title>
  <link rel="stylesheet" href="../styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
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
              <span class="user-role-badge"><?= e($role) ?></span>
            </div>
          </div>
          <nav class="nav-links">
            <?php if ($role === 'teacher' || $role === 'admin'): ?>
              <a href="create.php"><i class="fas fa-plus"></i> Create Event</a>
            <?php endif; ?>
            <a href="../<?= Auth::getDashboardUrl() ?>">Dashboard</a>
            <a href="../logout.php">Logout</a>
          </nav>
        </div>
      </div>
    </header>

    <main>
      <div class="container">
        <?php if (!$res || $res->num_rows === 0): ?>
          <div class="card">
            <div class="empty-state">
              <div class="empty-state-icon"><i class="fas fa-inbox fa-4x"></i></div>
              <h3 class="empty-state-title">No events found</h3>
              <p class="empty-state-text">Check back later for new events!</p>
            </div>
          </div>
        <?php else: ?>
          <div class="grid grid-cols-1">
            <?php while ($row = $res->fetch_assoc()): ?>
              <article class="card">
                <div class="card-header">
                  <h3 class="card-title"><?= e($row['title']) ?></h3>
                  <?php if(isset($row['status'])): ?>
                    <span class="badge badge-<?= e($row['status']) ?>">
                      <?= e(ucfirst($row['status'])) ?>
                    </span>
                  <?php endif; ?>
                </div>
                
                <?php if (!empty($row['image'])): ?>
                  <img src="../uploads/<?= e($row['image']) ?>" alt="<?= e($row['title']) ?>" class="card-image">
                <?php endif; ?>
                
                <div class="card-body">
                  <p class="text-muted text-sm mb-2">
                    <i class="fas fa-calendar-alt"></i> <?= date('F j, Y', strtotime($row['created_at'])) ?>
                  </p>
                  <p><?= nl2br(e(strlen($row['description']) > 300 ? substr($row['description'], 0, 300) . '...' : $row['description'])) ?></p>
                </div>
                
                <div class="card-footer">
                  <a href="view.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm">View Details</a>
                  
                  <?php if (in_array($role, ['teacher', 'admin'])): ?>
                    <a href="edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                    <a href="delete.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this event?');">Delete</a>
                    
                    <?php if ($role === 'admin'): ?>
                      <?php if ($row['status'] !== 'approved'): ?>
                        <a href="approve.php?id=<?= (int)$row['id'] ?>&action=approve" class="btn btn-sm btn-success">Approve</a>
                      <?php endif; ?>
                      <?php if ($row['status'] !== 'rejected'): ?>
                        <a href="approve.php?id=<?= (int)$row['id'] ?>&action=reject" class="btn btn-sm btn-warning">Reject</a>
                      <?php endif; ?>
                    <?php endif; ?>
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