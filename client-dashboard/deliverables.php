<?php
session_start();
require 'config.php'; // adjust path if needed

if (!isset($_SESSION['client_id'])) {
    header("Location: login.php");
    exit();
}

$client_id = $_SESSION['client_id'];

// Fetch client details (for logo/name)
$clientRes = $conn->query("SELECT name, logo FROM clients WHERE id = $client_id");
$client = $clientRes->fetch_assoc();

// Fetch assigned associate
$associateRes = $conn->query("SELECT name FROM dm_associates WHERE id = (SELECT assigned_associate_id FROM clients WHERE id=$client_id)");
$associate = $associateRes && $associateRes->num_rows > 0 ? $associateRes->fetch_assoc()['name'] : 'No Manager Assigned';

// Fetch plan deliverables (posts, reels, blogs) and progress
$analysis = $conn->query("SELECT total_posts, completed_posts, total_reels, completed_reels, total_blogs, completed_blogs, total_percent_done 
FROM client_analysis WHERE client_id = $client_id")->fetch_assoc();

$total_posts = $analysis['total_posts'] ?? 0;
$completed_posts = $analysis['completed_posts'] ?? 0;
$total_reels = $analysis['total_reels'] ?? 0;
$completed_reels = $analysis['completed_reels'] ?? 0;
$total_blogs = $analysis['total_blogs'] ?? 0;
$completed_blogs = $analysis['completed_blogs'] ?? 0;
$total_percent_done = $analysis['total_percent_done'] ?? 0;

// Fetch latest tasks
$tasks = $conn->query("SELECT title, description, status, deadline, created_at FROM tasks WHERE client_id=$client_id ORDER BY created_at DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Deliverables | GiggleZen Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">

    <style>
        .ring-chart-container {
            position: relative;
            width: 180px;
            height: 180px;
            margin: 20px auto;
        }

        .ring-chart-container canvas {
            width: 100% !important;
            height: 100% !important;
        }

        .ring-chart-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }

        .project-card p {
            min-height: 60px;
            color: #555;
            font-size: 15px;
        }

        .progress-section .card {
            text-align: center;
        }

        .daily-progress-card {
    background: #f9fafc;
    border-radius: 12px;
    padding: 20px;
    height: 380px; /* Fixed height for card */
    display: flex;
    flex-direction: column;
}

.daily-progress-card h3 {
    font-weight: 700;
    color: #222;
}

.progress-list {
    flex: 1;
    overflow-y: auto;
    padding-right: 5px;
}

/* Hide scrollbar but keep scrollable */
.progress-list::-webkit-scrollbar {
    width: 0;
    background: transparent;
}
body::-webkit-scrollbar {
    width: 0;
    background: transparent;
}
.progress-list {
    -ms-overflow-style: none;  /* IE and Edge */
    scrollbar-width: none;     /* Firefox */
}

.progress-item:hover {
    background: #f8f9fa;
    transform: translateY(-2px);
    transition: all 0.2s ease;
}
    .sidebar-toggle {
    position: fixed;             
    top: 50%;                    
    z-index: 3;       
    transform: translateY(-50%); 
              
    width: 40px;
    height: 40px;
    background: #ebebeb;
    border-radius: 10%;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    border: none;
    cursor: pointer;
    transition: background 0.3s, left 0.3s;
}@media (max-width: 768px) {
    .sidebar.active+.sidebar-toggle {
        left: 240px;
    }
}

    </style>
    <script>
 const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 992 && sidebar.classList.contains('active') && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
    }</script>
</head>

<body>
<div class="app">
    <aside class="sidebar">
        <ul class="nav-tabs">
            <li><a href="dashboard.php"><i class="bi bi-house-door-fill"></i><span class="txt">Overview</span></a></li>
            <li><a href="deliverables.php" class="active"><i class="bi bi-box-seam"></i><span class="txt">Deliverables</span></a></li>
            <li><a href="manager.php"><i class="bi bi-person-badge"></i><span class="txt">Manager Details</span></a></li>
        </ul>
    </aside>
  <button class="sidebar-toggle" aria-label="Toggle sidebar">
                   <i class="bi bi-box-arrow-in-right"></i>
                </button>
    <main class="main">
         <div class="topbar">
               
               <?php
// Assuming you already have $client_id from session
$client_q = $conn->query("SELECT name, logo FROM clients WHERE id = $client_id");
$client_data = $client_q->fetch_assoc();

