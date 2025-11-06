<?php
// associate_dashboard.php
// Requires: config.php (should create $conn = new mysqli(...))
// This file expects $_SESSION['associate_id'] to be set.

session_start();
require 'config.php';

if (!isset($_SESSION['associate_id']) || empty($_SESSION['associate_id'])) {
    header("Location: index.php");
    exit();
}

$associate_id = intval($_SESSION['associate_id']);

// Fetch associate
$assoc_stmt = $conn->prepare("SELECT * FROM dm_associates WHERE id = ?");
$assoc_stmt->bind_param("i", $associate_id);
$assoc_stmt->execute();
$assoc_res = $assoc_stmt->get_result();
if ($assoc_res->num_rows === 0) {
    echo "Associate not found.";
    exit();
}
$associate = $assoc_res->fetch_assoc();

// Find assigned client (prefer clients.assigned_associate_id -> dm_associates.assigned_client_id)
$client = null;
$client_stmt = $conn->prepare("
    SELECT c.* FROM clients c
    WHERE c.assigned_associate_id = ?
    LIMIT 1
");
$client_stmt->bind_param("i", $associate_id);
$client_stmt->execute();
$client_res = $client_stmt->get_result();
if ($client_res->num_rows > 0) {
    $client = $client_res->fetch_assoc();
} else {
    $assoc_client_stmt = $conn->prepare("SELECT assigned_client_id FROM dm_associates WHERE id = ?");
    $assoc_client_stmt->bind_param("i", $associate_id);
    $assoc_client_stmt->execute();
    $ac_res = $assoc_client_stmt->get_result();
    $ac_row = $ac_res->fetch_assoc();
    $assigned_client_id = intval($ac_row['assigned_client_id'] ?? 0);
    if ($assigned_client_id > 0) {
        $cstmt = $conn->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
        $cstmt->bind_param("i", $assigned_client_id);
        $cstmt->execute();
        $cres = $cstmt->get_result();
        if ($cres->num_rows > 0) $client = $cres->fetch_assoc();
    }
}

// If no client assigned
if (!$client) {
    $client = [
        'id' => 0,
        'name' => 'No client assigned',
        'address' => '—',
        'email' => '—',
        'logo' => '',
        'plan_id' => null,
        'website_link' => ''
    ];
    $no_client = true;
} else {
    $no_client = false;
    $client_id = (int)$client['id'];
}

// Fetch plan totals & client_analysis
$planTotals = ['total_posts' => 0, 'total_reels' => 0, 'total_blogs' => 0];
$completed = ['completed_posts' => 0, 'completed_reels' => 0, 'completed_blogs' => 0];
$total_percent_done = 0.0;

if (!$no_client) {
    $ca_stmt = $conn->prepare("SELECT * FROM client_analysis WHERE client_id = ? LIMIT 1");
    $ca_stmt->bind_param("i", $client_id);
    $ca_stmt->execute();
    $ca_res = $ca_stmt->get_result();
    if ($ca_res && $ca_res->num_rows > 0) {
        $ca = $ca_res->fetch_assoc();
        $planTotals = [
            'total_posts' => (int)($ca['total_posts'] ?? 0),
            'total_reels' => (int)($ca['total_reels'] ?? 0),
            'total_blogs' => (int)($ca['total_blogs'] ?? 0)
        ];
        $completed = [
            'completed_posts' => (int)($ca['completed_posts'] ?? 0),
            'completed_reels' => (int)($ca['completed_reels'] ?? 0),
            'completed_blogs' => (int)($ca['completed_blogs'] ?? 0)
        ];
        $total_percent_done = floatval($ca['total_percent_done'] ?? 0.0);
    } else {
        if (!empty($client['plan_id'])) {
            $pstmt = $conn->prepare("SELECT total_posts, total_reels, total_blogs FROM plans WHERE id = ? LIMIT 1");
            $pstmt->bind_param("i", $client['plan_id']);
            $pstmt->execute();
            $pres = $pstmt->get_result();
            if ($pres && $pres->num_rows > 0) {
                $prow = $pres->fetch_assoc();
                $planTotals = [
                    'total_posts' => (int)($prow['total_posts'] ?? 0),
                    'total_reels' => (int)($prow['total_reels'] ?? 0),
                    'total_blogs' => (int)($prow['total_blogs'] ?? 0)
                ];
            }
        }
    }
}

$sumTotals = array_sum($planTotals);
$sumCompleted = array_sum($completed);
$displayPercent = ($sumTotals > 0) ? round(($sumCompleted / $sumTotals) * 100, 1) : 0;

// Handle POST update
$update_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$no_client) {
    $cp = max(0, min(intval($_POST['completed_posts'] ?? 0), $planTotals['total_posts']));
    $cr = max(0, min(intval($_POST['completed_reels'] ?? 0), $planTotals['total_reels']));
    $cb = max(0, min(intval($_POST['completed_blogs'] ?? 0), $planTotals['total_blogs']));

    $newSum = $cp + $cr + $cb;
    $newPercent = ($sumTotals > 0) ? round(($newSum / $sumTotals) * 100, 1) : 0;

    $check_ca = $conn->prepare("SELECT id FROM client_analysis WHERE client_id = ? LIMIT 1");
    $check_ca->bind_param("i", $client_id);
    $check_ca->execute();
    $check_res = $check_ca->get_result();

    if ($check_res && $check_res->num_rows > 0) {
        $ca_id = $check_res->fetch_assoc()['id'];
        $upd = $conn->prepare("UPDATE client_analysis SET completed_posts = ?, completed_reels = ?, completed_blogs = ?, total_percent_done = ?, last_updated = CURRENT_TIMESTAMP WHERE id = ?");
        $upd->bind_param("iiidi", $cp, $cr, $cb, $newPercent, $ca_id);
        $upd->execute() or $conn->query("UPDATE client_analysis SET completed_posts = $cp, completed_reels = $cr, completed_blogs = $cb, total_percent_done = $newPercent WHERE id = $ca_id");
    } else {
        $plan_id_val = $client['plan_id'] ?? 0;
        $ins = $conn->prepare("INSERT INTO client_analysis (client_id, plan_id, total_posts, completed_posts, total_reels, completed_reels, total_blogs, completed_blogs, total_percent_done) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param("iiiiiiiii", $client_id, $plan_id_val, $planTotals['total_posts'], $cp, $planTotals['total_reels'], $cr, $planTotals['total_blogs'], $cb, $newPercent);
        $ins->execute() or $conn->query("INSERT INTO client_analysis (client_id, plan_id, total_posts, completed_posts, total_reels, completed_reels, total_blogs, completed_blogs, total_percent_done) VALUES ($client_id, $plan_id_val, {$planTotals['total_posts']}, $cp, {$planTotals['total_reels']}, $cr, {$planTotals['total_blogs']}, $cb, $newPercent)");
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// Fetch tasks
$tasks = [];
if (!$no_client) {
    $tstmt = $conn->prepare("SELECT t.*, a.name AS associate_name FROM tasks t LEFT JOIN dm_associates a ON t.associate_id = a.id WHERE t.client_id = ? ORDER BY t.created_at DESC LIMIT 25");
    $tstmt->bind_param("i", $client_id);
    $tstmt->execute();
    $tres = $tstmt->get_result();
    while ($tr = $tres->fetch_assoc()) $tasks[] = $tr;
}

// Task stats
$totalTasks = $inProgress = $overdue = 0;
if (!$no_client) {
    $count_stmt = $conn->prepare("SELECT 
        COUNT(*) AS total_tasks,
        SUM(status = 'Pending') AS in_progress,
        SUM(status = 'Pending' AND deadline < CURRENT_DATE()) AS overdue_tasks
        FROM tasks WHERE client_id = ?");
    $count_stmt->bind_param("i", $client_id);
    $count_stmt->execute();
    $count_res = $count_stmt->get_result()->fetch_assoc();
    $totalTasks = (int)($count_res['total_tasks'] ?? 0);
    $inProgress = (int)($count_res['in_progress'] ?? 0);
    $overdue = (int)($count_res['overdue_tasks'] ?? 0);
}

// Descriptions
$desc_posts = "Scheduled posts to maintain brand voice and engagement across platforms. Includes static posts, carousels & image creatives.";
$desc_reels = "Short-form video content for higher reach and trending engagement across social platforms.";
$desc_blogs = "SEO-friendly long-form articles to boost organic search traffic and domain authority.";

// Logo path
function client_logo_path($client) {
    if (!empty($client['logo'])) {
        $path1 = __DIR__ . '/../dm_admin/uploads/clients/' . basename($client['logo']);
        $rel1 = '../dm_admin/uploads/clients/' . basename($client['logo']);
        if (file_exists($path1)) return $rel1;
        return htmlspecialchars($client['logo']);
    }
    return 'assets/default-logo.png';
}
$logo_url = client_logo_path($client);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Associate Dashboard — GiggleZen</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="style.css" rel="stylesheet">
    <style>
        :root {
            --gold: #c19a2c;
            --gold-light: #f5d88a;
            --dark: #1e293b;
            --white: #ffffff;
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #334155;
            --text-light: #64748b;
            --shadow: 0 4px 12px rgba(0, 0, 0, .06);
            --radius: 16px;
            --transition: all .2s ease;
        }

        body { background: var(--bg); color: var(--text); font-family: system-ui, -apple-system, sans-serif; }
        .container { max-width: 1400px; }

        /* Layout */
        .dashboard {
            display: grid;
            gap: clamp(1rem, 3vw, 1.75rem);
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
            align-items: start;
        }

        .card {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .card-header {
            padding: clamp(1rem, 2vw, 1.5rem);
            background: #f8f9fa;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .card-title {
            font-size: clamp(.95rem, 2.5vw, 1.1rem);
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .card-title i { color: var(--gold); font-size: 1.2em; }

        .card-body { padding: clamp(1rem, 2.5vw, 1.5rem); flex: 1; }

        /* Client Photo */
        .client-photo {
            width: clamp(70px, 20vw, 90px);
            height: clamp(70px, 20vw, 90px);
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 6px 16px rgba(193,154,44,.2);
            border-radius: 50%;
            display: block;
            margin: 0 auto 1rem;
        }

        .info-row { display: flex; gap: .75rem; align-items: flex-start; }
        .info-icon {
            flex-shrink: 0;
            width: 36px; height: 36px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold);
            font-size: 1.1rem;
        }

        /* Form */
        .task-form .form-control {
            font-size: clamp(.85rem, 2vw, .9rem);
            padding: clamp(.5rem, 1.5vw, .75rem);
            border: 1px solid var(--gold);
            border-radius: 8px;
        }
        .btn-update {
            background: var(--gold);
            color: white;
            border: none;
            border-radius: 8px;
            padding: clamp(.5rem, 1.5vw, .75rem);
            font-size: clamp(.85rem, 2vw, .9rem);
            font-weight: 600;
            transition: var(--transition);
            width: 100%;
        }
        .btn-update:hover { background: var(--gold-light); color: var(--dark); }

        /* Progress Row */
        .progress-row {
            display: flex;
            flex-wrap: wrap;
            gap: clamp(1rem, 2vw, 1.5rem);
            justify-content: center;
        }
        .stat-card {
            flex: 1 1 200px;
            max-width: 260px;
            padding: clamp(1rem, 2vw, 1.5rem);
            text-align: center;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,.08);
            transition: var(--transition);
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card h3 { font-size: clamp(1.4rem, 4vw, 1.75rem); margin-bottom: .25rem; color: var(--gold); }
        .stat-card p { margin: 0; font-size: clamp(.75rem, 2vw, .875rem); color: var(--text-light); }

        .progress-circle {
            --size: clamp(90px, 30vw, 180px);
            width: var(--size);
            height: var(--size);
            background: conic-gradient(var(--gold) 0deg, #e9ecef 0deg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            margin: auto;
        }
        .progress-circle::before {
            content: "";
            position: absolute;
            width: calc(var(--size) * .8);
            height: calc(var(--size) * .8);
            background: #fff;
            border-radius: 50%;
        }
        .progress-circle span {
            font-size: clamp(1rem, 3vw, 1.5rem);
            font-weight: 600;
            color: var(--dark);
        }

        /* Task List */
        .progress-list { max-height: 400px; overflow-y: auto; }
        .progress-item + .progress-item { margin-top: 1rem; }

        /* Badges */
        .badge { font-size: .75rem; padding: .25rem .6rem; border-radius: 14px; text-transform: uppercase; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-completed { background: #d1fae5; color: #10b981; }

       @media (max-width: 480px) {
            .topbar{
             
             
                
            }
            
        }
        
        
    </style>
</head>
<body>
<div class="app">
<!-- SIDEBAR (Replace your current <aside> and toggle button) -->
<aside class="sidebar">
    <ul class="nav-tabs">
        <li><a href="dashboard.php" class="active"><i class="bi bi-house-door-fill"></i><span class="txt">Home</span></a></li>
        <li><a href="daily_tasks.php"><i class="bi bi-box-seam"></i><span class="txt">Daily Tasks</span></a></li>
        <li><a href="bills.php"><i class="bi bi-receipt"></i><span class="txt">Bill Upload</span></a></li>
        <li><a href="attendance.php"><i class="bi bi-calendar-check"></i><span class="txt">Attendance</span></a></li>
    </ul>
</aside>

<!-- Toggle Button (Fixed Positioning) -->
<button class="sidebar-toggle d-lg-none" aria-label="Toggle sidebar">
    <i class="bi bi-box-arrow-in-right"></i>
</button>
    <main class="main container py-4">
        <div class="topbar mb-4">
            <div class="brand">
                <div class="logo">GZ</div>
                <div>
                    <h3 class="mb-0">GiggleZen</h3>
                    <small>Associate Dashboard</small>
                </div>
            </div>

            <div class="ms-auto d-flex align-items-center gap-3">
                <div class="text-end">
                    <div style="font-weight:600"><?= htmlspecialchars($associate['name'] ?: 'Associate') ?></div>
                    <div style="font-size:0.85rem;color:#64748b"><?= htmlspecialchars($associate['email'] ?? '') ?></div>
                </div>
                <!-- <div class="avatar"><?= strtoupper(substr($associate['name'] ?? 'A', 0, 1)) ?></div> -->
                <button id="logoutBtn" class="logoutButton">
                    <svg class="doorway" viewBox="0 0 100 100">
                        <path d="M93.4 86.3H58.6c-1.9 0-3.4-1.5-3.4-3.4V17.1c0-1.9 1.5-3.4 3.4-3.4h34.8c1.9 0 3.4 1.5 3.4 3.4v65.8c0 1.9-1.5 3.4-3.4 3.4z" />
                        <path class="bang" d="M40.5 43.7L26.6 31.4l-2.5 6.7zM41.9 50.4l-19.5-4-1.4 6.3zM40 57.4l-17.7 3.9 3.9 5.7z" />
                    </svg>
                    <svg class="figure" viewBox="0 0 100 100">
                        <circle cx="52.1" cy="32.4" r="6.4" />
                        <path d="M50.7 62.8c-1.2 2.5-3.6 5-7.2 4-3.2-.9-4.9-3.5-4-7.8.7-3.4 3.1-13.8 4.1-15.8 1.7-3.4 1.6-4.6 7-3.7 4.3.7 4.6 2.5 4.3 5.4-.4 3.7-2.8 15.1-4.2 17.9z" />
                        <g class="arm1">
                            <path d="M55.5 56.5l-6-9.5c-1-1.5-.6-3.5.9-4.4 1.5-1 3.7-1.1 4.6.4l6.1 10c1 1.5.3 3.5-1.1 4.4-1.5.9-3.5.5-4.5-.9z" />
                            <path class="wrist1" d="M69.4 59.9L58.1 58c-1.7-.3-2.9-1.9-2.6-3.7.3-1.7 1.9-2.9 3.7-2.6l11.4 1.9c1.7.3 2.9 1.9 2.6 3.7-.4 1.7-2 2.9-3.8 2.6z" />
                        </g>
                        <g class="arm2">
                            <path d="M34.2 43.6L45 40.3c1.7-.6 3.5.3 4 2 .6 1.7-.3 4-2 4.5l-10.8 2.8c-1.7.6-3.5-.3-4-2-.6-1.6.3-3.4 2-4z" />
                            <path class="wrist2" d="M27.1 56.2L32 45.7c.7-1.6 2.6-2.3 4.2-1.6 1.6.7 2.3 2.6 1.6 4.2L33 58.8c-.7 1.6-2.6 2.3-4.2 1.6-1.7-.7-2.4-2.6-1.7-4.2z" />
                        </g>
                        <g class="leg1">
                            <path d="M52.1 73.2s-7-5.7-7.9-6.5c-.9-.9-1.2-3.5-.1-4.9 1.1-1.4 3.8-1.9 5.2-.9l7.9 7c1.4 1.1 1.7 3.5.7 4.9-1.1 1.4-4.4 1.5-5.8.4z" />
                            <path class="calf1" d="M52.6 84.4l-1-12.8c-.1-1.9 1.5-3.6 3.5-3.7 2-.1 3.7 1.4 3.8 3.4l1 12.8c.1 1.9-1.5 3.6-3.5 3.7-2 0-3.7-1.5-3.8-3.4z" />
                        </g>
                        <g class="leg2">
                            <path d="M37.8 72.7s1.3-10.2 1.6-11.4 2.4-2.8 4.1-2.6c1.7.2 3.6 2.3 3.4 4l-1.8 11.1c-.2 1.7-1.7 3.3-3.4 3.1-1.8-.2-4.1-2.4-3.9-4.2z" />
                            <path class="calf2" d="M29.5 82.3l9.6-10.9c1.3-1.4 3.6-1.5 5.1-.1 1.5 1.4.4 4.9-.9 6.3l-8.5 9.6c-1.3 1.4-3.6 1.5-5.1.1-1.4-1.3-1.5-3.5-.2-5z" />
                        </g>
                    </svg>
                    <svg class="door" viewBox="0 0 100 100">
                        <path d="M93.4 86.3H58.6c-1.9 0-3.4-1.5-3.4-3.4V17.1c0-1.9 1.5-3.4 3.4-3.4h34.8c1.9 0 3.4 1.5 3.4 3.4v65.8c0 1.9-1.5 3.4-3.4 3.4z" />
                        <circle cx="66" cy="50" r="3.7" />
                    </svg>
                    <span class="button-text">Log Out</span>
                </button>
            </div>
        </div>

        <h1 class="mb-4" style="font-size: clamp(1.5rem, 4vw, 1.8rem);">Welcome — <?= htmlspecialchars($associate['name'] ?: '') ?></h1>

        <div class="dashboard">
            <!-- Client Details -->
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="bi bi-building"></i> Client Details</div></div>
                <div class="card-body ">
                    <img src="<?= $logo_url ?>" alt="Client Logo" class="client-photo">
                    <div class="client-info mt-3">
                        <div class="info-row"><div class="info-icon"><i class="bi bi-person-circle"></i></div><div class="info-content"><strong>Company</strong><p><?= htmlspecialchars($client['name']) ?></p></div></div>
                        <div class="info-row"><div class="info-icon"><i class="bi bi-geo-alt"></i></div><div class="info-content"><strong>Address</strong><p><?= nl2br(htmlspecialchars($client['address'])) ?></p></div></div>
                        <div class="info-row"><div class="info-icon"><i class="bi bi-envelope"></i></div><div class="info-content"><strong>Email</strong><p><?= htmlspecialchars($client['email']) ?></p></div></div>
                        <div class="info-row"><div class="info-icon"><i class="bi bi-hash"></i></div><div class="info-content"><strong>Client ID</strong><p>#<?= intval($client['id']) ?></p></div></div>
                    </div>
                </div>
            </div>

            <!-- Update Deliverables -->
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="bi bi-list-task"></i> Update Deliverables</div></div>
                <div class="card-body">
                    <?php if ($no_client): ?>
                        <p class="text-muted mb-0">No client assigned yet.</p>
                    <?php else: ?>
                        <form method="post" class="task-form">
                            <div class=" g-3">
                                <div class="col-12 ">
                                    <label class="form-label">Completed Posts</label>
                                    <input type="number" name="completed_posts" class="form-control" value="<?= $completed['completed_posts'] ?>" min="0" max="<?= $planTotals['total_posts'] ?>">
                                </div>
                                <div class="col-12 ">
                                    <label class="form-label">Completed Reels</label>
                                    <input type="number" name="completed_reels" class="form-control" value="<?= $completed['completed_reels'] ?>" min="0" max="<?= $planTotals['total_reels'] ?>">
                                </div>
                                <div class="col-12  ">
                                    <label class="form-label">Completed Blogs</label>
                                    <input type="number" name="completed_blogs" class="form-control" value="<?= $completed['completed_blogs'] ?>" min="0" max="<?= $planTotals['total_blogs'] ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn-update mt-3">Update Progress</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Progress Stats -->
        <div class="progress-container mt-4 p-3 bg-light rounded">
            <div class="progress-row">
                <div class="stat-card">
                    <h3><?= $planTotals['total_posts'] ?></h3>
                    <p>Posts Planned</p>
                    <p class="small-desc text-muted"><?= htmlspecialchars($desc_posts) ?></p>
                    <p class="mt-2 fw-bold text-dark"><?= $completed['completed_posts'] ?> / <?= $planTotals['total_posts'] ?> completed</p>
                </div>
                <div class="stat-card">
                    <h3><?= $planTotals['total_reels'] ?></h3>
                    <p>Reels Planned</p>
                    <p class="small-desc text-muted"><?= htmlspecialchars($desc_reels) ?></p>
                    <p class="mt-2 fw-bold text-dark"><?= $completed['completed_reels'] ?> / <?= $planTotals['total_reels'] ?> completed</p>
                </div>
                <div class="stat-card">
                    <h3><?= $planTotals['total_blogs'] ?></h3>
                    <p>Blogs Planned</p>
                    <p class="small-desc text-muted"><?= htmlspecialchars($desc_blogs) ?></p>
                    <p class="mt-2 fw-bold text-dark"><?= $completed['completed_blogs'] ?> / <?= $planTotals['total_blogs'] ?> completed</p>
                </div>
                <div class="text-center d-flex flex-column justify-content-center">
                    <div class="progress-circle"><span><?= $displayPercent ?>%</span></div>
                    <div class="mt-2 text-secondary fw-semibold">Overall Progress</div>
                    <span><?= $displayPercent ?>%</span>
                </div>
            </div>
        </div>

        <!-- Daily Progress -->
        <div class="card mt-4">
            <div class="card-header"><div class="card-title"><i class="bi bi-clock-history"></i> Daily Progress Updates</div></div>
            <div class="card-body">
                <p>Recent updates on ongoing tasks</p>
                <div class="progress-list">
                    <?php if ($tasks): foreach ($tasks as $t): ?>
                        <div class="progress-item p-3 border rounded mb-2">
                            <p class="fw-bold mb-1"><?= htmlspecialchars($t['title']) ?></p>
                            <?php if ($t['description']): ?><div class="text-muted small mb-2"><?= nl2br(htmlspecialchars($t['description'])) ?></div><?php endif; ?>
                            <div class="small text-muted">
                                Status: <span class="badge <?= $t['status']==='Completed' ? 'badge-completed' : 'badge-pending' ?>"><?= htmlspecialchars($t['status']) ?></span>
                                &nbsp;|&nbsp; Assigned: <?= htmlspecialchars($t['associate_name'] ?? $associate['name']) ?>
                                &nbsp;|&nbsp; Date: <?= $t['deadline'] ? date('Y-m-d', strtotime($t['deadline'])) : date('Y-m-d', strtotime($t['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="text-muted">No recent task updates available.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Small Stats -->
        <div class="progress-container mt-3 p-3 bg-light rounded">
            <div class="progress-row">
                <div class="stat-card"><h3><?= $totalTasks ?></h3><p>Total Tasks</p></div>
                <div class="stat-card"><h3><?= $inProgress ?></h3><p>In Progress</p></div>
                <div class="stat-card"><h3><?= $overdue ?></h3><p>Overdue</p></div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="script.js"></script>
<script>
// Dynamic Progress Circle (Conic Gradient)
document.addEventListener('DOMContentLoaded', function () {
    const progressCircle = document.querySelector('.progress-circle');
    const percentText = progressCircle.querySelector('span');
    const percent = parseFloat(percentText.textContent);

    if (isNaN(percent)) return;

    // Calculate the angle for conic-gradient
    const angle = (percent / 100) * 360;

    // Apply dynamic gradient
    progressCircle.style.background = `conic-gradient(
        var(--gold) 0deg,
        var(--gold) ${angle}deg,
        #e9ecef ${angle}deg,
        #e9ecef 360deg
    )`;

    // Optional: Add a subtle pulse animation when > 90%
    if (percent >= 90) {
        progressCircle.style.animation = 'pulse 2s infinite';
    }
});

// Optional: Add pulse animation for near-complete
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(193, 154, 44, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(193, 154, 44, 0); }
    }
`;
document.head.appendChild(style);
</script>
</body>
</html>