<?php
session_start();
require 'config.php';
if(!isset($_SESSION['admin_id'])){
    header("Location: index.php");
    exit();
}

// Default filter date = today
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Fetch associates
$associates_res = $conn->query("SELECT * FROM dm_associates ORDER BY name ASC");

// Handle attendance save
if(isset($_POST['mark_attendance'])){
    $associate_id = intval($_POST['associate_id']);
    $status = $_POST['status'];
    $date = $_POST['attendance_date'];

    // Only allow past or today
    if(strtotime($date) > strtotime(date('Y-m-d'))){
        $error = "Cannot mark future dates!";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO associate_attendance (associate_id, attendance_date, status)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE status=VALUES(status)
        ");
        $stmt->bind_param("iss", $associate_id, $date, $status);
        $stmt->execute();
        $success = "Attendance saved successfully!";
    }
}

// Fetch attendance for filtered date
$attendance_res = $conn->query("
    SELECT a.id, a.name, at.status
    FROM dm_associates a
    LEFT JOIN associate_attendance at
    ON a.id = at.associate_id AND at.attendance_date='$filter_date'
    ORDER BY a.name ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Associate Attendance | Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

<h3 class="mb-4">Associate Attendance</h3>

<!-- Filter by Date -->
<form class="row g-2 g-md-3 mb-4 align-items-end" method="GET">
    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <label class="form-label">Date</label>
        <input type="date" name="date" value="<?= $filter_date ?>" class="form-control" max="<?= date('Y-m-d') ?>">
    </div>
    <div class="col-12 col-sm-6 col-md-3 col-lg-2">
        <button class="btn btn-primary w-100">Filter</button>
    </div>
</form>

<?php if(isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= $success ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if(isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= $error ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Attendance Table -->
<div class="table-responsive">
<table class="table table-bordered table-striped bg-white align-middle">
<thead class="table-dark">
<tr>
    <th class="text-center">#</th>
    <th>Associate Name</th>
    <th class="text-center">Status</th>
    <th class="text-center">Action</th>
</tr>
</thead>
<tbody>
<?php $i=1; while($row = $attendance_res->fetch_assoc()): ?>
<tr>
    <td class="text-center"><?= $i++ ?></td>
    <td>
      <a href="associate_attendance_details.php?id=<?= $row['id'] ?>" class="text-primary fw-bold text-decoration-none">
          <?= htmlspecialchars($row['name']) ?>
      </a>
    </td>
    <td class="text-center">
        <span class="badge bg-<?= $row['status']=='Present'?'success':($row['status']=='Absent'?'danger':'secondary') ?>">
            <?= $row['status'] ?? 'Not marked' ?>
        </span>
    </td>
    <td>
        <form method="POST" class="d-flex flex-column flex-sm-row gap-1 gap-sm-2">
            <input type="hidden" name="associate_id" value="<?= $row['id'] ?>">
            <input type="hidden" name="attendance_date" value="<?= $filter_date ?>">
            <select name="status" class="form-select form-select-sm">
                <option value="Present" <?= ($row['status']=='Present')?'selected':'' ?>>Present</option>
                <option value="Absent" <?= ($row['status']=='Absent')?'selected':'' ?>>Absent</option>
            </select>
            <button name="mark_attendance" class="btn btn-success btn-sm w-100 w-sm-auto">Save</button>
        </form>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>