$client_name = $client_data['name'] ?? 'Client';
$client_logo = !empty($client_data['logo']) ? '../dm_admin/uploads/clients/' . htmlspecialchars($client_data['logo']) : 'assets/default-logo.png';
?>
<div class="brand">
    <div class="logo">
        <img src="<?= $client_logo ?>" alt="Client Logo" style="width:45px; height:45px; object-fit:cover; border-radius:50%;">
    </div>
    <div>
        <h3><?= htmlspecialchars($client_name) ?></h3>
        <small>Client Dashboard</small>
    </div>
</div>

               <button id="logoutBtn" class="logoutButton">
                    <svg class="doorway" viewBox="0 0 100 100">
                        <path
                            d="M93.4 86.3H58.6c-1.9 0-3.4-1.5-3.4-3.4V17.1c0-1.9 1.5-3.4 3.4-3.4h34.8c1.9 0 3.4 1.5 3.4 3.4v65.8c0 1.9-1.5 3.4-3.4 3.4z" />
                        <path class="bang"
                            d="M40.5 43.7L26.6 31.4l-2.5 6.7zM41.9 50.4l-19.5-4-1.4 6.3zM40 57.4l-17.7 3.9 3.9 5.7z" />
                    </svg>
                    <svg class="figure" viewBox="0 0 100 100">
                        <circle cx="52.1" cy="32.4" r="6.4" />
                        <path
                            d="M50.7 62.8c-1.2 2.5-3.6 5-7.2 4-3.2-.9-4.9-3.5-4-7.8.7-3.4 3.1-13.8 4.1-15.8 1.7-3.4 1.6-4.6 7-3.7 4.3.7 4.6 2.5 4.3 5.4-.4 3.7-2.8 15.1-4.2 17.9z" />
                        <g class="arm1">
                            <path
                                d="M55.5 56.5l-6-9.5c-1-1.5-.6-3.5.9-4.4 1.5-1 3.7-1.1 4.6.4l6.1 10c1 1.5.3 3.5-1.1 4.4-1.5.9-3.5.5-4.5-.9z" />
                            <path class="wrist1"
                                d="M69.4 59.9L58.1 58c-1.7-.3-2.9-1.9-2.6-3.7.3-1.7 1.9-2.9 3.7-2.6l11.4 1.9c1.7.3 2.9 1.9 2.6 3.7-.4 1.7-2 2.9-3.8 2.6z" />
                        </g>
                        <g class="arm2">
                            <path
                                d="M34.2 43.6L45 40.3c1.7-.6 3.5.3 4 2 .6 1.7-.3 4-2 4.5l-10.8 2.8c-1.7.6-3.5-.3-4-2-.6-1.6.3-3.4 2-4z" />
                            <path class="wrist2"
                                d="M27.1 56.2L32 45.7c.7-1.6 2.6-2.3 4.2-1.6 1.6.7 2.3 2.6 1.6 4.2L33 58.8c-.7 1.6-2.6 2.3-4.2 1.6-1.7-.7-2.4-2.6-1.7-4.2z" />
                        </g>
                        <g class="leg1">
                            <path
                                d="M52.1 73.2s-7-5.7-7.9-6.5c-.9-.9-1.2-3.5-.1-4.9 1.1-1.4 3.8-1.9 5.2-.9l7.9 7c1.4 1.1 1.7 3.5.7 4.9-1.1 1.4-4.4 1.5-5.8.4z" />
                            <path class="calf1"
                                d="M52.6 84.4l-1-12.8c-.1-1.9 1.5-3.6 3.5-3.7 2-.1 3.7 1.4 3.8 3.4l1 12.8c.1 1.9-1.5 3.6-3.5 3.7-2 0-3.7-1.5-3.8-3.4z" />
                        </g>
                        <g class="leg2">
                            <path
                                d="M37.8 72.7s1.3-10.2 1.6-11.4 2.4-2.8 4.1-2.6c1.7.2 3.6 2.3 3.4 4l-1.8 11.1c-.2 1.7-1.7 3.3-3.4 3.1-1.8-.2-4.1-2.4-3.9-4.2z" />
                            <path class="calf2"
                                d="M29.5 82.3l9.6-10.9c1.3-1.4 3.6-1.5 5.1-.1 1.5 1.4.4 4.9-.9 6.3l-8.5 9.6c-1.3 1.4-3.6 1.5-5.1.1-1.4-1.3-1.5-3.5-.2-5z" />
                        </g>
                    </svg>
                    <svg class="door" viewBox="0 0 100 100">
                        <path
                            d="M93.4 86.3H58.6c-1.9 0-3.4-1.5-3.4-3.4V17.1c0-1.9 1.5-3.4 3.4-3.4h34.8c1.9 0 3.4 1.5 3.4 3.4v65.8c0 1.9-1.5 3.4-3.4 3.4z" />
                        <circle cx="66" cy="50" r="3.7" />
                    </svg>
                    <span class="button-text">Log Out</span>
                </button>
            </div>


        <div class="header">
            <h1>Deliverables</h1>
        </div>

        <section class="card-grid" role="grid" aria-label="Projects">
            <div class="card project-card">
                <h3>Social Media Posts</h3>
                <p>Creative posts designed and scheduled to boost your brand visibility across platforms.</p>
                <div class="progress-container">
                    <div class="progress-bar" style="width: <?= ($total_posts > 0) ? round(($completed_posts / $total_posts) * 100) : 0; ?>%;"></div>
                </div>
                <div class="progress-text">
                    <?= $completed_posts ?>/<?= $total_posts ?> Posts Completed
                </div>
            </div>

            <div class="card project-card">
                <h3>Reels & Video Content</h3>
                <p>Engaging short-form videos crafted to enhance audience engagement and reach.</p>
                <div class="progress-container">
                    <div class="progress-bar" style="width: <?= ($total_reels > 0) ? round(($completed_reels / $total_reels) * 100) : 0; ?>%;"></div>
                </div>
                <div class="progress-text">
                    <?= $completed_reels ?>/<?= $total_reels ?> Reels Completed
                </div>
            </div>

            <div class="card project-card">
                <h3>Blogs & Articles</h3>
                <p>SEO-optimized blog posts to improve website traffic and establish thought leadership.</p>
                <div class="progress-container">
                    <div class="progress-bar" style="width: <?= ($total_blogs > 0) ? round(($completed_blogs / $total_blogs) * 100) : 0; ?>%;"></div>
                </div>
                <div class="progress-text">
                    <?= $completed_blogs ?>/<?= $total_blogs ?> Blogs Completed
                </div>
            </div>
        </section>

        <section class="progress-section">
            <div class="card ring-chart-card">
                <h3>Total Deliverables Completed</h3>
                <p>Overall progress across all assigned deliverables</p>
                <div class="ring-chart-container">
                    <canvas id="taskRingChart"></canvas>
                    <div class="ring-chart-center"><?= round($total_percent_done) ?>%</div>
                </div>
            </div>

          <div class="card daily-progress-card">
    <h3>Daily Progress Updates</h3>
    <p class="text-muted mb-3">Recent updates on ongoing tasks and deliverables</p>

    <!-- Scrollable List -->
    <div class="progress-list">
        <?php if ($tasks->num_rows > 0): ?>
            <?php while ($task = $tasks->fetch_assoc()): ?>
                <div class="progress-item border rounded p-3 mb-3 shadow-sm" style="background:#fff;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0" style="color:#333;"><?= htmlspecialchars($task['title']); ?></h5>
                        <?php
                            $status = strtolower($task['status']);
                            $badgeClass = 'secondary';
                            if ($status === 'completed') $badgeClass = 'success';
                            elseif ($status === 'in progress') $badgeClass = 'warning';
                            elseif ($status === 'pending') $badgeClass = 'danger';
                        ?>
                        <span class="badge bg-<?= $badgeClass ?> text-uppercase"><?= htmlspecialchars($task['status']); ?></span>
                    </div>
                    <p class="mb-2 text-muted" style="font-size:15px;">
                        <?= nl2br(htmlspecialchars(strlen($task['description']) > 120 ? substr($task['description'], 0, 120) . '...' : $task['description'])); ?>
                    </p>
                    <div class="details text-end text-muted" style="font-size:14px;">
                        <i class="bi bi-calendar-event me-1"></i> <?= htmlspecialchars($task['deadline']); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center py-4 text-muted">
                <i class="bi bi-clipboard-x fs-2 mb-2"></i>
                <p>No recent task updates available.</p>
            </div>
        <?php endif; ?>
    </div>
</div>


        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="script.js"></script>
<script>
const ctx = document.getElementById('taskRingChart');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Completed', 'Remaining'],
        datasets: [{
            data: [<?= $total_percent_done ?>, <?= 100 - $total_percent_done ?>],
            backgroundColor: ['#4CAF50', '#E0E0E0'],
            borderWidth: 0
        }]
    },
    options: {
        cutout: '75%',
        plugins: { legend: { display: false } },
        responsive: true,
        maintainAspectRatio: false
    }
});
</script>
</body>
</html>
