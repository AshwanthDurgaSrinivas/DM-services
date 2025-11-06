<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$associate_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch associate name
$stmt = $conn->prepare("SELECT name FROM dm_associates WHERE id=?");
$stmt->bind_param("i", $associate_id);
$stmt->execute();
$associate = $stmt->get_result()->fetch_assoc();

if (!$associate) {
    die("Invalid associate.");
}

// Filters
$month = $_GET['month'] ?? date('Y-m');
list($year, $month_num) = explode('-', $month);

// === SUMMARY FROM associate_attendance ===
$summary_query = "
    SELECT 
        COUNT(*) AS total_days,
        SUM(status = 'Present') AS present,
        SUM(status = 'Absent') AS absent
    FROM associate_attendance
    WHERE associate_id = ? 
      AND YEAR(attendance_date) = ? 
      AND MONTH(attendance_date) = ?
";
$stmt = $conn->prepare($summary_query);
$stmt->bind_param("iii", $associate_id, $year, $month_num);
$stmt->execute();
$sum = $stmt->get_result()->fetch_assoc();

$present = $sum['present'] ?? 0;
$absent  = $sum['absent'] ?? 0;
$total_days = $sum['total_days'];
$percent_present = $total_days ? round(($present / $total_days) * 100, 2) : 0;

// === DETAILED LOGIN/LOGOUT FROM attendance ===
$query = "
    SELECT 
        DATE(check_in_time) as attendance_date,
        check_in_time,
        check_out_time,
        location_area,
        TIMESTAMPDIFF(MINUTE, check_in_time, check_out_time) as duration_minutes
    FROM attendance
    WHERE associate_id = ? 
      AND YEAR(check_in_time) = ? 
      AND MONTH(check_in_time) = ?
    ORDER BY check_in_time ASC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $associate_id, $year, $month_num);
$stmt->execute();
$res = $stmt->get_result();

$attendance_data = [];
while ($row = $res->fetch_assoc()) {
    $duration = $row['duration_minutes'] ?? 0;
    $row['duration'] = $duration > 0 ? round($duration / 60, 2) . ' hrs' : '—';
    $row['check_in'] = $row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '—';
    $row['check_out'] = $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '—';
    $attendance_data[] = $row;
}

// === 6-MONTH TREND FROM associate_attendance ===
$trend_query = "
    SELECT 
        DATE_FORMAT(attendance_date, '%Y-%m') AS month,
        COUNT(*) AS total_days,
        SUM(status = 'Present') AS present_days
    FROM associate_attendance
    WHERE associate_id = ?
    GROUP BY month
    ORDER BY month DESC
    LIMIT 6
";
$stmt = $conn->prepare($trend_query);
$stmt->bind_param("i", $associate_id);
$stmt->execute();
$trend_res = $stmt->get_result();

$months = $percentages = [];
while ($row = $trend_res->fetch_assoc()) {
    $months[] = date('M Y', strtotime($row['month'] . '-01'));
    $percent = $row['total_days'] ? round(($row['present_days'] / $row['total_days']) * 100, 2) : 0;
    $percentages[] = $percent;
}
$months = array_reverse($months);
$percentages = array_reverse($percentages);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Details - <?= htmlspecialchars($associate['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary: #d2c072; --accent: #faebcb; }
        .chart-container { max-width: 300px; margin: 0 auto; }
        .badge-present { background:#d1fae5; color:#065f46; }
        .badge-absent  { background:#fee2e2; color:#991b1b; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold text-primary" href="admin_dashboard.php">Admin Panel</a>
        <div class="ms-auto d-flex gap-2">
            <a href="admin_attendance.php" class="btn btn-outline-primary btn-sm">All Associates</a>
            <a href="admin_logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <a href="attendance.php" class="btn btn-secondary btn-sm mb-3">Back to List</a>
    <h3>Attendance Details - <?= htmlspecialchars($associate['name']) ?></h3>

    <form method="GET" class="row g-2 mb-4 align-items-end">
        <input type="hidden" name="id" value="<?= $associate_id ?>">
        <div class="col-auto">
            <label class="form-label">Month</label>
            <input type="month" name="month" class="form-control" value="<?= $month ?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-primary">Filter</button>
        </div>
    </form>

    <!-- SUMMARY CARDS (from associate_attendance) -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-center p-3 h-100">
                <h6>Present</h6>
                <h3 class="text-success"><?= $present ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center p-3 h-100">
                <h6>Absent</h6>
                <h3 class="text-danger"><?= $absent ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center p-3 h-100">
                <h6>Attendance %</h6>
                <h3 class="text-primary"><?= $percent_present ?>%</h3>
            </div>
        </div>
    </div>

    <!-- Pie Chart -->
    <div class="card p-3 mb-4">
        <div class="chart-container">
            <canvas id="pieChart"></canvas>
        </div>
    </div>
<!-- Detailed Table (from attendance) -->
    <div class="card mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Check-In</th>
                            <th>Check-Out</th>
                            <th>Duration</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attendance_data)): ?>
                            <tr><td colspan="5" class="text-center py-4">No records found.</td></tr>
                        <?php else: foreach ($attendance_data as $row): ?>
                            <tr>
                                <td><?= date('d M Y', strtotime($row['attendance_date'])) ?></td>
                                <td><?= $row['check_in'] ?></td>
                                <td><?= $row['check_out'] ?></td>
                                <td><?= $row['duration'] ?></td>
                                <td class="small"><?= htmlspecialchars($row['location_area'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Trend Chart -->
    <?php if (!empty($months)): ?>
    <div class="card p-3 mb-4">
        <h5 class="text-center mb-3">6-Month Attendance Trend</h5>
        <canvas id="trendChart"></canvas>
    </div>
    <?php endif; ?>

    
</div>

<script>
// Pie Chart
new Chart(document.getElementById('pieChart'), {
    type: 'doughnut',
    data: {
        labels: ['Present', 'Absent'],
        datasets: [{
            data: [<?= $present ?>, <?= $absent ?>],
            backgroundColor: ['#28a745', '#dc3545']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' },
            title: { display: true, text: '<?= $percent_present ?>% Present', font: { size: 16 } }
        }
    }
});

// Trend Chart
<?php if (!empty($months)): ?>
new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($months) ?>,
        datasets: [{
            label: 'Attendance %',
            data: <?= json_encode($percentages) ?>,
            backgroundColor: '#d2c072'
        }]
    },
    options: {
        responsive: true,
        scales: { y: { beginAtZero: true, max: 100 } },
        plugins: { legend: { display: false } }
    }
});
<?php endif; ?>
</script>

</body>
</html>