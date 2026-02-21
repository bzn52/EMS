<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
if (!defined('APP_INIT'))
    define('APP_INIT', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

Auth::requireLogin();
Auth::requireRole('admin');

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$role = Auth::role();

// Filter by event if specified
$filterEvent = (int) ($_GET['event_id'] ?? 0);

// Get all approved events for filter dropdown
$eventsDropdown = $conn->query("SELECT id, title FROM events WHERE status='approved' ORDER BY title ASC");

// Summary stats per event
$summarySQL = "SELECT e.id, e.title, e.event_date, e.event_time,
               COUNT(er.id) as join_count,
               CASE 
                 WHEN e.created_by_type='admin' THEN a.name
                 WHEN e.created_by_type='teacher' THEN t.name
                 ELSE 'Unknown'
               END as creator_name
               FROM events e
               LEFT JOIN event_registrations er ON e.id = er.event_id
               LEFT JOIN admins a ON e.created_by_type='admin' AND e.created_by_id = a.id
               LEFT JOIN teachers t ON e.created_by_type='teacher' AND e.created_by_id = t.id
               WHERE e.status = 'approved'
               GROUP BY e.id
               ORDER BY join_count DESC";
$summaryResult = $conn->query($summarySQL);

// Detailed registrations
$detailSQL = "SELECT er.id, er.joined_at, e.id as event_id, e.title as event_title,
              e.event_date, e.event_time,
              s.name as student_name, s.email as student_email
              FROM event_registrations er
              JOIN events e ON er.event_id = e.id
              JOIN students s ON er.student_id = s.id";
if ($filterEvent) {
    $detailSQL .= " WHERE er.event_id = " . $filterEvent;
}
$detailSQL .= " ORDER BY er.joined_at DESC";
$detailResult = $conn->query($detailSQL);

$totalRegistrations = $conn->query("SELECT COUNT(*) as cnt FROM event_registrations")->fetch_assoc()['cnt'];
$totalStudents = $conn->query("SELECT COUNT(DISTINCT student_id) as cnt FROM event_registrations")->fetch_assoc()['cnt'];
$totalEvents = $conn->query("SELECT COUNT(DISTINCT event_id) as cnt FROM event_registrations")->fetch_assoc()['cnt'];
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Event Registration Report</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-primary);
            padding: 1.25rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1;
            margin-bottom: 0.4rem;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .stat-card.green {
            border-left-color: #10b981;
            background: linear-gradient(135deg, #f0fdf4, #d1fae5);
        }

        .stat-card.blue {
            border-left-color: #3b82f6;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
        }

        .stat-card.purple {
            border-left-color: #8b5cf6;
            background: linear-gradient(135deg, #f5f3ff, #ede9fe);
        }

        .filter-bar {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-bar select {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
            margin: 2rem 0 1rem;
        }

        .progress-bar-wrap {
            background: var(--bg-secondary);
            border-radius: 999px;
            height: 6px;
            min-width: 80px;
            flex: 1;
        }

        .progress-bar {
            height: 6px;
            border-radius: 999px;
            background: var(--primary);
        }

        .user-menu-wrapper {
            position: relative;
        }

        .user-info {
            cursor: pointer;
            user-select: none;
        }

        .user-info .arrow-icon {
            margin-left: 0.5rem;
            font-size: 0.75rem;
            opacity: 0.6;
            transition: var(--transition);
        }

        .user-menu-wrapper.active .user-info .arrow-icon {
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
                            <div class="user-avatar">
                                <?= strtoupper(substr(Auth::name(), 0, 1)) ?>
                            </div>
                            <div>
                                <div>
                                    <?= e(Auth::name()) ?>
                                </div>
                                <span class="user-role-badge badge-admin">admin</span>
                            </div>
                            <i class="fas fa-chevron-down arrow-icon"></i>
                        </div>
                        <div class="dropdown-menu">
                            <a href="../dashboard_admin.php"><i class="fas fa-home"></i> Dashboard</a>
                            <a href="../profile.php"><i class="fa-solid fa-user"></i> Profile</a>
                            <a href="../events/create.php"><i class="fas fa-plus"></i> Create Event</a>
                            <a href="../admin_manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a>
                            <a href="../settings.php"><i class="fa-solid fa-key"></i> Change Password</a>
                            <a href="../logout.php" style="color: var(--error);"><i class="fas fa-sign-out-alt"></i>
                                Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main>
            <div class="container container-lg">
                <div class="page-header"
                    style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                    <h2 class="page-title"><i class="fas fa-chart-bar"></i> Registration Report</h2>
                    <a href="../dashboard_admin.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i>
                        Back</a>
                </div>

                <!-- Summary Stats -->
                <div class="stats-grid">
                    <div class="stat-card green">
                        <div class="stat-number">
                            <?= $totalRegistrations ?>
                        </div>
                        <div class="stat-label"><i class="fas fa-users"></i> Total Joins</div>
                    </div>
                    <div class="stat-card blue">
                        <div class="stat-number">
                            <?= $totalStudents ?>
                        </div>
                        <div class="stat-label"><i class="fas fa-user-graduate"></i> Students Participating</div>
                    </div>
                    <div class="stat-card purple">
                        <div class="stat-number">
                            <?= $totalEvents ?>
                        </div>
                        <div class="stat-label"><i class="fas fa-calendar-check"></i> Events With Joins</div>
                    </div>
                </div>

                <!-- Per-Event Summary -->
                <h3 class="section-title"><i class="fas fa-list-alt"></i> Joins Per Event</h3>
                <div class="card" style="padding: 0; overflow: hidden;">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Creator</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Joined</th>
                                    <th style="min-width:120px;">Progress</th>
                                    <th style="text-align:center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $maxJoins = 1;
                                $summaryRows = [];
                                while ($r = $summaryResult->fetch_assoc()) {
                                    $summaryRows[] = $r;
                                    $maxJoins = max($maxJoins, $r['join_count']);
                                }
                                foreach ($summaryRows as $r):
                                    ?>
                                    <tr>
                                        <td><strong>
                                                <?= e($r['title']) ?>
                                            </strong></td>
                                        <td>
                                            <?= e($r['creator_name']) ?>
                                        </td>
                                        <td>
                                            <?= $r['event_date'] ? date('M j, Y', strtotime($r['event_date'])) : '<span class="text-muted">—</span>' ?>
                                        </td>
                                        <td>
                                            <?= $r['event_time'] ? date('g:i A', strtotime($r['event_time'])) : '<span class="text-muted">—</span>' ?>
                                        </td>
                                        <td><strong>
                                                <?= $r['join_count'] ?>
                                            </strong></td>
                                        <td>
                                            <div class="progress-bar-wrap">
                                                <div class="progress-bar"
                                                    style="width: <?= $maxJoins > 0 ? round(($r['join_count'] / $maxJoins) * 100) : 0 ?>%">
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align:center;">
                                            <a href="?event_id=<?= $r['id'] ?>" class="btn btn-sm"
                                                style="background:#e0e7ff;color:#4338ca;border:1px solid #c7d2fe;width:auto;padding:0.3rem 0.7rem;font-size:0.8rem;">
                                                <i class="fas fa-users"></i> View Students
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($summaryRows)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align:center; color:var(--text-muted);">No registrations
                                            yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Detailed Registrations -->
                <div
                    style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-top:2rem; margin-bottom:1rem;">
                    <h3 class="section-title" style="margin:0;"><i class="fas fa-user-check"></i> Student Details
                        <?php if ($filterEvent): ?>
                            — <span style="font-size:1rem; color:var(--text-muted);">filtered</span>
                        <?php endif; ?>
                    </h3>
                    <div class="filter-bar" style="margin:0;">
                        <form method="get" style="display:flex; gap:0.5rem; align-items:center;">
                            <select name="event_id" onchange="this.form.submit()">
                                <option value="">All Events</option>
                                <?php while ($ev = $eventsDropdown->fetch_assoc()): ?>
                                    <option value="<?= $ev['id'] ?>" <?= $filterEvent === (int) $ev['id'] ? 'selected' : '' ?>>
                                        <?= e($ev['title']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <?php if ($filterEvent): ?>
                                <a href="?" class="btn btn-sm btn-outline">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Event</th>
                                    <th>Event Date</th>
                                    <th>Joined At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1;
                                $hasRows = false;
                                while ($r = $detailResult->fetch_assoc()):
                                    $hasRows = true; ?>
                                    <tr>
                                        <td>
                                            <?= $i++ ?>
                                        </td>
                                        <td><i class="fas fa-user-graduate"
                                                style="color:var(--primary); margin-right:0.4rem;"></i>
                                            <?= e($r['student_name']) ?>
                                        </td>
                                        <td>
                                            <?= e($r['student_email']) ?>
                                        </td>
                                        <td>
                                            <?= e($r['event_title']) ?>
                                        </td>
                                        <td>
                                            <?= $r['event_date'] ? date('M j, Y', strtotime($r['event_date'])) : '<span class="text-muted">—</span>' ?>
                                        </td>
                                        <td>
                                            <?= date('M j, Y g:i A', strtotime($r['joined_at'])) ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php if (!$hasRows): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center; color:var(--text-muted);">No registrations
                                            found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        const wrapper = document.querySelector('.user-menu-wrapper');
        const info = document.querySelector('.user-info');
        if (wrapper && info) {
            info.addEventListener('click', e => { e.stopPropagation(); wrapper.classList.toggle('active'); });
            document.addEventListener('click', e => { if (!wrapper.contains(e.target)) wrapper.classList.remove('active'); });
        }
    </script>
</body>

</html>