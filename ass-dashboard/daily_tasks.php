<?php
// daily_tasks.php
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
if (!$associate) die("Associate not found.");

// Fetch client
$client = null;
$client_stmt = $conn->prepare("SELECT * FROM clients WHERE assigned_associate_id = ? LIMIT 1");
$client_stmt->bind_param("i", $associate_id);
$client_stmt->execute();
$client_res = $client_stmt->get_result();
if ($client_res->num_rows > 0) {
    $client = $client_res->fetch_assoc();
} else {
    $fallback = $conn->prepare("SELECT assigned_client_id FROM dm_associates WHERE id = ?");
    $fallback->bind_param("i", $associate_id);
    $fallback->execute();
    $fb_res = $fallback->get_result();
    $fb_row = $fb_res->fetch_assoc();
    $cid = intval($fb_row['assigned_client_id'] ?? 0);
    if ($cid > 0) {
        $cstmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
        $cstmt->bind_param("i", $cid);
        $cstmt->execute();
        $cres = $cstmt->get_result();
        if ($cres->num_rows > 0) $client = $cres->fetch_assoc();
    }
}

$no_client = !$client;
$client_id = $client['id'] ?? 0;

// Fetch tasks
$tasks = [];
if (!$no_client) {
    $tstmt = $conn->prepare("
        SELECT t.*, c.name AS client_name 
        FROM tasks t 
        JOIN clients c ON t.client_id = c.id 
        WHERE t.client_id = ? 
        ORDER BY t.deadline desc
    ");
    $tstmt->bind_param("i", $client_id);
    $tstmt->execute();
    $tres = $tstmt->get_result();
    while ($row = $tres->fetch_assoc()) {
        $tasks[] = $row;
    }
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id']) && !$no_client) {
    $task_id = intval($_POST['task_id']);
    $status = $_POST['status'] ?? 'Pending';
    $comment = trim($_POST['comment'] ?? '');
    $report = trim($_POST['report'] ?? '');

    // Map to DB ENUM
    $db_status = ($status === 'Completed') ? 'Completed' : 'Pending';

    $upd = $conn->prepare("UPDATE tasks SET status = ?, comment = ?, report = ? WHERE id = ? AND client_id = ?");
    $upd->bind_param("sssii", $db_status, $comment, $report, $task_id, $client_id);
    $upd->execute();

    header("Location: daily_tasks.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GiggleZen — Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Quicksand:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
  <style>
    /* === ORIGINAL STYLES (UNCHANGED) === */
    .task-container { 
        display: grid; 
        gap: 1.5rem; 
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
    }
    .task-card { 
        border: 1px solid #e2e8f0; 
        border-radius: 12px; 
        overflow: hidden; 
        background: #fff; 
    }
    .task-header { 
        padding: 1rem 1.25rem; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        flex-wrap: wrap; 
        gap: .5rem; 
    }
    .task-header h3 { 
        margin: 0; 
        font-size: 1.1rem; 
        font-weight: 600; 
    }
    .task-header select { 
        font-size: 0.875rem; 
        padding: .35rem .5rem; 
        border-radius: 6px; 
    }
    .task-body { 
        padding: 0 1.25rem 1rem; 
    }
    .task-body p { 
        margin: 0.5rem 0; 
        font-size: 0.9rem; 
    }
    .task-action { 
        padding: 0 1.25rem 1rem; 
        display: flex; 
        flex-direction: column; 
        gap: .5rem; 
    }
    .task-action textarea, 
    .task-action input { 
        width: 100%; 
        padding: .5rem; 
        border: 1px solid #d1d5db; 
        border-radius: 6px; 
        font-size: 0.875rem; 
    }
    .task-action button { 
        background: #c19a2c; 
        color: white; 
        border: none; 
        padding: .75rem; 
        border-radius: 8px; 
        font-weight: 600; 
        cursor: pointer; 
    }
    .task-action button:hover { 
        background: #a47d23; 
    }

    /* Status Backgrounds — EXACTLY LIKE YOUR HTML */
    .inprogress .task-header { background: #fef3c7; }
    .completed .task-header { background: #d1fae5; }
    .pending .task-header { background: #db7d7dff; }
    .problem .task-header { background: #fce7f3; }

    h1 { font-size: 1.6rem; margin-bottom: 1.5rem; }

    /* === RESPONSIVE FIXES ONLY === */
    @media (max-width: 992px) {
        .task-container {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        .task-header {
            flex-direction: column;
            align-items: stretch;
            text-align: left;
        }
        .task-header h3 {
            font-size: 1rem;
            word-break: break-word;
        }
        .task-header select {
            width: 100%;
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }
        .task-body p {
            font-size: 0.85rem;
        }
    }

    @media (max-width: 576px) {
        .task-header h3 {
            font-size: 0.95rem;
        }
        .task-body p {
            font-size: 0.8rem;
            margin: 0.4rem 0;
        }
        .task-action textarea,
        .task-action input {
            font-size: 0.8rem;
            padding: 0.45rem;
        }
        .task-action button {
            padding: 0.65rem;
            font-size: 0.9rem;
        }
        h1 {
            font-size: 1.4rem;
        }
       
    }
    @media (max-width: 480px) {
            .topbar{
                /* flex-direction: column; */
             
                
            }
            
        }

    /* Prevent horizontal scroll */
    * { box-sizing: border-box; }
    body { overflow-x: hidden; }
</style>
</head>
<body style="margin-right: 5px;">
    <div class="app">
        <aside class="sidebar">
            <ul class="nav-tabs">
                <li><a href="dashboard.php"><i class="bi bi-house-door-fill"></i><span class="txt">Home</span></a></li>
                <li><a href="daily_tasks.php" class="active"><i class="bi bi-box-seam"></i><span class="txt">Daily Tasks</span></a></li>
                <li><a href="bills.php"><i class="bi bi-person-badge"></i><span class="txt">Bill Upload</span></a></li>
                <li><a href="attendance.php"><i class="bi bi-person-badge"></i><span class="txt">Attendance</span></a></li>
            </ul>
        </aside>

        <button class="sidebar-toggle" aria-label="Toggle sidebar">
            <i class="bi bi-box-arrow-in-right"></i>
        </button>

        <main class="main">
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

            <h1 class="mb-3" style="font-size:1.6rem">Welcome to GiggleZen...</h1>
            <h1>Daily Tasks</h1>

            <div class="task-container">
                <?php if ($no_client): ?>
                    <p>No client assigned.</p>
                <?php elseif (empty($tasks)): ?>
                    <p>No tasks available.</p>
                <?php else: ?>
                    <?php foreach ($tasks as $t): 
                        $status = $t['status'] ?? 'Pending';
                        $class = 'pending';
                        if ($status === 'Completed') $class = 'completed';
                        elseif ($status === 'Have Problem') $class = 'problem';
                        elseif (!empty($t['comment']) || !empty($t['report'])) $class = 'inprogress';
                    ?>
                        <div class="task-card <?= $class ?>">
                            <form method="post">
                                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                <div class="task-header">
                                    <h3><?= htmlspecialchars($t['title']) ?></h3>
                                    <select name="status" onchange="this.form.submit()">
                                        <!-- <option value="In Progress" <?= $class === 'inprogress' ? 'selected' : '' ?>>In Progress</option> -->
                                        <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="Pending" <?= $status === 'Pending' && empty($t['comment']) ? 'selected' : '' ?>>Pending</option>
                                        <!-- <option value="Have Problem" <?= $status === 'Have Problem' ? 'selected' : '' ?>>Have Problem</option> -->
                                    </select>
                                </div>
                                <div class="task-body">
                                    <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($t['description'] ?? '—')) ?></p>
                                    <p><strong>Deadline:</strong> <?= $t['deadline'] ? date('Y-m-d', strtotime($t['deadline'])) : '—' ?></p>
                                    <p><strong>Priority:</strong> <?= htmlspecialchars($t['priority'] ?? '—') ?></p>
                                    <p><strong>Client:</strong> <?= htmlspecialchars($t['client_name'] ?? '—') ?></p>
                                </div>
                                <div class="task-action">
                                    <textarea name="comment" placeholder="Add comment..."><?= htmlspecialchars($t['comment'] ?? '') ?></textarea>
                                    <input type="url" name="report" placeholder="Google Sheet Link..." value="<?= htmlspecialchars($t['report'] ?? '') ?>">
                                    <button type="submit">Update</button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="script.js"></script>
</body>
</html>