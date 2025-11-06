<?php
session_start();
require 'config.php';
if (!isset($_SESSION['admin_id'])) { header("Location: index.php"); exit(); }

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
if ($client_id <= 0) die("Invalid client ID!");

// Fetch client with plan (plan totals included)
$client_res = $conn->query("
    SELECT c.*, p.plan_name, p.total_posts, p.total_reels, p.total_blogs, p.id AS plan_id
    FROM clients c
    LEFT JOIN plans p ON c.plan_id=p.id
    WHERE c.id=$client_id
");
if (!$client_res || $client_res->num_rows == 0) die("Client not found!");
$client = $client_res->fetch_assoc();

// Plan totals (use 0 if null)
$total_posts = max(0, intval($client['total_posts']));
$total_reels = max(0, intval($client['total_reels']));
$total_blogs = max(0, intval($client['total_blogs']));
$plan_id = intval($client['plan_id']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_progress'])) {
    $completed_posts = max(0, intval($_POST['completed_posts']));
    $completed_reels = max(0, intval($_POST['completed_reels']));
    $completed_blogs = max(0, intval($_POST['completed_blogs']));

    // Clamp completed values to the totals to avoid >100% issues
    if ($total_posts > 0) $completed_posts = min($completed_posts, $total_posts);
    if ($total_reels > 0) $completed_reels = min($completed_reels, $total_reels);
    if ($total_blogs > 0) $completed_blogs = min($completed_blogs, $total_blogs);

    // Calculate individual percents (0..1)
    $percent_posts = ($total_posts > 0) ? ($completed_posts / $total_posts) : 0;
    $percent_reels = ($total_reels > 0) ? ($completed_reels / $total_reels) : 0;
    $percent_blogs = ($total_blogs > 0) ? ($completed_blogs / $total_blogs) : 0;

    // Average percent across available categories (ignore zero-total categories from average)
    $parts = [];
    if ($total_posts > 0) $parts[] = $percent_posts;
    if ($total_reels > 0) $parts[] = $percent_reels;
    if ($total_blogs > 0) $parts[] = $percent_blogs;

    if (count($parts) > 0) {
        $total_percent_done = round((array_sum($parts) / count($parts)) * 100, 2);
    } else {
        $total_percent_done = 0.00;
    }

    // Insert or update client_analysis (protect against SQL injection by prepared statements)
    $check = $conn->query("SELECT id FROM client_analysis WHERE client_id=$client_id AND plan_id=$plan_id");
    if ($check && $check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE client_analysis 
                                SET completed_posts=?, completed_reels=?, completed_blogs=?, total_percent_done=?, last_updated=NOW()
                                WHERE client_id=? AND plan_id=?");
        $stmt->bind_param("iiidii", $completed_posts, $completed_reels, $completed_blogs, $total_percent_done, $client_id, $plan_id);
        if ($stmt === false) {
            $completed_posts = intval($completed_posts);
            $completed_reels = intval($completed_reels);
            $completed_blogs = intval($completed_blogs);
            $tp = floatval($total_percent_done);
            $conn->query("UPDATE client_analysis SET completed_posts=$completed_posts, completed_reels=$completed_reels, completed_blogs=$completed_blogs, total_percent_done=$tp, last_updated=NOW() WHERE client_id=$client_id AND plan_id=$plan_id");
        } else {
            $stmt->execute();
            $stmt->close();
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO client_analysis (client_id, plan_id, total_posts, completed_posts, total_reels, completed_reels, total_blogs, completed_blogs, total_percent_done)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iiiiiiiii", $client_id, $plan_id, $total_posts, $completed_posts, $total_reels, $completed_reels, $total_blogs, $completed_blogs, $total_percent_done);
            $ok = $stmt->execute();
            if (!$ok) {
                $tp = floatval($total_percent_done);
                $conn->query("INSERT INTO client_analysis (client_id, plan_id, total_posts, completed_posts, total_reels, completed_reels, total_blogs, completed_blogs, total_percent_done)
                              VALUES ($client_id, $plan_id, $total_posts, $completed_posts, $total_reels, $completed_reels, $total_blogs, $completed_blogs, $tp)");
            }
            $stmt->close();
        } else {
            $tp = floatval($total_percent_done);
            $conn->query("INSERT INTO client_analysis (client_id, plan_id, total_posts, completed_posts, total_reels, completed_reels, total_blogs, completed_blogs, total_percent_done)
                          VALUES ($client_id, $plan_id, $total_posts, $completed_posts, $total_reels, $completed_reels, $total_blogs, $completed_blogs, $tp)");
        }
    }

    header("Location: client_analysis.php?client_id=$client_id");
    exit();
}

// Fetch analysis row (and ensure defaults exist)
$analysis_res = $conn->query("SELECT * FROM client_analysis WHERE client_id=$client_id AND plan_id=$plan_id");
if ($analysis_res && $analysis_res->num_rows > 0) {
    $analysis = $analysis_res->fetch_assoc();
    $analysis['completed_posts'] = isset($analysis['completed_posts']) ? intval($analysis['completed_posts']) : 0;
    $analysis['completed_reels'] = isset($analysis['completed_reels']) ? intval($analysis['completed_reels']) : 0;
    $analysis['completed_blogs'] = isset($analysis['completed_blogs']) ? intval($analysis['completed_blogs']) : 0;
    if (isset($analysis['total_percent_done'])) {
        $analysis['total_percent_done'] = floatval($analysis['total_percent_done']);
    } elseif (isset($analysis['total_percent'])) {
        $analysis['total_percent_done'] = floatval($analysis['total_percent']);
    } else {
        $analysis['total_percent_done'] = 0.00;
    }
} else {
    $analysis = [
        'completed_posts' => 0,
        'completed_reels' => 0,
        'completed_blogs' => 0,
        'total_percent_done' => 0.00
    ];
}

// Safe numeric strings for JS rendering
$completed_posts_js = intval($analysis['completed_posts']);
$completed_reels_js = intval($analysis['completed_reels']);
$completed_blogs_js = intval($analysis['completed_blogs']);
$total_percent_done_js = number_format(floatval($analysis['total_percent_done']), 2, '.', '');

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Client Analysis | Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
    <!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container-fluid px-4">
    <h4 class="navbar-brand mb-0 fw-bold text-primary">Admin Panel</h4>
    <div class="ms-auto d-flex align-items-center gap-2">
      <a href="admin_dashboard.php" class="btn btn-outline-primary btn-sm">
        Dashboard
      </a>
      <a href="logout.php" class="btn btn-danger btn-sm">
        Logout
      </a>
    </div>
  </div>
</nav>

<div class="container mt-4">
    <a href="analysis_overview.php" class="btn btn-secondary mb-3">Back to Analysis Overview</a>
    <h3 class="mb-4">Client Analysis: <?= htmlspecialchars($client['name']) ?> (<?= htmlspecialchars($client['plan_name']) ?>)</h3>

    <form method="POST" class="mb-5">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label">Completed Posts (<?= $total_posts ?>)</label>
                <input type="number" name="completed_posts" class="form-control" min="0" max="<?= $total_posts ?>" value="<?= $analysis['completed_posts'] ?>" required>
                <small class="text-muted d-block"><?= $analysis['completed_posts'] ?> / <?= $total_posts ?></small>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Completed Reels (<?= $total_reels ?>)</label>
                <input type="number" name="completed_reels" class="form-control" min="0" max="<?= $total_reels ?>" value="<?= $analysis['completed_reels'] ?>" required>
                <small class="text-muted d-block"><?= $analysis['completed_reels'] ?> / <?= $total_reels ?></small>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">Completed Blogs (<?= $total_blogs ?>)</label>
                <input type="number" name="completed_blogs" class="form-control" min="0" max="<?= $total_blogs ?>" value="<?= $analysis['completed_blogs'] ?>" required>
                <small class="text-muted d-block"><?= $analysis['completed_blogs'] ?> / <?= $total_blogs ?></small>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" name="update_progress" class="btn btn-primary w-100 w-md-auto">Update Progress</button>
        </div>
    </form>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-4 text-center">
        <div class="col">
            <h6 class="mb-3">Posts</h6>
            <canvas id="postsChart" height="150"></canvas>
        </div>
        <div class="col">
            <h6 class="mb-3">Reels</h6>
            <canvas id="reelsChart" height="150"></canvas>
        </div>
        <div class="col">
            <h6 class="mb-3">Blogs</h6>
            <canvas id="blogsChart" height="150"></canvas>
        </div>
        <div class="col">
            <h6 class="mb-3">Total Progress</h6>
            <canvas id="totalChart" height="150"></canvas>
            <p class="mt-2 fw-bold text-success fs-4"><?= number_format(floatval($analysis['total_percent_done']), 2) ?>%</p>
        </div>
    </div>
</div>

<script>
function renderChart(id, completed, total, color) {
    var ctx = document.getElementById(id).getContext('2d');
    var remaining = Math.max(total - completed, 0);
    if (total <= 0) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['N/A'],
                datasets: [{ data: [1], backgroundColor: ['#e0e0e0'] }]
            },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });
        return;
    }
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'Remaining'],
            datasets: [{ data: [completed, remaining], backgroundColor: [color, '#e0e0e0'] }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });
}

renderChart('postsChart', <?= $completed_posts_js ?>, <?= $total_posts ?>, '#4caf50');
renderChart('reelsChart', <?= $completed_reels_js ?>, <?= $total_reels ?>, '#2196f3');
renderChart('blogsChart', <?= $completed_blogs_js ?>, <?= $total_blogs ?>, '#ff9800');

(function(){
    var ctx = document.getElementById('totalChart').getContext('2d');
    var percent = parseFloat("<?= $total_percent_done_js ?>") || 0;
    var remaining = Math.max(100 - percent, 0);
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Completed','Remaining'],
            datasets: [{ data: [percent, remaining], backgroundColor: ['#9c27b0', '#e0e0e0'] }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });
})();
</script>
</body>
</html>