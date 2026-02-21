<?php
if (session_status() === PHP_SESSION_NONE)
  session_start();
if (!defined('APP_INIT'))
  define('APP_INIT', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

Auth::requireLogin();

function e($s)
{
  return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$role = Auth::role();
$userId = Auth::id();
$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
  header('Location: ../dashboard_student.php');
  exit;
}

// Fetch event with creator info
$stmt = $conn->prepare(
  "SELECT e.*, 
     CASE 
       WHEN e.created_by_type='admin' THEN a.name
       WHEN e.created_by_type='teacher' THEN t.name
       ELSE 'Unknown'
     END as creator_name
     FROM events e
     LEFT JOIN admins a ON e.created_by_type='admin' AND e.created_by_id=a.id
     LEFT JOIN teachers t ON e.created_by_type='teacher' AND e.created_by_id=t.id
     WHERE e.id=?"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event || ($event['status'] !== 'approved' && $role === 'student')) {
  header('Location: ../dashboard_student.php');
  exit;
}

$successMsg = $_SESSION['event_success'] ?? '';
unset($_SESSION['event_success']);

// Check if student is already joined
$isJoined = false;
$joinCount = 0;
if ($role === 'student') {
  $chk = $conn->prepare("SELECT id FROM event_registrations WHERE event_id=? AND student_id=?");
  $chk->bind_param('ii', $id, $userId);
  $chk->execute();
  $isJoined = $chk->get_result()->num_rows > 0;
}
$cntResult = $conn->query("SELECT COUNT(*) as cnt FROM event_registrations WHERE event_id=$id");
$joinCount = $cntResult ? $cntResult->fetch_assoc()['cnt'] : 0;

$isOwner = ($role === 'admin') || ($event['created_by_type'] === 'teacher' && (int) $event['created_by_id'] === $userId);

$backLink = match ($role) {
  'admin' => '../dashboard_admin.php',
  'teacher' => '../dashboard_teacher.php',
  default => '../dashboard_student.php',
};
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($event['title']) ?></title>
  <link rel="stylesheet" href="../styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
    integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    .event-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .event-meta-item {
      display: flex;
      align-items: center;
      gap: 0.4rem;
      color: var(--text-muted);
      font-size: 0.9rem;
    }

    .join-section {
      margin-top: 1.5rem;
      padding-top: 1.5rem;
      border-top: 1px solid var(--border-light);
      display: flex;
      align-items: center;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .join-count {
      color: var(--text-muted);
      font-size: 0.9rem;
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
          <a href="<?= $backLink ?>" class="btn btn-outline btn-sm">
            <i class="fas fa-arrow-left"></i> Back
          </a>
        </div>
      </div>
    </header>

    <main>
      <div class="container" style="max-width: 760px;">
        <?php if ($successMsg): ?>
          <div class="message message-success"><?= e($successMsg) ?></div>
        <?php endif; ?>

        <div class="card">
          <?php if (!empty($event['image'])): ?>
            <img src="../uploads/<?= e($event['image']) ?>" alt="<?= e($event['title']) ?>" class="card-image">
          <?php endif; ?>

          <div class="card-header"
            style="border-bottom:none; padding-bottom:0.5rem; justify-content:space-between; align-items:flex-start;">
            <h2 class="card-title" style="font-size:1.5rem;"><?= e($event['title']) ?></h2>
            <span class="badge badge-<?= e($event['status']) ?>"><?= e(ucfirst($event['status'])) ?></span>
          </div>

          <div class="event-meta">
            <?php if (!empty($event['event_date'])): ?>
              <div class="event-meta-item">
                <i class="fas fa-calendar-alt" style="color:var(--primary);"></i>
                <strong><?= date('F j, Y', strtotime($event['event_date'])) ?></strong>
              </div>
            <?php endif; ?>
            <?php if (!empty($event['event_time'])): ?>
              <div class="event-meta-item">
                <i class="fas fa-clock" style="color:var(--primary);"></i>
                <strong><?= date('g:i A', strtotime($event['event_time'])) ?></strong>
              </div>
            <?php endif; ?>
            <?php if (!empty($event['creator_name'])): ?>
              <div class="event-meta-item">
                <i class="fas fa-user"></i> <?= e($event['creator_name']) ?>
              </div>
            <?php endif; ?>
            <div class="event-meta-item">
              <i class="fas fa-calendar-plus"></i> Posted <?= date('M j, Y', strtotime($event['created_at'])) ?>
            </div>
          </div>

          <div class="card-body" style="padding:0;">
            <p style="line-height:1.7; white-space:pre-wrap;"><?= e($event['description']) ?></p>
          </div>

          <?php if ($role === 'student' && $event['status'] === 'approved'): ?>
            <div class="join-section">
              <form method="post" action="join.php">
                <?= CSRF::field() ?>
                <input type="hidden" name="event_id" value="<?= $id ?>">
                <input type="hidden" name="action" value="<?= $isJoined ? 'leave' : 'join' ?>">
                <button type="submit" class="btn <?= $isJoined ? 'btn-outline' : '' ?>">
                  <?php if ($isJoined): ?>
                    <i class="fas fa-times-circle"></i> Leave Event
                  <?php else: ?>
                    <i class="fas fa-check-circle"></i> Join Event
                  <?php endif; ?>
                </button>
              </form>
              <span class="join-count">
                <i class="fas fa-users"></i> <?= $joinCount ?> student<?= $joinCount !== 1 ? 's' : '' ?> joined
              </span>
            </div>
          <?php elseif ($role !== 'student'): ?>
            <div class="join-section">
              <span class="join-count">
                <i class="fas fa-users"></i> <?= $joinCount ?> student<?= $joinCount !== 1 ? 's' : '' ?> joined
              </span>
            </div>
          <?php endif; ?>

          <?php if ($isOwner || $role === 'admin'): ?>
            <div class="card-footer" style="margin-top:1.5rem;">
              <a href="edit.php?id=<?= $id ?>" class="btn btn-sm btn-outline"><i class="fas fa-edit"></i> Edit</a>
              <a href="delete.php?id=<?= $id ?>" class="btn btn-sm btn-danger"
                onclick="return confirm('Delete this event?')"><i class="fas fa-trash-alt"></i> Delete</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>
</body>

</html>