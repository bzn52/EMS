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
</head>
<body>
  <div class="page-wrapper">
    <header>
      <div class="header-content">
        <div class="header-left">
          <h1><i class="fas fa-calendar-alt"></i> Event Details</h1>
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
            <a href="index.php">← Back to Events</a>
            <a href="../<?= Auth::getDashboardUrl() ?>">Dashboard</a>
            <a href="../logout.php">Logout</a>
          </nav>
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
</body>
</html>